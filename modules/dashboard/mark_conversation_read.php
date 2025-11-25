<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../middlewares/auth.php';

header('Content-Type: application/json');

try {
    require_login();
    $pdo = db();
    $currentUser = current_user();
    $user_id = $currentUser['id'];
    
    $conversation_id = (int)($_POST['conversation_id'] ?? 0);
    
    if (!$conversation_id) {
        echo json_encode(['success' => false, 'error' => 'No conversation ID provided']);
        exit;
    }
    
    // Verify user has access to this conversation
    $checkAccess = $pdo->prepare("
        SELECT id FROM conversations 
        WHERE id = ? AND (buyer_id = ? OR seller_id = ?)
    ");
    $checkAccess->execute([$conversation_id, $user_id, $user_id]);
    
    if (!$checkAccess->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    // Create conversation_reads table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS conversation_reads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            user_id INT NOT NULL,
            last_read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_conversation_user (conversation_id, user_id)
        )
    ");
    
    // Insert or update read status
    $stmt = $pdo->prepare("
        INSERT INTO conversation_reads (conversation_id, user_id, last_read_at) 
        VALUES (?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE last_read_at = NOW()
    ");
    
    $stmt->execute([$conversation_id, $user_id]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Mark conversation read error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to mark as read']);
}
exit;