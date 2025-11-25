<?php
// Check for export FIRST - before any output
if (isset($_GET['export'])) {
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../includes/export_helper.php';
    
    ob_start();
    require_login();
    ob_end_clean();
    
    $pdo = db();
    
    // Get all listings for export
    $exportSql = "
        SELECT l.id, l.name, l.type, l.status, l.asking_price, l.monthly_revenue,
               l.site_age, l.created_at,
               u.name as seller_name, u.email as seller_email
        FROM listings l
        JOIN users u ON u.id = l.user_id
        ORDER BY FIELD(l.status, 'pending','approved','rejected'), l.created_at DESC
    ";
    
    $exportStmt = $pdo->query($exportSql);
    $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
    
    handleExportRequest($exportData, 'Listing Verification Report');
    exit;
}

require_once __DIR__ . '/../../config.php';

require_once __DIR__ . '/../../includes/pagination_helper.php';

require_login();
$user = current_user();

$pdo = db();

// Check for success message from verification
$verificationSuccess = null;
if (isset($_SESSION['verification_success'])) {
    $verificationSuccess = $_SESSION['verification_success'];
    unset($_SESSION['verification_success']);
}


// Get pagination parameters
$page = getCurrentPage('pg');
$perPage = 15; // Items per page

// Setup search and filter conditions
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$whereClause = '';
$params = [];

if ($search) {
    $whereClause .= ' AND (l.name LIKE :search OR u.name LIKE :search OR u.email LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
if ($status) {
    $whereClause .= ' AND l.status = :status';
    $params[':status'] = $status;
}

$sql = "

    SELECT l.*, u.name AS seller_name, u.email,
           (SELECT file_path FROM listing_proofs WHERE listing_id = l.id LIMIT 1) AS proof_image
    FROM listings l
    JOIN users u ON u.id = l.user_id

    WHERE 1=1 $whereClause
    ORDER BY FIELD(l.status, 'pending','approved','rejected'), l.created_at DESC
";

$countSql = "
    SELECT COUNT(*) as total
    FROM listings l
    JOIN users u ON u.id = l.user_id
    WHERE 1=1 $whereClause
";

// Get paginated data
$result = getCustomPaginationData($pdo, $sql, $countSql, $params, $page, $perPage);
$listings = $result['data'];
$pagination = $result['pagination'];

?>

<!-- Modern Professional Header -->
<div class="bg-white border-b border-gray-200">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center py-6">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <div class="w-10 h-10 bg-gradient-to-r from-green-600 to-emerald-600 rounded-lg flex items-center justify-center">
            <i class="fas fa-clipboard-check text-white text-lg"></i>
          </div>
        </div>
        <div class="ml-4">
          <h1 class="text-2xl font-bold text-gray-900">Listing Verification</h1>
          <p class="text-sm text-gray-500">Review and verify business listings submitted by sellers</p>
        </div>
      </div>
      <div class="flex items-center space-x-4">
        <div class="hidden md:flex items-center space-x-6">
          <div class="flex items-center">
            <span class="text-xs font-medium text-green-600 bg-green-50 px-2 py-1 rounded-full">
              Admin Access
            </span>
          </div>
        </div>
        <?php require_once __DIR__ . '/../../includes/export_helper.php'; echo getExportButton('listing_verification'); ?>
      </div>
    </div>
  </div>
</div>

<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

  <?php if ($verificationSuccess): ?>
    <!-- Success Notification -->
    <div id="successNotification" class="mb-6 bg-<?= $verificationSuccess['status'] === 'approved' ? 'green' : 'red' ?>-50 border border-<?= $verificationSuccess['status'] === 'approved' ? 'green' : 'red' ?>-200 rounded-xl p-4 flex items-start gap-3 animate-fade-in">
      <div class="flex-shrink-0">
        <i class="fas fa-<?= $verificationSuccess['status'] === 'approved' ? 'check' : 'times' ?>-circle text-<?= $verificationSuccess['status'] === 'approved' ? 'green' : 'red' ?>-500 text-xl"></i>
      </div>
      <div class="flex-1">
        <h3 class="font-semibold text-<?= $verificationSuccess['status'] === 'approved' ? 'green' : 'red' ?>-900 mb-1">
          Listing <?= $verificationSuccess['status'] === 'approved' ? 'Approved' : 'Rejected' ?> Successfully!
        </h3>
        <p class="text-sm text-<?= $verificationSuccess['status'] === 'approved' ? 'green' : 'red' ?>-700">
          <?= htmlspecialchars($verificationSuccess['message']) ?>
        </p>
      </div>
      <button onclick="document.getElementById('successNotification').remove()" class="flex-shrink-0 text-<?= $verificationSuccess['status'] === 'approved' ? 'green' : 'red' ?>-400 hover:text-<?= $verificationSuccess['status'] === 'approved' ? 'green' : 'red' ?>-600">
        <i class="fas fa-times"></i>
      </button>
    </div>
  <?php endif; ?>

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 md:gap-6 mb-8">
    <?php
    $pending_count = 0;
    $verified_count = 0;
    $rejected_count = 0;
    
    foreach ($listings as $l) {
      if ($l['status'] === 'pending') $pending_count++;
      if ($l['status'] === 'approved') $verified_count++;
      if ($l['status'] === 'rejected') $rejected_count++;
    }
    $total_count = count($listings);
    ?>
    
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-500">Total Listings</p>
          <p class="text-2xl md:text-3xl font-bold text-gray-900 mt-1"><?= $total_count ?></p>
        </div>
        <div class="bg-blue-50 p-3 rounded-lg">
          <i class="fa fa-list-alt text-blue-500 text-xl"></i>
        </div>
      </div>
      <div class="mt-4 flex items-center text-xs text-gray-500">
        <i class="fa fa-clock mr-1"></i> All time
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-500">Pending Review</p>
          <p class="text-2xl md:text-3xl font-bold text-yellow-500 mt-1"><?= $pending_count ?></p>
        </div>
        <div class="bg-yellow-50 p-3 rounded-lg">
          <i class="fa fa-clock text-yellow-500 text-xl"></i>
        </div>
      </div>
      <div class="mt-4 flex items-center text-xs text-gray-500">
        <i class="fa fa-exclamation-circle mr-1 text-yellow-500"></i> Needs attention
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-500">Verified</p>
          <p class="text-2xl md:text-3xl font-bold text-green-500 mt-1"><?= $verified_count ?></p>
        </div>
        <div class="bg-green-50 p-3 rounded-lg">
          <i class="fa fa-check-circle text-green-500 text-xl"></i>
        </div>
      </div>
      <div class="mt-4 flex items-center text-xs text-gray-500">
        <i class="fa fa-check mr-1 text-green-500"></i> Approved
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-500">Rejected</p>
          <p class="text-2xl md:text-3xl font-bold text-red-500 mt-1"><?= $rejected_count ?></p>
        </div>
        <div class="bg-red-50 p-3 rounded-lg">
          <i class="fa fa-times-circle text-red-500 text-xl"></i>
        </div>
      </div>
      <div class="mt-4 flex items-center text-xs text-gray-500">
        <i class="fa fa-ban mr-1 text-red-500"></i> Not approved
      </div>
    </div>
  </div>

  <!-- Listings Table -->
  <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
      <div>
        <h2 class="text-lg md:text-xl font-semibold text-gray-900">All Listings</h2>
        <p class="text-sm text-gray-500 mt-1">Review, verify or reject submitted listings</p>
      </div>

      <!-- Filters - Mobile Responsive -->
      <div class="mt-4 md:mt-0 w-full md:w-auto">
        <form method="GET" class="flex flex-col md:flex-row gap-2 md:gap-2">
          <input type="hidden" name="p" value="dashboard">
          <input type="hidden" name="page" value="listingverification">
          
          <!-- Search Input - Full width on mobile -->
          <div class="relative flex-1 md:flex-initial">
            <i class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                   placeholder="Search listings..." 
                   class="w-full md:w-auto pl-10 pr-4 py-2.5 md:py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
          </div>
          
          <!-- Status Dropdown - Full width on mobile -->
          <select name="status" class="w-full md:w-auto px-3 py-2.5 md:py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
            <option value="">All Status</option>
            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>> Pending</option>
            <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>> Approved</option>
            <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>> Rejected</option>
          </select>
          
          <!-- Action Buttons - Side by side on mobile -->
          <div class="flex gap-2">
            <button type="submit" class="flex-1 md:flex-initial px-4 py-2.5 md:py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
              <i class="fa fa-search mr-1"></i> <span class="md:inline">Filter</span>
            </button>
            
            <a href="?p=dashboard&page=listingverification" class="flex-1 md:flex-initial px-4 py-2.5 md:py-2 bg-gray-500 text-white rounded-lg text-sm font-medium hover:bg-gray-600 transition-colors text-center">
              <i class="fa fa-times mr-1"></i> <span class="md:inline">Clear</span>
            </a>
          </div>
        </form>
      </div>
    </div>
    
    <?php if (empty($listings)): ?>
      <!-- Empty State -->
      <div class="text-center py-16">
        <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
          <i class="fas fa-clipboard-list text-3xl text-gray-400"></i>
        </div>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">No listings found</h3>
        <?php if ($search || $status): ?>
          <p class="text-gray-600 mb-6">No listings match your current search criteria.</p>
          <a href="?p=dashboard&page=listingverification" 
             class="bg-blue-600 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 inline-flex items-center">
            <i class="fas fa-times mr-2"></i> Clear Filters
          </a>
        <?php else: ?>
          <p class="text-gray-600 mb-6">No listings have been submitted for verification yet.</p>
          <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 max-w-md mx-auto">
            <div class="flex items-center gap-3 mb-3">
              <i class="fas fa-info-circle text-blue-500 text-xl"></i>
              <h4 class="font-semibold text-blue-900">What happens next?</h4>
            </div>
            <p class="text-blue-700 text-sm">When sellers submit new listings, they will appear here for your review and verification.</p>
          </div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <!-- Mobile: Card View -->
      <div class="block md:hidden space-y-4">
        <?php foreach ($listings as $l): 
          $isRecentlyVerified = ($verificationSuccess && $verificationSuccess['listing_id'] == $l['id']);
          $cardClass = $isRecentlyVerified ? 'bg-blue-50 border-l-4 border-blue-500' : 'bg-white';
        ?>
          <div class="<?= $cardClass ?> rounded-lg shadow-sm border border-gray-200 p-4">
            <!-- Listing Info -->
            <div class="flex items-start gap-3 mb-3">
              <?php if ($l['proof_image']): ?>
                <img src="<?= htmlspecialchars($l['proof_image']) ?>" alt="Proof" class="w-16 h-16 rounded-lg object-cover border shadow-sm flex-shrink-0">
              <?php else: ?>
                <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 border flex-shrink-0">
                  <i class="fa-regular fa-image text-xl"></i>
                </div>
              <?php endif; ?>
              <div class="flex-1 min-w-0">
                <h3 class="font-semibold text-gray-900 truncate"><?= htmlspecialchars($l['name']) ?></h3>
                <p class="text-xs text-gray-500 mt-1">
                  <i class="fa fa-tag mr-1"></i><?= htmlspecialchars($l['category']) ?>
                </p>
                <!-- Status Badge -->
                <div class="mt-2">
                  <?php if ($l['status'] === 'pending'): ?>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-yellow-100 text-yellow-700 text-xs font-medium">
                      <i class="fa fa-clock mr-1"></i> Pending
                    </span>
                  <?php elseif ($l['status'] === 'approved'): ?>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">
                      <i class="fa fa-check-circle mr-1"></i> Verified
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-red-100 text-red-700 text-xs font-medium">
                      <i class="fa fa-times-circle mr-1"></i> Rejected
                    </span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            
            <!-- Details Grid -->
            <div class="grid grid-cols-2 gap-3 mb-3 text-sm">
              <div>
                <p class="text-gray-500 text-xs mb-1">Seller</p>
                <p class="font-medium text-gray-900 truncate"><?= htmlspecialchars($l['seller_name']) ?></p>
              </div>
              <div>
                <p class="text-gray-500 text-xs mb-1">Price</p>
                <p class="font-semibold text-gray-900">$<?= number_format($l['asking_price'], 0) ?></p>
              </div>
              <div class="col-span-2">
                <p class="text-gray-500 text-xs mb-1">URL</p>
                <a href="https://<?= htmlspecialchars($l['url']) ?>" target="_blank" class="text-blue-600 hover:text-blue-800 text-xs truncate block">
                  <i class="fa fa-external-link-alt mr-1"></i><?= htmlspecialchars($l['url']) ?>
                </a>
              </div>
            </div>
            
            <!-- Actions -->
            <div class="flex gap-2 pt-3 border-t border-gray-200">
              <a href="index.php?p=listingDetail&id=<?= $l['id'] ?>" class="flex-1 px-3 py-2 bg-blue-50 text-blue-600 rounded-lg text-center text-sm font-medium hover:bg-blue-100 transition-colors">
                <i class="fa fa-eye mr-1"></i> View
              </a>
              <?php if ($l['status'] === 'pending'): ?>
                <button onclick="verifyListing(<?= $l['id'] ?>, 'verify')" class="flex-1 px-3 py-2 bg-green-50 text-green-600 rounded-lg text-center text-sm font-medium hover:bg-green-100 transition-colors">
                  <i class="fa fa-check mr-1"></i> Approve
                </button>
                <button onclick="verifyListing(<?= $l['id'] ?>, 'reject')" class="flex-1 px-3 py-2 bg-red-50 text-red-600 rounded-lg text-center text-sm font-medium hover:bg-red-100 transition-colors">
                  <i class="fa fa-times mr-1"></i> Reject
                </button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      
      <!-- Desktop: Table View -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-gray-600 border-b">
              <th class="py-3 px-4 text-left font-medium">Listing</th>
              <th class="py-3 px-4 text-left font-medium">Seller</th>
              <th class="py-3 px-4 text-left font-medium">URL</th>
              <th class="py-3 px-4 text-left font-medium">Price</th>
              <th class="py-3 px-4 text-left font-medium">Status</th>
              <th class="py-3 px-4 text-left font-medium">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <?php foreach ($listings as $l): 
              $isRecentlyVerified = ($verificationSuccess && $verificationSuccess['listing_id'] == $l['id']);
              $rowClass = $isRecentlyVerified ? 'bg-blue-50 border-l-4 border-blue-500' : 'hover:bg-gray-50';
            ?>
              <tr class="<?= $rowClass ?> transition-colors">
                <td class="py-4 px-4">
                  <div class="flex items-center gap-3">
                    <?php if ($l['proof_image']): ?>
                      <img src="<?= htmlspecialchars($l['proof_image']) ?>" alt="Proof" class="w-12 h-12 rounded-lg object-cover border shadow-sm">
                    <?php else: ?>
                      <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 border">
                        <i class="fa-regular fa-image text-lg"></i>
                      </div>
                    <?php endif; ?>
                    <div>
                      <div class="font-medium text-gray-800"><?= htmlspecialchars($l['name']) ?></div>
                      <div class="text-xs text-gray-500 mt-1 flex items-center">
                        <i class="fa fa-tag mr-1"></i> <?= htmlspecialchars($l['category']) ?>
                      </div>
                    </div>
                  </div>
                </td>

                <td class="py-4 px-4">
                  <div class="font-medium"><?= htmlspecialchars($l['seller_name']) ?></div>
                  <div class="text-xs text-gray-500 mt-1 flex items-center">
                    <i class="fa fa-envelope mr-1"></i> <?= htmlspecialchars($l['email']) ?>
                  </div>
                </td>

                <td class="py-4 px-4">
                  <a href="https://<?= htmlspecialchars($l['url']) ?>" target="_blank" class="text-blue-600 hover:text-blue-800 hover:underline flex items-center">
                    <i class="fa fa-external-link-alt mr-2 text-sm"></i> <?= htmlspecialchars($l['url']) ?>
                  </a>
                </td>

                <td class="py-4 px-4">
                  <div class="font-semibold text-gray-800">$<?= number_format($l['asking_price'], 2) ?></div>
                  <div class="text-xs text-gray-500 mt-1 flex items-center">
                    <i class="fa fa-chart-line mr-1"></i> $<?= number_format($l['monthly_revenue'], 2) ?>/mo
                  </div>
                </td>

                <td class="py-4 px-4">
                  <?php if ($l['status'] === 'pending'): ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-yellow-100 text-yellow-700 text-xs font-medium">
                      <i class="fa fa-clock mr-1.5"></i> Pending
                    </span>
                  <?php elseif ($l['status'] === 'approved'): ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">
                      <i class="fa fa-check-circle mr-1.5"></i> Verified
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-medium">
                      <i class="fa fa-times-circle mr-1.5"></i> Rejected
                    </span>
                  <?php endif; ?>
                </td>

                <td class="py-4 px-4">
                  <div class="flex items-center space-x-3">
                    <a href="./index.php?p=listingDetail&id=<?= $l['id'] ?>" class="text-blue-600 hover:text-blue-800 p-2 rounded-lg hover:bg-blue-50 transition-colors" title="View Details">
                      <i class="fa-regular fa-eye"></i>
                    </a>

                    <?php if ($l['status'] === 'pending'): ?>
                      <button onclick="verifyListing(<?= $l['id'] ?>, 'verify', this)" class="text-green-600 hover:text-green-800 p-2 rounded-lg hover:bg-green-50 transition-colors" title="Approve">
                        <i class="fa-regular fa-circle-check"></i>
                      </button>
                      <button onclick="verifyListing(<?= $l['id'] ?>, 'reject', this)" class="text-red-600 hover:text-red-800 p-2 rounded-lg hover:bg-red-50 transition-colors" title="Reject">
                        <i class="fa-solid fa-xmark"></i>
                      </button>
                    <?php else: ?>
                      <span class="text-gray-300 p-2">
                        <i class="fa-regular fa-circle-check"></i>
                      </span>
                      <span class="text-gray-300 p-2">
                        <i class="fa-solid fa-xmark"></i>
                      </span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
    

    <!-- Pagination -->
    <div class="mt-6 pt-4 border-t border-gray-200">
      <?php 
      $extraParams = ['p' => 'dashboard', 'page' => 'listingverification'];
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
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Verification Status</h3>
      <div class="space-y-4">
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <div class="w-3 h-3 rounded-full bg-yellow-500 mr-3"></div>
            <span class="text-sm text-gray-600">Pending Review</span>
          </div>
          <span class="text-sm font-medium"><?= $pending_count ?> listings</span>
        </div>
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <div class="w-3 h-3 rounded-full bg-green-500 mr-3"></div>
            <span class="text-sm text-gray-600">Verified</span>
          </div>
          <span class="text-sm font-medium"><?= $verified_count ?> listings</span>
        </div>
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <div class="w-3 h-3 rounded-full bg-red-500 mr-3"></div>
            <span class="text-sm text-gray-600">Rejected</span>
          </div>
          <span class="text-sm font-medium"><?= $rejected_count ?> listings</span>
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
            <span class="text-sm font-medium">Export Verification Report</span>
          </div>
          <i class="fa fa-chevron-right text-gray-400"></i>
        </button>
        <button class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors flex items-center justify-between">
          <div class="flex items-center">
            <i class="fa fa-envelope text-green-500 mr-3"></i>
            <span class="text-sm font-medium">Send Bulk Notifications</span>
          </div>
          <i class="fa fa-chevron-right text-gray-400"></i>
        </button>
        <button class="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors flex items-center justify-between">
          <div class="flex items-center">
            <i class="fa fa-cog text-purple-500 mr-3"></i>
            <span class="text-sm font-medium">Verification Settings</span>
          </div>
          <i class="fa fa-chevron-right text-gray-400"></i>
        </button>
      </div>
    </div>
  </div>
</div>


<!-- Auto-dismiss success notification -->
<?php if ($verificationSuccess): ?>
<script>
setTimeout(() => {
  const notification = document.getElementById('successNotification');
  if (notification) {
    notification.style.opacity = '0';
    notification.style.transition = 'opacity 0.3s';
    setTimeout(() => notification.remove(), 300);
  }
}, 5000);
</script>
<?php endif; ?>

<!-- Polling Integration -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  console.log('🚀 Listing Verification polling initialization started');
  
  if (typeof startPolling === 'undefined') {
    console.error('❌ Polling system not loaded');
    return;
  }
  
  startPolling({
    listings: (newListings) => {
      console.log('📋 New listings for verification:', newListings.length);
      if (newListings.length > 0) {
        // Reload page to show new listings
        location.reload();
      }
    }
  });
});
</script>


<script src="<?= BASE ?>js/polling.js"></script>
<script>
// AJAX Verification Function
function verifyListing(listingId, action, buttonElement) {
  // Disable buttons to prevent double-click
  const row = buttonElement.closest('tr');
  const buttons = row.querySelectorAll('button');
  buttons.forEach(btn => btn.disabled = true);
  
  // Show loading state
  buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  
  // Make AJAX request with timeout
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
  
  fetch(`index.php?p=dashboard&page=admin_listing_verification&action=${action}&id=${listingId}&ajax=1`, {
    method: 'GET',
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    },
    signal: controller.signal
  })
  .then(response => {
    clearTimeout(timeoutId); // Clear timeout on successful response
    console.log('Response status:', response.status);
    console.log('Response headers:', response.headers);
    
    // First get the text to see what we're receiving
    return response.text().then(text => {
      console.log('Response text:', text);
      
      // Try to parse as JSON
      try {
        return JSON.parse(text);
      } catch (e) {
        console.error('JSON parse error:', e);
        console.error('Received text:', text);
        throw new Error('Invalid JSON response: ' + text.substring(0, 100));
      }
    });
  })
  .then(data => {
    console.log('Parsed data:', data);
    if (data.success) {
      // Update status badge
      const statusCell = row.querySelector('td:nth-child(5)');
      const newStatus = action === 'verify' ? 'approved' : 'rejected';
      
      if (newStatus === 'approved') {
        statusCell.innerHTML = `
          <span class="inline-flex items-center px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">
            <i class="fa fa-check-circle mr-1.5"></i> Verified
          </span>
        `;
        row.classList.add('bg-green-50', 'border-l-4', 'border-green-500');
      } else {
        statusCell.innerHTML = `
          <span class="inline-flex items-center px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-medium">
            <i class="fa fa-times-circle mr-1.5"></i> Rejected
          </span>
        `;
        row.classList.add('bg-red-50', 'border-l-4', 'border-red-500');
      }
      
      // Replace action buttons with disabled icons
      const actionsCell = row.querySelector('td:last-child .flex');
      const viewButton = actionsCell.querySelector('a');
      actionsCell.innerHTML = viewButton.outerHTML + `
        <span class="text-gray-300 p-2">
          <i class="fa-regular fa-circle-check"></i>
        </span>
        <span class="text-gray-300 p-2">
          <i class="fa-solid fa-xmark"></i>
        </span>
      `;
      
      // Show success notification
      showNotification(data.message, 'success');
      
      // Update stats
      updateStats();
      
      // Remove highlight after 3 seconds
      setTimeout(() => {
        row.classList.remove('bg-green-50', 'bg-red-50', 'border-l-4', 'border-green-500', 'border-red-500');
      }, 3000);
    } else {
      showNotification(data.message || 'Failed to update listing', 'error');
      // Re-enable buttons on error
      buttons.forEach(btn => {
        btn.disabled = false;
        btn.innerHTML = btn === buttonElement ? 
          (action === 'verify' ? '<i class="fa-regular fa-circle-check"></i>' : '<i class="fa-solid fa-xmark"></i>') : 
          btn.innerHTML;
      });
    }
  })
  .catch(error => {
    clearTimeout(timeoutId); // Clear timeout on error
    console.error('AJAX Error Details:', error);
    console.error('Error name:', error.name);
    console.error('Listing ID:', listingId);
    console.error('Action:', action);
    console.error('Request URL:', `index.php?p=dashboard&page=admin_listing_verification&action=${action}&id=${listingId}&ajax=1`);
    
    let errorMessage = 'An error occurred. Please try again.';
    if (error.name === 'AbortError') {
      errorMessage = 'Request timeout. The server took too long to respond.';
    }
    
    showNotification(errorMessage + ' Check console for details.', 'error');
    
    // Re-enable buttons on error
    buttons.forEach(btn => {
      btn.disabled = false;
      btn.innerHTML = btn === buttonElement ? 
        (action === 'verify' ? '<i class="fa-regular fa-circle-check"></i>' : '<i class="fa-solid fa-xmark"></i>') : 
        btn.innerHTML;
    });
  });
}

// Update stats after verification
function updateStats() {
  const rows = document.querySelectorAll('tbody tr');
  let pending = 0, approved = 0, rejected = 0;
  
  rows.forEach(row => {
    const statusText = row.querySelector('td:nth-child(5)').textContent.trim();
    if (statusText.includes('Pending')) pending++;
    else if (statusText.includes('Verified')) approved++;
    else if (statusText.includes('Rejected')) rejected++;
  });
  
  // Update stat cards
  const statCards = document.querySelectorAll('.grid.grid-cols-1.md\\:grid-cols-4 > div');
  if (statCards[1]) statCards[1].querySelector('.text-2xl').textContent = pending;
  if (statCards[2]) statCards[2].querySelector('.text-2xl').textContent = approved;
  if (statCards[3]) statCards[3].querySelector('.text-2xl').textContent = rejected;
}

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
      <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation' : 'info'}-circle"></i>
      <span>${message}</span>
    </div>
  `;
  
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.style.opacity = '0';
    notification.style.transition = 'opacity 0.3s';
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

document.addEventListener('DOMContentLoaded', () => {
  console.log('🚀 Listing Verification polling initialization started');
  
  if (typeof startPolling === 'undefined') {
    console.error('❌ Polling system not loaded');
    return;
  }
  
  startPolling({
    listings: (newListings) => {
      console.log('📋 New listings detected:', newListings.length);
      
      // Filter only pending listings
      const pendingListings = newListings.filter(l => l.status === 'pending');
      
      if (pendingListings.length > 0) {
        showNotification(`${pendingListings.length} new listing(s) pending verification`, 'info');
        
        // Reload page after 2 seconds to show new listings
        setTimeout(() => {
          location.reload();
        }, 2000);
      }
    }
  });
});
</script>
