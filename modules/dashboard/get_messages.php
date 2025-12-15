<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../middlewares/auth.php';
require_login();

$pdo = db();
$conversation_id = (int)($_GET['conversation_id'] ?? 0);

header('Content-Type: application/json');

$stmt = $pdo->prepare("
  SELECT 
    m.sender_id, 
    m.message, 
    m.created_at, 
    u.name AS sender_name, 
    COALESCE(u.profile_pic, '') AS sender_profile_pic
  FROM messages m
  LEFT JOIN users u ON m.sender_id = u.id
  WHERE m.conversation_id = ? 
  ORDER BY m.created_at ASC
");
$stmt->execute([$conversation_id]);

$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'messages' => $messages
]);
exit;
