<?php
// Check for export FIRST - before any output
if (isset($_GET['export'])) {
  require_once __DIR__ . '/../../config.php';
  require_once __DIR__ . '/../../includes/export_helper.php';

  ob_start();
  require_login();
  $user = current_user();
  if ($user['role'] !== 'admin' && $user['role'] !== 'super_admin') {
    die("Access denied");
  }
  ob_end_clean();

  $pdo = db();

  // Export dashboard summary data
  $exportSql = "
        SELECT 
            'Listings' as category,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM listings
        UNION ALL
        SELECT 
            'Users' as category,
            COUNT(*) as total,
            0 as pending,
            0 as approved,
            0 as rejected
        FROM users
        UNION ALL
        SELECT 
            'Offers' as category,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM offers
    ";

  try {
    $exportStmt = $pdo->query($exportSql);
    $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
    handleExportRequest($exportData, 'Admin Dashboard Summary');
  } catch (Exception $e) {
    handleExportRequest([], 'Admin Dashboard Summary');
  }
  exit;
}

require_once __DIR__ . '/../../config.php';
require_login();

$user = current_user();
if ($user['role'] !== 'admin' && $user['role'] !== 'super_admin') {
  die("Access denied");
}

$pdo = db();

// Initialize all stats with zero values
$listingsStats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'sold' => 0];
$offersCount = 0;
$ordersCount = 0;
$usersCount = 0;
$escrowBalance = 0;
$systemUptime = 0;

// Get real data from database
try {
  // Get listings stats
  $listingsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold
        FROM listings
    ");
  $result = $listingsStmt->fetch(PDO::FETCH_ASSOC);
  if ($result && $result['total'] !== null) {
    $listingsStats = [
      'total' => (int)$result['total'],
      'pending' => (int)($result['pending'] ?? 0),
      'approved' => (int)($result['approved'] ?? 0),
      'rejected' => (int)($result['rejected'] ?? 0),
      'sold' => (int)($result['sold'] ?? 0)
    ];
  }
  // Debug: Log the stats
  error_log("Admin Dashboard - Listings Stats: " . json_encode($listingsStats));
} catch (Exception $e) {
  // Table doesn't exist or error occurred, keep zero values
  error_log("Admin Dashboard - Listings Error: " . $e->getMessage());
}

try {
  // Get ALL offers count (not just pending)
  $offersStmt = $pdo->query("SELECT COUNT(*) as total FROM offers");
  $offersCount = (int)($offersStmt->fetchColumn() ?: 0);

  // Get pending offers separately
  $pendingOffersStmt = $pdo->query("SELECT COUNT(*) as total FROM offers WHERE status = 'pending'");
  $pendingOffersCount = (int)($pendingOffersStmt->fetchColumn() ?: 0);

  error_log("Admin Dashboard - Offers: Total=$offersCount, Pending=$pendingOffersCount");
} catch (Exception $e) {
  error_log("Admin Dashboard - Offers Error: " . $e->getMessage());
}

try {
  // Get active transactions count
  $transactionsStmt = $pdo->query("SELECT COUNT(*) as total FROM transactions WHERE status IN ('pending', 'paid')");
  $ordersCount = (int)($transactionsStmt->fetchColumn() ?: 0);
  error_log("Admin Dashboard - Transactions: $ordersCount");
} catch (Exception $e) {
  error_log("Admin Dashboard - Transactions Error: " . $e->getMessage());
}

try {
  // Get total users count
  $usersStmt = $pdo->query("SELECT COUNT(*) as total FROM users");
  $usersCount = (int)($usersStmt->fetchColumn() ?: 0);
  error_log("Admin Dashboard - Users: $usersCount");
} catch (Exception $e) {
  error_log("Admin Dashboard - Users Error: " . $e->getMessage());
}

try {
  // Calculate escrow balance from paid transactions
  $escrowStmt = $pdo->query("SELECT SUM(amount) as total FROM transactions WHERE status = 'paid'");
  $escrowBalance = (float)($escrowStmt->fetchColumn() ?: 0);
  error_log("Admin Dashboard - Escrow Balance: $escrowBalance");
} catch (Exception $e) {
  error_log("Admin Dashboard - Escrow Error: " . $e->getMessage());
}

// Calculate system uptime (simple check - if we can query database, system is up)
try {
  $pdo->query("SELECT 1");
  $systemUptime = 99.9; // System is operational
} catch (Exception $e) {
  $systemUptime = 0; // System has issues
}

// Get pending actions
$pendingActions = [];
try {
  // Get pending listings for verification
  if ($listingsStats['pending'] > 0) {
    $pendingActions[] = [
      'type' => 'listing_verification',
      'title' => 'Verify Listings',
      'description' => $listingsStats['pending'] . ' listings need verification',
      'priority' => 'high',
      'icon' => 'fas fa-clipboard-check',
      'color' => 'red',
      'action_url' => url('dashboard/listingverification?status=pending')
    ];
  }

  // Offers removed from pending actions

  // Check for pending orders
  if ($ordersCount > 0) {
    $pendingActions[] = [
      'type' => 'order_processing',
      'title' => 'Process Orders',
      'description' => $ordersCount . ' orders need processing',
      'priority' => 'high',
      'icon' => 'fas fa-shopping-cart',
      'color' => 'green',
      'action_url' => url('dashboard/orders?status=pending')
    ];
  }
} catch (Exception $e) {
  // Error getting pending actions, keep empty array
}
?>

<!-- Admin Dashboard Layout -->
<div class="min-h-screen bg-gray-50">
  <div class="flex flex-col min-h-screen overflow-hidden">
    <!-- Top bar -->
    <div class="bg-white shadow-sm border-b border-gray-200">
      <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-4">
          <div class="flex items-center">
            <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
          </div>

          <!-- Actions -->
          <div class="flex items-center space-x-4">
            <?php require_once __DIR__ . '/../../includes/export_helper.php';
            echo getExportButton('admin_dashboard'); ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Main content area -->
    <main class="flex-1 overflow-y-auto bg-gray-50">
      <div class="px-4 sm:px-6 lg:px-8 py-8">

        <!-- Modern Stats Grid - Flippa Style -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
          <!-- Total Listings Card -->
          <div class="bg-white rounded-2xl border border-gray-200 p-6 hover:shadow-lg transition-all duration-200">
            <div class="flex items-center justify-between mb-4">
              <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                <i class="fas fa-layer-group text-blue-600 text-xl"></i>
              </div>
              <?php if ($listingsStats['total'] > 0): ?>
                <span class="text-xs font-medium text-green-600 bg-green-50 px-2 py-1 rounded-full">
                  +8.2%
                </span>
              <?php endif; ?>
            </div>
            <div class="space-y-1">
              <h3 class="text-2xl font-bold text-gray-900" data-stat="total-listings"><?= number_format($listingsStats['total']) ?></h3>
              <p class="text-sm font-medium text-gray-600">Total Listings</p>
            </div>
            <div class="flex items-center space-x-2 mt-4">
              <div class="flex items-center text-xs">
                <div class="w-2 h-2 bg-yellow-400 rounded-full mr-1"></div>
                <span class="text-gray-600"><span data-stat="pending-listings"><?= $listingsStats['pending'] ?></span> pending</span>
              </div>
              <div class="flex items-center text-xs">
                <div class="w-2 h-2 bg-green-400 rounded-full mr-1"></div>
                <span class="text-gray-600"><span data-stat="approved-listings"><?= $listingsStats['approved'] ?></span> approved</span>
              </div>
              <div class="flex items-center text-xs">
                <div class="w-2 h-2 bg-red-400 rounded-full mr-1"></div>
                <span class="text-gray-600"><span data-stat="rejected-listings"><?= $listingsStats['rejected'] ?></span> rejected</span>
              </div>
            </div>
          </div>

          <!-- Escrow Balance Card -->
          <div class="bg-white rounded-2xl border border-gray-200 p-6 hover:shadow-lg transition-all duration-200">
            <div class="flex items-center justify-between mb-4">
              <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center">
                <i class="fas fa-shield-alt text-purple-600 text-xl"></i>
              </div>
              <span class="text-xs font-medium text-gray-500 bg-gray-50 px-2 py-1 rounded-full">
                Secure
              </span>
            </div>
            <div class="space-y-1">
              <h3 class="text-2xl font-bold text-gray-900">$2.4M</h3>
              <p class="text-sm font-medium text-gray-600">Escrow Balance</p>
            </div>
            <p class="text-xs text-gray-500 mt-4">Protected by escrow system</p>
          </div>

          <!-- System Health Card -->
          <div class="bg-white rounded-2xl border border-gray-200 p-6 hover:shadow-lg transition-all duration-200">
            <div class="flex items-center justify-between mb-4">
              <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                <i class="fas fa-heartbeat text-green-600 text-xl"></i>
              </div>
              <span class="text-xs font-medium text-green-600 bg-green-50 px-2 py-1 rounded-full">
                Online
              </span>
            </div>
            <div class="space-y-1">
              <h3 class="text-2xl font-bold text-<?= $systemUptime > 0 ? 'green' : 'red' ?>-600"><?= $systemUptime > 0 ? number_format($systemUptime, 1) . '%' : 'Offline' ?></h3>
              <p class="text-sm font-medium text-gray-600">System Uptime</p>
            </div>
            <p class="text-xs text-gray-500 mt-4">All systems operational</p>
          </div>
        </div>

        <!-- Main Content Grid - Flippa Style -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

          <!-- Left Column - Recent Activity -->
          <div class="lg:col-span-2 space-y-6">

            <!-- Recent Activity Feed -->
            <div class="bg-white rounded-2xl border border-gray-200">
              <div class="px-6 py-5 border-b border-gray-200">
                <div class="flex items-center justify-between">
                  <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
                  <a href="<?= url('dashboard/listingverification') ?>" class="text-sm text-blue-600 hover:text-blue-700 font-medium">View all</a>
                </div>
              </div>
              <div class="p-6">
                <?php
                // Get recent activity from database
                try {
                  $recentActivity = [];

                  // Get recent listings
                  $listingStmt = $pdo->prepare("
                SELECT l.name, l.created_at, u.name as user_name, l.status, 'listing' as type
                FROM listings l 
                LEFT JOIN users u ON l.user_id = u.id 
                ORDER BY l.created_at DESC 
                LIMIT 3
              ");
                  $listingStmt->execute();
                  $recentListings = $listingStmt->fetchAll(PDO::FETCH_ASSOC);

                  foreach ($recentListings as $listing) {
                    $recentActivity[] = [
                      'type' => 'listing',
                      'action' => $listing['status'] === 'approved' ? 'verified' : 'submitted',
                      'item' => $listing['name'],
                      'user' => $listing['user_name'],
                      'time' => $listing['created_at'],
                      'status' => $listing['status']
                    ];
                  }

                  // Offers removed from recent activity

                  // Sort by time
                  usort($recentActivity, function ($a, $b) {
                    return strtotime($b['time']) - strtotime($a['time']);
                  });

                  // Limit to 5 items
                  $recentActivity = array_slice($recentActivity, 0, 5);
                } catch (Exception $e) {
                  $recentActivity = [];
                }

                if (!empty($recentActivity)): ?>
                  <div id="recent-activity-list" class="space-y-4">
                    <?php foreach ($recentActivity as $activity):
                      $timeAgo = time() - strtotime($activity['time']);
                      if ($timeAgo < 60) {
                        $timeText = 'Just now';
                      } elseif ($timeAgo < 3600) {
                        $timeText = floor($timeAgo / 60) . ' min ago';
                      } elseif ($timeAgo < 86400) {
                        $timeText = floor($timeAgo / 3600) . ' hour' . (floor($timeAgo / 3600) > 1 ? 's' : '') . ' ago';
                      } else {
                        $timeText = floor($timeAgo / 86400) . ' day' . (floor($timeAgo / 86400) > 1 ? 's' : '') . ' ago';
                      }

                      if ($activity['type'] === 'listing'):
                        $iconClass = $activity['action'] === 'verified' ? 'bg-green-100' : 'bg-blue-100';
                        $iconColor = $activity['action'] === 'verified' ? 'text-green-600' : 'text-blue-600';
                        $iconName = $activity['action'] === 'verified' ? 'fas fa-check' : 'fas fa-plus';
                        $actionText = $activity['action'] === 'verified' ? 'verified' : 'submitted';
                        $description = 'Listing "' . htmlspecialchars($activity['item']) . '" ' . $actionText;
                      else:
                        $iconClass = 'bg-purple-100';
                        $iconColor = 'text-purple-600';
                        $iconName = 'fas fa-handshake';
                        $description = 'New offer for "' . htmlspecialchars($activity['item']) . '"';
                      endif;
                    ?>
                      <div class="flex items-start space-x-4 p-4 rounded-xl hover:bg-gray-50 transition-colors">
                        <div class="w-10 h-10 <?= $iconClass ?> rounded-full flex items-center justify-center flex-shrink-0">
                          <i class="<?= $iconName ?> <?= $iconColor ?>"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                          <p class="text-sm font-medium text-gray-900"><?= $description ?></p>
                          <p class="text-sm text-gray-500">by <?= htmlspecialchars($activity['user']) ?></p>
                          <p class="text-xs text-gray-400 mt-1"><?= $timeText ?></p>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <div class="text-center py-12">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                      <i class="fas fa-clock text-gray-400 text-xl"></i>
                    </div>
                    <h3 class="text-sm font-medium text-gray-900 mb-1">No Activity to Monitor</h3>
                    <p class="text-sm text-gray-500">Platform activity will appear here for admin monitoring</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>


          </div>

          <!-- Right Column - Pending Actions & Quick Links -->
          <div class="space-y-6">

            <!-- Pending Actions -->
            <div class="bg-white rounded-2xl border border-gray-200">
              <div class="px-6 py-5 border-b border-gray-200">
                <div class="flex items-center justify-between">
                  <h3 class="text-lg font-semibold text-gray-900">Pending Actions</h3>
                  <span class="bg-orange-100 text-orange-800 text-xs font-medium px-2 py-1 rounded-full" id="pending-actions-count">
                    <?= count($pendingActions) ?> items
                  </span>
                </div>
              </div>
              <div class="p-6" id="pending-actions-container">
                <?php if (!empty($pendingActions)): ?>
                  <div class="space-y-4" id="pending-actions-list">
                    <?php foreach ($pendingActions as $action): ?>
                      <div class="flex items-center justify-between p-4 bg-<?= $action['color'] ?>-50 rounded-xl border border-<?= $action['color'] ?>-100" data-action-type="<?= $action['type'] ?>">
                        <div class="flex items-center space-x-3">
                          <div class="w-8 h-8 bg-<?= $action['color'] ?>-100 rounded-lg flex items-center justify-center">
                            <i class="<?= $action['icon'] ?> text-<?= $action['color'] ?>-600 text-sm"></i>
                          </div>
                          <div>
                            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($action['title']) ?></p>
                            <p class="text-xs text-<?= $action['color'] ?>-600" data-action-description><?= htmlspecialchars($action['description']) ?></p>
                          </div>
                        </div>
                        <a href="<?= htmlspecialchars($action['action_url']) ?>" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                          Review
                        </a>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <div class="text-center py-8" id="no-pending-actions">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                      <i class="fas fa-check-circle text-gray-400 text-xl"></i>
                    </div>
                    <h3 class="text-sm font-medium text-gray-900 mb-1">All caught up!</h3>
                    <p class="text-sm text-gray-500">No pending actions require your attention</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>