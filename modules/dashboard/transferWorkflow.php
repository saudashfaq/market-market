<?php
// Check for export FIRST - before any output
if (isset($_GET['export'])) {
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../includes/export_helper.php';
    
    ob_start();
    require_login();
    ob_end_clean();
    
    $pdo = db();
    
    try {
        $exportSql = "
            SELECT ord.id, ord.amount, ord.status, ord.created_at,
                   b.name as buyer_name, b.email as buyer_email,
                   s.name as seller_name, s.email as seller_email,
                   l.name as listing_name
            FROM orders ord
            LEFT JOIN users b ON ord.buyer_id = b.id
            LEFT JOIN users s ON ord.seller_id = s.id
            LEFT JOIN listings l ON ord.listing_id = l.id
            ORDER BY ord.created_at DESC
        ";
        
        $exportStmt = $pdo->query($exportSql);
        $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
        
        handleExportRequest($exportData, 'Transfer Workflow Report');
    } catch (Exception $e) {
        handleExportRequest([], 'Transfer Workflow Report');
    }
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/pagination_helper.php';
require_login();

$pdo = db();

// Get pagination parameters
$page = getCurrentPage('pg');
$perPage = 10;

// Setup search and filter conditions
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

// Since this might be orders or transactions, let's check if orders table exists
try {
    $whereClause = 'WHERE 1=1';
    $params = [];

    if ($search) {
        $whereClause .= ' AND (buyer_name LIKE :search OR seller_name LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }
    if ($status) {
        $whereClause .= ' AND status = :status';
        $params[':status'] = $status;
    }

    // Try to get orders data
    $sql = "
        SELECT 
            ord.id,
            ord.amount,
            ord.status,
            ord.created_at,
            b.name as buyer_name,
            s.name as seller_name,
            l.name as listing_name
        FROM orders ord
        LEFT JOIN users b ON ord.buyer_id = b.id
        LEFT JOIN users s ON ord.seller_id = s.id
        LEFT JOIN listings l ON ord.listing_id = l.id
        $whereClause
        ORDER BY ord.created_at DESC
    ";

    $countSql = "
        SELECT COUNT(*) as total
        FROM orders ord
        LEFT JOIN users b ON ord.buyer_id = b.id
        LEFT JOIN users s ON ord.seller_id = s.id
        LEFT JOIN listings l ON ord.listing_id = l.id
        $whereClause
    ";

    $result = getCustomPaginationData($pdo, $sql, $countSql, $params, $page, $perPage);
    $transfers = $result['data'];
    $pagination = $result['pagination'];

} catch (Exception $e) {
    // If orders table doesn't exist, show empty state
    $transfers = [];
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
  <title>Transfer Workflow Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
</head>
<body class="bg-gray-50">

<div class="max-w-7xl mx-auto p-4 md:p-6">
  <!-- Header -->
  <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
    <div>
      <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Transfer Workflow</h1>
      <p class="text-gray-500 mt-1">Monitor order transfers and transaction progress</p>
    </div>
    <div class="flex items-center gap-4">
      <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1.5 rounded-full">
        <i class="fa fa-eye mr-1.5"></i> Read-only view
      </span>
      <?php require_once __DIR__ . '/../../includes/export_helper.php'; echo getExportButton('transfer_workflow'); ?>
    </div>
  </div>

  <!-- Search and Filter -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
      <input type="hidden" name="p" value="dashboard">
      <input type="hidden" name="page" value="transferWorkflow">
      
      <div class="flex-1 min-w-[200px]">
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fa fa-search mr-1"></i>Search Transfers
        </label>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
               placeholder="Search by buyer, seller, or listing..." 
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
      </div>
      
      <div class="min-w-[150px]">
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fa fa-filter mr-1"></i>Status
        </label>
        <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
          <option value="">All Status</option>
          <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
          <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
          <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
          <option value="disputed" <?= $status === 'disputed' ? 'selected' : '' ?>>Disputed</option>
        </select>
      </div>
      
      <div class="flex gap-2">
        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
          <i class="fa fa-search mr-2"></i>Filter
        </button>
        <a href="?p=dashboard&page=transferWorkflow" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors flex items-center">
          <i class="fa fa-times mr-2"></i>Clear
        </a>
      </div>
    </form>
  </div>

  <!-- Stats Cards -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 mb-8">
    <?php
    $statusCounts = ['pending' => 0, 'in_progress' => 0, 'completed' => 0, 'disputed' => 0];
    foreach ($transfers as $transfer) {
        if (isset($statusCounts[$transfer['status']])) {
            $statusCounts[$transfer['status']]++;
        }
    }
    ?>
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100 flex flex-col items-center">
      <div class="bg-gray-100 p-3 rounded-lg mb-3">
        <i class="fas fa-circle text-gray-500 text-xl"></i>
      </div>
      <h2 class="text-2xl md:text-3xl font-bold text-gray-900"><?= $statusCounts['pending'] ?></h2>
      <p class="text-gray-600 text-sm mt-1">Pending</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100 flex flex-col items-center">
      <div class="bg-blue-100 p-3 rounded-lg mb-3">
        <i class="fas fa-spinner text-blue-500 text-xl"></i>
      </div>
      <h2 class="text-2xl md:text-3xl font-bold text-gray-900"><?= $statusCounts['in_progress'] ?></h2>
      <p class="text-gray-600 text-sm mt-1">In Progress</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100 flex flex-col items-center">
      <div class="bg-green-100 p-3 rounded-lg mb-3">
        <i class="fas fa-check-circle text-green-500 text-xl"></i>
      </div>
      <h2 class="text-2xl md:text-3xl font-bold text-gray-900"><?= $statusCounts['completed'] ?></h2>
      <p class="text-gray-600 text-sm mt-1">Completed</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100 flex flex-col items-center">
      <div class="bg-red-100 p-3 rounded-lg mb-3">
        <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
      </div>
      <h2 class="text-2xl md:text-3xl font-bold text-gray-900"><?= $statusCounts['disputed'] ?></h2>
      <p class="text-gray-600 text-sm mt-1">Disputed</p>
    </div>
  </div>

  <!-- Transfer Workflow Table -->
  <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
      <div>
        <h2 class="text-lg md:text-xl font-semibold text-gray-900">Transfer Workflow</h2>
        <p class="text-sm text-gray-500 mt-1">Monitor order transfers and transaction progress</p>
      </div>
      <div class="mt-2 md:mt-0 flex items-center gap-2">
        <div class="relative">
          <i class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
          <input type="text" placeholder="Search orders..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <button class="text-gray-500 hover:text-gray-700 p-2 rounded-lg border border-gray-300">
          <i class="fa fa-filter"></i>
        </button>
        <button class="text-gray-500 hover:text-gray-700 p-2 rounded-lg border border-gray-300">
          <i class="fa fa-sort"></i>
        </button>
      </div>
    </div>
    
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-gray-600 border-b">
            <th class="py-3 px-3 text-left font-medium">Order ID</th>
            <th class="py-3 px-3 text-left font-medium">Buyer / Seller</th>
            <th class="py-3 px-3 text-left font-medium">Listing</th>
            <th class="py-3 px-3 text-left font-medium">Transfer Status</th>
            <th class="py-3 px-3 text-left font-medium">Progress</th>
            <th class="py-3 px-3 text-left font-medium">Buyer Confirmation</th>
            <th class="py-3 px-3 text-left font-medium">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <?php if (!empty($transfers)): ?>
            <?php foreach ($transfers as $transfer): ?>
              <tr class="hover:bg-gray-50 transition-colors">
                <td class="py-4 px-3">
                  <div class="font-semibold">ORD-<?= str_pad($transfer['id'], 3, '0', STR_PAD_LEFT) ?></div>
                  <div class="text-xs text-gray-500 mt-1"><?= date('d/m/Y', strtotime($transfer['created_at'])) ?></div>
                </td>
                <td class="py-4 px-3">
                  <div class="font-medium">Buyer: <?= htmlspecialchars($transfer['buyer_name']) ?></div>
                  <div class="text-xs text-gray-500 mt-1">Seller: <?= htmlspecialchars($transfer['seller_name']) ?></div>
                </td>
                <td class="py-4 px-3 font-medium"><?= htmlspecialchars($transfer['listing_name']) ?></td>
                <td class="py-4 px-3">
                  <?php
                  $statusConfig = [
                    'completed' => ['bg-green-100 text-green-700', 'fas fa-check-circle', 'Completed'],
                    'in_progress' => ['bg-blue-100 text-blue-700', 'fas fa-spinner', 'In Progress'],
                    'pending' => ['bg-yellow-100 text-yellow-700', 'fas fa-clock', 'Pending'],
                    'disputed' => ['bg-red-100 text-red-700', 'fas fa-exclamation-triangle', 'Disputed']
                  ];
                  $config = $statusConfig[$transfer['status']] ?? ['bg-gray-100 text-gray-700', 'fas fa-question', 'Unknown'];
                  ?>
                  <span class="inline-flex items-center px-3 py-1 rounded-full <?= $config[0] ?> text-xs font-medium">
                    <i class="<?= $config[1] ?> mr-1.5"></i> <?= $config[2] ?>
                  </span>
                </td>
                <td class="py-4 px-3">
                  <?php
                  $progress = $transfer['status'] === 'completed' ? 100 : 
                             ($transfer['status'] === 'in_progress' ? 60 : 
                             ($transfer['status'] === 'pending' ? 20 : 0));
                  $progressColor = $progress === 100 ? 'bg-green-500' : ($progress > 50 ? 'bg-blue-500' : 'bg-yellow-500');
                  ?>
                  <div class="w-full bg-gray-200 rounded-full h-2 mb-1">
                    <div class="<?= $progressColor ?> h-2 rounded-full" style="width:<?= $progress ?>%"></div>
                  </div>
                  <span class="text-xs text-gray-500"><?= $progress ?>%</span>
                </td>
                <td class="py-4 px-3">
                  <span class="inline-flex items-center px-3 py-1 rounded-full <?= $transfer['status'] === 'completed' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?> text-xs font-medium">
                    <i class="fas <?= $transfer['status'] === 'completed' ? 'fa-user-check' : 'fa-clock' ?> mr-1.5"></i> 
                    <?= $transfer['status'] === 'completed' ? 'Confirmed' : 'Pending' ?>
                  </span>
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
              <td colspan="7" class="py-16 text-center">
                <div class="flex flex-col items-center justify-center text-gray-400">
                  <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-exchange-alt text-3xl"></i>
                  </div>
                  <h3 class="text-lg font-medium text-gray-700 mb-2">No transfers found</h3>
                  <?php if ($search || $status): ?>
                    <p class="text-gray-500 mb-4">No transfer records match your search criteria.</p>
                    <a href="?p=dashboard&page=transferWorkflow" 
                       class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors inline-flex items-center">
                      <i class="fas fa-times mr-2"></i> Clear Filters
                    </a>
                  <?php else: ?>
                    <p class="text-gray-500 mb-4">No transfer workflows have been initiated yet.</p>
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 max-w-md mx-auto">
                      <div class="flex items-center gap-3 mb-3">
                        <i class="fas fa-info-circle text-blue-500 text-xl"></i>
                        <h4 class="font-semibold text-blue-900">What happens next?</h4>
                      </div>
                      <p class="text-blue-700 text-sm">Transfer workflows will appear here when buyers complete purchases and asset transfers begin.</p>
                    </div>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    
    <div class="flex flex-col md:flex-row justify-between items-center mt-6 pt-4 border-t border-gray-200">
      <p class="text-sm text-gray-500 mb-4 md:mb-0">Showing 4 of 4 orders</p>
      <div class="flex items-center gap-2">
        <button class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
          <i class="fa fa-chevron-left"></i>
        </button>
        <button class="px-3 py-1.5 text-sm border border-blue-500 bg-blue-50 text-blue-600 rounded-lg font-medium">1</button>
        <button class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">2</button>
        <button class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
          <i class="fa fa-chevron-right"></i>
        </button>
      </div>
    <!-- Pagination -->
    <div class="mt-6 pt-4 border-t border-gray-200">
      <?php 
      $extraParams = ['p' => 'dashboard', 'page' => 'transferWorkflow'];
      if ($search) $extraParams['search'] = $search;
      if ($status) $extraParams['status'] = $status;
      
      echo renderPagination($pagination, url('index.php'), $extraParams, 'pg'); 
      ?>
    </div>
  </div>

  <!-- Summary Section -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
    <!-- Status Summary -->
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Transfer Status Summary</h3>
      <div class="space-y-4">
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <div class="w-3 h-3 rounded-full bg-green-500 mr-3"></div>
            <span class="text-sm text-gray-600">Completed</span>
          </div>
          <span class="text-sm font-medium">1 order</span>
        </div>
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <div class="w-3 h-3 rounded-full bg-blue-500 mr-3"></div>
            <span class="text-sm text-gray-600">In Progress</span>
          </div>
          <span class="text-sm font-medium">1 order</span>
        </div>
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <div class="w-3 h-3 rounded-full bg-red-500 mr-3"></div>
            <span class="text-sm text-gray-600">Disputed</span>
          </div>
          <span class="text-sm font-medium">1 order</span>
        </div>
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <div class="w-3 h-3 rounded-full bg-gray-400 mr-3"></div>
            <span class="text-sm text-gray-600">Not Started</span>
          </div>
          <span class="text-sm font-medium">1 order</span>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
      <div class="space-y-3">
        <button class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors flex items-center justify-between">
          <div class="flex items-center">
            <i class="fa fa-file-alt text-blue-500 mr-3"></i>
            <span class="text-sm font-medium">Generate Transfer Report</span>
          </div>
          <i class="fa fa-chevron-right text-gray-400"></i>
        </button>
        <button class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors flex items-center justify-between">
          <div class="flex items-center">
            <i class="fa fa-chart-bar text-green-500 mr-3"></i>
            <span class="text-sm font-medium">View Progress Analytics</span>
          </div>
          <i class="fa fa-chevron-right text-gray-400"></i>
        </button>
        <button class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors flex items-center justify-between">
          <div class="flex items-center">
            <i class="fa fa-bell text-purple-500 mr-3"></i>
            <span class="text-sm font-medium">Status Notifications</span>
          </div>
          <i class="fa fa-chevron-right text-gray-400"></i>
        </button>
      </div>
    </div>
  </div>
</div>


<script>
// Ensure API_BASE_PATH is set
// Use PathUtils for API base path
if (!window.API_BASE_PATH && typeof BASE !== 'undefined') {
  window.API_BASE_PATH = BASE + 'api';
  console.log('🔧 API_BASE_PATH:', window.API_BASE_PATH);
}
</script>
<script src="<?= BASE ?>js/polling.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  console.log('🚀 Transfer Workflow polling initialization started');
  
  if (typeof startPolling === 'undefined') {
    console.error('❌ Polling system not loaded');
    return;
  }
  
  startPolling({
    orders: (newOrders) => {
      console.log('💳 New orders detected:', newOrders.length);
      if (newOrders.length > 0) {
        showNotification(`${newOrders.length} new order(s) for transfer`, 'info');
        
        // Reload page after 2 seconds
        setTimeout(() => {
          location.reload();
        }, 2000);
      }
    },
    
    listings: (newListings) => {
      console.log('📋 Listings updated:', newListings.length);
      
      // Check for sold listings
      const soldListings = newListings.filter(l => l.status === 'sold');
      if (soldListings.length > 0) {
        showNotification(`${soldListings.length} listing(s) marked as sold`, 'success');
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