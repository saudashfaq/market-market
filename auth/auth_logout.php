<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/log_helper.php";
require_once __DIR__ . "/../includes/flash_helper.php";
require_once __DIR__ . "/../includes/popup_helper.php";

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = $_SESSION['user'] ?? null;

if ($user) {
    log_action("User Logged Out", "User logged out: {$user['name']} ({$user['email']})", "auth", $user['id']);

    // Set popup message before destroying session
    setSuccessPopup("You have been logged out successfully.", [
        'title' => 'Logout Successful',
        'autoClose' => true,
        'autoCloseTime' => 3000
    ]);
}

// Clear all session data
$_SESSION = array();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

header("Location: " . url("public/index.php?p=home"));
exit;
