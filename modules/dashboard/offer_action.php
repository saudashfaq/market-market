<?php
require_once __DIR__ . "/../../config.php";
require_once __DIR__ . "/../../includes/flash_helper.php";
require_once __DIR__ . "/../../middlewares/auth.php";
require_once __DIR__ . "/../../includes/log_helper.php";

require_login(); // ensure only logged-in users can access

$pdo = db();
$currentUser = current_user();
$seller_id = $currentUser['id'] ?? 0;

$action = $_GET['action'] ?? '';
$offer_id = $_GET['id'] ?? 0;

if (!$action || !$offer_id) {
    die("❌ Invalid request");
}

// ✅ Fetch offer record
$stmt = $pdo->prepare("SELECT * FROM offers WHERE id = :id");
$stmt->execute(['id' => $offer_id]);
$offer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$offer) {
    die("❌ Offer not found");
}

// ✅ Security check — ensure current seller owns the offer
if ($offer['seller_id'] != $seller_id) {
    die("⚠️ Unauthorized action");
}

// ✅ Prevent duplicate processing
if ($offer['status'] !== 'pending') {
    die("⚠️ This offer has already been processed");
}

try {
    if ($action === 'accept') {
        $pdo->beginTransaction();

        // ✅ Update offer as accepted
        $pdo->prepare("UPDATE offers SET status = 'accepted', updated_at = NOW() WHERE id = :id")
            ->execute(['id' => $offer_id]);
        
        // ✅ Automatically reject all other pending offers for the same listing
        $pdo->prepare("UPDATE offers SET status = 'rejected', updated_at = NOW() 
                       WHERE listing_id = :listing_id AND id != :offer_id AND status = 'pending'")
            ->execute([
                'listing_id' => $offer['listing_id'],
                'offer_id' => $offer_id
            ]);

        // ✅ Get bidding percentage from system settings (set by super admin)
        $biddingPercentageStmt = $pdo->query("
            SELECT setting_value 
            FROM system_settings 
            WHERE setting_key = 'default_reserved_amount_percentage'
        ");
        $biddingPercentage = $biddingPercentageStmt ? floatval($biddingPercentageStmt->fetchColumn()) : 5.0;
        
        // ✅ Calculate platform fee and total based on super admin's bidding percentage
        $offerAmount = floatval($offer['amount']);
        $platformFee = ($offerAmount * $biddingPercentage) / 100;
        $totalAmount = $offerAmount + $platformFee;

        // ✅ Create new order for accepted offer with platform fee
        $stmt = $pdo->prepare("
            INSERT INTO orders (listing_id, buyer_id, seller_id, offer_id, amount, platform_fee, total, status, created_at)
            VALUES (:listing_id, :buyer_id, :seller_id, :offer_id, :amount, :platform_fee, :total, 'pending_payment', NOW())
        ");
        $stmt->execute([
            'listing_id'   => $offer['listing_id'],
            'buyer_id'     => $offer['user_id'],
            'seller_id'    => $offer['seller_id'],
            'offer_id'     => $offer['id'],
            'amount'       => $offerAmount,
            'platform_fee' => $platformFee,
            'total'        => $totalAmount
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


        // Send emails to buyer and seller in background
        register_shutdown_function(function() use ($offer, $pdo, $order_id) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            
            require_once __DIR__ . '/../../includes/email_helper.php';
            
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
        });

        // Create notification for buyer
        require_once __DIR__ . "/../../includes/notification_helper.php";
        $listingStmt = $pdo->prepare("SELECT name FROM listings WHERE id = ?");
        $listingStmt->execute([$offer['listing_id']]);
        $listingName = $listingStmt->fetchColumn() ?: 'listing';
        notifyOfferAccepted($offer['user_id'], $offer['id'], $listingName);
        
        // Notify all admins/superadmins about accepted offer
        $buyerStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $buyerStmt->execute([$offer['user_id']]);
        $buyerName = $buyerStmt->fetchColumn() ?: 'Buyer';
        createNotification(
            null, // Will be handled by notifyAdminsPaymentReceived
            'offer',
            'Offer Accepted',
            "{$currentUser['name']} accepted {$buyerName}'s offer of $" . number_format($offer['amount']) . " for '{$listingName}'",
            $offer['id'],
            'offer'
        );
        // Notify admins about the accepted offer
        try {
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


        // ✅ Use popup helper for success message
        require_once __DIR__ . "/../../includes/popup_helper.php";
        setSuccessPopup("Offer of $" . number_format($offer['amount']) . " has been accepted successfully!", [
            'title' => 'Offer Accepted',
            'autoClose' => true,
            'autoCloseTime' => 3000
        ]);
        
        header("Location: index.php?p=dashboard&page=offers");
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


        // Send email to buyer in background
        register_shutdown_function(function() use ($offer, $pdo) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            
            require_once __DIR__ . '/../../includes/email_helper.php';
            
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
            }
        });

        // Create notification for buyer
        require_once __DIR__ . "/../../includes/notification_helper.php";
        $listingStmt = $pdo->prepare("SELECT name FROM listings WHERE id = ?");
        $listingStmt->execute([$offer['listing_id']]);
        $listingName = $listingStmt->fetchColumn() ?: 'listing';
        notifyOfferRejected($offer['user_id'], $offer['id'], $listingName);


        // ✅ Use popup helper for rejection message
        require_once __DIR__ . "/../../includes/popup_helper.php";
        setWarningPopup("Offer of $" . number_format($offer['amount']) . " has been rejected.", [
            'title' => 'Offer Rejected',
            'autoClose' => true,
            'autoCloseTime' => 3000
        ]);
        
        header("Location: index.php?p=dashboard&page=offers");
        exit;
    }

    else {
        require_once __DIR__ . "/../../includes/popup_helper.php";
        setErrorPopup("Invalid action specified.", [
            'title' => 'Error',
            'autoClose' => false
        ]);
        
        header("Location: index.php?p=dashboard&page=offers");
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    
    require_once __DIR__ . "/../../includes/popup_helper.php";
    setErrorPopup("Error processing offer: " . $e->getMessage(), [
        'title' => 'Processing Error',
        'autoClose' => false
    ]);
    
    header("Location: index.php?p=dashboard&page=offers");
    exit;
}
