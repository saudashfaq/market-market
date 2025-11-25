<?php
require_once __DIR__ . '/../config.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/log_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to make an offer'
    ]);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

try {
    // Get form data
    $listing_id = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $user_id = $_SESSION['user']['id'];

    // Validate input
    if ($listing_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid listing ID'
        ]);
        exit;
    }

    if ($amount <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid offer amount'
        ]);
        exit;
    }

    // Message is optional now
    if (empty($message)) {
        $message = 'No message provided';
    }

    $pdo = db();
    
    // Check if listing exists and is active - INCLUDE BIDDING FIELDS
    $stmt = $pdo->prepare("SELECT id, name, asking_price, user_id, reserved_amount, min_down_payment_percentage FROM listings WHERE id = ? AND status = 'approved'");
    $stmt->execute([$listing_id]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$listing) {
        echo json_encode([
            'success' => false,
            'message' => 'Listing not found or not available'
        ]);
        exit;
    }
    
    // ✅ SUPERADMIN MINIMUM OFFER VALIDATION
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'min_offer_percentage'");
    $stmt->execute();
    $minOfferPercentage = floatval($stmt->fetchColumn() ?: 70); // Default 70%
    
    $askingPrice = floatval($listing['asking_price']);
    $minOfferAmount = ($askingPrice * $minOfferPercentage) / 100;
    
    if ($amount < $minOfferAmount) {
        echo json_encode([
            'success' => false,
            'message' => 'Your offer must be at least ' . number_format($minOfferPercentage, 0) . '% of the asking price ($' . number_format($minOfferAmount, 2) . ') as set by system administrator'
        ]);
        exit;
    }
    
    // ✅ SELLER'S RESERVED AMOUNT CHECK (if set)
    $reservedAmount = floatval($listing['reserved_amount'] ?? 0);
    if ($reservedAmount > 0 && $amount < $reservedAmount) {
        echo json_encode([
            'success' => false,
            'message' => 'Your offer must be at least $' . number_format($reservedAmount, 2) . ' (seller\'s minimum price)'
        ]);
        exit;
    }
    
    // Check if user is not trying to make offer on their own listing
    if ($listing['user_id'] == $user_id) {
        echo json_encode([
            'success' => false,
            'message' => 'You cannot make an offer on your own listing'
        ]);
        exit;
    }
    
    // Check if user has already made an offer on this listing
    $stmt = $pdo->prepare("SELECT id FROM offers WHERE listing_id = ? AND user_id = ? AND status IN ('pending', 'accepted')");
    $stmt->execute([$listing_id, $user_id]);
    $existing_offer = $stmt->fetch();
    
    if ($existing_offer) {
        echo json_encode([
            'success' => false,
            'message' => 'You already have a pending offer on this listing'
        ]);
        exit;
    }
    
    // Insert the offer (no down payment for regular offers)
    $stmt = $pdo->prepare("
        INSERT INTO offers (listing_id, user_id, seller_id, amount, message, is_private, status, created_at) 
        VALUES (?, ?, ?, ?, ?, 0, 'pending', NOW())
    ");
    
    $result = $stmt->execute([
        $listing_id,
        $user_id,
        $listing['user_id'],
        $amount,
        $message
    ]);
    
    if ($result) {
        $offer_id = $pdo->lastInsertId();
        
        // Send immediate response to user
        echo json_encode([
            'success' => true,
            'message' => 'Your offer has been submitted successfully!',
            'offer_id' => $offer_id
        ]);
        
        // Flush output to send response immediately
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
        
        // Close connection so user gets response immediately
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        
        // Now do background tasks (logging, email, notification)
        try {
            // Log the action
            log_action(
                "Offer Created",
                "User (ID: $user_id) made an offer (#$offer_id) of $amount for Listing ID: $listing_id",
                "offers",
                $user_id
            );
            
            // Send email to seller
            if (file_exists(__DIR__ . '/../includes/email_helper.php')) {
                require_once __DIR__ . '/../includes/email_helper.php';
                
                // Get seller details
                $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
                $stmt->execute([$listing['user_id']]);
                $seller = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get buyer details
                $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $buyer = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($seller && $buyer) {
                    $offerData = [
                        'amount' => $amount,
                        'message' => $message,
                        'buyer_name' => $buyer['name']
                    ];
                    
                    $listingData = [
                        'id' => $listing['id'],
                        'title' => $listing['name'],
                        'price' => $listing['asking_price']
                    ];
                    
                    sendOfferReceivedEmail($seller['email'], $seller['name'], $offerData, $listingData);
                }
            }
            
            // Create notification for seller
            if (file_exists(__DIR__ . '/../includes/notification_helper.php')) {
                require_once __DIR__ . '/../includes/notification_helper.php';
                $buyerName = $_SESSION['user']['name'] ?? 'A buyer';
                notifyNewOffer($listing['user_id'], $offer_id, $buyerName, $listing['name'], $amount);
            }
        } catch (Exception $e) {
            // Log background task errors but don't affect user response
            error_log("Background task error in offer submission: " . $e->getMessage());
        }
        
        exit; // Important: stop execution after background tasks
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to submit offer. Please try again.'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Offer submission error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log("General error in offer submission: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}
?>