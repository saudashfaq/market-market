<?php
/**
 * Temporary file to update Pandascrow webhook URL
 * Run this once, then delete this file
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api/escrow_api.php';

echo "🔧 Updating Pandascrow Webhook URL...\n\n";

// Get auth token
echo "1️⃣ Getting authentication token...\n";
$token = get_pandascrow_token();

if (!$token) {
    die("❌ Failed to get auth token. Check your Pandascrow credentials in .env file.\n");
}

echo "✅ Token received\n\n";

// Update webhook
echo "2️⃣ Updating webhook URL...\n";
$result = update_pandascrow_webhook($token);

if ($result['success']) {
    echo "✅ Webhook URL updated successfully!\n\n";
    echo "📋 New webhook URL: " . url('webhook.php') . "\n";
    echo "📋 Full URL: " . ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . url('webhook.php') . "\n\n";
    echo "🎉 Done! You can now delete this file.\n";
} else {
    echo "❌ Failed to update webhook\n";
    echo "Error: " . ($result['error'] ?? 'Unknown error') . "\n";
    print_r($result);
}

echo "\n";
?>
