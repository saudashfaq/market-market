<?php
/**
 * Admin Stats API - Real-time stats for admin dashboard
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middlewares/auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    $user = current_user();
    
    // Check if user is admin
    if (!$user || ($user['role'] !== 'admin' && $user['role'] !== 'superadmin' && $user['role'] !== 'admin_staff')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $pdo = db();
    
    // Get listings stats
    $listingsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold
        FROM listings
    ");
    $listingsStats = $listingsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get pending offers count
    $offersStmt = $pdo->query("SELECT COUNT(*) as total FROM offers WHERE status = 'pending'");
    $offersCount = $offersStmt->fetchColumn() ?: 0;
    
    // Get active orders count
    $ordersStmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status IN ('pending_payment', 'paid', 'in_progress')");
    $ordersCount = $ordersStmt->fetchColumn() ?: 0;
    
    // Get total users count
    $usersStmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $usersCount = $usersStmt->fetchColumn() ?: 0;
    
    // Calculate escrow balance
    $escrowStmt = $pdo->query("SELECT SUM(amount) as total FROM orders WHERE status = 'paid'");
    $escrowBalance = $escrowStmt->fetchColumn() ?: 0;
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'listings' => $listingsStats,
            'offers' => $offersCount,
            'orders' => $ordersCount,
            'users' => $usersCount,
            'escrow' => $escrowBalance
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
