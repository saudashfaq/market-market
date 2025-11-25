
<?php
/**
 * Place Bid Handler
 * Handles bid placement with all bidding system features
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../modules/bidding/EnhancedBiddingSystem.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to place a bid']);
    exit;
}

$pdo = db();
$biddingSystem = new EnhancedBiddingSystem($pdo);
$userId = $_SESSION['user_id'];

// Get POST data
$listingId = $_POST['listing_id'] ?? null;
$bidAmount = $_POST['bid_amount'] ?? null;
$downPaymentPercentage = $_POST['down_payment_percentage'] ?? 50.00;

if (!$listingId || !$bidAmount) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Get listing details
    $stmt = $pdo->prepare("SELECT * FROM listings WHERE id = ?");
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$listing) {
        echo json_encode(['success' => false, 'message' => 'Listing not found']);
        exit;
    }
    
    // Check if user is the seller
    if ($listing['user_id'] == $userId) {
        echo json_encode(['success' => false, 'message' => 'You cannot bid on your own listing']);
        exit;
    }
    
    // Place bid using enhanced bidding system
    $result = $biddingSystem->placeBid($listingId, $userId, $bidAmount, $downPaymentPercentage);
    
    // Log the action
    if ($result['success']) {
        log_action('bid_placed', "Placed bid of $" . number_format($bidAmount, 2) . " on listing: {$listing['name']}", 'bidding', $userId);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error placing bid: ' . $e->getMessage()
    ]);
}
?>

