<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../includes/validation_helper.php';
require_login();

$pdo = db();
$currentUser = current_user();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// CSRF validation
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid request. Please try again.']);
    exit;
}

$seller_id = (int)($_POST['seller_id'] ?? 0);
$listing_id = (int)($_POST['listing_id'] ?? 0);
$buyer_id = $currentUser['id'];

if (!$seller_id || $seller_id === $buyer_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid seller ID']);
    exit;
}

try {
    // Check if conversation already exists
    $check = $pdo->prepare("
        SELECT id FROM conversations 
        WHERE (buyer_id = ? AND seller_id = ?) 
           OR (buyer_id = ? AND seller_id = ?)
        LIMIT 1
    ");
    $check->execute([$buyer_id, $seller_id, $seller_id, $buyer_id]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo json_encode(['success' => true, 'conversation_id' => $existing['id'], 'existing' => true]);
    } else {
        // Create new conversation
        $insert = $pdo->prepare("
            INSERT INTO conversations (buyer_id, seller_id, listing_id, last_message_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $insert->execute([$buyer_id, $seller_id, $listing_id]);
        
        $conversation_id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'conversation_id' => $conversation_id, 'existing' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
exit;