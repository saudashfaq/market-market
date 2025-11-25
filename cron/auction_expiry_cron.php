<?php
/**
 * Auction Expiry Cron Job
 * Processes time-based auction endings
 * Run every minute: * * * * * /usr/bin/php /path/to/auction_expiry_cron.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modules/bidding/EnhancedBiddingSystem.php';

// Prevent multiple instances
$lockFile = __DIR__ . '/auction_cron.lock';
if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    if (time() - $lockTime < 300) { // 5 minutes
        exit('Cron job already running');
    }
    unlink($lockFile);
}

file_put_contents($lockFile, getmypid());

try {
    $pdo = db();
    $biddingSystem = new EnhancedBiddingSystem($pdo);
    
    // Update cron job status
    $stmt = $pdo->prepare("
        INSERT INTO auction_cron_jobs (job_type, status, next_run) 
        VALUES ('expire_auctions', 'running', DATE_ADD(NOW(), INTERVAL 1 MINUTE))
        ON DUPLICATE KEY UPDATE 
        status = 'running', 
        last_run = NOW(),
        next_run = DATE_ADD(NOW(), INTERVAL 1 MINUTE)
    ");
    $stmt->execute();
    $jobId = $pdo->lastInsertId();
    
    // Process expired auctions
    $result = $biddingSystem->processExpiredAuctions();
    $processedCount = count($result);
    
    // Update job completion
    $stmt = $pdo->prepare("
        UPDATE auction_cron_jobs 
        SET status = 'completed', processed_count = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$processedCount, $jobId]);
    
    // Log results
    error_log("Auction Cron: Processed {$processedCount} expired auctions");
    
    // Cleanup old logs (keep last 30 days)
    $stmt = $pdo->prepare("
        DELETE FROM secure_bidding_logs 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    
    echo "SUCCESS: Processed {$processedCount} expired auctions\n";
    
} catch (Exception $e) {
    // Update job error status
    if (isset($jobId)) {
        $stmt = $pdo->prepare("
            UPDATE auction_cron_jobs 
            SET status = 'failed', error_message = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$e->getMessage(), $jobId]);
    }
    
    error_log("Auction Cron Error: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
    
} finally {
    // Remove lock file
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}
?>