<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../includes/validation_helper.php';
require_login();
require_profile_completion();

$pdo = db();
$currentUser = current_user();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation for POST
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid request. Please try again.']);
        exit;
    }

    $seller_id = (int)($_POST['seller_id'] ?? 0);
    $listing_id = (int)($_POST['listing_id'] ?? 0);
} else {
    // GET handling (Temporary for payment bypass)
    $seller_id = (int)($_GET['seller_id'] ?? 0);
    $listing_id = (int)($_GET['listing_id'] ?? 0);
}

// Common validation
$buyer_id = $currentUser['id'];

if (!$seller_id || $seller_id === $buyer_id) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid seller ID']);
    } else {
        // Redirect back or show error
        header("Location: " . url("public/index.php?p=dashboard&page=message&error=invalid_seller"));
    }
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

    $conversation_id = 0;

    if ($existing) {
        $conversation_id = $existing['id'];
        $is_new = false;
    } else {
        // Create new conversation
        $insert = $pdo->prepare("
            INSERT INTO conversations (buyer_id, seller_id, listing_id, last_message_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $insert->execute([$buyer_id, $seller_id, $listing_id]);

        $conversation_id = $pdo->lastInsertId();
        $is_new = true;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['success' => true, 'conversation_id' => $conversation_id, 'existing' => !$is_new]);
    } else {
        // Redirect to messages
        header("Location: " . url("public/index.php?p=dashboard&page=message&conversation_id=" . $conversation_id));
    }
} catch (Exception $e) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    } else {
        header("Location: " . url("public/index.php?p=dashboard&page=message&error=db_error"));
    }
}
exit;
