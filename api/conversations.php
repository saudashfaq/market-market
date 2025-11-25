<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middlewares/auth.php';

require_login();

$pdo = db();
$currentUser = current_user();
$userId = $currentUser['id'];

header('Content-Type: application/json');

try {
    // Get all conversations for the current user
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            COALESCE(l.name, 'General Chat') as listing_title,
            COALESCE(l.asking_price, 0) as listing_price,
            buyer.name as buyer_name,
            COALESCE(buyer.profile_pic, '') as buyer_profile_pic,
            seller.name as seller_name,
            COALESCE(seller.profile_pic, '') as seller_profile_pic,
            (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message
        FROM conversations c
        LEFT JOIN listings l ON c.listing_id = l.id
        LEFT JOIN users buyer ON c.buyer_id = buyer.id
        LEFT JOIN users seller ON c.seller_id = seller.id
        WHERE c.buyer_id = ? OR c.seller_id = ?
        ORDER BY c.last_message_at DESC
    ");
    
    $stmt->execute([$userId, $userId]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Clean up last_message to remove image data for preview
    foreach ($conversations as &$conv) {
        if ($conv['last_message'] && strpos($conv['last_message'], '[IMAGES]') !== false) {
            $parts = explode('[IMAGES]', $conv['last_message']);
            $conv['last_message'] = trim($parts[0]) ?: '📷 Image';
        }
    }
    
    echo json_encode([
        'success' => true,
        'conversations' => $conversations
    ]);
    
} catch (Exception $e) {
    error_log("Conversations API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load conversations'
    ]);
}