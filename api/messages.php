<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middlewares/auth.php';

require_login();
$pdo = db();
$currentUser = current_user();
$userId = $currentUser['id'];
$conversation_id = (int)$_GET['conversation_id'];
header('Content-Type: application/json');
if (!$conversation_id) {
    echo json_encode(['success' => false, 'error' => 'No conversation ID provided']);
    exit;
}
try {
    $checkAccess = $pdo->prepare("
        SELECT id FROM conversations 
        WHERE id = ? AND (buyer_id = ? OR seller_id = ?)
    ");
    $checkAccess->execute([$conversation_id, $userId, $userId]);
    
    if (!$checkAccess->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    // Get messages for this conversation
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            u.name as sender_name,
            u.profile_pic as sender_profile_pic
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.conversation_id = ?
        ORDER BY m.created_at ASC
    ");
    
    $stmt->execute([$conversation_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    error_log("Messages API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load messages'
    ]);
}