<?php
/**
 * PandaScrow Webhook Handler
 * Receives and processes webhook events from PandaScrow
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/transaction_logger.php';

// Log all incoming requests
$rawInput = file_get_contents('php://input');
$headers = getallheaders();
$method = $_SERVER['REQUEST_METHOD'];

// Log webhook received
log_transaction_event('webhook_received_raw', [
    'method' => $method,
    'headers' => $headers,
    'raw_body' => $rawInput,
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
], 'info');

// Only accept POST requests
if ($method !== 'POST') {
    log_transaction_event('webhook_invalid_method', ['method' => $method], 'warning');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Parse JSON payload
$payload = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    log_transaction_event('webhook_invalid_json', [
        'error' => json_last_error_msg(),
        'raw' => $rawInput
    ], 'error');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Extract event data
$event = $payload['event'] ?? null;
$escrowId = $payload['escrow_id'] ?? $payload['data']['escrow_id'] ?? null;
$status = $payload['status'] ?? $payload['data']['status'] ?? null;
$transactionRef = $payload['transaction_ref'] ?? $payload['data']['transaction_ref'] ?? null;

// Log parsed webhook
log_webhook_received($event, $escrowId, $status, $payload);

if (!$escrowId) {
    log_transaction_event('webhook_missing_escrow_id', ['payload' => $payload], 'error');
    http_response_code(400);
    echo json_encode(['error' => 'Missing escrow_id']);
    exit;
}

try {
    $pdo = db();
    
    // Find transaction by escrow ID
    $stmt = $pdo->prepare("
        SELECT t.*, l.name AS listing_name, 
               b.email AS buyer_email, b.name AS buyer_name,
               s.email AS seller_email, s.name AS seller_name
        FROM transactions t
        LEFT JOIN listings l ON t.listing_id = l.id
        LEFT JOIN users b ON t.buyer_id = b.id
        LEFT JOIN users s ON t.seller_id = s.id
        WHERE t.pandascrow_escrow_id = ?
    ");
    $stmt->execute([$escrowId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        log_transaction_event('webhook_transaction_not_found', [
            'escrow_id' => $escrowId,
            'event' => $event
        ], 'warning');
        
        // Still return 200 to prevent retries
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Transaction not found']);
        exit;
    }
    
    $transactionId = $transaction['id'];
    
    // Process different webhook events
    switch ($event) {
        case 'escrow.created':
        case 'escrow.initiated':
            // Escrow created successfully
            log_transaction_event('webhook_escrow_created', [
                'transaction_id' => $transactionId,
                'escrow_id' => $escrowId
            ], 'info');
            
            $pdo->prepare("UPDATE transactions SET status = 'pending' WHERE id = ?")
                ->execute([$transactionId]);
            break;
            
        case 'escrow.paid':
        case 'payment.received':
            // Buyer has paid
            log_transaction_event('webhook_payment_received', [
                'transaction_id' => $transactionId,
                'escrow_id' => $escrowId,
                'amount' => $transaction['amount']
            ], 'success');
            
            $pdo->prepare("
                UPDATE transactions 
                SET status = 'paid', 
                    transfer_status = 'paid',
                    updated_at = NOW() 
                WHERE id = ?
            ")->execute([$transactionId]);
            
            // Notify seller to submit credentials
            require_once __DIR__ . '/../includes/notification_helper.php';
            createNotification(
                $transaction['seller_id'],
                'transaction',
                'Payment Received! 💰',
                "Buyer has paid for '{$transaction['listing_name']}'. Please submit access credentials.",
                $transactionId,
                'transaction'
            );
            
            // Send email to seller
            require_once __DIR__ . '/../includes/email_helper.php';
            sendPaymentReceivedEmail(
                $transaction['seller_email'],
                $transaction['seller_name'],
                $transaction['listing_name'],
                $transaction['amount']
            );
            break;
            
        case 'escrow.completed':
        case 'escrow.confirmed':
            // Buyer confirmed receipt with OTP
            log_transaction_event('webhook_escrow_confirmed', [
                'transaction_id' => $transactionId,
                'escrow_id' => $escrowId
            ], 'success');
            
            $pdo->prepare("
                UPDATE transactions 
                SET status = 'completed',
                    transfer_status = 'credentials_submitted',
                    completed_at = NOW() 
                WHERE id = ?
            ")->execute([$transactionId]);
            break;
            
        case 'escrow.released':
        case 'funds.released':
            // Funds released to seller
            log_transaction_event('webhook_funds_released', [
                'transaction_id' => $transactionId,
                'escrow_id' => $escrowId,
                'seller_amount' => $transaction['seller_amount'],
                'platform_fee' => $transaction['platform_fee']
            ], 'success');
            
            $pdo->prepare("
                UPDATE transactions 
                SET status = 'released',
                    transfer_status = 'released',
                    platform_paid = 1,
                    released_at = NOW() 
                WHERE id = ?
            ")->execute([$transactionId]);
            
            // Track platform earnings
            $pdo->prepare("
                INSERT INTO platform_earnings (transaction_id, amount, earned_at)
                VALUES (?, ?, NOW())
            ")->execute([$transactionId, $transaction['platform_fee']]);
            
            // Notify seller
            require_once __DIR__ . '/../includes/notification_helper.php';
            createNotification(
                $transaction['seller_id'],
                'transaction',
                'Funds Released! 🎉',
                "Payment of $" . number_format($transaction['seller_amount'], 2) . " has been released for '{$transaction['listing_name']}'.",
                $transactionId,
                'transaction'
            );
            
            // Notify buyer
            createNotification(
                $transaction['buyer_id'],
                'transaction',
                'Transaction Complete! ✅',
                "Your purchase of '{$transaction['listing_name']}' is complete. Thank you!",
                $transactionId,
                'transaction'
            );
            break;
            
        case 'escrow.cancelled':
        case 'escrow.refunded':
            // Escrow cancelled or refunded
            log_transaction_event('webhook_escrow_cancelled', [
                'transaction_id' => $transactionId,
                'escrow_id' => $escrowId
            ], 'warning');
            
            $pdo->prepare("
                UPDATE transactions 
                SET status = 'cancelled',
                    transfer_status = 'cancelled',
                    updated_at = NOW() 
                WHERE id = ?
            ")->execute([$transactionId]);
            
            // Notify both parties
            require_once __DIR__ . '/../includes/notification_helper.php';
            createNotification(
                $transaction['buyer_id'],
                'transaction',
                'Transaction Cancelled',
                "Transaction for '{$transaction['listing_name']}' has been cancelled.",
                $transactionId,
                'transaction'
            );
            createNotification(
                $transaction['seller_id'],
                'transaction',
                'Transaction Cancelled',
                "Transaction for '{$transaction['listing_name']}' has been cancelled.",
                $transactionId,
                'transaction'
            );
            break;
            
        case 'dispute.opened':
            // Dispute opened
            log_transaction_event('webhook_dispute_opened', [
                'transaction_id' => $transactionId,
                'escrow_id' => $escrowId
            ], 'warning');
            
            $pdo->prepare("
                UPDATE transactions 
                SET status = 'disputed',
                    transfer_status = 'disputed',
                    updated_at = NOW() 
                WHERE id = ?
            ")->execute([$transactionId]);
            
            // Notify admins
            require_once __DIR__ . '/../includes/notification_helper.php';
            $adminStmt = $pdo->query("SELECT id FROM users WHERE role IN ('admin', 'superadmin')");
            while ($admin = $adminStmt->fetch(PDO::FETCH_ASSOC)) {
                createNotification(
                    $admin['id'],
                    'dispute',
                    '⚠️ Dispute Opened',
                    "A dispute has been opened for transaction #{$transactionId}",
                    $transactionId,
                    'dispute'
                );
            }
            break;
            
        default:
            // Unknown event
            log_transaction_event('webhook_unknown_event', [
                'event' => $event,
                'escrow_id' => $escrowId,
                'payload' => $payload
            ], 'warning');
            break;
    }
    
    // Log action in audit log
    log_action(
        "Webhook Processed",
        "Event: {$event}, Escrow ID: {$escrowId}, Transaction ID: {$transactionId}",
        "webhook",
        null
    );
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Webhook processed',
        'event' => $event,
        'transaction_id' => $transactionId
    ]);
    
} catch (Exception $e) {
    log_transaction_event('webhook_exception', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'escrow_id' => $escrowId,
        'event' => $event
    ], 'error');
    
    error_log("Webhook error: " . $e->getMessage());
    
    // Return 500 to trigger retry
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error'
    ]);
}
