<?php
require_once __DIR__ . '/../config.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to use wishlist'
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
    $listing_id = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
    $user_id = $_SESSION['user']['id'];

    // Validate input
    if ($listing_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid listing ID'
        ]);
        exit;
    }

    $pdo = db();
    
    // Check if listing exists and is active
    $stmt = $pdo->prepare("SELECT id, name FROM listings WHERE id = ? AND status = 'approved'");
    $stmt->execute([$listing_id]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$listing) {
        echo json_encode([
            'success' => false,
            'message' => 'Listing not found or not available'
        ]);
        exit;
    }
    
    // Check if already in wishlist
    $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND listing_id = ?");
    $stmt->execute([$user_id, $listing_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Remove from wishlist
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND listing_id = ?");
        $result = $stmt->execute([$user_id, $listing_id]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'action' => 'removed',
                'message' => 'Removed from wishlist',
                'in_wishlist' => false
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to remove from wishlist'
            ]);
        }
    } else {
        // Add to wishlist
        $stmt = $pdo->prepare("INSERT INTO wishlist (user_id, listing_id, created_at) VALUES (?, ?, NOW())");
        $result = $stmt->execute([$user_id, $listing_id]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'action' => 'added',
                'message' => 'Added to wishlist',
                'in_wishlist' => true
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to add to wishlist'
            ]);
        }
    }
    
} catch (PDOException $e) {
    error_log("Wishlist error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log("General wishlist error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}
?>