<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../middlewares/auth.php";

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check authentication status
$response = [
    'authenticated' => is_logged_in(),
    'role' => is_logged_in() ? user_role() : null
];

echo json_encode($response);
exit;
?>