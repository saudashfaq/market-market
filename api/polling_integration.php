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
    // error_log("Polling API: Session ID = " . session_id());
    // error_log("Polling API: Request method = " . $_SERVER['REQUEST_METHOD']);

    // Use the same authentication as other API files
    $user = current_user();
    // Allow guest access for public pages (home, listing)
    // If not logged in, $user will be null, which is fine for public data

    $userId = $user['id'] ?? null;
    $userRole = $user['role'] ?? 'guest';

    error_log("Polling API: User ID = " . ($userId ?? 'guest') . ", Role = $userRole");

    // Get request data
    $input = file_get_contents('php://input');
    $lastCheckTimes = json_decode($input, true);

    if (!$lastCheckTimes) {
        $lastCheckTimes = [
            'listings' => '1970-01-01 00:00:00',
            'offers' => '1970-01-01 00:00:00',
            'orders' => '1970-01-01 00:00:00',
            'notifications' => '1970-01-01 00:00:00',
            'tickets' => '1970-01-01 00:00:00'
        ];
    }

    error_log("Polling API: Input data = " . json_encode($lastCheckTimes));

    $pdo = db();
    $newData = [];
    $newTimestamps = [];

    // Helper to get current DB time to avoid timezone mismatches
    // We use this as the fallback "now" if no records are found
    $dbTimeStmt = $pdo->query("SELECT NOW()");
    $dbNow = $dbTimeStmt->fetchColumn();

    // Initialize timestamps with DB Now if missing
    // But prefer the last check time if provided
    $fallbackTime = $dbNow;

    // Check for new listings or updates
    try {
        // Calculate the latest timestamp column
        $timestampCol = "GREATEST(l.created_at, COALESCE(l.updated_at, l.created_at))";

        if (in_array($userRole, ['admin', 'superadmin', 'super_admin', 'superAdmin'])) {
            // Admin sees all new/updated listings
            $sql = "
            SELECT l.*, 
                   COALESCE(GROUP_CONCAT(lp.file_path SEPARATOR ','), '') as proof_images,
                   COUNT(lp.id) as proof_count,
                   $timestampCol as latest_activity
            FROM listings l
            LEFT JOIN listing_proofs lp ON l.id = lp.listing_id
            WHERE $timestampCol > ?
            GROUP BY l.id
            ORDER BY latest_activity DESC
            LIMIT 50
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$lastCheckTimes['listings'] ?? '1970-01-01 00:00:00']);
        } else {
            // Regular users see approved listings (for home page) AND their own listings (for dashboard)
            $sql = "
                SELECT l.*, 
                       COALESCE(GROUP_CONCAT(lp.file_path SEPARATOR ','), '') as proof_images,
                       COUNT(lp.id) as proof_count,
                       $timestampCol as latest_activity
                FROM listings l
                LEFT JOIN listing_proofs lp ON l.id = lp.listing_id
                WHERE $timestampCol > ?
                GROUP BY l.id
                HAVING (l.status = 'approved' OR l.user_id = ?)
                ORDER BY latest_activity DESC
                LIMIT 50
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $lastCheckTimes['listings'] ?? '1970-01-01 00:00:00',
                $userId
            ]);
        }

        $newListings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($newListings)) {
            $newData['listings'] = $newListings;
            // Use the most recent record's latest_activity as the new checkpoint
            $newTimestamps['listings'] = $newListings[0]['latest_activity'];
            error_log("Polling API: Found " . count($newListings) . " updated/new listings for role: $userRole");
        } else {
            // If no new data, advance the timestamp to NOW (DB time) to avoid repeated queries for old ranges
            $newTimestamps['listings'] = $dbNow;
        }
    } catch (Exception $e) {
        error_log("Polling API: Listings query error: " . $e->getMessage());
        $newTimestamps['listings'] = $lastCheckTimes['listings'] ?? $dbNow;
    }

    // Check for new offers
    try {
        if (in_array($userRole, ['admin', 'superadmin', 'super_admin', 'superAdmin'])) {
            // Admin sees all offers
            $stmt = $pdo->prepare("
                SELECT o.*, l.name as listing_name, l.asking_price, 
                       u.name as buyer_name, u.email as buyer_email 
                FROM offers o
                LEFT JOIN listings l ON o.listing_id = l.id
                LEFT JOIN users u ON o.user_id = u.id
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
            $newTimestamps['offers'] = $dbNow;
        }
    } catch (Exception $e) {
        error_log("Polling API: Offers query error: " . $e->getMessage());
        $newTimestamps['offers'] = $lastCheckTimes['offers'] ?? $dbNow;
    }

    // Check for new transactions/orders
    try {
        if (in_array($userRole, ['admin', 'superadmin', 'super_admin', 'superAdmin'])) {
            // Admin sees all orders
            // Note: SuperAdminOffers uses 'orders' table, so we prioritize that.
            $stmt = $pdo->prepare("
                SELECT t.*, l.name as listing_name, 
                       buyer.name as buyer_name, buyer.email as buyer_email,
                       seller.name as seller_name, seller.email as seller_email
                FROM orders t
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
            // Note: Regular users might use 'transactions' table? Consolidating to check both if necessary or assume orders
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
            $newData['orders'] = $newOrders;
            $newTimestamps['orders'] = $newOrders[0]['created_at'];
        } else {
            $newTimestamps['orders'] = $dbNow;
        }
    } catch (Exception $e) {
        error_log("Polling API: Transactions query error: " . $e->getMessage());
        $newTimestamps['orders'] = $lastCheckTimes['orders'] ?? $dbNow;
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
            $newTimestamps['notifications'] = $dbNow;
        }
    } catch (Exception $e) {
        error_log("Polling API: Notifications query error: " . $e->getMessage());
        $newTimestamps['notifications'] = $lastCheckTimes['notifications'] ?? $dbNow;
    }

    // Check for tickets (new or updated)
    try {
        if (in_array($userRole, ['admin', 'superadmin', 'super_admin', 'superAdmin'])) {
            $stmt = $pdo->prepare("
                SELECT t.*, u.name as user_name, u.email as user_email
                FROM tickets t
                LEFT JOIN users u ON t.user_id = u.id
                WHERE t.updated_at > ? 
                ORDER BY t.updated_at DESC 
                LIMIT 50
            ");
            $stmt->execute([$lastCheckTimes['tickets'] ?? '1970-01-01 00:00:00']);
            $newTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($newTickets)) {
                $newData['tickets'] = $newTickets;
                $newTimestamps['tickets'] = $newTickets[0]['updated_at'];
            } else {
                $newTimestamps['tickets'] = $dbNow;
            }
        } else {
            // For regular users
            $stmt = $pdo->prepare("
                SELECT t.*
                FROM tickets t
                WHERE t.updated_at > ? AND t.user_id = ?
                ORDER BY t.updated_at DESC 
                LIMIT 50
            ");
            $stmt->execute([
                $lastCheckTimes['tickets'] ?? '1970-01-01 00:00:00',
                $userId
            ]);
            $newTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($newTickets)) {
                $newData['tickets'] = $newTickets;
                $newTimestamps['tickets'] = $newTickets[0]['updated_at'];
            } else {
                $newTimestamps['tickets'] = $dbNow;
            }
        }
    } catch (Exception $e) {
        error_log("Polling API: Tickets query error: " . $e->getMessage());
        $newTimestamps['tickets'] = $lastCheckTimes['tickets'] ?? $dbNow;
    }

    // Check for new audit logs (Admin/SuperAdmin only)
    try {
        if (in_array($userRole, ['admin', 'superadmin', 'super_admin', 'superAdmin'])) {
            $stmt = $pdo->prepare("
                SELECT l.*, u.name as user_name, u.email as user_email
                FROM logs l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE l.created_at > ?
                ORDER BY l.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$lastCheckTimes['logs'] ?? '1970-01-01 00:00:00']);
            $newLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($newLogs)) {
                $newData['logs'] = $newLogs;
                $newTimestamps['logs'] = $newLogs[0]['created_at'];
            } else {
                $newTimestamps['logs'] = $dbNow;
            }
        } else {
            // Non-admins don't see logs
            $newTimestamps['logs'] = $dbNow;
        }
    } catch (Exception $e) {
        error_log("Polling API: Logs query error: " . $e->getMessage());
        $newTimestamps['logs'] = $lastCheckTimes['logs'] ?? $dbNow;
    }

    // Check for new disputes (Admin/SuperAdmin only)
    try {
        if (in_array($userRole, ['admin', 'superadmin', 'super_admin', 'superAdmin'])) {
            $stmt = $pdo->prepare("
                SELECT d.*, 
                       l.name as listing, 
                       buyer.name as buyer, 
                       seller.name as seller 
                FROM disputes d 
                LEFT JOIN listings l ON d.listing_id = l.id 
                LEFT JOIN users buyer ON d.buyer_id = buyer.id 
                LEFT JOIN users seller ON d.seller_id = seller.id 
                WHERE d.created_at > ? 
                ORDER BY d.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$lastCheckTimes['disputes'] ?? '1970-01-01 00:00:00']);
            $newDisputes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($newDisputes)) {
                $newData['disputes'] = $newDisputes;
                $newTimestamps['disputes'] = $newDisputes[0]['created_at'];
            } else {
                $newTimestamps['disputes'] = $dbNow;
            }
        } else {
            // Non-admins don't see disputes via this endpoint for now
            $newTimestamps['disputes'] = $dbNow;
        }
    } catch (Exception $e) {
        // Table might not exist yet
        error_log("Polling API: Disputes query error: " . $e->getMessage());
        $newTimestamps['disputes'] = $lastCheckTimes['disputes'] ?? $dbNow;
    }

    // Check for new payments (Admin/SuperAdmin only)
    try {
        if (in_array($userRole, ['admin', 'superadmin', 'super_admin', 'superAdmin'])) {
            $stmt = $pdo->prepare("
                SELECT 
                    p.id AS payment_id,
                    p.amount,
                    p.commission,
                    p.status,
                    p.created_at AS payment_date,
                    p.payment_method,
                    buyer.name AS buyer_name,
                    buyer.email AS buyer_email,
                    seller.name AS seller_name,
                    seller.email AS seller_email
                FROM payments p
                LEFT JOIN users buyer ON p.buyer_id = buyer.id
                LEFT JOIN users seller ON p.seller_id = seller.id
                WHERE p.created_at > ?
                ORDER BY p.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$lastCheckTimes['payments'] ?? '1970-01-01 00:00:00']);
            $newPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($newPayments)) {
                $newData['payments'] = $newPayments;
                // Map payment_date back to created_at for consistency if needed, but timestamp logic uses input check time
                // The query selects created_at AS payment_date, but we need the raw val for timestamp comparison if we were using it from result
                // But the query used created_at for sorting.
                // We should use payment_date (which is created_at) for the new timestamp.
                $newTimestamps['payments'] = $newPayments[0]['payment_date'];
            } else {
                $newTimestamps['payments'] = $dbNow;
            }
        } else {
            $newTimestamps['payments'] = $dbNow;
        }
    } catch (Exception $e) {
        error_log("Polling API: Payments query error: " . $e->getMessage());
        $newTimestamps['payments'] = $lastCheckTimes['payments'] ?? $dbNow;
    }

    // Return response
    echo json_encode([
        'success' => true,
        'data' => $newData,
        'timestamps' => $newTimestamps,
        'user_id' => $userId,
        'user_role' => $userRole,
        'db_time' => $dbNow // Useful for debugging
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
