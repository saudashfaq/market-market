<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../middlewares/auth.php';

header('Content-Type: application/json');

try {
    require_login();
    $pdo = db();
    $currentUser = current_user();

    $conversation_id = (int)($_POST['conversation_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    // Check if we have either message text or images
    $hasMessage = !empty($message);
    $hasImages = isset($_FILES['images']) && !empty($_FILES['images']['name'][0]);

    if (!$conversation_id) {
        echo json_encode(['success' => false, 'error' => 'No conversation selected']);
        exit;
    }

    if (!$hasMessage && !$hasImages) {
        echo json_encode(['success' => false, 'error' => 'No message or images provided']);
        exit;
    }

    $pdo->beginTransaction();

    $imageUrls = [];

    // Handle image uploads
    if ($hasImages) {
        $uploadDir = __DIR__ . '/../../public/uploads/messages/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {
            if (is_uploaded_file($tmpName)) {
                $originalName = $_FILES['images']['name'][$index];
                $fileSize = $_FILES['images']['size'][$index];
                $fileType = $_FILES['images']['type'][$index];

                // Validate file
                if ($fileSize > 5 * 1024 * 1024) {
                    throw new Exception('Image too large. Max size is 5MB.');
                }

                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($fileType, $allowedTypes)) {
                    throw new Exception('Invalid image type. Only JPEG, PNG, GIF, and WebP are allowed.');
                }

                // Generate secure filename
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                $filename = 'msg_' . $conversation_id . '_' . uniqid() . '_' . time() . '.' . $extension;
                $filePath = $uploadDir . $filename;

                if (move_uploaded_file($tmpName, $filePath)) {
                    $imageUrls[] = 'uploads/messages/' . $filename;
                } else {
                    throw new Exception('Failed to upload image: ' . $originalName);
                }
            }
        }
    }

    // Prepare message content
    $messageContent = $message;
    if (!empty($imageUrls)) {
        $imageJson = json_encode($imageUrls);
        if ($messageContent) {
            $messageContent .= "[IMAGES]" . $imageJson;
        } else {
            $messageContent = "[IMAGES]" . $imageJson;
        }
    }

    // Verify user has access to this conversation
    $checkAccess = $pdo->prepare("
        SELECT id FROM conversations 
        WHERE id = ? AND (buyer_id = ? OR seller_id = ?)
    ");
    $checkAccess->execute([$conversation_id, $currentUser['id'], $currentUser['id']]);

    if (!$checkAccess->fetch()) {
        throw new Exception('Access denied to this conversation');
    }

    // Insert message
    $stmt = $pdo->prepare("
        INSERT INTO messages (conversation_id, sender_id, message, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$conversation_id, $currentUser['id'], $messageContent]);
    $message_id = $pdo->lastInsertId();

    // Update conversation timestamp
    $pdo->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?")
        ->execute([$conversation_id]);

    // Get conversation details to notify the recipient
    $convStmt = $pdo->prepare("
        SELECT c.*, l.name as listing_name,
               CASE 
                   WHEN c.buyer_id = ? THEN c.seller_id
                   ELSE c.buyer_id
               END as recipient_id
        FROM conversations c
        LEFT JOIN listings l ON c.listing_id = l.id
        WHERE c.id = ?
    ");
    $convStmt->execute([$currentUser['id'], $conversation_id]);
    $conversation = $convStmt->fetch(PDO::FETCH_ASSOC);

    // Create notification for recipient
    if ($conversation && $conversation['recipient_id']) {
        require_once __DIR__ . '/../../includes/notification_helper.php';

        $senderName = $currentUser['name'] ?? 'Someone';
        $listingName = $conversation['listing_name'] ?? 'a listing';

        // Create message preview (first 50 chars)
        $messagePreview = $message;
        if (strlen($messagePreview) > 50) {
            $messagePreview = substr($messagePreview, 0, 50) . '...';
        }
        if (empty($messagePreview) && !empty($imageUrls)) {
            $messagePreview = 'Sent an image';
        }

        createNotification(
            $conversation['recipient_id'],
            'message',
            "New message from {$senderName}",
            $messagePreview,
            $conversation_id,
            'conversation'
        );

        // Send email notification in background
        register_shutdown_function(function () use ($pdo, $conversation, $senderName, $message) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            try {
                require_once __DIR__ . '/../../includes/email_helper.php';

                // Get recipient details
                $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
                $stmt->execute([$conversation['recipient_id']]);
                $recipient = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($recipient) {
                    sendNewMessageEmail($recipient['email'], $recipient['name'], $senderName, $message);
                    error_log("✅ Message notification email sent to: {$recipient['email']}");
                }
            } catch (Exception $e) {
                error_log("❌ Error sending message notification email: " . $e->getMessage());
            }
        });
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'images' => $imageUrls,
        'message_id' => $pdo->lastInsertId()
    ]);
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log("Message send error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
