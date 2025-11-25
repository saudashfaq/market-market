<?php
/**
 * API: Confirm Credentials Receipt
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../api/escrow_api.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

header('Content-Type: application/json');

// Check session
if (!isset($_SESSION['user'])) {
    error_log("Confirm Credentials API - No user session");
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Please login']);
    exit;
}

$user = $_SESSION['user'];
$user_id = $user['id'];

// Get and validate input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

// Debug logging
error_log("Confirm Credentials API - Raw Input: " . $rawInput);
error_log("Confirm Credentials API - User ID: $user_id, Parsed Input: " . json_encode($input));

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Confirm Credentials API - JSON decode error: " . json_last_error_msg());
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit;
}

$transaction_id = $input['transaction_id'] ?? null;

if (!$transaction_id) {
    error_log("Confirm Credentials API - Missing transaction ID");
    echo json_encode(['success' => false, 'error' => 'Transaction ID required']);
    exit;
}

try {
    $pdo = db();
    if (!$pdo) {
        error_log("Confirm Credentials API - Database connection failed");
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
    
    // Verify buyer ownership
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND buyer_id = ?");
    $stmt->execute([$transaction_id, $user_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        exit;
    }
    
    // Get listing and seller details
    $detailsStmt = $pdo->prepare("
        SELECT l.id as listing_id, l.name as listing_name, 
               seller.name as seller_name, seller.email as seller_email,
               buyer.name as buyer_name, buyer.email as buyer_email
        FROM transactions t
        JOIN listings l ON t.listing_id = l.id
        JOIN users seller ON t.seller_id = seller.id
        JOIN users buyer ON t.buyer_id = buyer.id
        WHERE t.id = ?
    ");
    $detailsStmt->execute([$transaction_id]);
    $details = $detailsStmt->fetch(PDO::FETCH_ASSOC);
    
    // STEP 1: Call Pandascrow API to complete escrow
    error_log("Calling Pandascrow escrow completion for escrow_id: {$transaction['pandascrow_escrow_id']}");
    
    $escrowResult = complete_pandascrow_escrow(
        $transaction['pandascrow_escrow_id'],
        PANDASCROW_UUID, // Using platform UUID as confirming user
        null // No OTP for now
    );
    
    if (!$escrowResult['success']) {
        error_log("Pandascrow escrow completion failed: " . $escrowResult['error']);
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to complete escrow: ' . $escrowResult['error']
        ]);
        exit;
    }
    
    error_log("Pandascrow escrow completion successful");
    
    // STEP 2: Update local database after successful escrow completion
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Update transaction status to completed (escrow is now complete)
        $updateStmt = $pdo->prepare("
            UPDATE transactions 
            SET transfer_status = 'verified',
                status = 'completed',
                updated_at = NOW()
            WHERE id = ?
        ");
        $result1 = $updateStmt->execute([$transaction_id]);
        error_log("Transaction update result: " . ($result1 ? 'success' : 'failed'));
        
        // Mark listing as SOLD
        $updateListingStmt = $pdo->prepare("
            UPDATE listings 
            SET status = 'sold',
                updated_at = NOW()
            WHERE id = ?
        ");
        $result2 = $updateListingStmt->execute([$details['listing_id']]);
        error_log("Listing #{$details['listing_id']} update to 'sold': " . ($result2 ? 'success' : 'failed'));
        
        // Check if listing was actually updated
        $checkStmt = $pdo->prepare("SELECT status FROM listings WHERE id = ?");
        $checkStmt->execute([$details['listing_id']]);
        $listingStatus = $checkStmt->fetch(PDO::FETCH_ASSOC);
        error_log("Listing status after update: " . ($listingStatus['status'] ?? 'not found'));
        
        $pdo->commit();
        
        // Log successful completion
        error_log("Escrow completion successful - Transaction: $transaction_id, Escrow: {$transaction['pandascrow_escrow_id']}");
        
        // Send email to seller
        $sellerSubject = "Payment Released - Credentials Confirmed";
        $sellerBody = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Great News! Payment Released</h2>
            <p>Hi {$details['seller_name']},</p>
            <p>The buyer has confirmed receipt of the credentials for:</p>
            <p><strong>{$details['listing_name']}</strong></p>
            <p>Your payment has been released and will be transferred to your account.</p>
            <p><strong>Transaction Details:</strong></p>
            <ul>
                <li>Transaction ID: #{$transaction_id}</li>
                <li>Buyer: {$details['buyer_name']}</li>
                <li>Amount: $" . number_format($transaction['seller_amount'] ?? $transaction['amount'], 2) . "</li>
            </ul>
            <p style='margin-top: 30px;'>
                <a href='" . BASE . "index.php?p=dashboard&page=my_sales' 
                   style='background-color: #10b981; color: white; padding: 12px 30px; 
                          text-decoration: none; border-radius: 8px; display: inline-block;'>
                    View My Sales
                </a>
            </p>
            <hr style='margin: 30px 0;'>
            <p style='color: #666; font-size: 12px;'>
                Thank you for using our marketplace!
            </p>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: noreply@marketplace.com\r\n";
        @mail($details['seller_email'], $sellerSubject, $sellerBody, $headers);
        
        // Send email to buyer
        $buyerSubject = "Credentials Confirmed - Thank You";
        $buyerBody = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Thank You for Your Confirmation!</h2>
            <p>Hi {$details['buyer_name']},</p>
            <p>You have successfully confirmed receipt of credentials for:</p>
            <p><strong>{$details['listing_name']}</strong></p>
            <p>Payment has been released to the seller.</p>
            <p>If you have any issues in the future, please contact support.</p>
            <hr style='margin: 30px 0;'>
            <p style='color: #666; font-size: 12px;'>
                Thank you for your purchase!
            </p>
        </body>
        </html>
        ";
        @mail($details['buyer_email'], $buyerSubject, $buyerBody, $headers);
        
        // Create notification for seller
        if (function_exists('createNotification')) {
            createNotification(
                $transaction['seller_id'],
                'payment_released',
                'Payment Released',
                "Buyer confirmed receipt. Payment for '{$details['listing_name']}' has been released.",
                $transaction_id,
                'transaction'
            );
        }
        
        // Create notification for buyer
        if (function_exists('createNotification')) {
            createNotification(
                $transaction['buyer_id'],
                'credentials_confirmed',
                'Confirmation Successful',
                "You confirmed receipt for '{$details['listing_name']}'. Payment released to seller.",
                $transaction_id,
                'transaction'
            );
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Credentials confirmed successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Database transaction failed after escrow completion: " . $e->getMessage());
        
        // Note: Escrow is already completed on Pandascrow side
        // This is a critical state that needs manual intervention
        error_log("CRITICAL: Escrow completed but database update failed for transaction $transaction_id");
        
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Confirm credentials error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
