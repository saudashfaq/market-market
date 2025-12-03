<?php
require_once __DIR__ . '/../config.php';


$pdo = db();

// Check if user is logged in
$user_logged_in = isset($_SESSION['user']); // ya is_logged_in() agar helper include hai

// ✅ Only show listings that are approved AND not expired
if ($user_logged_in) {
    $user_id = $_SESSION['user']['id'];
    $stmt = $pdo->prepare("
      SELECT l.*, 
             (SELECT file_path FROM listing_proofs WHERE listing_id = l.id LIMIT 1) AS proof_image,
             (SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND listing_id = l.id) AS in_wishlist
      FROM listings l
      WHERE l.status IN ('approved')
      AND (l.expires_at IS NULL OR l.expires_at > NOW())
      ORDER BY GREATEST(l.created_at, COALESCE(l.updated_at, l.created_at)) DESC
      LIMIT 6
    ");
    $stmt->execute([$user_id]);
} else {
    $stmt = $pdo->query("
      SELECT l.*, 
             (SELECT file_path FROM listing_proofs WHERE listing_id = l.id LIMIT 1) AS proof_image,
             0 AS in_wishlist
      FROM listings l
      WHERE l.status IN ('approved')
      AND (l.expires_at IS NULL OR l.expires_at > NOW())
      ORDER BY GREATEST(l.created_at, COALESCE(l.updated_at, l.created_at)) DESC
      LIMIT 6
    ");
}
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Log what we're fetching (remove in production)
if (isset($_GET['debug'])) {
    echo "<pre style='background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;'>";
    echo "Fetched " . count($listings) . " listings (ordered by latest timestamp):\n";
    foreach ($listings as $l) {
        $latest = max($l['created_at'], $l['updated_at'] ?? $l['created_at']);
        echo "ID: {$l['id']} - {$l['name']} - Status: {$l['status']}\n";
        echo "  Created: {$l['created_at']}, Updated: {$l['updated_at']}, Latest: {$latest}\n";
    }
    echo "</pre>";
}
?>
<section class="relative text-gray-800 body-font bg-gradient-to-b from-white via-gray-50 to-gray-100 overflow-hidden">
  <!-- Background Decorative Elements -->
  <div class="absolute inset-0">
    <div class="absolute -top-20 -left-20 w-64 h-64 md:w-96 md:h-96 bg-blue-200 rounded-full mix-blend-multiply filter blur-3xl opacity-40 animate-pulse"></div>
    <div class="absolute -bottom-20 -right-20 w-64 h-64 md:w-96 md:h-96 bg-purple-200 rounded-full mix-blend-multiply filter blur-3xl opacity-40 animate-pulse"></div>
  </div>

  <div class="container mx-auto relative z-10 flex flex-col px-6 py-24 md:py-36 justify-center items-center text-center">

    <!-- Heading - Larger on Mobile -->
    <div class="max-w-4xl">
      <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-extrabold text-gray-900 leading-tight tracking-tight drop-shadow-lg">
        Buy & Sell Digital Assets
        <span class="block text-transparent bg-clip-text bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-700 animate-gradient mt-2">
          With Confidence
        </span>
      </h1>
      <p class="mt-6 text-base sm:text-lg md:text-xl text-gray-600 leading-relaxed max-w-2xl mx-auto">
        The trusted marketplace for websites, YouTube channels, SaaS products, and digital businesses.
        <span class="font-medium text-gray-700">Verified listings, secure transactions,</span> and expert support at your fingertips.
      </p>
    </div>

    <!-- Search Bar / Action Buttons -->
    <div class="w-full max-w-3xl mt-10 px-4 sm:px-0">
      <?php if ($user_logged_in): ?>
        <!-- Search Bar for Logged In Users - Professional Mobile Design -->
        <!-- Mobile: Stacked Design -->
        <div class="sm:hidden space-y-3">
          <form id="heroSearchForm" action="<?= url('index.php?p=listing') ?>" method="GET" class="space-y-3">
            <input type="hidden" name="p" value="listing">
            
            <!-- Category Dropdown - Full Width Mobile -->
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <i class="fas fa-layer-group text-gray-400"></i>
              </div>
              <select name="category" class="w-full pl-12 pr-4 py-4 text-base border-2 border-gray-200 rounded-xl text-gray-700 bg-white focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all shadow-sm appearance-none">
                <option value="">All Categories</option>
                <option value="website">🌐 Websites</option>
                <option value="youtube">📺 YouTube Channels</option>
              </select>
              <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                <i class="fas fa-chevron-down text-gray-400"></i>
              </div>
            </div>
            
            <!-- Search Input - Full Width Mobile -->
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <i class="fas fa-search text-gray-400"></i>
              </div>
              <input
                type="text"
                name="search"
                id="heroSearchInput"
                placeholder="Search by name, category, revenue..."
                class="w-full pl-12 pr-4 py-4 text-base border-2 border-gray-200 rounded-xl bg-white focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-gray-700 placeholder-gray-400 transition-all shadow-sm" />
            </div>
            
            <!-- Search Button - Full Width Mobile -->
            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-4 text-base font-semibold rounded-xl hover:from-blue-700 hover:to-purple-700 transition-all duration-300 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2">
              <i class="fas fa-search"></i>
              <span>Search Listings</span>
            </button>
          </form>
        </div>
        
        <!-- Desktop: Horizontal Design -->
        <form id="heroSearchFormDesktop" action="<?= url('index.php?p=listing') ?>" method="GET" class="hidden sm:flex bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl overflow-hidden ring-1 ring-gray-200 hover:ring-blue-400 transition-all duration-300">
          <input type="hidden" name="p" value="listing">
          
          <select name="category" class="p-3 border-r border-gray-200 text-gray-600 bg-transparent focus:outline-none focus:text-blue-600">
            <option value="">All Categories</option>
            <option value="website">Websites</option>
            <option value="youtube">YouTube</option>
          </select>
          
          <input
            type="text"
            name="search"
            placeholder="Search by name, category, revenue..."
            class="flex-grow p-3 bg-transparent focus:outline-none text-gray-700 placeholder-gray-400" />
            
          <button type="submit" class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 font-semibold hover:opacity-90 transition-all duration-300 flex items-center justify-center">
            <i class="fas fa-search mr-2"></i>
            Search
          </button>
        </form>
        
        <!-- Error Message -->
        <div id="searchError" class="hidden mt-3 p-3 bg-red-100 border border-red-300 text-red-700 rounded-lg text-sm flex items-center">
          <i class="fas fa-exclamation-circle mr-2"></i>
          <span>Please enter a search term to find listings</span>
        </div>
      <?php else: ?>
        <!-- Action Buttons for Non-Logged In Users -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <!-- Browse Listings Button -->
          <a href="<?= url('index.php?p=listing') ?>" class="flex-1 sm:flex-none bg-white/80 backdrop-blur-lg border-2 border-blue-500 text-blue-600 px-8 py-4 text-lg font-semibold rounded-xl hover:bg-blue-50 transition-all duration-300 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2 min-w-[200px]">
            <i class="fas fa-search"></i>
            <span>Browse Listings</span>
          </a>
          
          <!-- Start Selling Button -->
          <a href="<?= url('index.php?p=login') ?>" class="flex-1 sm:flex-none bg-gradient-to-r from-blue-600 to-purple-600 text-white px-8 py-4 text-lg font-semibold rounded-xl hover:from-blue-700 hover:to-purple-700 transition-all duration-300 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2 min-w-[200px]">
            <i class="fas fa-plus-circle"></i>
            <span>Start Selling</span>
          </a>
        </div>
        
        <!-- Login Message -->
        <div class="mt-4 text-center">
          <p class="text-gray-600 text-sm">
            <i class="fas fa-info-circle mr-1"></i>
            Please <a href="<?= url('index.php?p=login') ?>" class="text-blue-600 hover:text-blue-800 font-medium underline">login</a> to access all features
          </p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Stats Section -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 mt-14 sm:mt-16 text-center">
      <div class="p-4 rounded-xl bg-white/70 shadow-md backdrop-blur-md hover:shadow-lg transition-all">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900">2,500+</h2>
        <p class="text-gray-600 font-medium mt-1 text-sm sm:text-base">Active Listings</p>
      </div>
      <div class="p-4 rounded-xl bg-white/70 shadow-md backdrop-blur-md hover:shadow-lg transition-all">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900">$45M+</h2>
        <p class="text-gray-600 font-medium mt-1 text-sm sm:text-base">Total Revenue</p>
      </div>
      <div class="p-4 rounded-xl bg-white/70 shadow-md backdrop-blur-md hover:shadow-lg transition-all">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900">850+</h2>
        <p class="text-gray-600 font-medium mt-1 text-sm sm:text-base">Successful Sales</p>
      </div>
      <div class="p-4 rounded-xl bg-white/70 shadow-md backdrop-blur-md hover:shadow-lg transition-all">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900">99.2%</h2>
        <p class="text-gray-600 font-medium mt-1 text-sm sm:text-base">Success Rate</p>
      </div>
    </div>
  </div>

  <!-- Custom Animation -->
  <style>
    @keyframes gradient {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }

    .animate-gradient {
      background-size: 200% 200%;
      animation: gradient 5s ease infinite;
    }
  </style>
</section>

<section class="bg-gray-50 py-20 relative">
  <!-- Animated Background Blobs -->
  <div class="absolute inset-0 overflow-hidden pointer-events-none">
    <div class="absolute -top-32 -left-32 w-96 h-96 bg-blue-500/10 rounded-full mix-blend-multiply filter blur-3xl animate-pulse"></div>
    <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-purple-500/10 rounded-full mix-blend-multiply filter blur-3xl animate-pulse"></div>
  </div>

  <div class="container mx-auto px-6 relative z-10">
    <!-- Header -->
    <div class="text-center mb-14 animate-fade-in">
      <h2 class="text-4xl sm:text-5xl font-extrabold text-gray-900 mb-4">
        Featured
        <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-purple-600">Listings</span>
      </h2>
      <p class="mt-3 text-lg text-gray-500 max-w-2xl mx-auto">
        Hand-picked verified digital assets with proven revenue streams and growth potential.
      </p>
      <div class="mt-6 h-1 w-24 mx-auto bg-gradient-to-r from-blue-500 to-purple-500 rounded-full"></div>
    </div>

    <!-- Listings Grid -->
    <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
      <?php if (empty($listings)): ?>
        <div class="col-span-full text-center text-gray-500">No listings available.</div>
      <?php else: ?>
        <?php foreach ($listings as $listing): ?>
          <div class="group relative rounded-2xl border border-gray-200 shadow-md hover:shadow-lg transition-all duration-500 overflow-hidden hover:-translate-y-2 animate-fade-in
          <?php echo $user_logged_in ? 'bg-white' : 'bg-white/90 filter blur-sm pointer-events-none'; ?>
">

            <!-- Image -->
            <div class="relative h-56 overflow-hidden bg-gray-100">
              <?php if (!empty($listing['proof_image'])): ?>
                <img src="<?= htmlspecialchars($listing['proof_image']) ?>" 
                     alt="<?= htmlspecialchars($listing['name']) ?>" 
                     class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                <div class="w-full h-full flex items-center justify-center bg-gray-200 text-gray-500 text-sm" style="display: none;">
                  <i class="fas fa-image mr-2"></i>Image Not Available
                </div>
              <?php else: ?>
                <div class="w-full h-full flex items-center justify-center bg-gray-200 text-gray-500 text-sm">
                  <i class="fas fa-image mr-2"></i>No Image
                </div>
              <?php endif; ?>

              <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

              <!-- Status Badge -->
              <?php if ($listing['status'] === 'approved'): ?>
                <span class="absolute top-4 left-4 bg-green-500 text-white text-xs font-semibold px-3 py-1 rounded-full shadow-lg">
                  <i class="fa-solid fa-check-circle mr-1"></i> Verified
                </span>
              <?php elseif ($listing['status'] === 'pending'): ?>
                <span class="absolute top-4 left-4 bg-yellow-500 text-white text-xs font-semibold px-3 py-1 rounded-full shadow-lg">
                  <i class="fa-solid fa-clock mr-1"></i> Pending
                </span>
              <?php endif; ?>
              
              <!-- Wishlist Button -->
              <?php if ($user_logged_in): ?>
                <button onclick="toggleWishlist(<?= $listing['id'] ?>, this)" 
                        class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/90 backdrop-blur-sm shadow-lg hover:bg-white transition-all duration-300 flex items-center justify-center group"
                        data-listing-id="<?= $listing['id'] ?>"
                        data-in-wishlist="<?= $listing['in_wishlist'] ? 'true' : 'false' ?>">
                  <i class="<?= $listing['in_wishlist'] ? 'fas fa-bookmark text-blue-600' : 'far fa-bookmark text-gray-600' ?> text-lg group-hover:scale-110 transition-transform"></i>
                </button>
              <?php endif; ?>
            </div>

            <div class="p-6">
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

              <?php if ($user_logged_in): ?>
              <?php if ($listing['user_id'] != $user_id): ?>
              <div class="flex gap-2">
                <!-- Buy Now Button - Opens Chat -->
                <a href="index.php?p=dashboard&page=message&seller_id=<?= $listing['user_id'] ?>&listing_id=<?= $listing['id'] ?>&action=buy" 
                   class="flex-1 bg-gradient-to-r from-blue-500 to-purple-500 text-white text-sm font-bold py-3 rounded-md hover:opacity-90 transition-opacity shadow-md text-center flex items-center justify-center">
                  <i class="fa-solid fa-shopping-cart mr-2"></i> Buy Now
                </a>
                
                <!-- Make Offer Button - Opens Chat -->
                <a href="index.php?p=dashboard&page=message&seller_id=<?= $listing['user_id'] ?>&listing_id=<?= $listing['id'] ?>&action=offer" 
                   class="flex-1 bg-gradient-to-r from-green-500 to-green-600 text-white text-sm font-semibold py-3 rounded-md hover:opacity-90 transition-opacity text-center flex items-center justify-center">
                  <i class="fa-solid fa-handshake mr-2"></i> Make Offer
                </a>
              </div>
              <?php else: ?>
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-center">
                <p class="text-sm text-blue-700 font-medium">
                  <i class="fa-solid fa-user-check mr-1"></i> Your Listing
                </p>
              </div>
              <?php endif; ?>
              
              <!-- View Details Button - Less Prominent -->
              <div class="mt-2">
                <a href="./index.php?p=listingDetail&id=<?= $listing['id'] ?>" class="block w-full text-center border border-gray-300 text-gray-600 text-xs font-medium py-2 rounded-md hover:bg-gray-50 transition-colors">
                  <i class="fa-solid fa-eye mr-1"></i> View Details
                </a>
              </div>
              <?php endif; ?>
            </div>

            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 to-purple-500 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($user_logged_in): ?>
    <!-- View All Button -->
    <div class="mt-14 text-center animate-fade-in">
      <a href="./index.php?p=listing" class="group border border-blue-600 text-blue-600 hover:bg-blue-600 hover:text-white font-semibold px-8 py-3 rounded-md shadow-sm transition">
        View All Listings
        <i class="fa-solid fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
      </a>
    </div>
    <?php endif; ?>
  </div>
</section>
  <section class="py-20 bg-gray-50">
    <div class="container mx-auto px-6">
      <!-- Header -->
      <div class="text-center mb-16">
        <h2 class="text-4xl sm:text-5xl font-extrabold text-gray-900 mb-4">
          Why Choose 
          <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-purple-600">
            AssetMarket
          </span>
        </h2>
        <p class="text-lg text-gray-600 max-w-2xl mx-auto">
          Built with trust, security, and transparency at the core of every transaction
        </p>
      </div>

      <!-- Cards Grid -->
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        
        <!-- Card 1 -->
        <div class="group bg-white border border-gray-200 rounded-xl p-6 shadow-md hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
          <div class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 mb-4 group-hover:scale-110 transition-transform">
            <i class="fas fa-check-circle text-green-600 text-xl"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Verified Listings</h3>
          <p class="text-sm text-gray-600 leading-relaxed">
            Every listing is thoroughly verified with financial documentation and traffic proof.
          </p>
        </div>

        <!-- Card 2 -->
        <div class="group bg-white border border-gray-200 rounded-xl p-6 shadow-md hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
          <div class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 mb-4 group-hover:scale-110 transition-transform">
            <i class="fas fa-shield-alt text-blue-600 text-xl"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Secure Escrow</h3>
          <p class="text-sm text-gray-600 leading-relaxed">
            Your funds are held securely until the transfer is completed and verified.
          </p>
        </div>

        <!-- Card 3 -->
        <div class="group bg-white border border-gray-200 rounded-xl p-6 shadow-md hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
          <div class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-purple-100 mb-4 group-hover:scale-110 transition-transform">
            <i class="fas fa-headset text-purple-600 text-xl"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Expert Support</h3>
          <p class="text-sm text-gray-600 leading-relaxed">
            Get guidance from our team of digital asset specialists throughout the process.
          </p>
        </div>

        <!-- Card 4 -->
        <div class="group bg-white border border-gray-200 rounded-xl p-6 shadow-md hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
          <div class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-yellow-100 mb-4 group-hover:scale-110 transition-transform">
            <i class="fas fa-gavel text-yellow-600 text-xl"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Legal Protection</h3>
          <p class="text-sm text-gray-600 leading-relaxed">
            Comprehensive contracts and legal documentation for every transaction.
          </p>
        </div>

        <!-- Card 5 -->
        <div class="group bg-white border border-gray-200 rounded-xl p-6 shadow-md hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
          <div class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 mb-4 group-hover:scale-110 transition-transform">
            <i class="fas fa-search text-blue-600 text-xl"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">24/7 Monitoring</h3>
          <p class="text-sm text-gray-600 leading-relaxed">
            Continuous monitoring of listings to ensure accuracy and prevent fraud.
          </p>
        </div>

        <!-- Card 6 -->
        <div class="group bg-white border border-gray-200 rounded-xl p-6 shadow-md hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
          <div class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 mb-4 group-hover:scale-110 transition-transform">
            <i class="fas fa-balance-scale text-green-600 text-xl"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Fair Pricing</h3>
          <p class="text-sm text-gray-600 leading-relaxed">
            Transparent pricing with detailed financial breakdowns and market analysis.
          </p>
        </div>

      </div>
    </div>
  </section>
  <section class="py-20 bg-gray-50">
    <div class="container mx-auto px-6">
      <!-- Heading -->
      <div class="text-center mb-12">
        <h2 class="text-4xl sm:text-5xl font-extrabold text-gray-900 mb-4">
          Trusted & Secure
          <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-purple-600">
            Platform
          </span>
        </h2>
        <p class="text-gray-600 max-w-2xl mx-auto">
          Enterprise-grade security trusted by thousands of entrepreneurs worldwide
        </p>
      </div>

      <!-- Icons Section -->
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        
        <!-- Card 1 -->
        <div class="p-6 rounded-xl border border-gray-200 shadow-md hover:shadow-lg hover:-translate-y-1 transition-all duration-300 bg-white text-center">
          <div class="flex flex-col items-center">
            <div class="h-14 w-14 flex items-center justify-center rounded-full bg-green-100 mb-4">
              <i class="fas fa-lock text-green-600 text-2xl"></i>
            </div>
            <h3 class="font-semibold text-gray-900 mb-2">SSL Secured</h3>
            <p class="text-gray-600 text-sm leading-relaxed">
              Your transactions are encrypted and protected by industry-grade SSL security.
            </p>
          </div>
        </div>

        <!-- Card 2 -->
        <div class="p-6 rounded-xl border border-gray-200 shadow-md hover:shadow-lg hover:-translate-y-1 transition-all duration-300 bg-white text-center">
          <div class="flex flex-col items-center">
            <div class="h-14 w-14 flex items-center justify-center rounded-full bg-blue-100 mb-4">
              <i class="fas fa-university text-blue-600 text-2xl"></i>
            </div>
            <h3 class="font-semibold text-gray-900 mb-2">Bank Grade Security</h3>
            <p class="text-gray-600 text-sm leading-relaxed">
              We use bank-level security measures to ensure your assets stay safe.
            </p>
          </div>
        </div>

        <!-- Card 3 -->
        <div class="p-6 rounded-xl border border-gray-200 shadow-md hover:shadow-lg hover:-translate-y-1 transition-all duration-300 bg-white text-center">
          <div class="flex flex-col items-center">
            <div class="h-14 w-14 flex items-center justify-center rounded-full bg-gray-100 mb-4">
              <i class="fas fa-user-check text-gray-800 text-2xl"></i>
            </div>
            <h3 class="font-semibold text-gray-900 mb-2">Verified by Experts</h3>
            <p class="text-gray-600 text-sm leading-relaxed">
              All listings and users are manually verified by our expert team.
            </p>
          </div>
        </div>

        <!-- Card 4 -->
        <div class="p-6 rounded-xl border border-gray-200 shadow-md hover:shadow-lg hover:-translate-y-1 transition-all duration-300 bg-white text-center">
          <div class="flex flex-col items-center">
            <div class="h-14 w-14 flex items-center justify-center rounded-full bg-purple-100 mb-4">
              <i class="fas fa-balance-scale text-purple-600 text-2xl"></i>
            </div>
            <h3 class="font-semibold text-gray-900 mb-2">Legal Compliance</h3>
            <p class="text-gray-600 text-sm leading-relaxed">
              Every transaction follows strict legal standards and compliance protocols.
            </p>
          </div>
        </div>

      </div>
    </div>
  </section>
<section class="relative py-16 bg-gradient-to-br from-indigo-700 via-purple-700 to-pink-600 overflow-hidden">
  <!-- Background Glow -->
  <div class="absolute inset-0">
    <div class="absolute -top-20 -left-20 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
    <div class="absolute bottom-0 right-0 w-80 h-80 bg-pink-500/20 rounded-full blur-3xl"></div>
  </div>

  <div class="relative max-w-6xl mx-auto px-5 text-white">
    <!-- Heading -->
    <div class="text-center mb-12">
      <h2 class="text-3xl sm:text-4xl font-extrabold mb-3 tracking-tight">
        Turn Digital Assets Into <span class="text-yellow-300">Opportunities</span>
      </h2>
      <p class="text-base sm:text-lg text-gray-100 max-w-xl mx-auto">
        Buy or sell websites, apps, and channels with trusted escrow, verified revenue, and secure transfers.
      </p>
    </div>

    <!-- Layout -->
    <div class="grid lg:grid-cols-3 gap-8 items-stretch">
      <!-- Sell Card -->
      <div class="lg:col-span-2 bg-white/10 backdrop-blur-xl rounded-2xl p-8 flex flex-col justify-between shadow-xl hover:-translate-y-1 transition-transform duration-500">
        <div>
          <div class="flex items-center justify-center w-12 h-12 rounded-full bg-white/20 mb-4">
            <i class="fas fa-upload text-2xl"></i>
          </div>
          <h3 class="text-2xl font-bold mb-3">Sell Your Website or Channel</h3>
          <p class="text-gray-100 mb-5 text-sm leading-relaxed">
            List your digital business with verified performance data and reach trusted global buyers. Get a free valuation before you sell.
          </p>

          <ul class="grid sm:grid-cols-2 gap-2 text-gray-100 mb-6 text-sm">
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-400"></i> Free valuation</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-400"></i> Verified buyers</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-400"></i> Secure escrow</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-400"></i> Expert support</li>
          </ul>
        </div>
        <div class="flex justify-end">
          <?php if ($user_logged_in): ?>
            <a href="./index.php?p=sellingOption" class="bg-white text-indigo-700 font-semibold px-8 py-2.5 rounded-lg shadow-md hover:bg-gray-100 transition duration-300">
              Start Selling
            </a>
          <?php else: ?>
            <a href="<?= url('index.php?p=login') ?>" class="bg-white text-indigo-700 font-semibold px-8 py-2.5 rounded-lg shadow-md hover:bg-gray-100 transition duration-300">
              Start Selling
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Browse Card -->
      <div class="bg-white/10 backdrop-blur-xl rounded-2xl p-6 flex flex-col justify-between shadow-xl hover:-translate-y-1 transition-transform duration-500">
        <div>
          <div class="flex items-center justify-center w-12 h-12 rounded-full bg-white/20 mb-4">
            <i class="fas fa-search text-2xl"></i>
          </div>
          <h3 class="text-xl font-bold mb-2">Explore Premium Deals</h3>
          <p class="text-gray-100 text-sm leading-relaxed mb-4">
            Discover profitable websites, apps, and channels ready for acquisition.
          </p>

          <ul class="space-y-2 text-gray-100 text-sm mb-6">
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-400"></i> Verified traffic</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-400"></i> Market valuations</li>
            <li class="flex items-center gap-2"><i class="fas fa-check text-green-400"></i> Direct contact</li>
          </ul>
        </div>
        <button onclick="window.location.href='<?= url('index.php?p=listing') ?>'" class="bg-gradient-to-r from-orange-500 to-pink-500 hover:opacity-90 text-white font-semibold px-6 py-2.5 rounded-lg transition duration-300">
          Browse Listings
        </button>
      </div>
    </div>

    <!-- Stats Section -->
    <div class="mt-12 grid sm:grid-cols-3 gap-6">
      <div class="bg-white/10 rounded-xl p-6 backdrop-blur-xl text-center shadow-md hover:-translate-y-1 transition-all duration-300">
        <h3 class="text-3xl font-extrabold">$45M+</h3>
        <p class="mt-1 text-gray-200 text-xs uppercase tracking-wide">Total Transactions</p>
      </div>
      <div class="bg-white/10 rounded-xl p-6 backdrop-blur-xl text-center shadow-md hover:-translate-y-1 transition-all duration-300">
        <h3 class="text-3xl font-extrabold">850+</h3>
        <p class="mt-1 text-gray-200 text-xs uppercase tracking-wide">Successful Deals</p>
      </div>
      <div class="bg-white/10 rounded-xl p-6 backdrop-blur-xl text-center shadow-md hover:-translate-y-1 transition-all duration-300">
        <h3 class="text-3xl font-extrabold">99.2%</h3>
        <p class="mt-1 text-gray-200 text-xs uppercase tracking-wide">Client Satisfaction</p>
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
document.addEventListener('DOMContentLoaded', function() {
  // Only handle search forms if user is logged in
  <?php if ($user_logged_in): ?>
  // Handle both mobile and desktop forms
  const searchFormMobile = document.getElementById('heroSearchForm');
  const searchFormDesktop = document.getElementById('heroSearchFormDesktop');
  const searchInput = document.getElementById('heroSearchInput');
  const searchError = document.getElementById('searchError');

  // Mobile form validation
  if (searchFormMobile) {
    searchFormMobile.addEventListener('submit', function(e) {
      const searchValue = this.querySelector('input[name="search"]').value.trim();
      const categoryValue = this.querySelector('select[name="category"]').value;
      
      if (!searchValue && !categoryValue) {
        e.preventDefault();
        if (searchError) {
          searchError.classList.remove('hidden');
          searchError.classList.add('animate-pulse');
          setTimeout(() => {
            searchError.classList.add('hidden');
            searchError.classList.remove('animate-pulse');
          }, 4000);
        }
      } else {
        if (searchError) searchError.classList.add('hidden');
      }
    });
  }

  // Desktop form validation
  if (searchFormDesktop) {
    searchFormDesktop.addEventListener('submit', function(e) {
      const searchValue = this.querySelector('input[name="search"]').value.trim();
      const categoryValue = this.querySelector('select[name="category"]').value;
      
      if (!searchValue && !categoryValue) {
        e.preventDefault();
        if (searchError) {
          searchError.classList.remove('hidden');
          searchError.classList.add('animate-pulse');
          setTimeout(() => {
            searchError.classList.add('hidden');
            searchError.classList.remove('animate-pulse');
          }, 4000);
        }
      } else {
        if (searchError) searchError.classList.add('hidden');
      }
    });
  }

  // Hide error when user starts typing (for both forms)
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      if (this.value.trim().length > 0 && searchError) {
        searchError.classList.add('hidden');
      }
    });
  }
  <?php endif; ?>
});

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

function showMakeOfferPopup(listingId, listingName, askingPrice, sellerId) {
  document.getElementById('offerListingName').textContent = listingName;
  document.getElementById('offerAskingPrice').textContent = '$' + parseFloat(askingPrice).toLocaleString();
  document.getElementById('offerListingId').value = listingId;
  document.getElementById('offerSellerId').value = sellerId;
  
  // Get minimum offer percentage from server (default 70%)
  fetch(PathUtils.getApiUrl('get_min_offer_percentage.php'))
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
  
  // Add emoji to message based on type
  const emojiMessage = type === 'success' 
    ? `✅ ${message}` 
    : `❌ ${message}`;
  
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
      <span>${emojiMessage}</span>
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

// Wishlist functionality
function toggleWishlist(listingId, button) {
  const icon = button.querySelector('i');
  const isInWishlist = button.dataset.inWishlist === 'true';
  
  // Show loading state
  icon.className = 'fas fa-spinner fa-spin text-gray-400 text-lg';
  button.disabled = true;
  
  // Create form data
  const formData = new FormData();
  formData.append('listing_id', listingId);
  
  // Submit wishlist toggle
  fetch('index.php?p=wishlist_toggle', {
    method: 'POST',
    body: formData,
    credentials: 'same-origin'
  })
  .then(response => {
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    return response.json();
  })
  .then(data => {
    if (data.success) {
      // Update button state
      if (data.in_wishlist) {
        icon.className = 'fas fa-bookmark text-blue-600 text-lg group-hover:scale-110 transition-transform';
        button.dataset.inWishlist = 'true';
        showWishlistNotification('Added to saved listings 📌', 'success');
      } else {
        icon.className = 'far fa-bookmark text-gray-600 text-lg group-hover:scale-110 transition-transform';
        button.dataset.inWishlist = 'false';
        showWishlistNotification('Removed from saved listings 🗑️', 'info');
      }
    } else {
      showWishlistNotification(data.message || 'Failed to update wishlist', 'error');
      // Reset icon
      icon.className = isInWishlist ? 'fas fa-bookmark text-blue-600 text-lg' : 'far fa-bookmark text-gray-600 text-lg';
    }
  })
  .catch(error => {
    console.error('Wishlist error:', error);
    showWishlistNotification('Network error. Please try again.', 'error');
    // Reset icon
    icon.className = isInWishlist ? 'fas fa-bookmark text-blue-600 text-lg' : 'far fa-bookmark text-gray-600 text-lg';
  })
  .finally(() => {
    button.disabled = false;
  });
}

// Show wishlist notification
function showWishlistNotification(message, type) {
  // Remove existing notifications
  const existingNotification = document.getElementById('wishlistNotification');
  if (existingNotification) {
    existingNotification.remove();
  }
  
  // Add emoji to message based on type
  let emojiMessage;
  if (type === 'success') {
    emojiMessage = `✅ ${message}`;
  } else if (type === 'info') {
    emojiMessage = `ℹ️ ${message}`;
  } else {
    emojiMessage = `❌ ${message}`;
  }
  
  // Create notification
  const notification = document.createElement('div');
  notification.id = 'wishlistNotification';
  notification.className = `fixed top-4 right-4 z-[60] px-6 py-4 rounded-lg shadow-lg transform transition-all duration-300 ${
    type === 'success' 
      ? 'bg-green-500 text-white' 
      : type === 'info'
      ? 'bg-blue-500 text-white'
      : 'bg-red-500 text-white'
  }`;
  
  notification.innerHTML = `
    <div class="flex items-center gap-3">
      <i class="fas ${type === 'success' ? 'fa-bookmark' : type === 'info' ? 'fa-info-circle' : 'fa-exclamation-circle'}"></i>
      <span>${emojiMessage}</span>
      <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-white hover:text-gray-200">
        <i class="fas fa-times"></i>
      </button>
    </div>
  `;
  
  document.body.appendChild(notification);
  
  // Auto remove after 3 seconds
  setTimeout(() => {
    if (notification && notification.parentNode) {
      notification.remove();
    }
  }, 3000);
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

// Function to dynamically add new listings to the page
function addNewListingsToPage(newListings) {
  const listingsGrid = document.querySelector('.grid.gap-8.md\\:grid-cols-2.lg\\:grid-cols-3');
  if (!listingsGrid) {
    console.error('Listings grid not found');
    return;
  }
  
  // Check if "No listings available" message exists and remove it
  const noListingsMsg = listingsGrid.querySelector('.col-span-full');
  if (noListingsMsg) {
    noListingsMsg.remove();
  }
  
  newListings.forEach(listing => {
    // Check if listing already exists on page
    const existingListing = document.querySelector(`[data-listing-id="${listing.id}"]`);
    if (existingListing) {
      console.log(`Listing ${listing.id} already exists on page`);
      return;
    }
    
    // Create new listing HTML
    const listingHTML = createListingHTML(listing);
    
    // Add to beginning of grid with animation
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = listingHTML;
    const newListingElement = tempDiv.firstElementChild;
    
    // Add data attribute for tracking
    newListingElement.setAttribute('data-listing-id', listing.id);
    
    // Insert at the beginning with fade-in animation
    newListingElement.style.opacity = '0';
    newListingElement.style.transform = 'translateY(-20px)';
    listingsGrid.insertBefore(newListingElement, listingsGrid.firstChild);
    
    // Animate in
    setTimeout(() => {
      newListingElement.style.transition = 'all 0.5s ease';
      newListingElement.style.opacity = '1';
      newListingElement.style.transform = 'translateY(0)';
    }, 100);
    
    console.log(`✅ Added new listing: ${listing.name} (ID: ${listing.id})`);
  });
}

// Function to create listing HTML
function createListingHTML(listing) {
  const userLoggedIn = <?= $user_logged_in ? 'true' : 'false' ?>;
  const currentUserId = <?= $user_logged_in ? $_SESSION['user']['id'] : 'null' ?>;
  
  // Fix image path - ensure it has the correct base URL
  let proofImage = '';
  if (listing.proof_image) {
    // If the path doesn't start with http or /, add the base path
    if (!listing.proof_image.startsWith('http') && !listing.proof_image.startsWith('/')) {
      proofImage = '<?= BASE ?>' + listing.proof_image;
    } else if (listing.proof_image.startsWith('uploads/')) {
      proofImage = '<?= BASE ?>' + listing.proof_image;
    } else {
      proofImage = listing.proof_image;
    }
  }
  
  const isOwnListing = userLoggedIn && listing.user_id == currentUserId;
  
  return `
    <div class="group relative rounded-2xl border border-gray-200 shadow-md hover:shadow-lg transition-all duration-500 overflow-hidden hover:-translate-y-2 animate-fade-in ${userLoggedIn ? 'bg-white' : 'bg-white/90 filter blur-sm pointer-events-none'}" data-listing-id="${listing.id}">
      <!-- Image -->
      <div class="relative h-56 overflow-hidden bg-gray-100">
        ${proofImage ? `
          <img src="${proofImage}" 
               alt="${listing.name}" 
               class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
               onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
          <div class="w-full h-full flex items-center justify-center bg-gray-200 text-gray-500 text-sm" style="display: none;">
            <i class="fas fa-image mr-2"></i>Image Not Available
          </div>
        ` : `
          <div class="w-full h-full flex items-center justify-center bg-gray-200 text-gray-500 text-sm">
            <i class="fas fa-image mr-2"></i>No Image
          </div>
        `}

        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

        <!-- Status Badge -->
        <span class="absolute top-4 left-4 bg-green-500 text-white text-xs font-semibold px-3 py-1 rounded-full shadow-lg">
          <i class="fa-solid fa-check-circle mr-1"></i> Verified
        </span>
        
        <!-- Wishlist Button -->
        ${userLoggedIn ? `
          <button onclick="toggleWishlist(${listing.id}, this)" 
                  class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/90 backdrop-blur-sm shadow-lg hover:bg-white transition-all duration-300 flex items-center justify-center group"
                  data-listing-id="${listing.id}"
                  data-in-wishlist="false">
            <i class="far fa-bookmark text-gray-600 text-lg group-hover:scale-110 transition-transform"></i>
          </button>
        ` : ''}
      </div>

      <div class="p-6">
        <div class="flex items-center gap-2 mb-3">
          <span class="border border-blue-500 text-blue-500 text-xs px-2 py-1 rounded-full">
            <i class="fa-solid fa-tag mr-1 text-xs"></i> ${listing.type}
          </span>
          <span class="text-xs text-gray-500">${listing.category || ''}</span>
        </div>

        <h3 class="text-xl font-bold text-gray-900 mb-4 group-hover:text-blue-600 transition-colors duration-300">
          ${listing.name}
        </h3>

        <div class="flex items-center justify-between bg-green-50 rounded-lg p-3 mb-4 border border-green-200">
          <div class="flex items-center gap-2 text-sm text-gray-600">
            <i class="fa-solid fa-chart-line text-green-600"></i>
            <span>Monthly Revenue</span>
          </div>
          <span class="font-bold text-green-600">
            <i class="fa-solid fa-dollar-sign text-xs"></i> ${listing.monthly_revenue}/mo
          </span>
        </div>

        <div class="mb-5">
          <div class="flex items-baseline gap-2">
            <i class="fa-solid fa-dollar-sign text-blue-600 text-lg"></i>
            <span class="text-3xl font-bold text-gray-900">${listing.asking_price}</span>
          </div>
          <p class="text-xs text-gray-500 mt-1">Asking Price</p>
        </div>

        ${userLoggedIn ? `
          ${!isOwnListing ? `
            <div class="flex gap-2">
              <!-- Buy Now Button -->
              <button type="button" 
                      data-listing-id="${listing.id}"
                      data-listing-name="${listing.name}"
                      data-asking-price="${listing.asking_price}"
                      data-seller-id="${listing.user_id}"
                      class="buy-now-btn flex-1 bg-gradient-to-r from-blue-500 to-purple-500 text-white text-sm font-bold py-3 rounded-md hover:opacity-90 transition-opacity shadow-md">
                <i class="fa-solid fa-shopping-cart mr-2"></i> Buy Now
              </button>
              
              <!-- Make Offer Button -->
              <button type="button"
                      data-listing-id="${listing.id}"
                      data-listing-name="${listing.name}"
                      data-asking-price="${listing.asking_price}"
                      data-seller-id="${listing.user_id}"
                      class="make-offer-btn flex-1 bg-gradient-to-r from-green-500 to-green-600 text-white text-sm font-semibold py-3 rounded-md hover:opacity-90 transition-opacity">
                <i class="fa-solid fa-handshake mr-2"></i> Make Offer
              </button>
            </div>
          ` : `
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-center">
              <p class="text-sm text-blue-700 font-medium">
                <i class="fa-solid fa-user-check mr-1"></i> Your Listing
              </p>
            </div>
          `}
          
          <!-- View Details Button -->
          <div class="mt-2">
            <a href="./index.php?p=listingDetail&id=${listing.id}" class="block w-full text-center border border-gray-300 text-gray-600 text-xs font-medium py-2 rounded-md hover:bg-gray-50 transition-colors">
              <i class="fa-solid fa-eye mr-1"></i> View Details
            </a>
          </div>
        ` : ''}
      </div>

      <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 to-purple-500 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
    </div>
  `;
}

// Home page polling integration with PathDetector fix
console.log('🏠 HOME PAGE: Initializing polling system with PathDetector fix');

// PathDetector Server Fix for Home Page
console.log('🔧 Home Page: Applying PathDetector server fix...');

// Ensure BASE constant is available
if (typeof BASE === 'undefined') {
    console.error('❌ BASE constant not defined in home page');
    var BASE = '<?= defined('BASE') ? BASE : '' ?>';
    if (!BASE) {
        BASE = window.location.origin + '/';
    }
    console.log('🔧 Using fallback BASE for home page:', BASE);
} else {
    console.log('✅ BASE constant available for home page:', BASE);
}

// Override PathDetector if it exists to use BASE
if (window.pathDetector) {
    console.log('🔧 Home Page: Overriding PathDetector to use BASE constant');
    window.pathDetector.buildApiUrl = function(endpoint) {
        if (!endpoint.startsWith('/')) endpoint = '/' + endpoint;
        let baseUrl = BASE;
        if (baseUrl.endsWith('/') && endpoint.startsWith('/')) {
            endpoint = endpoint.substring(1);
        }
        const fullUrl = baseUrl + endpoint;
        console.log('🔗 Home Page PathDetector using BASE:', { endpoint, BASE, fullUrl });
        return fullUrl;
    };
}

// Load polling.js if not already loaded
function loadPollingForHome() {
  if (typeof BASE === 'undefined') {
    console.error('❌ BASE constant not defined for home page polling');
    return;
  }
  
  if (typeof startPolling === 'undefined') {
    console.log('📦 Loading polling.js for home page...');
    const script = document.createElement('script');
    script.src = BASE + 'js/polling.js';
    
    script.onload = function() {
      console.log('✅ polling.js loaded for home page');
      initHomePolling();
    };
    
    script.onerror = function() {
      console.error('❌ Failed to load polling.js for home page');
      // Retry after 5 seconds
      setTimeout(() => {
        console.log('🔄 Retrying to load polling.js for home page...');
        document.head.appendChild(script.cloneNode(true));
      }, 5000);
    };
    
    document.head.appendChild(script);
  } else {
    console.log('✅ polling.js already loaded for home page');
    initHomePolling();
  }
}

// Initialize home page specific polling
function initHomePolling() {
  // Wait for polling manager to be available
  function waitForPollingManager() {
    if (window.pollingManager) {
      console.log('✅ Polling manager found, adding home page callbacks');
      
      // Add home page specific callbacks
      window.pollingManager.renderCallbacks = {
        ...window.pollingManager.renderCallbacks,
        listings: function(newListings) {
          console.log('🏠 Home page listings callback triggered:', newListings.length);
          
          // Filter only approved listings for home page
          const approvedListings = newListings.filter(listing => 
            listing.status === 'approved' &&
            listing.name && 
            listing.name.trim() !== '' &&
            !listing.name.toLowerCase().includes('test listing')
          );
          
          if (approvedListings.length > 0) {
            console.log(`🎉 Found ${approvedListings.length} new approved listings for home page`);
            addNewListingsToPage(approvedListings);
            
            // Show notification
            showHomeNotification(`🎉 ${approvedListings.length} new verified listing${approvedListings.length > 1 ? 's' : ''}!`, 'success');
          }
        }
      };
      
      // Start polling for home page
      console.log('🚀 Starting polling for home page');
      
    } else {
      console.log('⏳ Waiting for polling manager on home page...');
      setTimeout(waitForPollingManager, 1000);
    }
  }
  
  waitForPollingManager();
}

// Show home page notifications
function showHomeNotification(message, type = 'info') {
  const colors = {
    info: 'bg-blue-500',
    success: 'bg-green-500',
    warning: 'bg-yellow-500',
    error: 'bg-red-500'
  };
  
  const notification = document.createElement('div');
  notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-4 rounded-lg shadow-lg z-50 animate-fade-in`;
  notification.innerHTML = `
    <div class="flex items-center gap-2">
      <i class="fas fa-${type === 'success' ? 'check' : 'info'}-circle"></i>
      <span>${message}</span>
      <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-white hover:text-gray-200">
        <i class="fas fa-times"></i>
      </button>
    </div>
  `;
  
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.style.opacity = '0';
    setTimeout(() => notification.remove(), 300);
  }, 4000);
}

// Start loading polling when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  console.log('🏠 Home page DOM loaded, starting polling initialization');
  setTimeout(loadPollingForHome, 1000);
});

// Event delegation for Buy Now and Make Offer buttons
document.addEventListener('click', function(e) {
  // Buy Now button
  if (e.target.closest('.buy-now-btn')) {
    const btn = e.target.closest('.buy-now-btn');
    const listingId = btn.dataset.listingId;
    const listingName = btn.dataset.listingName;
    const askingPrice = btn.dataset.askingPrice;
    const sellerId = btn.dataset.sellerId;
    showBuyNowPopup(listingId, listingName, askingPrice, sellerId);
  }
  
  // Make Offer button
  if (e.target.closest('.make-offer-btn')) {
    const btn = e.target.closest('.make-offer-btn');
    const listingId = btn.dataset.listingId;
    const listingName = btn.dataset.listingName;
    const askingPrice = btn.dataset.askingPrice;
    const sellerId = btn.dataset.sellerId;
    showMakeOfferPopup(listingId, listingName, askingPrice, sellerId);
  }
});

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