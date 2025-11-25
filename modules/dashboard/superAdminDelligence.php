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
        // Export due diligence records
        $exportSql = "
            SELECT dd.id, dd.listing_id, dd.verification_score, dd.status, 
                   dd.risk_level, dd.documents, dd.created_at,
                   l.name as listing_name,
                   u.name as seller_name, u.email as seller_email
            FROM due_diligence dd
            LEFT JOIN listings l ON dd.listing_id = l.id
            LEFT JOIN users u ON l.user_id = u.id
            ORDER BY dd.created_at DESC
        ";
        
        $exportStmt = $pdo->query($exportSql);
        $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
        
        handleExportRequest($exportData, 'Due Diligence Report');
    } catch (Exception $e) {
        // If table doesn't exist, return empty
        handleExportRequest([], 'Due Diligence Report');
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
$risk_level = $_GET['risk_level'] ?? '';

// Handle due diligence actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'update_status':
                    $ddId = $_POST['dd_id'] ?? 0;
                    $newStatus = $_POST['status'] ?? '';
                    $score = $_POST['verification_score'] ?? 0;
                    $notes = $_POST['reviewer_notes'] ?? '';
                    
                    $updateData = [
                        'status' => $newStatus,
                        'verification_score' => $score,
                        'reviewer_notes' => $notes,
                        'reviewer_id' => $_SESSION['user_id']
                    ];
                    
                    if ($newStatus === 'completed') {
                        $updateData['completed_at'] = date('Y-m-d H:i:s');
                    }
                    
                    $stmt = $pdo->prepare("UPDATE due_diligence SET status = ?, verification_score = ?, reviewer_notes = ?, reviewer_id = ?, completed_at = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$newStatus, $score, $notes, $_SESSION['user_id'], $updateData['completed_at'] ?? null, $ddId]);
                    
                    $_SESSION['success_message'] = "Due diligence status updated successfully!";
                    break;
                    
                case 'update_risk':
                    $ddId = $_POST['dd_id'] ?? 0;
                    $riskLevel = $_POST['risk_level'] ?? '';
                    
                    $stmt = $pdo->prepare("UPDATE due_diligence SET risk_level = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$riskLevel, $ddId]);
                    
                    $_SESSION['success_message'] = "Risk level updated!";
                    break;
                    
                case 'verify_checklist':
                    $ddId = $_POST['dd_id'] ?? 0;
                    $businessReg = isset($_POST['business_registration']) ? 1 : 0;
                    $financial = isset($_POST['financial_records']) ? 1 : 0;
                    $traffic = isset($_POST['traffic_analytics']) ? 1 : 0;
                    $legal = isset($_POST['legal_compliance']) ? 1 : 0;
                    
                    // Calculate verification score based on checklist
                    $score = ($businessReg + $financial + $traffic + $legal) * 25;
                    
                    $stmt = $pdo->prepare("UPDATE due_diligence SET business_registration_verified = ?, financial_records_verified = ?, traffic_analytics_verified = ?, legal_compliance_verified = ?, verification_score = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$businessReg, $financial, $traffic, $legal, $score, $ddId]);
                    
                    $_SESSION['success_message'] = "Verification checklist updated!";
                    break;
            }
            
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
        }
    }
}

// Get real due diligence data from database only
$diligenceRecords = [];
$hasRealData = false;

try {
    // Check if due_diligence table exists and has data
    $checkStmt = $pdo->query("SELECT COUNT(*) FROM due_diligence");
    $totalRecords = $checkStmt->fetchColumn();
    
    if ($totalRecords > 0) {
        $hasRealData = true;
        
        // Fetch real data from due_diligence table
        $stmt = $pdo->query("
            SELECT dd.*, l.name as listing, u.name as seller 
            FROM due_diligence dd 
            LEFT JOIN listings l ON dd.listing_id = l.id 
            LEFT JOIN users u ON l.user_id = u.id 
            ORDER BY dd.created_at DESC
        ");
        $diligenceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Table doesn't exist or error occurred - no data available
    $hasRealData = false;
    $diligenceRecords = [];
}

// Apply filters only if we have real data
$filteredDiligence = $diligenceRecords;

// Apply filters only if we have real data
if ($hasRealData && !empty($filteredDiligence)) {
    if ($search) {
        $filteredDiligence = array_filter($filteredDiligence, function($item) use ($search) {
            return stripos($item['seller'], $search) !== false || 
                   stripos($item['listing'], $search) !== false ||
                   stripos($item['listing_id'], $search) !== false;
        });
    }

    if ($status) {
        $filteredDiligence = array_filter($filteredDiligence, function($item) use ($status) {
            return $item['status'] === $status;
        });
    }

    if ($risk_level) {
        $filteredDiligence = array_filter($filteredDiligence, function($item) use ($risk_level) {
            return $item['risk_level'] === $risk_level;
        });
    }
}

// Simulate pagination
$totalItems = count($filteredDiligence);
$totalPages = ceil($totalItems / $perPage);
$offset = ($page - 1) * $perPage;
$diligenceData = array_slice($filteredDiligence, $offset, $perPage);

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
  <title>Due Diligence Dashboard</title>
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
    .progress-bar {
      height: 6px;
      border-radius: 3px;
      overflow: hidden;
      background-color: #e5e7eb;
    }
    .progress-fill {
      height: 100%;
      border-radius: 3px;
      transition: width 0.3s ease;
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
        <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Due Diligence</h1>
        <p class="text-gray-500 mt-1">Review and verify business listings through comprehensive due diligence</p>
      </div>
      <div class="flex items-center gap-4">
        <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1.5 rounded-full">
          <i class="fa fa-shield-alt mr-1.5"></i> Compliance Review
        </span>
        <?php require_once __DIR__ . '/../../includes/export_helper.php'; echo getExportButton('due_diligence'); ?>
      </div>
    </div>

    <!-- Search and Filter - Only show if we have data -->
    <?php if ($hasRealData): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <form method="GET" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="p" value="dashboard">
        <input type="hidden" name="page" value="superAdminDelligence">
        
        <div class="flex-1 min-w-[200px]">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fa fa-search mr-1"></i>Search Due Diligence
          </label>
          <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                 placeholder="Search by listing ID, seller, or listing name..." 
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
            <option value="flagged" <?= $status === 'flagged' ? 'selected' : '' ?>>Flagged</option>
          </select>
        </div>
        
        <div class="min-w-[150px]">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fa fa-shield-alt mr-1"></i>Risk Level
          </label>
          <select name="risk_level" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">All Risk Levels</option>
            <option value="low" <?= $risk_level === 'low' ? 'selected' : '' ?>>Low</option>
            <option value="medium" <?= $risk_level === 'medium' ? 'selected' : '' ?>>Medium</option>
            <option value="high" <?= $risk_level === 'high' ? 'selected' : '' ?>>High</option>
          </select>
        </div>
        
        <div class="flex gap-2">
          <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
            <i class="fa fa-search mr-2"></i>Filter
          </button>
          <a href="?p=dashboard&page=superAdminDelligence" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors flex items-center">
            <i class="fa fa-times mr-2"></i>Clear
          </a>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <?php
    // Calculate stats from real data only
    if ($hasRealData && !empty($diligenceRecords)) {
        $pendingCount = count(array_filter($diligenceRecords, function($item) { return $item['status'] === 'pending'; }));
        $completedCount = count(array_filter($diligenceRecords, function($item) { return $item['status'] === 'completed'; }));
        $flaggedCount = count(array_filter($diligenceRecords, function($item) { return $item['status'] === 'flagged'; }));
        $avgScore = round(array_sum(array_column($diligenceRecords, 'verification_score')) / count($diligenceRecords));
    } else {
        // No real data - show zeros
        $pendingCount = 0;
        $completedCount = 0;
        $flaggedCount = 0;
        $avgScore = 0;
    }
    
    // Show empty state when no real data from database
    $showEmptyState = !$hasRealData;
    ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
      <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
        <div class="flex justify-between items-start">
          <div>
            <p class="text-gray-600 text-sm font-medium">Pending Review</p>
            <h2 class="text-2xl md:text-3xl font-bold text-yellow-500 mt-1"><?= $pendingCount ?></h2>
            <?php if ($pendingCount > 0): ?>
              <p class="text-yellow-500 text-sm font-semibold mt-2 flex items-center">
                <i class="fa fa-clock mr-1"></i> Requires attention
              </p>
            <?php else: ?>
              <p class="text-gray-400 text-sm mt-2 flex items-center">
                <i class="fa fa-check mr-1"></i> No pending reviews
              </p>
            <?php endif; ?>
          </div>
          <div class="bg-yellow-100 text-yellow-500 p-3 rounded-lg">
            <i class="fa-solid fa-clock text-xl"></i>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
        <div class="flex justify-between items-start">
          <div>
            <p class="text-gray-600 text-sm font-medium">Completed</p>
            <h2 class="text-2xl md:text-3xl font-bold text-green-500 mt-1"><?= $completedCount ?></h2>
            <?php if ($completedCount > 0): ?>
              <p class="text-green-500 text-sm font-semibold mt-2 flex items-center">
                <i class="fa fa-check-circle mr-1"></i> Verified & approved
              </p>
            <?php else: ?>
              <p class="text-gray-400 text-sm mt-2 flex items-center">
                <i class="fa fa-hourglass mr-1"></i> Awaiting completions
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
            <p class="text-gray-600 text-sm font-medium">Flagged</p>
            <h2 class="text-2xl md:text-3xl font-bold text-red-500 mt-1"><?= $flaggedCount ?></h2>
            <?php if ($flaggedCount > 0): ?>
              <p class="text-red-500 text-sm font-semibold mt-2 flex items-center">
                <i class="fa fa-flag mr-1"></i> Failed verification
              </p>
            <?php else: ?>
              <p class="text-gray-400 text-sm mt-2 flex items-center">
                <i class="fa fa-shield-check mr-1"></i> No issues found
              </p>
            <?php endif; ?>
          </div>
          <div class="bg-red-100 text-red-500 p-3 rounded-lg">
            <i class="fa-solid fa-circle-xmark text-xl"></i>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
        <div class="flex justify-between items-start">
          <div>
            <p class="text-gray-600 text-sm font-medium">Avg Score</p>
            <h2 class="text-2xl md:text-3xl font-bold text-blue-500 mt-1"><?= $avgScore ?>%</h2>
            <?php if ($avgScore > 0): ?>
              <p class="text-blue-500 text-sm font-semibold mt-2 flex items-center">
                <i class="fa fa-chart-line mr-1"></i> Verification score
              </p>
            <?php else: ?>
              <p class="text-gray-400 text-sm mt-2 flex items-center">
                <i class="fa fa-calculator mr-1"></i> No scores yet
              </p>
            <?php endif; ?>
          </div>
          <div class="bg-blue-100 text-blue-500 p-3 rounded-lg">
            <i class="fa-solid fa-percent text-xl"></i>
          </div>
        </div>
      </div>
    </div>

    <?php if ($showEmptyState): ?>
    <!-- Empty State Banner -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-8 mb-8">
      <div class="text-center">
        <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
          <i class="fa fa-shield-alt text-blue-600 text-2xl"></i>
        </div>
        <h3 class="text-xl font-semibold text-gray-900 mb-3">No Due Diligence Records Found</h3>
        <p class="text-gray-600 mb-6 max-w-md mx-auto">
          Due diligence records will appear here when listings are submitted for verification and compliance review.
        </p>
        
        <div class="bg-blue-100 px-4 py-3 rounded-lg max-w-lg mx-auto">
          <p class="text-sm text-blue-800 font-medium">
            <i class="fa fa-info-circle mr-1"></i> 
            Due diligence system is ready and will automatically process listings as they are submitted
          </p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Due Diligence Table - Only show if we have data -->
    <?php if ($hasRealData): ?>
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
      <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-4">
        <div>
          <h2 class="text-lg md:text-xl font-semibold text-gray-900">Due Diligence Reviews</h2>
          <p class="text-sm text-gray-500 mt-1">Monitor and manage listing verification processes</p>
        </div>
        <div class="text-sm text-gray-500">
          Showing <?= $pagination['start_item'] ?>-<?= $pagination['end_item'] ?> of <?= $pagination['total_items'] ?> records
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead>
            <tr class="text-gray-600 border-b">
              <th class="py-3 px-4 font-medium">LISTING ID</th>
              <th class="py-3 px-4 font-medium">SELLER</th>
              <th class="py-3 px-4 font-medium">LISTING</th>
              <th class="py-3 px-4 font-medium">VERIFICATION SCORE</th>
              <th class="py-3 px-4 font-medium">STATUS</th>
              <th class="py-3 px-4 font-medium">RISK LEVEL</th>
              <th class="py-3 px-4 font-medium">ACTIONS</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-sm">
            <?php if (empty($diligenceData)): ?>
              <tr>
                <td colspan="7" class="px-6 py-16 text-center">
                  <div class="flex flex-col items-center justify-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                      <i class="fa fa-shield-alt text-gray-400 text-2xl"></i>
                    </div>
                    <?php if ($showEmptyState): ?>
                      <h3 class="text-lg font-medium text-gray-900 mb-2">No Due Diligence Records</h3>
                      <p class="text-gray-500 mb-4 max-w-sm">Due diligence records will appear here when listings are submitted for verification</p>
                      <div class="bg-blue-50 px-4 py-2 rounded-lg">
                        <p class="text-sm text-blue-700 font-medium">
                          <i class="fa fa-info-circle mr-1"></i> System ready for due diligence processing
                        </p>
                      </div>
                    <?php else: ?>
                      <h3 class="text-lg font-medium text-gray-900 mb-2">No Records Match Your Filters</h3>
                      <p class="text-gray-500 mb-4">Try adjusting your search criteria or clearing filters</p>
                      <a href="?p=dashboard&page=superAdminDelligence" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                        <i class="fa fa-times mr-2"></i> Clear Filters
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($diligenceData as $item): ?>
                <?php
                  // Status configuration
                  $statusConfig = [
                    'completed' => ['bg-green-100 text-green-700', 'fa-check-circle', 'Completed'],
                    'in_progress' => ['bg-blue-100 text-blue-700', 'fa-spinner', 'In Progress'],
                    'pending' => ['bg-yellow-100 text-yellow-700', 'fa-clock', 'Pending'],
                    'flagged' => ['bg-red-100 text-red-700', 'fa-flag', 'Flagged']
                  ];
                  $statusInfo = $statusConfig[$item['status']] ?? ['bg-gray-100 text-gray-700', 'fa-question', 'Unknown'];
                  
                  // Risk level configuration
                  $riskConfig = [
                    'low' => ['bg-green-100 text-green-700', 'Low Risk'],
                    'medium' => ['bg-yellow-100 text-yellow-700', 'Medium Risk'],
                    'high' => ['bg-red-100 text-red-700', 'High Risk']
                  ];
                  $riskInfo = $riskConfig[$item['risk_level']] ?? ['bg-gray-100 text-gray-700', 'Unknown'];
                  
                  // Progress bar color based on score
                  $progressColor = $item['verification_score'] >= 80 ? 'bg-green-500' : 
                                  ($item['verification_score'] >= 60 ? 'bg-yellow-500' : 'bg-red-500');
                ?>
                <tr class="hover-row transition-colors">
                  <td class="py-4 px-4">
                    <div class="font-medium text-gray-800"><?= htmlspecialchars($item['listing_id']) ?></div>
                    <div class="text-xs text-gray-500 mt-1">Created: <?= date('d/m/Y', strtotime($item['created_at'])) ?></div>
                  </td>
                  <td class="py-4 px-4">
                    <div class="font-medium text-gray-800"><?= htmlspecialchars($item['seller']) ?></div>
                    <div class="text-xs text-gray-500 mt-1"><?= $item['documents'] ?> documents</div>
                  </td>
                  <td class="py-4 px-4">
                    <div class="font-medium text-gray-800"><?= htmlspecialchars($item['listing']) ?></div>
                    <div class="text-xs text-gray-500 mt-1">Business listing</div>
                  </td>
                  <td class="py-4 px-4">
                    <div class="flex items-center gap-3">
                      <div class="flex-1">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                          <div class="<?= $progressColor ?> h-2 rounded-full" style="width: <?= $item['verification_score'] ?>%"></div>
                        </div>
                        <p class="text-sm text-gray-600 mt-1"><?= $item['verification_score'] ?>% Score</p>
                      </div>
                    </div>
                  </td>
                  <td class="py-4 px-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-full <?= $statusInfo[0] ?> text-xs font-medium">
                      <i class="fa <?= $statusInfo[1] ?> mr-1.5"></i> <?= $statusInfo[2] ?>
                    </span>
                  </td>
                  <td class="py-4 px-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-full <?= $riskInfo[0] ?> text-xs font-medium">
                      <?= $riskInfo[1] ?>
                    </span>
                  </td>
                  <td class="py-4 px-4">
                    <div class="flex items-center gap-3">
                      <button onclick="reviewDiligence(<?= $item['id'] ?>)" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                        <i class="fa fa-eye mr-1.5"></i> Review
                      </button>
                      <button onclick="updateChecklist(<?= $item['id'] ?>)" class="text-green-600 hover:text-green-800 text-sm font-medium flex items-center">
                        <i class="fa fa-check-square mr-1.5"></i> Checklist
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
        $extraParams = ['p' => 'dashboard', 'page' => 'superAdminDelligence'];
        if ($search) $extraParams['search'] = $search;
        if ($status) $extraParams['status'] = $status;
        if ($risk_level) $extraParams['risk_level'] = $risk_level;
        
        echo renderPagination($pagination, url('index.php'), $extraParams, 'pg'); 
        ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Summary Section - Only show if we have data -->
    <?php if ($hasRealData): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
      <!-- Due Diligence Checklist -->
      <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Due Diligence Checklist</h3>
        <div class="space-y-4">
          <div class="flex items-center justify-between p-3 rounded-lg border border-gray-200">
            <div class="flex items-center">
              <i class="fa fa-check-circle text-green-500 mr-3"></i>
              <span class="text-sm text-gray-700">Business Registration</span>
            </div>
            <span class="text-xs text-green-500 font-medium">Completed</span>
          </div>
          <div class="flex items-center justify-between p-3 rounded-lg border border-gray-200">
            <div class="flex items-center">
              <i class="fa fa-check-circle text-green-500 mr-3"></i>
              <span class="text-sm text-gray-700">Financial Records</span>
            </div>
            <span class="text-xs text-green-500 font-medium">Completed</span>
          </div>
          <div class="flex items-center justify-between p-3 rounded-lg border border-gray-200">
            <div class="flex items-center">
              <i class="fa fa-clock text-yellow-500 mr-3"></i>
              <span class="text-sm text-gray-700">Traffic Analytics</span>
            </div>
            <span class="text-xs text-yellow-500 font-medium">Pending</span>
          </div>
          <div class="flex items-center justify-between p-3 rounded-lg border border-gray-200">
            <div class="flex items-center">
              <i class="fa fa-times-circle text-red-500 mr-3"></i>
              <span class="text-sm text-gray-700">Legal Compliance</span>
            </div>
            <span class="text-xs text-red-500 font-medium">Failed</span>
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
              <span class="text-sm font-medium">Export Due Diligence Report</span>
            </div>
            <i class="fa fa-chevron-right text-gray-400"></i>
          </button>
          <button class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors flex items-center justify-between">
            <div class="flex items-center">
              <i class="fa fa-user-check text-green-500 mr-3"></i>
              <span class="text-sm font-medium">Assign Reviewer</span>
            </div>
            <i class="fa fa-chevron-right text-gray-400"></i>
          </button>
          <button class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors flex items-center justify-between">
            <div class="flex items-center">
              <i class="fa fa-cog text-purple-500 mr-3"></i>
              <span class="text-sm font-medium">Due Diligence Settings</span>
            </div>
            <i class="fa fa-chevron-right text-gray-400"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Review Modal -->
  <div id="reviewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
      <div class="p-6 border-b border-gray-200 flex justify-between items-center">
        <h3 class="text-xl font-bold text-gray-900">Due Diligence Review</h3>
        <button onclick="closeReviewModal()" class="text-gray-400 hover:text-gray-600">
          <i class="fa fa-times text-xl"></i>
        </button>
      </div>
      <form method="POST" class="p-6">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="dd_id" id="review_dd_id">
        
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
          <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" required>
            <option value="pending">Pending</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
            <option value="flagged">Flagged</option>
          </select>
        </div>
        
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Verification Score (%)</label>
          <input type="number" name="verification_score" min="0" max="100" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" required>
        </div>
        
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Reviewer Notes</label>
          <textarea name="reviewer_notes" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Add your review notes..."></textarea>
        </div>
        
        <div class="flex gap-3">
          <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium">
            <i class="fa fa-save mr-2"></i>Save Review
          </button>
          <button type="button" onclick="closeReviewModal()" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium">
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Checklist Modal -->
  <div id="checklistModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-lg w-full">
      <div class="p-6 border-b border-gray-200">
        <h3 class="text-xl font-bold text-gray-900">Verification Checklist</h3>
      </div>
      <form method="POST" class="p-6">
        <input type="hidden" name="action" value="verify_checklist">
        <input type="hidden" name="dd_id" id="checklist_dd_id">
        
        <div class="space-y-4 mb-6">
          <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" name="business_registration" class="w-5 h-5 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
            <span class="ml-3 text-sm font-medium text-gray-700">Business Registration Verified</span>
          </label>
          
          <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" name="financial_records" class="w-5 h-5 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
            <span class="ml-3 text-sm font-medium text-gray-700">Financial Records Verified</span>
          </label>
          
          <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" name="traffic_analytics" class="w-5 h-5 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
            <span class="ml-3 text-sm font-medium text-gray-700">Traffic Analytics Verified</span>
          </label>
          
          <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" name="legal_compliance" class="w-5 h-5 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
            <span class="ml-3 text-sm font-medium text-gray-700">Legal Compliance Verified</span>
          </label>
        </div>
        
        <div class="flex gap-3">
          <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium">
            <i class="fa fa-check mr-2"></i>Update Checklist
          </button>
          <button type="button" onclick="closeChecklistModal()" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium">
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
  function reviewDiligence(ddId) {
    document.getElementById('review_dd_id').value = ddId;
    document.getElementById('reviewModal').classList.remove('hidden');
  }
  
  function closeReviewModal() {
    document.getElementById('reviewModal').classList.add('hidden');
  }
  
  function updateChecklist(ddId) {
    document.getElementById('checklist_dd_id').value = ddId;
    document.getElementById('checklistModal').classList.remove('hidden');
  }
  
  function closeChecklistModal() {
    document.getElementById('checklistModal').classList.add('hidden');
  }
  
  document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 SuperAdmin Due Diligence polling initialization started');
    
    if (typeof startPolling !== 'undefined') {
      startPolling({
        listings: (newListings) => {
          console.log('📋 New listings for due diligence:', newListings.length);
          if (newListings.length > 0) {
            // Update total listings count
            const totalEl = document.querySelector('[data-stat="total-listings"]');
            if (totalEl) {
              const current = parseInt(totalEl.textContent) || 0;
              totalEl.textContent = current + newListings.length;
              totalEl.classList.add('animate-pulse');
              setTimeout(() => totalEl.classList.remove('animate-pulse'), 1000);
            }
            
            // Update pending review count (new listings need review)
            const pendingEl = document.querySelector('[data-stat="pending-review"]');
            if (pendingEl) {
              const current = parseInt(pendingEl.textContent) || 0;
              pendingEl.textContent = current + newListings.length;
              pendingEl.classList.add('animate-pulse');
              setTimeout(() => pendingEl.classList.remove('animate-pulse'), 1000);
            }
            
            // Add to listings table
            const tbody = document.querySelector('table tbody');
            if (tbody) {
              newListings.slice(0, 5).forEach(listing => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50 animate-fade-in';
                row.style.backgroundColor = '#dbeafe';
                
                row.innerHTML = `
                  <td class="py-4 px-6 font-medium">${listing.name || 'Unnamed Listing'}</td>
                  <td class="py-4 px-6">${listing.user_name || 'N/A'}</td>
                  <td class="py-4 px-6">$${parseFloat(listing.asking_price || 0).toLocaleString()}</td>
                  <td class="py-4 px-6">
                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                      Pending Review
                    </span>
                  </td>
                  <td class="py-4 px-6 text-sm text-gray-600">Just now</td>
                  <td class="py-4 px-6">
                    <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">Review</button>
                  </td>
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
                `${newListings.length} new listing(s) for due diligence!`,
                'info'
              );
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