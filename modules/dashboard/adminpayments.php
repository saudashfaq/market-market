
<?php
// Check for export FIRST - before any output
if (isset($_GET['export'])) {
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../includes/export_helper.php';
    
    // Suppress any output
    ob_start();
    require_login();
    ob_end_clean();
    
    $pdo = db();
    
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $type = $_GET['type'] ?? '';
    
    $whereClause = 'WHERE 1=1';
    $params = [];
    
    if ($search) {
        $whereClause .= ' AND (buyer_name LIKE :search OR seller_name LIKE :search OR transaction_id LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }
    if ($status) {
        $whereClause .= ' AND status = :status';
        $params[':status'] = $status;
    }
    if ($type) {
        $whereClause .= ' AND type = :type';
        $params[':type'] = $type;
    }
    
    $sql = "
        SELECT 
            p.id,
            p.transaction_id,
            p.amount,
            p.fee,
            p.status,
            p.type,
            p.created_at,
            b.name as buyer,
            s.name as seller
        FROM payments p
        LEFT JOIN users b ON p.buyer_id = b.id
        LEFT JOIN users s ON p.seller_id = s.id
        $whereClause
        ORDER BY p.created_at DESC
    ";
    
    try {
        $exportStmt = $pdo->prepare($sql);
        $exportStmt->execute($params);
        $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
        handleExportRequest($exportData, 'Payments Report');
    } catch (Exception $e) {
        die('Export error: ' . $e->getMessage());
    }
    exit;
}

// Normal page load
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/pagination_helper.php';
require_once __DIR__ . '/../../includes/log_helper.php';
require_once __DIR__ . '/../../includes/export_helper.php';
require_login();

// Check if user is admin or superadmin
// if (!in_array($_SESSION['role'], ['admin', 'superadmin'])) {
//     header('Location: ' . url('index.php?p=dashboard'));
//     exit;
// }

$pdo = db();

// Get pagination parameters
$page = getCurrentPage('pg');
$perPage = 10;

// Setup search and filter conditions
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';

// Log page access
$user = current_user();
$filters = [];
if ($search) $filters[] = "Search: {$search}";
if ($status) $filters[] = "Status: {$status}";
if ($type) $filters[] = "Type: {$type}";
$filterStr = !empty($filters) ? implode(', ', $filters) : 'No filters';
log_action(
    "Admin Payments Accessed",
    "Filters: {$filterStr}",
    "payment",
    $user['id'],
    $user['role']
);

// Try to get real payment data from database
try {
    $whereClause = 'WHERE 1=1';
    $params = [];

    if ($search) {
        $whereClause .= ' AND (buyer_name LIKE :search OR seller_name LIKE :search OR transaction_id LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }
    if ($status) {
        $whereClause .= ' AND status = :status';
        $params[':status'] = $status;
    }
    if ($type) {
        $whereClause .= ' AND type = :type';
        $params[':type'] = $type;
    }

    // Try to get payments data
    $sql = "
        SELECT 
            p.id,
            p.transaction_id,
            p.amount,
            p.fee,
            p.status,
            p.type,
            p.created_at,
            b.name as buyer,
            s.name as seller
        FROM payments p
        LEFT JOIN users b ON p.buyer_id = b.id
        LEFT JOIN users s ON p.seller_id = s.id
        $whereClause
        ORDER BY p.created_at DESC
    ";

    $countSql = "
        SELECT COUNT(*) as total
        FROM payments p
        LEFT JOIN users b ON p.buyer_id = b.id
        LEFT JOIN users s ON p.seller_id = s.id
        $whereClause
    ";
    
    $result = getCustomPaginationData($pdo, $sql, $countSql, $params, $page, $perPage);
    $payments = $result['data'];
    $pagination = $result['pagination'];

} catch (Exception $e) {
    // If payments table doesn't exist, show empty state
    $payments = [];
    $pagination = [
        'current_page' => 1,
        'per_page' => $perPage,
        'total_items' => 0,
        'total_pages' => 0,
        'has_prev' => false,
        'has_next' => false,
        'prev_page' => null,
        'next_page' => null,
        'start_item' => 0,
        'end_item' => 0
    ];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payments & Escrow Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    body {
      font-family: 'Inter', sans-serif;
    }
    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.375rem 0.75rem;
      border-radius: 6px;
      font-size: 0.75rem;
      font-weight: 500;
    }
    .table-container {
      overflow-x: auto;
    }
    .table-container table {
      min-width: 900px;
    }
    .hover-row:hover {
      background-color: #f9fafb;
    }
    @media (max-width: 768px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body class="bg-gray-50">

<div class="max-w-7xl mx-auto p-4 md:p-6">
  <!-- Header -->
  <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
    <div>
      <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Payments & Escrow</h1>
      <p class="text-gray-500 mt-1">Monitor transactions, escrow balances, and payment releases</p>
    </div>
    <div class="flex items-center gap-4">
      <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1.5 rounded-full">
        <i class="fa fa-eye mr-1.5"></i> Read-only view
      </span>
      <?= getExportButton('payments') ?>
    </div>
  </div>


  <!-- Search and Filter -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
      <input type="hidden" name="p" value="dashboard">
      <input type="hidden" name="page" value="adminpayments">
      
      <div class="flex-1 min-w-[200px]">
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fa fa-search mr-1"></i>Search Payments
        </label>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
               placeholder="Search by transaction ID, buyer, or seller..." 
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
      </div>
      
      <div class="min-w-[150px]">
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fa fa-filter mr-1"></i>Status
        </label>
        <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
          <option value="">All Status</option>
          <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
          <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
          <option value="escrow" <?= $status === 'escrow' ? 'selected' : '' ?>>In Escrow</option>
          <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
          <option value="refunded" <?= $status === 'refunded' ? 'selected' : '' ?>>Refunded</option>
        </select>
      </div>
      
      <div class="min-w-[150px]">
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fa fa-exchange-alt mr-1"></i>Type
        </label>
        <select name="type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
          <option value="">All Types</option>
          <option value="purchase" <?= $type === 'purchase' ? 'selected' : '' ?>>Purchase</option>
          <option value="refund" <?= $type === 'refund' ? 'selected' : '' ?>>Refund</option>
          <option value="payout" <?= $type === 'payout' ? 'selected' : '' ?>>Payout</option>
        </select>
      </div>
      
      <div class="flex gap-2">
        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
          <i class="fa fa-search mr-2"></i>Filter
        </button>
        <a href="?p=dashboard&page=adminpayments" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors flex items-center">
          <i class="fa fa-times mr-2"></i>Clear
        </a>
      </div>
    </form>
  </div>


  <!-- Stats Cards -->
  <div class="stats-grid grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-500">Held in Escrow</p>
          <p class="text-2xl md:text-3xl font-bold text-gray-900 mt-1">$0</p>
          <p class="text-gray-600 text-sm mt-1">0 transactions</p>
        </div>
        <div class="bg-blue-50 p-3 rounded-lg">
          <i class="fas fa-lock text-blue-500 text-xl"></i>
        </div>
      </div>
      <div class="mt-4 flex items-center text-xs text-gray-500">
        <i class="fa fa-info-circle mr-1"></i> No escrow funds
      </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-500">Total Released</p>
          <p class="text-2xl md:text-3xl font-bold text-gray-900 mt-1">$0</p>
          <p class="text-gray-600 text-sm mt-1">0 completed</p>
        </div>
        <div class="bg-green-50 p-3 rounded-lg">
          <i class="fas fa-check-circle text-green-500 text-xl"></i>
        </div>
      </div>
      <div class="mt-4 flex items-center text-xs text-gray-500">
        <i class="fa fa-info-circle mr-1"></i> No payments released
      </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-500">Commission Earned</p>
          <p class="text-2xl md:text-3xl font-bold text-gray-900 mt-1">$0</p>
          <p class="text-gray-600 text-sm mt-1">10% platform fee</p>
        </div>
        <div class="bg-purple-50 p-3 rounded-lg">
          <i class="fas fa-coins text-purple-500 text-xl"></i>
        </div>
      </div>
      <div class="mt-4 flex items-center text-xs text-gray-500">
        <i class="fa fa-info-circle mr-1"></i> No commissions yet
      </div>
    </div>
  </div>

  <!-- Payment Transactions -->
  <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
      <div>
        <h2 class="text-lg md:text-xl font-semibold text-gray-900">Payment Transactions</h2>
        <p class="text-sm text-gray-500 mt-1">Monitor escrow payments and releases (SuperAdmin controls)</p>
      </div>
      <div class="mt-2 md:mt-0 flex items-center gap-2">
        <div class="relative">
          <i class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
          <input type="text" placeholder="Search payments..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <button class="text-gray-500 hover:text-gray-700 p-2 rounded-lg border border-gray-300">
          <i class="fa fa-filter"></i>
        </button>
        <button class="text-gray-500 hover:text-gray-700 p-2 rounded-lg border border-gray-300">
          <i class="fa fa-sort"></i>
        </button>
      </div>
    </div>
    
    <div class="table-container">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-gray-600 border-b">
            <th class="py-3 px-3 text-left font-medium">Payment ID</th>
            <th class="py-3 px-3 text-left font-medium">Buyer / Seller</th>
            <th class="py-3 px-3 text-left font-medium">Amount</th>
            <th class="py-3 px-3 text-left font-medium">Commission</th>
            <th class="py-3 px-3 text-left font-medium">Net to Seller</th>
            <th class="py-3 px-3 text-left font-medium">Status</th>
            <th class="py-3 px-3 text-left font-medium">Dates</th>
            <th class="py-3 px-3 text-left font-medium">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <?php if (!empty($payments)): ?>
            <?php foreach ($payments as $payment): ?>
              <tr class="hover-row transition-colors">
                <td class="py-4 px-3">
                  <div class="font-semibold"><?= htmlspecialchars($payment['transaction_id']) ?></div>
                  <div class="text-xs text-gray-500 mt-1">Ref: ORD-<?= str_pad($payment['id'], 3, '0', STR_PAD_LEFT) ?></div>
                </td>
                <td class="py-4 px-3">
                  <div class="font-medium">From: <?= htmlspecialchars($payment['buyer']) ?></div>
                  <div class="text-xs text-gray-500 mt-1">To: <?= htmlspecialchars($payment['seller']) ?></div>
                </td>
                <td class="py-4 px-3 font-semibold">$<?= number_format($payment['amount']) ?></td>
                <td class="py-4 px-3">
                  <div>$<?= number_format($payment['fee']) ?></div>
                  <div class="text-xs text-gray-500 mt-1">10%</div>
                </td>
                <td class="py-4 px-3 font-semibold text-green-600">$<?= number_format($payment['amount'] - $payment['fee']) ?></td>
                <td class="py-4 px-3">
                  <?php
                  $statusConfig = [
                    'completed' => ['bg-green-100 text-green-700', 'fa-check-circle', 'Released'],
                    'pending' => ['bg-yellow-100 text-yellow-700', 'fa-hourglass-half', 'Pending'],
                    'escrow' => ['bg-blue-100 text-blue-700', 'fa-shield-alt', 'In Escrow'],
                    'failed' => ['bg-red-100 text-red-700', 'fa-times-circle', 'Failed'],
                    'refunded' => ['bg-purple-100 text-purple-700', 'fa-undo', 'Refunded']
                  ];
                  $config = $statusConfig[$payment['status']] ?? ['bg-gray-100 text-gray-700', 'fa-question', 'Unknown'];
                  ?>
                  <span class="status-badge <?= $config[0] ?>">
                    <i class="fa <?= $config[1] ?> mr-1.5"></i> <?= $config[2] ?>
                  </span>
                </td>
                <td class="py-4 px-3 text-xs text-gray-600">
                  <div>Date: <?= date('d/m/Y', strtotime($payment['created_at'])) ?></div>
                  <?php if ($payment['status'] === 'completed'): ?>
                    <div>Released: <?= date('d/m/Y', strtotime($payment['created_at'] . ' +4 days')) ?></div>
                  <?php endif; ?>
                </td>
                <td class="py-4 px-3">
                  <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    <i class="fa fa-eye mr-1"></i> View
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="8" class="py-16 text-center">
                <div class="flex flex-col items-center justify-center text-gray-400">
                  <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-credit-card text-3xl"></i>
                  </div>
                  <h3 class="text-lg font-medium text-gray-700 mb-2">No payments found</h3>
                  <?php if ($search || $status || $type): ?>
                    <p class="text-gray-500 mb-4">No payment records match your search criteria.</p>
                    <a href="?p=dashboard&page=adminpayments" 
                       class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors inline-flex items-center">
                      <i class="fas fa-times mr-2"></i> Clear Filters
                    </a>
                  <?php else: ?>
                    <p class="text-gray-500 mb-4">No payment transactions have been processed yet.</p>
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 max-w-md mx-auto">
                      <div class="flex items-center gap-3 mb-3">
                        <i class="fas fa-info-circle text-blue-500 text-xl"></i>
                        <h4 class="font-semibold text-blue-900">About Payments</h4>
                      </div>
                      <p class="text-blue-700 text-sm">Payment transactions will appear here when buyers make purchases and funds are processed through escrow.</p>
                    </div>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    

    <!-- Pagination -->
    <div class="mt-6 pt-4 border-t border-gray-200">
      <?php 
      $extraParams = ['p' => 'dashboard', 'page' => 'adminpayments'];
      if ($search) $extraParams['search'] = $search;
      if ($status) $extraParams['status'] = $status;
      if ($type) $extraParams['type'] = $type;
      
      echo renderPagination($pagination, url('index.php'), $extraParams, 'pg'); 
      ?>

    </div>
  </div>

  <!-- Summary Section -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
    <!-- Payment Status Summary -->
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Status Summary</h3>
      <div class="space-y-4">
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <div class="w-3 h-3 rounded-full bg-green-500 mr-2"></div>
            <span class="text-sm text-gray-600">Released</span>
          </div>
          <span class="text-sm font-medium">2 payments</span>
        </div>
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <div class="w-3 h-3 rounded-full bg-blue-500 mr-2"></div>
            <span class="text-sm text-gray-600">Authorized</span>
          </div>
          <span class="text-sm font-medium">2 payments</span>
        </div>
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <div class="w-3 h-3 rounded-full bg-yellow-500 mr-2"></div>
            <span class="text-sm text-gray-600">Pending</span>
          </div>
          <span class="text-sm font-medium">1 payment</span>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
      <div class="space-y-3">
        <button class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors flex items-center justify-between">
          <div class="flex items-center">
            <i class="fa fa-file-invoice-dollar text-blue-500 mr-3"></i>
            <span class="text-sm font-medium">Generate Payment Report</span>
          </div>
          <i class="fa fa-chevron-right text-gray-400"></i>
        </button>
        <button class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors flex items-center justify-between">
          <div class="flex items-center">
            <i class="fa fa-chart-line text-green-500 mr-3"></i>
            <span class="text-sm font-medium">View Revenue Analytics</span>
          </div>
          <i class="fa fa-chevron-right text-gray-400"></i>
        </button>
        <button class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors flex items-center justify-between">
          <div class="flex items-center">
            <i class="fa fa-bell text-purple-500 mr-3"></i>
            <span class="text-sm font-medium">Payment Notifications</span>
          </div>
          <i class="fa fa-chevron-right text-gray-400"></i>
        </button>
      </div>
    </div>
  </div>
</div>


<script>
// Ensure API_BASE_PATH is set
if (!window.API_BASE_PATH) {
  const path = window.location.pathname;
  window.API_BASE_PATH = (path.includes('/marketplace/') ? '/marketplace' : '') + '/api';
  console.log('🔧 API_BASE_PATH:', window.API_BASE_PATH);
}
</script>
<script src="<?= BASE ?>js/polling.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  console.log('🚀 Admin Payments polling initialization started');
  
  if (typeof startPolling === 'undefined') {
    console.error('❌ Polling system not loaded');
    return;
  }
  
  startPolling({
    orders: (newOrders) => {
      console.log('💳 New orders detected:', newOrders.length);
      if (newOrders.length > 0) {
        // Show notification
        showNotification(`${newOrders.length} new payment(s) received`, 'success');
        
        // Optionally reload the page to show new payments
        setTimeout(() => {
          location.reload();
        }, 2000);
      }
    }
  });
  
  function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    const colors = {
      info: 'bg-blue-500',
      success: 'bg-green-500',
      warning: 'bg-yellow-500',
      error: 'bg-red-500'
    };
    
    notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-fade-in`;
    notification.innerHTML = `
      <div class="flex items-center gap-2">
        <i class="fas fa-${type === 'success' ? 'check' : 'info'}-circle"></i>
        <span>${message}</span>
      </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
      notification.style.opacity = '0';
      setTimeout(() => notification.remove(), 300);
    }, 3000);
  }
});
</script>

</body>
</html>