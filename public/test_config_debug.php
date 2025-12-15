<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain');
echo "Script: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "Dirname: " . dirname($_SERVER['SCRIPT_NAME']) . "\n";
echo "BASE: " . BASE . "\n";

$apiBase = defined('BASE') ? BASE : '';
if (strpos($apiBase, '/public/') !== false) {
    $apiBase = str_replace('/public/', '/api/', $apiBase);
} else {
    $apiBase = rtrim($apiBase, '/') . '/api/';
}
$apiBase = rtrim($apiBase, '/');

echo "Calculated API Base: " . $apiBase . "\n";

// Test file existence
$apiFile = __DIR__ . '/../api/notifications_api.php';
echo "API File Path: " . realpath($apiFile) . "\n";
echo "API File Exists: " . (file_exists($apiFile) ? 'Yes' : 'No') . "\n";
