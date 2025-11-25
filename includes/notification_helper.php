<?php
/**
 * Notification Helper Functions
 * Create and manage notifications
 */

/**
 * Create a new notification
 */
function createNotification($userId, $type, $title, $message, $relatedId = null, $relatedType = null) {
    try {
        $pdo = db();
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, related_id, related_type, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $type,
            $title,
            $message,
            $relatedId,
            $relatedType
        ]);
        
        return $pdo->lastInsertId();
        
    } catch (Exception $e) {
        error_log("Notification creation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Create notification for new offer (seller receives)
 */
function notifyNewOffer($sellerId, $offerId, $buyerName, $listingName, $amount) {
    $title = "New Offer Received";
    $message = "{$buyerName} offered $" . number_format($amount) . " for '{$listingName}'";
    
    return createNotification($sellerId, 'offer', $title, $message, $offerId, 'offer');
}

/**
 * Create notification for offer accepted (buyer receives)
 */
function notifyOfferAccepted($buyerId, $offerId, $listingName) {
    $title = "Offer Accepted!";
    $message = "Your offer for '{$listingName}' has been accepted";
    
    return createNotification($buyerId, 'offer', $title, $message, $offerId, 'offer');
}

/**
 * Create notification for offer rejected (buyer receives)
 */
function notifyOfferRejected($buyerId, $offerId, $listingName) {
    $title = "Offer Declined";
    $message = "Your offer for '{$listingName}' was declined";
    
    return createNotification($buyerId, 'offer', $title, $message, $offerId, 'offer');
}

/**
 * Create notification for new message
 */
function notifyNewMessage($recipientId, $senderName, $messagePreview, $conversationId) {
    $title = "💬 New message from {$senderName}";
    $message = "📩 " . $messagePreview;
    
    return createNotification($recipientId, 'message', $title, $message, $conversationId, 'conversation');
}

/**
 * Create notification for order status change
 */
function notifyOrderStatus($userId, $orderId, $status, $listingName) {
    $statusMessages = [
        'pending_payment' => 'Payment pending',
        'paid' => 'Payment received',
        'in_progress' => 'Order in progress',
        'completed' => 'Order completed',
        'cancelled' => 'Order cancelled'
    ];
    
    $title = "📦 Order Update";
    $message = "Order for '{$listingName}' - " . ($statusMessages[$status] ?? $status);
    
    return createNotification($userId, 'order', $title, $message, $orderId, 'order');
}

/**
 * Create notification for new listing (admin)
 */
function notifyNewListing($listingId, $listingName, $userName) {
    try {
        $pdo = db();
        
        // Get all admin/superadmin users
        $admins = $pdo->query("
            SELECT id FROM users 
            WHERE role IN ('admin', 'superadmin') 
            AND status = 'active'
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        $title = "New Listing Pending";
        $message = "{$userName} submitted '{$listingName}' for approval";
        
        foreach ($admins as $adminId) {
            createNotification($adminId, 'listing', $title, $message, $listingId, 'listing');
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Admin notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notification count for user
 */
function getUnreadCount($userId) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
        
    } catch (Exception $e) {
        error_log("Get unread count error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Notify all admins/superadmins about new ticket
 */
function notifyAdminsNewTicket($ticketId, $ticketSubject, $userName) {
    try {
        $pdo = db();
        
        // Get all admin/superadmin users
        $admins = $pdo->query("
            SELECT id FROM users 
            WHERE role IN ('admin', 'superadmin') 
            AND status = 'active'
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        $title = "🎫 New Support Ticket";
        $message = "🆘 {$userName} created ticket: '{$ticketSubject}'";
        
        foreach ($admins as $adminId) {
            createNotification($adminId, 'ticket', $title, $message, $ticketId, 'ticket');
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Admin ticket notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify all admins/superadmins about new offer
 */
function notifyAdminsNewOffer($offerId, $buyerName, $listingName, $amount) {
    try {
        $pdo = db();
        
        // Get all admin/superadmin users
        $admins = $pdo->query("
            SELECT id FROM users 
            WHERE role IN ('admin', 'superadmin') 
            AND status = 'active'
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        $title = "New Offer Made";
        $message = "{$buyerName} offered $" . number_format($amount) . " for '{$listingName}'";
        
        foreach ($admins as $adminId) {
            createNotification($adminId, 'offer', $title, $message, $offerId, 'offer');
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Admin offer notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify all admins/superadmins about payment received
 */
function notifyAdminsPaymentReceived($orderId, $amount, $listingName) {
    try {
        $pdo = db();
        
        // Get all admin/superadmin users
        $admins = $pdo->query("
            SELECT id FROM users 
            WHERE role IN ('admin', 'superadmin') 
            AND status = 'active'
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        $title = "Payment Received";
        $message = "Payment of $" . number_format($amount) . " received for '{$listingName}'";
        
        foreach ($admins as $adminId) {
            createNotification($adminId, 'payment', $title, $message, $orderId, 'order');
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Admin payment notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify all admins/superadmins about dispute
 */
function notifyAdminsDispute($disputeId, $orderId, $userName, $reason) {
    try {
        $pdo = db();
        
        // Get all admin/superadmin users
        $admins = $pdo->query("
            SELECT id FROM users 
            WHERE role IN ('admin', 'superadmin') 
            AND status = 'active'
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        $title = "New Dispute Raised";
        $message = "{$userName} raised a dispute for order #{$orderId}: {$reason}";
        
        foreach ($admins as $adminId) {
            createNotification($adminId, 'dispute', $title, $message, $disputeId, 'dispute');
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Admin dispute notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify all admins/superadmins about new user registration
 */
function notifyAdminsNewUser($userId, $userName, $userEmail) {
    try {
        $pdo = db();
        
        // Get all admin/superadmin users
        $admins = $pdo->query("
            SELECT id FROM users 
            WHERE role IN ('admin', 'superadmin') 
            AND status = 'active'
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        $title = "New User Registered";
        $message = "{$userName} ({$userEmail}) just registered";
        
        foreach ($admins as $adminId) {
            createNotification($adminId, 'user', $title, $message, $userId, 'user');
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Admin user notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify all admins/superadmins about listing status change
 */
function notifyAdminsListingStatus($listingId, $listingName, $status, $userName) {
    try {
        $pdo = db();
        
        // Get all admin/superadmin users
        $admins = $pdo->query("
            SELECT id FROM users 
            WHERE role IN ('admin', 'superadmin') 
            AND status = 'active'
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        $statusMessages = [
            'pending' => 'submitted for review',
            'approved' => 'has been approved',
            'rejected' => 'has been rejected',
            'sold' => 'has been sold'
        ];
        
        $title = "Listing Status Update";
        $message = "'{$listingName}' by {$userName} " . ($statusMessages[$status] ?? $status);
        
        foreach ($admins as $adminId) {
            createNotification($adminId, 'listing', $title, $message, $listingId, 'listing');
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Admin listing status notification error: " . $e->getMessage());
        return false;
    }
}
