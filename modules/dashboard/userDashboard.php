<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../middlewares/auth.php';
require_login();

$user = current_user();

// Debug: Check if user data is properly set
if (!$user || !isset($user['id'])) {
  error_log("USERDASHBOARD ERROR: User data not properly set. User: " . print_r($user, true));
  echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>
            <strong>Session Error:</strong> Please log out and log back in.
          </div>";
  exit;
}

error_log("USERDASHBOARD DEBUG: Current user ID: " . $user['id']);

$pdo = db();

// 🟦 Stats
try {
  $offersSent = $pdo->prepare("SELECT COUNT(*) FROM offers WHERE user_id = ?");
  $offersSent->execute([$user['id']]);
  $totalOffers = $offersSent->fetchColumn();

  $offersAccepted = $pdo->prepare("SELECT COUNT(*) FROM offers WHERE user_id = ? AND status = 'accepted'");
  $offersAccepted->execute([$user['id']]);
  $acceptedCount = $offersAccepted->fetchColumn();

  $completedOrders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND status = 'completed'");
  $completedOrders->execute([$user['id']]);
  $completedCount = $completedOrders->fetchColumn();

  $totalSpent = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM orders WHERE buyer_id = ? AND status = 'completed'");
  $totalSpent->execute([$user['id']]);
  $totalSpentValue = $totalSpent->fetchColumn();

  error_log("USERDASHBOARD DEBUG: Stats loaded - Offers: $totalOffers, Accepted: $acceptedCount, Completed: $completedCount, Spent: $totalSpentValue");
} catch (Exception $e) {
  error_log("USERDASHBOARD ERROR: Stats query failed: " . $e->getMessage());
  $totalOffers = $acceptedCount = $completedCount = $totalSpentValue = 0;
}

// 🟨 Recent Offers - Updated query with LEFT JOIN to handle missing data
try {
  $recentOffers = $pdo->prepare("
        SELECT o.*, l.name AS listing_name, s.name AS seller_name 
        FROM offers o
        LEFT JOIN listings l ON o.listing_id = l.id
        LEFT JOIN users s ON l.user_id = s.id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT 4
    ");
  $recentOffers->execute([$user['id']]);
  $recentOffers = $recentOffers->fetchAll(PDO::FETCH_ASSOC);

  error_log("USERDASHBOARD DEBUG: Recent offers loaded: " . count($recentOffers));
} catch (Exception $e) {
  error_log("USERDASHBOARD ERROR: Recent offers query failed: " . $e->getMessage());
  $recentOffers = [];
}

// 🟩 Recent Orders - Updated query with LEFT JOIN to handle missing data
try {
  $recentOrders = $pdo->prepare("
        SELECT o.*, l.name AS listing_name, s.name AS seller_name
        FROM orders o
        LEFT JOIN listings l ON o.listing_id = l.id
        LEFT JOIN users s ON l.user_id = s.id
        WHERE o.buyer_id = ?
        ORDER BY o.created_at DESC
        LIMIT 3
    ");
  $recentOrders->execute([$user['id']]);
  $recentOrders = $recentOrders->fetchAll(PDO::FETCH_ASSOC);

  error_log("USERDASHBOARD DEBUG: Recent orders loaded: " . count($recentOrders));
} catch (Exception $e) {
  error_log("USERDASHBOARD ERROR: Recent orders query failed: " . $e->getMessage());
  $recentOrders = [];
}

// Check user's listings as well
try {
  $userListings = $pdo->prepare("SELECT COUNT(*) FROM listings WHERE user_id = ?");
  $userListings->execute([$user['id']]);
  $totalListings = $userListings->fetchColumn();

  error_log("USERDASHBOARD DEBUG: Total listings: " . $totalListings);
} catch (Exception $e) {
  error_log("USERDASHBOARD ERROR: Listings count query failed: " . $e->getMessage());
  $totalListings = 0;
}

// Debug information (remove in production)
// echo "<!-- DEBUG: User ID: {$user['id']}, Total Offers: $totalOffers, Recent Offers: " . count($recentOffers) . ", Recent Orders: " . count($recentOrders) . ", Total Listings: $totalListings -->";

// Check if user has any activity (offers, orders, or listings)
$hasActivity = !empty($recentOffers) || !empty($recentOrders) || $totalOffers > 0 || $completedCount > 0 || $totalListings > 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>User Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    html {
      scroll-behavior: smooth;
    }

    ::-webkit-scrollbar {
      width: 8px;
    }

    ::-webkit-scrollbar-thumb {
      background: linear-gradient(to bottom, #60a5fa, #818cf8);
      border-radius: 6px;
    }
  </style>
</head>

<body class="bg-gradient-to-br from-white via-blue-50 to-indigo-50/50 min-h-screen text-gray-800 font-[Inter]">

  <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-10">

    <?php if (!$hasActivity): ?>
      <!-- Empty State for New Users -->
      <div class="min-h-[70vh] flex items-center justify-center">
        <div class="text-center max-w-2xl mx-auto p-8">
          <div class="w-32 h-32 bg-gradient-to-br from-blue-100 to-indigo-200 rounded-full flex items-center justify-center mx-auto mb-8">
            <i class="fas fa-rocket text-blue-600 text-4xl"></i>
          </div>

          <h1 class="text-4xl font-bold text-gray-900 mb-4">Welcome to Your Dashboard!</h1>
          <p class="text-xl text-gray-600 mb-8 leading-relaxed">
            You're all set to start your digital asset journey. Your dashboard will show activity once you start exploring listings, making offers, or creating your own listings.
          </p>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
              <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-search text-blue-600"></i>
              </div>
              <h3 class="font-semibold text-gray-900 mb-2">Explore Listings</h3>
              <p class="text-sm text-gray-600">Browse verified digital assets ready for acquisition</p>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
              <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-handshake text-green-600"></i>
              </div>
              <h3 class="font-semibold text-gray-900 mb-2">Make Offers</h3>
              <p class="text-sm text-gray-600">Submit competitive offers on assets you're interested in</p>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
              <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-chart-line text-purple-600"></i>
              </div>
              <h3 class="font-semibold text-gray-900 mb-2">Track Progress</h3>
              <p class="text-sm text-gray-600">Monitor your offers and manage your investments</p>
            </div>
          </div>

          <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="index.php?p=listing" class="inline-flex items-center px-8 py-4 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition font-semibold text-lg shadow-lg hover:shadow-xl">
              <i class="fas fa-store mr-3"></i>
              Browse Marketplace
            </a>
            <a href="index.php?p=sellingOption" class="inline-flex items-center px-8 py-4 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-semibold text-lg">
              <i class="fas fa-plus mr-3"></i>
              List Your Asset
            </a>
          </div>
        </div>
      </div>

    <?php else: ?>
      <!-- Regular Dashboard Content -->
      <!-- Heading -->
      <div class="mb-8 text-center sm:text-left">
        <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-800 mb-2 flex items-center justify-center sm:justify-start">
          <i class="fa-solid fa-chart-line text-blue-600 mr-2"></i> Overview
        </h2>
        <p class="text-gray-500 text-sm sm:text-base">A quick summary of your performance and latest activities.</p>
      </div>

      <!-- 📊 Stats -->
      <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 sm:gap-6 mb-10">
        <?php
        $cards = [
          ['My Listings', $totalListings, 'fa-list', 'from-cyan-100 to-cyan-50', 'text-cyan-600', 'total-listings'],
          ['Offers Sent', $totalOffers, 'fa-paper-plane', 'from-blue-100 to-blue-50', 'text-blue-600', 'total-offers'],
          ['Offers Accepted', $acceptedCount, 'fa-handshake', 'from-emerald-100 to-green-50', 'text-emerald-600', 'accepted-offers'],
          ['Completed Orders', $completedCount, 'fa-circle-check', 'from-amber-100 to-yellow-50', 'text-amber-600', 'completed-orders'],
          ['Total Spent', "$" . number_format($totalSpentValue, 2), 'fa-sack-dollar', 'from-purple-100 to-indigo-50', 'text-purple-600', 'total-spent']
        ];
        foreach ($cards as [$title, $value, $icon, $gradient, $iconColor, $dataStat]) {
          echo "
          <div class='bg-gradient-to-br $gradient rounded-2xl p-5 sm:p-6 shadow-md hover:shadow-lg transition transform hover:-translate-y-1'>
            <div class='flex items-center justify-between'>
              <h3 class='text-gray-700 font-semibold text-sm sm:text-base'>$title</h3>
              <i class='fa-solid $icon $iconColor text-xl sm:text-2xl'></i>
            </div>
            <p class='text-3xl sm:text-4xl font-extrabold mt-3 text-gray-900' data-stat='$dataStat'>$value</p>
          </div>";
        }
        ?>
      </section>

      <!-- 🔵 Recent Offers -->
      <section class="mb-10">
        <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 gap-2">
          <h2 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center justify-center sm:justify-start">
            <i class="fa-solid fa-paper-plane text-blue-600 mr-2"></i> Recent Offers
          </h2>
          <a href="index.php?p=dashboard&page=offers" class="text-sm text-blue-600 hover:underline font-medium text-center sm:text-right">
            View All
          </a>
        </div>
        <?php if (empty($recentOffers)): ?>
          <div class="bg-white p-8 sm:p-12 rounded-xl border border-gray-100 shadow text-center">
            <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
              <i class="fas fa-paper-plane text-blue-500 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-3">No Offers Yet</h3>
            <p class="text-gray-600 mb-6 max-w-md mx-auto">You haven't made any offers yet. Start exploring digital assets and make your first offer!</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
              <a href="index.php?p=sellingOption" class="inline-flex items-center justify-center px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-200 font-semibold shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                <i class="fas fa-plus-circle mr-2"></i>
                Start Selling
              </a>
              <a href="index.php?p=dashboard&page=offers" class="inline-flex items-center justify-center px-6 py-3 border-2 border-blue-600 text-blue-600 rounded-lg hover:bg-blue-50 transition-all duration-200 font-semibold">
                <i class="fas fa-history mr-2"></i>
                View Offers History
              </a>
            </div>
          </div>
        <?php else: ?>
          <div class="grid gap-4 sm:gap-5 sm:grid-cols-2" id="recent-offers-container">
            <?php foreach ($recentOffers as $offer): ?>
              <div class="bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition p-5 sm:p-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                  <div>
                    <h3 class="text-base sm:text-lg font-semibold text-gray-800">
                      <i class="fa-solid fa-list mr-1 text-blue-500"></i>
                      <?= htmlspecialchars($offer['listing_name']) ?>
                    </h3>
                    <p class="text-sm text-gray-500">
                      <i class="fa-solid fa-user mr-1"></i>
                      Seller: <?= htmlspecialchars($offer['seller_name']) ?>
                    </p>
                    <p class="text-xs text-gray-400 mt-1">
                      <i class="fa-regular fa-clock mr-1"></i>
                      <?= date('d M Y', strtotime($offer['created_at'])) ?>
                    </p>
                  </div>
                  <div class="text-right w-full sm:w-auto">
                    <p class="text-base sm:text-lg font-bold text-gray-800">
                      <i class="fa-solid fa-dollar-sign mr-1 text-green-600"></i>
                      <?= number_format($offer['amount'], 0) ?>
                    </p>
                    <?php if ($offer['status'] === 'accepted'): ?>
                      <a href="<?= url('index.php?p=payment&id=' . $offer['listing_id']) ?>"
                        class="inline-flex mt-2 px-3 py-1.5 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg text-sm shadow hover:opacity-90 w-full sm:w-auto justify-center">
                        <i class="fa-solid fa-credit-card mr-1"></i> Pay
                      </a>
                    <?php elseif ($offer['status'] === 'pending'): ?>
                      <span class="text-sm text-orange-500 font-medium">
                        <i class="fa-regular fa-hourglass-half mr-1"></i> Pending
                      </span>
                    <?php else: ?>
                      <span class="text-sm text-red-500 font-medium">
                        <i class="fa-solid fa-xmark mr-1"></i> Rejected
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <!-- 🟢 Recent Orders -->
      <section>
        <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 gap-2">
          <h2 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center justify-center sm:justify-start">
            <i class="fa-solid fa-cart-shopping text-indigo-600 mr-2"></i> Recent Orders
          </h2>
          <a href="index.php?p=dashboard&page=my_order" class="text-sm text-blue-600 hover:underline font-medium text-center sm:text-right">
            View All
          </a>
        </div>
        <?php if (empty($recentOrders)): ?>
          <div class="bg-white p-8 sm:p-12 rounded-xl border border-gray-100 shadow text-center">
            <div class="w-20 h-20 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-6">
              <i class="fas fa-shopping-cart text-indigo-500 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-3">No Orders Yet</h3>
            <p class="text-gray-600 mb-6 max-w-md mx-auto">You haven't completed any purchases yet. Your order history will appear here once you make your first purchase.</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
              <a href="index.php?p=listing" class="inline-flex items-center justify-center px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg hover:from-indigo-700 hover:to-purple-700 transition-all duration-200 font-semibold shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                <i class="fas fa-store mr-2"></i>
                Start Shopping
              </a>
              <a href="index.php?p=dashboard&page=my_order" class="inline-flex items-center justify-center px-6 py-3 border-2 border-indigo-600 text-indigo-600 rounded-lg hover:bg-indigo-50 transition-all duration-200 font-semibold">
                <i class="fas fa-history mr-2"></i>
                View Order History
              </a>
            </div>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto bg-white rounded-xl border border-gray-100 shadow-sm">
            <table class="w-full text-xs sm:text-sm text-left text-gray-600 min-w-[600px]">
              <thead class="bg-gray-50 text-gray-700 font-semibold">
                <tr>
                  <th class="px-4 sm:px-6 py-3">Listing</th>
                  <th class="px-4 sm:px-6 py-3">Seller</th>
                  <th class="px-4 sm:px-6 py-3">Amount</th>
                  <th class="px-4 sm:px-6 py-3">Status</th>
                  <th class="px-4 sm:px-6 py-3 text-right">Date</th>
                </tr>
              </thead>
              <tbody id="recent-orders-container">
                <?php foreach ($recentOrders as $order): ?>
                  <tr class="border-t hover:bg-gray-50 transition">
                    <td class="px-4 sm:px-6 py-3 font-medium text-gray-900">
                      <i class="fa-solid fa-list mr-1 text-blue-500"></i>
                      <?= htmlspecialchars($order['listing_name']) ?>
                    </td>
                    <td class="px-4 sm:px-6 py-3">
                      <i class="fa-solid fa-user mr-1"></i><?= htmlspecialchars($order['seller_name']) ?>
                    </td>
                    <td class="px-4 sm:px-6 py-3">
                      <i class="fa-solid fa-dollar-sign mr-1 text-green-600"></i>
                      <?= number_format($order['amount'], 2) ?>
                    </td>
                    <td class="px-4 sm:px-6 py-3">
                      <?php if ($order['status'] === 'completed'): ?>
                        <span class="text-green-600 font-medium">
                          <i class="fa-solid fa-check mr-1"></i> Completed
                        </span>
                      <?php elseif ($order['status'] === 'pending'): ?>
                        <span class="text-orange-500 font-medium">
                          <i class="fa-regular fa-hourglass-half mr-1"></i> Pending
                        </span>
                      <?php else: ?>
                        <span class="text-gray-500">
                          <i class="fa-solid fa-circle-minus mr-1"></i> <?= ucfirst($order['status']) ?>
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="px-4 sm:px-6 py-3 text-right text-gray-500">
                      <i class="fa-regular fa-calendar mr-1"></i>
                      <?= date('d M Y', strtotime($order['created_at'])) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </main>

  <!-- User Dashboard Polling Integration -->
  <script src="<?= BASE ?>js/polling.js"></script>
  <script>
    // Initialize polling for user dashboard
    const currentUserId = <?= $user['id'] ?>;
    console.log('🔧 User Dashboard - User ID:', currentUserId);

    document.addEventListener('DOMContentLoaded', function() {
      console.log('🚀 User Dashboard polling initialization started');

      // Wait for global polling manager to be available
      function initDashboardPolling() {
        if (window.pollingManager) {
          console.log('✅ Polling manager found, adding dashboard callbacks');

          // Add dashboard-specific callbacks
          window.pollingManager.renderCallbacks = {
            ...window.pollingManager.renderCallbacks,
            listings: (newListings) => {
              console.log('📋 New listings detected:', newListings.length);

              // Filter listings created by current user
              const myListings = newListings.filter(listing => listing.user_id == currentUserId);

              if (myListings.length > 0) {
                console.log('📊 Updating dashboard with', myListings.length, 'new listings');
                updateListingsCount(myListings);
                showNotification(`${myListings.length} new listing${myListings.length > 1 ? 's' : ''} added!`, 'success');
              }
            },
            offers: (newOffers) => {
              console.log('💰 New offers detected:', newOffers.length);

              // Filter offers where current user is the buyer
              const myOffers = newOffers.filter(offer => offer.user_id == currentUserId);

              if (myOffers.length > 0) {
                console.log('📊 Updating dashboard with', myOffers.length, 'new offers');
                updateDashboardStats(myOffers);
                addRecentOffers(myOffers);
              }
            },
            transactions: (newOrders) => {
              console.log('📦 New orders detected:', newOrders.length);

              // Filter orders where current user is the buyer
              const myOrders = newOrders.filter(order => order.buyer_id == currentUserId);

              if (myOrders.length > 0) {
                console.log('📊 Updating dashboard with', myOrders.length, 'new orders');
                updateOrderStats(myOrders);
                addRecentOrders(myOrders);
              }
            }
          };
        } else {
          console.log('⏳ Waiting for polling manager...');
          setTimeout(initDashboardPolling, 1000);
        }
      }

      // Start dashboard polling
      setTimeout(initDashboardPolling, 2000);
    });

  <!-- User Dashboard Polling Integration -->
  <script src="<?= BASE ?>js/polling.js"></script>
  <script>
    // Initialize polling for user dashboard
    const currentUserId = <?= $user['id'] ?>;
    console.log('🔧 User Dashboard - User ID:', currentUserId);

    document.addEventListener('DOMContentLoaded', function() {
      console.log('🚀 User Dashboard polling initialization started');

      // Wait for polling manager to be available
      function initDashboardPolling() {
        if (window.pollingManager) {
          console.log('✅ Polling manager found, adding dashboard callbacks');

          // Add dashboard-specific callbacks
          window.pollingManager.renderCallbacks = {
            ...window.pollingManager.renderCallbacks,
            listings: (newListings) => {
              console.log('📋 New listings detected:', newListings.length);

              // Filter listings created by current user
              const myListings = newListings.filter(listing => listing.user_id == currentUserId);

              if (myListings.length > 0) {
                console.log('📊 Updating dashboard with', myListings.length, 'new listings');
                updateListingsCount(myListings);
                showNotification(`${myListings.length} new listing${myListings.length > 1 ? 's' : ''} added!`, 'success');
              }
            },
            offers: (newOffers) => {
              console.log('💰 New offers detected:', newOffers.length);

              // Filter offers where current user is the buyer
              const myOffers = newOffers.filter(offer => offer.user_id == currentUserId);

              if (myOffers.length > 0) {
                console.log('📊 Updating dashboard with', myOffers.length, 'new offers');
                updateDashboardStats(myOffers);
                addRecentOffers(myOffers);
              }
            },
            transactions: (newOrders) => {
              console.log('📦 New orders detected:', newOrders.length);

              // Filter orders where current user is the buyer
              const myOrders = newOrders.filter(order => order.buyer_id == currentUserId);

              if (myOrders.length > 0) {
                console.log('📊 Updating dashboard with', myOrders.length, 'new orders');
                updateOrderStats(myOrders);
                addRecentOrders(myOrders);
              }
            }
          };
        } else {
          console.log('⏳ Waiting for polling manager...');
          setTimeout(initDashboardPolling, 1000);
        }
      }

      // Start dashboard polling
      setTimeout(initDashboardPolling, 2000);
    });

    function updateListingsCount(listings) {
      const listingsEl = document.querySelector('[data-stat="total-listings"]');
      if (listingsEl) {
        const currentCount = parseInt(listingsEl.textContent) || 0;
        listingsEl.textContent = currentCount + listings.length;
      }
    }

    function updateDashboardStats(offers) {
      // Update total offers count
      const totalOffersEl = document.querySelector('[data-stat="total-offers"]');
      if (totalOffersEl) {
        const currentCount = parseInt(totalOffersEl.textContent) || 0;
        totalOffersEl.textContent = currentCount + offers.length;
      }

      // Update accepted offers if any
      const acceptedOffers = offers.filter(o => o.status === 'accepted');
      if (acceptedOffers.length > 0) {
        const acceptedEl = document.querySelector('[data-stat="accepted-offers"]');
        if (acceptedEl) {
          const currentCount = parseInt(acceptedEl.textContent) || 0;
          acceptedEl.textContent = currentCount + acceptedOffers.length;
        }
      }

      showNotification(`${offers.length} new offer update${offers.length > 1 ? 's' : ''}!`, 'success');
    }

    function updateOrderStats(orders) {
      // Update completed orders count
      const completedOrders = orders.filter(o => o.status === 'completed');
      if (completedOrders.length > 0) {
        const completedEl = document.querySelector('[data-stat="completed-orders"]');
        if (completedEl) {
          const currentCount = parseInt(completedEl.textContent) || 0;
          completedEl.textContent = currentCount + completedOrders.length;
        }

        // Update total spent
        const totalSpent = completedOrders.reduce((sum, order) => sum + parseFloat(order.amount || 0), 0);
        const spentEl = document.querySelector('[data-stat="total-spent"]');
        if (spentEl) {
          const currentSpent = parseFloat(spentEl.textContent.replace(/[$,]/g, '')) || 0;
          spentEl.textContent = '$' + (currentSpent + totalSpent).toLocaleString('en-US', { minimumFractionDigits: 2 });$' + (currentSpent + totalSpent).toLocaleString('en-US', { minimumFractionDigits: 2 });
        }
      }
      
      showNotification(`${orders.length} new order update${orders.length > 1 ? 's' : ''}!`, 'info');
    }

    function addRecentOffers(offers) {
      const container = document.querySelector('#recent-offers-container');
      if (!container) return;

      offers.slice(0, 2).forEach(offer => {
        const statusColors = {
          'pending': 'bg-yellow-100 text-yellow-700',
          'accepted': 'bg-green-100 text-green-700',
          'rejected': 'bg-red-100 text-red-700'
        };

        const card = document.createElement('div');
        card.className = 'bg-gradient-to-br from-blue-50 to-purple-50 rounded-xl p-4 border border-blue-200 animate-fade-in';
        card.style.backgroundColor = '#dbeafe';

        card.innerHTML = `
      <div class="flex justify-between items-start mb-2">
        <h3 class="font-semibold text-gray-900">${offer.listing_name || 'Listing'}</h3>
        <span class="text-xs px-2 py-1 rounded-full ${statusColors[offer.status] || 'bg-gray-100 text-gray-700'}">
          ${offer.status}
        </span>
      </div>
      <div class="text-sm text-gray-600">
        <div><i class="fas fa-dollar-sign mr-1"></i>$${parseFloat(offer.amount).toLocaleString()}</div>
        <div><i class="fas fa-user mr-1"></i>${offer.seller_name || 'Seller'}</div>
      </div>
    `;

        container.insertBefore(card, container.firstChild);

        setTimeout(() => {
          card.style.backgroundColor = '';
        }, 3000);
      });
    }

    function addRecentOrders(orders) {
      const container = document.querySelector('#recent-orders-container');
      if (!container) return;

      orders.slice(0, 2).forEach(order => {
        const statusColors = {
          'pending_payment': 'bg-yellow-100 text-yellow-700',
          'paid': 'bg-blue-100 text-blue-700',
          'completed': 'bg-green-100 text-green-700',
          'cancelled': 'bg-red-100 text-red-700'
        };

        const card = document.createElement('div');
        card.className = 'bg-white rounded-xl p-4 border border-gray-200 shadow-sm animate-fade-in';
        card.style.backgroundColor = '#f0f9ff';

        card.innerHTML = `
      <div class="flex justify-between items-start mb-2">
        <h3 class="font-semibold text-gray-900">${order.listing_name || 'Listing'}</h3>
        <span class="text-xs px-2 py-1 rounded-full ${statusColors[order.status] || 'bg-gray-100 text-gray-700'}">
          ${order.status}
        </span>
      </div>
      <div class="text-sm text-gray-600">
        <div><i class="fas fa-dollar-sign mr-1"></i>$${parseFloat(order.amount).toLocaleString()}</div>
        <div><i class="fas fa-calendar mr-1"></i>${new Date(order.created_at).toLocaleDateString()}</div>
      </div>
    `;

        container.insertBefore(card, container.firstChild);

        setTimeout(() => {
          card.style.backgroundColor = '';
        }, 3000);
      });
    }

    function showNotification(message, type = 'info') {
      const colors = {
        info: 'bg-blue-500',
        success: 'bg-green-500',
        warning: 'bg-yellow-500',
        error: 'bg-red-500'
      };

      const notification = document.createElement('div');
      notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-4 py-3 rounded-lg shadow-lg z-50 animate-fade-in`;
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
  </script>
</body>

</html>