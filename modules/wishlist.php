<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middlewares/auth.php';

require_login();
$pdo = db();
$user = current_user();

// Get user's wishlist
$stmt = $pdo->prepare("
    SELECT l.*, 
           w.created_at as added_to_wishlist,
           (SELECT file_path FROM listing_proofs WHERE listing_id = l.id LIMIT 1) AS proof_image
    FROM wishlist w
    JOIN listings l ON w.listing_id = l.id
    WHERE w.user_id = ? AND l.status = 'approved'
    ORDER BY w.created_at DESC
");
$stmt->execute([$user['id']]);
$wishlist_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 to-white">
  <div class="container mx-auto px-6 py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
          <i class="fas fa-bookmark text-blue-600"></i>
          Saved Listings
        </h1>
        <p class="text-gray-600 mt-2">Your bookmarked listings (<?= count($wishlist_items) ?> items)</p>
      </div>
      <a href="index.php?p=listing" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
        <i class="fas fa-plus"></i>
        Browse More Listings
      </a>
    </div>

    <?php if (empty($wishlist_items)): ?>
      <!-- Empty State -->
      <div class="text-center py-16">
        <div class="max-w-md mx-auto">
          <i class="fas fa-bookmark text-6xl text-gray-300 mb-6"></i>
          <h3 class="text-xl font-semibold text-gray-700 mb-4">No saved listings yet</h3>
          <p class="text-gray-500 mb-8">Start bookmarking listings you're interested in to keep track of them easily.</p>
          <a href="index.php?p=listing" class="bg-gradient-to-r from-blue-500 to-purple-500 text-white px-8 py-3 rounded-lg hover:opacity-90 transition-opacity inline-flex items-center gap-2">
            <i class="fas fa-search"></i>
            Explore Listings
          </a>
        </div>
      </div>
    <?php else: ?>
      <!-- Wishlist Grid -->
      <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($wishlist_items as $listing): ?>
          <div class="group relative rounded-2xl border border-gray-200 shadow-md hover:shadow-lg transition-all duration-500 overflow-hidden hover:-translate-y-2 bg-white">
            <!-- Image -->
            <div class="relative h-56 overflow-hidden bg-gray-100">
              <?php if (!empty($listing['proof_image'])): ?>
                <img src="<?= htmlspecialchars($listing['proof_image']) ?>" alt="<?= htmlspecialchars($listing['name']) ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
              <?php else: ?>
                <div class="w-full h-full flex items-center justify-center bg-gray-200 text-gray-500 text-sm">No Image</div>
              <?php endif; ?>

              <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

              <!-- Remove from Saved Button -->
              <button onclick="toggleWishlist(<?= $listing['id'] ?>, this)"
                class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/90 backdrop-blur-sm shadow-lg hover:bg-white transition-all duration-300 flex items-center justify-center group"
                data-listing-id="<?= $listing['id'] ?>"
                data-in-wishlist="true">
                <i class="fas fa-bookmark text-blue-600 text-lg group-hover:scale-110 transition-transform"></i>
              </button>

              <!-- Added Date -->
              <div class="absolute bottom-4 left-4 bg-black/70 text-white text-xs px-3 py-1 rounded-full">
                <i class="fas fa-calendar mr-1"></i>
                Added <?= date('M j', strtotime($listing['added_to_wishlist'])) ?>
              </div>
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

              <!-- Action Buttons -->
              <div class="flex gap-2">
                <button onclick="showBuyNowPopup(<?= $listing['id'] ?>, '<?= htmlspecialchars($listing['name']) ?>', '<?= htmlspecialchars($listing['asking_price']) ?>')" class="flex-1 bg-gradient-to-r from-blue-500 to-purple-500 text-white text-sm font-bold py-3 rounded-md hover:opacity-90 transition-opacity shadow-md">
                  <i class="fa-solid fa-shopping-cart mr-2"></i> Buy Now
                </button>

                <button onclick="window.location.href='index.php?p=dashboard&page=conversation_create&listing_id=<?= $listing['id'] ?>&seller_id=<?= $listing['user_id'] ?>'" class="flex-1 bg-gradient-to-r from-green-500 to-green-600 text-white text-sm font-semibold py-3 rounded-md hover:opacity-90 transition-opacity">
                  <i class="fa-solid fa-handshake mr-2"></i> Make Offer
                </button>
              </div>

              <!-- View Details Button -->
              <div class="mt-2">
                <a href="./index.php?p=listingDetail&id=<?= $listing['id'] ?>" class="block w-full text-center border border-gray-300 text-gray-600 text-xs font-medium py-2 rounded-md hover:bg-gray-50 transition-colors">
                  <i class="fa-solid fa-eye mr-1"></i> View Details
                </a>
              </div>
            </div>

            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-red-500 to-pink-500 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
  // Include the same wishlist and popup functions from home.php
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
          if (data.in_wishlist) {
            icon.className = 'fas fa-bookmark text-blue-600 text-lg group-hover:scale-110 transition-transform';
            button.dataset.inWishlist = 'true';
            showWishlistNotification('Added to saved listings 📌', 'success');
          } else {
            // Remove the entire card with animation
            const card = button.closest('.group');
            card.style.transform = 'translateX(100%)';
            card.style.opacity = '0';
            setTimeout(() => {
              card.remove();
              // Update count
              const countElement = document.querySelector('p.text-gray-600');
              if (countElement) {
                const currentCount = parseInt(countElement.textContent.match(/\d+/)[0]);
                const newCount = currentCount - 1;
                countElement.textContent = `Your bookmarked listings (${newCount} items)`;

                // Show empty state if no items left
                if (newCount === 0) {
                  location.reload();
                }
              }
            }, 300);
            showWishlistNotification('Removed from saved listings', 'info');
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
      <span>${message}</span>
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

  // Placeholder functions for popups (these should be included from home.php or made global)
  function showBuyNowPopup(listingId, listingName, askingPrice) {
    // Redirect to listing detail for now
    window.location.href = `index.php?p=listingDetail&id=${listingId}`;
  }

  function showMakeOfferPopup(listingId, listingName, askingPrice) {
    // Redirect to make offer page for now
    window.location.href = `index.php?p=makeoffer&id=${listingId}`;
  }
</script>