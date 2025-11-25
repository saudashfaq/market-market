<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middlewares/auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    require_login();
    $user = current_user();
    
    // Check if user is admin/superadmin
    if (!in_array($user['role'], ['admin', 'super_admin', 'superAdmin', 'superadmin'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Access denied'
        ]);
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
    
    // Get offers count
    $offersStmt = $pdo->query("SELECT COUNT(*) as total FROM offers WHERE status = 'pending'");
    $offersCount = $offersStmt->fetchColumn() ?: 0;
    
    // Get orders count
    $ordersStmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status IN ('pending', 'paid')");
    $ordersCount = $ordersStmt->fetchColumn() ?: 0;
    
    // Get escrow balance
    $escrowStmt = $pdo->query("SELECT SUM(amount) as total FROM orders WHERE status = 'paid'");
    $escrowBalance = $escrowStmt->fetchColumn() ?: 0;
    
    // Get disputes count
    try {
        $disputesStmt = $pdo->query("SELECT COUNT(*) as total FROM disputes WHERE status = 'open'");
        $disputesCount = $disputesStmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        $disputesCount = 0;
    }
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'listings' => $listingsStats,
            'offers' => $offersCount,
            'orders' => $ordersCount,
            'escrow' => $escrowBalance,
            'disputes' => $disputesCount
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
