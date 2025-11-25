<?php
/**
 * API: View Credentials (for AJAX)
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/encryption_helper.php';
require_once __DIR__ . '/../../includes/log_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = $_SESSION['user'];
$user_id = $user['id'];
$transaction_id = $_GET['transaction_id'] ?? null;

if (!$transaction_id) {
    echo json_encode(['success' => false, 'error' => 'Transaction ID required']);
    exit;
}

try {
    $pdo = db();
    
    // Fetch transaction (buyer only)
    $stmt = $pdo->prepare("
        SELECT t.*, lc.credentials_data
        FROM transactions t
        LEFT JOIN listing_credentials lc ON t.id = lc.transaction_id
        WHERE t.id = ? AND t.buyer_id = ?
    ");
    $stmt->execute([$transaction_id, $user_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        exit;
    }
    
    if (!$transaction['credentials_data']) {
        echo json_encode(['success' => false, 'error' => 'Credentials not yet submitted']);
        exit;
    }
    
    // Decrypt credentials
    $encryptionKey = $transaction['encryption_key'];
    
    // Convert hex to base64 if needed
    if (ctype_xdigit($encryptionKey) && strlen($encryptionKey) === 32) {
        $encryptionKey = base64_encode(hex2bin($encryptionKey));
    } elseif (strlen($encryptionKey) === 64 && ctype_xdigit($encryptionKey)) {
        $encryptionKey = base64_encode(hex2bin($encryptionKey));
    }
    
    $credentials = decryptCredentials($transaction['credentials_data'], $encryptionKey);
    
    if ($credentials === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to decrypt credentials']);
        exit;
    }
    
    // Log access
    logCredentialAccess($transaction_id, $user_id, 'view', $pdo);
    
    // Log to audit system
    log_action(
        "Credentials Viewed",
        "Transaction ID: {$transaction_id}, Buyer viewed credentials",
        "credentials",
        $user_id,
        "user"
    );
    
    echo json_encode([
        'success' => true,
        'credentials' => $credentials
    ]);
    
} catch (Exception $e) {
    error_log("View credentials error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to retrieve credentials'
    ]);
}
?>
