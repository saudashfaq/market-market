<?php
/**
 * Resend Email Verification
 * Resends the verification email to pending email address
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/email_verification_helper.php';
require_once __DIR__ . '/../../includes/flash_helper.php';
require_login();

$pdo = db();
$user = current_user();
$user_id = $user['id'];

// Fetch fresh user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if there's a pending email
if (empty($user['pending_email'])) {
    setFlashMessage('error', 'No pending email change found.');
    header('Location: ' . BASE . 'index.php?p=dashboard&page=profile');
    exit;
}

// Check if token expired
if (!empty($user['email_verification_expires_at']) && strtotime($user['email_verification_expires_at']) < time()) {
    // Token expired, generate new one
    $token = generateEmailVerificationToken();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $stmt = $pdo->prepare("
        UPDATE users 
        SET email_verification_token = ?,
            email_verification_expires_at = ?
        WHERE id = ?
    ");
    $stmt->execute([$token, $expiresAt, $user_id]);
} else {
    // Use existing token
    $token = $user['email_verification_token'];
}

// Resend verification email
$emailSent = sendEmailVerificationLink($user['pending_email'], $user['name'], $token);

if ($emailSent) {
    setFlashMessage('success', 'Verification email has been resent to ' . htmlspecialchars($user['pending_email']) . '. Please check your inbox.');
} else {
    setFlashMessage('error', 'Failed to send verification email. Please try again later.');
}

header('Location: ' . BASE . 'index.php?p=dashboard&page=profile');
exit;
