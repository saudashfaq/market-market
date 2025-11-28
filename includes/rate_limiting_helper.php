<?php
/**
 * Rate Limiting Helper for Email Resend Functionality
 */

/**
 * Check if email resend is rate limited for a given email address
 * @param string $email Email address to check
 * @param int $maxAttempts Maximum attempts allowed (default: 3)
 * @param int $timeWindowMinutes Time window in minutes (default: 5)
 * @return bool True if rate limited, false otherwise
 */
function isResendRateLimited($email, $maxAttempts = 3, $timeWindowMinutes = 5) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM email_resend_attempts 
            WHERE email = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$email, $timeWindowMinutes]);
        $result = $stmt->fetch();
        
        return $result['attempts'] >= $maxAttempts;
    } catch (Exception $e) {
        // If database error, allow the attempt but log the error
        error_log("Rate limiting check failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Record a resend attempt for rate limiting
 * @param string $email Email address
 * @return bool True if recorded successfully, false otherwise
 */
function recordResendAttempt($email) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            INSERT INTO email_resend_attempts (email, ip_address, user_agent) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $email, 
            $_SERVER['REMOTE_ADDR'] ?? '', 
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Failed to record resend attempt: " . $e->getMessage());
        return false;
    }
}

/**
 * Get remaining wait time for rate limited email
 * @param string $email Email address
 * @param int $timeWindowMinutes Time window in minutes (default: 5)
 * @return int Minutes to wait, 0 if not rate limited
 */
function getRemainingWaitTime($email, $timeWindowMinutes = 5) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT MAX(attempt_time) as last_attempt
            FROM email_resend_attempts 
            WHERE email = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$email, $timeWindowMinutes]);
        $result = $stmt->fetch();
        
        if ($result['last_attempt']) {
            $lastAttempt = new DateTime($result['last_attempt']);
            $now = new DateTime();
            $diff = $now->getTimestamp() - $lastAttempt->getTimestamp();
            $waitTime = ($timeWindowMinutes * 60) - $diff;
            return max(0, ceil($waitTime / 60));
        }
        
        return 0;
    } catch (Exception $e) {
        error_log("Failed to get remaining wait time: " . $e->getMessage());
        return 0;
    }
}

/**
 * Clean up old resend attempts (for maintenance)
 * @param int $daysOld Days old to clean up (default: 7)
 * @return int Number of records cleaned up
 */
function cleanupOldResendAttempts($daysOld = 7) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            DELETE FROM email_resend_attempts 
            WHERE attempt_time < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$daysOld]);
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Failed to cleanup old resend attempts: " . $e->getMessage());
        return 0;
    }
}

/**
 * Create the email_resend_attempts table if it doesn't exist
 * @return bool True if table exists or was created successfully
 */
function ensureResendAttemptsTable() {
    try {
        $pdo = db();
        $sql = "CREATE TABLE IF NOT EXISTS email_resend_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            user_agent TEXT,
            INDEX idx_email_time (email, attempt_time),
            INDEX idx_ip_time (ip_address, attempt_time)
        )";
        $pdo->exec($sql);
        return true;
    } catch (Exception $e) {
        error_log("Failed to create email_resend_attempts table: " . $e->getMessage());
        return false;
    }
}
?>