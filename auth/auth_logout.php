<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/log_helper.php";
require_once __DIR__ . "/../includes/flash_helper.php";
require_once __DIR__ . "/../includes/popup_helper.php";

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

session_destroy();

header("Location: " . url("index.php?p=home"));
exit;
