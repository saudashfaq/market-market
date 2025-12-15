<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/email_helper.php';
require_once __DIR__ . '/../../includes/notification_helper.php';
require_once __DIR__ . '/../../includes/log_helper.php';

require_login();

$user = current_user();
if ($user['role'] !== 'admin') {
    die("Access denied");
}

// Check if this is an AJAX request FIRST
$isAjax = isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// For AJAX requests, disable all output buffering and error display
if ($isAjax) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
}

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

// Get listing and seller details
$stmt = $pdo->prepare("
    SELECT l.*, u.email as seller_email, u.name as seller_name 
    FROM listings l 
    JOIN users u ON l.user_id = u.id 
    WHERE l.id = ?
");
$stmt->execute([$id]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$listing) {
    if ($isAjax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Listing not found']);
        exit;
    }
    die("Listing not found");
}

$status = ($action === 'verify') ? 'approved' : 'rejected';

// Update listing status and timestamp
$stmt = $pdo->prepare("UPDATE listings SET status = ?, updated_at = NOW() WHERE id = ?");
$stmt->execute([$status, $id]);

// Log admin action
log_action(
    "Listing " . ucfirst($status),
    "Admin ({$user['name']}) {$status} listing: {$listing['name']} (ID: {$id}) by {$listing['seller_name']}",
    "admin",
    $user['id'],
    $user['role']
);

// Create notification for user
if ($status === 'approved') {
    createNotification(
        $listing['user_id'],
        'listing',
        'Listing Approved',
        "Your listing '{$listing['name']}' has been approved and is now live",
        $id,
        'listing'
    );
} else {
    createNotification(
        $listing['user_id'],
        'listing',
        'Listing Rejected',
        "Your listing '{$listing['name']}' was not approved. Please review and resubmit",
        $id,
        'listing'
    );
}

// Notify all admins/superadmins about listing status change
notifyAdminsListingStatus($id, $listing['name'], $status, $listing['seller_name']);

// Handle AJAX vs Normal request
if ($isAjax) {
    // Clear any buffered output
    ob_clean();

    // Send JSON response IMMEDIATELY
    $statusText = ($status === 'approved') ? 'approved and is now live' : 'rejected';
    $response = [
        'success' => true,
        'listing_id' => $id,
        'listing_name' => $listing['name'],
        'status' => $status,
        'message' => "Listing '{$listing['name']}' has been {$statusText}."
    ];

    header('Content-Type: application/json');
    header('Content-Length: ' . strlen(json_encode($response)));
    header('Connection: close');
    echo json_encode($response);

    // Flush and close connection
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();

    // Close session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    // Now send email in background after response is sent
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    // Email sending (this happens after response is sent to browser)
    try {
        if ($status === 'approved') {
            $listingData = [
                'id' => $listing['id'],
                'title' => $listing['name'],
                'category' => $listing['category'] ?? 'N/A',
                'price' => $listing['asking_price'] ?? 0
            ];
            sendListingApprovedEmail($listing['seller_email'], $listing['seller_name'], $listingData);
        } else {
            $listingData = [
                'id' => $listing['id'],
                'title' => $listing['name'],
                'type' => $listing['type'] ?? 'website'
            ];
            $reason = $_GET['reason'] ?? 'Your listing does not meet our community guidelines.';
            sendListingRejectedEmail($listing['seller_email'], $listing['seller_name'], $listingData, $reason);
        }
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
    }

    exit;
} else {
    // Store success message in session
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $statusText = ($status === 'approved') ? 'approved and is now live' : 'rejected';
    $_SESSION['verification_success'] = [
        'listing_id' => $id,
        'listing_name' => $listing['name'],
        'status' => $status,
        'message' => "Listing '{$listing['name']}' has been {$statusText}. Email notification will be sent to seller."
    ];

    // Redirect
    header('Location: index.php?p=dashboard&page=listingverification');
    exit;
}
