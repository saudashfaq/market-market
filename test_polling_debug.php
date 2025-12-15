<?php
// Test script for polling API logic
require_once 'config.php';

// Mock session for Super Admin
// Check what the actual role is in the DB for the user
$pdo = db();
$stmt = $pdo->query("SELECT * FROM users WHERE role LIKE '%admin%' LIMIT 1");
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die("No admin user found in DB to test with.");
}

echo "Testing with Admin User: " . $admin['name'] . " (Role: " . $admin['role'] . ")\n";

// Simulate the logic in polling_integration.php
$userRole = $admin['role'];
$lastCheckTimes = [
    'logs' => '2024-01-01 00:00:00', // Old date to get all recent
    'payments' => '2024-01-01 00:00:00',
    'disputes' => '2024-01-01 00:00:00'
];

echo "Checking logs for role: $userRole\n";

if (in_array($userRole, ['admin', 'superadmin', 'super_admin', 'superAdmin'])) {
    echo "Role authorized.\n";

    $sql = "
        SELECT l.*, u.name as user_name, u.email as user_email
        FROM logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE l.created_at > ?
        ORDER BY l.created_at DESC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$lastCheckTimes['logs']]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($logs) . " logs.\n";
    if (count($logs) > 0) {
        print_r($logs[0]);
    }
} else {
    echo "Role NOT authorized.\n";
}
