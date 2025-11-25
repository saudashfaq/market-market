<?php
/**
 * webhook.php — Pandascrow Webhook Listener
 * Listens for webhook events like escrow.created, payment.success, etc.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/log_helper.php';

$pdo = db(); // your existing DB connection
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) mkdir($logDir, 0777, true);
$logFile = $logDir . '/webhook_log.txt';

// ✅ Step 1: Capture raw webhook payload
$input = file_get_contents("php://input");
$event = json_decode($input, true);

// ✅ Step 2: Validate structure
if (!$event || !isset($event['event']) || !isset($event['data'])) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Invalid payload: " . $input . PHP_EOL, FILE_APPEND);
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Invalid payload structure']);
    exit;
}

// ✅ Step 3: Validate signature (only if headers exist)
$receivedSignature = $_SERVER['HTTP_X_PANDASCROW_SIGNATURE'] ?? '';
$appKey = $_SERVER['HTTP_X_PANDASCROW_APP'] ?? '';
$expectedSecret = PANDASCROW_SECRET_KEY;

if (!empty($receivedSignature)) {
    $calculatedSignature = hash_hmac('sha256', json_encode([
        'event'     => $event['event'],
        'data'      => $event['data'],
        'timestamp' => $event['timestamp'] ?? ''
    ]), $expectedSecret);

    if (!hash_equals($calculatedSignature, $receivedSignature)) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Invalid signature" . PHP_EOL, FILE_APPEND);
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'Invalid signature']);
        exit;
    }
}

// ✅ Step 4: Extract details
$eventType = $event['event'];
$data = $event['data'] ?? [];

// ✅ Step 5: Log received event
file_put_contents(
    $logFile,
    date('Y-m-d H:i:s') . " - EVENT: {$eventType} | DATA: " . json_encode($data) . PHP_EOL,
    FILE_APPEND
);

// ✅ Step 6: Handle webhook events
try {
    switch ($eventType) {
        case 'escrow.created':
            // Optional: Log only
            file_put_contents($logFile, "→ Escrow Created: " . json_encode($data) . PHP_EOL, FILE_APPEND);
            break;

        case 'escrow.payment.success':
        case 'success.transaction':
            $escrow_id = $data['escrow_id'] ?? null;
            $transaction_ref = $data['transaction_ref'] ?? null;

            if ($escrow_id) {
                // Get transaction details
                $txnStmt = $pdo->prepare("
                    SELECT t.*, o.buyer_id, o.seller_id, l.name as listing_name
                    FROM transactions t
                    LEFT JOIN orders o ON t.order_id = o.id
                    LEFT JOIN listings l ON o.listing_id = l.id
                    WHERE t.escrow_transaction_id = ? OR t.pandascrow_escrow_id = ?
                    LIMIT 1
                ");
                $txnStmt->execute([$transaction_ref, $escrow_id]);
                $transaction = $txnStmt->fetch(PDO::FETCH_ASSOC);
                
                // Update transaction status
                $stmt = $pdo->prepare("
                    UPDATE transactions 
                    SET status = 'paid', escrow_transaction_id = ? 
                    WHERE escrow_transaction_id = ? OR pandascrow_escrow_id = ?
                ");
                $stmt->execute([$transaction_ref, $transaction_ref, $escrow_id]);
                
                // Log payment success
                if ($transaction) {
                    log_action(
                        "Payment Successful",
                        "Payment processed for Order ID: {$transaction['order_id']}, Listing: {$transaction['listing_name']}, Amount: $" . number_format($transaction['amount'] ?? 0, 2) . ", Escrow ID: {$escrow_id}",
                        "payment",
                        $transaction['buyer_id'],
                        "user"
                    );
                }
                
                // Create notifications
                if ($transaction) {
                    require_once __DIR__ . '/../includes/notification_helper.php';
                    
                    // Notify seller
                    if ($transaction['seller_id']) {
                        createNotification(
                            $transaction['seller_id'],
                            'order',
                            'Payment Received! 💰',
                            "Payment received for '{$transaction['listing_name']}'. Funds are in escrow",
                            $transaction['order_id'],
                            'order'
                        );
                    }
                    
                    // Notify buyer
                    if ($transaction['buyer_id']) {
                        createNotification(
                            $transaction['buyer_id'],
                            'order',
                            'Payment Successful',
                            "Your payment for '{$transaction['listing_name']}' has been processed",
                            $transaction['order_id'],
                            'order'
                        );
                    }
                    
                    // Notify all admins/superadmins about payment received
                    notifyAdminsPaymentReceived(
                        $transaction['order_id'],
                        $transaction['amount'] ?? 0,
                        $transaction['listing_name']
                    );
                }
            }
            break;

        case 'escrow.completed':
            $escrow_id = $data['escrow_id'] ?? null;
            if ($escrow_id) {
                $stmt = $pdo->prepare("UPDATE transactions SET status = 'completed' WHERE pandascrow_escrow_id = ?");
                $stmt->execute([$escrow_id]);
            }
            break;

        case 'escrow.refunded':
            $escrow_id = $data['escrow_id'] ?? null;
            if ($escrow_id) {
                $stmt = $pdo->prepare("UPDATE transactions SET status = 'refunded' WHERE pandascrow_escrow_id = ?");
                $stmt->execute([$escrow_id]);
            }
            break;

        default:
            // Unknown event type
            file_put_contents($logDir . '/webhook_unknown.txt', date('Y-m-d H:i:s') . " - Unhandled Event: {$eventType}" . PHP_EOL, FILE_APPEND);
            break;
    }
} catch (Exception $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - DB ERROR: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Database error']);
    exit;
}

// ✅ Step 7: Send acknowledgement
http_response_code(200);
echo json_encode(['status' => true, 'message' => 'Webhook received successfully']);
