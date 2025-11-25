<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../api/wallet_api.php';
require_once __DIR__ . '/../../includes/flash_helper.php';
require_login();

// Only superadmin can access
if (user_role() !== 'superadmin') {
    header("Location: " . url('index.php?p=dashboard'));
    exit;
}

$user = current_user();
$pdo = db();

// Get wallet balance from PandaScrow
$walletBalance = get_platform_wallet_balance('1month');
$balanceData = $walletBalance['success'] ? ($walletBalance['data'] ?? []) : [];

// Get platform earnings from database
$stmt = $pdo->query("
    SELECT 
        SUM(platform_fee) as total_earned,
        SUM(CASE WHEN platform_paid = 1 THEN platform_fee ELSE 0 END) as total_received,
        SUM(CASE WHEN platform_paid = 0 THEN platform_fee ELSE 0 END) as pending_earnings,
        COUNT(*) as total_transactions
    FROM transactions
    WHERE status IN ('completed', 'released')
");
$earnings = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent transactions with earnings
$recentStmt = $pdo->query("
    SELECT 
        t.id,
        t.amount,
        t.platform_fee,
        t.status,
        t.platform_paid,
        t.created_at,
        t.completed_at,
        l.name as listing_name,
        b.name as buyer_name,
        s.name as seller_name
    FROM transactions t
    LEFT JOIN listings l ON t.listing_id = l.id
    LEFT JOIN users b ON t.buyer_id = b.id
    LEFT JOIN users s ON t.seller_id = s.id
    WHERE t.status IN ('completed', 'released')
    ORDER BY t.created_at DESC
    LIMIT 20
");
$recentTransactions = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly earnings chart data
$monthlyStmt = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(platform_fee) as earnings,
        COUNT(*) as transactions
    FROM transactions
    WHERE status IN ('completed', 'released')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
");
$monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="text-gray-800 body-font py-6 px-4">
  <div class="max-w-7xl mx-auto">

    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 flex items-center gap-3">
        <i class="fas fa-wallet text-green-600"></i>
        Platform Earnings & Wallet
      </h1>
      <p class="text-gray-600 mt-2">Manage your commission earnings and wallet balance</p>
    </div>

    <?php displayFlashMessages(); ?>

    <!-- Wallet Balance Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      
      <!-- Total Earned -->
      <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between mb-4">
          <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
            <i class="fas fa-dollar-sign text-2xl"></i>
          </div>
          <span class="text-xs bg-white/20 px-3 py-1 rounded-full">All Time</span>
        </div>
        <h3 class="text-sm font-medium opacity-90 mb-1">Total Earned</h3>
        <p class="text-3xl font-bold">$<?= number_format($earnings['total_earned'] ?? 0, 2) ?></p>
        <p class="text-xs opacity-75 mt-2"><?= number_format($earnings['total_transactions'] ?? 0) ?> transactions</p>
      </div>

      <!-- Wallet Balance -->
      <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between mb-4">
          <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
            <i class="fas fa-wallet text-2xl"></i>
          </div>
          <span class="text-xs bg-white/20 px-3 py-1 rounded-full">Available</span>
        </div>
        <h3 class="text-sm font-medium opacity-90 mb-1">Wallet Balance</h3>
        <p class="text-3xl font-bold">
          $<?= number_format($balanceData['available_balance'] ?? 0, 2) ?>
        </p>
        <p class="text-xs opacity-75 mt-2">Ready to withdraw</p>
      </div>

      <!-- Pending Earnings -->
      <div class="bg-gradient-to-br from-yellow-500 to-orange-600 rounded-xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between mb-4">
          <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
            <i class="fas fa-clock text-2xl"></i>
          </div>
          <span class="text-xs bg-white/20 px-3 py-1 rounded-full">Pending</span>
        </div>
        <h3 class="text-sm font-medium opacity-90 mb-1">Pending Earnings</h3>
        <p class="text-3xl font-bold">$<?= number_format($earnings['pending_earnings'] ?? 0, 2) ?></p>
        <p class="text-xs opacity-75 mt-2">In escrow</p>
      </div>

      <!-- Locked Balance -->
      <div class="bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between mb-4">
          <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
            <i class="fas fa-lock text-2xl"></i>
          </div>
          <span class="text-xs bg-white/20 px-3 py-1 rounded-full">Locked</span>
        </div>
        <h3 class="text-sm font-medium opacity-90 mb-1">Locked Balance</h3>
        <p class="text-3xl font-bold">
          $<?= number_format($balanceData['locked_balance'] ?? 0, 2) ?>
        </p>
        <p class="text-xs opacity-75 mt-2">In active escrows</p>
      </div>

    </div>

    <!-- Action Buttons -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
      <a href="<?= url('index.php?p=dashboard&page=request_payout') ?>" 
         class="bg-gradient-to-r from-green-600 to-emerald-600 text-white py-4 px-6 rounded-xl font-semibold hover:opacity-90 transition-all flex items-center justify-center gap-3 shadow-lg">
        <i class="fas fa-money-bill-wave text-xl"></i>
        <span>Request Payout</span>
      </a>
      
      <a href="<?= url('index.php?p=dashboard&page=wallet_transactions') ?>" 
         class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-4 px-6 rounded-xl font-semibold hover:opacity-90 transition-all flex items-center justify-center gap-3 shadow-lg">
        <i class="fas fa-history text-xl"></i>
        <span>Transaction History</span>
      </a>
      
      <button onclick="refreshBalance()" 
              class="bg-gradient-to-r from-gray-600 to-gray-700 text-white py-4 px-6 rounded-xl font-semibold hover:opacity-90 transition-all flex items-center justify-center gap-3 shadow-lg">
        <i class="fas fa-sync-alt text-xl"></i>
        <span>Refresh Balance</span>
      </button>
    </div>

    <!-- Monthly Earnings Chart -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
      <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
        <i class="fas fa-chart-line text-blue-600"></i>
        Monthly Earnings (Last 12 Months)
      </h2>
      
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-200">
              <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Month</th>
              <th class="text-right py-3 px-4 text-sm font-semibold text-gray-700">Earnings</th>
              <th class="text-right py-3 px-4 text-sm font-semibold text-gray-700">Transactions</th>
              <th class="text-right py-3 px-4 text-sm font-semibold text-gray-700">Avg per Transaction</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($monthlyData as $month): 
              $avgPerTransaction = $month['transactions'] > 0 ? $month['earnings'] / $month['transactions'] : 0;
            ?>
            <tr class="border-b border-gray-100 hover:bg-gray-50">
              <td class="py-3 px-4 text-sm text-gray-900">
                <?= date('F Y', strtotime($month['month'] . '-01')) ?>
              </td>
              <td class="py-3 px-4 text-sm text-right font-semibold text-green-600">
                $<?= number_format($month['earnings'], 2) ?>
              </td>
              <td class="py-3 px-4 text-sm text-right text-gray-700">
                <?= number_format($month['transactions']) ?>
              </td>
              <td class="py-3 px-4 text-sm text-right text-gray-600">
                $<?= number_format($avgPerTransaction, 2) ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recent Transactions -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
      <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
        <i class="fas fa-receipt text-purple-600"></i>
        Recent Commission Earnings
      </h2>
      
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-200">
              <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">ID</th>
              <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Listing</th>
              <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Buyer → Seller</th>
              <th class="text-right py-3 px-4 text-sm font-semibold text-gray-700">Amount</th>
              <th class="text-right py-3 px-4 text-sm font-semibold text-gray-700">Commission</th>
              <th class="text-center py-3 px-4 text-sm font-semibold text-gray-700">Status</th>
              <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentTransactions as $tx): ?>
            <tr class="border-b border-gray-100 hover:bg-gray-50">
              <td class="py-3 px-4 text-sm text-gray-900 font-mono">
                #<?= $tx['id'] ?>
              </td>
              <td class="py-3 px-4 text-sm text-gray-900">
                <?= htmlspecialchars($tx['listing_name'] ?? 'N/A') ?>
              </td>
              <td class="py-3 px-4 text-sm text-gray-700">
                <div class="flex items-center gap-2">
                  <span><?= htmlspecialchars(substr($tx['buyer_name'] ?? 'N/A', 0, 15)) ?></span>
                  <i class="fas fa-arrow-right text-xs text-gray-400"></i>
                  <span><?= htmlspecialchars(substr($tx['seller_name'] ?? 'N/A', 0, 15)) ?></span>
                </div>
              </td>
              <td class="py-3 px-4 text-sm text-right text-gray-900">
                $<?= number_format($tx['amount'], 2) ?>
              </td>
              <td class="py-3 px-4 text-sm text-right font-semibold text-green-600">
                $<?= number_format($tx['platform_fee'], 2) ?>
              </td>
              <td class="py-3 px-4 text-center">
                <?php if ($tx['platform_paid']): ?>
                  <span class="inline-flex items-center gap-1 px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">
                    <i class="fas fa-check-circle"></i> Received
                  </span>
                <?php else: ?>
                  <span class="inline-flex items-center gap-1 px-3 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full">
                    <i class="fas fa-clock"></i> Pending
                  </span>
                <?php endif; ?>
              </td>
              <td class="py-3 px-4 text-sm text-gray-600">
                <?= date('M d, Y', strtotime($tx['created_at'])) ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</section>

<script>
function refreshBalance() {
  window.location.reload();
}
</script>
