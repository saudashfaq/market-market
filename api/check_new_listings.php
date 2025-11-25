<?php
/**
 * Check for new approved listings
 * Simple API endpoint for home and listing pages polling
 */

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    $pdo = db();
    
    // Get the timestamp from query parameter
    $since = $_GET['since'] ?? '1970-01-01 00:00:00';
    
    // Validate timestamp format
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
        $since = '1970-01-01 00:00:00';
    }
    
    // Check for new approved listings since the given timestamp
    // Use updated_at to catch re-approved listings too
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, MAX(GREATEST(created_at, COALESCE(updated_at, created_at))) as latest
        FROM listings 
        WHERE status = 'approved' 
        AND GREATEST(created_at, COALESCE(updated_at, created_at)) > ?
    ");
    $stmt->execute([$since]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug logging
    error_log("Polling check - Since: $since, Count: {$result['count']}, Latest: {$result['latest']}");
    
    $hasNew = $result['count'] > 0;
    $latestTimestamp = $result['latest'] ?? $since;
    
    echo json_encode([
        'success' => true,
        'has_new' => $hasNew,
        'count' => (int)$result['count'],
        'latest_timestamp' => $latestTimestamp,
        'checked_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Check new listings error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'server_error',
        'message' => 'Failed to check for new listings'
    ]);
}
