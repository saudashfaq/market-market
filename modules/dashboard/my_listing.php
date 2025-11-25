
<?php
// Check for export FIRST - before any output
if (isset($_GET['export'])) {
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../includes/export_helper.php';
    
    ob_start();
    require_login();
    $user = current_user();
    $user_id = $user['id'];
    ob_end_clean();
    
    $pdo = db();
    
    // Get all listings for export
    $exportSql = "
        SELECT l.id, l.name, l.type, l.status, l.asking_price, l.monthly_revenue, 
               l.site_age, l.subscribers, l.created_at
        FROM listings l
        WHERE l.user_id = :user_id
        ORDER BY l.created_at DESC
    ";
    
    $exportStmt = $pdo->prepare($exportSql);
    $exportStmt->execute([':user_id' => $user_id]);
    $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
    
    handleExportRequest($exportData, 'My Listings Report');
    exit;
}

// Clean version of my_listing.php with working polling
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../includes/pagination_helper.php';

require_login();
$user = current_user();

// Debug: Check if user data is properly set
if (!$user || !isset($user['id'])) {
    error_log("MY_LISTING ERROR: User data not properly set. User: " . print_r($user, true));
    setErrorMessage("Session error. Please log out and log back in.");
    header("Location: " . url('index.php?p=login'));
    exit;
}

$user_id = $user['id'];

// Debug: Log the user ID being used
error_log("MY_LISTING DEBUG: Current user ID: " . $user_id);

// Additional debug: Check if user exists in database
try {
    $userCheckStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $userCheckStmt->execute([$user_id]);
    $userExists = $userCheckStmt->fetch(PDO::FETCH_ASSOC);
    error_log("MY_LISTING DEBUG: User exists in DB: " . ($userExists ? "YES - " . $userExists['name'] : "NO"));
} catch (Exception $e) {
    error_log("MY_LISTING ERROR: User check failed: " . $e->getMessage());
}

$pdo = db();

// Get pagination parameters
$page = getCurrentPage('pg');
$perPage = 10;

// Setup search and filter conditions
$conditions = ['user_id' => $user_id];
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';

if ($search) {
    $conditions['name'] = ['like' => $search];
}
if ($status) {
    $conditions['status'] = $status;
}
if ($category) {
    $conditions['type'] = $category;
}

// Custom SQL for listings with proof images
$whereClause = 'WHERE l.user_id = :user_id';
$params = [':user_id' => $user_id];

if ($search) {
    $whereClause .= ' AND l.name LIKE :search';
    $params[':search'] = '%' . $search . '%';
}
if ($status) {
    $whereClause .= ' AND l.status = :status';
    $params[':status'] = $status;
}
if ($category) {
    $whereClause .= ' AND l.type = :category';
    $params[':category'] = $category;
}

$sql = "
  SELECT l.*, 
         GROUP_CONCAT(lp.file_path) as proof_images,
         COUNT(lp.id) as proof_count
  FROM listings l
  LEFT JOIN listing_proofs lp ON l.id = lp.listing_id
  {$whereClause}
  GROUP BY l.id
  ORDER BY l.created_at DESC
";

// Count query for pagination
$countSql = "
  SELECT COUNT(DISTINCT l.id) as total
  FROM listings l
  LEFT JOIN listing_proofs lp ON l.id = lp.listing_id
  {$whereClause}
";

// Debug: Log the query and parameters
error_log("MY_LISTING DEBUG: SQL Query: " . $sql);
error_log("MY_LISTING DEBUG: Parameters: " . print_r($params, true));

try {
    $result = getCustomPaginationData($pdo, $sql, $countSql, $params, $page, $perPage);
    $listings = $result['data'];
    $pagination = $result['pagination'];

    // Debug: Log the results
    error_log("MY_LISTING DEBUG: Found " . count($listings) . " listings for user ID: " . $user_id);
    if (!empty($listings)) {
        error_log("MY_LISTING DEBUG: First listing: " . print_r($listings[0], true));
    }
} catch (Exception $e) {
    error_log("MY_LISTING ERROR: Query failed: " . $e->getMessage());
    $listings = [];
    $pagination = ['current_page' => 1, 'total_pages' => 1, 'total_records' => 0];
}

// Get categories for filter
$categoriesStmt = $pdo->query("SELECT DISTINCT type FROM listings WHERE type IS NOT NULL AND type != ''");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<style>
.proof-thumbnail {
  transition: all 0.2s ease;
}
.proof-thumbnail:hover {
  transform: scale(1.1);
  z-index: 10;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
</style>

<section class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 py-8">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    
    <!-- Header -->
    <div class="mb-8">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div>
          <h1 class="text-3xl lg:text-4xl font-bold text-gray-900 tracking-tight mb-2">
            My Listings
          </h1>
          <p class="text-lg text-gray-600">
            Manage and track your digital assets
          </p>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-3">
          <?php require_once __DIR__ . '/../../includes/export_helper.php'; echo getExportButton('my_listings'); ?>
          <a href="index.php?p=sellingOption" 
             class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 inline-flex items-center justify-center">
            <i class="fas fa-plus-circle mr-2"></i> Add New Listing
          </a>
        </div>
      </div>
    </div>

    <!-- Real-time Updates Container -->
    <div id="listing-updates-container" class="mb-6"></div>
    
    <!-- Debug Info (remove in production) -->
    <?php if (isset($_GET['debug'])): ?>
      <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
        <h3 class="font-semibold text-yellow-800 mb-2">Debug Information</h3>
        <p><strong>Current User ID:</strong> <?= $user_id ?></p>
        <p><strong>Total Listings Found:</strong> <?= count($listings) ?></p>
        <?php if (!empty($listings)): ?>
          <p><strong>Sample Listing Data:</strong></p>
          <pre style="background: white; padding: 10px; border-radius: 5px; font-size: 12px; overflow-x: auto;">
            <?php 
            $sampleListing = $listings[0];
            echo "ID: " . $sampleListing['id'] . "\n";
            echo "Name: " . $sampleListing['name'] . "\n";
            echo "Proof Count: " . $sampleListing['proof_count'] . "\n";
            echo "Proof Images: " . ($sampleListing['proof_images'] ?: 'None') . "\n";
            ?>
          </pre>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="bg-white/80 backdrop-blur-xl border border-gray-100 rounded-2xl shadow-lg p-6 mb-6">
      <form method="GET" class="flex flex-col lg:flex-row gap-4">
        <input type="hidden" name="p" value="dashboard">
        <input type="hidden" name="page" value="my_listing">
        
        <div class="flex-1">
          <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                 placeholder="Search listings..." 
                 class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        
        <div class="lg:w-48">
          <select name="status" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">All Status</option>
            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
          </select>
        </div>
        
        <div class="lg:w-48">
          <select name="category" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">All Types</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                <?= ucfirst(htmlspecialchars($cat)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors">
          <i class="fas fa-search mr-2"></i> Filter
        </button>
      </form>
    </div>

    <!-- Listings Table -->
    <div class="bg-white/80 backdrop-blur-xl border border-gray-100 rounded-2xl shadow-lg overflow-hidden" id="listings-table-container">
      <?php if (empty($listings)): ?>
        <!-- Empty State Message -->
        <div class="text-center py-16" id="empty-state-message">
          <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <i class="fas fa-list-alt text-3xl text-gray-400"></i>
          </div>
          <h3 class="text-xl font-semibold text-gray-900 mb-2">No listings found</h3>
          <p class="text-gray-600 mb-6">Start by creating your first listing to showcase your digital assets.</p>
          <a href="index.php?p=sellingOption" 
             class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 inline-flex items-center">
            <i class="fas fa-plus-circle mr-2"></i> Create Your First Listing
          </a>
        </div>
        
        <!-- Hidden Table Structure for Dynamic Addition -->
        <div class="overflow-x-auto" id="table-structure" style="display: none;">
          <table class="w-full text-sm">
            <thead class="bg-gradient-to-r from-blue-50 to-purple-50 text-gray-700 uppercase tracking-wide">
              <tr>
                <th class="text-left py-3 px-3 font-semibold whitespace-nowrap">
                  <i class="fas fa-image mr-1 text-blue-500"></i>Listing
                </th>
                <th class="text-left py-3 px-3 font-semibold whitespace-nowrap">
                  <i class="fas fa-tag mr-1 text-green-500"></i>Status
                </th>
                <th class="text-left py-3 px-3 font-semibold whitespace-nowrap">
                  <i class="fas fa-dollar-sign mr-1 text-yellow-500"></i>Price
                </th>
                <th class="text-left py-3 px-3 font-semibold whitespace-nowrap">
                  <i class="fas fa-info-circle mr-1 text-purple-500"></i>Info
                </th>
                <th class="text-left py-3 px-3 font-semibold whitespace-nowrap">
                  <i class="fas fa-money-bill-wave mr-1 text-green-500"></i>Revenue
                </th>
                <th class="text-left py-3 px-3 font-semibold whitespace-nowrap">
                  <i class="fas fa-file-image mr-1 text-indigo-500"></i>Proofs
                </th>
                <th class="text-left py-3 px-3 font-semibold whitespace-nowrap">
                  <i class="fas fa-cog mr-1 text-gray-500"></i>Actions
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gradient-to-r from-blue-50 to-purple-50 text-gray-700 uppercase tracking-wide">
            <tr>
              <th class="text-left py-3 px-3 font-semibold whitespace-nowrap">
                <i class="fas fa-image mr-1 text-blue-500"></i>Listing
              </th>
              <th class="text-left py-3 px-3 font-semibold whitespace-nowrap">
                <i class="fas fa-tag mr-1 text-green-500"></i>Status
              </th>
              <th class="text-left py-3 px-3 font-semibold whitespace-nowrap">
                <i class="fas fa-dollar-sign mr-1 text-yellow-500"></i>Price
              </th>
              <th class="text-left py-3 px-3 font-semibold whitespace-nowrap">
                <i class="fas fa-info-circle mr-1 text-purple-500"></i>Info
              </th>
              <th class="text-left py-3 px-3 font-semibold whitespace-nowrap">
                <i class="fas fa-money-bill-wave mr-1 text-green-500"></i>Revenue
              </th>
              <th class="text-left py-3 px-3 font-semibold whitespace-nowrap">
                <i class="fas fa-file-image mr-1 text-indigo-500"></i>Proofs
              </th>
              <th class="text-left py-3 px-3 font-semibold whitespace-nowrap">
                <i class="fas fa-cog mr-1 text-gray-500"></i>Actions
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php foreach ($listings as $listing): ?>
              <tr class="hover:bg-blue-50/50 transition-colors">
                <td class="py-3 px-3">
                  <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-100 to-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                      <i class="fas fa-<?= $listing['type'] === 'youtube' ? 'play' : 'globe' ?> text-xl text-blue-600"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                      <h3 class="font-semibold text-gray-900 truncate">
                        <?= htmlspecialchars($listing['name'] ?: 'Untitled Listing') ?>
                      </h3>
                      <p class="text-sm text-gray-600 capitalize">
                        <?= htmlspecialchars($listing['type']) ?>
                      </p>
                      <div class="flex items-center gap-2 mt-1">
                        <span class="text-xs text-gray-500">
                          <i class="fas fa-calendar mr-1"></i>
                          <?= date('M j, Y', strtotime($listing['created_at'])) ?>
                        </span>
                      </div>
                    </div>
                  </div>
                </td>
                
                <td class="py-3 px-3">
                  <?php
                  $statusColors = [
                    'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                    'approved' => 'bg-green-100 text-green-800 border-green-200',
                    'rejected' => 'bg-red-100 text-red-800 border-red-200'
                  ];
                  $statusIcons = [
                    'pending' => 'clock',
                    'approved' => 'check-circle',
                    'rejected' => 'times-circle'
                  ];
                  ?>
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium border <?= $statusColors[$listing['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200' ?>">
                    <i class="fas fa-<?= $statusIcons[$listing['status']] ?? 'question' ?> mr-1"></i>
                    <?= ucfirst(htmlspecialchars($listing['status'])) ?>
                  </span>
                </td>
                
                <td class="py-3 px-3">
                  <div class="font-semibold text-gray-900 text-sm">
                    $<?= number_format($listing['asking_price'], 0) ?>
                  </div>
                </td>
                
                <td class="py-3 px-3">
                  <div class="text-xs text-gray-600">
                    <?php if ($listing['type'] === 'youtube'): ?>
                      <div><i class="fas fa-users mr-1 text-red-500"></i> <?= number_format($listing['subscribers']) ?></div>
                    <?php endif; ?>
                    <div><i class="fas fa-birthday-cake mr-1 text-purple-500"></i> <?= htmlspecialchars($listing['site_age']) ?></div>
                  </div>
                </td>
                
                <td class="py-3 px-3">
                  <div class="font-medium text-green-600 text-sm">
                    $<?= number_format($listing['monthly_revenue'], 0) ?>/mo
                  </div>
                </td>
                
                <td class="py-3 px-3">
                  <?php if ($listing['proof_count'] > 0 && !empty($listing['proof_images'])): ?>
                    <div class="flex items-center gap-2">
                      <div class="flex -space-x-1">
                        <?php 
                        $proofPaths = explode(',', $listing['proof_images']);
                        $displayCount = min(3, count($proofPaths)); // Show max 3 thumbnails
                        for ($i = 0; $i < $displayCount; $i++): 
                          $proofPath = trim($proofPaths[$i]);
                          if (empty($proofPath)) continue;
                          $extension = strtolower(pathinfo($proofPath, PATHINFO_EXTENSION));
                          $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                        ?>
                          <div class="w-8 h-8 rounded-full border-2 border-white overflow-hidden cursor-pointer proof-thumbnail"
                               onclick="openImageModal('<?= htmlspecialchars($proofPath) ?>')"
                               title="Click to view full size - <?= basename($proofPath) ?>">
                            <?php if ($isImage): ?>
                              <img src="<?= htmlspecialchars($proofPath) ?>" 
                                   alt="Proof" 
                                   class="w-full h-full object-cover"
                                   onerror="this.parentElement.innerHTML='<div class=\'w-full h-full bg-gray-200 flex items-center justify-center\'><i class=\'fas fa-image text-gray-400 text-xs\'></i></div>'"
                                   onload="console.log('Thumbnail loaded:', '<?= htmlspecialchars($proofPath) ?>')"
                                   style="border: 1px solid #ddd;">
                            <?php else: ?>
                              <div class="w-full h-full bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-file text-blue-600 text-xs"></i>
                              </div>
                            <?php endif; ?>
                          </div>
                        <?php endfor; ?>
                        
                        <?php if (count($proofPaths) > 3): ?>
                          <div class="w-8 h-8 rounded-full border-2 border-white bg-gray-100 flex items-center justify-center text-xs font-medium text-gray-600">
                            +<?= count($proofPaths) - 3 ?>
                          </div>
                        <?php endif; ?>
                      </div>
                      <span class="text-xs text-gray-500">
                        <?= $listing['proof_count'] ?> file<?= $listing['proof_count'] > 1 ? 's' : '' ?>
                      </span>
                    </div>
                  <?php else: ?>
                    <div class="text-xs text-gray-400 flex items-center">
                      <i class="fas fa-minus-circle mr-1"></i>
                      No proofs
                    </div>
                  <?php endif; ?>
                </td>
                
                <td class="py-3 px-3">
                  <div class="flex items-center gap-2">
                    <a href="index.php?p=listingDetail&id=<?= $listing['id'] ?>" 
                       class="text-blue-600 hover:text-blue-800 transition-colors text-sm" title="View">
                      <i class="fas fa-eye"></i>
                    </a>
                    
                    <?php if ($listing['type'] === 'youtube'): ?>
                      <a href="index.php?p=updateYtListing&id=<?= $listing['id'] ?>" 
                         class="text-green-600 hover:text-green-800 transition-colors text-sm" title="Edit">
                        <i class="fas fa-edit"></i>
                      </a>
                    <?php else: ?>
                      <a href="index.php?p=updateWebListing&id=<?= $listing['id'] ?>" 
                         class="text-green-600 hover:text-green-800 transition-colors text-sm" title="Edit">
                        <i class="fas fa-edit"></i>
                      </a>
                    <?php endif; ?>
                    <a href="#" 
                       class="text-red-600 hover:text-red-800 transition-colors text-sm" 
                       onclick="confirmDeleteListing(event, <?= $listing['id'] ?>, '<?= htmlspecialchars($listing['name'], ENT_QUOTES) ?>')" 
                       title="Delete">
                      <i class="fas fa-trash"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
      <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mt-6">
        <div class="flex items-center gap-2">
          <?php if ($pagination['current_page'] > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['pg' => $pagination['current_page'] - 1])) ?>" 
               class="px-4 py-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
              <i class="fas fa-chevron-left mr-1"></i> Previous
            </a>
          <?php endif; ?>
          
          <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['pg' => $i])) ?>" 
               class="px-4 py-2 <?= $i === $pagination['current_page'] ? 'bg-blue-600 text-white' : 'bg-white border border-gray-200 hover:bg-gray-50' ?> rounded-lg transition-colors">
              <?= $i ?>
            </a>
          <?php endfor; ?>
          
          <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['pg' => $pagination['current_page'] + 1])) ?>" 
               class="px-4 py-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
              Next <i class="fas fa-chevron-right ml-1"></i>
            </a>
          <?php endif; ?>
        </div>
        
        <div class="text-sm text-gray-500">
          Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?>
        </div>
      </div>
    <?php endif; ?>

  </div>
</section>

<script src="<?= BASE ?>js/polling.js?v=<?= time() ?>"></script>
<script>
// Use centralized polling system
const currentUserId = <?= $user_id ?>;
console.log('🔧 My Listing Page - User ID:', currentUserId);

// Manual test function for polling URL
window.testPollingURL = function() {
  const origin = window.location.origin;
  const pathname = window.location.pathname;
  let basePath = '';
  if (pathname.includes('/public/')) {
    basePath = pathname.substring(0, pathname.indexOf('/public/'));
  } else if (pathname.includes('/modules/')) {
    basePath = pathname.substring(0, pathname.indexOf('/modules/'));
  } else if (pathname.includes('/index.php')) {
    basePath = pathname.substring(0, pathname.indexOf('/index.php'));
  } else {
    basePath = '/marketplace';
  }
  const pollingUrl = origin + basePath + '/includes/polling_integration.php';
  console.log('🧪 Test Polling URL:', pollingUrl);
  console.log('🧪 Base path:', basePath);
  console.log('🧪 Current pathname:', pathname);
  
  // Test if file exists
  fetch(pollingUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ listings: '2025-01-01 00:00:00' })
  })
  .then(response => {
    console.log('✅ Polling file found! Status:', response.status);
    return response.json();
  })
  .then(data => {
    console.log('✅ Polling response:', data);
  })
  .catch(error => {
    console.error('❌ Polling test failed:', error);
  });
};

console.log('💡 Run testPollingURL() in console to test polling connection');

// Initialize polling with callbacks
document.addEventListener('DOMContentLoaded', function() {
  console.log('🚀 My Listing page polling initialization started');
  console.log('👤 Current User ID:', currentUserId);
  
  // Reset polling timestamps to catch recent listings
  setTimeout(() => {
    console.log('🔄 Resetting polling timestamps to catch recent listings...');
    const twoHoursAgo = new Date();
    twoHoursAgo.setHours(twoHoursAgo.getHours() - 2);
    const resetTimestamp = twoHoursAgo.toISOString().slice(0, 19).replace('T', ' ');
    
    // Reset localStorage timestamps
    const timestamps = {
      listings: resetTimestamp,
      offers: resetTimestamp,
      orders: resetTimestamp,
      logs: resetTimestamp,
      transactions: resetTimestamp
    };
    localStorage.setItem('polling_timestamps', JSON.stringify(timestamps));
    console.log('✅ Timestamps reset to:', resetTimestamp);
    

  }, 2000);
  

  
  // Track processed listings and offers to avoid duplicates
  const processedListings = new Set();
  const processedOffers = new Set();
  
  startPolling({
    listings: (newListings) => {
      console.log('📋 Listings callback triggered!');
      console.log('📊 Received listings count:', newListings.length);
      
      // Filter listings for current user only
      const userListings = newListings.filter(item => {
        return item.user_id == currentUserId;
      });
      
      // Filter out already processed listings
      const unprocessedListings = userListings.filter(item => {
        const key = `${item.id}_${item.status}_${item.updated_at || item.created_at}`;
        if (processedListings.has(key)) {
          console.log(`⏭️ Skipping already processed listing: ${item.name}`);
          return false;
        }
        processedListings.add(key);
        return true;
      });
      
      console.log('👤 User listings filtered:', userListings.length, 'listings');
      console.log('🆕 Unprocessed listings:', unprocessedListings.length, 'listings');
      
      if (unprocessedListings.length > 0) {
        console.log('✅ Processing', unprocessedListings.length, 'new listings');
        handleNewListings(unprocessedListings);
      } else {
        console.log('ℹ️ No new listings to process');
      }
    },
    offers: (newOffers) => {
      console.log('💰 Offers callback triggered!');
      
      // Filter offers for current user's listings only
      const myOffers = newOffers.filter(offer => offer.seller_id == currentUserId);
      
      // Filter out already processed offers
      const unprocessedOffers = myOffers.filter(offer => {
        const key = `${offer.id}_${offer.status}_${offer.created_at}`;
        if (processedOffers.has(key)) {
          console.log(`⏭️ Skipping already processed offer: ${offer.id}`);
          return false;
        }
        processedOffers.add(key);
        return true;
      });
      
      console.log('👤 My offers filtered:', myOffers.length);
      console.log('🆕 Unprocessed offers:', unprocessedOffers.length);
      
      if (unprocessedOffers.length > 0) {
        handleNewOffers(unprocessedOffers);
      }
    }
  });
});

// Keep the existing handler functions
function handleNewListings(userListings) {
  console.log('🎯 Processing new listings for user:', userListings);
  
  userListings.forEach(item => {
    // Show notification based on change type
    const isStatusUpdate = item.change_type === 'status_changed';
    if (isStatusUpdate) {
      showNotification(`Listing "${item.name}" status updated to ${item.status}`, 'info');
    } else {
      showNotification(`New listing "${item.name}" has been added!`, 'success');
    }
    
    // Add to updates container
    const div = document.createElement('div');
    div.className = "p-4 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-xl shadow-sm mb-3 animate-pulse";
    div.innerHTML = `
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
          <i class="fas fa-plus text-white"></i>
        </div>
        <div class="flex-1">
          <p class="text-sm font-semibold text-green-800">New Listing Added</p>
          <p class="text-xs text-green-600">${item.name} - $${parseFloat(item.asking_price).toLocaleString()}</p>
        </div>
        <button onclick="this.parentElement.parentElement.remove()" class="text-green-500 hover:text-green-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
    `;
    
    const container = document.querySelector('#listing-updates-container');
    if (container) {
      container.prepend(div);
      
      // Auto remove after 10 seconds
      setTimeout(() => {
        if (div.parentElement) {
          div.style.opacity = '0';
          div.style.transform = 'translateX(100%)';
          setTimeout(() => div.remove(), 300);
        }
      }, 10000);
    }
  });
  
  // Add or update listings in the table
  userListings.forEach(item => {
    // Check if this is a status update or new listing
    console.log('📦 Processing listing:', item.name, 'ID:', item.id, 'Change type:', item.change_type);
    
    if (item.change_type === 'status_changed') {
      console.log('🔄 Calling updateListingInTable for:', item.name);
      updateListingInTable(item);
    } else {
      console.log('➕ Calling addListingToTable for:', item.name);
      addListingToTable(item);
    }
  });
}

function updateListingInTable(listing) {
  console.log('🔄 Updating existing listing:', listing.name, 'ID:', listing.id);
  
  // Debug: Check all existing rows
  const allRows = document.querySelectorAll('tr[data-listing-id]');
  console.log('📋 All rows with data-listing-id:', allRows.length);
  allRows.forEach(row => {
    console.log('  - Row ID:', row.dataset.listingId);
  });
  
  // Find the existing row
  const existingRow = document.querySelector(`tr[data-listing-id="${listing.id}"]`);
  console.log('🔍 Looking for row with ID:', listing.id, 'Found:', !!existingRow);
  
  if (!existingRow) {
    console.log('⚠️ Listing not found in table, adding as new');
    addListingToTable(listing);
    return;
  }
  
  console.log('✅ Found existing row, updating status...');
  
  // Update the status badge
  const statusCell = existingRow.querySelector('td:nth-child(2)');
  if (statusCell) {
    const statusColors = {
      'pending': 'bg-yellow-100 text-yellow-800 border-yellow-200',
      'approved': 'bg-green-100 text-green-800 border-green-200',
      'rejected': 'bg-red-100 text-red-800 border-red-200'
    };
    const statusIcons = {
      'pending': 'clock',
      'approved': 'check-circle',
      'rejected': 'times-circle'
    };
    
    const statusClass = statusColors[listing.status] || 'bg-gray-100 text-gray-800 border-gray-200';
    const statusIcon = statusIcons[listing.status] || 'question';
    
    statusCell.innerHTML = `
      <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium border ${statusClass}">
        <i class="fas fa-${statusIcon} mr-1"></i>
        ${listing.status.charAt(0).toUpperCase() + listing.status.slice(1)}
      </span>
    `;
  }
  
  // Highlight the row to show it was updated
  existingRow.style.backgroundColor = '#fef3c7'; // Yellow highlight for updates
  setTimeout(() => {
    existingRow.style.backgroundColor = '';
  }, 3000);
  
  // Show notification
  showNotification(`Listing "${listing.name}" status updated to ${listing.status}`, 'info');
}

function addListingToTable(listing) {
  console.log('🔧 Adding new listing to table:', listing.name);
  
  // Find the table tbody
  let tbody = document.querySelector('#listings-table-container table tbody') ||
              document.querySelector('.overflow-x-auto table tbody') ||
              document.querySelector('table.w-full tbody') ||
              document.querySelector('table tbody');
  
  if (!tbody) {
    console.error('❌ Table tbody not found!');
    return;
  }
  
  // Final check - if still no tbody, return with error
  if (!tbody) {
    console.error('❌ Still no tbody found after all attempts!');
    return;
  }
  
  // Check if listing already exists in table
  const existingRow = document.querySelector(`tr[data-listing-id="${listing.id}"]`);
  console.log('🔍 Existing row check:', !!existingRow);
  if (existingRow) {
    console.log('⚠️ Listing already exists in table, skipping');
    return;
  }
  
  // Create new table row matching exact structure
  const row = document.createElement('tr');
  row.className = 'hover:bg-blue-50/50 transition-colors animate-fade-in';
  row.dataset.listingId = listing.id;
  row.style.backgroundColor = '#dbeafe'; // Light blue background for new items
  
  // Format status badge
  const statusColors = {
    'pending': 'bg-yellow-100 text-yellow-800 border-yellow-200',
    'approved': 'bg-green-100 text-green-800 border-green-200',
    'rejected': 'bg-red-100 text-red-800 border-red-200'
  };
  const statusIcons = {
    'pending': 'clock',
    'approved': 'check-circle',
    'rejected': 'times-circle'
  };
  
  const statusClass = statusColors[listing.status] || 'bg-gray-100 text-gray-800 border-gray-200';
  const statusIcon = statusIcons[listing.status] || 'question';
  const listingType = listing.type || 'website';
  const typeIcon = listingType === 'youtube' ? 'play' : 'globe';
  
  // Create the row HTML matching the image design
  row.innerHTML = `
    <!-- LISTING Column -->
    <td class="py-4 px-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
          <i class="fas fa-${typeIcon} text-blue-600"></i>
        </div>
        <div class="min-w-0">
          <h3 class="font-semibold text-gray-900 text-sm">${listing.name || 'Untitled Listing'}</h3>
          <p class="text-xs text-gray-500 capitalize">${listingType}</p>
          <p class="text-xs text-gray-400">
            <i class="fas fa-calendar mr-1"></i>
            ${new Date(listing.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
          </p>
          ${listing.proof_count > 0 ? `
            <span class="text-xs text-blue-600 cursor-pointer" onclick="toggleProofPreview(${listing.id})">
              <i class="fas fa-paperclip mr-1"></i>
              ${listing.proof_count} proof${listing.proof_count > 1 ? 's' : ''}
            </span>
          ` : ''}
        </div>
      </div>
    </td>
    <td class="py-3 px-3">
      <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium border ${statusClass}">
        <i class="fas fa-${statusIcon} mr-1"></i>
        ${listing.status.charAt(0).toUpperCase() + listing.status.slice(1)}
      </span>
    </td>
    <td class="py-3 px-3">
      <div class="font-semibold text-gray-900 text-sm">
        $${parseFloat(listing.asking_price || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
      </div>
    </td>
    <td class="py-3 px-3">
      <div class="text-xs text-gray-600">
        ${listingType === 'youtube' && listing.subscribers ? `
          <div><i class="fas fa-users mr-1 text-red-500"></i> ${parseInt(listing.subscribers).toLocaleString()}</div>
        ` : ''}
        <div><i class="fas fa-birthday-cake mr-1 text-purple-500"></i> ${listing.site_age || 'N/A'}</div>
      </div>
    </td>
    <td class="py-3 px-3">
      <div class="font-medium text-green-600 text-sm">
        $${parseFloat(listing.monthly_revenue || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}/mo
      </div>
    </td>
    <td class="py-3 px-3">
      ${listing.proof_count > 0 && listing.proof_images ? `
        <div class="flex items-center gap-2">
          <div class="flex -space-x-1">
            ${(() => {
              const proofPaths = listing.proof_images.split(',');
              const displayCount = Math.min(3, proofPaths.length);
              let thumbnailsHtml = '';
              
              for (let i = 0; i < displayCount; i++) {
                const proofPath = proofPaths[i].trim();
                if (!proofPath) continue;
                
                const extension = proofPath.split('.').pop().toLowerCase();
                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension);
                
                if (isImage) {
                  thumbnailsHtml += `
                    <div class="w-8 h-8 rounded-full border-2 border-white overflow-hidden cursor-pointer proof-thumbnail"
                         onclick="openImageModal('${proofPath}')"
                         title="Click to view full size - ${proofPath.split('/').pop()}">
                      <img src="${proofPath}" 
                           alt="Proof" 
                           class="w-full h-full object-cover"
                           onerror="this.parentElement.innerHTML='<div class=\\'w-full h-full bg-gray-200 flex items-center justify-center\\'><i class=\\'fas fa-image text-gray-400 text-xs\\'></i></div>'"
                           style="border: 1px solid #ddd;">
                    </div>
                  `;
                } else {
                  thumbnailsHtml += `
                    <div class="w-8 h-8 rounded-full border-2 border-white bg-blue-100 flex items-center justify-center">
                      <i class="fas fa-file text-blue-600 text-xs"></i>
                    </div>
                  `;
                }
              }
              
              if (proofPaths.length > 3) {
                thumbnailsHtml += `
                  <div class="w-8 h-8 rounded-full border-2 border-white bg-gray-100 flex items-center justify-center text-xs font-medium text-gray-600">
                    +${proofPaths.length - 3}
                  </div>
                `;
              }
              
              return thumbnailsHtml;
            })()}
          </div>
          <span class="text-xs text-gray-500">
            ${listing.proof_count} file${listing.proof_count > 1 ? 's' : ''}
          </span>
        </div>
      ` : `
        <div class="text-xs text-gray-400 flex items-center">
          <i class="fas fa-minus-circle mr-1"></i>
          No proofs
        </div>
      `}
    </td>
    <td class="py-3 px-3">
      <div class="flex items-center gap-2">
        <a href="index.php?p=listingDetail&id=${listing.id}" 
           class="text-blue-600 hover:text-blue-800 transition-colors text-sm" title="View">
          <i class="fas fa-eye"></i>
        </a>
        ${listingType === 'youtube' ? `
          <a href="index.php?p=updateYtListing&id=${listing.id}" 
             class="text-green-600 hover:text-green-800 transition-colors text-sm" title="Edit">
            <i class="fas fa-edit"></i>
          </a>
        ` : `
          <a href="index.php?p=updateWebListing&id=${listing.id}" 
             class="text-green-600 hover:text-green-800 transition-colors text-sm" title="Edit">
            <i class="fas fa-edit"></i>
          </a>
        `}
        <a href="#" 
           class="text-red-600 hover:text-red-800 transition-colors text-sm" 
           onclick="confirmDeleteListing(event, ${listing.id}, '${(listing.name || 'Untitled Listing').replace(/'/g, '\\\'')}')" 
           title="Delete">
          <i class="fas fa-trash"></i>
        </a>
      </div>
    </td>
  `;
  
  console.log('✅ Row HTML created, adding to table...');
  console.log('📊 Current tbody children count:', tbody.children.length);
  
  // Check if we need to show the table (hide empty state message)
  const emptyStateMessage = document.querySelector('#empty-state-message');
  const tableStructure = document.querySelector('#table-structure');
  
  if (emptyStateMessage && tableStructure) {
    console.log('🔄 Switching from empty state to table view');
    emptyStateMessage.style.display = 'none';
    tableStructure.style.display = 'block';
    
    // Update tbody reference to the now-visible table
    tbody = tableStructure.querySelector('tbody');
    console.log('✅ Updated tbody reference:', !!tbody);
  }
  
  // Add to top of table
  if (tbody.firstChild) {
    tbody.insertBefore(row, tbody.firstChild);
  } else {
    tbody.appendChild(row);
  }
  console.log('✅ Row added to table successfully!');
  console.log('📊 New tbody children count:', tbody.children.length);
  
  // Remove highlight after 5 seconds
  setTimeout(() => {
    row.style.backgroundColor = '';
    console.log('🎨 Removed highlight from new listing row');
  }, 5000);
}



function handleNewOffers(newOffers) {
  console.log('💰 My Listing - Offers callback triggered!');
  console.log('📊 Received offers count:', newOffers.length);
  
  // Filter offers for current user's listings only
  const myOffers = newOffers.filter(offer => offer.seller_id == currentUserId);
  console.log('👤 My offers filtered:', myOffers.length);
  
  if (myOffers.length === 0) {
    console.log('ℹ️ No new offers for current user');
    return;
  }
  
  // Group offers by listing_id to determine highest/lowest
  const offersByListing = {};
  myOffers.forEach(offer => {
    if (!offersByListing[offer.listing_id]) {
      offersByListing[offer.listing_id] = [];
    }
    offersByListing[offer.listing_id].push(offer);
  });
  
  // Process each offer with proper highest/lowest logic
  myOffers.forEach(offer => {
    const listingOffers = offersByListing[offer.listing_id] || [];
    const isLowerOffer = (offer.total_offers_count > 1 && offer.amount < offer.max_offer_amount);
    const isHighestOffer = (offer.amount == offer.max_offer_amount && offer.total_offers_count > 1);
    
    // Show notification with badge
    let badgeText = '';
    if (isHighestOffer) {
      badgeText = ' 🌟 (Highest)';
    } else if (isLowerOffer) {
      badgeText = ' ⬇️ (Lower)';
    }
    
    showNotification(
      `New offer received: $${parseFloat(offer.amount).toLocaleString()}${badgeText} for "${offer.listing_name}"`, 
      isHighestOffer ? 'success' : (isLowerOffer ? 'warning' : 'info')
    );
  });
}

// Notification function
function showNotification(message, type = 'info') {
  const notification = document.createElement('div');
  notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transform transition-all duration-300 translate-x-full`;
  
  const colors = {
    info: 'bg-blue-500 text-white',
    success: 'bg-green-500 text-white',
    warning: 'bg-yellow-500 text-white',
    error: 'bg-red-500 text-white'
  };
  
  notification.className += ` ${colors[type] || colors.info}`;
  
  notification.innerHTML = `
    <div class="flex items-center gap-3">
      <div class="flex-shrink-0">
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : type === 'error' ? 'times-circle' : 'info-circle'}"></i>
      </div>
      <div class="flex-1">
        <p class="text-sm font-medium">${message}</p>
      </div>
      <button onclick="this.parentElement.parentElement.remove()" class="flex-shrink-0 text-white hover:text-gray-200">
        <i class="fas fa-times"></i>
      </button>
    </div>
  `;
  
  document.body.appendChild(notification);
  
  // Slide in animation
  setTimeout(() => {
    notification.classList.remove('translate-x-full');
  }, 100);
  
  // Auto remove after 5 seconds
  setTimeout(() => {
    notification.classList.add('translate-x-full');
    setTimeout(() => {
      if (notification.parentElement) {
        notification.remove();
      }
    }, 300);
  }, 5000);
}



// Polling is now initialized in DOMContentLoaded event above

// Proof preview functions
function toggleProofPreview(listingId) {
  const preview = document.getElementById('proof-preview-' + listingId);
  if (preview) {
    if (preview.classList.contains('show')) {
      preview.classList.remove('show');
      setTimeout(() => {
        preview.style.display = 'none';
      }, 300);
    } else {
      preview.style.display = 'block';
      setTimeout(() => {
        preview.classList.add('show');
      }, 10);
    }
  }
}

// Image modal functions
function openImageModal(imageSrc) {
  // Create modal if it doesn't exist
  let modal = document.getElementById('imageModal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'imageModal';
    modal.className = 'fixed inset-0 bg-black bg-opacity-75 z-50 hidden flex items-center justify-center p-4';
    modal.innerHTML = `
      <div class="relative max-w-4xl max-h-full">
        <button onclick="closeImageModal()" class="absolute top-4 right-4 text-white bg-black bg-opacity-50 rounded-full w-10 h-10 flex items-center justify-center hover:bg-opacity-75 z-10">
          <i class="fas fa-times"></i>
        </button>
        <img id="modalImage" src="" alt="Proof document" class="max-w-full max-h-full rounded-lg">
      </div>
    `;
    document.body.appendChild(modal);
    
    // Close modal when clicking outside the image
    modal.addEventListener('click', function(e) {
      if (e.target === modal) {
        closeImageModal();
      }
    });
  }
  
  document.getElementById('modalImage').src = imageSrc;
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeImageModal() {
  const modal = document.getElementById('imageModal');
  if (modal) {
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
  }
}

// Add keyboard support for closing modal
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeImageModal();
  }
  

});

// Delete listing confirmation function
function confirmDeleteListing(event, listingId, listingName) {
  if (event && event.preventDefault) {
    event.preventDefault();
  }
  
  console.log('Delete confirmation triggered for listing:', listingId, listingName);
  
  // Wait for popup system to be ready
  if (typeof showConfirm === 'undefined') {
    console.error('Popup system not loaded yet');
    setTimeout(() => confirmDeleteListing(event, listingId, listingName), 100);
    return;
  }
  
  // Show confirmation popup
  showConfirm(`Are you sure you want to delete "${listingName}"?`, {
    title: 'Delete Listing',
    confirmText: 'Yes, Delete',
    cancelText: 'Cancel'
  }).then(function(result) {
    console.log('Delete confirmation result:', result);
    if (result === true) {
      console.log('User confirmed delete - proceeding...');
      
      // Show loading message
      showWarning('Deleting listing...', {
        title: 'Please wait',
        showConfirm: false,
        autoClose: true,
        autoCloseTime: 2000
      });
      
      // Redirect to delete page after short delay
      setTimeout(function() {
        window.location.href = `index.php?p=dashboard&page=delete_listing&id=${listingId}`;
      }, 500);
    } else {
      console.log('Delete cancelled by user');
    }
  }).catch(function(error) {
    console.error('Delete confirmation error:', error);
    // Fallback - still allow delete on error
    if (confirm('Popup system error. Delete listing anyway?')) {
      window.location.href = `index.php?p=dashboard&page=delete_listing&id=${listingId}`;
    }
  });
}
</script>

