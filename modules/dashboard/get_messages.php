<?php
// Clean output buffer to prevent any whitespace/errors before JSON
ob_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../middlewares/auth.php';

// Clear any output from includes and restart buffer
ob_end_clean();
ob_start();

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

// Clear any output that might have been generated
ob_clean();

echo json_encode([
    'success' => true,
    'messages' => $messages
]);

ob_end_flush();
exit;
