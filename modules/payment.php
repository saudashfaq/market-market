<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../api/escrow_api.php';
require_once __DIR__ . '/../includes/flash_helper.php';
require_once __DIR__ . '/../includes/validation_helper.php';
require_once __DIR__ . '/../includes/transaction_logger.php';

$user = $_SESSION['user'] ?? null;
if (!$user) {
  header("Location: login.php");
  exit;
}
$error=null;
$pdo = db();
$listing_id = $_GET['id'] ?? null;
if (!$listing_id) die("Invalid listing.");

// === Fetch Listing & Seller ===
$stmt = $pdo->prepare("SELECT l.*, u.email AS seller_email, u.id AS seller_id, u.name AS seller_name 
                       FROM listings l 
                       JOIN users u ON u.id = l.user_id 
                       WHERE l.id = ?");
$stmt->execute([$listing_id]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$listing) die("Listing not found.");

$buyer_id = $user['id'];
$seller_id = $listing['seller_id'];

// === Check if there's an accepted offer for this buyer ===
$offerStmt = $pdo->prepare("
    SELECT amount, id 
    FROM offers 
    WHERE listing_id = ? 
    AND user_id = ? 
    AND status = 'accepted' 
    ORDER BY updated_at DESC 
    LIMIT 1
");
$offerStmt->execute([$listing_id, $buyer_id]);
$acceptedOffer = $offerStmt->fetch(PDO::FETCH_ASSOC);

// === Price calculations ===
// Use accepted offer amount if exists, otherwise use asking price
if ($acceptedOffer) {
    $amount = (float) $acceptedOffer['amount'];
    $offer_id = $acceptedOffer['id'];
    $is_offer_payment = true;
} else {
    $amount = (float) $listing['asking_price'];
    $offer_id = null;
    $is_offer_payment = false;
}

$platformFee = round($amount * 0.05, 2);
$total = $amount + $platformFee;

// Get validation errors
$validationErrors = FormValidator::getStoredErrors();

// === Handle checkout form submit ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  try {
    // Log payment initiation
    log_payment_initiated($listing_id, $buyer_id, $seller_id, $amount, $is_offer_payment, $offer_id ?? null);
    
    // Step 3: Get seller payout configuration
    $sellerStmt = $pdo->prepare("
      SELECT payout_method, payout_bank_name, payout_account_number, 
             payout_account_name, payout_bank_code, payout_currency
      FROM users WHERE id = ?
    ");
    $sellerStmt->execute([$seller_id]);
    $sellerPayout = $sellerStmt->fetch(PDO::FETCH_ASSOC);
    
    // Build payout configuration for PandaScrow
    $payoutConfig = null;
    if ($sellerPayout && $sellerPayout['payout_method'] === 'bank_account') {
      $payoutConfig = [
        'payout_type' => 'bank',
        'bank_name' => $sellerPayout['payout_bank_name'],
        'account_number' => $sellerPayout['payout_account_number'],
        'account_name' => $sellerPayout['payout_account_name'],
        'bank_code' => $sellerPayout['payout_bank_code'],
        // Optional fields based on your needs:
        // 'routing_number' => $sellerPayout['payout_routing_number'] ?? null,
        // 'swift_code' => $sellerPayout['payout_swift_code'] ?? null,
      ];
      
      // Log payout configuration
      log_payout_config($seller_id, 'bank_account', $payoutConfig);
    } else {
      // Log wallet payout
      log_payout_config($seller_id, 'wallet', []);
    }
    // If payout_method is 'wallet' or not set, payoutConfig remains null
    // Funds will go to seller's PandaScrow wallet for manual withdrawal
    
    // Step 4: Prepare buyer and seller details
    $buyerDetails = [
      'name' => $user['name'],
      'email' => $user['email'],
      'phone' => $user['phone'] ?? '+10000000000',
    ];
    
    $sellerDetails = [
      'name' => $listing['seller_name'],
      'email' => $listing['seller_email'],
      'phone' => '+10000000001', // You should store seller phone in DB
    ];

    // Step 5: Create Escrow
    $title = "Purchase: " . ($listing['name'] ?? 'Unnamed Listing');
    $description = "Escrow for purchase of '" . ($listing['name'] ?? 'Unnamed Listing') . "'.";

    // Log escrow creation attempt
    log_escrow_creation($listing_id, $amount, $buyerDetails, $sellerDetails, $payoutConfig);

    $escrow_res = create_pandascrow_escrow(
      $total,  // Use total (amount + platform fee) instead of just amount
      $title,
      $description,
      $buyerDetails,
      $sellerDetails,
      $payoutConfig
    );

    if (!$escrow_res['success']) {
      // Log failure
      log_escrow_failure($escrow_res['error'] ?? 'Unknown', $escrow_res);
      throw new Exception("Escrow creation failed: " . ($escrow_res['error'] ?? 'Unknown'));
    }
    $escrow_data = $escrow_res['data'] ?? [];

    $escrow_id = $escrow_data['escrow_id'] ?? null;
    $payment_url = $escrow_data['payment_url'] ?? null;
    $transaction_ref = $escrow_data['transaction_ref'] ?? null;
    $provider = $escrow_data['provider'] ?? null;
    
    if (!$escrow_id || !$payment_url) {
      log_escrow_failure('Missing escrow_id or payment_url', $escrow_res);
      throw new Exception("Escrow created but payment link not found | Response: " . json_encode($escrow_res));
    }
    
    // Log success
    log_escrow_success($escrow_id, $transaction_ref, $payment_url, $provider);
    
        if ($escrow_res['success']){
          $sellerAmount = $amount - $platformFee;
    $insert = $pdo->prepare("
      INSERT INTO transactions 
        (listing_id, buyer_id, seller_id, pandascrow_escrow_id, escrow_transaction_id, amount, platform_fee, seller_amount, status, escrow_provider, created_at)
      VALUES 
        (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
    ");
    $insert->execute([
      $listing_id,
      $buyer_id,
      $seller_id,
      $escrow_id,
      $transaction_ref,
      $amount,
      $platformFee,
      $sellerAmount,
      $provider
    ]);

    $transaction_id = $pdo->lastInsertId();
    
    // Log database transaction
    log_db_transaction('INSERT', 'transactions', [
      'transaction_id' => $transaction_id,
      'listing_id' => $listing_id,
      'escrow_id' => $escrow_id,
      'amount' => $amount,
      'platform_fee' => $platformFee
    ], true);

    error_log("Transaction inserted successfully for escrow_id $escrow_id");
  
    // Step 6: Redirect
    log_transaction_event('payment_redirect', [
      'transaction_id' => $transaction_id,
      'escrow_id' => $escrow_id,
      'payment_url' => $payment_url
    ], 'info');
    
    header("Location: " . $payment_url);
    exit;
  }else{
    log_transaction_event('payment_timeout', ['escrow_response' => $escrow_res], 'error');
    echo 'time out';
    }
  } catch (Exception $e) {
    $error = $e->getMessage();
    log_transaction_event('payment_exception', [
      'error' => $error,
      'trace' => $e->getTraceAsString()
    ], 'error');
    error_log("Payment Error: " . $error);
  }
}
?>


<section class="bg-gray-50 min-h-screen flex items-center justify-center">
  <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-lg">
    <h2 class="text-2xl font-bold mb-4">Secure Checkout</h2>

    <div class="border-b pb-3 mb-3">
      <p class="text-sm text-gray-700">Listing: <strong><?= htmlspecialchars($listing['name']) ?></strong></p>
      <p class="text-sm text-gray-700">Seller: <?= htmlspecialchars($listing['seller_name']) ?> (<?= htmlspecialchars($listing['seller_email']) ?>)</p>
      <p class="text-sm text-gray-700">Category: <?= htmlspecialchars($listing['category'] ?? 'N/A') ?></p>
      <?php if ($is_offer_payment): ?>
        <div class="mt-2 bg-green-50 border border-green-200 rounded-lg p-2">
          <p class="text-xs text-green-700 flex items-center gap-2">
            <i class="fas fa-check-circle"></i>
            <span><strong>Accepted Offer:</strong> Paying negotiated price</span>
          </p>
        </div>
      <?php endif; ?>
    </div>

    <div class="space-y-2 text-gray-800">
      <?php if ($is_offer_payment): ?>
        <div class="flex justify-between text-sm text-gray-500">
          <span>Original Price</span>
          <span class="line-through">$<?= number_format($listing['asking_price'], 2) ?></span>
        </div>
        <div class="flex justify-between text-green-600 font-medium">
          <span>Negotiated Price</span>
          <span>$<?= number_format($amount, 2) ?></span>
        </div>
      <?php else: ?>
        <div class="flex justify-between"><span>Listing Price</span><span>$<?= number_format($amount, 2) ?></span></div>
      <?php endif; ?>
      <div class="flex justify-between"><span>Platform Fee (5%)</span><span>$<?= number_format($platformFee, 2) ?></span></div>
      <div class="flex justify-between font-semibold text-lg border-t pt-2 mt-2">
        <span>Total</span>
        <span>$<?= number_format($total, 2) ?></span>
      </div>
    </div>

    <div class="mt-6 space-y-3">
      <form method="POST">
        <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-3 rounded-md font-medium shadow-md hover:opacity-90">
          <i class="fa fa-lock mr-2"></i> Proceed to Secure Payment
        </button>
      </form>
      
      <a href="index.php?p=dashboard&page=message&seller_id=<?= $seller_id ?>&listing_id=<?= $listing_id ?>" 
         class="w-full bg-green-100 hover:bg-green-200 text-green-700 py-3 rounded-md font-medium flex items-center justify-center transition-colors">
        <i class="far fa-envelope mr-2"></i>
        Contact Seller
      </a>
    </div>

    <?php if ($error): ?>
      <p class="text-red-600 text-sm mt-4"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <p class="text-xs text-gray-500 mt-3 text-center">
      <i class="fa fa-shield-alt mr-1"></i> Your funds are securely held in escrow until the seller completes the transfer.
    </p>
  </div>
</section>