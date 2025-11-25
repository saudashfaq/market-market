<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../middlewares/auth.php';

header('Content-Type: application/json');

try {
    require_login();
    $pdo = db();
    $currentUser = current_user();
    $user_id = $currentUser['id'];

    // Get unread conversations count (how many people sent messages)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT c.id) as unread_conversations
        FROM conversations c
        WHERE (c.buyer_id = ? OR c.seller_id = ?)
        AND EXISTS (
            SELECT 1 FROM messages m 
            WHERE m.conversation_id = c.id 
            AND m.sender_id != ? 
            AND m.is_read = 0
        )
    ");
    
    $stmt->execute([$user_id, $user_id, $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $unreadCount = (int)$result['unread_conversations'];

    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount
    ]);

} catch (Exception $e) {
    error_log("Get unread count error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to get unread count',
        'unread_count' => 0
    ]);
}
exit;