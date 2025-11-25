<?php
// ----------------------------------------------------
// ✅ Start session and output buffering early
// ----------------------------------------------------
ob_start();
require_once __DIR__ . "/../config.php";

// ----------------------------------------------------
// 🧭 Determine route
// ----------------------------------------------------
$path = $_GET['p'] ?? 'home';

// ----------------------------------------------------
// 📄 Allowed pages
// ----------------------------------------------------
$pages = [
  'home','listing','listingDetail','addlisting','checkout','payment_success','webhook','userManagement',
  'dashboard','profile','userDashboard','my_listing','adminDashboard','listingverification','offers',
  'adminPayments','settings','transferWorkflow','admin_create','my_order','receipt','create_listing',
  'message','adminmessages','superAdminDashboard','listingManagement','categories','superAdminPayment',
  'superAdminOffers','superAdminDelligence','superAdminQuestion','superAdminReports','superAdminDisputes',
  'superAdminAudit','superAdminSettings','makeoffer','payment','forgotPassword','adminreports',
  'sellingOption','addWebList','addYTList','saveListing','create_transaction',
  'delete_listing','updateWebListing','updateYtListing','updateListing','addAdminSuperAdmin',
  'offer_action','offer_action_ajax','admin_listing_verification','save_category','profile_update','delete_category',
  'update_category','save_label','delete_label','conversation_create','mark_read',

  'create_ticket','my_tickets','view_ticket','admin_tickets','admin_ticket_view',

  'send_message','get_conversations','get_messages','get_unread_count','listingSuccess','submit_offer_ajax','wishlist_toggle','wishlist','submit_credentials',
  
  'resend_email_verification','cancel_email_change'
];

// ----------------------------------------------------
// 🔐 Auth-only pages
// ----------------------------------------------------
$authPages = [
  'login','auth_submit','auth_logout','auth_login','auth_google_callback','auth_facebook_callback','auth_check'
];

// ----------------------------------------------------
// ⚙️ Identify API requests (JSON only, no header/footer)
// ----------------------------------------------------
$apiPages = [
  'send_message',
  'get_conversations',
  'get_messages',
  'get_unread_count',
  'mark_read',
  'conversation_create',
  'submit_offer_ajax',
  'wishlist_toggle',
  'get_superadmin_stats',
  'auth_check',
  'offer_action_ajax'
];

$isApiRequest = in_array($path, $apiPages);

// ----------------------------------------------------
// 🧱 Routing Logic
// ----------------------------------------------------
if ($isApiRequest) {
    // ✅ Handle API routes (no HTML output)
    header('Content-Type: application/json');
$file = __DIR__ . '/../modules/' . $path . '.php';
if (!file_exists($file)) {
    // check in dashboard folder too
    $file = __DIR__ . '/../modules/dashboard/' . $path . '.php';
}

if (file_exists($file)) {
    include $file;
} else {
    echo json_encode(['error' => 'API endpoint not found', 'file' => $file]);
}
    exit; // stop execution (important)
}

if (in_array($path, $pages)) {

    // ✅ Normal web pages (with header/footer)
    require_once __DIR__ . '/../includes/header.php';

    if ($path === 'dashboard') {
        include __DIR__ . '/../modules/dashboard/dashboard.php';
    } else {
        // Check if it's a dashboard page first
        $dashboardFile = __DIR__ . '/../modules/dashboard/' . $path . '.php';
        $regularFile = __DIR__ . '/../modules/' . $path . '.php';
        
        if (file_exists($dashboardFile)) {
            include $dashboardFile;
        } elseif (file_exists($regularFile)) {
            include $regularFile;
        } else {
            echo "<h1 class='text-center text-2xl font-semibold text-gray-600 mt-10'>Page Not Found</h1>";
        }
    }

    require_once __DIR__ . '/../includes/footer.php';

} elseif (in_array($path, $authPages)) {

    // ✅ Auth-related pages (no header/footer)
    $file = __DIR__ . '/../auth/' . $path . '.php';
    if (file_exists($file)) {
        include $file;
    } else {
        echo "<h1 class='text-center text-2xl font-semibold text-gray-600 mt-10'>Auth Page Not Found</h1>";
    }

} else {

    // ✅ Fallback to home page
    require_once __DIR__ . '/../includes/header.php';
    include __DIR__ . '/../modules/home.php';
    require_once __DIR__ . '/../includes/footer.php';
}

// ----------------------------------------------------
// 🚀 Send buffered output at the end
// ----------------------------------------------------
ob_end_flush();
