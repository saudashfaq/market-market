<?php
/**
 * API: Report Credential Issue
 */

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = $_SESSION['user'];
$user_id = $user['id'];

$input = json_decode(file_get_contents('php://input'), true);
$transaction_id = $input['transaction_id'] ?? null;
$reason = $input['reason'] ?? '';

if (!$transaction_id || empty($reason)) {
    echo json_encode(['success' => false, 'error' => 'Transaction ID and reason required']);
    exit;
}

try {
    $pdo = db();
    
    // Verify buyer ownership
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND buyer_id = ?");
    $stmt->execute([$transaction_id, $user_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        exit;
    }
    
    // Update status to disputed
    $updateStmt = $pdo->prepare("
        UPDATE transactions 
        SET transfer_status = 'disputed',
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$transaction_id]);
    
    // Create dispute record
    try {
        $disputeStmt = $pdo->prepare("
            INSERT INTO disputes (transaction_id, reported_by, reason, status, created_at)
            VALUES (?, ?, ?, 'open', NOW())
        ");
        $disputeStmt->execute([$transaction_id, $user_id, $reason]);
    } catch (Exception $e) {
        // Disputes table might not exist
        error_log("Dispute insert failed: " . $e->getMessage());
    }
    
    // Create notification for seller
    if (function_exists('createNotification')) {
        createNotification(
            $transaction['seller_id'],
            'credential_issue',
            'Credential Issue Reported',
            "Buyer has reported an issue with the credentials. Please review.",
            $transaction_id,
            'transaction'
        );
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Issue reported successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Report issue error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
