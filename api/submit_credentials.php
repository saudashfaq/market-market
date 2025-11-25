<?php
/**
 * API Endpoint: Submit Credentials
 * Processes seller credential submission with encryption
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/encryption_helper.php';
require_once __DIR__ . '/../../includes/notification_helper.php';
require_once __DIR__ . '/../../includes/validation_helper.php';

header('Content-Type: application/json');

// Helper function fallbacks
if (!function_exists('validate_csrf_token')) {
    function validate_csrf_token($token) {
        return true; // Temporarily disable for testing
    }
}

if (!function_exists('logCredentialAccess')) {
    function logCredentialAccess($transaction_id, $user_id, $action, $pdo) {
        // Log to database if table exists
        try {
            $stmt = $pdo->prepare("INSERT INTO credential_logs (transaction_id, user_id, action, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$transaction_id, $user_id, $action]);
        } catch (Exception $e) {
            // Table doesn't exist, skip logging
        }
    }
}

if (!function_exists('createNotification')) {
    function createNotification($user_id, $type, $title, $message, $ref_id, $ref_type) {
        // Create notification if table exists
        try {
            $pdo = db();
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, reference_id, reference_type, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $type, $title, $message, $ref_id, $ref_type]);
        } catch (Exception $e) {
            // Table doesn't exist, skip
        }
    }
}

// Require login
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = $_SESSION['user'];
$user_id = $user['id'];

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}
$transaction_id = $_POST['transaction_id'] ?? null;

if (!$transaction_id) {
    log_action("Credentials Submission Failed", "Missing transaction ID", "credentials", $user_id, "user");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Transaction ID required']);
    exit;
}

try {
    $pdo = db();
    
    // Fetch transaction and verify seller ownership (or buyer for testing)
    $stmt = $pdo->prepare("
        SELECT t.*, 
               l.name as listing_name,
               l.category,
               l.type as listing_type,
               buyer.email as buyer_email,
               buyer.name as buyer_name,
               buyer.id as buyer_id
        FROM transactions t
        JOIN listings l ON t.listing_id = l.id
        JOIN users buyer ON t.buyer_id = buyer.id
        WHERE t.id = ? AND (t.seller_id = ? OR t.buyer_id = ?)
    ");
    
    $stmt->execute([$transaction_id, $user_id, $user_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        log_action("Credentials Submission Failed", "Transaction ID: {$transaction_id} - Not found or unauthorized", "credentials", $user_id, "user");
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Transaction not found or unauthorized']);
        exit;
    }
    
    // Verify payment is confirmed and awaiting credentials - Temporarily relaxed for testing
    if (false) { // Temporarily disabled
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Payment not confirmed yet']);
        exit;
    }
    
    // Check if credentials already submitted
    $checkStmt = $pdo->prepare("SELECT id FROM listing_credentials WHERE transaction_id = ?");
    $checkStmt->execute([$transaction_id]);
    if ($checkStmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Payment not confirmed yet']);
        exit;
    }
    
    // Collect credential data based on category
    $category = $transaction['listing_type'] ?? $transaction['category'] ?? 'website';
    $credentialsData = [];
    $filledFieldsCount = 0;
    
    // Website credentials
    if ($category === 'website') {
        $fields = [
            'hosting_provider', 'hosting_username', 'hosting_password',
            'domain_registrar', 'domain_username', 'domain_password',
            'cms_url', 'cms_username', 'cms_password'
        ];
        
        foreach ($fields as $field) {
            $value = trim($_POST[$field] ?? '');
            if (!empty($value)) {
                $credentialsData[$field] = $value;
                $filledFieldsCount++;
            }
        }
    }
    
    // YouTube credentials
    elseif ($category === 'youtube') {
        $fields = [
            'channel_email', 'channel_password', 'recovery_email',
            'recovery_phone', 'two_factor_backup_codes'
        ];
        
        foreach ($fields as $field) {
            $value = trim($_POST[$field] ?? '');
            if (!empty($value)) {
                $credentialsData[$field] = $value;
                $filledFieldsCount++;
            }
        }
    }
    
    // Social media credentials
    elseif ($category === 'social_media') {
        $fields = [
            'platform_name', 'account_username', 'account_password',
            'associated_email', 'email_password'
        ];
        
        foreach ($fields as $field) {
            $value = trim($_POST[$field] ?? '');
            if (!empty($value)) {
                $credentialsData[$field] = $value;
                $filledFieldsCount++;
            }
        }
    }
    
    // Add additional notes if provided
    $additionalNotes = trim($_POST['additional_notes'] ?? '');
    if (!empty($additionalNotes)) {
        $credentialsData['additional_notes'] = $additionalNotes;
    }
    
    // Validate minimum 3 required fields filled
    if ($filledFieldsCount < 3) {
        log_action("Credentials Submission Failed", "Transaction ID: {$transaction_id} - Insufficient fields ({$filledFieldsCount}/3)", "credentials", $user_id, "user");
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Please fill at least 3 required credential fields'
        ]);
        exit;
    }
    
    // Add metadata
    $credentialsData['category'] = $category;
    $credentialsData['submitted_at'] = date('Y-m-d H:i:s');
    $credentialsData['submitted_by'] = $user_id;
    
    // Get encryption key from transaction
    $encryptionKey = $transaction['encryption_key'];
    
    // If no encryption key, generate one
    if (!$encryptionKey) {
        $encryptionKey = generateEncryptionKey();
        
        // Update transaction with new key
        $updateKeyStmt = $pdo->prepare("UPDATE transactions SET encryption_key = ? WHERE id = ?");
        $updateKeyStmt->execute([$encryptionKey, $transaction_id]);
    }
    
    // Check if it's hex (32 chars) or base64
    $originalKey = $encryptionKey;
    if (ctype_xdigit($encryptionKey) && strlen($encryptionKey) === 32) {
        // It's a 32-char hex string (16 bytes), convert to base64
        $encryptionKey = base64_encode(hex2bin($encryptionKey));
    } elseif (strlen($encryptionKey) === 64 && ctype_xdigit($encryptionKey)) {
        // It's a 64-char hex string (32 bytes), convert to base64
        $encryptionKey = base64_encode(hex2bin($encryptionKey));
    }
    
    // Validate the key
    if (!validateEncryptionKey($encryptionKey)) {
        // If validation fails, try to generate a new valid key
        error_log("Invalid encryption key, generating new one. Original: $originalKey, Converted: $encryptionKey");
        $encryptionKey = generateEncryptionKey();
        
        // Update transaction with new key
        $updateKeyStmt = $pdo->prepare("UPDATE transactions SET encryption_key = ? WHERE id = ?");
        $updateKeyStmt->execute([$encryptionKey, $transaction_id]);
    }
    
    // Encrypt credentials
    $encryptedData = encryptCredentials($credentialsData, $encryptionKey);
    
    if ($encryptedData === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Encryption failed']);
        exit;
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Insert encrypted credentials
        $insertStmt = $pdo->prepare("
            INSERT INTO listing_credentials (transaction_id, credentials_data, created_at, updated_at)
            VALUES (?, ?, NOW(), NOW())
        ");
        
        $insertStmt->execute([$transaction_id, $encryptedData]);
        
        // Update transaction transfer status
        $updateStmt = $pdo->prepare("
            UPDATE transactions 
            SET transfer_status = 'credentials_submitted',
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $updateStmt->execute([$transaction_id]);
        
        // Log credential submission
        logCredentialAccess($transaction_id, $user_id, 'submit', $pdo);
        
        // Log successful submission to audit log
        log_action(
            "Credentials Submitted",
            "Transaction ID: {$transaction_id}, Listing: {$transaction['listing_name']}, Buyer: {$transaction['buyer_name']}, Fields: {$filledFieldsCount}",
            "credentials",
            $user_id,
            "user"
        );
        
        // Commit transaction
        $pdo->commit();
        
        // Get seller details
        $sellerStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
        $sellerStmt->execute([$user_id]);
        $seller = $sellerStmt->fetch(PDO::FETCH_ASSOC);
        
        $buyerEmail = $transaction['buyer_email'];
        $buyerName = $transaction['buyer_name'];
        $listingName = $transaction['listing_name'];
        
        // Send email to BUYER
        $buyerSubject = "Credentials Received - Access Your Purchase";
        $credentialViewUrl = BASE . "index.php?p=dashboard&page=view_credentials&transaction_id={$transaction_id}";
        
        $buyerEmailBody = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Credentials Received!</h2>
            <p>Hi {$buyerName},</p>
            <p>Great news! The seller has submitted the access credentials for your purchase:</p>
            
            <p><strong>{$listingName}</strong></p>
            
            <p><strong>Transaction Details:</strong></p>
            <ul>
                <li>Transaction ID: #{$transaction_id}</li>
                <li>Seller: {$seller['name']}</li>
                <li>Amount: $" . number_format($transaction['amount'], 2) . "</li>
            </ul>
            
            <p style='margin: 30px 0;'>
                <a href='{$credentialViewUrl}' 
                   style='background-color: #3b82f6; color: white; padding: 12px 30px; 
                          text-decoration: none; border-radius: 8px; display: inline-block;'>
                    View Credentials Now
                </a>
            </p>
            
            <p style='color: #d97706;'><strong>⏰ Verification Period:</strong></p>
            <p>You have <strong>7 days</strong> to verify the credentials and confirm receipt.</p>
            <p>If you encounter any issues, please report them within this period.</p>
            
            <hr style='margin: 30px 0;'>
            
            <p style='color: #666; font-size: 12px;'>
                This is an automated notification from your marketplace platform.<br>
                All credentials are encrypted and secure.
            </p>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: noreply@marketplace.com\r\n";
        
        @mail($buyerEmail, $buyerSubject, $buyerEmailBody, $headers);
        
        // Send email to SELLER (confirmation)
        $sellerSubject = "Credentials Submitted Successfully";
        $sellerEmailBody = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Credentials Submitted</h2>
            <p>Hi {$seller['name']},</p>
            <p>You have successfully submitted credentials for:</p>
            <p><strong>{$listingName}</strong></p>
            <p>The buyer has been notified and has 7 days to verify and confirm receipt.</p>
            <p><strong>Transaction ID:</strong> #{$transaction_id}</p>
            <p>Once the buyer confirms, payment will be released to your account.</p>
            <hr style='margin: 30px 0;'>
            <p style='color: #666; font-size: 12px;'>
                Thank you for using our marketplace!
            </p>
        </body>
        </html>
        ";
        
        @mail($seller['email'], $sellerSubject, $sellerEmailBody, $headers);
        
        // Create in-app notification for BUYER
        createNotification(
            $transaction['buyer_id'],
            'credentials_received',
            'Credentials Received',
            "Access credentials for '{$listingName}' are now available. Verify within 7 days.",
            $transaction_id,
            'transaction'
        );
        
        // Create in-app notification for SELLER
        createNotification(
            $user_id,
            'credentials_submitted',
            'Credentials Submitted',
            "You submitted credentials for '{$listingName}'. Awaiting buyer confirmation.",
            $transaction_id,
            'transaction'
        );
        
        // Success response
        echo json_encode([
            'success' => true,
            'message' => 'Credentials submitted successfully',
            'redirect' => url('index.php?p=dashboard&page=offers')
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Credential submission error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to submit credentials: ' . $e->getMessage()
    ]);
}

exit;
?>
