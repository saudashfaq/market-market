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
        // Export disputes
        $exportSql = "
            SELECT d.id, d.case_id, d.reason, d.amount, d.status, d.priority, d.created_at,
                   l.name as listing_name,
                   buyer.name as buyer_name, buyer.email as buyer_email,
                   seller.name as seller_name, seller.email as seller_email
            FROM disputes d
            LEFT JOIN listings l ON d.listing_id = l.id
            LEFT JOIN users buyer ON d.buyer_id = buyer.id
            LEFT JOIN users seller ON d.seller_id = seller.id
            ORDER BY d.created_at DESC
        ";
        
        $exportStmt = $pdo->query($exportSql);
        $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
        
        handleExportRequest($exportData, 'Disputes Report');
    } catch (Exception $e) {
        // If table doesn't exist, return empty
        handleExportRequest([], 'Disputes Report');
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
$priority = $_GET['priority'] ?? '';

// Handle dispute actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'update_status':
                    $disputeId = $_POST['dispute_id'] ?? 0;
                    $newStatus = $_POST['status'] ?? '';
                    $resolution = $_POST['resolution'] ?? '';
                    
                    $stmt = $pdo->prepare("UPDATE disputes SET status = ?, resolution = ?, resolved_by = ?, resolved_at = NOW(), updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$newStatus, $resolution, $_SESSION['user_id'], $disputeId]);
                    
                    $_SESSION['success_message'] = "Dispute status updated successfully!";
                    break;
                    
                case 'update_priority':
                    $disputeId = $_POST['dispute_id'] ?? 0;
                    $priority = $_POST['priority'] ?? '';
                    
                    $stmt = $pdo->prepare("UPDATE disputes SET priority = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$priority, $disputeId]);
                    
                    $_SESSION['success_message'] = "Dispute priority updated!";
                    break;
                    
                case 'add_message':
                    $disputeId = $_POST['dispute_id'] ?? 0;
                    $message = $_POST['message'] ?? '';
                    
                    $stmt = $pdo->prepare("INSERT INTO dispute_messages (dispute_id, user_id, message) VALUES (?, ?, ?)");
                    $stmt->execute([$disputeId, $_SESSION['user_id'], $message]);
                    
                    $_SESSION['success_message'] = "Message added to dispute!";
                    break;
            }
            
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
        }
    }
}

// Get real disputes data from database only
$disputeRecords = [];
$hasRealData = false;

try {
    // Check if disputes table exists and has data
    $checkStmt = $pdo->query("SELECT COUNT(*) FROM disputes");
    $totalRecords = $checkStmt->fetchColumn();
    
    if ($totalRecords > 0) {
        $hasRealData = true;
        
        // Fetch real data from disputes table
        $stmt = $pdo->query("
            SELECT d.*, 
                   l.name as listing, 
                   buyer.name as buyer, 
                   seller.name as seller 
            FROM disputes d 
            LEFT JOIN listings l ON d.listing_id = l.id 
            LEFT JOIN users buyer ON d.buyer_id = buyer.id 
            LEFT JOIN users seller ON d.seller_id = seller.id 
            ORDER BY d.created_at DESC
        ");
        $disputeRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Table doesn't exist or error occurred - no data available
    $hasRealData = false;
    $disputeRecords = [];
}

// Apply filters only if we have real data
$filteredDisputes = $disputeRecords;

if ($hasRealData && !empty($filteredDisputes)) {
    if ($search) {
        $filteredDisputes = array_filter($filteredDisputes, function($dispute) use ($search) {
            return stripos($dispute['buyer'], $search) !== false || 
                   stripos($dispute['seller'], $search) !== false ||
                   stripos($dispute['listing'], $search) !== false ||
                   stripos($dispute['case_id'], $search) !== false;
        });
    }

    if ($status) {
        $filteredDisputes = array_filter($filteredDisputes, function($dispute) use ($status) {
            return $dispute['status'] === $status;
        });
    }

    if ($priority) {
        $filteredDisputes = array_filter($filteredDisputes, function($dispute) use ($priority) {
            return $dispute['priority'] === $priority;
        });
    }
}

// Simulate pagination
$totalItems = count($filteredDisputes);
$totalPages = ceil($totalItems / $perPage);
$offset = ($page - 1) * $perPage;
$disputes = array_slice($filteredDisputes, $offset, $perPage);

$pagination = [
    'current_page' => $page,
    'per_page' => $perPage,
    'total_items' => $totalItems,
    'total_pages' => $totalPages,
    'has_prev' => $page > 1,
    'has_next' => $page < $totalPages,
    'prev_page' => $page > 1 ? $page - 1 : null,
    'next_page' => $page < $totalPages ? $page + 1 : null,
    'start_item' => $offset + 1,
    'end_item' => min($offset + $perPage, $totalItems)
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Disputes Management</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    body {
      font-family: 'Inter', sans-serif;
    }
    .hover-row:hover {
      background-color: #f8fafc;
    }
  </style>
</head>
<body class="bg-gray-50">
  <div class="max-w-7xl mx-auto p-4 md:p-6">
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
      <div class="flex items-center">
        <i class="fa-solid fa-check-circle text-green-600 mr-2"></i>
        <span class="text-green-800"><?= htmlspecialchars($_SESSION['success_message']) ?></span>
      </div>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
      <div class="flex items-center">
        <i class="fa-solid fa-exclamation-triangle text-red-600 mr-2"></i>
        <span class="text-red-800"><?= htmlspecialchars($_SESSION['error_message']) ?></span>
      </div>
    </div>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Disputes Management</h1>
        <p class="text-gray-500 mt-1">Monitor and resolve transaction disputes and conflicts</p>
      </div>
      <div class="flex items-center gap-4">
        <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1.5 rounded-full">
          <i class="fa fa-gavel mr-1.5"></i> Mediation Access
        </span>
        <?php require_once __DIR__ . '/../../includes/export_helper.php'; echo getExportButton('disputes'); ?>
      </div>
    </div>

    <!-- Search and Filter - Only show if we have data -->
    <?php if ($hasRealData): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <form method="GET" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="p" value="dashboard">
        <input type="hidden" name="page" value="superAdminDisputes">
        
        <div class="flex-1 min-w-[200px]">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fa fa-search mr-1"></i>Search Disputes
          </label>
          <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                 placeholder="Search by case ID, buyer, seller, or listing..." 
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        
        <div class="min-w-[150px]">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fa fa-filter mr-1"></i>Status
          </label>
          <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">All Status</option>
            <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
            <option value="investigating" <?= $status === 'investigating' ? 'selected' : '' ?>>Investigating</option>
            <option value="resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>Resolved</option>
            <option value="escalated" <?= $status === 'escalated' ? 'selected' : '' ?>>Escalated</option>
          </select>
        </div>
        
        <div class="min-w-[150px]">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fa fa-exclamation-triangle mr-1"></i>Priority
          </label>
          <select name="priority" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">All Priority</option>
            <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>High</option>
            <option value="medium" <?= $priority === 'medium' ? 'selected' : '' ?>>Medium</option>
            <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Low</option>
          </select>
        </div>
        
        <div class="flex gap-2">
          <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
            <i class="fa fa-search mr-2"></i>Filter
          </button>
          <a href="?p=dashboard&page=superAdminDisputes" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors flex items-center">
            <i class="fa fa-times mr-2"></i>Clear
          </a>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <?php if (!$hasRealData): ?>
    <!-- Empty State Banner -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-8 mb-8">
      <div class="text-center">
        <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
          <i class="fa fa-gavel text-blue-600 text-2xl"></i>
        </div>
        <h3 class="text-xl font-semibold text-gray-900 mb-3">No Disputes Found</h3>
        <p class="text-gray-600 mb-6 max-w-md mx-auto">
          Dispute records will appear here when users report issues with transactions or listings.
        </p>
        
        <div class="bg-blue-100 px-4 py-3 rounded-lg max-w-lg mx-auto">
          <p class="text-sm text-blue-800 font-medium">
            <i class="fa fa-info-circle mr-1"></i> 
            Dispute management system is active and ready to handle any reported issues
          </p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <?php
    // Calculate stats from real data only
    if ($hasRealData && !empty($disputeRecords)) {
        $openCount = count(array_filter($disputeRecords, function($item) { return $item['status'] === 'open'; }));
        $investigatingCount = count(array_filter($disputeRecords, function($item) { return $item['status'] === 'investigating'; }));
        $resolvedCount = count(array_filter($disputeRecords, function($item) { return $item['status'] === 'resolved'; }));
        $totalValue = array_sum(array_column($disputeRecords, 'amount'));
    } else {
        // No real data - show zeros
        $openCount = 0;
        $investigatingCount = 0;
        $resolvedCount = 0;
        $totalValue = 0;
    }
    
    // Show empty state when no real data from database
    $showEmptyState = !$hasRealData;
    ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
      <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
        <div class="flex justify-between items-start">
          <div>
            <p class="text-gray-600 text-sm font-medium">Open Disputes</p>
            <h2 class="text-2xl md:text-3xl font-bold text-red-500 mt-1"><?= $openCount ?></h2>
            <?php if ($openCount > 0): ?>
              <p class="text-red-500 text-sm font-semibold mt-2 flex items-center">
                <i class="fa fa-exclamation-triangle mr-1"></i> Requires attention
              </p>
            <?php else: ?>
              <p class="text-gray-400 text-sm mt-2 flex items-center">
                <i class="fa fa-check mr-1"></i> No open disputes
              </p>
            <?php endif; ?>
          </div>
          <div class="bg-red-100 text-red-500 p-3 rounded-lg">
            <i class="fa-solid fa-triangle-exclamation text-xl"></i>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
        <div class="flex justify-between items-start">
          <div>
            <p class="text-gray-600 text-sm font-medium">Investigating</p>
            <h2 class="text-2xl md:text-3xl font-bold text-yellow-500 mt-1"><?= $investigatingCount ?></h2>
            <?php if ($investigatingCount > 0): ?>
              <p class="text-yellow-500 text-sm font-semibold mt-2 flex items-center">
                <i class="fa fa-clock mr-1"></i> In progress
              </p>
            <?php else: ?>
              <p class="text-gray-400 text-sm mt-2 flex items-center">
                <i class="fa fa-pause mr-1"></i> No investigations
              </p>
            <?php endif; ?>
          </div>
          <div class="bg-yellow-100 text-yellow-500 p-3 rounded-lg">
            <i class="fa-solid fa-hourglass-half text-xl"></i>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
        <div class="flex justify-between items-start">
          <div>
            <p class="text-gray-600 text-sm font-medium">Resolved</p>
            <h2 class="text-2xl md:text-3xl font-bold text-green-500 mt-1"><?= $resolvedCount ?></h2>
            <?php if ($resolvedCount > 0): ?>
              <p class="text-green-500 text-sm font-semibold mt-2 flex items-center">
                <i class="fa fa-check-circle mr-1"></i> Successfully closed
              </p>
            <?php else: ?>
              <p class="text-gray-400 text-sm mt-2 flex items-center">
                <i class="fa fa-hourglass mr-1"></i> Awaiting resolutions
              </p>
            <?php endif; ?>
          </div>
          <div class="bg-green-100 text-green-500 p-3 rounded-lg">
            <i class="fa-solid fa-circle-check text-xl"></i>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
        <div class="flex justify-between items-start">
          <div>
            <p class="text-gray-600 text-sm font-medium">Total Value</p>
            <h2 class="text-2xl md:text-3xl font-bold text-blue-500 mt-1">
              <?php if ($totalValue > 0): ?>
                $<?= number_format($totalValue / 1000, 1) ?>K
              <?php else: ?>
                $0
              <?php endif; ?>
            </h2>
            <?php if ($totalValue > 0): ?>
              <p class="text-blue-500 text-sm font-semibold mt-2 flex items-center">
                <i class="fa fa-chart-line mr-1"></i> In dispute
              </p>
            <?php else: ?>
              <p class="text-gray-400 text-sm mt-2 flex items-center">
                <i class="fa fa-dollar-sign mr-1"></i> No disputed amounts
              </p>
            <?php endif; ?>
          </div>
          <div class="bg-blue-100 text-blue-500 p-3 rounded-lg">
            <i class="fa-solid fa-dollar-sign text-xl"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Disputes Table - Only show if we have data -->
    <?php if ($hasRealData): ?>
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
      <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-4">
        <div>
          <h2 class="text-lg md:text-xl font-semibold text-gray-900">All Disputes</h2>
          <p class="text-sm text-gray-500 mt-1">Monitor and manage transaction disputes</p>
        </div>
        <div class="text-sm text-gray-500">
          Showing <?= $pagination['start_item'] ?>-<?= $pagination['end_item'] ?> of <?= $pagination['total_items'] ?> disputes
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead>
            <tr class="text-gray-600 border-b">
              <th class="py-3 px-4 font-medium">Dispute ID</th>
              <th class="py-3 px-4 font-medium">Order ID</th>
              <th class="py-3 px-4 font-medium">Buyer</th>
              <th class="py-3 px-4 font-medium">Seller</th>
              <th class="py-3 px-4 font-medium">Issue</th>
              <th class="py-3 px-4 font-medium">Amount</th>
              <th class="py-3 px-4 font-medium">Days Open</th>
              <th class="py-3 px-4 font-medium">Status</th>
              <th class="py-3 px-4 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-sm">
            <?php if (empty($disputes)): ?>
              <tr>
                <td colspan="9" class="px-6 py-16 text-center">
                  <div class="flex flex-col items-center justify-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                      <i class="fa fa-gavel text-gray-400 text-2xl"></i>
                    </div>
                    <?php if ($showEmptyState): ?>
                      <h3 class="text-lg font-medium text-gray-900 mb-2">No Dispute Records</h3>
                      <p class="text-gray-500 mb-4 max-w-sm">Dispute records will appear here when users report transaction issues</p>
                      <div class="bg-green-50 px-4 py-2 rounded-lg">
                        <p class="text-sm text-green-700 font-medium">
                          <i class="fa fa-shield-check mr-1"></i> Dispute management system ready
                        </p>
                      </div>
                    <?php else: ?>
                      <h3 class="text-lg font-medium text-gray-900 mb-2">No Disputes Match Your Filters</h3>
                      <p class="text-gray-500 mb-4">Try adjusting your search criteria or clearing filters</p>
                      <a href="?p=dashboard&page=superAdminDisputes" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                        <i class="fa fa-times mr-2"></i> Clear Filters
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($disputes as $dispute): ?>
                <?php
                  // Status configuration
                  $statusConfig = [
                    'open' => ['bg-red-100 text-red-700', 'fa-exclamation-triangle', 'Open'],
                    'investigating' => ['bg-yellow-100 text-yellow-700', 'fa-clock', 'Investigating'],
                    'resolved' => ['bg-green-100 text-green-700', 'fa-check-circle', 'Resolved'],
                    'escalated' => ['bg-purple-100 text-purple-700', 'fa-arrow-up', 'Escalated']
                  ];
                  $statusInfo = $statusConfig[$dispute['status']] ?? ['bg-gray-100 text-gray-700', 'fa-question', 'Unknown'];
                  
                  // Priority configuration
                  $priorityConfig = [
                    'high' => 'text-red-500',
                    'medium' => 'text-yellow-500',
                    'low' => 'text-green-500'
                  ];
                  $priorityColor = $priorityConfig[$dispute['priority']] ?? 'text-gray-500';
                  
                  // Calculate days open
                  $daysOpen = (time() - strtotime($dispute['created_at'])) / (60 * 60 * 24);
                  $daysOpen = floor($daysOpen);
                ?>
                <tr class="hover-row transition-colors">
                  <td class="py-4 px-4">
                    <div class="font-medium text-gray-800"><?= htmlspecialchars($dispute['case_id']) ?></div>
                    <div class="text-xs text-gray-500 mt-1">Opened: <?= date('d/m/Y', strtotime($dispute['created_at'])) ?></div>
                  </td>
                  <td class="py-4 px-4">
                    <div class="font-medium text-gray-800">ORD-<?= str_pad($dispute['id'], 3, '0', STR_PAD_LEFT) ?></div>
                    <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($dispute['listing']) ?></div>
                  </td>
                  <td class="py-4 px-4">
                    <div class="font-medium text-gray-800"><?= htmlspecialchars($dispute['buyer']) ?></div>
                    <div class="text-xs text-gray-500 mt-1">Buyer</div>
                  </td>
                  <td class="py-4 px-4">
                    <div class="font-medium text-gray-800"><?= htmlspecialchars($dispute['seller']) ?></div>
                    <div class="text-xs text-gray-500 mt-1">Seller</div>
                  </td>
                  <td class="py-4 px-4">
                    <div class="font-medium text-gray-800"><?= htmlspecialchars($dispute['reason']) ?></div>
                    <div class="text-xs text-gray-500 mt-1">Dispute reason</div>
                  </td>
                  <td class="py-4 px-4">
                    <div class="font-semibold text-gray-800">$<?= number_format($dispute['amount']) ?></div>
                    <div class="text-xs text-gray-500 mt-1">In dispute</div>
                  </td>
                  <td class="py-4 px-4">
                    <div class="<?= $priorityColor ?> font-medium"><?= $daysOpen ?> days</div>
                    <div class="text-xs text-gray-500 mt-1"><?= ucfirst($dispute['priority']) ?> priority</div>
                  </td>
                  <td class="py-4 px-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-full <?= $statusInfo[0] ?> text-xs font-medium">
                      <i class="fa <?= $statusInfo[1] ?> mr-1.5"></i> <?= $statusInfo[2] ?>
                    </span>
                  </td>
                  <td class="py-4 px-4">
                    <div class="flex flex-col gap-2">
                      <button onclick="viewDispute(<?= $dispute['id'] ?>)" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                        <i class="fa fa-eye mr-1.5"></i> View Details
                      </button>
                      <?php if ($dispute['status'] === 'open' || $dispute['status'] === 'investigating'): ?>
                        <button onclick="resolveDispute(<?= $dispute['id'] ?>)" class="text-green-600 hover:text-green-800 text-sm font-medium flex items-center">
                          <i class="fa fa-check mr-1.5"></i> Resolve
                        </button>
                      <?php endif; ?>
                      <button onclick="updatePriority(<?= $dispute['id'] ?>, '<?= $dispute['priority'] ?>')" class="text-orange-600 hover:text-orange-800 text-sm font-medium flex items-center">
                        <i class="fa fa-exclamation-triangle mr-1.5"></i> Priority
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      
      <!-- Pagination -->
      <div class="mt-6 pt-4 border-t border-gray-200">
        <?php 
        $extraParams = ['p' => 'dashboard', 'page' => 'superAdminDisputes'];
        if ($search) $extraParams['search'] = $search;
        if ($status) $extraParams['status'] = $status;
        if ($priority) $extraParams['priority'] = $priority;
        
        echo renderPagination($pagination, url('index.php'), $extraParams, 'pg'); 
        ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Summary Section - Only show if we have data -->
    <?php if ($hasRealData): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
      <!-- Dispute Types -->
      <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Dispute Types</h3>
        <div class="space-y-4">
          <div class="flex justify-between items-center">
            <div class="flex items-center">
              <div class="w-3 h-3 rounded-full bg-red-500 mr-3"></div>
              <span class="text-sm text-gray-600">Non-Delivery</span>
            </div>
            <span class="text-sm font-medium">24 disputes</span>
          </div>
          <div class="flex justify-between items-center">
            <div class="flex items-center">
              <div class="w-3 h-3 rounded-full bg-yellow-500 mr-3"></div>
              <span class="text-sm text-gray-600">Data Mismatch</span>
            </div>
            <span class="text-sm font-medium">18 disputes</span>
          </div>
          <div class="flex justify-between items-center">
            <div class="flex items-center">
              <div class="w-3 h-3 rounded-full bg-blue-500 mr-3"></div>
              <span class="text-sm text-gray-600">Access Issues</span>
            </div>
            <span class="text-sm font-medium">12 disputes</span>
          </div>
          <div class="flex justify-between items-center">
            <div class="flex items-center">
              <div class="w-3 h-3 rounded-full bg-purple-500 mr-3"></div>
              <span class="text-sm text-gray-600">Quality Concerns</span>
            </div>
            <span class="text-sm font-medium">11 disputes</span>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
        <div class="space-y-3">
          <button class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors flex items-center justify-between">
            <div class="flex items-center">
              <i class="fa fa-file-export text-blue-500 mr-3"></i>
              <span class="text-sm font-medium">Export Disputes Report</span>
            </div>
            <i class="fa fa-chevron-right text-gray-400"></i>
          </button>
          <button class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors flex items-center justify-between">
            <div class="flex items-center">
              <i class="fa fa-user-shield text-green-500 mr-3"></i>
              <span class="text-sm font-medium">Assign Mediator</span>
            </div>
            <i class="fa fa-chevron-right text-gray-400"></i>
          </button>
          <button class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors flex items-center justify-between">
            <div class="flex items-center">
              <i class="fa fa-cog text-purple-500 mr-3"></i>
              <span class="text-sm font-medium">Dispute Settings</span>
            </div>
            <i class="fa fa-chevron-right text-gray-400"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Dispute Details Modal -->
  <div id="disputeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
      <div class="p-6 border-b border-gray-200 flex justify-between items-center">
        <h3 class="text-xl font-bold text-gray-900">Dispute Details</h3>
        <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
          <i class="fa fa-times text-xl"></i>
        </button>
      </div>
      <div id="disputeContent" class="p-6">
        <!-- Content will be loaded here -->
      </div>
    </div>
  </div>

  <!-- Resolve Dispute Modal -->
  <div id="resolveModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-lg w-full">
      <div class="p-6 border-b border-gray-200">
        <h3 class="text-xl font-bold text-gray-900">Resolve Dispute</h3>
      </div>
      <form method="POST" class="p-6">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="dispute_id" id="resolve_dispute_id">
        
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Resolution Status</label>
          <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" required>
            <option value="resolved">Resolved</option>
            <option value="escalated">Escalated</option>
          </select>
        </div>
        
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Resolution Notes</label>
          <textarea name="resolution" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" required placeholder="Describe the resolution..."></textarea>
        </div>
        
        <div class="flex gap-3">
          <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium">
            <i class="fa fa-check mr-2"></i>Resolve Dispute
          </button>
          <button type="button" onclick="closeResolveModal()" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium">
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Priority Update Modal -->
  <div id="priorityModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full">
      <div class="p-6 border-b border-gray-200">
        <h3 class="text-xl font-bold text-gray-900">Update Priority</h3>
      </div>
      <form method="POST" class="p-6">
        <input type="hidden" name="action" value="update_priority">
        <input type="hidden" name="dispute_id" id="priority_dispute_id">
        
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Priority Level</label>
          <select name="priority" id="priority_select" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" required>
            <option value="low">Low Priority</option>
            <option value="medium">Medium Priority</option>
            <option value="high">High Priority</option>
          </select>
        </div>
        
        <div class="flex gap-3">
          <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium">
            Update Priority
          </button>
          <button type="button" onclick="closePriorityModal()" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium">
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
  function viewDispute(disputeId) {
    document.getElementById('disputeModal').classList.remove('hidden');
    document.getElementById('disputeContent').innerHTML = '<div class="text-center py-8"><i class="fa fa-spinner fa-spin text-3xl text-blue-600"></i></div>';
    
    // In a real implementation, fetch dispute details via AJAX
    setTimeout(() => {
      document.getElementById('disputeContent').innerHTML = `
        <div class="space-y-4">
          <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="font-semibold text-gray-900 mb-2">Dispute Information</h4>
            <p class="text-sm text-gray-600">Dispute ID: <span class="font-medium">${disputeId}</span></p>
            <p class="text-sm text-gray-600 mt-1">Status: <span class="font-medium">Open</span></p>
          </div>
          <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="font-semibold text-gray-900 mb-2">Description</h4>
            <p class="text-sm text-gray-600">Dispute details will be loaded here...</p>
          </div>
        </div>
      `;
    }, 500);
  }
  
  function closeModal() {
    document.getElementById('disputeModal').classList.add('hidden');
  }
  
  function resolveDispute(disputeId) {
    document.getElementById('resolve_dispute_id').value = disputeId;
    document.getElementById('resolveModal').classList.remove('hidden');
  }
  
  function closeResolveModal() {
    document.getElementById('resolveModal').classList.add('hidden');
  }
  
  function updatePriority(disputeId, currentPriority) {
    document.getElementById('priority_dispute_id').value = disputeId;
    document.getElementById('priority_select').value = currentPriority;
    document.getElementById('priorityModal').classList.remove('hidden');
  }
  
  function closePriorityModal() {
    document.getElementById('priorityModal').classList.add('hidden');
  }
  
  document.addEventListener('DOMContentLoaded', () => {
    // Define BASE constant globally
    const BASE = "<?php echo BASE; ?>";
    console.log('🔧 BASE constant defined:', BASE);
    
    // Ensure API_BASE_PATH is set
    if (!window.API_BASE_PATH) {
      const path = window.location.pathname;
      window.API_BASE_PATH = (path.includes('/marketplace/') ? '/marketplace' : '') + '/api';
      console.log('🔧 [Disputes] API_BASE_PATH:', window.API_BASE_PATH);
    }
    
    console.log('🚀 SuperAdmin Disputes polling initialization started');
    
    if (typeof startPolling !== 'undefined') {
      startPolling({
        orders: (newOrders) => {
          console.log('⚠️ New disputes/orders detected:', newOrders.length);
          if (newOrders.length > 0) {
            // Check for disputed orders
            const disputedOrders = newOrders.filter(o => o.status === 'disputed' || o.has_dispute);
            
            if (disputedOrders.length > 0) {
              // Update dispute count
              const disputeCountEl = document.querySelector('[data-stat="total-disputes"]');
              if (disputeCountEl) {
                const current = parseInt(disputeCountEl.textContent) || 0;
                disputeCountEl.textContent = current + disputedOrders.length;
                disputeCountEl.classList.add('animate-pulse');
                setTimeout(() => disputeCountEl.classList.remove('animate-pulse'), 1000);
              }
              
              // Add to disputes table
              const tbody = document.querySelector('table tbody');
              if (tbody) {
                disputedOrders.slice(0, 5).forEach(order => {
                  const row = document.createElement('tr');
                  row.className = 'hover:bg-gray-50 animate-fade-in';
                  row.style.backgroundColor = '#fee2e2';
                  
                  row.innerHTML = `
                    <td class="py-4 px-6 font-medium">DSP${order.id || 'NEW'}</td>
                    <td class="py-4 px-6">${order.listing_name || 'N/A'}</td>
                    <td class="py-4 px-6">${order.buyer_name || 'N/A'}</td>
                    <td class="py-4 px-6">${order.seller_name || 'N/A'}</td>
                    <td class="py-4 px-6">
                      <span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        Open
                      </span>
                    </td>
                    <td class="py-4 px-6 text-sm text-gray-600">Just now</td>
                  `;
                  
                  tbody.insertBefore(row, tbody.firstChild);
                  
                  setTimeout(() => {
                    row.style.backgroundColor = '';
                  }, 5000);
                });
              }
              
              // Show notification
              if (typeof PollingUIHelpers !== 'undefined') {
                PollingUIHelpers.showBriefNotification(
                  `${disputedOrders.length} new dispute(s) detected!`,
                  'error'
                );
              }
            }
          }
        }
      });
    } else {
      console.warn('⚠️ Polling system not available');
    }
  });
  </script>
</body>
</html>