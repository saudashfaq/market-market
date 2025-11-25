<?php
/**
 * Encryption Helper for Credential Transfer System
 * Provides AES-256-CBC encryption/decryption utilities
 */

/**
 * Generate a unique encryption key for a transaction
 * @return string Base64 encoded encryption key
 */
function generateEncryptionKey() {
    $key = openssl_random_pseudo_bytes(32); // 256 bits
    return base64_encode($key);
}

/**
 * Encrypt credentials data using AES-256-CBC
 * @param array $credentialsArray Associative array of credentials
 * @param string $encryptionKey Base64 encoded encryption key
 * @return string|false Encrypted data or false on failure
 */
function encryptCredentials($credentialsArray, $encryptionKey) {
    try {
        // Convert array to JSON
        $jsonData = json_encode($credentialsArray);
        
        // Decode the base64 key
        $key = base64_decode($encryptionKey);
        
        // Generate initialization vector
        $ivLength = openssl_cipher_iv_length('AES-256-CBC');
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        // Encrypt the data
        $encrypted = openssl_encrypt(
            $jsonData,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($encrypted === false) {
            error_log("Encryption failed: " . openssl_error_string());
            return false;
        }
        
        // Combine IV and encrypted data, then base64 encode
        $encryptedData = base64_encode($iv . $encrypted);
        
        return $encryptedData;
        
    } catch (Exception $e) {
        error_log("Encryption error: " . $e->getMessage());
        return false;
    }
}

/**
 * Decrypt credentials data using AES-256-CBC
 * @param string $encryptedData Base64 encoded encrypted data with IV
 * @param string $encryptionKey Base64 encoded encryption key
 * @return array|false Decrypted credentials array or false on failure
 */
function decryptCredentials($encryptedData, $encryptionKey) {
    try {
        // Decode the base64 encrypted data
        $data = base64_decode($encryptedData);
        
        // Decode the base64 key
        $key = base64_decode($encryptionKey);
        
        // Extract IV and encrypted content
        $ivLength = openssl_cipher_iv_length('AES-256-CBC');
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        
        // Decrypt the data
        $decrypted = openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($decrypted === false) {
            error_log("Decryption failed: " . openssl_error_string());
            return false;
        }
        
        // Convert JSON back to array
        $credentialsArray = json_decode($decrypted, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg());
            return false;
        }
        
        return $credentialsArray;
        
    } catch (Exception $e) {
        error_log("Decryption error: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate encryption key format
 * @param string $encryptionKey Base64 encoded encryption key
 * @return bool True if valid, false otherwise
 */
function validateEncryptionKey($encryptionKey) {
    if (empty($encryptionKey)) {
        return false;
    }
    
    $decoded = base64_decode($encryptionKey, true);
    
    // Check if it's valid base64 and has correct length (32 bytes = 256 bits)
    return $decoded !== false && strlen($decoded) === 32;
}

/**
 * Check rate limiting for credential access
 * @param int $userId User ID accessing credentials
 * @param int $transactionId Transaction ID
 * @param mysqli $conn Database connection
 * @return bool True if access allowed, false if rate limit exceeded
 */
function checkCredentialAccessRateLimit($userId, $transactionId, $conn) {
    $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as access_count 
        FROM credential_access_logs 
        WHERE user_id = ? 
        AND transaction_id = ? 
        AND action_type = 'view'
        AND created_at >= ?
    ");
    
    $stmt->bind_param("iis", $userId, $transactionId, $oneHourAgo);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    // Allow max 10 views per hour
    return $row['access_count'] < 10;
}

/**
 * Log credential access
 * @param int $transactionId Transaction ID
 * @param int $userId User ID
 * @param string $actionType Type of action (view, submit, update, etc.)
 * @param PDO $pdo Database connection
 * @return bool True on success, false on failure
 */
function logCredentialAccess($transactionId, $userId, $actionType, $pdo) {
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        
        $stmt = $pdo->prepare("
            INSERT INTO credential_access_logs 
            (transaction_id, user_id, action_type, ip_address, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([$transactionId, $userId, $actionType, $ipAddress]);
    } catch (Exception $e) {
        // Table might not exist, just log and continue
        error_log("Credential access log failed: " . $e->getMessage());
        return false;
    }
}
?>
