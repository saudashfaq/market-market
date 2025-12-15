<?php
require_once __DIR__ . "/../../middlewares/auth.php";

require_login();

$page = $_GET['page'] ?? 'welcome';
$role = user_role();
$user = current_user();

$profilePic = $user['profile_pic'] ?? null;

// Handle AJAX POST requests before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  // Include the appropriate handler based on the page
  if ($page === 'addAdminSuperAdmin' && in_array($_POST['action'], ['add', 'update', 'delete'])) {
    // Clear any output buffers
    while (ob_get_level()) {
      ob_end_clean();
    }
    require_once __DIR__ . '/addAdminSuperAdmin.php';
    // File will exit() after handling POST, so this line won't be reached
  }
}

// Include global header (handled by public/index.php)
// require_once __DIR__ . '/../../includes/header.php';
?>

<div class="relative min-h-screen bg-gradient-to-b from-white via-gray-50 to-gray-100 font-inter overflow-x-hidden">
  <!-- 🌈 Animated Background -->
  <div class="absolute inset-0 overflow-hidden -z-10">
    <div class="absolute -top-40 -left-40 w-[400px] h-[400px] bg-blue-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-pulse"></div>
    <div class="absolute -bottom-40 -right-40 w-[400px] h-[400px] bg-purple-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-pulse"></div>
    <div class="absolute top-1/3 left-1/3 w-[300px] h-[300px] bg-pink-200 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-pulse"></div>
  </div>

  <style>
    @keyframes pulse {

      0%,
      100% {
        opacity: 0.3;
        transform: scale(1);
      }

      50% {
        opacity: 0.5;
        transform: scale(1.05);
      }
    }

    .animate-pulse {
      animation: pulse 8s infinite;
    }
  </style>

  <!-- 🧭 Sidebar + Main Wrapper -->
  <div class="flex flex-col lg:flex-row">

    <!-- 🧭 Sidebar -->
    <?php include "sidebar.php"; ?>

    <!-- 📄 Main Content -->
    <main class="flex-1 min-h-screen overflow-y-auto transition-all duration-300">
      <div class="p-6 lg:p-6 pt-2 lg:pt-6">
        <?php
        if ($page == 'userDashboard') {
          include "userDashboard.php";
        } elseif ($page == 'adminDashboard') {
          include "adminDashboard.php";
        } elseif ($page == 'superAdminDashboard') {
          include "superAdminDashboard.php";
        } elseif ($page == 'categories') {
          include "categories.php";
        } elseif ($page == 'superAdminPayment') {
          include "superAdminPayment.php";
        } elseif ($page == 'superAdminOffers') {
          include "superAdminOffers.php";
        } elseif ($page == 'superAdminDelligence') {
          include "superAdminDelligence.php";
        } elseif ($page == 'superAdminQuestion') {
          include "superAdminQuestion.php";
        } elseif ($page == 'superAdminReports') {
          include "superAdminReports.php";
        } elseif ($page == 'superAdminDisputes') {
          include "superAdminDisputes.php";
        } elseif ($page == 'superAdminAudit') {
          include "superAdminAudit.php";
        } elseif ($page == 'listingverification') {
          include "listingverification.php";
        } elseif ($page == 'offers') {
          include "offers.php";
        } elseif ($page == 'addAdminSuperAdmin') {
          include "addAdminSuperAdmin.php";
        } elseif ($page == 'biddingDashboard') {
          // Only allow superadmin to access bidding dashboard
          if ($role === 'superadmin') {
            include "biddingDashboard.php";
          } else {
            echo "<div class='bg-red-50 border border-red-200 rounded-lg p-6 text-center'>";
            echo "<i class='fas fa-lock text-red-500 text-4xl mb-4'></i>";
            echo "<h2 class='text-xl font-semibold text-red-700 mb-2'>Access Denied</h2>";
            echo "<p class='text-red-600'>You don't have permission to access the Bidding System.</p>";
            echo "<p class='text-sm text-red-500 mt-2'>This feature is only available for Super Administrators.</p>";
            echo "</div>";
          }
        } elseif ($page == 'adminPayments') {
          include "adminPayments.php";
        } elseif ($page == 'transferWorkflow') {
          include "transferWorkflow.php";
        } elseif ($page == 'my_listing') {
          include "my_listing.php";
        } elseif ($page == 'my_order') {
          include "my_order.php";
        } elseif ($page == 'message') {
          include __DIR__ . '/message.php';
        } elseif ($page == 'userCreatelisting') {
          include "userCreatelisting.php";
        } elseif ($page == 'profile') {
          include "profile.php";
        } elseif ($page == 'settings') {
          include "dashboard/settings.php";
        } elseif ($page == 'chat') {
          include "dashboard/chat.php";
        } elseif ($page == 'delete_listing') {
          include "delete_listing.php";
        } elseif ($page == 'offer_action') {
          include "offer_action.php";
        } elseif ($page == 'admin_listing_verification') {
          include "admin_listing_verification.php";
        } elseif ($page == 'adminreports') {
          include "adminreports.php";
        } elseif ($page == 'profile_update') {
          include "profile_update.php";
        } elseif ($page == 'save_category') {
          include "save_category.php";
        } elseif ($page == 'delete_category') {
          include "delete_category.php";
        } elseif ($page == 'update_category') {
          include "update_category.php";
        } elseif ($page == 'save_label') {
          include "save_label.php";
        } elseif ($page == 'delete_label') {
          include "delete_label.php";
        } elseif ($page == 'conversation_create') {
          include "conversation_create.php";
        } elseif ($page == 'mark_read') {
          include "mark_read.php";
        } elseif ($page == 'send_message') {
          include "send_message.php";
        } elseif ($page == 'submit_credentials') {
          include "submit_credentials.php";
        } elseif ($page == 'my_tickets') {
          include "my_tickets.php";
        } elseif ($page == 'create_ticket') {
          include "create_ticket.php";
        } elseif ($page == 'view_ticket') {
          include "view_ticket.php";
        } elseif ($page == 'admin_tickets') {
          include "admin_tickets.php";
        } elseif ($page == 'admin_ticket_view') {
          include "admin_ticket_view.php";
        } elseif ($page == 'view_credentials') {
          include "view_credentials.php";
        } elseif ($page == 'my_sales') {
          include "my_sales.php";
        } elseif ($page == 'purchases') {
          include "purchases.php";
        } elseif ($page == 'sent_credentials') {
          include "sent_credentials.php";
        } elseif ($page == 'received_credentials') {
          include "received_credentials.php";
        } elseif ($page == 'resend_email_verification') {
          include "resend_email_verification.php";
        } elseif ($page == 'cancel_email_change') {
          include "cancel_email_change.php";
        } elseif ($page == 'complete_order') {
          include "complete_order.php";
        } elseif ($page == 'payment_settings') {
          include "payment_settings.php";
        } elseif ($page == 'platform_earnings') {
          include "platform_earnings.php";
        } else {
          echo "<h1 class='text-2xl font-semibold text-gray-700'>Page Not Found</h1>";
        }
        ?>
      </div>
    </main>
  </div>
  <!-- 💡 Sidebar Toggle Logic -->
  <script>
    console.log('🚀 Script loaded - waiting for DOM');
    console.log('Current page:', '<?php echo $page; ?>');
    console.log('User role:', '<?php echo $role; ?>');

    // Wait for DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
      console.log('🔧 DOM Loaded - Initializing Sidebar Toggle');
      console.log('Document ready state:', document.readyState);

      const sidebar = document.getElementById('sidebar');
      const body = document.body;

      console.log('🔧 Sidebar Toggle Initialized');
      console.log('Sidebar element:', sidebar);
      console.log('Window width:', window.innerWidth);
      console.log('Is mobile view:', window.innerWidth < 1024);

      if (!sidebar) {
        console.error('❌ Sidebar element not found!');
        return;
      }

      // Mobile Navbar Sidebar Toggle Functionality
      function toggleMobileSidebar() {
        if (!sidebar || window.innerWidth >= 1024) return;

        console.log('🔘 Mobile sidebar toggle from navbar');
        sidebar.classList.toggle('show');
        body.classList.toggle('sidebar-open');

        if (sidebar.classList.contains('show')) {
          console.log('✅ Mobile sidebar opened from navbar');
        } else {
          console.log('✅ Mobile sidebar closed from navbar');
        }
      }

      // Listen for navbar toggle events (mobile only)
      window.addEventListener('toggleSidebar', (e) => {
        console.log('🔘 Received sidebar toggle event from:', e.detail?.source || 'unknown');
        if (window.innerWidth < 1024) {
          toggleMobileSidebar();
        }
      });

      // Desktop sidebar toggle functionality
      const desktopToggle = document.getElementById('desktopSidebarToggle');
      if (desktopToggle && sidebar) {
        console.log('✅ Adding click event listener to desktop toggle button');

        // Load saved state from localStorage
        const savedState = localStorage.getItem('sidebarCollapsed');
        if (savedState === 'true') {
          sidebar.classList.add('sidebar-collapsed');
          sidebar.classList.remove('sidebar-expanded');
          console.log('🔄 Restored collapsed state from localStorage');
        }

        desktopToggle.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();

          console.log('🔘 Desktop toggle button clicked!');
          console.log('Before toggle - sidebar classes:', sidebar.className);

          const isCollapsed = sidebar.classList.contains('sidebar-collapsed');

          if (isCollapsed) {
            // Expand sidebar
            sidebar.classList.remove('sidebar-collapsed');
            sidebar.classList.add('sidebar-expanded');
            localStorage.setItem('sidebarCollapsed', 'false');
            console.log('✅ Sidebar expanded');
          } else {
            // Collapse sidebar
            sidebar.classList.add('sidebar-collapsed');
            sidebar.classList.remove('sidebar-expanded');
            localStorage.setItem('sidebarCollapsed', 'true');
            console.log('✅ Sidebar collapsed');
          }

          console.log('After toggle - sidebar classes:', sidebar.className);
        });

        console.log('✅ Desktop click event listener added successfully');
      } else {
        // Only log if we expected it to be there - for now just suppress or log info
        console.log('ℹ️ Desktop sidebar toggle not present on this page');
      }

      // Close sidebar when clicking outside on mobile
      document.addEventListener('click', (e) => {
        // Define toggle button reference
        const toggle = document.getElementById('menu-btn') || document.getElementById('navbarSidebarToggle');

        if (window.innerWidth < 1024 && sidebar && toggle) {
          if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('show');
            body.classList.remove('sidebar-open');
            // toggle.style.display = 'flex'; // Not needed usually
            console.log('🔘 Sidebar closed - toggle button shown');
          }
        }
      });

      // Handle window resize
      window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024 && sidebar) {
          // Desktop view - remove mobile classes
          sidebar.classList.remove('show');
          body.classList.remove('sidebar-open');
          console.log('📱→💻 Switched to desktop view');
        }
      });

      console.log('✅ All event listeners initialized successfully');
    }); // End of DOMContentLoaded

    // Real-time sidebar badge update functionality
    async function updateSidebarMessageBadge() {
      try {
        const response = await fetch(`${window.BASE}public/index.php?p=get_unread_count`);
        if (response.ok) {
          const data = await response.json();
          if (data.success) {
            updateMessageBadge(data.unread_count);
          }
        }
      } catch (error) {
        console.error('Error updating sidebar badge:', error);
      }
    }

    function updateMessageBadge(count) {
      // Find the Messages link in sidebar
      const messageLinks = document.querySelectorAll('a[href*="page=message"]');

      messageLinks.forEach(link => {
        // Remove existing badge
        const existingBadge = link.querySelector('.bg-red-500');
        if (existingBadge) {
          existingBadge.remove();
        }

        // Add new badge if count > 0 and not on message page
        if (count > 0 && currentPage !== 'message') {
          const badge = document.createElement('span');
          badge.className = 'bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full min-w-[20px] h-5 flex items-center justify-center shadow-sm animate-pulse';
          badge.textContent = count > 99 ? '99+' : count;
          badge.title = count === 1 ? '1 person sent you a message' : count + ' people sent you messages';

          // Make sure link has flex justify-between
          if (!link.classList.contains('justify-between')) {
            link.classList.add('justify-between');
          }

          link.appendChild(badge);
        } else {
          // Remove justify-between if no badge
          link.classList.remove('justify-between');
        }
      });
    }

    // Add mouse movement detection for more frequent updates
    let lastActivity = Date.now();
    document.addEventListener('mousemove', () => {
      lastActivity = Date.now();
    });

    // More frequent updates when user is active
    setInterval(() => {
      if (Date.now() - lastActivity < 30000 && currentPage !== 'message') { // Active in last 30 seconds
        updateSidebarMessageBadge();
      }
    }, 2000); // Check every 2 seconds when active

    // Check if we're on the message page
    const currentPage = "<?php echo $page; ?>";

    // If on message page, clear badge immediately and don't show it
    if (currentPage === 'message') {
      updateMessageBadge(0);
      // Don't update badge while on message page
    } else {
      // Update badge every 5 seconds for real-time updates
      setInterval(updateSidebarMessageBadge, 5000);

      // Update badge when page loads
      document.addEventListener('DOMContentLoaded', updateSidebarMessageBadge);

      // Update badge when returning to page (visibility change)
      document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
          updateSidebarMessageBadge();
        }
      });

      // Initial update
      updateSidebarMessageBadge();
    }
  </script>

  <style>
    /* 🧾 Sidebar Scroll Fix */
    #sidebar {
      width: 16rem;
      /* Tailwind w-64 */
      height: auto;
      overflow-y: auto;
      z-index: 500;
    }

    #sidebar::-webkit-scrollbar {
      width: 6px;
    }

    #sidebar::-webkit-scrollbar-thumb {
      background-color: rgba(100, 100, 100, 0.3);
      border-radius: 4px;
    }

    /* ✅ Desktop view — make sidebar sticky */
    @media (min-width: 1024px) {
      #sidebar {
        position: sticky !important;
        top: 4px !important;
        /* Navbar height approx */
        height: 200vh ;
        transform: none !important;
        z-index: 40 !important;
        overflow-y: auto !important;
      }
    }

    /* 📱 Mobile sidebar (slide-in) */
    @media (max-width: 1023px) {
      #sidebar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        height: 100vh !important;
        transform: translateX(-100%) !important;
        transition: transform 0.3s ease-in-out !important;
        z-index: 9999 !important;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
      }

      #sidebar.show {
        transform: translateX(0) !important;
      }

      body.sidebar-open {
        overflow: hidden !important;
      }

      body.sidebar-open::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9998;
        backdrop-filter: blur(4px);
      }
    }

    footer {
      z-index: 10 !important;
    }
  </style>

  <!-- Polling System -->
  <!-- BASE constant already defined above -->
  <!-- polling.js will be loaded dynamically by individual pages as needed -->

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>