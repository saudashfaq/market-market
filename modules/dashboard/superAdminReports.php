<?php
// Check for export FIRST
if (isset($_GET['export'])) {
  ob_start(); // Start output buffering
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../includes/export_helper.php';

require_login();

// If export, clean any buffered output
if (isset($_GET['export'])) {
  ob_end_clean();
}
$user = current_user();

// Check if user is admin/superadmin
if (!in_array($user['role'], ['admin', 'super_admin', 'superAdmin', 'superadmin'])) {
  die("Access denied - Admin access required");
}

$pdo = db();

// Initialize stats
$stats = [
  'total_revenue' => 0,
  'total_users' => 0,
  'total_listings' => 0,
  'total_orders' => 0,
  'pending_offers' => 0,
  'completed_orders' => 0,
  'revenue_this_month' => 0,
  'revenue_last_month' => 0,
  'users_this_month' => 0,
  'listings_this_month' => 0
];

try {
  // Get comprehensive stats
  $statsQuery = "
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)) as users_this_month,
            (SELECT COUNT(*) FROM listings) as total_listings,
            (SELECT COUNT(*) FROM listings WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)) as listings_this_month,
            (SELECT COUNT(*) FROM transactions) as total_orders,
            (SELECT COUNT(*) FROM transactions WHERE status = 'completed') as completed_orders,
            (SELECT COUNT(*) FROM offers WHERE status = 'pending') as pending_offers,
            (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed') as total_revenue,
            (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)) as revenue_this_month,
            (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)) as revenue_last_month
    ";

  $stats = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);

  // Calculate growth percentages
  $revenueGrowth = $stats['revenue_last_month'] > 0
    ? (($stats['revenue_this_month'] - $stats['revenue_last_month']) / $stats['revenue_last_month']) * 100
    : 0;

  // Get monthly revenue for chart (last 6 months)
  $monthlyRevenue = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%b') as month,
            COALESCE(SUM(amount), 0) as revenue
        FROM transactions 
        WHERE status = 'completed' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY created_at ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

  // If no data, create sample months with zero revenue for chart display
  if (empty($monthlyRevenue)) {
    $monthlyRevenue = [];
    for ($i = 5; $i >= 0; $i--) {
      $monthlyRevenue[] = [
        'month' => date('M', strtotime("-$i months")),
        'revenue' => 0
      ];
    }
  }

  // Get top categories
  $topCategories = $pdo->query("
        SELECT 
            c.name,
            COUNT(l.id) as listing_count,
            COALESCE(SUM(o.amount), 0) as revenue
        FROM categories c
        LEFT JOIN listings l ON c.id = l.category_id
        LEFT JOIN transactions o ON l.id = o.listing_id AND o.status = 'completed'
        GROUP BY c.id, c.name
        ORDER BY revenue DESC, listing_count DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

  // Get recent transactions
  $recentTransactions = $pdo->query("
        SELECT 
            o.id,
            o.amount,
            o.status,
            o.created_at,
            l.name as listing_name,
            buyer.name as buyer_name,
            seller.name as seller_name
        FROM transactions o
        LEFT JOIN listings l ON o.listing_id = l.id
        LEFT JOIN users buyer ON o.buyer_id = buyer.id
        LEFT JOIN users seller ON o.seller_id = seller.id
        ORDER BY o.created_at DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

  // Handle export request
  if (isset($_GET['export'])) {
    $exportTransactions = $pdo->query("
            SELECT 
                o.id,
                o.amount,
                o.status,
                o.created_at,
                l.name as listing_name,
                buyer.name as buyer_name,
                seller.name as seller_name
            FROM transactions o
            LEFT JOIN listings l ON o.listing_id = l.id
            LEFT JOIN users buyer ON o.buyer_id = buyer.id
            LEFT JOIN users seller ON o.seller_id = seller.id
            ORDER BY o.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    handleExportRequest($exportTransactions, 'System Reports');
  }
} catch (Exception $e) {
  error_log("Reports Error: " . $e->getMessage());
}
?>

<style>
  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(-10px);
    }

    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .animate-fade-in {
    animation: fadeIn 0.5s ease-out;
  }

  @keyframes pulse {

    0%,
    100% {
      opacity: 1;
    }

    50% {
      opacity: 0.7;
    }
  }

  .animate-pulse {
    animation: pulse 1s ease-in-out;
  }

  /* Platform Overview Styles */
  .overview-card {
    transition: all 0.3s ease;
  }

  .overview-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  }
</style>

<div class="max-w-7xl mx-auto p-6">

  <!-- Header -->
  <div class="flex justify-between items-center mb-8">
    <div>
      <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <div class="rounded-full bg-blue-100 p-3">
          <i class="fa-solid fa-chart-line text-blue-600 text-xl"></i>
        </div>
        Business Reports & Analytics
      </h1>
      <p class="text-gray-600 mt-2">Comprehensive platform performance overview</p>
    </div>
    <div class="flex gap-3">
      <?= getExportButton('reports') ?>
      <button onclick="location.reload()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
        <i class="fa fa-refresh"></i> Refresh
      </button>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Revenue -->
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-xl p-6 shadow-lg">
      <div class="flex justify-between items-start">
        <div>
          <p class="text-blue-100 text-sm font-medium">Total Revenue</p>
          <p id="total-revenue" class="text-3xl font-bold mt-2" data-value="<?= $stats['total_revenue'] ?>">
            $<?= number_format($stats['total_revenue']) ?>
          </p>
          <p class="text-blue-100 text-sm mt-2">
            <i class="fa fa-arrow-<?= $revenueGrowth >= 0 ? 'up' : 'down' ?> mr-1"></i>
            <?= number_format(abs($revenueGrowth), 1) ?>% from last month
          </p>
        </div>
        <div class="bg-white bg-opacity-20 p-3 rounded-lg">
          <i class="fa-solid fa-dollar-sign text-2xl"></i>
        </div>
      </div>
    </div>

    <!-- Total Users -->
    <div class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-xl p-6 shadow-lg">
      <div class="flex justify-between items-start">
        <div>
          <p class="text-green-100 text-sm font-medium">Total Users</p>
          <p id="total-users" class="text-3xl font-bold mt-2" data-value="<?= $stats['total_users'] ?>">
            <?= number_format($stats['total_users']) ?>
          </p>
          <p class="text-green-100 text-sm mt-2">
            <i class="fa fa-plus mr-1"></i>
            <?= $stats['users_this_month'] ?> this month
          </p>
        </div>
        <div class="bg-white bg-opacity-20 p-3 rounded-lg">
          <i class="fa-solid fa-users text-2xl"></i>
        </div>
      </div>
    </div>

    <!-- Total Listings -->
    <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white rounded-xl p-6 shadow-lg">
      <div class="flex justify-between items-start">
        <div>
          <p class="text-purple-100 text-sm font-medium">Total Listings</p>
          <p id="total-listings" class="text-3xl font-bold mt-2" data-value="<?= $stats['total_listings'] ?>">
            <?= number_format($stats['total_listings']) ?>
          </p>
          <p class="text-purple-100 text-sm mt-2">
            <i class="fa fa-plus mr-1"></i>
            <?= $stats['listings_this_month'] ?> this month
          </p>
        </div>
        <div class="bg-white bg-opacity-20 p-3 rounded-lg">
          <i class="fa-solid fa-list text-2xl"></i>
        </div>
      </div>
    </div>

    <!-- Completed Orders -->
    <div class="bg-gradient-to-br from-orange-500 to-orange-600 text-white rounded-xl p-6 shadow-lg">
      <div class="flex justify-between items-start">
        <div>
          <p class="text-orange-100 text-sm font-medium">Completed Orders</p>
          <p id="completed-orders" class="text-3xl font-bold mt-2" data-value="<?= $stats['completed_orders'] ?>">
            <?= number_format($stats['completed_orders']) ?>
          </p>
          <p class="text-orange-100 text-sm mt-2">
            <i class="fa fa-check mr-1"></i>
            <?= $stats['total_orders'] ?> total orders
          </p>
        </div>
        <div class="bg-white bg-opacity-20 p-3 rounded-lg">
          <i class="fa-solid fa-handshake text-2xl"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts Section -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Platform Overview -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
      <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
        <i class="fa fa-tachometer-alt text-blue-600 mr-2"></i>
        Platform Overview
      </h3>

      <div class="grid grid-cols-2 gap-4 mb-6">
        <!-- Revenue This Month -->
        <div class="bg-gradient-to-r from-blue-50 to-blue-100 p-4 rounded-lg">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-blue-600 text-sm font-medium">This Month</p>
              <p class="text-2xl font-bold text-blue-800">$<?= number_format($stats['revenue_this_month']) ?></p>
            </div>
            <div class="bg-blue-200 p-2 rounded-full">
              <i class="fa fa-dollar-sign text-blue-600"></i>
            </div>
          </div>
        </div>

        <!-- Revenue Last Month -->
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-4 rounded-lg">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600 text-sm font-medium">Last Month</p>
              <p class="text-2xl font-bold text-gray-800">$<?= number_format($stats['revenue_last_month']) ?></p>
            </div>
            <div class="bg-gray-200 p-2 rounded-full">
              <i class="fa fa-calendar text-gray-600"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Growth Indicator -->
      <div class="border-t pt-4">
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-600">Revenue Growth</span>
          <?php if ($revenueGrowth > 0): ?>
            <span class="flex items-center text-green-600 text-sm font-semibold">
              <i class="fa fa-arrow-up mr-1"></i>
              +<?= number_format($revenueGrowth, 1) ?>%
            </span>
          <?php elseif ($revenueGrowth < 0): ?>
            <span class="flex items-center text-red-600 text-sm font-semibold">
              <i class="fa fa-arrow-down mr-1"></i>
              <?= number_format($revenueGrowth, 1) ?>%
            </span>
          <?php else: ?>
            <span class="flex items-center text-gray-600 text-sm font-semibold">
              <i class="fa fa-minus mr-1"></i>
              0%
            </span>
          <?php endif; ?>
        </div>

        <!-- Progress Bar -->
        <div class="mt-2">
          <div class="w-full bg-gray-200 rounded-full h-2">
            <?php
            $progressWidth = min(100, abs($revenueGrowth) * 2); // Scale for visual effect
            $progressColor = $revenueGrowth > 0 ? 'bg-green-500' : ($revenueGrowth < 0 ? 'bg-red-500' : 'bg-gray-400');
            ?>
            <div class="<?= $progressColor ?> h-2 rounded-full transition-all duration-300" style="width: <?= $progressWidth ?>%"></div>
          </div>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="mt-6 grid grid-cols-3 gap-4 pt-4 border-t">
        <div class="text-center">
          <p class="text-2xl font-bold text-purple-600"><?= $stats['users_this_month'] ?></p>
          <p class="text-xs text-gray-500">New Users</p>
        </div>
        <div class="text-center">
          <p class="text-2xl font-bold text-orange-600"><?= $stats['listings_this_month'] ?></p>
          <p class="text-xs text-gray-500">New Listings</p>
        </div>
        <div class="text-center">
          <p class="text-2xl font-bold text-green-600"><?= $stats['pending_offers'] ?></p>
          <p class="text-xs text-gray-500">Pending Offers</p>
        </div>
      </div>
    </div>

    <!-- Top Categories -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
      <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
        <i class="fa fa-tags text-purple-600 mr-2"></i>
        Top Categories by Revenue
      </h3>
      <?php if (!empty($topCategories)): ?>
        <div class="space-y-4">
          <?php foreach ($topCategories as $cat): ?>
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div class="flex-1">
                <p class="font-medium text-gray-900"><?= htmlspecialchars($cat['name']) ?></p>
                <p class="text-sm text-gray-500"><?= $cat['listing_count'] ?> listings</p>
              </div>
              <div class="text-right">
                <p class="font-bold text-gray-900">$<?= number_format($cat['revenue']) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="flex flex-col items-center justify-center py-12">
          <i class="fa fa-tags text-gray-300 text-5xl mb-4"></i>
          <p class="text-gray-500">No category data available</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Transactions -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200">
      <h3 class="text-lg font-semibold text-gray-900 flex items-center">
        <i class="fa fa-clock text-green-600 mr-2"></i>
        Recent Transactions
      </h3>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase">Order ID</th>
            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase">Listing</th>
            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase">Buyer</th>
            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase">Seller</th>
            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
          </tr>
        </thead>
        <tbody id="transactions-table" class="divide-y divide-gray-200">
          <?php if (!empty($recentTransactions)): ?>
            <?php foreach ($recentTransactions as $txn): ?>
              <tr class="hover:bg-gray-50">
                <td class="py-4 px-6">
                  <span class="font-medium text-gray-900">ORD<?= $txn['id'] ?></span>
                </td>
                <td class="py-4 px-6">
                  <span class="text-gray-900"><?= htmlspecialchars($txn['listing_name'] ?? 'N/A') ?></span>
                </td>
                <td class="py-4 px-6">
                  <span class="text-gray-700"><?= htmlspecialchars($txn['buyer_name'] ?? 'N/A') ?></span>
                </td>
                <td class="py-4 px-6">
                  <span class="text-gray-700"><?= htmlspecialchars($txn['seller_name'] ?? 'N/A') ?></span>
                </td>
                <td class="py-4 px-6">
                  <span class="font-semibold text-gray-900">$<?= number_format($txn['amount']) ?></span>
                </td>
                <td class="py-4 px-6">
                  <span class="px-2 py-1 rounded-full text-xs font-medium <?= $txn['status'] == 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                    <?= ucfirst($txn['status']) ?>
                  </span>
                </td>
                <td class="py-4 px-6">
                  <span class="text-sm text-gray-600"><?= date('M j, Y', strtotime($txn['created_at'])) ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" class="py-12 text-center text-gray-500">
                <i class="fa fa-inbox text-4xl mb-2"></i>
                <p>No transactions yet</p>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<script>
  // BASE constant already defined in dashboard.php
  // Platform Overview - Chart replaced with overview cards
  console.log('✅ Platform Overview loaded successfully');
</script>

<!-- Polling Integration -->
<!-- Polling Integration -->
<!-- Polling logic is handled by public/js/polling-init.js loaded in dashboard.php -->
```