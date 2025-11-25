<?php
/**
 * ✅ Tawk.to Webhook Handler (PHP Core Version)
 * Handles chat:message (Pro) and chat:transcript_created (Free) events
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/log_helper.php';

/* ==========================================================
   CONFIGURATION
   ========================================================== */
$webhookSecret = '4c3f6387a1c146173f7902603c19f357ec3d97f15d340c76508da06621c41569c1396b0b496635b249720bd48a301283';

// Log file path setup
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/tawk_webhook.log';

function logToFile($message)
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

/* ==========================================================
   READ RAW PAYLOAD & HEADERS
   ========================================================== */
$payload = file_get_contents('php://input');
$headers = getallheaders();
$signature = $headers['X-Tawk-Signature'] ?? '';

logToFile("=== Incoming Webhook Request ===");
logToFile("Headers: " . print_r($headers, true));
logToFile("Raw Payload: " . $payload);

/* ==========================================================
   VERIFY SIGNATURE (HMAC-SHA1)
   ========================================================== */
if (!empty($signature)) {
    $expectedSignature = hash_hmac('sha1', $payload, $webhookSecret);
    logToFile("Received Signature: $signature");
    logToFile("Expected Signature: $expectedSignature");

    if (!hash_equals($expectedSignature, $signature)) {
        logToFile("❌ Invalid signature — webhook rejected");
        log_action("Webhook Failed", "Tawk.to webhook - Invalid signature", "webhook", null, "system");
        http_response_code(401);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    } else {
        logToFile("✅ Signature verified successfully");
    }
} else {
    logToFile("⚠️ No signature provided (testing mode)");
}

/* ==========================================================
   PARSE JSON PAYLOAD
   ========================================================== */
$data = json_decode($payload, true);

if (!$data) {
    logToFile("❌ Invalid JSON payload: " . substr($payload, 0, 200));
    log_action("Webhook Failed", "Tawk.to webhook - Invalid JSON payload", "webhook", null, "system");
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

logToFile("Decoded Payload: " . print_r($data, true));

/* ==========================================================
   HANDLE EVENTS
   ========================================================== */
try {
    $pdo = db();

    $event = $data['event'] ?? '';
    
    // Log webhook receipt
    log_action("Webhook Received", "Tawk.to event: {$event}", "webhook", null, "system");

    // ===========================
    // chat:message (Pro plan)
    // ===========================
    if ($event === 'chat:message') {
        $message = $data['message']['text'] ?? '';
        $senderType = $data['message']['sender']['type'] ?? ''; // 'visitor' or 'agent'

        $ticketId = getTicketId($data['visitor']['customAttributes'] ?? []);
        $ticketId = autoCreateTicketIfNeeded($pdo, $ticketId, $message, $data['visitor'] ?? []);

        if ($ticketId && !empty($message)) {
            saveMessage($pdo, $ticketId, $message, $senderType);
            log_action("Webhook Processed", "Tawk.to chat:message - Ticket ID: {$ticketId}, Sender: {$senderType}", "webhook", null, "system");
        } else {
            logToFile("⚠️ Missing ticket_id or message text for chat:message");
            log_action("Webhook Failed", "Tawk.to chat:message - Missing ticket_id or message", "webhook", null, "system");
        }
    }

    // ===========================
    // chat:transcript_created (Free plan)
    // ===========================
    elseif ($event === 'chat:transcript_created') {
        $chat = $data['chat'] ?? [];
        $ticketId = getTicketId($chat['visitor']['customAttributes'] ?? []);
        $ticketId = autoCreateTicketIfNeeded($pdo, $ticketId, '', $chat['visitor'] ?? []);

        $messages = $chat['messages'] ?? [];
        $messageCount = 0;
        foreach ($messages as $msg) {
            $messageText = $msg['msg'] ?? '';
            $senderType = ($msg['sender']['t'] === 's') ? 'agent' : 'visitor';
            if (!empty($messageText)) {
                saveMessage($pdo, $ticketId, $messageText, $senderType);
                $messageCount++;
            }
        }
        log_action("Webhook Processed", "Tawk.to transcript - Ticket ID: {$ticketId}, Messages: {$messageCount}", "webhook", null, "system");
    }

    // ===========================
    // chat:start - Create ticket when chat starts
    // ===========================
    elseif ($event === 'chat:start') {
        logToFile("ℹ️ Chat started, creating ticket");
        
        $visitor = $data['visitor'] ?? [];
        $message = $data['message']['text'] ?? 'Chat started via Tawk.to';
        $chatId = $data['chatId'] ?? '';
        
        // Create ticket automatically
        $ticketId = autoCreateTicketIfNeeded($pdo, null, $message, $visitor);
        
        if ($ticketId) {
            // Save first message
            saveMessage($pdo, $ticketId, $message, 'visitor');
            logToFile("✅ Ticket created on chat:start - Ticket ID: $ticketId, Chat ID: $chatId");
            log_action("Webhook Processed", "Tawk.to chat:start - Ticket ID: {$ticketId}, Chat ID: {$chatId}", "webhook", null, "system");
        }
    }
    
    // ===========================
    // Other events
    // ===========================
    else {
        logToFile("ℹ️ Non-message event received: " . $event);
        log_action("Webhook Received", "Tawk.to unhandled event: {$event}", "webhook", null, "system");
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Event processed']);

} catch (Exception $e) {
    logToFile("❌ Exception: " . $e->getMessage());
    log_action("Webhook Failed", "Tawk.to error: " . $e->getMessage(), "webhook", null, "system");
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/* ==========================================================
   HELPER FUNCTIONS
   ========================================================== */
function getTicketId($customAttributes)
{
    foreach ($customAttributes as $attr) {
        if ($attr['key'] === 'ticket_id') {
            return (int)$attr['value'];
        }
    }
    return null;
}

function autoCreateTicketIfNeeded($pdo, $ticketId, $message, $visitor)
{
    if ($ticketId || empty($message)) return $ticketId;

    logToFile("ℹ️ No ticket_id, creating new ticket from Tawk.to");

    $visitorEmail = $visitor['email'] ?? null;
    $visitorName = $visitor['name'] ?? 'Guest';

    // Find user by email
    $userId = null;
    if ($visitorEmail) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$visitorEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $userId = $user['id'] ?? null;
    }

    // Fallback to admin user
    if (!$userId) {
        $stmt = $pdo->query("SELECT id FROM users WHERE role='superadmin' LIMIT 1");
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        $userId = $admin['id'] ?? 1;
    }

    // Create ticket
    $subject = "Tawk.to Chat: " . substr($message, 0, 50);
    $stmt = $pdo->prepare("INSERT INTO tickets (user_id, subject, status, created_at, updated_at) VALUES (?, ?, 'open', NOW(), NOW())");
    $stmt->execute([$userId, $subject]);
    $ticketId = $pdo->lastInsertId();

    logToFile("✅ Auto-created ticket ID: $ticketId for user ID: $userId");
    log_action("Ticket Created", "Auto-created from Tawk.to - Ticket ID: {$ticketId}, User: {$visitorName}", "ticket", $userId, "user");
    return $ticketId;
}

function saveMessage($pdo, $ticketId, $message, $senderType)
{
    global $logFile;

    $stmt = $pdo->prepare("SELECT user_id FROM tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        logToFile("❌ Ticket not found for ID: $ticketId");
        return;
    }

    $isAdmin = ($senderType === 'agent') ? 1 : 0;
    $userId = $ticket['user_id'];

    if ($isAdmin) {
        $stmt = $pdo->query("SELECT id FROM users WHERE role='superadmin' LIMIT 1");
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        $userId = $admin['id'] ?? $userId;
    }

    $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, is_admin, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$ticketId, $userId, $message, $isAdmin]);

    $pdo->prepare("UPDATE tickets SET updated_at=NOW() WHERE id=?")->execute([$ticketId]);

    logToFile("✅ Message saved | Ticket ID: $ticketId | Sender: " . ($isAdmin ? 'Admin' : 'User'));
}
