<?php
session_start();

// Load .env file
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_TYPED);
    foreach ($env as $k => $v) {
        if (!defined($k)) define($k, $v);
    }
}

// Set defaults if .env variables not defined
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'marketplace');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

if (!defined('MAIL_HOST')) define('MAIL_HOST', 'smtp.gmail.com');
if (!defined('MAIL_PORT')) define('MAIL_PORT', 587);
if (!defined('MAIL_USERNAME')) define('MAIL_USERNAME', '');
if (!defined('MAIL_PASSWORD')) define('MAIL_PASSWORD', '');
if (!defined('MAIL_FROM_ADDRESS')) define('MAIL_FROM_ADDRESS', '');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'Marketplace');

// Pandascrow defaults
if (!defined('PANDASCROW_MODE')) define('PANDASCROW_MODE', 'sandbox');
if (!defined('PANDASCROW_DEFAULT_EMAIL')) define('PANDASCROW_DEFAULT_EMAIL', '');
if (!defined('PANDASCROW_DEFAULT_PASSWORD')) define('PANDASCROW_DEFAULT_PASSWORD', '');
if (!defined('PANDASCROW_DEFAULT_USER_PASSWORD')) define('PANDASCROW_DEFAULT_USER_PASSWORD', '');
if (!defined('PANDASCROW_UUID')) define('PANDASCROW_UUID', '');
if (!defined('PANDASCROW_PUBLIC_KEY')) define('PANDASCROW_PUBLIC_KEY', '');
if (!defined('PANDASCROW_SECRET_KEY')) define('PANDASCROW_SECRET_KEY', '');
if (!defined('PANDASCROW_BASE_URL')) {
    define('PANDASCROW_BASE_URL', PANDASCROW_MODE === 'sandbox'
        ? 'https://sandbox.pandascrow.io'
        : 'https://api.pandascrow.io');
}

// Social Login defaults
if (!defined('GOOGLE_CLIENT_ID')) define('GOOGLE_CLIENT_ID', '');
if (!defined('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET', '');
if (!defined('FACEBOOK_APP_ID')) define('FACEBOOK_APP_ID', '');
if (!defined('FACEBOOK_APP_SECRET')) define('FACEBOOK_APP_SECRET', '');

// Base URL Detection
if (!defined('BASE')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 0) == 443 ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Check script directory
    // On Local: /marketplace/public/index.php -> Dir: /marketplace/public
    // On Server: /index.php -> Dir: / (or empty)
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

    // Ensure trailing slash and correct BASE
    $basePath = rtrim($scriptDir, '/') . '/';
    define('BASE', $protocol . $host . $basePath);

    // Determine if we are in "Public Root" mode (Production)
    // If the path is just '/', or it doesn't end in 'public', we assume production logic might be needed for assets
    // But since we are normalizing BASE to always be the public folder (or root), 
    // we just need to handle asset paths correctly.
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    define('IS_PUBLIC_ROOT', (basename($docRoot) === 'public') || ($basePath === '/'));
}

// PDO connection
function db()
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (PDOException $e) {
        die("DB Connection Failed: " . $e->getMessage());
    }

    return $pdo;
}


// URL helper
function url($path = '')
{
    // If path is empty, return base
    if (empty($path)) return BASE;

    // Handle full URLs (already containing http)
    if (strpos($path, 'http') === 0) return $path;

    // Clean path
    $cleanPath = ltrim($path, '/');

    // ALWAYS clean "public/" prefix from the input path
    // Reason: BASE is already pointing to the public root (either /marketplace/public/ or /)
    // So 'public/js/foo.js' should become BASE . 'js/foo.js'
    if (strpos($cleanPath, 'public/') === 0) {
        $cleanPath = substr($cleanPath, 7);
    }

    // 2. SEO Friendly Transformation
    // Check if it's a legacy page route (index.php?p=...)
    if (strpos($cleanPath, 'index.php') !== false && strpos($cleanPath, '?p=') !== false) {
        $parts = parse_url($cleanPath);
        parse_str($parts['query'] ?? '', $query);

        $page = $query['p'] ?? 'home';
        unset($query['p']); // Remove 'p' from query params

        $seoPath = '';

        // --- DASHBOARD ROUTES ---
        if ($page === 'dashboard') {
            $seoPath = 'dashboard';

            // Check for sub-page
            if (isset($query['page'])) {
                $subPage = $query['page'];
                $seoPath .= '/' . $subPage;
                unset($query['page']);

                // Specific Dashboard Sub-Page Params
                // /dashboard/view_ticket/123
                if (($subPage === 'view_ticket' || $subPage === 'admin_ticket_view') && isset($query['ticket_id'])) {
                    $seoPath .= '/' . $query['ticket_id'];
                    unset($query['ticket_id']);
                }
                // /dashboard/message/SELLER_ID/LISTING_ID
                elseif ($subPage === 'message' && isset($query['seller_id'])) {
                    $seoPath .= '/' . $query['seller_id'];
                    unset($query['seller_id']);
                    if (isset($query['listing_id'])) {
                        $seoPath .= '/' . $query['listing_id'];
                        unset($query['listing_id']);
                    }
                }
                // /dashboard/submit_credentials/TRANSACTION_ID
                elseif ($subPage === 'submit_credentials' && isset($query['transaction_id'])) {
                    $seoPath .= '/' . $query['transaction_id'];
                    unset($query['transaction_id']);
                }
            }
        }
        // --- PUBLIC ROUTES WITH ID ---
        elseif ($page === 'listingDetail' && isset($query['id'])) {
            // /listing/123
            $seoPath = 'listing/' . $query['id'];
            unset($query['id']);
        } elseif ($page === 'listing' && empty($query)) {
            // /listings (Browse)
            $seoPath = 'listings';
        } elseif ($page === 'profile' && isset($query['id'])) {
            // /user/123
            $seoPath = 'user/' . $query['id'];
            unset($query['id']);
        } elseif ($page === 'payment') {
            // /payment/listing/123 OR /payment/offer/123
            $seoPath = 'payment';
            if (isset($query['id'])) { // Listing ID
                $seoPath .= '/listing/' . $query['id'];
                unset($query['id']);
            } elseif (isset($query['offer_id'])) {
                $seoPath .= '/offer/' . $query['offer_id'];
                unset($query['offer_id']);
            }
        } elseif (($page === 'updateWebListing' || $page === 'updateYtListing') && isset($query['id'])) {
            // /updateWebListing/123
            $seoPath = $page . '/' . $query['id'];
            unset($query['id']);
        }
        // --- AUTH ROUTES ---
        elseif ($page === 'verify-email' && isset($query['token'])) {
            $seoPath = 'verify-email/' . $query['token'];
            unset($query['token']);
        } elseif ($page === 'reset-password' && isset($query['token'])) {
            $seoPath = 'reset-password/' . $query['token'];
            unset($query['token']);
        }
        // --- DEFAULT FALLBACK ---
        else {
            // Map simple pages: 'login' -> '/login', 'home' -> ''
            $seoPath = ($page === 'home') ? '' : $page;
        }

        // Re-append remaining query params (e.g. ?debug=true, ?filter=xyz)
        if (!empty($query)) {
            $seoPath .= '?' . http_build_query($query);
        }

        return BASE . $seoPath;
    }

    // Fallback for asset paths or direct paths
    return BASE . ltrim($cleanPath, '/');
}

// CSRF helpers moved to includes/validation_helper.php

// escaping
function e($s)
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'utf-8');
}

// initialize Stripe SDK
// \Stripe\Stripe::setApiKey(STRIPE_SECRET);


/**
 * Log user activity to database
 * 
 * @param string $action Action name
 * @param string $details Action details
 * @param string $category Category (optional)
 * @param int $userId User ID (optional, uses current user if not provided)
 */
function log_action($action, $details = '', $category = 'general', $userId = null)
{
    try {
        $pdo = db();

        // Get current user if not provided
        if ($userId === null) {
            $currentUser = current_user();
            if ($currentUser) {
                $userId = $currentUser['id'];
                $role = $currentUser['role'];
            } else {
                $userId = null;
                $role = 'guest';
            }
        } else {
            // Get role from user ID
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $role = $stmt->fetchColumn() ?: 'user';
        }

        // Get IP address
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '127.0.0.1';

        // Insert log entry
        $stmt = $pdo->prepare("
            INSERT INTO logs (user_id, role, action, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([$userId, $role, $action, $details, $ipAddress]);

        return true;
    } catch (Exception $e) {
        // Log error but don't break the application
        error_log("Logging Error: " . $e->getMessage());
        return false;
    }
}
