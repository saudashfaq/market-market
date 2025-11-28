<?php
/**
 * Temporary file to show webhook URL
 * Copy this URL and update manually in Pandascrow dashboard
 */

require_once __DIR__ . '/config.php';

echo "🔧 Pandascrow Webhook URL Configuration\n";
echo "==========================================\n\n";

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? '91.99.115.150';
$webhookUrl = $protocol . $host . '/webhook.php';

echo "📋 Your Webhook URL:\n";
echo "   " . $webhookUrl . "\n\n";

echo "📝 Steps to Update:\n";
echo "   1. Login to Pandascrow Dashboard\n";
echo "   2. Go to Settings → Application/Webhooks\n";
echo "   3. Update webhook URL to: " . $webhookUrl . "\n";
echo "   4. Save changes\n\n";

echo "✅ Current Configuration:\n";
echo "   - PANDASCROW_MODE: " . (defined('PANDASCROW_MODE') ? PANDASCROW_MODE : 'Not set') . "\n";
echo "   - PANDASCROW_UUID: " . (defined('PANDASCROW_UUID') ? PANDASCROW_UUID : 'Not set') . "\n";
echo "   - BASE URL: " . BASE . "\n";
echo "   - Webhook Path: /webhook.php\n\n";

echo "🧪 Test Webhook:\n";
echo "   curl -X POST " . $webhookUrl . " \\\n";
echo "     -H 'Content-Type: application/json' \\\n";
echo "     -d '{\"event\":\"test\",\"data\":{}}'\n\n";

echo "🎉 Done! Delete this file after updating Pandascrow dashboard.\n";
?>
