<?php

/**
 * Check Login Status API
 * Returns current user login status and appropriate redirect URL
 */

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../middlewares/auth.php";

header('Content-Type: application/json');

// Check if user is logged in
if (is_logged_in()) {
    $role = user_role();

    // Determine redirect URL based on role
    $redirectUrl = '';
    switch ($role) {
        case 'superadmin':
        case 'super_admin':
            $redirectUrl = url("public/index.php?p=dashboard&page=superAdminDashboard");
            break;
        case 'admin':
            $redirectUrl = url("public/index.php?p=dashboard&page=adminDashboard");
            break;
        default:
            $redirectUrl = url("public/index.php?p=dashboard&page=userDashboard");
            break;
    }

    echo json_encode([
        'logged_in' => true,
        'role' => $role,
        'redirect_url' => $redirectUrl
    ]);
} else {
    echo json_encode([
        'logged_in' => false
    ]);
}
