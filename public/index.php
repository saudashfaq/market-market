<?php
// ----------------------------------------------------
// ✅ Start session and output buffering early
// ----------------------------------------------------
ob_start();
require_once __DIR__ . "/../config.php";

// ----------------------------------------------------
// 🧭 Determine route
// ----------------------------------------------------
$path = $_GET['p'] ?? '';

// Fallback to REQUEST_URI parsing if 'p' is empty (for Nginx/Pretty URLs)
if (empty($path)) {
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $scriptName = dirname($_SERVER['SCRIPT_NAME']);

    // Remove script path from URI
    // e.g. URI: /marketplace/public/dashboard, Script: /marketplace/public
    // Result: /dashboard
    if (strpos($requestUri, $scriptName) === 0) {
        $path = substr($requestUri, strlen($scriptName));
    } else {
        $path = $requestUri;
    }

    // Trim slashes
    $path = trim($path, '/');
}

// Strip .php extension if present
$path = preg_replace('/\.php$/', '', $path);

// Default to home if still empty
if (empty($path)) {
    $path = 'home';
}

// ----------------------------------------------------
// 📄 Allowed pages
// ----------------------------------------------------
$pages = [
    'home',
    'listing',
    'listingDetail',
    'addlisting',
    'checkout',
    'payment_success',
    'webhook',
    'userManagement',
    'dashboard',
    'profile',
    'userDashboard',
    'my_listing',
    'adminDashboard',
    'listingverification',
    'offers',
    'adminPayments',
    'settings',
    'transferWorkflow',
    'admin_create',
    'my_order',
    'receipt',
    'create_listing',
    'message',
    'adminmessages',
    'superAdminDashboard',
    'listingManagement',
    'categories',
    'superAdminPayment',
    'superAdminOffers',
    'superAdminDelligence',
    'superAdminQuestion',
    'superAdminReports',
    'superAdminDisputes',
    'superAdminAudit',
    'superAdminSettings',
    'makeoffer',
    'payment',
    'forgotPassword',
    'adminreports',
    'sellingOption',
    'addWebList',
    'addYTList',
    'saveListing',
    'create_transaction',
    'delete_listing',
    'updateWebListing',
    'updateYtListing',
    'updateListing',
    'addAdminSuperAdmin',
    'offer_action',
    'offer_action_ajax',
    'admin_listing_verification',
    'save_category',
    'profile_update',
    'delete_category',
    'update_category',
    'save_label',
    'delete_label',
    'conversation_create',
    'mark_read',

    'create_ticket',
    'my_tickets',
    'view_ticket',
    'admin_tickets',
    'admin_ticket_view',

    'send_message',
    'get_conversations',
    'get_messages',
    'get_unread_count',
    'listingSuccess',
    'submit_offer_ajax',
    'wishlist_toggle',
    'wishlist',
    'submit_credentials',

    'resend-verification',
    'cancel_email_change',
    'reset-password',
    'verify-email'
];

// ----------------------------------------------------
// 🔐 Auth-only pages
// ----------------------------------------------------
$authPages = [
    'login',
    'auth_submit',
    'auth_logout',
    'auth_login',
    'auth_google_callback',
    'auth_facebook_callback',
    'auth_check'
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
    'offer_action_ajax',
    'polling_integration',
    'notifications_api'
];

$isApiRequest = in_array($path, $apiPages);

// ----------------------------------------------------
// 🧠 Routing & Parameter Parsing (Comprehensive)
// ----------------------------------------------------

// Breakdown path by slashes
$pathParts = explode('/', $path);


if ($path === 'logout') {
    $path = 'auth_logout';
}

// 1. Listings
// /listings -> listing
// /listing/123 -> listingDetail, id=123
if ($pathParts[0] === 'listings') {
    $path = 'listing';
} elseif ($pathParts[0] === 'listing' && isset($pathParts[1]) && is_numeric($pathParts[1])) {
    $path = 'listingDetail';
    $_GET['id'] = $pathParts[1];
}

// 2. User Profile
// /user/123 -> profile, id=123
if ($pathParts[0] === 'user' && isset($pathParts[1]) && is_numeric($pathParts[1])) {
    $path = 'profile';
    $_GET['id'] = $pathParts[1];
}

// 3. Dashboard Routes
// /dashboard/subpage/params...
if ($pathParts[0] === 'dashboard' && isset($pathParts[1])) {
    // Force the main path to 'dashboard' so it loads modules/dashboard/dashboard.php
    $path = 'dashboard';

    $_GET['page'] = $pathParts[1]; // e.g. 'view_ticket'

    // Check specific sub-pages that need IDs
    if (isset($pathParts[2])) {
        // Tickets
        if ($pathParts[1] === 'view_ticket' || $pathParts[1] === 'admin_ticket_view') {
            $_GET['ticket_id'] = $pathParts[2];
        }
        // Messages: /dashboard/message/SELLER/LISTING
        elseif ($pathParts[1] === 'message') {
            $_GET['seller_id'] = $pathParts[2];
            if (isset($pathParts[3])) {
                $_GET['listing_id'] = $pathParts[3];
            }
        }
        // Credentials
        elseif ($pathParts[1] === 'submit_credentials') {
            $_GET['transaction_id'] = $pathParts[2];
        }
    }
}

// 4. Payment Routes
// /payment/listing/123
// /payment/offer/456
if ($pathParts[0] === 'payment') {
    if (isset($pathParts[1]) && $pathParts[1] === 'listing' && isset($pathParts[2])) {
        $_GET['id'] = $pathParts[2];
    } elseif (isset($pathParts[1]) && $pathParts[1] === 'offer' && isset($pathParts[2])) {
        $_GET['offer_id'] = $pathParts[2];
    }
}

// 5. Update Listings
// /updateWebListing/123
if (($pathParts[0] === 'updateWebListing' || $pathParts[0] === 'updateYtListing') && isset($pathParts[1])) {
    $path = $pathParts[0];
    $_GET['id'] = $pathParts[1];
}

// 6. Auth Tokens
// 6. Auth Tokens
// /verify-email/TOKEN
if ($pathParts[0] === 'verify-email' && isset($pathParts[1])) {
    $path = 'verify-email';
    $_GET['token'] = $pathParts[1];
}
// /reset-password/TOKEN
if ($pathParts[0] === 'reset-password' && isset($pathParts[1])) {
    $path = 'reset-password';
    $_GET['token'] = $pathParts[1];
}

// ----------------------------------------------------
// 🚀 Page Execution
// ----------------------------------------------------
if ($isApiRequest) {
    // ✅ Handle API routes (no HTML output)
    header('Content-Type: application/json');

    // Check in api folder first
    $apiFile = __DIR__ . '/../api/' . $path . '.php';
    if (file_exists($apiFile)) {
        include $apiFile;
        exit;
    }

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
    if ($path === 'dashboard') {
        define('SHOW_SIDEBAR_TOGGLE', true);
    }
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
