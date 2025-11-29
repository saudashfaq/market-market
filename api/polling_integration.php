<?php
/**
 * Polling Integration API
 * Handles real-time updates for listings, offers, orders, and notifications
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

// Prevent any output before JSON headers
ob_start();

try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../middlewares/auth.php';
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Clear any previous output
    ob_clean();
    
    // Set JSON headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST');
    header('Access-Control-Allow-Headers: Content-Type');
    // Allow credentials (cookies) to be sent with requests when needed
    header('Access-Control-Allow-Credentials: true');
    
    // Include auth middleware
    require_once __DIR__ . '/../middlewares/auth.php';
    
    // Debug session info
    error_log("Polling API: Session status = " . session_status());
    error_log("Polling API: Session ID = " . session_id());
    error_log("Polling API: Session data = " . json_encode($_SESSION));
    error_log("Polling API: Cookies = " . json_encode($_COOKIE));
    error_log("Polling API: Request method = " . $_SERVER['REQUEST_METHOD']);
    error_log("Polling API: Content type = " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
    
    // Use the same authentication as other API files
    $user = current_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'error' => 'Unauthorized - please login',
            'debug' => 'Session user not found',
            'session_status' => session_status(),
            'session_id' => session_id(),
            'has_session_data' => !empty($_SESSION),
            'session_keys' => array_keys($_SESSION ?? [])
        ]);
        exit;
    }
    
    $userId = $user['id'];
    $userRole = $user['role'] ?? 'user';
    
    error_log("Polling API: User ID = $userId, Role = $userRole");
    
    // Get request data
    $input = file_get_contents('php://input');
    $lastCheckTimes = json_decode($input, true);
    
    if (!$lastCheckTimes) {
        $lastCheckTimes = [
            'listings' => '1970-01-01 00:00:00',
            'offers' => '1970-01-01 00:00:00',
            'orders' => '1970-01-01 00:00:00',
            'notifications' => '1970-01-01 00:00:00'
        ];
    }
    
    error_log("Polling API: Input data = " . json_encode($lastCheckTimes));
    
    $pdo = db();
    $newData = [];
    $newTimestamps = [];
    
    // For now, just return empty data to test authentication
    $newTimestamps = [
        'listings' => date('Y-m-d H:i:s'),
        'offers' => date('Y-m-d H:i:s'),
        'orders' => date('Y-m-d H:i:s'),
        'notifications' => date('Y-m-d H:i:s')
    ];
    
    // Check for new listings
    try {
        if (in_array($userRole, ['admin', 'superadmin'])) {
            // Admin sees all new listings
            $stmt = $pdo->prepare("
                SELECT * FROM listings 
                WHERE created_at > ? 
                ORDER BY created_at DESC 
                LIMIT 50
            ");
            $stmt->execute([$lastCheckTimes['listings'] ?? '1970-01-01 00:00:00']);
        } else {
            // Regular users see only approved listings (for home page)
            $stmt = $pdo->prepare("
                SELECT * FROM listings 
                WHERE created_at > ? AND status = 'approved'
                ORDER BY created_at DESC 
                LIMIT 50
            ");
            $stmt->execute([$lastCheckTimes['listings'] ?? '1970-01-01 00:00:00']);
        }
        
        $newListings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($newListings)) {
            $newData['listings'] = $newListings;
            $newTimestamps['listings'] = $newListings[0]['created_at'];
            error_log("Polling API: Found " . count($newListings) . " new listings for role: $userRole");
            error_log("Polling API: Latest listing timestamp: " . $newListings[0]['created_at']);
        } else {
            $newTimestamps['listings'] = $lastCheckTimes['listings'] ?? date('Y-m-d H:i:s');
            error_log("Polling API: No new listings found. Last check: " . ($lastCheckTimes['listings'] ?? '1970-01-01 00:00:00'));
        }
    } catch (Exception $e) {
        error_log("Polling API: Listings query error: " . $e->getMessage());
        $newTimestamps['listings'] = $lastCheckTimes['listings'] ?? date('Y-m-d H:i:s');
    }
    
    // Check for new offers
    try {
        // Simplified query to avoid complex conditions
        if (in_array($userRole, ['admin', 'superadmin'])) {
            // Admin sees all offers
            $stmt = $pdo->prepare("
                SELECT o.*, l.name as listing_name, u.name as buyer_name 
                FROM offers o
                LEFT JOIN listings l ON o.listing_id = l.id
                LEFT JOIN users u ON o.buyer_id = u.id
                WHERE o.created_at > ? 
                ORDER BY o.created_at DESC 
                LIMIT 50
            ");
            $stmt->execute([$lastCheckTimes['offers'] ?? '1970-01-01 00:00:00']);
        } else {
            // Regular users see only their offers
            $stmt = $pdo->prepare("
                SELECT o.*, l.name as listing_name, u.name as buyer_name 
                FROM offers o
                LEFT JOIN listings l ON o.listing_id = l.id
                LEFT JOIN users u ON o.buyer_id = u.id
                WHERE o.created_at > ? 
                AND (o.seller_id = ? OR o.buyer_id = ?)
                ORDER BY o.created_at DESC 
                LIMIT 50
            ");
            $stmt->execute([
                $lastCheckTimes['offers'] ?? '1970-01-01 00:00:00',
                $userId,
                $userId
            ]);
        }
        
        $newOffers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($newOffers)) {
            $newData['offers'] = $newOffers;
            $newTimestamps['offers'] = $newOffers[0]['created_at'];
        } else {
            $newTimestamps['offers'] = $lastCheckTimes['offers'] ?? date('Y-m-d H:i:s');
        }
        error_log("Polling API: Found " . count($newOffers) . " new offers");
    } catch (Exception $e) {
        error_log("Polling API: Offers query error: " . $e->getMessage());
        $newTimestamps['offers'] = $lastCheckTimes['offers'] ?? date('Y-m-d H:i:s');
    }
    
    // Check for new transactions/orders
    try {
        if (in_array($userRole, ['admin', 'superadmin'])) {
            // Admin sees all transactions
            $stmt = $pdo->prepare("
                SELECT t.*, l.name as listing_name, 
                       buyer.name as buyer_name, seller.name as seller_name
                FROM transactions t
                LEFT JOIN listings l ON t.listing_id = l.id
                LEFT JOIN users buyer ON t.buyer_id = buyer.id
                LEFT JOIN users seller ON t.seller_id = seller.id
                WHERE t.created_at > ? 
                ORDER BY t.created_at DESC 
                LIMIT 50
            ");
            $stmt->execute([$lastCheckTimes['orders'] ?? '1970-01-01 00:00:00']);
        } else {
            // Regular users see only their transactions
            $stmt = $pdo->prepare("
                SELECT t.*, l.name as listing_name, 
                       buyer.name as buyer_name, seller.name as seller_name
                FROM transactions t
                LEFT JOIN listings l ON t.listing_id = l.id
                LEFT JOIN users buyer ON t.buyer_id = buyer.id
                LEFT JOIN users seller ON t.seller_id = seller.id
                WHERE t.created_at > ? 
                AND (t.buyer_id = ? OR t.seller_id = ?)
                ORDER BY t.created_at DESC 
                LIMIT 50
            ");
            $stmt->execute([
                $lastCheckTimes['orders'] ?? '1970-01-01 00:00:00',
                $userId,
                $userId
            ]);
        }
        
        $newOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($newOrders)) {
            $newData['orders'] = $newOrders; // Changed from 'transactions' to 'orders' to match callback
            $newTimestamps['orders'] = $newOrders[0]['created_at'];
        } else {
            $newTimestamps['orders'] = $lastCheckTimes['orders'] ?? date('Y-m-d H:i:s');
        }
        error_log("Polling API: Found " . count($newOrders) . " new transactions");
    } catch (Exception $e) {
        error_log("Polling API: Transactions query error: " . $e->getMessage());
        $newTimestamps['orders'] = $lastCheckTimes['orders'] ?? date('Y-m-d H:i:s');
    }
    
    // Check for new notifications
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND created_at > ? 
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        $stmt->execute([
            $userId,
            $lastCheckTimes['notifications'] ?? '1970-01-01 00:00:00'
        ]);
        $newNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($newNotifications)) {
            $newData['notifications'] = $newNotifications;
            $newTimestamps['notifications'] = $newNotifications[0]['created_at'];
        } else {
            $newTimestamps['notifications'] = $lastCheckTimes['notifications'] ?? date('Y-m-d H:i:s');
        }
        error_log("Polling API: Found " . count($newNotifications) . " new notifications");
    } catch (Exception $e) {
        error_log("Polling API: Notifications query error: " . $e->getMessage());
        $newTimestamps['notifications'] = $lastCheckTimes['notifications'] ?? date('Y-m-d H:i:s');
    }
    
    // Return response
    echo json_encode([
        'success' => true,
        'data' => $newData,
        'timestamps' => $newTimestamps,
        'user_id' => $userId,
        'user_role' => $userRole
    ]);
    
} catch (Exception $e) {
    // Clear any previous output
    ob_clean();
    
    // Log the detailed error
    error_log("Polling Integration Error: " . $e->getMessage());
    error_log("Polling Integration Stack Trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error occurred',
        'debug' => $e->getMessage() // Include error message for debugging
    ]);
} finally {
    // End output buffering
    ob_end_flush();
}
?>