<?php
/**
 * Resend Email Verification Endpoint
 * Handles requests to resend verification emails with rate limiting
 */

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/flash_helper.php";
require_once __DIR__ . "/../includes/signup_notification_helper.php";
require_once __DIR__ . "/../includes/rate_limiting_helper.php";
require_once __DIR__ . "/../includes/validation_helper.php";
require_once __DIR__ . "/../includes/log_helper.php";

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . url('index.php?p=login&tab=login'));
    exit;
}

// CSRF protection
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setErrorMessage('Invalid request. Please try again.');
    header("Location: " . url('index.php?p=login&tab=login'));
    exit;
}

$email = trim($_POST['email'] ?? '');

// Validate email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setErrorMessage("Please provide a valid email address.");
    header("Location: " . url('index.php?p=login&tab=login'));
    exit;
}

// Ensure rate limiting table exists
ensureResendAttemptsTable();

// Check rate limiting
if (isResendRateLimited($email)) {
    $waitTime = getRemainingWaitTime($email);
    SignupNotificationHelper::setRateLimitNotifications($waitTime);
    log_action("Resend Rate Limited", "Rate limit exceeded for email: {$email}", "auth");
    header("Location: " . url('index.php?p=login&tab=login'));
    exit;
}

try {
    // Find user with unverified email
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT id, name, email_verification_token, email_verification_expires_at 
        FROM users 
        WHERE email = ? AND email_verified = 0
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Don't reveal if email exists or not for security
        setErrorMessage("If this email is registered and unverified, a verification email will be sent.");
        header("Location: " . url('index.php?p=login&tab=login'));
        exit;
    }
    
    // Generate new verification token
    $verificationToken = bin2hex(random_bytes(32));
    $verificationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Update user with new token
    $updateStmt = $pdo->prepare("
        UPDATE users 
        SET email_verification_token = ?, email_verification_expires_at = ? 
        WHERE id = ?
    ");
    $updateStmt->execute([$verificationToken, $verificationExpires, $user['id']]);
    
    // Record the resend attempt for rate limiting
    recordResendAttempt($email);
    
    // Send verification email
    require_once __DIR__ . "/../includes/email_helper.php";
    
    $emailSent = sendEmailVerificationEmail($email, $user['name'], $verificationToken);
    
    if ($emailSent) {
        SignupNotificationHelper::setResendSuccessNotifications($email);
        log_action("Verification Email Resent", "Verification email resent to {$email}", "auth", $user['id']);
    } else {
        SignupNotificationHelper::setResendFailureNotifications($email);
        log_action("Resend Email Failed", "Failed to resend verification email to {$email}", "auth", $user['id']);
    }
    
} catch (Exception $e) {
    SignupNotificationHelper::setResendFailureNotifications($email);
    log_action("Resend Email Error", "Error resending verification email: " . $e->getMessage(), "auth");
    error_log("Resend verification error: " . $e->getMessage());
}

header("Location: " . url('index.php?p=login&tab=login'));
exit;
?>