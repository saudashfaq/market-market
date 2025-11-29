<?php
/**
 * Test Data Creator for Polling
 * Creates test data to verify real-time updates
 */

require_once 'config.php';
require_once 'middlewares/auth.php';

// Check if user is logged in
$user = current_user();
if (!$user) {
    die('Please login first');
}

$pdo = db();

echo "<h1>🧪 Test Data Creator for Polling</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; background: #e8f5e8; padding: 10px; margin: 5px 0; }
    .error { color: red; background: #ffe8e8; padding: 10px; margin: 5px 0; }
    button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
    button:hover { background: #0056b3; }
</style>";

if ($_POST['action'] ?? false) {
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'create_listing':
                // Create test listing
                $stmt = $pdo->prepare("
                    INSERT INTO listings (user_id, name, description, asking_price, monthly_revenue, category, type, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', NOW(), NOW())
                ");
                $stmt->execute([
                    $user['id'],
                    'Test Listing ' . date('H:i:s'),
                    'Test listing for polling verification',
                    rand(1000, 5000),
                    rand(100, 500),
                    'website',
                    'website',
                ]);
                echo "<div class='success'>✅ Test listing created! Check console for real-time update.</div>";
                break;
                
            case 'create_notification':
                // Create test notification
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                    VALUES (?, ?, ?, 'info', 0, NOW())
                ");
                $stmt->execute([
                    $user['id'],
                    'Test Notification',
                    'This is a test notification for polling at ' . date('H:i:s')
                ]);
                echo "<div class='success'>✅ Test notification created! Check console for real-time update.</div>";
                break;
                
            case 'create_offer':
                // Get a random listing to make offer on
                $stmt = $pdo->prepare("SELECT id FROM listings WHERE user_id != ? AND status = 'approved' LIMIT 1");
                $stmt->execute([$user['id']]);
                $listing = $stmt->fetch();
                
                if ($listing) {
                    $stmt = $pdo->prepare("
                        INSERT INTO offers (user_id, listing_id, amount, message, status, created_at) 
                        VALUES (?, ?, ?, ?, 'pending', NOW())
                    ");
                    $stmt->execute([
                        $user['id'],
                        $listing['id'],
                        rand(500, 2000),
                        'Test offer for polling at ' . date('H:i:s')
                    ]);
                    echo "<div class='success'>✅ Test offer created! Check console for real-time update.</div>";
                } else {
                    echo "<div class='error'>❌ No listings available to make offer on.</div>";
                }
                break;
                
            case 'reset_timestamps':
                // Reset polling timestamps to force data reload
                echo "<div class='success'>✅ Run this in browser console to reset timestamps:</div>";
                echo "<div style='background: #f4f4f4; padding: 10px; font-family: monospace;'>";
                echo "localStorage.removeItem('polling_timestamps');<br>";
                echo "console.log('Timestamps reset - refresh page to see all data');";
                echo "</div>";
                break;
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
    }
}

echo "<h2>🎯 Create Test Data</h2>";
echo "<p>Create test data to verify real-time polling updates:</p>";

echo "<form method='POST' style='margin: 10px 0;'>";
echo "<input type='hidden' name='action' value='create_listing'>";
echo "<button type='submit'>📋 Create Test Listing</button>";
echo "</form>";

echo "<form method='POST' style='margin: 10px 0;'>";
echo "<input type='hidden' name='action' value='create_notification'>";
echo "<button type='submit'>🔔 Create Test Notification</button>";
echo "</form>";

echo "<form method='POST' style='margin: 10px 0;'>";
echo "<input type='hidden' name='action' value='create_offer'>";
echo "<button type='submit'>💰 Create Test Offer</button>";
echo "</form>";

echo "<form method='POST' style='margin: 10px 0;'>";
echo "<input type='hidden' name='action' value='reset_timestamps'>";
echo "<button type='submit'>🔄 Reset Timestamps (Console Command)</button>";
echo "</form>";

echo "<h2>📊 Current User Info</h2>";
echo "<p><strong>User ID:</strong> " . $user['id'] . "</p>";
echo "<p><strong>Role:</strong> " . ($user['role'] ?? 'user') . "</p>";

echo "<h2>🧪 Testing Instructions</h2>";
echo "<ol>";
echo "<li>Open userDashboard or home page in another tab</li>";
echo "<li>Open browser console (F12)</li>";
echo "<li>Create test data using buttons above</li>";
echo "<li>Watch console for real-time updates</li>";
echo "<li>Look for messages like: '📋 New listings detected: 1'</li>";
echo "</ol>";

echo "<h2>🔍 Expected Console Messages</h2>";
echo "<div style='background: #f4f4f4; padding: 10px; font-family: monospace;'>";
echo "📦 Parsed response data: {success: true, data: {listings: [...]}, ...}<br>";
echo "📋 New listings detected: 1<br>";
echo "🎯 Calling listings callback...<br>";
echo "✅ listings callback completed<br>";
echo "📊 Updating dashboard with 1 new listings<br>";
echo "</div>";
?>

<script>
// Auto-refresh every 30 seconds to show latest data
setTimeout(() => {
    window.location.reload();
}, 30000);

console.log('🧪 Test Data Creator loaded');
console.log('💡 Tip: Keep console open on dashboard/home page while creating test data');
</script>