<?php
 session_start();

if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_TYPED);
    foreach ($env as $k => $v) {
        if (!defined($k)) define($k, $v);
    }
}
if (!defined('DB_HOST')) define('DB_HOST', 'DB_HOST');
if (!defined('DB_NAME')) define('DB_NAME', 'DB_NAME');
if (!defined('DB_USER')) define('DB_USER', 'DB_USER');
if (!defined('DB_PASS')) define('DB_PASS', 'DB_PASS');

// Email configuration
if (!defined('MAIL_HOST')) define('MAIL_HOST', 'smtp.gmail.com');
if (!defined('MAIL_PORT')) define('MAIL_PORT', 587);
if (!defined('MAIL_USERNAME')) define('MAIL_USERNAME', 'MAIL_USERNAME');
if (!defined('MAIL_PASSWORD')) define('MAIL_PASSWORD', 'MAIL_PASSWORD');
if (!defined('MAIL_FROM_ADDRESS')) define('MAIL_FROM_ADDRESS', 'MAIL_FROM_ADDRESS');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'MAIL_FROM_NAME');

// Email Configuration (removed duplicate - using .env values)

// OAuth Configuration
if (!defined('GOOGLE_CLIENT_ID')) define('GOOGLE_CLIENT_ID', '');
if (!defined('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET', '');
if (!defined('GOOGLE_REDIRECT_URI')) define('GOOGLE_REDIRECT_URI', '');
if (!defined('FACEBOOK_APP_ID')) define('FACEBOOK_APP_ID', '');
if (!defined('FACEBOOK_APP_SECRET')) define('FACEBOOK_APP_SECRET', '');
if (!defined('FACEBOOK_REDIRECT_URI')) define('FACEBOOK_REDIRECT_URI', '');

// 🔐 Pandascrow Partner Credentials (replace with yours)
define('PANDASCROW_DEFAULT_EMAIL', 'PANDASCROW_DEFAULT_EMAIL');
define('PANDASCROW_DEFAULT_PASSWORD', 'PANDASCROW_DEFAULT_PASSWORD');
define('PANDASCROW_DEFAULT_USER_PASSWORD', 'PANDASCROW_DEFAULT_USER_PASSWORD');
if (!defined('PANDASCROW_MODE'))        define('PANDASCROW_MODE', 'sandbox');
if (!defined('PANDASCROW_UUID'))        define('PANDASCROW_UUID', 'PANDASCROW_UUID');
if (!defined('PANDASCROW_PUBLIC_KEY'))     define('PANDASCROW_PUBLIC_KEY', 'PANDASCROW_PUBLIC_KEY');
if (!defined('PANDASCROW_SECRET_KEY'))  define('PANDASCROW_SECRET_KEY', 'PANDASCROW_SECRET_KEY');
if (!defined('PANDASCROW_BASE_URL')) {
    define('PANDASCROW_BASE_URL',
        PANDASCROW_MODE === 'sandbox'
        ? 'https://sandbox.pandascrow.io'
        : 'https://api.pandascrow.io'
    );
}
// if (!defined('STRIPE_WEBHOOK_SECRET')) define('STRIPE_WEBHOOK_SECRET', '');
if (!defined('BASE')) {
    // Detect protocol
    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443))
                ? "https://" : "http://";

    // Detect host
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // LIVE server base URL
    define('BASE', $protocol . $host . '/');
}

function db() {
    static $pdo = null;

    if ($pdo) return $pdo;

    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    return $pdo;
}


// URL helper
function url($path = '') {
    return BASE . ltrim($path, '/');
}

// CSRF helpers moved to includes/validation_helper.php

// escaping
function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'utf-8'); }

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
function log_action($action, $details = '', $category = 'general', $userId = null) {
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
