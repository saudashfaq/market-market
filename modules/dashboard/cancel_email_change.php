<?php
/**
 * Cancel Email Change
 * Cancels the pending email change request
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/flash_helper.php';
require_login();

$pdo = db();
$user = current_user();
$user_id = $user['id'];

// Clear pending email and verification token
$stmt = $pdo->prepare("
    UPDATE users 
    SET pending_email = NULL,
        email_verification_token = NULL,
        email_verification_expires_at = NULL
    WHERE id = ?
");
$stmt->execute([$user_id]);

setFlashMessage('success', 'Email change request has been cancelled. Your current email remains unchanged.');

header('Location: ' . BASE . 'index.php?p=dashboard&page=profile');
exit;
