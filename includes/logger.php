<?php
/**
 * Logging Helper Functions
 * Automatically logs user activities to the logs table
 */

/**
 * Log an activity to the database
 * 
 * @param string $action The action being performed (e.g., 'login', 'create_listing', 'update_profile')
 * @param string $details Additional details about the action
 * @param int|null $userId User ID (if null, uses current logged-in user)
 * @param string|null $role User role (if null, uses current user's role)
 * @return bool Success status
 */
function logActivity($action, $details = '', $userId = null, $role = null) {
    try {
        $pdo = db();
        
        // Get current user if not provided
        if ($userId === null || $role === null) {
            $currentUser = current_user();
            if ($currentUser) {
                $userId = $userId ?? $currentUser['id'];
                $role = $role ?? $currentUser['role'];
            }
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

/**
 * Log user login
 */
function logLogin($userId = null) {
    $user = $userId ? null : current_user();
    $userName = $user ? $user['name'] : 'Unknown';
    logActivity('login', "User logged in: $userName", $userId);
}

/**
 * Log user logout
 */
function logLogout($userId = null) {
    $user = $userId ? null : current_user();
    $userName = $user ? $user['name'] : 'Unknown';
    logActivity('logout', "User logged out: $userName", $userId);
}

/**
 * Log listing creation
 */
function logListingCreate($listingId, $listingName) {
    logActivity('create_listing', "Created listing: $listingName (ID: $listingId)");
}

/**
 * Log listing update
 */
function logListingUpdate($listingId, $listingName) {
    logActivity('update_listing', "Updated listing: $listingName (ID: $listingId)");
}

/**
 * Log listing delete
 */
function logListingDelete($listingId, $listingName) {
    logActivity('delete_listing', "Deleted listing: $listingName (ID: $listingId)");
}

/**
 * Log offer creation
 */
function logOfferCreate($offerId, $listingName, $amount) {
    logActivity('create_offer', "Created offer for: $listingName - Amount: $" . number_format($amount));
}

/**
 * Log offer acceptance
 */
function logOfferAccept($offerId, $listingName) {
    logActivity('accept_offer', "Accepted offer for: $listingName (Offer ID: $offerId)");
}

/**
 * Log offer rejection
 */
function logOfferReject($offerId, $listingName) {
    logActivity('reject_offer', "Rejected offer for: $listingName (Offer ID: $offerId)");
}

/**
 * Log order creation
 */
function logOrderCreate($orderId, $listingName, $amount) {
    logActivity('create_order', "Created order for: $listingName - Amount: $" . number_format($amount) . " (Order ID: $orderId)");
}

/**
 * Log profile update
 */
function logProfileUpdate() {
    logActivity('update_profile', "Updated profile information");
}

/**
 * Log password change
 */
function logPasswordChange() {
    logActivity('change_password', "Changed password");
}

/**
 * Log admin action
 */
function logAdminAction($action, $details) {
    logActivity($action, $details);
}

/**
 * Log failed login attempt
 */
function logFailedLogin($email) {
    try {
        $pdo = db();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        $stmt = $pdo->prepare("
            INSERT INTO logs (user_id, role, action, details, ip_address, created_at)
            VALUES (NULL, 'guest', 'failed_login', ?, ?, NOW())
        ");
        
        $stmt->execute(["Failed login attempt for: $email", $ipAddress]);
        
    } catch (Exception $e) {
        error_log("Logging Error: " . $e->getMessage());
    }
}

/**
 * Log page view (optional - can be used for analytics)
 */
function logPageView($pageName) {
    // Only log for authenticated users
    $user = current_user();
    if ($user) {
        logActivity('page_view', "Viewed page: $pageName");
    }
}
