<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../middlewares/auth.php';

header('Content-Type: application/json');

try {
    require_login();
    $pdo = db();
    $currentUser = current_user();
    $user_id = $currentUser['id'];

    $stmt = $pdo->prepare("
      SELECT 
        c.id,
        c.listing_id,
        c.buyer_id,
        c.seller_id,
        CASE 
          WHEN c.buyer_id = ? THEN COALESCE(s.name, 'Unknown User')
          ELSE COALESCE(b.name, 'Unknown User')
        END AS other_user_name,
        CASE 
          WHEN c.buyer_id = ? THEN COALESCE(s.profile_pic, '')
          ELSE COALESCE(b.profile_pic, '')
        END AS other_user_profile_pic,
        COALESCE(m.message, '') AS last_message,
        c.last_message_at,
        COALESCE(l.name, '') AS listing_title,
        (
          SELECT COUNT(*) 
          FROM messages msg 
          WHERE msg.conversation_id = c.id 
          AND msg.sender_id != ?
          AND msg.is_read = 0
        ) AS unread_count
      FROM conversations c
      LEFT JOIN users b ON c.buyer_id = b.id
      LEFT JOIN users s ON c.seller_id = s.id
      LEFT JOIN listings l ON c.listing_id = l.id
      LEFT JOIN messages m ON m.id = (
        SELECT id FROM messages 
        WHERE conversation_id = c.id 
        ORDER BY created_at DESC LIMIT 1
      )
      WHERE c.buyer_id = ? OR c.seller_id = ?
      ORDER BY c.last_message_at DESC
    ");

    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Clean up last_message to remove image data for preview
    foreach ($result as &$conv) {
        if ($conv['last_message'] && strpos($conv['last_message'], '[IMAGES]') !== false) {
            $parts = explode('[IMAGES]', $conv['last_message']);
            $conv['last_message'] = trim($parts[0]) ?: '📷 Image';
        }
        
        // Ensure profile_pic is never null
        if (empty($conv['other_user_profile_pic'])) {
            $conv['other_user_profile_pic'] = '';
        }
    }

    echo json_encode([
        'success' => true,
        'conversations' => $result
    ]);

} catch (Exception $e) {
    error_log("Get conversations error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load conversations: ' . $e->getMessage()
    ]);
}
exit;