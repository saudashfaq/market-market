<?php
// Normal page load
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/pagination_helper.php';
require_once __DIR__ . '/../../includes/export_helper.php';
require_login();

// Check for export FIRST - before any output
if (isset($_GET['export'])) {
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../includes/export_helper.php';
    
    // Suppress any output
    ob_start();
    require_login();
    ob_end_clean();
    
    $pdo = db();
    
    // Setup search and filter conditions
    $search = $_GET['search'] ?? '';
    $role = $_GET['role'] ?? '';
    $action = $_GET['action'] ?? '';
    
    $whereClause = 'WHERE 1=1';
    $params = [];
    
    if ($search) {
        $whereClause .= ' AND (l.action LIKE :search OR l.details LIKE :search OR u.name LIKE :search OR u.email LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }
    if ($role) {
        $whereClause .= ' AND l.role = :role';
        $params[':role'] = $role;
    }
    if ($action) {
        $whereClause .= ' AND l.action LIKE :action';
        $params[':action'] = '%' . $action . '%';
    }
    
    $sql = "
      SELECT l.*, u.name AS user_name, u.email AS user_email
      FROM logs l
      LEFT JOIN users u ON l.user_id = u.id
      $whereClause
      ORDER BY l.created_at DESC
    ";
    
    $exportStmt = $pdo->prepare($sql);
    $exportStmt->execute($params);
    $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
    handleExportRequest($exportData, 'Audit Logs Report');
    exit;
}

$pdo = db();

// Get pagination parameters
$page = getCurrentPage('pg');
$perPage = 10;

// Setup search and filter conditions
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$action = $_GET['action'] ?? '';

$whereClause = 'WHERE 1=1';
$params = [];

if ($search) {
    $whereClause .= ' AND (l.action LIKE :search OR l.details LIKE :search OR u.name LIKE :search OR u.email LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
if ($role) {
    $whereClause .= ' AND l.role = :role';
    $params[':role'] = $role;
}
if ($action) {
    $whereClause .= ' AND l.action LIKE :action';
    $params[':action'] = '%' . $action . '%';
}

$sql = "
  SELECT l.*, u.name AS user_name, u.email AS user_email
  FROM logs l
  LEFT JOIN users u ON l.user_id = u.id
  $whereClause
  ORDER BY l.created_at DESC
";

$countSql = "
  SELECT COUNT(*) as total
  FROM logs l
  LEFT JOIN users u ON l.user_id = u.id
  $whereClause
";

// Get paginated data
$result = getCustomPaginationData($pdo, $sql, $countSql, $params, $page, $perPage);
$logs = $result['data'];
$pagination = $result['pagination'];

// Calculate professional stats for super admin
try {
    // Total system activities
    $totalLogsStmt = $pdo->query("SELECT COUNT(*) as total FROM logs");
    $totalLogs = $totalLogsStmt->fetch()['total'] ?? 0;

    // Critical security events (failed logins, admin actions, webhook failures, credential issues)
    $securityEventsStmt = $pdo->query("
        SELECT COUNT(*) as total FROM logs 
        WHERE LOWER(action) LIKE '%failed%' 
        OR LOWER(action) LIKE '%login%' 
        OR LOWER(action) LIKE '%security%'
        OR LOWER(action) LIKE '%webhook%'
        OR LOWER(action) LIKE '%credentials%'
        OR role IN ('admin', 'super_admin', 'superadmin')
    ");
    $securityEvents = $securityEventsStmt->fetch()['total'] ?? 0;

    // Admin/Super Admin activities in last 24 hours
    $adminActivitiesStmt = $pdo->query("
        SELECT COUNT(*) as total FROM logs 
        WHERE role IN ('admin', 'super_admin') 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $adminActivities = $adminActivitiesStmt->fetch()['total'] ?? 0;
    
    // If no activities in last 24 hours, show all-time count
    if ($adminActivities == 0) {
        $adminActivitiesAllTimeStmt = $pdo->query("
            SELECT COUNT(*) as total FROM logs 
            WHERE role IN ('admin', 'super_admin', 'superadmin', 'superAdmin')
        ");
        $adminActivities = $adminActivitiesAllTimeStmt->fetch()['total'] ?? 0;
    }

    // System health indicators (successful vs failed actions)
    $successfulActionsStmt = $pdo->query("
        SELECT COUNT(*) as total FROM logs 
        WHERE LOWER(action) NOT LIKE '%failed%' 
        AND LOWER(action) NOT LIKE '%error%'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $successfulActions = $successfulActionsStmt->fetch()['total'] ?? 0;

    $failedActionsStmt = $pdo->query("
        SELECT COUNT(*) as total FROM logs 
        WHERE (LOWER(action) LIKE '%failed%' OR LOWER(action) LIKE '%error%')
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $failedActions = $failedActionsStmt->fetch()['total'] ?? 0;

    // Calculate system health percentage
    $totalTodayActions = $successfulActions + $failedActions;
    $systemHealth = $totalTodayActions > 0 ? round(($successfulActions / $totalTodayActions) * 100, 1) : 100;

    // Total active users (from users table)
    $activeUsersStmt = $pdo->query("
        SELECT COUNT(*) as total FROM users 
        WHERE status = 'active'
    ");
    $activeUsers = $activeUsersStmt->fetch()['total'] ?? 0;

    // Webhook events (24h)
    $webhookEventsStmt = $pdo->query("
        SELECT COUNT(*) as total FROM logs 
        WHERE LOWER(action) LIKE '%webhook%'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $webhookEvents = $webhookEventsStmt->fetch()['total'] ?? 0;

    // Credential operations (24h)
    $credentialOpsStmt = $pdo->query("
        SELECT COUNT(*) as total FROM logs 
        WHERE LOWER(action) LIKE '%credential%'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $credentialOps = $credentialOpsStmt->fetch()['total'] ?? 0;

} catch (Exception $e) {
    // Fallback values if database queries fail
    $totalLogs = 0;
    $securityEvents = 0;
    $adminActivities = 0;
    $systemHealth = 100;
    $activeUsers = 0;
    $webhookEvents = 0;
    $credentialOps = 0;
}
?>

<section class="p-6 bg-gray-50 min-h-screen">
  <div class="max-w-7xl mx-auto">
    <!-- Header Section -->
    <div class="mb-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
            <div class="rounded-full bg-blue-100 p-2">
              <i class="fa-solid fa-clipboard-list text-blue-600 text-xl"></i>
            </div>
            Audit Logs
          </h1>
          <p class="text-gray-600 mt-1">Monitor and track all system activities</p>
        </div>
        <div class="flex items-center gap-3">
          <?= getExportButton('audit_logs') ?>
        </div>
      </div>

      <!-- Search and Filter Section -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
          <input type="hidden" name="p" value="dashboard">
          <input type="hidden" name="page" value="superAdminAudit">
          
          <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fa fa-search mr-1"></i>Search Logs
            </label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                   placeholder="Search by action, details, user name or email..." 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
          </div>
          
          <div class="min-w-[150px]">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fa fa-user mr-1"></i>Role
            </label>
            <select name="role" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
              <option value="">All Roles</option>
              <option value="system" <?= $role === 'system' ? 'selected' : '' ?>>System</option>
              <option value="superadmin" <?= $role === 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
              <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
              <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>User</option>
              <option value="guest" <?= $role === 'guest' ? 'selected' : '' ?>>Guest</option>
            </select>
          </div>
          
          <div class="min-w-[150px]">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fa fa-cog mr-1"></i>Action
            </label>
            <select name="action" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
              <option value="">All Actions</option>
              <option value="Webhook" <?= $action === 'Webhook' ? 'selected' : '' ?>>Webhook</option>
              <option value="Credentials" <?= $action === 'Credentials' ? 'selected' : '' ?>>Credentials</option>
              <option value="login" <?= $action === 'login' ? 'selected' : '' ?>>Login</option>
              <option value="logout" <?= $action === 'logout' ? 'selected' : '' ?>>Logout</option>
              <option value="Listing" <?= $action === 'Listing' ? 'selected' : '' ?>>Listing</option>
              <option value="Offer" <?= $action === 'Offer' ? 'selected' : '' ?>>Offer</option>
              <option value="Payment" <?= $action === 'Payment' ? 'selected' : '' ?>>Payment</option>
              <option value="Ticket" <?= $action === 'Ticket' ? 'selected' : '' ?>>Ticket</option>
              <option value="create" <?= $action === 'create' ? 'selected' : '' ?>>Create</option>
              <option value="update" <?= $action === 'update' ? 'selected' : '' ?>>Update</option>
              <option value="delete" <?= $action === 'delete' ? 'selected' : '' ?>>Delete</option>
            </select>
          </div>
          
          <div class="flex gap-2">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
              <i class="fa fa-search mr-2"></i>Filter
            </button>
            <a href="?p=dashboard&page=superAdminAudit" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors flex items-center">
              <i class="fa fa-times mr-2"></i>Clear
            </a>
          </div>
        </form>
      </div>

      <!-- Professional Stats Cards for Super Admin -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- System Activities -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <div class="rounded-full bg-blue-100 p-3 mr-4">
                <i class="fa-solid fa-chart-line text-blue-600 text-lg"></i>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-600">System Activities</p>
                <p id="total-logs-count" class="text-2xl font-bold text-gray-900" data-count="<?= $totalLogs ?>"><?= number_format($totalLogs) ?></p>
              </div>
            </div>
            <div class="text-xs text-green-600 bg-green-50 px-2 py-1 rounded-full">
              All Time
            </div>
          </div>
          <div class="mt-4 text-xs text-gray-500">
            <i class="fa-solid fa-info-circle mr-1"></i>
            Total logged activities
          </div>
        </div>
        
        <!-- Security Events -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <div class="rounded-full bg-red-100 p-3 mr-4">
                <i class="fa-solid fa-shield-halved text-red-600 text-lg"></i>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-600">Security Events</p>
                <p id="security-events-count" class="text-2xl font-bold text-gray-900" data-count="<?= $securityEvents ?>"><?= number_format($securityEvents) ?></p>
              </div>
            </div>
            <?php if ($securityEvents > 0): ?>
              <div class="text-xs text-orange-600 bg-orange-50 px-2 py-1 rounded-full">
                Monitor
              </div>
            <?php else: ?>
              <div class="text-xs text-green-600 bg-green-50 px-2 py-1 rounded-full">
                Secure
              </div>
            <?php endif; ?>
          </div>
          <div class="mt-4 text-xs text-gray-500">
            <i class="fa-solid fa-exclamation-triangle mr-1"></i>
            Failed logins & admin actions
          </div>
        </div>
        
        <!-- Admin Activities (24h) -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <div class="rounded-full bg-purple-100 p-3 mr-4">
                <i class="fa-solid fa-user-shield text-purple-600 text-lg"></i>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-600">Admin Activities</p>
                <p id="admin-activities-count" class="text-2xl font-bold text-gray-900" data-count="<?= $adminActivities ?>"><?= number_format($adminActivities) ?></p>
              </div>
            </div>
            <?php
            // Check if showing 24h or all-time data
            $is24hData = $pdo->query("SELECT COUNT(*) FROM logs WHERE role IN ('admin', 'super_admin', 'superadmin', 'superAdmin') AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn() > 0;
            ?>
            <div class="text-xs <?= $is24hData ? 'text-blue-600 bg-blue-50' : 'text-gray-600 bg-gray-100' ?> px-2 py-1 rounded-full">
              <?= $is24hData ? '24 Hours' : 'All Time' ?>
            </div>
          </div>
          <div class="mt-4 text-xs text-gray-500">
            <i class="fa-solid fa-clock mr-1"></i>
            Admin & Super Admin actions
          </div>
        </div>
        
        <!-- System Health -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <div class="rounded-full <?= $systemHealth >= 95 ? 'bg-green-100' : ($systemHealth >= 85 ? 'bg-yellow-100' : 'bg-red-100') ?> p-3 mr-4">
                <i class="fa-solid fa-heartbeat <?= $systemHealth >= 95 ? 'text-green-600' : ($systemHealth >= 85 ? 'text-yellow-600' : 'text-red-600') ?> text-lg"></i>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-600">System Health</p>
                <p id="system-health-value" class="text-2xl font-bold <?= $systemHealth >= 95 ? 'text-green-600' : ($systemHealth >= 85 ? 'text-yellow-600' : 'text-red-600') ?>" data-value="<?= $systemHealth ?>"><?= $systemHealth ?>%</p>
              </div>
            </div>
            <div class="text-xs <?= $systemHealth >= 95 ? 'text-green-600 bg-green-50' : ($systemHealth >= 85 ? 'text-yellow-600 bg-yellow-50' : 'text-red-600 bg-red-50') ?> px-2 py-1 rounded-full">
              <?= $systemHealth >= 95 ? 'Excellent' : ($systemHealth >= 85 ? 'Good' : 'Needs Attention') ?>
            </div>
          </div>
          <div class="mt-4 text-xs text-gray-500">
            <i class="fa-solid fa-chart-pie mr-1"></i>
            Success rate (24h)
          </div>
        </div>
      </div>

      <!-- Additional Metrics Row -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
        <!-- Active Users (7 days) -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Active Users</p>
              <p id="active-users-count" class="text-xl font-bold text-gray-900" data-count="<?= $activeUsers ?>"><?= number_format($activeUsers) ?></p>
            </div>
            <div class="rounded-lg bg-indigo-100 p-2">
              <i class="fa-solid fa-users text-indigo-600"></i>
            </div>
          </div>
        </div>

        <!-- Webhook Events -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Webhooks (24h)</p>
              <p id="webhook-events-count" class="text-xl font-bold text-gray-900" data-count="<?= $webhookEvents ?>"><?= number_format($webhookEvents) ?></p>
            </div>
            <div class="rounded-lg bg-cyan-100 p-2">
              <i class="fa-solid fa-webhook text-cyan-600"></i>
            </div>
          </div>
        </div>

        <!-- Credential Operations -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Credentials (24h)</p>
              <p id="credential-ops-count" class="text-xl font-bold text-gray-900" data-count="<?= $credentialOps ?>"><?= number_format($credentialOps) ?></p>
            </div>
            <div class="rounded-lg bg-amber-100 p-2">
              <i class="fa-solid fa-key text-amber-600"></i>
            </div>
          </div>
        </div>

        <!-- Failed Actions Today -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Failed (24h)</p>
              <p id="failed-actions-count" class="text-xl font-bold <?= $failedActions > 10 ? 'text-red-600' : 'text-gray-900' ?>" data-count="<?= $failedActions ?>"><?= number_format($failedActions) ?></p>
            </div>
            <div class="rounded-lg <?= $failedActions > 10 ? 'bg-red-100' : 'bg-gray-100' ?> p-2">
              <i class="fa-solid fa-exclamation-circle <?= $failedActions > 10 ? 'text-red-600' : 'text-gray-600' ?>"></i>
            </div>
          </div>
        </div>

        <!-- Successful Actions Today -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Success (24h)</p>
              <p id="successful-actions-count" class="text-xl font-bold text-green-600" data-count="<?= $successfulActions ?>"><?= number_format($successfulActions) ?></p>
            </div>
            <div class="rounded-lg bg-green-100 p-2">
              <i class="fa-solid fa-check-circle text-green-600"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Logs Table -->
    <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-200">
      <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-list text-blue-600"></i>
            Activity Logs
          </h2>
          <div class="text-sm text-gray-500">
            Showing <span id="showing-start"><?= $pagination['start_item'] ?></span>-<span id="showing-end"><?= $pagination['end_item'] ?></span> of <span id="showing-total"><?= $pagination['total_items'] ?></span> entries
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead class="bg-gray-50">
            <tr class="border-b border-gray-200">
              <th class="py-4 px-6 text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
              <th class="py-4 px-6 text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
              <th class="py-4 px-6 text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
              <th class="py-4 px-6 text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
              <th class="py-4 px-6 text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
              <th class="py-4 px-6 text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200" id="logs-table-body">
            <?php if (empty($logs)): ?>
              <tr id="no-logs-row">
                <td colspan="6" class="px-6 py-12 text-center">
                  <div class="flex flex-col items-center justify-center text-gray-400">
                    <i class="fa-solid fa-clipboard-list text-5xl mb-4"></i>
                    <p class="text-lg font-medium">No logs found</p>
                    <p class="mt-1">When activities occur, they will appear here</p>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($logs as $l): ?>
                <?php
                  // Determine colors based on role and action type
                  $roleColor = [
                    'superadmin' => ['bg-red-100 text-red-800', 'fa-crown'],
                    'admin' => ['bg-purple-100 text-purple-800', 'fa-user-shield'],
                    'user' => ['bg-green-100 text-green-800', 'fa-user'],
                    'guest' => ['bg-gray-100 text-gray-800', 'fa-user']
                  ][$l['role']] ?? ['bg-gray-100 text-gray-800', 'fa-user'];

                  // Determine action icon based on action type
                  $actionIcon = [
                    'login' => 'fa-sign-in-alt',
                    'logout' => 'fa-sign-out-alt',
                    'create' => 'fa-plus-circle',
                    'update' => 'fa-edit',
                    'delete' => 'fa-trash',
                    'view' => 'fa-eye',
                    'download' => 'fa-download',
                    'upload' => 'fa-upload'
                  ][strtolower($l['action'])] ?? 'fa-clipboard-list';

                  $actionColor = [
                    'login' => 'text-green-500 bg-green-50',
                    'logout' => 'text-blue-500 bg-blue-50',
                    'create' => 'text-emerald-500 bg-emerald-50',
                    'update' => 'text-yellow-500 bg-yellow-50',
                    'delete' => 'text-red-500 bg-red-50',
                    'view' => 'text-purple-500 bg-purple-50',
                    'download' => 'text-indigo-500 bg-indigo-50',
                    'upload' => 'text-cyan-500 bg-cyan-50'
                  ][strtolower($l['action'])] ?? 'text-gray-500 bg-gray-50';
                ?>
                <tr class="hover:bg-gray-50 transition-colors" data-log-id="<?= $l['id'] ?>">
                  <td class="py-4 px-6">
                    <div class="flex items-center gap-3">
                      <div class="w-10 h-10 rounded-lg <?= $actionColor ?> flex items-center justify-center">
                        <i class="fa-solid <?= $actionIcon ?> text-sm"></i>
                      </div>
                      <div>
                        <div class="font-medium text-gray-900"><?= htmlspecialchars($l['action']) ?></div>
                        <div class="text-xs text-gray-500 mt-1">
                          <?= $l['user_name'] ? 'Performed by user' : 'System action' ?>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="py-4 px-6">
                    <?php if ($l['user_name']): ?>
                      <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                          <i class="fa-solid fa-user text-blue-600 text-xs"></i>
                        </div>
                        <div>
                          <div class="font-medium text-gray-900"><?= htmlspecialchars($l['user_name']) ?></div>
                          <div class="text-xs text-gray-500"><?= htmlspecialchars($l['user_email']) ?></div>
                        </div>
                      </div>
                    <?php else: ?>
                      <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center">
                          <i class="fa-solid fa-robot text-gray-600 text-xs"></i>
                        </div>
                        <div>
                          <div class="font-medium text-gray-900">System</div>
                          <div class="text-xs text-gray-500">Automated action</div>
                        </div>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="py-4 px-6">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?= $roleColor[0] ?>">
                      <i class="fa-solid <?= $roleColor[1] ?> mr-1.5"></i>
                      <?= htmlspecialchars($l['role'] ?? 'system') ?>
                    </span>
                  </td>
                  <td class="py-4 px-6">
                    <div class="max-w-xs">
                      <div class="text-sm text-gray-900 font-medium truncate toggle-details-text" title="<?= htmlspecialchars($l['details'] ?? 'No details') ?>">
                        <?= htmlspecialchars($l['details'] ?? '-') ?>
                      </div>
                      <?php if ($l['details'] && strlen($l['details']) > 50): ?>
                        <button class="text-xs text-blue-600 hover:text-blue-800 mt-1 toggle-details-btn">
                          Show more
                        </button>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="py-4 px-6">
                    <?php if ($l['ip_address']): ?>
                      <div class="font-mono text-xs bg-gray-100 px-2 py-1 rounded text-gray-700 inline-block">
                        <?= htmlspecialchars($l['ip_address']) ?>
                      </div>
                    <?php else: ?>
                      <span class="text-gray-400 text-sm">-</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-4 px-6">
                    <div class="text-sm text-gray-900">
                      <?= date('M j, Y', strtotime($l['created_at'])) ?>
                    </div>
                    <div class="text-xs text-gray-500">
                      <?= date('g:i A', strtotime($l['created_at'])) ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if (!empty($logs)): ?>
      <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
        <?php 
        $extraParams = ['p' => 'dashboard', 'page' => 'superAdminAudit'];
        if ($search) $extraParams['search'] = $search;
        if ($role) $extraParams['role'] = $role;
        if ($action) $extraParams['action'] = $action;
        
        echo renderPagination($pagination, url('index.php'), $extraParams, 'pg'); 
        ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<script>
// Define BASE constant for JavaScript
const BASE = '<?= BASE ?>';

// Global toggleDetails function
function toggleDetails(button) {
  const detailsCell = button.closest('td');
  const detailsText = detailsCell.querySelector('.toggle-details-text');
  
  if (button.textContent === 'Show more') {
    detailsText.classList.remove('truncate');
    button.textContent = 'Show less';
  } else {
    detailsText.classList.add('truncate');
    button.textContent = 'Show more';
  }
}

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
  console.log('🚀 Audit Logs Page Loaded');

  // Event delegation for "Show more/less" buttons
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('toggle-details-btn')) {
      toggleDetails(e.target);
    }
  });

  // Function to create log row HTML
  function createLogRow(log) {
    // Determine colors based on role
    const roleColors = {
      'superadmin': ['bg-red-100 text-red-800', 'fa-crown'],
      'admin': ['bg-purple-100 text-purple-800', 'fa-user-shield'],
      'user': ['bg-green-100 text-green-800', 'fa-user'],
      'guest': ['bg-gray-100 text-gray-800', 'fa-user']
    };
    const roleColor = roleColors[log.role] || ['bg-gray-100 text-gray-800', 'fa-user'];
    
    // Determine action icon and color
    const actionIcons = {
      'login': 'fa-sign-in-alt',
      'logout': 'fa-sign-out-alt',
      'create': 'fa-plus-circle',
      'update': 'fa-edit',
      'delete': 'fa-trash',
      'view': 'fa-eye',
      'download': 'fa-download',
      'upload': 'fa-upload'
    };
    const actionColors = {
      'login': 'text-green-500 bg-green-50',
      'logout': 'text-blue-500 bg-blue-50',
      'create': 'text-emerald-500 bg-emerald-50',
      'update': 'text-yellow-500 bg-yellow-50',
      'delete': 'text-red-500 bg-red-50',
      'view': 'text-purple-500 bg-purple-50',
      'download': 'text-indigo-500 bg-indigo-50',
      'upload': 'text-cyan-500 bg-cyan-50'
    };
    
    const actionLower = (log.action || '').toLowerCase();
    const actionIcon = actionIcons[actionLower] || 'fa-clipboard-list';
    const actionColor = actionColors[actionLower] || 'text-gray-500 bg-gray-50';
    
    const createdDate = new Date(log.created_at);
    const dateStr = createdDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    const timeStr = createdDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    
    const details = log.details || '-';
    const showMoreBtn = details.length > 50 ? 
      '<button class="text-xs text-blue-600 hover:text-blue-800 mt-1 toggle-details-btn">Show more</button>' : '';
    
    return `
      <tr class="hover:bg-gray-50 transition-colors bg-blue-50 animate-fade-in" data-log-id="${log.id}">
        <td class="py-4 px-6">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg ${actionColor} flex items-center justify-center">
              <i class="fa-solid ${actionIcon} text-sm"></i>
            </div>
            <div>
              <div class="font-medium text-gray-900">${log.action || 'Unknown'}</div>
              <div class="text-xs text-gray-500 mt-1">
                ${log.user_name ? 'Performed by user' : 'System action'}
              </div>
            </div>
          </div>
        </td>
        <td class="py-4 px-6">
          ${log.user_name ? `
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                <i class="fa-solid fa-user text-blue-600 text-xs"></i>
              </div>
              <div>
                <div class="font-medium text-gray-900">${log.user_name}</div>
                <div class="text-xs text-gray-500">${log.user_email || ''}</div>
              </div>
            </div>
          ` : `
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center">
                <i class="fa-solid fa-robot text-gray-600 text-xs"></i>
              </div>
              <div>
                <div class="font-medium text-gray-900">System</div>
                <div class="text-xs text-gray-500">Automated action</div>
              </div>
            </div>
          `}
        </td>
        <td class="py-4 px-6">
          <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${roleColor[0]}">
            <i class="fa-solid ${roleColor[1]} mr-1.5"></i>
            ${log.role || 'system'}
          </span>
        </td>
        <td class="py-4 px-6">
          <div class="max-w-xs">
            <div class="text-sm text-gray-900 font-medium ${details.length > 50 ? 'truncate' : ''} toggle-details-text" title="${details}">
              ${details}
            </div>
            ${showMoreBtn}
          </div>
        </td>
        <td class="py-4 px-6">
          ${log.ip_address ? `
            <div class="font-mono text-xs bg-gray-100 px-2 py-1 rounded text-gray-700 inline-block">
              ${log.ip_address}
            </div>
          ` : '<span class="text-gray-400 text-sm">-</span>'}
        </td>
        <td class="py-4 px-6">
          <div class="text-sm text-gray-900">${dateStr}</div>
          <div class="text-xs text-gray-500">${timeStr}</div>
        </td>
      </tr>
    `;
  }

  // Function to add new logs to table
  function addNewLogs(newLogs) {
    console.log('📝 Adding', newLogs.length, 'new logs to table');
    
    const tbody = document.getElementById('logs-table-body');
    if (!tbody) {
      console.warn('⚠️ Logs table body not found');
      return;
    }
    
    // Remove "no logs" message if present
    const noLogsRow = document.getElementById('no-logs-row');
    if (noLogsRow) {
      noLogsRow.remove();
    }
    
    // Add new logs to top of table
    newLogs.forEach(log => {
      const rowHTML = createLogRow(log);
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
    
    // Update total count
    updateTotalCount(newLogs.length);
  }

  // Function to update total count
  function updateTotalCount(increment = 1) {
    const totalCountEl = document.getElementById('total-logs-count');
    if (totalCountEl) {
      const currentCount = parseInt(totalCountEl.dataset.count) || 0;
      const newCount = currentCount + increment;
      totalCountEl.dataset.count = newCount;
      totalCountEl.textContent = newCount.toLocaleString();
      
      // Add pulse animation
      totalCountEl.classList.add('animate-pulse');
      setTimeout(() => totalCountEl.classList.remove('animate-pulse'), 1000);
    }
    
    // Update showing total
    const showingTotalEl = document.getElementById('showing-total');
    if (showingTotalEl) {
      const currentTotal = parseInt(showingTotalEl.textContent.replace(/,/g, '')) || 0;
      showingTotalEl.textContent = (currentTotal + increment).toLocaleString();
    }
  }

  // Function to update specific stat card
  function updateStatCard(cardId, increment) {
    const cardEl = document.getElementById(cardId);
    if (cardEl) {
      const currentCount = parseInt(cardEl.dataset.count) || 0;
      const newCount = currentCount + increment;
      cardEl.dataset.count = newCount;
      cardEl.textContent = newCount.toLocaleString();
      
      // Add pulse animation
      cardEl.classList.add('animate-pulse');
      setTimeout(() => cardEl.classList.remove('animate-pulse'), 1000);
    }
  }

  // Function to update audit stats
  function updateAuditStats(newLogs) {
    console.log('📊 Updating audit stats with', newLogs.length, 'new logs');
    
    let securityEvents = 0;
    let adminActivities = 0;
    let webhookEvents = 0;
    let credentialOps = 0;
    let failedActions = 0;
    let successfulActions = 0;
    
    newLogs.forEach(log => {
      const action = log.action?.toLowerCase() || '';
      const role = log.role?.toLowerCase() || '';
      
      // Security events
      if (action.includes('failed') || action.includes('login') || action.includes('error') || 
          action.includes('unauthorized') || role.includes('admin') || role.includes('super')) {
        securityEvents++;
      }
      
      // Admin activities
      if (role.includes('admin') || role.includes('super')) {
        adminActivities++;
      }
      
      // Webhook events
      if (action.includes('webhook')) {
        webhookEvents++;
      }
      
      // Credential operations
      if (action.includes('credential')) {
        credentialOps++;
      }
      
      // Failed actions
      if (action.includes('failed') || action.includes('error')) {
        failedActions++;
      } else {
        successfulActions++;
      }
    });
    
    // Update individual stat cards
    if (securityEvents > 0) updateStatCard('security-events-count', securityEvents);
    if (adminActivities > 0) updateStatCard('admin-activities-count', adminActivities);
    if (webhookEvents > 0) updateStatCard('webhook-events-count', webhookEvents);
    if (credentialOps > 0) updateStatCard('credential-ops-count', credentialOps);
    if (failedActions > 0) updateStatCard('failed-actions-count', failedActions);
    if (successfulActions > 0) updateStatCard('successful-actions-count', successfulActions);
    
    // Update system health
    updateSystemHealth(successfulActions, failedActions);
  }

  // Function to update system health
  function updateSystemHealth(successfulIncrement = 0, failedIncrement = 0) {
    const healthEl = document.getElementById('system-health-value');
    if (!healthEl) return;
    
    const successfulEl = document.getElementById('successful-actions-count');
    const failedEl = document.getElementById('failed-actions-count');
    
    if (successfulEl && failedEl) {
      const successful = parseInt(successfulEl.dataset.count) || 0;
      const failed = parseInt(failedEl.dataset.count) || 0;
      
      const total = successful + failed;
      const health = total > 0 ? Math.round((successful / total) * 100) : 100;
      
      healthEl.dataset.value = health;
      healthEl.textContent = health + '%';
      
      // Update colors based on health
      if (health >= 95) {
        healthEl.className = 'text-2xl font-bold text-green-600';
      } else if (health >= 85) {
        healthEl.className = 'text-2xl font-bold text-yellow-600';
      } else {
        healthEl.className = 'text-2xl font-bold text-red-600';
      }
    }
  }

  // Load polling.js and start polling
  console.log('📦 Loading polling.js for audit logs...');
  
  // Check if polling.js is already loaded
  if (typeof startPolling === 'undefined') {
    const script = document.createElement('script');
    script.src = BASE + 'js/polling.js';
    
    script.onload = function() {
      console.log('✅ polling.js loaded for audit page');
      initializePolling();
    };
    
    script.onerror = function() {
      console.error('❌ Failed to load polling.js');
      // Retry after 5 seconds
      setTimeout(() => {
        console.log('🔄 Retrying to load polling.js...');
        document.head.appendChild(script.cloneNode(true));
      }, 5000);
    };
    
    document.head.appendChild(script);
  } else {
    console.log('✅ polling.js already loaded');
    initializePolling();
  }

  // Initialize polling
  function initializePolling() {
    if (typeof startPolling === 'undefined') {
      console.error('❌ startPolling function not available');
      return;
    }
    
    console.log('✅ Starting polling for audit logs');
    
    try {
      startPolling({
        logs: (newLogs) => {
          console.log('📋 New logs detected:', newLogs.length);
          if (newLogs.length > 0) {
            // Add new logs to table
            addNewLogs(newLogs);
            
            // Update stats cards
            updateAuditStats(newLogs);
            
            // Show notification
            showNotification(`${newLogs.length} new audit log(s) detected!`, 'success');
          }
        },
        
        // Monitor other data types that affect audit stats
        listings: (newListings) => {
          if (newListings.length > 0) {
            console.log('📋 New listings detected');
            showNotification(`${newListings.length} new listing(s) created`, 'info');
          }
        },
        
        offers: (newOffers) => {
          if (newOffers.length > 0) {
            console.log('💰 New offers detected');
            showNotification(`${newOffers.length} new offer(s) created`, 'info');
          }
        },
        
        orders: (newOrders) => {
          if (newOrders.length > 0) {
            console.log('📦 New orders detected');
            showNotification(`${newOrders.length} new order(s) created`, 'info');
          }
        }
      });
      
      console.log('✅ Audit logs polling started successfully');
      
    } catch (error) {
      console.error('❌ Error starting polling:', error);
    }
  }

  // Simple notification function
  function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium ${
      type === 'success' ? 'bg-green-500' : 
      type === 'error' ? 'bg-red-500' : 
      'bg-blue-500'
    } animate-fade-in`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
      notification.remove();
    }, 3000);
  }

  // Debug function for manual testing
  window.testAuditPolling = function() {
    console.log('🧪 Testing audit polling manually...');
    
    const testLogs = [
      {
        id: Date.now(),
        action: 'Test Log Entry',
        details: 'This is a very long test message that should trigger the show more button functionality to test if the toggleDetails function works properly with dynamically added content',
        role: 'admin',
        user_name: 'Test User',
        user_email: 'test@example.com',
        created_at: new Date().toISOString(),
        ip_address: '127.0.0.1'
      }
    ];
    
    updateAuditStats(testLogs);
    addNewLogs(testLogs);
    showNotification('Test completed - check stats and table', 'success');
    
    console.log('✅ Test completed - check stats cards and table');
  };

  console.log('🎯 Audit logs page initialized successfully');

}); // End of DOMContentLoaded
</script>

<style>
/* Custom scrollbar for table */
.overflow-x-auto::-webkit-scrollbar {
  height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

/* Smooth transitions */
tr {
  transition: all 0.2s ease-in-out;
}

/* Fade in animation for new logs */
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
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.7;
  }
}

.animate-pulse {
  animation: pulse 1s ease-in-out;
}

/* Highlight new rows */
.bg-blue-50 {
  background-color: #eff6ff;
  border-left: 4px solid #3b82f6;
}
</style>