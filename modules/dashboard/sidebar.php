<?php
require_once __DIR__ . "/../../middlewares/auth.php";
$role = user_role();
$user = current_user();

$profilePic = $user['profile_pic'] ?? null;

$currentPage = $_GET['page'] ?? '';

// Get unread conversations count (how many people sent messages)
$unreadCount = 0;
try {
  $pdo = db();
  $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT c.id) as unread_conversations
        FROM conversations c
        WHERE (c.buyer_id = ? OR c.seller_id = ?)
        AND EXISTS (
            SELECT 1 FROM messages m 
            WHERE m.conversation_id = c.id 
            AND m.sender_id != ? 
            AND m.is_read = 0
        )
    ");
  $stmt->execute([$user['id'], $user['id'], $user['id']]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $unreadCount = (int)$result['unread_conversations'];
} catch (Exception $e) {
  // If there's an error, just set count to 0
  $unreadCount = 0;
}

// Get awaiting credentials count
$awaitingCredentialsCount = 0;
try {
  $stmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM transactions t
        LEFT JOIN listing_credentials lc ON t.id = lc.transaction_id
        WHERE t.seller_id = ? AND t.status = 'paid' AND t.transfer_status = 'awaiting_credentials' AND lc.id IS NULL
    ");
  $stmt->execute([$user['id']]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $awaitingCredentialsCount = (int)$result['total'];
} catch (Exception $e) {
  $awaitingCredentialsCount = 0;
}
?>

<style>
  .sidebar-scroll::-webkit-scrollbar {
    display: none;
  }

  .sidebar-scroll {
    -ms-overflow-style: none;
    /* IE and Edge */
    scrollbar-width: none;
    /* Firefox */
  }
</style>

<aside
  id="sidebar"
  class="w-64 bg-white/95 backdrop-blur-xl border-r border-gray-100 shadow-xl flex flex-col">
  <!-- Mobile Close Button -->
  <div class="lg:hidden flex justify-end p-4 border-b border-gray-100">
    <button
      id="sidebarCloseBtn"
      class="w-8 h-8 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-lg flex items-center justify-center transition-all duration-200 group"
      title="Close Sidebar">
      <i class="fa-solid fa-xmark text-gray-600 text-sm group-hover:text-gray-800 transition-colors duration-200"></i>
    </button>
  </div>

  <!-- Profile Header -->
  <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-blue-50/60 to-white flex items-center gap-4">
    <div class="relative">
      <div class="w-12 h-12 rounded-full overflow-hidden bg-gradient-to-br from-blue-100 to-indigo-100 border border-gray-200 flex items-center justify-center shadow-sm">
        <?php if ($profilePic): ?>

          <img src="<?= BASE . htmlspecialchars($profilePic) ?>" alt="Profile Picture" class="w-full h-full object-cover">

        <?php else: ?>
          <i class="fa-solid fa-user text-blue-400 text-xl"></i>
        <?php endif; ?>
      </div>
      <span class="absolute bottom-0 right-0 bg-green-500 border-2 border-white w-3 h-3 rounded-full"></span>
    </div>
    <div class="min-w-0">
      <h3 class="text-base font-semibold text-gray-800 truncate"><?= htmlspecialchars($user['name'] ?? 'User') ?></h3>
      <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($user['email'] ?? '') ?></p>
    </div>
  </div>

  <!-- Menu -->
  <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto sidebar-scroll">
    <?php
    if ($role === 'user') {
      $menu = [
        ['page' => 'userDashboard', 'label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'color' => 'blue'],
        ['page' => 'my_listing', 'label' => 'My Listings', 'icon' => 'fa-store', 'color' => 'indigo'],
        ['page' => 'offers', 'label' => 'Offers', 'icon' => 'fa-tags', 'color' => 'yellow'],
        ['page' => 'my_order', 'label' => 'My Orders', 'icon' => 'fa-cart-shopping', 'color' => 'teal'],
        ['external' => './index.php?p=sellingOption', 'label' => 'Create Listing', 'icon' => 'fa-plus', 'color' => 'purple'],
        ['divider' => true],
        ['page' => 'my_sales', 'label' => 'My Sales', 'icon' => 'fa-exchange-alt', 'color' => 'purple', 'badge' => 'awaiting_credentials'],
        ['divider' => true],
        ['page' => 'payment_settings', 'label' => 'Payment Settings', 'icon' => 'fa-university', 'color' => 'green'],
        ['page' => 'my_tickets', 'label' => 'Support', 'icon' => 'fa-headset', 'color' => 'purple'],
        ['divider' => true],
        ['page' => 'profile', 'label' => 'Profile', 'icon' => 'fa-id-card', 'color' => 'gray'],
        ['page' => 'message', 'label' => 'Messages', 'icon' => 'fa-comment-dots', 'color' => 'blue']
      ];
    }

    if ($role === 'admin') {
      $menu = [
        ['page' => 'adminDashboard', 'label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'color' => 'blue'],
        ['page' => 'listingverification', 'label' => 'Listing Verification', 'icon' => 'fa-check-double', 'color' => 'green'],
        ['page' => 'transferWorkflow', 'label' => 'Transfer Workflow', 'icon' => 'fa-random', 'color' => 'teal'],
        ['page' => 'adminPayments', 'label' => 'Payments', 'icon' => 'fa-money-bill', 'color' => 'emerald'],
        ['page' => 'adminreports', 'label' => 'Reports & Logs', 'icon' => 'fa-file-alt', 'color' => 'indigo'],
        ['divider' => true],
        ['page' => 'profile', 'label' => 'Profile', 'icon' => 'fa-id-card', 'color' => 'gray'],
        ['page' => 'message', 'label' => 'Messages', 'icon' => 'fa-comment-dots', 'color' => 'blue']
      ];
    }

    if ($role === 'superadmin') {
      $menu = [
        ['page' => 'superAdminDashboard', 'label' => 'Dashboard', 'icon' => 'fa-gauge', 'color' => 'blue'],
        ['page' => 'superAdminAudit', 'label' => 'Audit', 'icon' => 'fa-magnifying-glass-chart', 'color' => 'indigo'],
        ['page' => 'superAdminDelligence', 'label' => 'Delligence', 'icon' => 'fa-briefcase', 'color' => 'green'],
        ['page' => 'superAdminDisputes', 'label' => 'Disputes', 'icon' => 'fa-scale-balanced', 'color' => 'orange'],
        ['page' => 'superAdminOffers', 'label' => 'Offers', 'icon' => 'fa-tags', 'color' => 'purple'],
        ['page' => 'addAdminSuperAdmin', 'label' => 'Team Access', 'icon' => 'fa-user-shield', 'color' => 'cyan'],
        ['page' => 'superAdminPayment', 'label' => 'Payment', 'icon' => 'fa-credit-card', 'color' => 'teal'],
        ['page' => 'platform_earnings', 'label' => 'Platform Earnings', 'icon' => 'fa-wallet', 'color' => 'green'],
        ['page' => 'superAdminQuestion', 'label' => 'Questions', 'icon' => 'fa-circle-question', 'color' => 'yellow'],
        ['page' => 'categories', 'label' => 'Categories', 'icon' => 'fa-layer-group', 'color' => 'pink'],
        ['page' => 'superAdminReports', 'label' => 'Reports', 'icon' => 'fa-file-lines', 'color' => 'red'],
        ['page' => 'biddingDashboard', 'label' => 'Bidding System', 'icon' => 'fa-gavel', 'color' => 'orange'],
        ['divider' => true],
        ['page' => 'admin_tickets', 'label' => 'Support Tickets', 'icon' => 'fa-headset', 'color' => 'purple'],
        ['divider' => true],
        ['page' => 'profile', 'label' => 'Profile', 'icon' => 'fa-id-card', 'color' => 'gray'],
        ['page' => 'message', 'label' => 'Messages', 'icon' => 'fa-comment-dots', 'color' => 'blue']
      ];
    }

    foreach ($menu as $item):
      if (isset($item['divider'])): ?>
        <hr class="my-3 border-gray-200">
      <?php else:
        $active = ($currentPage === ($item['page'] ?? ''));
        $href = isset($item['external'])
          ? $item['external']
          : "index.php?p=dashboard&page={$item['page']}";
      ?>
        <a href="<?= $href ?>"
          class="group flex items-center justify-between px-3 py-2.5 mx-1 rounded-lg font-medium transition-all duration-200
             <?= $active
                ? "bg-gradient-to-r from-{$item['color']}-50 to-{$item['color']}-100 text-{$item['color']}-700 shadow-sm border border-{$item['color']}-100"
                : "text-gray-700 hover:bg-gradient-to-r hover:from-{$item['color']}-50 hover:to-{$item['color']}-100 hover:text-{$item['color']}-700"
              ?>"
          title="<?= $item['label'] ?>">
          <div class="flex items-center">
            <div class="w-7 h-7 flex items-center justify-center rounded-lg mr-3 transition-colors
                <?= $active
                  ? "bg-{$item['color']}-100 text-{$item['color']}-600"
                  : "bg-gray-50 text-{$item['color']}-500 group-hover:bg-{$item['color']}-100 group-hover:text-{$item['color']}-600"
                ?>">
              <i class="fa-solid <?= $item['icon'] ?> text-sm"></i>
            </div>
            <?= $item['label'] ?>
          </div>
          <?php
          $badgeCount = 0;
          if (isset($item['badge'])) {
            if ($item['badge'] === 'awaiting_credentials') {
              $badgeCount = $awaitingCredentialsCount;
            }
          }
          if ($badgeCount > 0): ?>
            <span class="px-2 py-1 bg-red-500 text-white text-xs font-bold rounded-full">
              <?= $badgeCount ?>
            </span>
          <?php endif; ?>
        </a>
    <?php endif;
    endforeach;
    ?>

    <hr class="my-4 border-gray-200">
    <a href="#" onclick="confirmLogout(event)" class="group flex items-center px-3 py-2.5 mx-1 rounded-lg text-red-600 hover:bg-gradient-to-r hover:from-red-50 hover:to-red-100 transition" title="Logout">
      <div class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-50 text-red-500 group-hover:bg-red-100 group-hover:text-red-600 mr-3 transition">
        <i class="fa fa-sign-out-alt text-sm"></i>
      </div>
      Logout
    </a>
  </nav>
</aside>
<script>
  function confirmLogout(event) {
    if (event && event.preventDefault) {
      event.preventDefault();
    }

    console.log('Sidebar logout confirmation triggered');

    // Wait for popup system to be ready
    if (typeof showConfirm === 'undefined') {
      console.error('Popup system not loaded yet');
      setTimeout(() => confirmLogout(event), 100);
      return;
    }

    // Use showConfirm for better promise handling
    showConfirm('Are you sure you want to logout?', {
      title: 'Confirm Logout',
      confirmText: 'Yes, Logout',
      cancelText: 'Cancel'
    }).then(function(result) {
      console.log('Logout confirmation result:', result);
      if (result === true) {
        console.log('User confirmed logout - redirecting...');

        // Show loading message
        showSuccess('Logging out...', {
          title: 'Please wait',
          showConfirm: false,
          autoClose: true,
          autoCloseTime: 1000
        });

        // Redirect after short delay
        setTimeout(function() {
          window.location.href = './index.php?p=auth_logout';
        }, 500);
      } else {
        console.log('Logout cancelled by user');
      }
    }).catch(function(error) {
      console.error('Logout confirmation error:', error);
      // Fallback - direct redirect
      window.location.href = './index.php?p=auth_logout';
    });
  }

  // Mobile sidebar close functionality
  document.addEventListener('DOMContentLoaded', function() {
    const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
    const sidebar = document.getElementById('sidebar');
    const body = document.body;

    if (sidebarCloseBtn) {
      sidebarCloseBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        // Close sidebar on mobile
        if (window.innerWidth < 1024) {
          sidebar.classList.remove('show');
          body.classList.remove('sidebar-open');
          console.log('🔘 Sidebar closed via close button');

          // Dispatch sidebar state change event
          const stateEvent = new CustomEvent('sidebarStateChanged', {
            detail: {
              isOpen: false,
              source: 'close-button'
            }
          });
          window.dispatchEvent(stateEvent);
        }
      });
    }
  });
</script>