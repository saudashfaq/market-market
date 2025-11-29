<?php
// Check for export FIRST - before any output
if (isset($_GET['export'])) {
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../includes/export_helper.php';
    
    ob_start();
    require_login();
    ob_end_clean();
    
    $pdo = db();
    $activeTab = $_GET['tab'] ?? 'offers';
    
    if ($activeTab === 'offers') {
        // Export offers
        $exportSql = "
            SELECT o.id, o.amount, o.status, o.created_at, o.message,
                   l.name AS listing_name, l.asking_price,
                   b.name AS buyer_name, b.email AS buyer_email
            FROM offers o
            LEFT JOIN listings l ON o.listing_id = l.id
            LEFT JOIN users b ON o.user_id = b.id
            ORDER BY o.created_at DESC
        ";
        $title = 'Offers Report';
    } else {
        // Export orders
        $exportSql = "
            SELECT ord.id, ord.amount, ord.platform_fee, ord.total, ord.status, ord.created_at,
                   b.name AS buyer_name, b.email AS buyer_email,
                   s.name AS seller_name, s.email AS seller_email
            FROM orders ord
            LEFT JOIN users b ON ord.buyer_id = b.id
            LEFT JOIN users s ON ord.seller_id = s.id
            ORDER BY ord.created_at DESC
        ";
        $title = 'Orders Report';
    }
    
    $exportStmt = $pdo->query($exportSql);
    $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
    
    handleExportRequest($exportData, $title);
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/pagination_helper.php';
require_login();
$pdo = db();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php




// ========== MAIN CODE ==========
// Simple debug check
try {
    $testOffers = $pdo->query("SELECT COUNT(*) FROM offers")->fetchColumn();
    $testOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    // Uncomment for debugging: echo "<!-- Debug: Offers: $testOffers, Orders: $testOrders -->";
} catch (Exception $e) {
    // Tables might not exist
    $testOffers = 0;
    $testOrders = 0;
}

// Get pagination parameters
$page = getCurrentPage('pg');
$perPage = 15;

// Get current tab
$activeTab = $_GET['tab'] ?? 'offers';
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

// Setup conditions
$whereClause = '';
$params = [];

if ($search) {
    if ($activeTab === 'offers') {
        $whereClause .= ' AND (l.name LIKE :search OR b.name LIKE :search OR b.email LIKE :search)';
    } else {
        $whereClause .= ' AND (b.name LIKE :search OR b.email LIKE :search OR s.name LIKE :search OR s.email LIKE :search)';
    }
    $params[':search'] = '%' . $search . '%';
}

if ($status) {
    if ($activeTab === 'offers') {
        $whereClause .= ' AND o.status = :status';
    } else {
        $whereClause .= ' AND ord.status = :status';
    }
    $params[':status'] = $status;
}

// Check if offers table exists and has data
$hasOffersData = false;
$offers = [];
$offersPagination = null;
$totalOffers = 0;
$pendingOffers = 0;

try {
    // Check if offers table exists and has data
    $checkOffersStmt = $pdo->query("SELECT COUNT(*) FROM offers");
    $totalOffersCount = $checkOffersStmt->fetchColumn();
    
    if ($totalOffersCount > 0) {
        $hasOffersData = true;
        
        // 🧠 Fetch Offers with pagination
        if ($activeTab === 'offers') {
            $offersSql = "
              SELECT 
                o.id AS offer_id,
                o.amount,
                o.status AS offer_status,
                o.created_at AS offer_date,
                o.message,
                l.name AS listing_title,
                l.asking_price,
                b.name AS buyer_name,
                b.email AS buyer_email
              FROM offers o
              LEFT JOIN listings l ON o.listing_id = l.id
              LEFT JOIN users b ON o.user_id = b.id
              WHERE 1=1 $whereClause
              ORDER BY o.created_at DESC
            ";
            
            $offersCountSql = "
              SELECT COUNT(*) as total
              FROM offers o
              LEFT JOIN listings l ON o.listing_id = l.id
              LEFT JOIN users b ON o.user_id = b.id
              WHERE 1=1 $whereClause
            ";
            
            try {
                $offersResult = getCustomPaginationData($pdo, $offersSql, $offersCountSql, $params, $page, $perPage);
                $offers = $offersResult['data'];
                $offersPagination = $offersResult['pagination'];
            } catch (Exception $e) {
                $offers = [];
                $offersPagination = null;
                error_log("Offers query failed: " . $e->getMessage());
            }
        }
        
        // Get total offers for stats
        try {
            $totalOffersStmt = $pdo->query("SELECT COUNT(*) as total FROM offers");
            $totalOffers = $totalOffersStmt->fetch()['total'];
            $pendingOffersStmt = $pdo->query("SELECT COUNT(*) as total FROM offers WHERE status = 'pending'");
            $pendingOffers = $pendingOffersStmt->fetch()['total'];
        } catch (Exception $e) {
            $totalOffers = 0;
            $pendingOffers = 0;
        }
    }
} catch (Exception $e) {
    // Table doesn't exist or error occurred - no data available
    $hasOffersData = false;
    // Error handled silently
}

// Check if orders table exists and has data
$hasOrdersData = false;
$orders = [];
$ordersPagination = null;
$totalOrders = 0;
$completedOrders = 0;

try {
    // Check if orders table exists and has data
    $checkOrdersStmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $totalOrdersCount = $checkOrdersStmt->fetchColumn();
    
    if ($totalOrdersCount > 0) {
        $hasOrdersData = true;
        
        // Fetch Orders with pagination
        if ($activeTab === 'orders') {
            $ordersSql = "
              SELECT 
                ord.id AS order_id,
                ord.amount,
                ord.platform_fee,
                ord.total,
                ord.status AS order_status,
                ord.created_at AS order_date,
                b.name AS buyer_name,
                b.email AS buyer_email,
                s.name AS seller_name,
                s.email AS seller_email
              FROM orders ord
              LEFT JOIN users b ON ord.buyer_id = b.id
              LEFT JOIN users s ON ord.seller_id = s.id
              WHERE 1=1 $whereClause
              ORDER BY ord.created_at DESC
            ";
            
            $ordersCountSql = "
              SELECT COUNT(*) as total
              FROM orders ord
              LEFT JOIN users b ON ord.buyer_id = b.id
              LEFT JOIN users s ON ord.seller_id = s.id
              WHERE 1=1 $whereClause
            ";
            
            try {
                $ordersResult = getCustomPaginationData($pdo, $ordersSql, $ordersCountSql, $params, $page, $perPage);
                $orders = $ordersResult['data'];
                $ordersPagination = $ordersResult['pagination'];
            } catch (Exception $e) {
                $orders = [];
                $ordersPagination = null;
                error_log("Orders query failed: " . $e->getMessage());
            }
        }
        
        // Get total orders for stats
        try {
            $totalOrdersStmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
            $totalOrders = $totalOrdersStmt->fetch()['total'];
            $completedOrdersStmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status = 'completed'");
            $completedOrders = $completedOrdersStmt->fetch()['total'];
        } catch (Exception $e) {
            $totalOrders = 0;
            $completedOrders = 0;
        }
    }
} catch (Exception $e) {
    // Table doesn't exist or error occurred - no data available
    $hasOrdersData = false;
    // Error handled silently
}

// Get stats for both tabs if not already set
if ($activeTab === 'offers' && !$hasOrdersData) {
    try {
        $totalOrdersStmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
        $totalOrders = $totalOrdersStmt->fetch()['total'];
        $completedOrdersStmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status = 'completed'");
        $completedOrders = $completedOrdersStmt->fetch()['total'];
    } catch (Exception $e) {
        $totalOrders = 0;
        $completedOrders = 0;
    }
} elseif ($activeTab === 'orders' && !$hasOffersData) {
    try {
        $totalOffersStmt = $pdo->query("SELECT COUNT(*) as total FROM offers");
        $totalOffers = $totalOffersStmt->fetch()['total'];
        $pendingOffersStmt = $pdo->query("SELECT COUNT(*) as total FROM offers WHERE status = 'pending'");
        $pendingOffers = $pendingOffersStmt->fetch()['total'];
    } catch (Exception $e) {
        $totalOffers = 0;
        $pendingOffers = 0;
    }
}

// Determine if we should show empty state
$showEmptyState = ($activeTab === 'offers' && !$hasOffersData) || ($activeTab === 'orders' && !$hasOrdersData);
?>

<div class="max-w-7xl mx-auto p-6">
  <!-- Header Section -->
  <div class="mb-8">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Offers & Orders</h1>
        <p class="text-gray-600 mt-1">Manage and track all offers and transactions</p>
      </div>
      <?php if (!$showEmptyState): ?>
      <div class="flex items-center gap-3">
        <?php require_once __DIR__ . '/../../includes/export_helper.php'; echo getExportButton($activeTab === 'offers' ? 'offers' : 'orders'); ?>
        <form method="GET" class="flex items-center gap-3">
          <input type="hidden" name="p" value="dashboard">
          <input type="hidden" name="page" value="superAdminOffers">
          <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
          
          <div class="relative">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 w-64">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          
          <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
            <option value="">All Status</option>
            <?php if ($activeTab === 'offers'): ?>
              <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
              <option value="accepted" <?= $status === 'accepted' ? 'selected' : '' ?>>Accepted</option>
              <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            <?php else: ?>
              <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>Processing</option>
              <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
              <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            <?php endif; ?>
          </select>
          
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            Search
          </button>
          
          <a href="?p=dashboard&page=superAdminOffers&tab=<?= htmlspecialchars($activeTab) ?>" class="px-4 py-2 bg-gray-500 text-white rounded-lg text-sm hover:bg-gray-600 transition-colors">
            Clear
          </a>
        </form>
      </div>
      <?php endif; ?>
    </div>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
      <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <div class="flex items-center">
          <div class="rounded-full bg-blue-100 p-3 mr-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
            </svg>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-600">Total Offers</p>
            <p id="total-offers-count" class="text-2xl font-bold text-gray-900" data-count="<?= $totalOffers ?>"><?= $totalOffers ?></p>
            <?php if ($totalOffers == 0): ?>
              <p class="text-xs text-gray-400 mt-1">No offers yet</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <div class="flex items-center">
          <div class="rounded-full bg-yellow-100 p-3 mr-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-600">Pending Offers</p>
            <p id="pending-offers-count" class="text-2xl font-bold text-gray-900" data-count="<?= $pendingOffers ?>"><?= $pendingOffers ?></p>
            <?php if ($pendingOffers == 0): ?>
              <p class="text-xs text-gray-400 mt-1">No pending offers</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <div class="flex items-center">
          <div class="rounded-full bg-green-100 p-3 mr-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
            </svg>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-600">Total Orders</p>
            <p id="total-orders-count" class="text-2xl font-bold text-gray-900" data-count="<?= $totalOrders ?>"><?= $totalOrders ?></p>
            <?php if ($totalOrders == 0): ?>
              <p class="text-xs text-gray-400 mt-1">No orders yet</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <div class="flex items-center">
          <div class="rounded-full bg-purple-100 p-3 mr-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-600">Completed Orders</p>
            <p id="completed-orders-count" class="text-2xl font-bold text-gray-900" data-count="<?= $completedOrders ?>"><?= $completedOrders ?></p>
            <?php if ($completedOrders == 0): ?>
              <p class="text-xs text-gray-400 mt-1">No completed orders</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($showEmptyState): ?>
  <!-- Empty State Banner -->
  <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-8 mb-8">
    <div class="text-center">
      <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
        <?php if ($activeTab === 'offers'): ?>
          <i class="fa fa-handshake text-blue-600 text-2xl"></i>
        <?php else: ?>
          <i class="fa fa-shopping-cart text-blue-600 text-2xl"></i>
        <?php endif; ?>
      </div>
      <h3 class="text-xl font-semibold text-gray-900 mb-3">
        <?php if ($activeTab === 'offers'): ?>
          No Offers Found
        <?php else: ?>
          No Orders Found
        <?php endif; ?>
      </h3>
      <p class="text-gray-600 mb-6 max-w-md mx-auto">
        <?php if ($activeTab === 'offers'): ?>
          Offer records will appear here when buyers make offers on listings.
        <?php else: ?>
          Order records will appear here when transactions are completed.
        <?php endif; ?>
      </p>
      
      <div class="bg-blue-100 px-4 py-3 rounded-lg max-w-lg mx-auto">
        <p class="text-sm text-blue-800 font-medium">
          <i class="fa fa-info-circle mr-1"></i> 
          <?php if ($activeTab === 'offers'): ?>
            Offers management system is ready to track buyer offers
          <?php else: ?>
            Orders management system is ready to track transactions
          <?php endif; ?>
        </p>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Tab Navigation -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
    <div class="flex border-b border-gray-200">
      <a href="?p=dashboard&page=superAdminOffers&tab=offers" class="tab-btn <?= $activeTab === 'offers' ? 'active-tab text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-blue-600' ?> px-6 py-4 text-sm font-medium flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
        </svg>
        Offers (<?= $totalOffers ?>)
      </a>
      <a href="?p=dashboard&page=superAdminOffers&tab=orders" class="tab-btn <?= $activeTab === 'orders' ? 'active-tab text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-blue-600' ?> px-6 py-4 text-sm font-medium flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
        </svg>
        Orders (<?= $totalOrders ?>)
      </a>
    </div>

    <!-- OFFERS TABLE -->
    <div id="offers" class="tab-content <?= $activeTab !== 'offers' ? 'hidden' : '' ?>">
      <div class="overflow-x-auto">
        <table class="w-full text-left" id="offersTable">
          <thead>
            <tr class="bg-gray-50 text-gray-700 text-sm border-b">
              <th class="py-4 px-6 font-medium">Offer ID</th>
              <th class="py-4 px-6 font-medium">Buyer</th>
              <th class="py-4 px-6 font-medium">Listing</th>
              <th class="py-4 px-6 font-medium">Offered Price</th>
              <th class="py-4 px-6 font-medium">Status</th>
              <th class="py-4 px-6 font-medium">Date</th>
              <th class="py-4 px-6 font-medium text-right">Action</th>
            </tr>
          </thead>
          <tbody id="offers-table-body" class="divide-y divide-gray-200">
            <?php if ($hasOffersData && count($offers)): ?>
              <?php foreach ($offers as $o): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="py-4 px-6">
                    <div class="font-medium text-gray-900">OFF<?= $o['offer_id'] ?></div>
                  </td>
                  <td class="py-4 px-6">
                    <div class="font-medium text-gray-900"><?= htmlspecialchars($o['buyer_name']) ?></div>
                    <div class="text-sm text-gray-500"><?= htmlspecialchars($o['buyer_email']) ?></div>
                  </td>
                  <td class="py-4 px-6">
                    <div class="font-medium text-gray-900"><?= htmlspecialchars($o['listing_title']) ?></div>
                    <div class="text-sm text-gray-500">Asking: $<?= number_format($o['asking_price'], 2) ?></div>
                  </td>
                  <td class="py-4 px-6">
                    <div class="font-semibold text-gray-900">$<?= number_format($o['amount'], 2) ?></div>
                    <?php if ($o['amount'] < $o['asking_price']): ?>
                      <div class="text-xs text-red-600 mt-1">Below asking price</div>
                    <?php elseif ($o['amount'] > $o['asking_price']): ?>
                      <div class="text-xs text-green-600 mt-1">Above asking price</div>
                    <?php else: ?>
                      <div class="text-xs text-blue-600 mt-1">At asking price</div>
                    <?php endif; ?>
                  </td>
                  <td class="py-4 px-6">
                    <?= renderStatus($o['offer_status']) ?>
                  </td>
                  <td class="py-4 px-6 text-gray-600">
                    <div><?= date('M j, Y', strtotime($o['offer_date'])) ?></div>
                    <div class="text-sm text-gray-500"><?= date('g:i A', strtotime($o['offer_date'])) ?></div>
                  </td>
                  <td class="py-4 px-6 text-right">
                    <div class="flex justify-end space-x-2">
                      <button onclick="viewOfferDetails(<?= $o['offer_id'] ?>, '<?= htmlspecialchars($o['buyer_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($o['listing_title'], ENT_QUOTES) ?>', '<?= number_format($o['amount'], 2) ?>', '<?= $o['offer_status'] ?>', '<?= date('M j, Y g:i A', strtotime($o['offer_date'])) ?>', '<?= htmlspecialchars($o['message'] ?? '', ENT_QUOTES) ?>')" 
                              class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        View
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="7" class="px-6 py-16 text-center">
                  <div class="flex flex-col items-center justify-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                      <i class="fa fa-handshake text-gray-400 text-2xl"></i>
                    </div>
                    <?php if (!$hasOffersData): ?>
                      <h3 class="text-lg font-medium text-gray-900 mb-2">No Offer Records</h3>
                      <p class="text-gray-500 mb-4 max-w-sm">Offer records will appear here when buyers make offers on listings</p>
                      <div class="bg-blue-50 px-4 py-2 rounded-lg">
                        <p class="text-sm text-blue-700 font-medium">
                          <i class="fa fa-info-circle mr-1"></i> Offers system ready
                        </p>
                      </div>
                    <?php else: ?>
                      <h3 class="text-lg font-medium text-gray-900 mb-2">No Offers Match Your Filters</h3>
                      <p class="text-gray-500 mb-4">Try adjusting your search criteria or clearing filters</p>
                      <a href="?p=dashboard&page=superAdminOffers&tab=offers" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                        <i class="fa fa-times mr-2"></i> Clear Filters
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      
      <!-- Offers Pagination -->
      <?php if ($activeTab === 'offers'): ?>
        <div class="mt-6 pt-4 border-t border-gray-200">
          <?php if ($offersPagination): ?>
            <?php 
            $extraParams = ['p' => 'dashboard', 'page' => 'superAdminOffers', 'tab' => 'offers'];
            if ($search) $extraParams['search'] = $search;
            if ($status) $extraParams['status'] = $status;
            
            echo renderPagination($offersPagination, url('index.php'), $extraParams, 'pg'); 
            ?>
          <?php else: ?>
            <p class="text-sm text-gray-500">No pagination data available</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ORDERS TABLE -->
    <div id="orders" class="tab-content <?= $activeTab !== 'orders' ? 'hidden' : '' ?>">
      <div class="overflow-x-auto">
        <table class="w-full text-left" id="ordersTable">
          <thead>
            <tr class="bg-gray-50 text-gray-700 text-sm border-b">
              <th class="py-4 px-6 font-medium">Order ID</th>
              <th class="py-4 px-6 font-medium">Buyer</th>
              <th class="py-4 px-6 font-medium">Seller</th>
              <th class="py-4 px-6 font-medium">Amount</th>
              <th class="py-4 px-6 font-medium">Status</th>
              <th class="py-4 px-6 font-medium">Date</th>
              <th class="py-4 px-6 font-medium text-right">Action</th>
            </tr>
          </thead>
          <tbody id="orders-table-body" class="divide-y divide-gray-200">
            <?php if ($hasOrdersData && count($orders)): ?>
              <?php foreach ($orders as $o): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="py-4 px-6">
                    <div class="font-medium text-gray-900">ORD<?= $o['order_id'] ?></div>
                  </td>
                  <td class="py-4 px-6">
                    <div class="font-medium text-gray-900"><?= htmlspecialchars($o['buyer_name']) ?></div>
                    <div class="text-sm text-gray-500"><?= htmlspecialchars($o['buyer_email']) ?></div>
                  </td>
                  <td class="py-4 px-6">
                    <div class="font-medium text-gray-900"><?= htmlspecialchars($o['seller_name']) ?></div>
                    <div class="text-sm text-gray-500"><?= htmlspecialchars($o['seller_email']) ?></div>
                  </td>
                  <td class="py-4 px-6">
                    <div class="font-semibold text-gray-900">$<?= number_format($o['amount'], 2) ?></div>
                    <div class="text-xs text-gray-500">Fee: $<?= number_format($o['platform_fee'], 2) ?></div>
                    <div class="text-sm font-medium text-blue-600">Total: $<?= number_format($o['total'], 2) ?></div>
                  </td>
                  <td class="py-4 px-6">
                    <?= renderStatus($o['order_status']) ?>
                  </td>
                  <td class="py-4 px-6 text-gray-600">
                    <div><?= date('M j, Y', strtotime($o['order_date'])) ?></div>
                    <div class="text-sm text-gray-500"><?= date('g:i A', strtotime($o['order_date'])) ?></div>
                  </td>
                  <td class="py-4 px-6 text-right">
                    <div class="flex justify-end space-x-2">
                      <button onclick="viewOrderDetails(<?= $o['order_id'] ?>, '<?= htmlspecialchars($o['buyer_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($o['seller_name'], ENT_QUOTES) ?>', '<?= number_format($o['amount'], 2) ?>', '<?= number_format($o['platform_fee'], 2) ?>', '<?= number_format($o['total'], 2) ?>', '<?= $o['order_status'] ?>', '<?= date('M j, Y g:i A', strtotime($o['order_date'])) ?>')" 
                              class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        View
                      </button>
                      <?php if ($o['order_status'] === 'processing'): ?>
                        <button onclick="completeOrder(<?= $o['order_id'] ?>)" 
                                class="inline-flex items-center px-3 py-1.5 border border-transparent rounded-lg text-sm font-medium text-white bg-green-600 hover:bg-green-700 transition-colors">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                          </svg>
                          Complete
                        </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="7" class="px-6 py-16 text-center">
                  <div class="flex flex-col items-center justify-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                      <i class="fa fa-shopping-cart text-gray-400 text-2xl"></i>
                    </div>
                    <?php if (!$hasOrdersData): ?>
                      <h3 class="text-lg font-medium text-gray-900 mb-2">No Order Records</h3>
                      <p class="text-gray-500 mb-4 max-w-sm">Order records will appear here when transactions are completed</p>
                      <div class="bg-blue-50 px-4 py-2 rounded-lg">
                        <p class="text-sm text-blue-700 font-medium">
                          <i class="fa fa-info-circle mr-1"></i> Orders system ready
                        </p>
                      </div>
                    <?php else: ?>
                      <h3 class="text-lg font-medium text-gray-900 mb-2">No Orders Match Your Filters</h3>
                      <p class="text-gray-500 mb-4">Try adjusting your search criteria or clearing filters</p>
                      <a href="?p=dashboard&page=superAdminOffers&tab=orders" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                        <i class="fa fa-times mr-2"></i> Clear Filters
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      
      <!-- Orders Pagination -->
      <?php if ($activeTab === 'orders'): ?>
        <div class="mt-6 pt-4 border-t border-gray-200">
          <?php if ($ordersPagination): ?>
            <?php 
            $extraParams = ['p' => 'dashboard', 'page' => 'superAdminOffers', 'tab' => 'orders'];
            if ($search) $extraParams['search'] = $search;
            if ($status) $extraParams['status'] = $status;
            
            echo renderPagination($ordersPagination, url('index.php'), $extraParams, 'pg'); 
            ?>
          <?php else: ?>
            <p class="text-sm text-gray-500">No pagination data available</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Offer Details Modal -->
<div id="offerModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 hidden z-50">
  <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
    <!-- Modal Header -->
    <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
          <i class="fas fa-handshake text-blue-600 text-lg"></i>
        </div>
        <div>
          <h3 class="text-xl font-bold text-gray-900">Offer Details</h3>
          <p class="text-sm text-gray-600">Complete offer information</p>
        </div>
      </div>
      <button onclick="closeModal('offerModal')" class="text-gray-400 hover:text-gray-600 p-2 hover:bg-white hover:bg-opacity-50 rounded-full transition-all">
        <i class="fas fa-times text-lg"></i>
      </button>
    </div>
    
    <!-- Modal Body -->
    <div class="p-6 space-y-6">
      <!-- Basic Info -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-xl border border-blue-200">
          <label class="block text-xs font-bold text-blue-600 uppercase tracking-wide mb-2">Offer ID</label>
          <p id="offerIdDisplay" class="text-xl font-bold text-blue-800"></p>
        </div>
        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
          <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-2">Status</label>
          <div id="offerStatusDisplay"></div>
        </div>
      </div>
      
      <!-- Parties -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="border-2 border-gray-200 p-4 rounded-xl hover:border-blue-300 transition-colors">
          <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-2">
            <i class="fas fa-user mr-1"></i>Buyer
          </label>
          <p id="offerBuyerDisplay" class="text-lg font-semibold text-gray-900"></p>
        </div>
        <div class="border-2 border-gray-200 p-4 rounded-xl hover:border-blue-300 transition-colors">
          <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-2">
            <i class="fas fa-home mr-1"></i>Listing
          </label>
          <p id="offerListingDisplay" class="text-lg font-semibold text-gray-900"></p>
        </div>
      </div>
      
      <!-- Financial Info -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-gradient-to-br from-green-50 to-emerald-100 border-2 border-green-200 p-4 rounded-xl">
          <label class="block text-xs font-bold text-green-600 uppercase tracking-wide mb-2">
            <i class="fas fa-dollar-sign mr-1"></i>Offer Amount
          </label>
          <p id="offerAmountDisplay" class="text-3xl font-bold text-green-700"></p>
        </div>
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 border-2 border-purple-200 p-4 rounded-xl">
          <label class="block text-xs font-bold text-purple-600 uppercase tracking-wide mb-2">
            <i class="fas fa-calendar mr-1"></i>Date & Time
          </label>
          <p id="offerDateDisplay" class="text-base font-bold text-purple-700"></p>
        </div>
      </div>
      
      <!-- Message -->
      <div class="border-2 border-gray-200 rounded-xl p-4 bg-gradient-to-br from-gray-50 to-gray-100">
        <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-3">
          <i class="fas fa-comment mr-1"></i>Buyer Message
        </label>
        <div id="offerMessageDisplay" class="text-gray-800 bg-white p-4 rounded-lg min-h-[80px] italic border border-gray-200 shadow-inner"></div>
      </div>
    </div>
    
    <!-- Modal Footer -->
    <div class="flex justify-end gap-3 p-6 border-t border-gray-200 bg-gray-50">
      <button onclick="closeModal('offerModal')" class="px-6 py-3 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-xl hover:from-gray-700 hover:to-gray-800 transition-all font-semibold shadow-lg">
        <i class="fas fa-times mr-2"></i>Close
      </button>
    </div>
  </div>
</div>

<!-- Order Details Modal -->
<div id="orderModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 hidden z-50">
  <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
    <!-- Modal Header -->
    <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-green-50 to-emerald-50">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
          <i class="fas fa-shopping-cart text-green-600 text-lg"></i>
        </div>
        <div>
          <h3 class="text-xl font-bold text-gray-900">Order Details</h3>
          <p class="text-sm text-gray-600">Complete transaction information</p>
        </div>
      </div>
      <button onclick="closeModal('orderModal')" class="text-gray-400 hover:text-gray-600 p-2 hover:bg-white hover:bg-opacity-50 rounded-full transition-all">
        <i class="fas fa-times text-lg"></i>
      </button>
    </div>
    
    <!-- Modal Body -->
    <div class="p-6 space-y-6">
      <!-- Basic Info -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-xl border border-green-200">
          <label class="block text-xs font-bold text-green-600 uppercase tracking-wide mb-2">Order ID</label>
          <p id="orderIdDisplay" class="text-xl font-bold text-green-800"></p>
        </div>
        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
          <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-2">Status</label>
          <div id="orderStatusDisplay"></div>
        </div>
      </div>
      
      <!-- Parties -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="border-2 border-gray-200 p-4 rounded-xl hover:border-green-300 transition-colors">
          <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-2">
            <i class="fas fa-user mr-1"></i>Buyer
          </label>
          <p id="orderBuyerDisplay" class="text-lg font-semibold text-gray-900"></p>
        </div>
        <div class="border-2 border-gray-200 p-4 rounded-xl hover:border-green-300 transition-colors">
          <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-2">
            <i class="fas fa-store mr-1"></i>Seller
          </label>
          <p id="orderSellerDisplay" class="text-lg font-semibold text-gray-900"></p>
        </div>
      </div>
      
      <!-- Financial Breakdown -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 border-2 border-blue-200 p-4 rounded-xl">
          <label class="block text-xs font-bold text-blue-600 uppercase tracking-wide mb-2">
            <i class="fas fa-dollar-sign mr-1"></i>Amount
          </label>
          <p id="orderAmountDisplay" class="text-2xl font-bold text-blue-700"></p>
        </div>
        <div class="bg-gradient-to-br from-orange-50 to-orange-100 border-2 border-orange-200 p-4 rounded-xl">
          <label class="block text-xs font-bold text-orange-600 uppercase tracking-wide mb-2">
            <i class="fas fa-percentage mr-1"></i>Platform Fee
          </label>
          <p id="orderFeeDisplay" class="text-xl font-bold text-orange-700"></p>
        </div>
        <div class="bg-gradient-to-br from-green-50 to-emerald-100 border-2 border-green-200 p-4 rounded-xl">
          <label class="block text-xs font-bold text-green-600 uppercase tracking-wide mb-2">
            <i class="fas fa-calculator mr-1"></i>Total
          </label>
          <p id="orderTotalDisplay" class="text-2xl font-bold text-green-700"></p>
        </div>
      </div>
      
      <!-- Date Info -->
      <div class="border-2 border-gray-200 rounded-xl p-4 bg-gradient-to-br from-purple-50 to-purple-100">
        <label class="block text-xs font-bold text-purple-600 uppercase tracking-wide mb-2">
          <i class="fas fa-calendar-alt mr-1"></i>Order Date & Time
        </label>
        <p id="orderDateDisplay" class="text-lg font-bold text-purple-700"></p>
      </div>
    </div>
    
    <!-- Modal Footer -->
    <div class="flex justify-end gap-3 p-6 border-t border-gray-200 bg-gray-50">
      <button onclick="closeModal('orderModal')" class="px-6 py-3 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-xl hover:from-gray-700 hover:to-gray-800 transition-all font-semibold shadow-lg">
        <i class="fas fa-times mr-2"></i>Close
      </button>
    </div>
  </div>
</div>

<?php
function renderStatus($status) {
  $map = [
    'pending' => ['bg-yellow-100 text-yellow-800', 'fa-clock'],
    'accepted' => ['bg-green-100 text-green-800', 'fa-check-circle'],
    'rejected' => ['bg-red-100 text-red-800', 'fa-times-circle'],
    'processing' => ['bg-blue-100 text-blue-800', 'fa-cog'],
    'completed' => ['bg-green-100 text-green-800', 'fa-check-circle'],
    'disputed' => ['bg-red-100 text-red-800', 'fa-exclamation-triangle'],
    'cancelled' => ['bg-gray-100 text-gray-800', 'fa-ban']
  ];
  $cls = $map[$status] ?? ['bg-gray-100 text-gray-600', 'fa-question-circle'];
  return "<span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {$cls[0]}'>
            <i class='fa {$cls[1]} mr-1.5'></i> " . ucfirst($status) . "
          </span>";
}
?>

<script>
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(b => {
      b.classList.remove('active-tab', 'text-blue-600', 'border-blue-600');
      b.classList.add('text-gray-600', 'hover:text-blue-600');
    });
    btn.classList.add('active-tab', 'text-blue-600', 'border-blue-600');
    btn.classList.remove('text-gray-600', 'hover:text-blue-600');

    // Update tab content
    const tabId = btn.dataset.tab;
    document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
    document.getElementById(tabId).classList.remove('hidden');
  });
});

// Modal Functions
function viewOfferDetails(offerId, buyerName, listingTitle, amount, status, date, message) {
  // Clean and set text content to avoid HTML injection
  document.getElementById('offerIdDisplay').textContent = 'OFF' + offerId;
  document.getElementById('offerBuyerDisplay').textContent = buyerName || 'Unknown Buyer';
  document.getElementById('offerListingDisplay').textContent = listingTitle || 'Unknown Listing';
  document.getElementById('offerAmountDisplay').textContent = '$' + amount;
  document.getElementById('offerDateDisplay').textContent = date;
  document.getElementById('offerMessageDisplay').textContent = message || 'No message provided';
  
  // Set status with appropriate styling
  const statusElement = document.getElementById('offerStatusDisplay');
  statusElement.innerHTML = getStatusBadge(status);
  
  // Show modal
  document.getElementById('offerModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden'; // Prevent background scroll
}

function viewOrderDetails(orderId, buyerName, sellerName, amount, fee, total, status, date) {
  console.log('viewOrderDetails called with:', {orderId, buyerName, sellerName, amount, fee, total, status, date});
  
  // Clean and set text content to avoid HTML injection
  document.getElementById('orderIdDisplay').textContent = 'ORD' + orderId;
  document.getElementById('orderBuyerDisplay').textContent = buyerName || 'Unknown Buyer';
  document.getElementById('orderSellerDisplay').textContent = sellerName || 'Unknown Seller';
  document.getElementById('orderAmountDisplay').textContent = '$' + amount;
  document.getElementById('orderFeeDisplay').textContent = '$' + fee;
  document.getElementById('orderTotalDisplay').textContent = '$' + total;
  document.getElementById('orderDateDisplay').textContent = date;
  
  // Set status with appropriate styling
  const statusElement = document.getElementById('orderStatusDisplay');
  statusElement.innerHTML = getStatusBadge(status);
  
  // Show modal
  document.getElementById('orderModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden'; // Prevent background scroll
}

function closeModal(modalId) {
  document.getElementById(modalId).classList.add('hidden');
  document.body.style.overflow = 'auto'; // Restore background scroll
}

function getStatusBadge(status) {
  const statusMap = {
    'pending': '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"><i class="fa fa-clock mr-1.5"></i>Pending</span>',
    'accepted': '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800"><i class="fa fa-check-circle mr-1.5"></i>Accepted</span>',
    'rejected': '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800"><i class="fa fa-times-circle mr-1.5"></i>Rejected</span>',
    'processing': '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800"><i class="fa fa-cog mr-1.5"></i>Processing</span>',
    'completed': '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800"><i class="fa fa-check-circle mr-1.5"></i>Completed</span>',
    'cancelled': '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800"><i class="fa fa-ban mr-1.5"></i>Cancelled</span>'
  };
  return statusMap[status] || '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600"><i class="fa fa-question-circle mr-1.5"></i>' + status + '</span>';
}

function completeOrder(orderId) {
  if (confirm('Are you sure you want to mark this order as completed?')) {
    // Here you would make an AJAX call to update the order status
    alert('Order #' + orderId + ' marked as completed! (This is a demo - implement AJAX call for real functionality)');
    // Reload page to show updated status
    location.reload();
  }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('fixed') && e.target.classList.contains('inset-0')) {
    const modals = ['offerModal', 'orderModal'];
    modals.forEach(modalId => {
      if (!document.getElementById(modalId).classList.contains('hidden')) {
        closeModal(modalId);
      }
    });
  }
});
</script>


<!-- Polling Integration -->
<script>
// Define BASE constant globally
const BASE = "<?php echo BASE; ?>";
console.log('🔧 BASE constant defined:', BASE);

document.addEventListener('DOMContentLoaded', () => {
  console.log('🚀 SuperAdmin Offers polling initialization started');
  
  // Store current counts
  let currentOffers = <?= $totalOffers ?>;
  let currentOrders = <?= $totalOrders ?>;
  let currentPendingOffers = <?= $pendingOffers ?>;
  let currentCompletedOrders = <?= $completedOrders ?>;
  
  console.log('📊 Initial counts:', {
    offers: currentOffers,
    orders: currentOrders,
    pendingOffers: currentPendingOffers,
    completedOrders: currentCompletedOrders
  });
  
  // Start polling for updates
  const pollInterval = setInterval(() => {
    console.log('🔄 Polling API...');
    
    fetch('<?= BASE ?>api/get_superadmin_stats.php', {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin'
    })
      .then(response => {
        console.log('📡 Response status:', response.status);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        console.log('📦 API Response:', data);
        
        if (data.success && data.stats) {
          const newOffers = parseInt(data.stats.offers?.total) || 0;
          const newOrders = parseInt(data.stats.orders?.total) || 0;
          const newPendingOffers = parseInt(data.stats.offers?.pending) || 0;
          const newCompletedOrders = parseInt(data.stats.orders?.completed) || 0;
          
          console.log('📊 Polling update:', {
            offers: { current: currentOffers, new: newOffers, changed: newOffers !== currentOffers },
            orders: { current: currentOrders, new: newOrders, changed: newOrders !== currentOrders },
            pendingOffers: { current: currentPendingOffers, new: newPendingOffers, changed: newPendingOffers !== currentPendingOffers },
            completedOrders: { current: currentCompletedOrders, new: newCompletedOrders, changed: newCompletedOrders !== currentCompletedOrders }
          });
          
          // Check if data has changed
          if (newOffers !== currentOffers || 
              newOrders !== currentOrders || 
              newPendingOffers !== currentPendingOffers || 
              newCompletedOrders !== currentCompletedOrders) {
            
            console.log('🔄 Data changed, reloading page...');
            location.reload();
          } else {
            console.log('✅ No changes detected');
          }
        } else {
          console.warn('⚠️ Invalid API response:', data);
        }
      })
      .catch(error => {
        console.error('❌ Polling error:', error);
      });
  }, 5000); // Poll every 5 seconds
  
  console.log('✅ Polling started (interval: 5 seconds)');
  
  // Cleanup on page unload
  window.addEventListener('beforeunload', () => {
    console.log('🛑 Stopping polling...');
    clearInterval(pollInterval);
  });
});
</script>

<!-- Real-time Polling System -->
<script>
// Wait for BASE constant to be available
document.addEventListener('DOMContentLoaded', function() {
  if (typeof BASE === 'undefined') {
    console.error('❌ BASE constant not defined - polling may not work');
    return;
  }
  console.log('🔧 Using BASE constant for offers/orders:', BASE);

// Function to create offer row HTML
function createOfferRow(offer) {
  const belowAskingPrice = offer.amount < offer.asking_price;
  
  return `
    <tr class="hover:bg-gray-50 transition-colors bg-blue-50 animate-fade-in" data-offer-id="${offer.id}">
      <td class="py-4 px-6">
        <div class="font-medium text-gray-900">OFF${offer.id}</div>
      </td>
      <td class="py-4 px-6">
        <div class="font-medium text-gray-900">${offer.buyer_name || 'Unknown'}</div>
        <div class="text-sm text-gray-500">${offer.buyer_email || ''}</div>
      </td>
      <td class="py-4 px-6">
        <div class="font-medium text-gray-900">${offer.listing_name || 'Listing'}</div>
        <div class="text-sm text-gray-500">Asking: $${Number(offer.listing_price || 0).toLocaleString()}</div>
      </td>
      <td class="py-4 px-6">
        <div class="font-semibold text-gray-900">$${Number(offer.amount).toLocaleString()}</div>
        ${belowAskingPrice ? '<div class="text-xs text-red-600 mt-1">Below asking price</div>' : ''}
      </td>
      <td class="py-4 px-6">
        <span class="px-3 py-1 rounded-full text-xs font-medium ${getOfferStatusClass(offer.status)}">
          ${offer.status || 'pending'}
        </span>
      </td>
      <td class="py-4 px-6">
        <div class="text-sm text-gray-900">${new Date(offer.created_at).toLocaleDateString()}</div>
        <div class="text-xs text-gray-500">${new Date(offer.created_at).toLocaleTimeString()}</div>
      </td>
      <td class="py-4 px-6 text-right">
        <div class="flex justify-end space-x-2">
          <button onclick="viewOfferDetails(${offer.id}, '${offer.buyer_name}', '${offer.listing_name}', '${Number(offer.amount).toFixed(2)}', '${offer.status}', '${new Date(offer.created_at).toLocaleString()}', '')" 
                  class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            View
          </button>
        </div>
      </td>
    </tr>
  `;
}

// Function to create order row HTML
function createOrderRow(order) {
  return `
    <tr class="hover:bg-gray-50 transition-colors bg-blue-50 animate-fade-in" data-order-id="${order.id}">
      <td class="py-4 px-6">
        <div class="font-medium text-gray-900">ORD${order.id}</div>
      </td>
      <td class="py-4 px-6">
        <div class="font-medium text-gray-900">${order.buyer_name || 'Unknown'}</div>
        <div class="text-sm text-gray-500">${order.buyer_email || ''}</div>
      </td>
      <td class="py-4 px-6">
        <div class="font-medium text-gray-900">${order.seller_name || 'Unknown'}</div>
        <div class="text-sm text-gray-500">${order.seller_email || ''}</div>
      </td>
      <td class="py-4 px-6">
        <div class="font-semibold text-gray-900">$${Number(order.amount || 0).toLocaleString()}</div>
        <div class="text-xs text-gray-500">Fee: $${Number(order.platform_fee || 0).toLocaleString()}</div>
        <div class="text-sm font-medium text-blue-600">Total: $${Number((order.amount || 0) + (order.platform_fee || 0)).toLocaleString()}</div>
      </td>
      <td class="py-4 px-6">
        <span class="px-3 py-1 rounded-full text-xs font-medium ${getOrderStatusClass(order.status)}">
          ${order.status || 'pending'}
        </span>
      </td>
      <td class="py-4 px-6">
        <div class="text-sm text-gray-900">${new Date(order.created_at).toLocaleDateString()}</div>
        <div class="text-xs text-gray-500">${new Date(order.created_at).toLocaleTimeString()}</div>
      </td>
      <td class="py-4 px-6 text-right">
        <div class="flex justify-end space-x-2">
          <button onclick="viewOrderDetails(${order.id})" 
                  class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            View
          </button>
        </div>
      </td>
    </tr>
  `;
}

function getOfferStatusClass(status) {
  const classes = {
    'pending': 'bg-yellow-100 text-yellow-800',
    'accepted': 'bg-green-100 text-green-800',
    'rejected': 'bg-red-100 text-red-800',
    'expired': 'bg-gray-100 text-gray-800'
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
}

function getOrderStatusClass(status) {
  const classes = {
    'pending': 'bg-yellow-100 text-yellow-800',
    'paid': 'bg-blue-100 text-blue-800',
    'processing': 'bg-purple-100 text-purple-800',
    'completed': 'bg-green-100 text-green-800',
    'cancelled': 'bg-red-100 text-red-800'
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
}

// Function to update stats cards
function updateStatsCards(newOffers, newOrders) {
  console.log('📊 Updating stats cards');
  
  // Update Total Offers
  const totalOffersEl = document.getElementById('total-offers-count');
  if (totalOffersEl && newOffers.length > 0) {
    const currentCount = parseInt(totalOffersEl.dataset.count) || 0;
    const newCount = currentCount + newOffers.length;
    totalOffersEl.dataset.count = newCount;
    totalOffersEl.textContent = newCount;
    totalOffersEl.classList.add('animate-pulse');
    setTimeout(() => totalOffersEl.classList.remove('animate-pulse'), 1000);
  }
  
  // Update Pending Offers - handle status changes
  const pendingOffersEl = document.getElementById('pending-offers-count');
  if (pendingOffersEl && newOffers.length > 0) {
    const pendingCount = newOffers.filter(o => o.status === 'pending' || !o.status).length;
    const acceptedCount = newOffers.filter(o => o.status === 'accepted').length;
    const rejectedCount = newOffers.filter(o => o.status === 'rejected').length;
    
    const currentCount = parseInt(pendingOffersEl.dataset.count) || 0;
    
    // Add new pending offers
    let newCount = currentCount + pendingCount;
    
    // Subtract accepted/rejected offers from pending
    newCount = Math.max(0, newCount - acceptedCount - rejectedCount);
    
    pendingOffersEl.dataset.count = newCount;
    pendingOffersEl.textContent = newCount;
    pendingOffersEl.classList.add('animate-pulse');
    setTimeout(() => pendingOffersEl.classList.remove('animate-pulse'), 1000);
    
    if (acceptedCount > 0 || rejectedCount > 0) {
      console.log(`✅ Updated pending offers: ${currentCount} -> ${newCount} (accepted: ${acceptedCount}, rejected: ${rejectedCount})`);
    }
  }
  
  // Update Total Orders
  const totalOrdersEl = document.getElementById('total-orders-count');
  if (totalOrdersEl && newOrders.length > 0) {
    const currentCount = parseInt(totalOrdersEl.dataset.count) || 0;
    const newCount = currentCount + newOrders.length;
    totalOrdersEl.dataset.count = newCount;
    totalOrdersEl.textContent = newCount;
    totalOrdersEl.classList.add('animate-pulse');
    setTimeout(() => totalOrdersEl.classList.remove('animate-pulse'), 1000);
  }
  
  // Update Completed Orders
  const completedOrdersEl = document.getElementById('completed-orders-count');
  if (completedOrdersEl && newOrders.length > 0) {
    const completedCount = newOrders.filter(o => o.status === 'completed').length;
    if (completedCount > 0) {
      const currentCount = parseInt(completedOrdersEl.dataset.count) || 0;
      const newCount = currentCount + completedCount;
      completedOrdersEl.dataset.count = newCount;
      completedOrdersEl.textContent = newCount;
      completedOrdersEl.classList.add('animate-pulse');
      setTimeout(() => completedOrdersEl.classList.remove('animate-pulse'), 1000);
    }
  }
}

// Function to add new offers to table
function addNewOffers(newOffers) {
  console.log('💰 Adding', newOffers.length, 'new offers to table');
  
  const tbody = document.getElementById('offers-table-body');
  if (!tbody) {
    console.warn('⚠️ Offers table body not found');
    return;
  }
  
  // Remove "no offers" message if present
  const noOffersRow = tbody.querySelector('td[colspan]');
  if (noOffersRow) {
    noOffersRow.closest('tr').remove();
  }
  
  // Add new offers to top of table
  newOffers.forEach(offer => {
    const rowHTML = createOfferRow(offer);
    tbody.insertAdjacentHTML('afterbegin', rowHTML);
  });
  
  // Remove highlight after 5 seconds
  setTimeout(() => {
    const newRows = tbody.querySelectorAll('.bg-blue-50');
    newRows.forEach(row => row.classList.remove('bg-blue-50'));
  }, 5000);
  
  // Keep only last 50 rows
  while (tbody.children.length > 50) {
    tbody.removeChild(tbody.lastChild);
  }
}

// Function to add new orders to table
function addNewOrders(newOrders) {
  console.log('📦 Adding', newOrders.length, 'new orders to table');
  
  const tbody = document.getElementById('orders-table-body');
  if (!tbody) {
    console.warn('⚠️ Orders table body not found');
    return;
  }
  
  // Remove "no orders" message if present
  const noOrdersRow = tbody.querySelector('td[colspan]');
  if (noOrdersRow) {
    noOrdersRow.closest('tr').remove();
  }
  
  // Add new orders to top of table
  newOrders.forEach(order => {
    const rowHTML = createOrderRow(order);
    tbody.insertAdjacentHTML('afterbegin', rowHTML);
  });
  
  // Remove highlight after 5 seconds
  setTimeout(() => {
    const newRows = tbody.querySelectorAll('.bg-blue-50');
    newRows.forEach(row => row.classList.remove('bg-blue-50'));
  }, 5000);
  
  // Keep only last 50 rows
  while (tbody.children.length > 50) {
    tbody.removeChild(tbody.lastChild);
  }
}

  // Ensure API_BASE_PATH is set
  if (!window.API_BASE_PATH) {
    const path = window.location.pathname;
    window.API_BASE_PATH = (path.includes('/marketplace/') ? '/marketplace' : '') + '/api';
    console.log('🔧 [Offers] API_BASE_PATH:', window.API_BASE_PATH);
  }
  
  // Load polling.js and start polling
  console.log('📦 Loading polling.js for offers/orders...');
  const pollingScript = document.createElement('script');
  pollingScript.src = BASE + 'js/polling.js';

  pollingScript.onload = function() {
    console.log('✅ polling.js loaded for offers/orders page');
    console.log('✅ API_BASE_PATH:', window.API_BASE_PATH);
    
    if (typeof startPolling !== 'undefined') {
      console.log('✅ Starting polling for offers and orders');
      
        try {
          startPolling({
          offers: (newOffers) => {
          console.log('💰 New offers detected:', newOffers.length);
          if (newOffers.length > 0) {
            console.log('💰 Offers data:', newOffers);
            
            // Update stats cards
            updateStatsCards(newOffers, []);
            
            // Add new offers to table
            addNewOffers(newOffers);
            
            // Show notification
            if (typeof PollingUIHelpers !== 'undefined') {
              PollingUIHelpers.showBriefNotification(
                `${newOffers.length} new offer(s) received!`,
                'success'
              );
            }
          }
        },
        
        orders: (newOrders) => {
          console.log('📦 New orders detected:', newOrders.length);
          if (newOrders.length > 0) {
            console.log('📦 Orders data:', newOrders);
            
            // Update stats cards
            updateStatsCards([], newOrders);
            
            // Add new orders to table
            addNewOrders(newOrders);
            
            // Show notification
            if (typeof PollingUIHelpers !== 'undefined') {
              PollingUIHelpers.showBriefNotification(
                `${newOrders.length} new order(s) placed!`,
                'success'
              );
            }
          }
        }
      });
      
      console.log('✅ Offers/Orders polling started - checking every 5 seconds');
    } catch (error) {
      console.error('❌ Error starting polling:', error);
    }
  } else {
    console.error('❌ startPolling function not found');
  }
};

  pollingScript.onerror = function() {
    console.error('❌ Failed to load polling.js');
  };

  document.head.appendChild(pollingScript);

}); // End of DOMContentLoaded
</script>

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
</style>
