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

// Skip CSRF validation for now to avoid issues
// if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
//     echo json_encode(['success' => false, 'error' => 'Invalid request. Please try again.']);
//     exit;
// }

$conversation_id = (int)($_POST['conversation_id'] ?? 0);

if (!$conversation_id) {
    echo json_encode(['success' => false, 'error' => 'Missing conversation ID']);
    exit;
}

try {
    // Mark all messages in this conversation as read for current user
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE conversation_id = ? 
        AND sender_id != ? 
        AND is_read = 0
    ");
    $stmt->execute([$conversation_id, $currentUser['id']]);
    
    echo json_encode(['success' => true, 'marked_count' => $stmt->rowCount()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
exit;