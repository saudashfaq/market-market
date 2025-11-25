
<?php
// Check for export FIRST - before any output
if (isset($_GET['export'])) {
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../includes/export_helper.php';
    
    ob_start();
    require_login();
    ob_end_clean();
    
    $pdo = db();
    $activeSection = $_GET['section'] ?? 'logs';
    
    // Export based on active section
    if ($activeSection === 'logs') {
        $exportSql = "
            SELECT l.id, l.action, l.details, l.ip_address, l.created_at,
                   u.name as user_name, u.email as user_email, u.role
            FROM logs l
            LEFT JOIN users u ON l.user_id = u.id
            WHERE l.user_id IS NOT NULL AND u.role = 'user'
            ORDER BY l.created_at DESC
        ";
        $title = 'Admin Reports - Audit Logs';
    } elseif ($activeSection === 'listing') {
        $exportSql = "
            SELECT l.id, l.action, l.details, l.created_at,
                   u.name as user_name, u.email as user_email
            FROM logs l
            LEFT JOIN users u ON l.user_id = u.id
            WHERE l.user_id IS NOT NULL AND u.role = 'user' 
            AND (l.action LIKE '%listing%' OR l.details LIKE '%listing%')
            ORDER BY l.created_at DESC
        ";
        $title = 'Admin Reports - Listing Changes';
    } else {
        $exportSql = "
            SELECT l.id, l.action, l.details, l.created_at,
                   u.name as user_name, u.email as user_email
            FROM logs l
            LEFT JOIN users u ON l.user_id = u.id
            WHERE l.user_id IS NOT NULL AND u.role = 'user'
            AND (l.action LIKE '%payment%' OR l.details LIKE '%payment%')
            ORDER BY l.created_at DESC
        ";
        $title = 'Admin Reports - Payment Logs';
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

// Get pagination parameters
$page = getCurrentPage('pg');
$perPage = 10;

// Get current section
$activeSection = $_GET['section'] ?? 'logs';
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

// Get real data from database
$logs = [];
$listingChanges = [];
$paymentLogs = [];
$listingChangesCount = 0;
$paymentLogsCount = 0;

try {
    // Get audit logs - from all users
    if ($activeSection === 'logs') {
        $whereClause = 'WHERE l.user_id IS NOT NULL';
        $params = [];

        if ($search) {
            $whereClause .= ' AND (l.action LIKE :search OR l.details LIKE :search OR u.name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql = "SELECT l.*, u.name as user_name, u.role FROM logs l LEFT JOIN users u ON l.user_id = u.id $whereClause ORDER BY l.created_at DESC";
        $countSql = "SELECT COUNT(*) as total FROM logs l LEFT JOIN users u ON l.user_id = u.id $whereClause";

        $result = getCustomPaginationData($pdo, $sql, $countSql, $params, $page, $perPage);
        $logs = $result['data'];
        $pagination = $result['pagination'];
    } elseif ($activeSection === 'listing') {
        // Get listing changes with pagination - from all users
        $whereClause = 'WHERE l.user_id IS NOT NULL AND (l.action LIKE \'%listing%\' OR l.action LIKE \'%Listing%\' OR l.details LIKE \'%listing%\')';
        $params = [];

        if ($search) {
            $whereClause .= ' AND (l.action LIKE :search OR l.details LIKE :search OR u.name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql = "SELECT l.*, u.name as user_name, u.role FROM logs l LEFT JOIN users u ON l.user_id = u.id $whereClause ORDER BY l.created_at DESC";
        $countSql = "SELECT COUNT(*) as total FROM logs l LEFT JOIN users u ON l.user_id = u.id $whereClause";

        $result = getCustomPaginationData($pdo, $sql, $countSql, $params, $page, $perPage);
        $listingChanges = $result['data'];
        $pagination = $result['pagination'];
    } elseif ($activeSection === 'payment') {
        // Get payment logs with pagination - from all users
        $whereClause = 'WHERE l.user_id IS NOT NULL AND (l.action LIKE \'%payment%\' OR l.action LIKE \'%Payment%\' OR l.details LIKE \'%payment%\' OR l.details LIKE \'%escrow%\')';
        $params = [];

        if ($search) {
            $whereClause .= ' AND (l.action LIKE :search OR l.details LIKE :search OR u.name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql = "SELECT l.*, u.name as user_name, u.role FROM logs l LEFT JOIN users u ON l.user_id = u.id $whereClause ORDER BY l.created_at DESC";
        $countSql = "SELECT COUNT(*) as total FROM logs l LEFT JOIN users u ON l.user_id = u.id $whereClause";

        $result = getCustomPaginationData($pdo, $sql, $countSql, $params, $page, $perPage);
        $paymentLogs = $result['data'];
        $pagination = $result['pagination'];
    }
    
    // Get counts for stats boxes - from all users
    $listingChangesCountStmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM logs l 
        LEFT JOIN users u ON l.user_id = u.id
        WHERE l.user_id IS NOT NULL AND (l.action LIKE '%listing%' OR l.action LIKE '%Listing%' OR l.details LIKE '%listing%')
    ");
    $listingChangesCountStmt->execute();
    $listingChangesCount = $listingChangesCountStmt->fetchColumn();
    
    $paymentLogsCountStmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM logs l 
        LEFT JOIN users u ON l.user_id = u.id
        WHERE l.user_id IS NOT NULL AND (l.action LIKE '%payment%' OR l.action LIKE '%Payment%' OR l.details LIKE '%payment%' OR l.details LIKE '%escrow%')
    ");
    $paymentLogsCountStmt->execute();
    $paymentLogsCount = $paymentLogsCountStmt->fetchColumn();
    
} catch (Exception $e) {
    // If logs table doesn't exist, show empty state
    $logs = [];
    $listingChanges = [];
    $paymentLogs = [];
    $listingChangesCount = 0;
    $paymentLogsCount = 0;
    
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
  <title>Logs & Reports Dashboard</title>
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
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
    }
    .table-container {
      overflow-x: auto;
    }
    .table-container table {
      min-width: 800px;
    }
    @media (max-width: 768px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    @media (max-width: 480px) {
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
      <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Logs & Reports</h1>
      <p class="text-gray-500 mt-1">Monitor and track all system activities and transactions</p>
    </div>
    <div class="flex items-center gap-4">
      <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1.5 rounded-full">
        <i class="fa fa-lock mr-1.5"></i> Read-only access
      </span>
      <?php require_once __DIR__ . '/../../includes/export_helper.php'; echo getExportButton('admin_reports'); ?>
    </div>
  </div>

  <!-- Stats Boxes -->
  <div class="stats-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-500">Audit Entries</p>
          <p class="text-2xl md:text-3xl font-bold text-gray-900 mt-1"><?= $pagination['total_items'] ?></p>
        </div>
        <div class="bg-blue-50 p-3 rounded-lg">
          <i class="fa fa-clipboard-list text-blue-500 text-xl"></i>
        </div>
      </div>
      <div class="mt-4 flex items-center text-xs text-gray-500">
        <?php if ($pagination['total_items'] > 0): ?>
          <i class="fa fa-clock mr-1"></i> Updated just now
        <?php else: ?>
          <i class="fa fa-info-circle mr-1"></i> No entries yet
        <?php endif; ?>
      </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-500">Listing Changes</p>
          <p class="text-2xl md:text-3xl font-bold text-gray-900 mt-1"><?= $listingChangesCount ?></p>
        </div>
        <div class="bg-green-50 p-3 rounded-lg">
          <i class="fa fa-list-check text-green-500 text-xl"></i>
        </div>
      </div>
      <div class="mt-4 flex items-center text-xs text-gray-500">
        <?php if ($listingChangesCount > 0): ?>
          <i class="fa fa-clock mr-1"></i> Updated recently
        <?php else: ?>
          <i class="fa fa-info-circle mr-1"></i> No changes yet
        <?php endif; ?>
      </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-500">Payment Logs</p>
          <p class="text-2xl md:text-3xl font-bold text-gray-900 mt-1"><?= $paymentLogsCount ?></p>
        </div>
        <div class="bg-purple-50 p-3 rounded-lg">
          <i class="fa fa-credit-card text-purple-500 text-xl"></i>
        </div>
      </div>
      <div class="mt-4 flex items-center text-xs text-gray-500">
        <?php if ($paymentLogsCount > 0): ?>
          <i class="fa fa-clock mr-1"></i> Updated recently
        <?php else: ?>
          <i class="fa fa-info-circle mr-1"></i> No payments yet
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="flex flex-wrap gap-2 mb-6">
    <a href="?p=dashboard&page=adminreports&section=logs" class="tab-btn px-4 py-2.5 text-sm font-medium <?= $activeSection === 'logs' ? 'bg-white shadow-sm border border-gray-200' : 'text-gray-600 bg-gray-100' ?> rounded-lg flex items-center transition-colors">
      <i class="fa fa-clipboard-list mr-2 text-blue-500"></i> 
      User Activity 
      <span class="ml-2 bg-blue-100 text-blue-700 text-xs font-medium px-2 py-0.5 rounded-full"><?= $pagination['total_items'] ?? 0 ?></span>
    </a>
    <a href="?p=dashboard&page=adminreports&section=listing" class="tab-btn px-4 py-2.5 text-sm font-medium <?= $activeSection === 'listing' ? 'bg-white shadow-sm border border-gray-200' : 'text-gray-600 bg-gray-100' ?> rounded-lg flex items-center transition-colors">
      <i class="fa fa-list-check mr-2 text-green-500"></i> 
      User Listings 
      <span class="ml-2 bg-green-100 text-green-700 text-xs font-medium px-2 py-0.5 rounded-full"><?= $listingChangesCount ?></span>
    </a>
    <a href="?p=dashboard&page=adminreports&section=payment" class="tab-btn px-4 py-2.5 text-sm font-medium <?= $activeSection === 'payment' ? 'bg-white shadow-sm border border-gray-200' : 'text-gray-600 bg-gray-100' ?> rounded-lg flex items-center transition-colors">
      <i class="fa fa-credit-card mr-2 text-purple-500"></i> 
      User Payments 
      <span class="ml-2 bg-purple-100 text-purple-700 text-xs font-medium px-2 py-0.5 rounded-full"><?= $paymentLogsCount ?></span>
    </a>
  </div>

  <!-- Audit Logs -->
  <div id="audit" class="tab-content bg-white rounded-xl shadow-sm p-4 md:p-6 mb-6 border border-gray-100 <?= $activeSection !== 'logs' ? 'hidden' : '' ?>">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
      <div>
        <h2 class="text-lg md:text-xl font-semibold text-gray-900">User Activity Logs</h2>
        <p class="text-sm text-gray-500 mt-1">Track all user activities and actions</p>
      </div>
      <div class="mt-2 md:mt-0 flex items-center gap-2">
        <div class="relative">
          <i class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
          <input type="text" placeholder="Search logs..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <button class="text-gray-500 hover:text-gray-700 p-2 rounded-lg border border-gray-300">
          <i class="fa fa-filter"></i>
        </button>
      </div>
    </div>
    
    <div class="table-container">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-gray-600 border-b">
            <th class="py-3 text-left font-medium">Action Type</th>
            <th class="py-3 text-left font-medium">User</th>
            <th class="py-3 text-left font-medium">Timestamp</th>
            <th class="py-3 text-left font-medium">Details</th>
            <th class="py-3 text-left font-medium">IP Address</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <?php if (!empty($logs)): ?>
            <?php foreach ($logs as $log): ?>
              <tr class="hover:bg-gray-50 transition-colors">
                <td class="py-4">
                  <span class="status-badge bg-green-100 text-green-700">
                    <i class="fa fa-check-circle mr-1.5"></i> <?= htmlspecialchars($log['action'] ?? 'System Action') ?>
                  </span>
                </td>
                <td class="py-4 font-medium"><?= htmlspecialchars($log['user_name'] ?? 'Unknown User') ?></td>
                <td class="py-4 text-gray-600"><?= date('d/m/Y, H:i:s', strtotime($log['created_at'] ?? 'now')) ?></td>
                <td class="py-4"><?= htmlspecialchars($log['details'] ?? 'No details available') ?></td>
                <td class="py-4 text-gray-500"><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="py-16 text-center">
                <div class="flex flex-col items-center justify-center text-gray-400">
                  <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-clipboard-list text-3xl"></i>
                  </div>
                  <h3 class="text-lg font-medium text-gray-700 mb-2">No audit logs found</h3>
                  <p class="text-gray-500 mb-4">No audit log entries match your search criteria.</p>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    
    <div class="flex flex-col md:flex-row justify-between items-center mt-6 pt-4 border-t border-gray-200">

      <!-- Pagination -->
      <?php 
      $extraParams = ['p' => 'dashboard', 'page' => 'adminreports', 'section' => 'logs'];
      if ($search) $extraParams['search'] = $search;
      
      echo renderPagination($pagination, url('index.php'), $extraParams, 'pg'); 
      ?>

    </div>
  </div>

  <!-- Listing Changes -->
  <div id="listing" class="tab-content bg-white rounded-xl shadow-sm p-4 md:p-6 mb-6 border border-gray-100 <?= $activeSection !== 'listing' ? 'hidden' : '' ?>">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
      <div>
        <h2 class="text-lg md:text-xl font-semibold text-gray-900">User Listing Changes</h2>
        <p class="text-sm text-gray-500 mt-1">Track user listing modifications and updates</p>
      </div>
      <div class="mt-2 md:mt-0 flex items-center gap-2">
        <div class="relative">
          <i class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
          <input type="text" placeholder="Search changes..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <button class="text-gray-500 hover:text-gray-700 p-2 rounded-lg border border-gray-300">
          <i class="fa fa-filter"></i>
        </button>
      </div>
    </div>
    
    <div class="table-container">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-gray-600 border-b">
            <th class="py-3 text-left font-medium">Change Type</th>
            <th class="py-3 text-left font-medium">Changed By</th>
            <th class="py-3 text-left font-medium">Timestamp</th>
            <th class="py-3 text-left font-medium">Details</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <?php if (!empty($listingChanges)): ?>
            <?php foreach ($listingChanges as $change): 
              // Determine change type and icon based on action
              $changeType = 'General Change';
              $iconClass = 'fa-edit';
              $badgeColor = 'bg-blue-100 text-blue-600';
              
              if (stripos($change['action'], 'create') !== false || stripos($change['action'], 'submit') !== false) {
                $changeType = 'Listing Created';
                $iconClass = 'fa-plus';
                $badgeColor = 'bg-green-100 text-green-600';
              } elseif (stripos($change['action'], 'update') !== false || stripos($change['action'], 'edit') !== false) {
                $changeType = 'Listing Updated';
                $iconClass = 'fa-edit';
                $badgeColor = 'bg-yellow-100 text-yellow-600';
              } elseif (stripos($change['action'], 'approve') !== false || stripos($change['action'], 'verify') !== false) {
                $changeType = 'Listing Approved';
                $iconClass = 'fa-check';
                $badgeColor = 'bg-green-100 text-green-600';
              } elseif (stripos($change['action'], 'reject') !== false || stripos($change['action'], 'decline') !== false) {
                $changeType = 'Listing Rejected';
                $iconClass = 'fa-times';
                $badgeColor = 'bg-red-100 text-red-600';
              } elseif (stripos($change['action'], 'delete') !== false) {
                $changeType = 'Listing Deleted';
                $iconClass = 'fa-trash';
                $badgeColor = 'bg-red-100 text-red-600';
              } elseif (stripos($change['details'], 'price') !== false) {
                $changeType = 'Price Updated';
                $iconClass = 'fa-dollar-sign';
                $badgeColor = 'bg-yellow-100 text-yellow-600';
              }
            ?>
              <tr class="hover:bg-gray-50 transition-colors">
                <td class="py-4">
                  <span class="status-badge <?= $badgeColor ?>">
                    <i class="fa <?= $iconClass ?> mr-1.5"></i> <?= $changeType ?>
                  </span>
                </td>
                <td class="py-4 font-medium"><?= htmlspecialchars($change['user_name'] ?? 'System') ?></td>
                <td class="py-4 text-gray-600"><?= date('d/m/Y, H:i:s', strtotime($change['created_at'])) ?></td>
                <td class="py-4"><?= htmlspecialchars($change['details'] ?? $change['action']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="4" class="py-16 text-center">
                <div class="flex flex-col items-center justify-center text-gray-400">
                  <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-list-check text-3xl"></i>
                  </div>
                  <h3 class="text-lg font-medium text-gray-700 mb-2">No listing changes found</h3>
                  <p class="text-gray-500 mb-4">Listing modifications will appear here when they occur.</p>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    
    <div class="flex flex-col md:flex-row justify-between items-center mt-6 pt-4 border-t border-gray-200">
      <!-- Pagination -->
      <?php if ($activeSection === 'listing'): ?>
        <?php 
        $extraParams = ['p' => 'dashboard', 'page' => 'adminreports', 'section' => 'listing'];
        if ($search) $extraParams['search'] = $search;
        
        echo renderPagination($pagination, url('index.php'), $extraParams, 'pg'); 
        ?>
      <?php else: ?>
        <p class="text-sm text-gray-500 mb-4 md:mb-0">Showing <?= count($listingChanges) ?> of <?= $listingChangesCount ?> entries</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Payment Logs -->
  <div id="payment" class="tab-content bg-white rounded-xl shadow-sm p-4 md:p-6 mb-6 border border-gray-100 <?= $activeSection !== 'payment' ? 'hidden' : '' ?>">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
      <div>
        <h2 class="text-lg md:text-xl font-semibold text-gray-900">User Payment Logs</h2>
        <p class="text-sm text-gray-500 mt-1">Track user payment activities and transactions</p>
      </div>
      <div class="mt-2 md:mt-0 flex items-center gap-2">
        <div class="relative">
          <i class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
          <input type="text" placeholder="Search payments..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <button class="text-gray-500 hover:text-gray-700 p-2 rounded-lg border border-gray-300">
          <i class="fa fa-filter"></i>
        </button>
      </div>
    </div>
    
    <div class="table-container">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-gray-600 border-b">
            <th class="py-3 text-left font-medium">Transaction</th>
            <th class="py-3 text-left font-medium">User</th>
            <th class="py-3 text-left font-medium">Amount</th>
            <th class="py-3 text-left font-medium">Status</th>
            <th class="py-3 text-left font-medium">Timestamp</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <?php if (!empty($paymentLogs)): ?>
            <?php foreach ($paymentLogs as $payment): 
              // Extract amount from details if possible
              $amount = 'N/A';
              if (preg_match('/\$[\d,]+/', $payment['details'], $matches)) {
                $amount = $matches[0];
              }
              
              // Determine status based on action/details
              $status = 'Completed';
              $statusColor = 'bg-green-100 text-green-700';
              $statusIcon = 'fa-circle-check';
              
              if (stripos($payment['action'], 'pending') !== false || stripos($payment['details'], 'pending') !== false) {
                $status = 'Pending';
                $statusColor = 'bg-yellow-100 text-yellow-600';
                $statusIcon = 'fa-clock';
              } elseif (stripos($payment['action'], 'failed') !== false || stripos($payment['details'], 'failed') !== false) {
                $status = 'Failed';
                $statusColor = 'bg-red-100 text-red-600';
                $statusIcon = 'fa-times-circle';
              } elseif (stripos($payment['action'], 'escrow') !== false || stripos($payment['details'], 'escrow') !== false) {
                $status = 'In Escrow';
                $statusColor = 'bg-blue-100 text-blue-600';
                $statusIcon = 'fa-shield-alt';
              }
            ?>
              <tr class="hover:bg-gray-50 transition-colors">
                <td class="py-4 font-medium"><?= htmlspecialchars($payment['action']) ?></td>
                <td class="py-4"><?= htmlspecialchars($payment['user_name'] ?? 'System') ?></td>
                <td class="py-4 font-medium"><?= $amount ?></td>
                <td class="py-4">
                  <span class="status-badge <?= $statusColor ?>">
                    <i class="fa <?= $statusIcon ?> mr-1.5"></i> <?= $status ?>
                  </span>
                </td>
                <td class="py-4 text-gray-600"><?= date('d/m/Y, H:i:s', strtotime($payment['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="py-16 text-center">
                <div class="flex flex-col items-center justify-center text-gray-400">
                  <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-credit-card text-3xl"></i>
                  </div>
                  <h3 class="text-lg font-medium text-gray-700 mb-2">No payment logs found</h3>
                  <p class="text-gray-500 mb-4">Payment transactions will appear here when they occur.</p>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    
    <div class="flex flex-col md:flex-row justify-between items-center mt-6 pt-4 border-t border-gray-200">
      <!-- Pagination -->
      <?php if ($activeSection === 'payment'): ?>
        <?php 
        $extraParams = ['p' => 'dashboard', 'page' => 'adminreports', 'section' => 'payment'];
        if ($search) $extraParams['search'] = $search;
        
        echo renderPagination($pagination, url('index.php'), $extraParams, 'pg'); 
        ?>
      <?php else: ?>
        <p class="text-sm text-gray-500 mb-4 md:mb-0">Showing <?= count($paymentLogs) ?> of <?= $paymentLogsCount ?> entries</p>
      <?php endif; ?>
    </div>
  </div>


</div>




<script src="<?= BASE ?>js/polling.js"></script>
<script>
const currentSection = '<?= $activeSection ?>';
let totalNewActivities = 0;

document.addEventListener('DOMContentLoaded', () => {
  console.log('🚀 Admin Reports polling initialization started');
  console.log('Current section:', currentSection);
  
  if (typeof startPolling === 'undefined') {
    console.error('❌ Polling system not loaded');
    return;
  }
  
  startPolling({
    logs: (newLogs) => {
      console.log('📝 New logs detected:', newLogs.length);
      if (newLogs.length > 0) {
        totalNewActivities += newLogs.length;
        updateStatsBoxes('logs', newLogs.length);
        
        if (currentSection === 'logs') {
          addNewLogEntries(newLogs, 'logs');
        }
        
        // Notifications removed
      }
    },
    
    listings: (newListings) => {
      console.log('📋 New listings detected:', newListings.length);
      if (newListings.length > 0) {
        // Only count new listings (pending status), not status changes
        const actualNewListings = newListings.filter(l => l.status === 'pending' || !l.status);
        const statusChanges = newListings.filter(l => l.status === 'approved' || l.status === 'rejected');
        
        if (actualNewListings.length > 0) {
          totalNewActivities += actualNewListings.length;
          updateStatsBoxes('listing', actualNewListings.length);
          
          if (currentSection === 'logs' || currentSection === 'listing') {
            addNewLogEntries(actualNewListings, 'listing');
          }
        }
        
        // Log status changes but don't increment counts
        if (statusChanges.length > 0) {
          console.log(`✅ ${statusChanges.length} listing(s) status changed (approved/rejected)`);
          
          if (currentSection === 'logs' || currentSection === 'listing') {
            addNewLogEntries(statusChanges, 'listing');
          }
        }
        
        // Notifications removed
      }
    },
    
    offers: (newOffers) => {
      console.log('💰 New offers detected:', newOffers.length);
      if (newOffers.length > 0) {
        totalNewActivities += newOffers.length;
        updateStatsBoxes('offer', newOffers.length);
        
        if (currentSection === 'logs') {
          addNewLogEntries(newOffers, 'offer');
        }
        
        // Notifications removed
      }
    },
    
    orders: (newOrders) => {
      console.log('💳 New orders detected:', newOrders.length);
      if (newOrders.length > 0) {
        totalNewActivities += newOrders.length;
        updateStatsBoxes('payment', newOrders.length);
        
        if (currentSection === 'logs' || currentSection === 'payment') {
          addNewLogEntries(newOrders, 'payment');
        }
        
        // Notifications removed
      }
    }
  });
  
  function updateStatsBoxes(type, count) {
    // Update Audit Entries count
    const auditCount = document.querySelector('.stats-grid > div:nth-child(1) p.text-2xl');
    if (auditCount) {
      const current = parseInt(auditCount.textContent) || 0;
      auditCount.textContent = current + count;
    }
    
    // Update specific stat based on type
    if (type === 'listing') {
      const listingCount = document.querySelector('.stats-grid > div:nth-child(2) p.text-2xl');
      if (listingCount) {
        const current = parseInt(listingCount.textContent) || 0;
        listingCount.textContent = current + count;
      }
      
      // Update tab badge
      const listingBadge = document.querySelector('a[href*="section=listing"] span.bg-green-100');
      if (listingBadge) {
        const current = parseInt(listingBadge.textContent) || 0;
        listingBadge.textContent = current + count;
      }
    } else if (type === 'payment') {
      const paymentCount = document.querySelector('.stats-grid > div:nth-child(3) p.text-2xl');
      if (paymentCount) {
        const current = parseInt(paymentCount.textContent) || 0;
        paymentCount.textContent = current + count;
      }
      
      // Update tab badge
      const paymentBadge = document.querySelector('a[href*="section=payment"] span.bg-purple-100');
      if (paymentBadge) {
        const current = parseInt(paymentBadge.textContent) || 0;
        paymentBadge.textContent = current + count;
      }
    }
    
    // Update logs tab badge
    const logsBadge = document.querySelector('a[href*="section=logs"] span.bg-blue-100');
    if (logsBadge) {
      const current = parseInt(logsBadge.textContent) || 0;
      logsBadge.textContent = current + count;
    }
  }
  
  function addNewLogEntries(entries, type) {
    let tbody;
    
    if (currentSection === 'logs') {
      tbody = document.querySelector('#audit tbody');
    } else if (currentSection === 'listing' && type === 'listing') {
      tbody = document.querySelector('#listing tbody');
    } else if (currentSection === 'payment' && type === 'payment') {
      tbody = document.querySelector('#payment tbody');
    }
    
    if (!tbody) {
      console.warn('No tbody found for section:', currentSection, 'type:', type);
      return;
    }
    
    // Remove "no data" row if exists
    const noDataRow = tbody.querySelector('td[colspan]');
    if (noDataRow) {
      noDataRow.parentElement.remove();
    }
    
    entries.slice(0, 5).forEach(entry => {
      const row = document.createElement('tr');
      row.className = 'hover:bg-gray-50 transition-colors animate-fade-in';
      row.style.backgroundColor = '#dbeafe';
      
      if (currentSection === 'logs') {
        // Handle actual log entries
        if (type === 'logs') {
          row.innerHTML = `
            <td class="py-4">
              <span class="status-badge bg-green-100 text-green-700">
                <i class="fa fa-check-circle mr-1.5"></i> ${entry.action || 'System Action'}
              </span>
            </td>
            <td class="py-4 font-medium">${entry.user_name || 'Unknown User'}</td>
            <td class="py-4 text-gray-600">Just now</td>
            <td class="py-4">${entry.details || 'No details available'}</td>
            <td class="py-4 text-gray-500">${entry.ip_address || 'N/A'}</td>
          `;
        } else {
          // Handle listings/offers/orders in logs section
          row.innerHTML = `
            <td class="py-4">
              <span class="status-badge bg-green-100 text-green-700">
                <i class="fa fa-check-circle mr-1.5"></i> ${type === 'listing' ? 'Listing Activity' : type === 'offer' ? 'Offer Activity' : 'Payment Activity'}
              </span>
            </td>
            <td class="py-4 font-medium">${entry.user_name || 'Unknown User'}</td>
            <td class="py-4 text-gray-600">Just now</td>
            <td class="py-4">${entry.name || entry.listing_name || entry.details || 'New activity'}</td>
            <td class="py-4 text-gray-500">N/A</td>
          `;
        }
      } else if (currentSection === 'listing') {
        row.innerHTML = `
          <td class="py-4">
            <span class="status-badge bg-green-100 text-green-600">
              <i class="fa fa-plus mr-1.5"></i> Listing Created
            </span>
          </td>
          <td class="py-4 font-medium">${entry.user_name || 'User'}</td>
          <td class="py-4 text-gray-600">Just now</td>
          <td class="py-4">${entry.name || entry.details || 'New listing'}</td>
        `;
      } else if (currentSection === 'payment') {
        row.innerHTML = `
          <td class="py-4 font-medium">${entry.action || 'Payment Activity'}</td>
          <td class="py-4">${entry.user_name || 'User'}</td>
          <td class="py-4 font-medium">$${entry.amount || '0.00'}</td>
          <td class="py-4">
            <span class="status-badge bg-blue-100 text-blue-600">
              <i class="fa fa-shield-alt mr-1.5"></i> In Escrow
            </span>
          </td>
          <td class="py-4 text-gray-600">Just now</td>
        `;
      }
      
      tbody.insertBefore(row, tbody.firstChild);
      
      // Remove highlight after 5 seconds
      setTimeout(() => {
        row.style.backgroundColor = '';
      }, 5000);
    });
  }
  
  // Notification functions removed
});
</script>

<style>
.animate-fade-in {
  animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>

</body>
</html>