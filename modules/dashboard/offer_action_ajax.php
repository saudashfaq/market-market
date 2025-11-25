<?php
require_once __DIR__ . "/../../config.php";
require_once __DIR__ . "/../../includes/flash_helper.php";
require_once __DIR__ . "/../../middlewares/auth.php";
require_once __DIR__ . "/../../includes/log_helper.php";

// Set JSON header
header('Content-Type: application/json');

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_login(); // ensure only logged-in users can access

$pdo = db();
$currentUser = current_user();
$seller_id = $currentUser['id'] ?? 0;

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$offer_id = $_POST['offer_id'] ?? $_GET['id'] ?? 0;

if (!$action || !$offer_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    exit;
}

// ✅ Fetch offer record
$stmt = $pdo->prepare("SELECT * FROM offers WHERE id = :id");
$stmt->execute(['id' => $offer_id]);
$offer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$offer) {
    echo json_encode(['success' => false, 'message' => 'Offer not found']);
    exit;
}

// ✅ Security check — ensure current seller owns the offer
if ($offer['seller_id'] != $seller_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized action']);
    exit;
}

// ✅ Prevent duplicate processing
if ($offer['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'This offer has already been processed']);
    exit;
}

try {
    if ($action === 'accept') {
        $pdo->beginTransaction();

        // ✅ Update offer as accepted
        $pdo->prepare("UPDATE offers SET status = 'accepted', updated_at = NOW() WHERE id = :id")
            ->execute(['id' => $offer_id]);
        
        // ✅ Get count of other offers that will be rejected
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM offers WHERE listing_id = :listing_id AND id != :offer_id AND status = 'pending'");
        $stmt->execute(['listing_id' => $offer['listing_id'], 'offer_id' => $offer_id]);
        $rejectedCount = $stmt->fetchColumn();
        
        // ✅ Automatically reject all other pending offers for the same listing
        $pdo->prepare("UPDATE offers SET status = 'rejected', updated_at = NOW() 
                       WHERE listing_id = :listing_id AND id != :offer_id AND status = 'pending'")
            ->execute([
                'listing_id' => $offer['listing_id'],
                'offer_id' => $offer_id
            ]);

        // ✅ Create new order for accepted offer
        $stmt = $pdo->prepare("
            INSERT INTO orders (listing_id, buyer_id, seller_id, offer_id, amount, status, created_at)
            VALUES (:listing_id, :buyer_id, :seller_id, :offer_id, :amount, 'pending_payment', NOW())
        ");
        $stmt->execute([
            'listing_id' => $offer['listing_id'],
            'buyer_id'   => $offer['user_id'],
            'seller_id'  => $offer['seller_id'],
            'offer_id'   => $offer['id'],
            'amount'     => $offer['amount']
        ]);

        $order_id = $pdo->lastInsertId();

        $pdo->commit();

        // ✅ Log accepted offer
        log_action(
            "Offer Accepted",
            "Seller ({$currentUser['name']}) accepted offer (Offer ID: {$offer['id']}, Amount: {$offer['amount']}) for Listing ID: {$offer['listing_id']}",
            "offers",
            $seller_id,
            $currentUser['role']
        );

        // Send emails and notifications in background
        register_shutdown_function(function() use ($offer, $pdo, $order_id, $currentUser) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            
            require_once __DIR__ . '/../../includes/email_helper.php';
            require_once __DIR__ . "/../../includes/notification_helper.php";
            
            // Get buyer details
            $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
            $stmt->execute([$offer['user_id']]);
            $buyer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get seller details
            $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
            $stmt->execute([$offer['seller_id']]);
            $seller = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get listing details
            $stmt = $pdo->prepare("SELECT name, asking_price FROM listings WHERE id = ?");
            $stmt->execute([$offer['listing_id']]);
            $listing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($buyer && $seller && $listing) {
                // Send offer accepted email to buyer
                $offerData = ['id' => $offer['id'], 'amount' => $offer['amount']];
                $listingData = ['title' => $listing['name'], 'price' => $listing['asking_price']];
                sendOfferAcceptedEmail($buyer['email'], $buyer['name'], $offerData, $listingData);
                
                // Send order confirmation emails
                $orderData = [
                    'id' => $order_id,
                    'amount' => $offer['amount'],
                    'other_party_name' => $seller['name']
                ];
                sendOrderConfirmationEmail($buyer['email'], $buyer['name'], $orderData, $listingData, 'buyer');
                
                $orderData['other_party_name'] = $buyer['name'];
                sendOrderConfirmationEmail($seller['email'], $seller['name'], $orderData, $listingData, 'seller');
                
                error_log("✅ Offer accepted and order confirmation emails sent for order #$order_id");
            }
            
            // Create notification for buyer
            $listingName = $listing['name'] ?? 'listing';
            notifyOfferAccepted($offer['user_id'], $offer['id'], $listingName);
            
            // Notify admins about the accepted offer
            try {
                $buyerName = $buyer['name'] ?? 'Buyer';
                $admins = $pdo->query("
                    SELECT id FROM users 
                    WHERE role IN ('admin', 'superadmin') 
                    AND status = 'active'
                ")->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($admins as $adminId) {
                    createNotification(
                        $adminId,
                        'offer',
                        'Offer Accepted',
                        "{$currentUser['name']} accepted {$buyerName}'s offer of $" . number_format($offer['amount']) . " for '{$listingName}'",
                        $offer['id'],
                        'offer'
                    );
                }
            } catch (Exception $e) {
                error_log("Admin notification error: " . $e->getMessage());
            }
        });

        echo json_encode([
            'success' => true, 
            'message' => "Offer of $" . number_format($offer['amount']) . " has been accepted successfully!",
            'action' => 'accept',
            'offer_id' => $offer_id,
            'rejected_count' => $rejectedCount,
            'order_id' => $order_id
        ]);
        exit;
    }

    elseif ($action === 'reject') {
        // ✅ Update offer as rejected
        $stmt = $pdo->prepare("UPDATE offers SET status = 'rejected', updated_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $offer_id]);

        // ✅ Log rejected offer
        log_action(
            "Offer Rejected",
            "Seller ({$currentUser['name']}) rejected offer (Offer ID: {$offer['id']}, Amount: {$offer['amount']}) for Listing ID: {$offer['listing_id']}",
            "offers",
            $seller_id,
            $currentUser['role']
        );

        // Send email and notifications in background
        register_shutdown_function(function() use ($offer, $pdo) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            
            require_once __DIR__ . '/../../includes/email_helper.php';
            require_once __DIR__ . "/../../includes/notification_helper.php";
            
            // Get buyer details
            $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
            $stmt->execute([$offer['user_id']]);
            $buyer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get listing details
            $stmt = $pdo->prepare("SELECT id, name, asking_price FROM listings WHERE id = ?");
            $stmt->execute([$offer['listing_id']]);
            $listing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($buyer && $listing) {
                $offerData = ['amount' => $offer['amount']];
                $listingData = ['id' => $listing['id'], 'title' => $listing['name'], 'price' => $listing['asking_price']];
                sendOfferRejectedEmail($buyer['email'], $buyer['name'], $offerData, $listingData);
                
                // Create notification for buyer
                $listingName = $listing['name'] ?? 'listing';
                notifyOfferRejected($offer['user_id'], $offer['id'], $listingName);
            }
        });

        echo json_encode([
            'success' => true, 
            'message' => "Offer of $" . number_format($offer['amount']) . " has been rejected.",
            'action' => 'reject',
            'offer_id' => $offer_id
        ]);
        exit;
    }

    else {
        echo json_encode(['success' => false, 'message' => 'Invalid action specified']);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    
    echo json_encode(['success' => false, 'message' => 'Error processing offer: ' . $e->getMessage()]);
    exit;
}
?>