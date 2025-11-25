<?php
/**
 * Enhanced Bidding API - ONLY missing logic endpoints
 * Handles: Bid Increment, Auction End Rules, Down Payment Validation, Secure Logging
 */

require_once '../config.php';
require_once '../modules/bidding/EnhancedBiddingSystem.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Initialize system
$pdo = db();
$biddingSystem = new EnhancedBiddingSystem($pdo);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($action, $biddingSystem);
            break;
        case 'POST':
            handlePostRequest($action, $biddingSystem);
            break;
        case 'PUT':
            handlePutRequest($action, $biddingSystem);
            break;
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleGetRequest($action, $biddingSystem) {
    switch ($action) {
        case 'get_bidding_settings':
            $pdo = db();
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                'bid_increment_type',
                'bid_increment_fixed',
                'bid_increment_percentage',
                'default_min_down_payment',
                'down_payment_warning_threshold',
                'auction_extension_minutes',
                'default_reserved_amount_percentage'
            ]);
            
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            echo json_encode([
                'success' => true,
                'settings' => $settings
            ]);
            break;
            
        case 'calculate_minimum_bid':
            $itemId = $_GET['item_id'] ?? null;
            if (!$itemId) throw new Exception('Item ID required');
            
            $minimumBid = $biddingSystem->calculateMinimumBid($itemId);
            echo json_encode([
                'success' => true,
                'minimum_bid' => $minimumBid,
                'formatted' => '$' . number_format($minimumBid, 2)
            ]);
            break;
            
        case 'verify_log_integrity':
            $logId = $_GET['log_id'] ?? null;
            $result = $biddingSystem->verifyLogIntegrity($logId);
            echo json_encode([
                'success' => true,
                'verification_result' => $result
            ]);
            break;
            
        case 'get_auction_status':
            $itemId = $_GET['item_id'] ?? null;
            if (!$itemId) throw new Exception('Item ID required');
            
            // Get item with auction details
            $pdo = db();
            $stmt = $pdo->prepare("
                SELECT i.*, 
                       CASE 
                           WHEN i.auction_end_time IS NULL THEN 'no_time_limit'
                           WHEN i.auction_end_time > NOW() THEN 'active'
                           ELSE 'expired'
                       END as time_status,
                       TIMESTAMPDIFF(SECOND, NOW(), i.auction_end_time) as seconds_remaining
                FROM items i WHERE i.id = ?
            ");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) throw new Exception('Item not found');
            
            echo json_encode([
                'success' => true,
                'auction_status' => $item
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
}

function handlePostRequest($action, $biddingSystem) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update_bidding_settings':
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $adminId = $_SESSION['user_id'] ?? null;
            $adminRole = $_SESSION['role'] ?? null;
            
            if (!$adminId || $adminRole !== 'superadmin') {
                throw new Exception('Unauthorized - Super Admin access required');
            }
            
            $pdo = db();
            
            // Update all bidding settings
            $settingsToUpdate = [
                'bid_increment_type' => $input['bid_increment_type'] ?? 'fixed',
                'bid_increment_fixed' => $input['bid_increment_fixed'] ?? '10.00',
                'bid_increment_percentage' => $input['bid_increment_percentage'] ?? '5.00',
                'default_min_down_payment' => $input['default_min_down_payment'] ?? '50.00',
                'down_payment_warning_threshold' => $input['down_payment_warning_threshold'] ?? '10.00',
                'auction_extension_minutes' => $input['auction_extension_minutes'] ?? '2',
                'default_reserved_amount_percentage' => $input['default_reserved_amount_percentage'] ?? '0.00'
            ];
            
            foreach ($settingsToUpdate as $key => $value) {
                $stmt = $pdo->prepare("
                    INSERT INTO system_settings (setting_key, setting_value, updated_by) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?
                ");
                $stmt->execute([$key, $value, $adminId, $value, $adminId]);
            }
            
            // Secure log the settings change
            $biddingSystem->secureLog('commission_changed', null, $adminId, null, null, [
                'action' => 'bidding_settings_updated',
                'settings' => $settingsToUpdate
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Bidding settings updated successfully'
            ]);
            break;
            
        case 'place_enhanced_bid':
            $itemId = $input['item_id'] ?? null;
            $bidderId = $input['bidder_id'] ?? null;
            $bidAmount = $input['bid_amount'] ?? null;
            $downPaymentPercentage = $input['down_payment_percentage'] ?? 50.00;
            
            if (!$itemId || !$bidderId || !$bidAmount) {
                throw new Exception('Missing required parameters');
            }
            
            $result = $biddingSystem->placeBid($itemId, $bidderId, $bidAmount, $downPaymentPercentage);
            echo json_encode($result);
            break;
            
        case 'end_auction':
            $itemId = $input['item_id'] ?? null;
            $sellerId = $input['seller_id'] ?? null;
            
            if (!$itemId || !$sellerId) {
                throw new Exception('Missing required parameters');
            }
            
            $result = $biddingSystem->endAuction($itemId, $sellerId);
            echo json_encode($result);
            break;
            
        case 'process_expired_auctions':
            // This should be called by cron job
            $result = $biddingSystem->processExpiredAuctions();
            echo json_encode([
                'success' => true,
                'processed_auctions' => $result,
                'count' => count($result)
            ]);
            break;
            
        case 'set_buy_now_price':
            $itemId = $input['item_id'] ?? null;
            $sellerId = $input['seller_id'] ?? null;
            $buyNowPrice = $input['buy_now_price'] ?? null;
            
            if (!$itemId || !$sellerId || !$buyNowPrice) {
                throw new Exception('Missing required parameters');
            }
            
            $pdo = db();
            
            // Verify ownership
            $stmt = $pdo->prepare("SELECT seller_id FROM items WHERE id = ?");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item || $item['seller_id'] != $sellerId) {
                throw new Exception('Unauthorized');
            }
            
            // Update buy now price
            $stmt = $pdo->prepare("UPDATE items SET buy_now_price = ? WHERE id = ?");
            $stmt->execute([$buyNowPrice, $itemId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Buy Now price set successfully'
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
}

function handlePutRequest($action, $biddingSystem) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update_increment_settings':
            $adminId = $input['admin_id'] ?? null;
            $incrementType = $input['increment_type'] ?? null;
            $incrementValue = $input['increment_value'] ?? null;
            
            if (!$adminId || !$incrementType || !$incrementValue) {
                throw new Exception('Missing required parameters');
            }
            
            $pdo = db();
            
            // Update increment type
            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET setting_value = ?, updated_by = ? 
                WHERE setting_key = 'bid_increment_type'
            ");
            $stmt->execute([$incrementType, $adminId]);
            
            // Update increment value
            $settingKey = ($incrementType === 'percentage') ? 'bid_increment_percentage' : 'bid_increment_fixed';
            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET setting_value = ?, updated_by = ? 
                WHERE setting_key = ?
            ");
            $stmt->execute([$incrementValue, $adminId, $settingKey]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Increment settings updated successfully'
            ]);
            break;
            
        case 'update_auction_settings':
            $adminId = $input['admin_id'] ?? null;
            $extensionMinutes = $input['extension_minutes'] ?? null;
            $warningThreshold = $input['warning_threshold'] ?? null;
            
            if (!$adminId) {
                throw new Exception('Admin ID required');
            }
            
            $pdo = db();
            
            if ($extensionMinutes !== null) {
                $stmt = $pdo->prepare("
                    UPDATE system_settings 
                    SET setting_value = ?, updated_by = ? 
                    WHERE setting_key = 'auction_extension_minutes'
                ");
                $stmt->execute([$extensionMinutes, $adminId]);
            }
            
            if ($warningThreshold !== null) {
                $stmt = $pdo->prepare("
                    UPDATE system_settings 
                    SET setting_value = ?, updated_by = ? 
                    WHERE setting_key = 'down_payment_warning_threshold'
                ");
                $stmt->execute([$warningThreshold, $adminId]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Auction settings updated successfully'
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
}
?>