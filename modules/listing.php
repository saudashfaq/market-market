<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/pagination_helper.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = db();

// Check if user is logged in
$user_logged_in = isset($_SESSION['user']);
$current_user_id = $user_logged_in ? $_SESSION['user']['id'] : null;

// Get pagination parameters
$page = getCurrentPage('pg');
$perPage = 12; // 12 listings per page (4x3 grid)
// Base SQL
$sql = "SELECT l.*, 
               (SELECT file_path FROM listing_proofs WHERE listing_id = l.id LIMIT 1) AS proof_image
        FROM listings l
        WHERE l.status = 'approved'";

$countSql = "SELECT COUNT(*) as total FROM listings l WHERE l.status = 'approved'";

$params = [];

// Filters
if (!empty($_GET['category']) && $_GET['category'] != 'All') {
    if (in_array($_GET['category'], ['website', 'youtube', 'app'])) {
        // Filter by type for main categories
        $sql .= " AND l.type = :type";
        $countSql .= " AND l.type = :type";
        $params[':type'] = $_GET['category'];
    } else {
        // Filter by category for other filters
        $sql .= " AND l.category = :category";
        $countSql .= " AND l.category = :category";
        $params[':category'] = $_GET['category'];
    }
}

if (!empty($_GET['search'])) {
    $sql .= " AND (l.name LIKE :search OR l.category LIKE :search OR l.url LIKE :search OR l.type LIKE :search OR l.monetization_methods LIKE :search)";
    $countSql .= " AND (l.name LIKE :search OR l.category LIKE :search OR l.url LIKE :search OR l.type LIKE :search OR l.monetization_methods LIKE :search)";
    $params[':search'] = '%' . $_GET['search'] . '%';
}

$sql .= " ORDER BY l.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get paginated data
$result = getCustomPaginationData($pdo, $sql, $countSql, $params, $page, $perPage);
$listings = $result['data'];
$pagination = $result['pagination'];

$categories = ['All', 'Website', 'YouTube'];
?>

<style>
/* Enhanced blur effect for non-logged-in users */
.blur-content {
  filter: blur(3px);
  user-select: none;
  pointer-events: none;
}

/* Smooth transitions */
.listing-card {
  transition: all 0.3s ease;
}
</style>



<!-- Listings Section -->
<section class="bg-white py-12">
  <div class="container mx-auto px-4 grid grid-cols-1 lg:grid-cols-4 gap-8">

    <!-- Sidebar -->
    <aside class="lg:col-span-1 bg-white rounded-xl shadow-sm border border-gray-200 p-6" style="position: sticky; top: 0; align-self: start; max-height: 100vh; overflow-y: auto;">
      <?php if ($user_logged_in): ?>
        <form method="GET">
          <input type="hidden" name="p" value="listing">
          
          <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center">
            <i class="fas fa-sliders-h mr-2 text-purple-600"></i> Filters
          </h3>
   
          <!-- Search Input -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <div class="relative">
              <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                     placeholder="Search listings..." 
                     class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
            </div>
          </div>
          <!-- Category Filter -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-list mr-2 text-gray-500"></i> Category
            </label>
            <select name="category" onchange="this.form.submit()" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
              <option value="">All Categories</option>
              <option value="website" <?= ($_GET['category'] ?? '') === 'website' ? 'selected' : '' ?>>Websites</option>
              <option value="youtube" <?= ($_GET['category'] ?? '') === 'youtube' ? 'selected' : '' ?>>YouTube Channels</option>
            </select>
          </div>

          <button type="submit" class="w-full rounded-lg bg-gradient-to-r from-purple-600 to-pink-600 px-4 py-3 text-sm font-medium text-white hover:opacity-90 transition-opacity flex items-center justify-center mb-3">
            <i class="fas fa-search mr-2"></i> Search
          </button>
          
          <a href="<?= url('index.php?p=listing') ?>" class="w-full block text-center rounded-lg border border-gray-300 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
            <i class="fas fa-times mr-2"></i> Clear Filters
          </a>
        </form>
      <?php else: ?>
        <!-- Login Required Message for Sidebar -->
        <div class="text-center">
          <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center justify-center">
            <i class="fas fa-sliders-h mr-2 text-purple-600"></i> Filters
          </h3>
          
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
            <i class="fas fa-lock text-blue-600 text-2xl mb-2"></i>
            <p class="text-sm font-medium text-gray-900 mb-2">Login Required</p>
            <p class="text-xs text-gray-600 mb-3">Sign in to search and filter listings</p>
          </div>
          
          <a href="<?= url('index.php?p=login') ?>" class="w-full block text-center rounded-lg bg-gradient-to-r from-blue-600 to-purple-600 px-4 py-3 text-sm font-medium text-white hover:opacity-90 transition-opacity mb-3">
            <i class="fas fa-right-to-bracket mr-2"></i> Login to Search
          </a>
          
          <div class="text-xs text-gray-500">
            <p>Browse all listings below or login for advanced search and filtering options.</p>
          </div>
        </div>
      <?php endif; ?>
    </aside>

    <?php if ($user_logged_in): ?>
    <script>
      // Auto-submit form when Enter is pressed in search input
      const searchInput = document.querySelector('input[name="search"]');
      if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
          if (e.key === 'Enter') {
            this.form.submit();
          }
        });
      }
    </script>
    <?php endif; ?>

    <!-- Listings -->
    <div class="lg:col-span-3">
      <div class="mb-8">
        <h1 class="text-xl font-bold text-gray-900">Digital Assets Marketplace</h1>
        <?php if (!$user_logged_in): ?>
          <p class="text-sm text-gray-600 mt-2">
            <i class="fas fa-info-circle mr-1"></i>
            <a href="<?= url('index.php?p=login') ?>" class="text-blue-600 hover:text-blue-800 underline">Login</a> to view full details, search, and make offers
          </p>
        <?php endif; ?>
      </div>

      <div id="cardsContainer" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <?php if (empty($listings)): ?>
          <div class="col-span-full text-center py-16">
            <div class="max-w-md mx-auto">
              <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-search text-gray-400 text-3xl"></i>
              </div>
              <h3 class="text-2xl font-bold text-gray-800 mb-3">No Listings Found</h3>
              <p class="text-gray-600 mb-6">
                <?php if (!empty($_GET['search']) || !empty($_GET['category'])): ?>
                  We couldn't find any listings matching your criteria. Try adjusting your filters or search terms.
                <?php else: ?>
                  No digital assets are currently available. Check back soon for new listings!
                <?php endif; ?>
              </p>
              <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="<?= url('index.php?p=listing') ?>" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                  <i class="fas fa-list mr-2"></i>
                  Browse All Listings
                </a>
                <?php if ($user_logged_in): ?>
                  <a href="<?= url('index.php?p=sellingOption') ?>" class="inline-flex items-center px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-semibold">
                    <i class="fas fa-plus mr-2"></i>
                    List Your Asset
                  </a>
                <?php else: ?>
                  <a href="<?= url('index.php?p=login') ?>" class="inline-flex items-center px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-semibold">
                    <i class="fas fa-right-to-bracket mr-2"></i>
                    Login to Sell
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($listings as $listing): ?>
            <div class="group listing-card relative rounded-2xl border border-gray-200 shadow-md hover:shadow-lg transition-all duration-500 overflow-hidden hover:-translate-y-2 animate-fade-in bg-white">

            <!-- Image -->
            <div class="relative h-56 overflow-hidden bg-gray-100">
              <?php if (!empty($listing['proof_image'])): ?>
                <img src="<?= htmlspecialchars($listing['proof_image']) ?>" alt="<?= htmlspecialchars($listing['name']) ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
              <?php else: ?>
                <div class="w-full h-full flex items-center justify-center bg-gray-200 text-gray-500 text-sm">No Image</div>
              <?php endif; ?>

              <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

              <span class="absolute top-4 left-4 bg-green-500 text-white text-xs font-semibold px-3 py-1 rounded-full shadow-lg">
                <i class="fa-solid fa-check-circle mr-1"></i> Verified
              </span>
            </div>

            <div class="p-6">
              <!-- Blur content for non-logged-in users -->
              <div class="<?= !$user_logged_in ? 'blur-content' : '' ?>">
                <div class="flex items-center gap-2 mb-3">
                  <span class="border border-blue-500 text-blue-500 text-xs px-2 py-1 rounded-full">
                    <i class="fa-solid fa-tag mr-1 text-xs"></i> <?= htmlspecialchars($listing['type']) ?>
                  </span>
                  <span class="text-xs text-gray-500"><?= htmlspecialchars($listing['category']) ?></span>
                </div>

                <h3 class="text-xl font-bold text-gray-900 mb-4 group-hover:text-blue-600 transition-colors duration-300">
                  <?= htmlspecialchars($listing['name']) ?>
                </h3>

                <div class="flex items-center justify-between bg-green-50 rounded-lg p-3 mb-4 border border-green-200">
                  <div class="flex items-center gap-2 text-sm text-gray-600">
                    <i class="fa-solid fa-chart-line text-green-600"></i>
                    <span>Monthly Revenue</span>
                  </div>
                  <span class="font-bold text-green-600">
                    <i class="fa-solid fa-dollar-sign text-xs"></i> <?= htmlspecialchars($listing['monthly_revenue']) ?>/mo
                  </span>
                </div>

                <div class="mb-5">
                  <div class="flex items-baseline gap-2">
                    <i class="fa-solid fa-dollar-sign text-blue-600 text-lg"></i>
                    <span class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($listing['asking_price']) ?></span>
                  </div>
                  <p class="text-xs text-gray-500 mt-1">Asking Price</p>
                </div>
              </div>

              <?php if ($current_user_id && $listing['user_id'] == $current_user_id): ?>
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-center mb-3">
                <p class="text-sm text-blue-700 font-medium">
                  <i class="fa-solid fa-user-check mr-1"></i> Your Listing
                </p>
              </div>
              <a href="./index.php?p=listingDetail&id=<?= $listing['id'] ?>" class="block w-full bg-gradient-to-r from-blue-500 to-purple-500 text-white text-sm font-semibold py-2 text-center rounded-md hover:opacity-90 transition-opacity">
                <i class="fa-solid fa-eye mr-2"></i> View Details
              </a>
              <?php elseif ($user_logged_in): ?>
              <div class="flex gap-2 mb-2">
                <!-- Buy Now Button -->
                <button onclick="showBuyNowPopup(<?= $listing['id'] ?>, '<?= htmlspecialchars($listing['name']) ?>', '<?= htmlspecialchars($listing['asking_price']) ?>', <?= $listing['user_id'] ?>)" class="flex-1 bg-gradient-to-r from-blue-500 to-purple-500 text-white text-sm font-bold py-2 rounded-md hover:opacity-90 transition-opacity shadow-md">
                  <i class="fa-solid fa-shopping-cart mr-1"></i> Buy Now
                </button>
                
                <!-- Make Offer Button -->
                <button onclick="showMakeOfferPopup(<?= $listing['id'] ?>, '<?= htmlspecialchars($listing['name']) ?>', '<?= htmlspecialchars($listing['asking_price']) ?>', <?= $listing['user_id'] ?>)" class="flex-1 bg-gradient-to-r from-green-500 to-green-600 text-white text-sm font-semibold py-2 rounded-md hover:opacity-90 transition-opacity">
                  <i class="fa-solid fa-handshake mr-1"></i> Make Offer
                </button>
              </div>
              
              <!-- View Details Button -->
              <a href="./index.php?p=listingDetail&id=<?= $listing['id'] ?>" class="block w-full text-center border border-gray-300 text-gray-600 text-xs font-medium py-2 rounded-md hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-eye mr-1"></i> View Details
              </a>
              <?php else: ?>
              <!-- Login Required Message for Non-Logged In Users -->
              <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-center mb-3">
                <p class="text-sm text-yellow-700 font-medium">
                  <i class="fa-solid fa-lock mr-1"></i> Login required to view details and make offers
                </p>
              </div>
              
              <div class="flex gap-2 mb-2">
                <!-- Login to Buy Button -->
                <a href="<?= url('index.php?p=login') ?>" class="flex-1 bg-gradient-to-r from-blue-500 to-purple-500 text-white text-sm font-bold py-2 rounded-md hover:opacity-90 transition-opacity shadow-md text-center">
                  <i class="fa-solid fa-right-to-bracket mr-1"></i> Login to Buy
                </a>
                
                <!-- Login to Offer Button -->
                <a href="<?= url('index.php?p=login') ?>" class="flex-1 bg-gradient-to-r from-green-500 to-green-600 text-white text-sm font-semibold py-2 rounded-md hover:opacity-90 transition-opacity text-center">
                  <i class="fa-solid fa-right-to-bracket mr-1"></i> Login to Offer
                </a>
              </div>
              
              <!-- Login to View Details Button -->
              <a href="<?= url('index.php?p=login') ?>" class="block w-full text-center border border-gray-300 text-gray-600 text-xs font-medium py-2 rounded-md hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-right-to-bracket mr-1"></i> Login to View Details
              </a>
              <?php endif; ?>
            </div>



          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

      <!-- Pagination -->
      <div class="mt-12 flex justify-center">
        <?php 
        $extraParams = ['p' => 'listing'];
        if (!empty($_GET['category']) && $_GET['category'] != 'All') $extraParams['category'] = $_GET['category'];
        if (!empty($_GET['search'])) $extraParams['search'] = $_GET['search'];
        if (!empty($_GET['escrow'])) $extraParams['escrow'] = $_GET['escrow'];
        
        echo renderPagination($pagination, url('index.php'), $extraParams, 'pg'); 
        ?>
      </div>
    </div>
  </div>
</section>

<!-- Buy Now Popup -->
<div id="buyNowPopup" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all">
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
            <i class="fas fa-shopping-cart text-blue-600 text-xl"></i>
          </div>
          <div>
            <h3 class="text-xl font-bold text-gray-900">Buy Now</h3>
            <p class="text-sm text-gray-500">Complete your purchase</p>
          </div>
        </div>
        <button onclick="closeBuyNowPopup()" class="text-gray-400 hover:text-gray-600 transition-colors">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <!-- Listing Info -->
      <div class="bg-gray-50 rounded-lg p-4 mb-6">
        <h4 class="font-semibold text-gray-900 mb-2" id="buyNowListingName">Loading...</h4>
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-600">Purchase Price:</span>
          <span class="text-2xl font-bold text-blue-600" id="buyNowPrice">$0</span>
        </div>
      </div>

      <!-- Purchase Details -->
      <div class="space-y-3 mb-6">
        <div class="flex items-center gap-3 text-sm">
          <i class="fas fa-shield-alt text-green-500"></i>
          <span class="text-gray-700">Secure escrow protection</span>
        </div>
        <div class="flex items-center gap-3 text-sm">
          <i class="fas fa-check-circle text-green-500"></i>
          <span class="text-gray-700">Verified listing with documentation</span>
        </div>
        <div class="flex items-center gap-3 text-sm">
          <i class="fas fa-headset text-green-500"></i>
          <span class="text-gray-700">24/7 transfer support included</span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-col gap-3">
        <div class="flex gap-3">
          <button onclick="closeBuyNowPopup()" class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
            Cancel
          </button>
          <button onclick="proceedToBuy()" class="flex-1 px-4 py-3 bg-gradient-to-r from-blue-500 to-purple-500 text-white rounded-lg hover:opacity-90 transition-opacity font-semibold">
            Proceed to Payment
          </button>
        </div>
        <button onclick="contactSellerFromPopup()" class="w-full px-4 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg hover:opacity-90 transition-opacity font-semibold flex items-center justify-center gap-2">
          <i class="fas fa-comments"></i>
          Contact Seller
        </button>
      </div>
    </div>
  </div>
  <input type="hidden" id="buyNowListingId" value="">
  <input type="hidden" id="buyNowSellerId" value="">
</div>

<!-- Make Offer Popup -->
<div id="makeOfferPopup" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all">
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
            <i class="fas fa-handshake text-green-600 text-xl"></i>
          </div>
          <div>
            <h3 class="text-xl font-bold text-gray-900">Make an Offer</h3>
            <p class="text-sm text-gray-500">Negotiate the best price</p>
          </div>
        </div>
        <button onclick="closeMakeOfferPopup()" class="text-gray-400 hover:text-gray-600 transition-colors">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <!-- Listing Info -->
      <div class="bg-gray-50 rounded-lg p-4 mb-6">
        <h4 class="font-semibold text-gray-900 mb-2" id="offerListingName">Loading...</h4>
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-sm text-gray-600">Asking Price:</span>
            <span class="text-lg font-bold text-gray-900" id="offerAskingPrice">$0</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-sm text-blue-600">Minimum Offer:</span>
            <span class="text-sm font-semibold text-blue-600" id="offerMinimumAmount">$0</span>
          </div>
        </div>
      </div>

      <!-- Offer Form -->
      <form id="offerForm" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Your Offer Amount ($) <span class="text-red-500">*</span></label>
          <input type="number" id="offerAmount" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition-colors" placeholder="Enter your offer amount" min="1" step="0.01">
          <div id="offerAmountError" class="text-red-500 text-sm mt-1 hidden"></div>
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mt-2">
            <p class="text-xs text-blue-800">
              <i class="fas fa-info-circle"></i> 
              <strong>System Requirement:</strong> Offers must be at least <span id="offerMinPercentage">70</span>% of asking price
            </p>
          </div>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Message (Optional)</label>
          <textarea id="offerMessage" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none resize-none transition-colors" placeholder="Add a message to your offer (optional)..."></textarea>
          <div class="text-xs text-gray-500 mt-1">
            <span id="messageCharCount">0</span> characters
          </div>
        </div>

        <!-- Offer Tips -->
        <div class="bg-blue-50 rounded-lg p-3">
          <div class="flex items-start gap-2">
            <i class="fas fa-lightbulb text-blue-500 mt-0.5"></i>
            <div class="text-sm text-blue-700">
              <p class="font-medium mb-1">Offer Tips:</p>
              <ul class="text-xs space-y-1">
                <li>• Be realistic with your offer amount</li>
                <li>• Explain your interest and plans</li>
                <li>• Highlight your experience or qualifications</li>
              </ul>
            </div>
          </div>
        </div>
      </form>

      <!-- Action Buttons -->
      <div class="flex flex-col gap-3 mt-6">
        <div class="flex gap-3">
          <button onclick="closeMakeOfferPopup()" class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
            Cancel
          </button>
          <button onclick="submitOffer()" class="flex-1 px-4 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg hover:opacity-90 transition-opacity font-semibold">
            Submit Offer
          </button>
        </div>
        <button onclick="contactSellerFromPopup()" class="w-full px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg hover:opacity-90 transition-opacity font-semibold flex items-center justify-center gap-2">
          <i class="fas fa-comments"></i>
          Contact Seller
        </button>
      </div>
    </div>
  </div>
  <input type="hidden" id="offerListingId" value="">
  <input type="hidden" id="offerSellerId" value="">
</div>

<script>
// Buy Now Popup Functions
function showBuyNowPopup(listingId, listingName, askingPrice, sellerId) {
  document.getElementById('buyNowListingName').textContent = listingName;
  document.getElementById('buyNowPrice').textContent = '$' + askingPrice;
  document.getElementById('buyNowListingId').value = listingId;
  document.getElementById('buyNowSellerId').value = sellerId;
  document.getElementById('buyNowPopup').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeBuyNowPopup() {
  document.getElementById('buyNowPopup').classList.add('hidden');
  document.body.style.overflow = 'auto';
}

function proceedToBuy() {
  const listingId = document.getElementById('buyNowListingId').value;
  window.location.href = './index.php?p=payment&id=' + listingId;
}

function contactSellerFromPopup() {
  // Get listing ID and seller ID from either popup
  let listingId = document.getElementById('buyNowListingId').value;
  let sellerId = document.getElementById('buyNowSellerId').value;
  
  if (!listingId) {
    listingId = document.getElementById('offerListingId').value;
    sellerId = document.getElementById('offerSellerId').value;
  }
  
  // Debug log
  console.log('Contact Seller - Listing ID:', listingId, 'Seller ID:', sellerId);
  
  // Validate IDs
  if (!listingId || !sellerId) {
    alert('Error: Missing listing or seller information');
    return;
  }
  
  // Close any open popups
  closeBuyNowPopup();
  closeMakeOfferPopup();
  
  // Redirect to messages page with seller ID and listing ID
  window.location.href = './index.php?p=dashboard&page=message&seller_id=' + sellerId + '&listing_id=' + listingId;
}

// Make Offer Popup Functions
function showMakeOfferPopup(listingId, listingName, askingPrice, sellerId) {
  document.getElementById('offerListingName').textContent = listingName;
  document.getElementById('offerAskingPrice').textContent = '$' + parseFloat(askingPrice).toLocaleString();
  document.getElementById('offerListingId').value = listingId;
  document.getElementById('offerSellerId').value = sellerId;
  
  // Get minimum offer percentage from server (default 70%)
  fetch('/marketplace/api/get_min_offer_percentage.php')
    .then(response => response.json())
    .then(data => {
      const minPercentage = data.percentage || 70;
      const minAmount = (parseFloat(askingPrice) * minPercentage) / 100;
      
      document.getElementById('offerMinPercentage').textContent = minPercentage;
      document.getElementById('offerMinimumAmount').textContent = '$' + minAmount.toLocaleString();
      document.getElementById('offerAmount').min = minAmount;
      
      // Store for validation
      window.currentOfferData = {
        askingPrice: parseFloat(askingPrice),
        minPercentage: minPercentage,
        minAmount: minAmount
      };
    })
    .catch(error => {
      console.error('Error fetching minimum offer percentage:', error);
      // Fallback to 70%
      const minPercentage = 70;
      const minAmount = (parseFloat(askingPrice) * minPercentage) / 100;
      
      document.getElementById('offerMinPercentage').textContent = minPercentage;
      document.getElementById('offerMinimumAmount').textContent = '$' + minAmount.toLocaleString();
      document.getElementById('offerAmount').min = minAmount;
      
      window.currentOfferData = {
        askingPrice: parseFloat(askingPrice),
        minPercentage: minPercentage,
        minAmount: minAmount
      };
    });
  
  document.getElementById('makeOfferPopup').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
  
  // Reset form validation states
  resetOfferFormValidation();
  
  // Add real-time validation
  setupOfferFormValidation();
}

function closeMakeOfferPopup() {
  document.getElementById('makeOfferPopup').classList.add('hidden');
  document.getElementById('offerForm').reset();
  document.body.style.overflow = 'auto';
}

function submitOffer() {
  const listingId = document.getElementById('offerListingId').value;
  const offerAmount = parseFloat(document.getElementById('offerAmount').value);
  const offerMessage = document.getElementById('offerMessage').value.trim();
  const submitButton = document.querySelector('#makeOfferPopup button[onclick="submitOffer()"]');
  
  // Validate offer amount
  if (!offerAmount || offerAmount <= 0) {
    showOfferNotification('Please enter a valid offer amount', 'error');
    document.getElementById('offerAmount').focus();
    return;
  }
  
  // Check minimum offer requirement
  if (window.currentOfferData && offerAmount < window.currentOfferData.minAmount) {
    showOfferNotification(`Your offer must be at least ${window.currentOfferData.minPercentage}% of the asking price ($${window.currentOfferData.minAmount.toLocaleString()})`, 'error');
    document.getElementById('offerAmount').focus();
    return;
  }
  
  // Message is optional - no validation needed
  
  // Show loading state
  submitButton.disabled = true;
  submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
  
  // Create form data
  const formData = new FormData();
  formData.append('listing_id', listingId);
  formData.append('amount', offerAmount);
  formData.append('message', offerMessage);
  
  // Submit offer via AJAX
  fetch('index.php?p=submit_offer_ajax', {
    method: 'POST',
    body: formData,
    credentials: 'same-origin'
  })
  .then(response => {
    console.log('Response status:', response.status);
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    return response.text().then(text => {
      console.log('Raw response:', text);
      try {
        return JSON.parse(text);
      } catch (e) {
        console.error('JSON parse error:', e);
        console.error('Response text:', text);
        throw new Error('Invalid JSON response from server');
      }
    });
  })
  .then(data => {
    if (data.success) {
      showOfferNotification('Your offer has been submitted successfully!', 'success');
      closeMakeOfferPopup();
    } else {
      showOfferNotification(data.message || 'Failed to submit offer. Please try again.', 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    console.error('Error details:', error.message);
    showOfferNotification('Network error: ' + error.message + '. Please try again.', 'error');
  })
  .finally(() => {
    // Reset button state
    submitButton.disabled = false;
    submitButton.innerHTML = 'Submit Offer';
  });
}

// Show notification function
function showOfferNotification(message, type) {
  // Remove existing notifications
  const existingNotification = document.getElementById('offerNotification');
  if (existingNotification) {
    existingNotification.remove();
  }
  
  // Create notification
  const notification = document.createElement('div');
  notification.id = 'offerNotification';
  notification.className = `fixed top-4 right-4 z-[60] px-6 py-4 rounded-lg shadow-lg transform transition-all duration-300 ${
    type === 'success' 
      ? 'bg-green-500 text-white' 
      : 'bg-red-500 text-white'
  }`;
  
  notification.innerHTML = `
    <div class="flex items-center gap-3">
      <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
      <span>${message}</span>
      <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-white hover:text-gray-200">
        <i class="fas fa-times"></i>
      </button>
    </div>
  `;
  
  document.body.appendChild(notification);
  
  // Auto remove after 5 seconds
  setTimeout(() => {
    if (notification && notification.parentNode) {
      notification.remove();
    }
  }, 5000);
}

// Offer form validation functions
function resetOfferFormValidation() {
  const amountField = document.getElementById('offerAmount');
  const messageField = document.getElementById('offerMessage');
  const charCount = document.getElementById('messageCharCount');
  
  // Reset field styles
  amountField.classList.remove('border-red-500', 'focus:border-red-500');
  messageField.classList.remove('border-red-500', 'focus:border-red-500');
  
  // Reset character count
  if (charCount) {
    charCount.textContent = '0';
    charCount.parentElement.classList.remove('text-red-500', 'text-green-500');
  }
}

function setupOfferFormValidation() {
  const messageField = document.getElementById('offerMessage');
  const charCount = document.getElementById('messageCharCount');
  
  // Real-time character counting and validation
  messageField.addEventListener('input', function() {
    const length = this.value.trim().length;
    charCount.textContent = length;
    
    const charCountContainer = charCount.parentElement;
    
    if (length === 0) {
      charCountContainer.classList.remove('text-red-500', 'text-green-500');
      charCountContainer.classList.add('text-gray-500');
      this.classList.remove('border-red-500', 'border-green-500');
    } else if (length < 10) {
      charCountContainer.classList.remove('text-gray-500', 'text-green-500');
      charCountContainer.classList.add('text-red-500');
      this.classList.remove('border-green-500');
      this.classList.add('border-red-500');
    } else {
      charCountContainer.classList.remove('text-gray-500', 'text-red-500');
      charCountContainer.classList.add('text-green-500');
      this.classList.remove('border-red-500');
      this.classList.add('border-green-500');
    }
  });
  
  // Amount field validation
  const amountField = document.getElementById('offerAmount');
  amountField.addEventListener('input', function() {
    const value = parseFloat(this.value);
    if (this.value && (isNaN(value) || value <= 0)) {
      this.classList.add('border-red-500');
    } else {
      this.classList.remove('border-red-500');
    }
  });
}

// Close popups when clicking outside
document.addEventListener('click', function(e) {
  if (e.target.id === 'buyNowPopup') {
    closeBuyNowPopup();
  }
  if (e.target.id === 'makeOfferPopup') {
    closeMakeOfferPopup();
  }
});

// Close popups with Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeBuyNowPopup();
    closeMakeOfferPopup();
  }
});

// WORKING POLLING SYSTEM for listing page
console.log('🚀 LISTING PAGE: Starting real-time polling system');

const pollingScript = document.createElement('script');
pollingScript.src = '<?= BASE ?>js/polling.js';

pollingScript.onload = function() {
  console.log('✅ Polling system loaded for listing page');
  
  if (typeof startPolling !== 'undefined') {
    console.log('🎯 Starting listing polling...');
    
    startPolling({
      listings: (newListings) => {
        console.log('🔥 NEW LISTINGS on listing page!');
        console.log('📊 Count:', newListings.length);
        
        if (newListings.length > 0) {
          // Simple notification
          const notification = document.createElement('div');
          notification.style.cssText = `
            position: fixed; top: 20px; right: 20px; z-index: 9999;
            background: #17a2b8; color: white; padding: 15px 20px;
            border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            font-family: Arial, sans-serif; font-weight: bold;
          `;
          notification.innerHTML = `📋 ${newListings.length} new listing(s)! Refreshing...`;
          document.body.appendChild(notification);
          
          setTimeout(() => location.reload(), 2000);
        }
      }
    });
    
    console.log('✅ Listing page polling active');
  } else {
    console.error('❌ startPolling not available');
  }
};

pollingScript.onerror = function() {
  console.error('❌ Polling.js failed to load');
  
  // Fallback
  setInterval(function() {
    fetch('<?= BASE ?>api/check_new_listings.php?since=<?= date('Y-m-d H:i:s', strtotime('-1 minute')) ?>')
      .then(r => r.json())
      .then(d => {
        if (d.has_new) {
          console.log('🔄 Fallback: New listings detected, reloading...');
          location.reload();
        }
      })
      .catch(e => console.error('❌ Fallback error:', e));
  }, 15000);
};

document.head.appendChild(pollingScript);

// Offer form validation functions
function resetOfferFormValidation() {
  const amountInput = document.getElementById('offerAmount');
  const errorDiv = document.getElementById('offerAmountError');
  
  if (amountInput) {
    amountInput.style.borderColor = '';
    amountInput.style.backgroundColor = '';
  }
  
  if (errorDiv) {
    errorDiv.classList.add('hidden');
    errorDiv.textContent = '';
  }
}

function setupOfferFormValidation() {
  const amountInput = document.getElementById('offerAmount');
  
  if (amountInput) {
    amountInput.addEventListener('input', function(e) {
      const amount = parseFloat(e.target.value) || 0;
      const errorDiv = document.getElementById('offerAmountError');
      
      // Clear previous styling
      e.target.style.borderColor = '';
      e.target.style.backgroundColor = '';
      errorDiv.classList.add('hidden');
      
      if (amount > 0 && window.currentOfferData) {
        if (amount < window.currentOfferData.minAmount) {
          e.target.style.borderColor = '#ef4444';
          e.target.style.backgroundColor = '#fef2f2';
          errorDiv.textContent = `Offer must be at least ${window.currentOfferData.minPercentage}% of asking price ($${window.currentOfferData.minAmount.toLocaleString()})`;
          errorDiv.classList.remove('hidden');
        } else {
          e.target.style.borderColor = '#10b981';
          e.target.style.backgroundColor = '#f0fdf4';
        }
      }
    });
  }
}

</script>