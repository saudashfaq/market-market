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
    log_action("Webhook Failed", "Invalid payload structure received", "webhook", null, "system");
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
        log_action("Webhook Failed", "Invalid signature for event: " . ($event['event'] ?? 'unknown'), "webhook", null, "system");
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'Invalid signature']);
        exit;
    }
}

// ✅ Step 4: Extract details
$eventType = $event['event'];
$data = $event['data'] ?? [];
$escrow_id = $data['escrow_id'] ?? 'N/A';

// ✅ Step 5: Log received event
file_put_contents(
    $logFile,
    date('Y-m-d H:i:s') . " - EVENT: {$eventType} | DATA: " . json_encode($data) . PHP_EOL,
    FILE_APPEND
);

// Log webhook receipt to database
log_action("Webhook Received", "Event: {$eventType}, Escrow ID: {$escrow_id}", "webhook", null, "system");

// ✅ Step 6: Handle webhook events
try {
    file_put_contents($logFile, "→ Handling Event: {$eventType}" . PHP_EOL, FILE_APPEND);

    switch ($eventType) {
        case 'escrow.created':
            file_put_contents($logFile, "→ Escrow Created: " . json_encode($data) . PHP_EOL, FILE_APPEND);
            log_action("Webhook Processed", "Escrow created - ID: {$escrow_id}", "webhook", null, "system");
            break;

        case 'escrow.paid':
        case 'escrow.payment.success':
        case 'success.transaction':
            require_once __DIR__ . '/../includes/encryption_helper.php';
            require_once __DIR__ . '/../includes/notification_helper.php';
            
            $escrow_id = $data['escrow_id'] ?? null;
            $transaction_ref = $data['transaction']['transaction_ref'] ?? null;
            $amount = $data['escrow_data']['amount'] ?? 0;

            if ($escrow_id) {
                // Find transaction
                $findStmt = $pdo->prepare("
                    SELECT t.*, 
                           l.name as listing_name, 
                           l.category,
                           seller.email as seller_email, 
                           seller.name as seller_name,
                           seller.id as seller_id
                    FROM transactions t
                    JOIN listings l ON t.listing_id = l.id
                    JOIN users seller ON t.seller_id = seller.id
                    WHERE t.pandascrow_escrow_id = ? OR t.escrow_transaction_id = ?
                    LIMIT 1
                ");
                $findStmt->execute([$escrow_id, $transaction_ref]);
                $transaction = $findStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($transaction) {
                    // Generate encryption key
                    $encryptionKey = generateEncryptionKey();
                    $stmt = $pdo->prepare("
                        UPDATE transactions 
                        SET status = 'holding',
                            escrow_transaction_id = ?,
                            encryption_key = ?,
                            transfer_status = 'awaiting_credentials',
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$transaction_ref, $encryptionKey, $transaction['id']]);

                    file_put_contents($logFile, "→ Escrow Paid Updated: escrow_id={$escrow_id}, ref={$transaction_ref}, encryption_key generated" . PHP_EOL, FILE_APPEND);
                    
                    // Log successful payment processing
                    log_action(
                        "Webhook Processed", 
                        "Payment confirmed - Escrow ID: {$escrow_id}, Transaction: {$transaction['id']}, Listing: {$transaction['listing_name']}, Amount: $" . number_format($amount, 2), 
                        "webhook", 
                        $transaction['seller_id'], 
                        "system"
                    );
                    
                    // Send email to seller
                    $credentialSubmitUrl = url("modules/dashboard/submit_credentials.php?transaction_id=" . $transaction['id']);
                    
                    $subject = "Payment Received - Submit Credentials Now";
                    $emailBody = "
                    <html>
                    <body style='font-family: Arial, sans-serif;'>
                        <h2>Payment Confirmed! 🎉</h2>
                        <p>Hi {$transaction['seller_name']},</p>
                        <p>Great news! Payment has been confirmed for your listing: <strong>{$transaction['listing_name']}</strong></p>
                        
                        <p><strong>Transaction Details:</strong></p>
                        <ul>
                            <li>Transaction ID: #{$transaction['id']}</li>
                            <li>Amount: $" . number_format($amount, 2) . "</li>
                        </ul>
                        
                        <p style='color: #d9534f;'><strong>⏰ Action Required:</strong></p>
                        <p>You have <strong>48 hours</strong> to submit the access credentials.</p>
                        
                        <p style='margin: 30px 0;'>
                            <a href='{$credentialSubmitUrl}' 
                               style='background-color: #5cb85c; color: white; padding: 12px 30px; 
                                      text-decoration: none; border-radius: 5px; display: inline-block;'>
                                Submit Credentials Now
                            </a>
                        </p>
                        
                        <p><small>If the button doesn't work, copy this link:<br>{$credentialSubmitUrl}</small></p>
                    </body>
                    </html>
                    ";
                    
                    $headers = "MIME-Version: 1.0" . "\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                    $headers .= "From: noreply@marketplace.com" . "\r\n";
                    
                    mail($transaction['seller_email'], $subject, $emailBody, $headers);
                    
                    // Create notification
                    createNotification(
                        $transaction['seller_id'],
                        'payment',
                        'Payment Received - Submit Credentials',
                        "Payment confirmed for '{$transaction['listing_name']}'. Submit credentials within 48 hours.",
                        $transaction['id'],
                        'transaction'
                    );
                    
                    file_put_contents($logFile, "→ Email & notification sent to seller" . PHP_EOL, FILE_APPEND);
                } else {
                    file_put_contents($logFile, "→ WARNING: Transaction not found for escrow_id={$escrow_id}" . PHP_EOL, FILE_APPEND);
                    log_action("Webhook Failed", "Transaction not found for Escrow ID: {$escrow_id}", "webhook", null, "system");
                }
            }
            break;

        case 'escrow.completed':
            $escrow_id = $data['escrow_id'] ?? null;
            if ($escrow_id) {
                $stmt = $pdo->prepare("UPDATE transactions SET status = 'completed' WHERE pandascrow_escrow_id = ?");
                $stmt->execute([$escrow_id]);
                log_action("Webhook Processed", "Escrow completed - ID: {$escrow_id}", "webhook", null, "system");
            }
            break;

        case 'escrow.refunded':
            $escrow_id = $data['escrow_id'] ?? null;
            if ($escrow_id) {
                $stmt = $pdo->prepare("UPDATE transactions SET status = 'refunded' WHERE pandascrow_escrow_id = ?");
                $stmt->execute([$escrow_id]);
                log_action("Webhook Processed", "Escrow refunded - ID: {$escrow_id}", "webhook", null, "system");
            }
            break;

        default:
            file_put_contents($logDir . '/webhook_unknown.txt', date('Y-m-d H:i:s') . " - Unhandled Event: {$eventType}" . PHP_EOL, FILE_APPEND);
            log_action("Webhook Received", "Unhandled event type: {$eventType}", "webhook", null, "system");
            break;
    }
} catch (Exception $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - DB ERROR: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    log_action("Webhook Failed", "Event: {$eventType}, Error: " . $e->getMessage(), "webhook", null, "system");
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Database error']);
    exit;
}


// ✅ Step 7: Send acknowledgement
http_response_code(200);
echo json_encode(['status' => true, 'message' => 'Webhook received successfully']);

