<?php
/**
 * Simple Polling Test - Check if API is accessible
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

echo json_encode([
    'success' => true,
    'message' => 'Polling API is accessible',
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => [
        'php_version' => PHP_VERSION,
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'request_uri' => $_SERVER['REQUEST_URI'],
        'script_name' => $_SERVER['SCRIPT_NAME']
    ]
]);
