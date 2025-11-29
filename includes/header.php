<?php
require_once __DIR__ . "/../middlewares/auth.php";
require_once __DIR__ . "/flash_helper.php";
require_once __DIR__ . "/popup_helper.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>MarketPlace</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
    rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="<?= BASE ?>js/popup.js"></script>
  <?php if (is_logged_in()): ?>
  <script>
    // Define API base path globally for JavaScript
    // Use relative path from current location
    (function() {
      const currentPath = window.location.pathname;
      const baseUrl = window.location.origin;
      
      // Detect base directory
      let basePath = '';
      
      if (currentPath.includes('/public/')) {
        // We're in public folder, go up one level
        basePath = currentPath.substring(0, currentPath.indexOf('/public/'));
      } else if (currentPath.includes('/index.php')) {
        // We're at root with index.php
        basePath = currentPath.substring(0, currentPath.indexOf('/index.php'));
      } else if (currentPath.includes('/modules/')) {
        // We're in modules folder
        basePath = currentPath.substring(0, currentPath.indexOf('/modules/'));
      } else {
        // Default: assume root
        basePath = '';
      }
      
      // Set API_BASE_PATH as relative path (works better with nginx)
      window.API_BASE_PATH = basePath + '/api';
      
      console.log('🔧 Current Path:', currentPath);
      console.log('🔧 Base Path:', basePath);
      console.log('🔧 API_BASE_PATH:', window.API_BASE_PATH);
      console.log('🔧 Test URL:', baseUrl + window.API_BASE_PATH + '/notifications_api.php');
    })();
  </script>
  <script src="<?= BASE ?>js/notifications.js"></script>
  <script src="<?= BASE ?>js/path-detector.js"></script>
  <script src="<?= BASE ?>js/polling.js"></script>
  <script src="<?= BASE ?>js/polling-init.js"></script>
  <script src="<?= BASE ?>js/polling-debug.js"></script>
  <?php endif; ?>
  <script src="<?= BASE ?>js/logout-confirmation.js" defer></script>
  <style>
    @keyframes slideUpFade {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    .animate-slideUpFade {
      animation: slideUpFade 0.25s ease-out forwards;
    }
    /* Mobile menu animation */
    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .animate-slideDown {
      animation: slideDown 0.3s ease-out forwards;
    }

    /* Enhanced responsive navbar styles */
    @media (min-width: 768px) and (max-width: 1023px) {
      /* Tablet/Small Desktop specific styles */
      .navbar-container {
        padding-left: 1rem;
        padding-right: 1rem;
      }
      
      .navbar-logo {
        font-size: 1.25rem;
      }
    }

    @media (min-width: 1024px) and (max-width: 1279px) {
      /* Medium Desktop specific styles */
      .navbar-nav-item {
        font-size: 0.875rem;
        padding: 0.5rem 0.75rem;
      }
      
      .navbar-button {
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
      }
    }

    /* Ensure proper spacing and prevent overflow */
    .navbar-container {
      max-width: 100%;
    }

    .navbar-nav {
      flex-wrap: nowrap;
      overflow-x: auto;
      scrollbar-width: none;
      -ms-overflow-style: none;
    }

    .navbar-nav::-webkit-scrollbar {
      display: none;
    }

    /* Custom scrollbar for notifications */
    .scrollbar-thin {
      scrollbar-width: thin;
      scrollbar-color: #d1d5db #f3f4f6;
    }

    .scrollbar-thin::-webkit-scrollbar {
      width: 6px;
    }

    .scrollbar-thin::-webkit-scrollbar-track {
      background: #f3f4f6;
      border-radius: 3px;
    }

    .scrollbar-thin::-webkit-scrollbar-thumb {
      background: #d1d5db;
      border-radius: 3px;
    }

    .scrollbar-thin::-webkit-scrollbar-thumb:hover {
      background: #9ca3af;
    }

    /* Simple notification dropdown - no complex styling needed */
  </style>
</head>

<body>
  <!-- Navbar -->
  <header class="text-gray-600 body-font border-b sticky top-0 z-50 bg-white shadow-sm">
    <div class="navbar-container container mx-auto flex flex-wrap p-3 sm:p-4 flex-row items-center justify-between">
      <!-- Logo and Sidebar Toggle -->
      <div class="flex items-center space-x-3">
        <a href="./index.php?p=home" class="flex title-font font-medium items-center text-gray-900 flex-shrink-0">
          <span class="navbar-logo ml-1 text-xl sm:text-2xl font-bold text-blue-600">Market<span class="text-purple-600">Place</span></span>
        </a>
        
        <!-- Mobile Sidebar Toggle Button (only show on dashboard pages in mobile) -->
        <?php 
        $currentPath = $_GET['p'] ?? '';
        if ($currentPath === 'dashboard' && is_logged_in()): 
        ?>
          <button 
            id="navbarSidebarToggle" 
            class="flex lg:hidden w-9 h-9 bg-gradient-to-br from-blue-50 to-indigo-50 hover:from-blue-100 hover:to-indigo-100 border border-blue-200 rounded-lg items-center justify-center transition-all duration-200 group shadow-sm"
            title="Open Menu"
            aria-label="Toggle Sidebar"
          >
            <i class="fa-solid fa-bars text-blue-600 text-base group-hover:text-blue-700 transition-colors duration-200"></i>
          </button>
        <?php endif; ?>
      </div>

      <!-- Mobile/Tablet Menu Button (shows on screens smaller than 1024px) -->
      <div class="flex items-center space-x-3 sm:space-x-4 lg:hidden">
        <?php if (is_logged_in()): ?>
          <!-- Mobile Notification Bell -->
          <div class="relative">
            <button id="mobileNotificationBtn" class="relative text-gray-700 hover:text-gray-900 text-lg sm:text-xl focus:outline-none">
              <i class="fa-solid fa-bell"></i>
              <?php
              if (!function_exists('getUnreadCount')) {
                  require_once __DIR__ . '/notification_helper.php';
              }
              $mobileUnreadCount = is_logged_in() ? getUnreadCount(current_user()['id']) : 0;
              ?>
              <span id="mobileNotificationBadge" class="absolute -top-1 -right-2 flex h-4 w-4 items-center justify-center rounded-full text-[10px] text-white font-bold <?= $mobileUnreadCount > 0 ? '' : 'hidden' ?>" style="background-color: red !important;">
                <?= $mobileUnreadCount ?>
              </span>
            </button>

            <!-- Mobile Notification Dropdown -->
            <div id="mobileNotificationDropdown" class="hidden absolute right-0 mt-3 w-80 bg-white border border-gray-200 rounded-2xl shadow-2xl overflow-hidden z-50 animate-slideUpFade">
              <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-4 py-3 text-white font-semibold text-sm flex justify-between items-center">
                <span>Notifications</span>
                <button class="mark-all-read-btn text-xs text-white/80 hover:text-white transition">Mark all as read</button>
              </div>

              <ul id="mobileNotificationList" class="max-h-80 overflow-y-auto divide-y divide-gray-100">
                <li class="p-8 text-center text-gray-500">
                  <i class="fa-solid fa-spinner fa-spin text-2xl mb-2"></i>
                  <p class="text-sm">Loading...</p>
                </li>
              </ul>
            </div>
          </div>
        <?php endif; ?>
        
        <!-- Mobile Menu Toggle -->
        <button id="menu-btn" class="text-xl sm:text-2xl text-gray-700 focus:outline-none">
          <i class="fa-solid fa-bars"></i>
        </button>
      </div>

      <!-- Desktop Menu (shows on screens 1024px and larger) -->
      <nav class="navbar-nav hidden lg:flex items-center space-x-4 xl:space-x-6 text-sm xl:text-base text-gray-700">
        <?php if (is_logged_in()): ?>
          <a href="index.php?p=listing" class="hover:text-gray-900 whitespace-nowrap">Browse Listings</a>
          <a href="index.php?p=sellingOption" class="hover:text-gray-900 whitespace-nowrap">Sell Your Asset</a>
          <a href="index.php?p=wishlist" class="hover:text-gray-900 whitespace-nowrap">Saved</a>
          <?php 
            $user = current_user();
            $userRole = $user['role'] ?? 'user';
            if ($userRole === 'superadmin') {
              $dashboardPage = 'superAdminDashboard';
            } elseif ($userRole === 'admin' || $userRole === 'admin_staff') {
              $dashboardPage = 'adminDashboard';
            } else {
              $dashboardPage = 'userDashboard';
            }
          ?>
          <a href="index.php?p=dashboard&page=<?= $dashboardPage ?>" class="hover:text-gray-900 whitespace-nowrap">Dashboard</a>
        <?php endif; ?>
      </nav>

      <!-- Desktop Right Icons (shows on screens 1024px and larger) -->
      <div class="hidden lg:flex items-center space-x-3 xl:space-x-5 flex-shrink-0">
        <?php
        if (is_logged_in()) { 
        ?>
          <div class="relative">
            <button id="notificationBtn" class="relative text-gray-700 hover:text-gray-900 text-lg xl:text-xl focus:outline-none">
              <i class="fa-solid fa-bell"></i>
              <?php
              if (!function_exists('getUnreadCount')) {
                  require_once __DIR__ . '/notification_helper.php';
              }
              $unreadCount = is_logged_in() ? getUnreadCount(current_user()['id']) : 0;
              ?>
              <span id="notificationBadge" class="absolute -top-1 -right-2 flex h-4 w-4 items-center justify-center rounded-full text-[10px] text-white font-bold <?= $unreadCount > 0 ? '' : 'hidden' ?>" style="background-color: red !important;">
                <?= $unreadCount ?>
              </span>
            </button>

            <!-- Dropdown -->
            <div id="notificationDropdown" class="hidden absolute right-0 mt-3 w-80 bg-white border border-gray-200 rounded-2xl shadow-2xl overflow-hidden z-50 animate-slideUpFade">
              <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-4 py-3 text-white font-semibold text-sm flex justify-between items-center">
                <span>Notifications</span>
                <button class="mark-all-read-btn text-xs text-white/80 hover:text-white transition">Mark all as read</button>
              </div>

              <ul id="notificationList" class="max-h-80 overflow-y-auto divide-y divide-gray-100">
                <li class="p-8 text-center text-gray-500">
                  <i class="fa-solid fa-spinner fa-spin text-2xl mb-2"></i>
                  <p class="text-sm">Loading...</p>
                </li>
              </ul>
            </div>
          </div>
        <?php
        } 
        ?>

        <!-- User/Login -->
        <?php if (is_logged_in()): ?>
          <?php 
            $user = current_user();
            $role = $user['role'] ?? 'user';
          ?>
          <a 
            href="<?php 
              if ($role === 'superadmin') {
                echo 'index.php?p=dashboard&page=superAdminDashboard';
              } elseif ($role === 'admin') {
                echo 'index.php?p=dashboard&page=adminDashboard';
              } else {
                echo 'index.php?p=dashboard&page=userDashboard';
              }
            ?>" 
            class="flex items-center gap-1 text-gray-700 hover:text-gray-900 text-lg xl:text-xl"
          >
            <i class="fa-regular fa-user"></i>
          </a>
        <?php else: ?>
          <a href="./index.php?p=login" class="flex items-center gap-1 text-gray-700 hover:text-gray-900 text-sm xl:text-base whitespace-nowrap">
            <i class="fa-solid fa-right-to-bracket"></i> <span class="hidden xl:inline">Login</span>
          </a>
        <?php endif; ?>

        <!-- Start Selling Button -->
        <?php if (is_logged_in()): ?>
          <a href="./index.php?p=sellingOption"
            class="px-3 xl:px-4 py-2 rounded-lg text-white font-medium bg-gradient-to-r from-indigo-500 to-purple-500 hover:opacity-90 transition text-sm xl:text-base whitespace-nowrap">
            Start Selling
          </a>
        <?php else: ?>
          <a href="./index.php?p=login"
            class="px-3 xl:px-4 py-2 rounded-lg text-white font-medium bg-gradient-to-r from-indigo-500 to-purple-500 hover:opacity-90 transition text-sm xl:text-base whitespace-nowrap">
            Start Selling
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Mobile Menu (shows on screens smaller than 1024px) -->
    <div id="mobile-menu" class="hidden lg:hidden bg-white shadow-lg animate-slideDown">
      <div class="container mx-auto px-4 py-3">
        <div class="flex flex-col space-y-1">
          <?php if (is_logged_in()): ?>
            <a href="index.php?p=listing" class="py-3 px-2 text-gray-700 hover:bg-gray-100 rounded-lg transition flex items-center">
              <i class="fa-solid fa-compass mr-3 text-gray-500"></i> Browse Listings
            </a>
            <a href="index.php?p=wishlist" class="py-3 px-2 text-gray-700 hover:bg-gray-100 rounded-lg transition flex items-center">
              <i class="fa-solid fa-bookmark mr-3 text-gray-500"></i> Saved
            </a>
          <?php endif; ?>
          <a href="#" class="py-3 px-2 text-gray-700 hover:bg-gray-100 rounded-lg transition flex items-center">
            <i class="fa-solid fa-circle-info mr-3 text-gray-500"></i> About
          </a>
          <a href="#" class="py-3 px-2 text-gray-700 hover:bg-gray-100 rounded-lg transition flex items-center">
            <i class="fa-solid fa-address-book mr-3 text-gray-500"></i> Contact
          </a>
          
          <div class="border-t my-2"></div>
          
          <?php if (is_logged_in()): ?>
            <?php
            $user = current_user();
            $role = $user['role'] ?? 'user';

            switch ($role) {
              case 'superadmin':
                $dashboardLink = 'index.php?p=dashboard&page=superAdminDashboard';
                break;
              case 'admin':
                $dashboardLink = 'index.php?p=dashboard&page=adminDashboard';
                break;
              default:
                $dashboardLink = 'index.php?p=dashboard&page=userDashboard';
                break;
            }
            ?>
            <a href="<?= htmlspecialchars($dashboardLink) ?>" class="py-3 px-2 text-gray-700 hover:bg-gray-100 rounded-lg transition flex items-center">
              <i class="fa-regular fa-user mr-3 text-gray-500"></i> Dashboard
            </a>
          <?php else: ?>
            <a href="./index.php?p=login" class="py-3 px-2 text-gray-700 hover:bg-gray-100 rounded-lg transition flex items-center">
              <i class="fa-solid fa-right-to-bracket mr-3 text-gray-500"></i> Login
            </a>
          <?php endif; ?>
          
          <div class="pt-2">
            <?php if (is_logged_in()): ?>
              <a href="./index.php?p=sellingOption"
                class="block w-full text-center px-4 py-3 rounded-lg text-white font-medium bg-gradient-to-r from-indigo-500 to-purple-500 hover:opacity-90 transition">
                Start Selling
              </a>
            <?php else: ?>
              <a href="./index.php?p=login"
                class="block w-full text-center px-4 py-3 rounded-lg text-white font-medium bg-gradient-to-r from-indigo-500 to-purple-500 hover:opacity-90 transition">
                Start Selling
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Flash Messages -->
  <?php displayFlashMessages(); ?>
  
  <!-- Popup Messages -->
  <?php renderPopup(); ?>

  <!-- JS -->
  <script>
    const menuBtn = document.getElementById("menu-btn");
    const mobileMenu = document.getElementById("mobile-menu");
    const btn = document.getElementById("notificationBtn");
    const dropdown = document.getElementById("notificationDropdown");
    const mobileNotificationBtn = document.getElementById("mobileNotificationBtn");
    const mobileNotificationDropdown = document.getElementById("mobileNotificationDropdown");

    // Mobile menu toggle
    menuBtn.addEventListener("click", () => {
      mobileMenu.classList.toggle("hidden");
      if (!mobileMenu.classList.contains("hidden")) {
        mobileMenu.classList.add("animate-slideDown");
      }
    });

    // Notification toggle functions (fallback if notifications.js not loaded yet)
    function toggleNotificationDropdown(e) {
      e.preventDefault();
      e.stopPropagation();
      const dropdown = document.getElementById('notificationDropdown');
      const isHidden = dropdown.classList.contains('hidden');
      
      dropdown.classList.toggle('hidden');
      dropdown.classList.toggle('animate-slideUpFade');
      
      // Load notifications if opening
      if (isHidden) {
        console.log('Dropdown opening, loading notifications...');
        if (window.notificationManager) {
          console.log('Using notificationManager');
          window.notificationManager.loadNotifications('notificationList');
        } else {
          console.log('notificationManager not found, loading directly');
          loadNotificationsDirectly('notificationList');
        }
      }
    }
    
    // Direct notification loading (fallback)
    function loadNotificationsDirectly(listId) {
      fetch('/marketplace/api/notifications_api.php?action=list&limit=10')
        .then(response => response.json())
        .then(data => {
          console.log('Direct API response:', data);
          if (data.success) {
            renderNotificationsSimple(data.notifications, listId);
          }
        })
        .catch(error => {
          console.error('Direct API error:', error);
        });
    }
    
    // Simple notification renderer
    function renderNotificationsSimple(notifications, listId) {
      const list = document.getElementById(listId);
      if (!list) return;
      
      if (notifications.length === 0) {
        list.innerHTML = `
          <li class="p-8 text-center text-gray-500">
            <i class="fa-solid fa-bell-slash text-3xl mb-2"></i>
            <p class="text-sm">No notifications yet</p>
          </li>
        `;
        return;
      }
      
      list.innerHTML = notifications.map(notif => {
        const unreadClass = notif.is_read == 0 ? 'bg-blue-50' : '';
        return `
          <li class="flex items-start gap-3 p-4 hover:bg-gray-50 transition ${unreadClass}">
            <div class="w-10 h-10 bg-blue-100 text-blue-600 flex items-center justify-center rounded-full flex-shrink-0">
              <i class="fa-solid fa-bell"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm text-gray-800 font-medium">${notif.title}</p>
              <p class="text-xs text-gray-600 mt-0.5">${notif.message}</p>
            </div>
          </li>
        `;
      }).join('');
    }
    
    function toggleMobileNotificationDropdown(e) {
      e.preventDefault();
      e.stopPropagation();
      const dropdown = document.getElementById('mobileNotificationDropdown');
      const isHidden = dropdown.classList.contains('hidden');
      
      dropdown.classList.toggle('hidden');
      dropdown.classList.toggle('animate-slideUpFade');
      
      // Load notifications if opening
      if (isHidden) {
        if (window.notificationManager) {
          window.notificationManager.loadNotifications('mobileNotificationList');
        } else {
          loadNotificationsDirectly('mobileNotificationList');
        }
      }
    }
    
    // Close dropdowns on outside click
    document.addEventListener('click', (e) => {
      const desktopDropdown = document.getElementById('notificationDropdown');
      const mobileDropdown = document.getElementById('mobileNotificationDropdown');
      const desktopBtn = document.getElementById('notificationBtn');
      const mobileBtn = document.getElementById('mobileNotificationBtn');
      
      if (desktopDropdown && desktopBtn && 
          !desktopBtn.contains(e.target) && 
          !desktopDropdown.contains(e.target)) {
        desktopDropdown.classList.add('hidden');
      }
      
      if (mobileDropdown && mobileBtn && 
          !mobileBtn.contains(e.target) && 
          !mobileDropdown.contains(e.target)) {
        mobileDropdown.classList.add('hidden');
      }
    });

    // Navbar Sidebar Toggle Functionality
    const navbarSidebarToggle = document.getElementById('navbarSidebarToggle');
    if (navbarSidebarToggle) {
      console.log('✅ Navbar sidebar toggle button found');
      
      navbarSidebarToggle.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('🔘 Navbar sidebar toggle clicked - dispatching event');
        
        // Dispatch custom event that dashboard can listen to
        const toggleEvent = new CustomEvent('toggleSidebar', {
          detail: { source: 'navbar' },
          bubbles: true
        });
        window.dispatchEvent(toggleEvent);
        
        console.log('✅ Toggle event dispatched');
      });
    } else {
      console.log('ℹ️ Navbar sidebar toggle button not found (not on dashboard page)');
    }
  </script>
</body>

</html>