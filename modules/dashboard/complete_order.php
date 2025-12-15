<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../api/escrow_api.php';
require_once __DIR__ . '/../../includes/flash_helper.php';
require_once __DIR__ . '/../../includes/validation_helper.php';
require_once __DIR__ . '/../../includes/transaction_logger.php';
require_login();

$user = current_user();
$pdo = db();

$transaction_id = $_GET['id'] ?? null;
if (!$transaction_id) {
  setErrorMessage('Invalid transaction.');
  header("Location: " . url('public/index.php?p=dashboard&page=my_order'));
  exit;
}

// Fetch transaction details
$stmt = $pdo->prepare("
    SELECT t.*, l.name AS listing_name, l.type AS category,
           s.name AS seller_name, s.email AS seller_email,
           b.name AS buyer_name, b.email AS buyer_email
    FROM transactions t
    JOIN listings l ON t.listing_id = l.id
    JOIN users s ON t.seller_id = s.id
    JOIN users b ON t.buyer_id = b.id
    WHERE t.id = ? AND t.buyer_id = ?
");
$stmt->execute([$transaction_id, $user['id']]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
  setErrorMessage('Transaction not found or you do not have permission to access it.');
  header("Location: " . url('public/index.php?p=dashboard&page=my_order'));
  exit;
}

// Check if transaction is in correct status
if ($transaction['transfer_status'] !== 'credentials_submitted') {
  setErrorMessage('This transaction cannot be completed at this time. Status: ' . $transaction['transfer_status']);
  header("Location: " . url('public/index.php?p=dashboard&page=my_order'));
  exit;
}

// Handle OTP submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setErrorMessage('Invalid request. Please try again.');
    header("Location: " . url('public/index.php?p=dashboard&page=complete_order&id=' . $transaction_id));
    exit;
  }

  $validator = new FormValidator($_POST);
  $validator->required('otp', 'OTP is required');

  if ($validator->fails()) {
    $validator->storeErrors();
    header("Location: " . url('public/index.php?p=dashboard&page=complete_order&id=' . $transaction_id));
    exit;
  }

  $otp = trim($_POST['otp']);
  $escrowId = $transaction['pandascrow_escrow_id'];

  // Log OTP attempt
  log_otp_attempt($transaction_id, $escrowId, strlen($otp));

  try {
    // Call PandaScrow API to complete escrow
    $result = complete_pandascrow_escrow($escrowId, $otp);

    if ($result['success']) {
      // Log OTP success
      log_otp_success($transaction_id, $escrowId, $transaction['amount']);
      // Update transaction status
      $updateStmt = $pdo->prepare("
                UPDATE transactions 
                SET status = 'completed', completed_at = NOW() 
                WHERE id = ?
            ");
      $updateStmt->execute([$transaction_id]);

      // Log the action
      log_action(
        "Order Completed",
        "Buyer confirmed receipt for Order ID: {$transaction_id}, Listing: {$transaction['listing_name']}, Amount: $" . number_format($transaction['amount'], 2),
        "order",
        $user['id']
      );

      // Create notifications
      require_once __DIR__ . '/../../includes/notification_helper.php';

      // Notify seller
      createNotification(
        $transaction['seller_id'],
        'order',
        'Order Completed',
        "Buyer confirmed receipt for '{$transaction['listing_name']}'. Funds have been released.",
        $transaction_id,
        'order'
      );

      // Notify admins
      $adminStmt = $pdo->query("SELECT id FROM users WHERE role IN ('admin', 'superadmin')");
      while ($admin = $adminStmt->fetch(PDO::FETCH_ASSOC)) {
        createNotification(
          $admin['id'],
          'order',
          'Order Completed',
          "Order #{$transaction_id} completed. Funds released to seller.",
          $transaction_id,
          'order'
        );
      }

      setSuccessMessage('Order completed successfully! Funds have been released to the seller.');
      header("Location: " . url('public/index.php?p=dashboard&page=my_order'));
      exit;
    } else {
      $errorMsg = $result['error'] ?? 'Failed to complete escrow';

      // Log OTP failure
      log_otp_failure($transaction_id, $escrowId, $errorMsg, $result);

      setErrorMessage('Failed to complete order: ' . $errorMsg);

      log_action(
        "Order Completion Failed",
        "Failed to complete Order ID: {$transaction_id}, Error: {$errorMsg}",
        "order",
        $user['id']
      );
    }
  } catch (Exception $e) {
    // Log exception
    log_transaction_event('otp_exception', [
      'transaction_id' => $transaction_id,
      'escrow_id' => $escrowId,
      'error' => $e->getMessage(),
      'trace' => $e->getTraceAsString()
    ], 'error');

    setErrorMessage('An error occurred: ' . $e->getMessage());
    error_log("Complete order error: " . $e->getMessage());
  }

  header("Location: " . url('public/index.php?p=dashboard&page=complete_order&id=' . $transaction_id));
  exit;
}

$validationErrors = FormValidator::getStoredErrors();
?>

<section class="text-gray-800 body-font py-12 px-4 sm:px-6 lg:px-8">
  <div class="max-w-3xl mx-auto">

    <!-- Header -->
    <div class="mb-10">
      <a href="<?= url('index.php?p=dashboard&page=my_order') ?>"
        class="text-blue-600 hover:text-blue-700 flex items-center gap-2 mb-4">
        <i class="fas fa-arrow-left"></i>
        Back to My Orders
      </a>
      <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-gray-900 flex items-center gap-2">
        <i class="fas fa-check-circle text-green-600"></i>
        Complete Order
      </h1>
      <p class="text-gray-600 mt-2">Confirm that you have received the digital asset</p>
    </div>

    <?php displayFlashMessages(); ?>

    <!-- Transaction Details -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
        <i class="fas fa-file-invoice text-blue-600"></i>
        Transaction Details
      </h2>

      <div class="space-y-3 text-sm">
        <div class="flex justify-between py-2 border-b border-gray-100">
          <span class="text-gray-600">Transaction ID:</span>
          <span class="font-semibold text-gray-900">#<?= $transaction['id'] ?></span>
        </div>
        <div class="flex justify-between py-2 border-b border-gray-100">
          <span class="text-gray-600">Listing:</span>
          <span class="font-semibold text-gray-900"><?= htmlspecialchars($transaction['listing_name']) ?></span>
        </div>
        <div class="flex justify-between py-2 border-b border-gray-100">
          <span class="text-gray-600">Seller:</span>
          <span class="font-semibold text-gray-900"><?= htmlspecialchars($transaction['seller_name']) ?></span>
        </div>
        <div class="flex justify-between py-2 border-b border-gray-100">
          <span class="text-gray-600">Amount Paid:</span>
          <span class="font-semibold text-green-600">$<?= number_format($transaction['amount'], 2) ?></span>
        </div>
        <div class="flex justify-between py-2 border-b border-gray-100">
          <span class="text-gray-600">Platform Fee:</span>
          <span class="font-semibold text-gray-900">$<?= number_format($transaction['platform_fee'], 2) ?></span>
        </div>
        <div class="flex justify-between py-2 border-b border-gray-100">
          <span class="text-gray-600">Seller Receives:</span>
          <span class="font-semibold text-green-600">$<?= number_format($transaction['seller_amount'], 2) ?></span>
        </div>
        <div class="flex justify-between py-2">
          <span class="text-gray-600">Payment Date:</span>
          <span class="font-semibold text-gray-900"><?= date('M d, Y', strtotime($transaction['created_at'])) ?></span>
        </div>
      </div>
    </div>

    <!-- Important Notice -->
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 mb-6">
      <div class="flex items-start gap-3">
        <i class="fas fa-exclamation-triangle text-yellow-600 text-xl mt-1"></i>
        <div>
          <h3 class="font-bold text-yellow-900 mb-2">Important Notice</h3>
          <ul class="text-sm text-yellow-800 space-y-1">
            <li>• Only complete this order if you have received the digital asset</li>
            <li>• Once completed, funds will be released to the seller</li>
            <li>• This action cannot be undone</li>
            <li>• You will receive an OTP from PandaScrow to confirm completion</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- OTP Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 md:p-8">
      <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
        <i class="fas fa-key text-purple-600"></i>
        Enter OTP to Complete
      </h2>

      <p class="text-gray-600 mb-6">
        An OTP (One-Time Password) has been sent to your registered email/phone.
        Enter it below to confirm receipt and release funds to the seller.
      </p>

      <form method="POST" class="space-y-6">
        <?php csrfTokenField(); ?>

        <!-- OTP Input -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fas fa-lock mr-1"></i>Enter OTP
          </label>
          <input type="text"
            name="otp"
            id="otp"
            maxlength="8"
            placeholder="Enter 6-8 digit OTP"
            class="<?= inputErrorClass('otp', $validationErrors, 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent text-center text-2xl tracking-widest font-mono') ?>"
            autocomplete="off"
            required>
          <?php displayFieldError('otp', $validationErrors); ?>
          <p class="text-xs text-gray-500 mt-2">
            <i class="fas fa-info-circle mr-1"></i>
            Didn't receive OTP? Check your email/SMS or contact support.
          </p>
        </div>

        <!-- Confirmation Checkbox -->
        <div class="bg-gray-50 rounded-lg p-4">
          <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox"
              name="confirm_receipt"
              id="confirm_receipt"
              class="mt-1 w-5 h-5 text-green-600 border-gray-300 rounded focus:ring-green-500"
              required>
            <span class="text-sm text-gray-700">
              I confirm that I have received the digital asset as described and am satisfied with the transfer.
              I understand that completing this order will release the funds to the seller.
            </span>
          </label>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-4">
          <button type="submit"
            id="submitBtn"
            class="flex-1 bg-gradient-to-r from-green-600 to-emerald-600 text-white py-3 px-6 rounded-lg font-semibold hover:opacity-90 transition-all flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
            disabled>
            <i class="fas fa-check-circle mr-2"></i>
            Complete Order & Release Funds
          </button>
          <a href="<?= url('index.php?p=dashboard&page=my_order') ?>"
            class="flex-1 px-6 py-3 border-2 border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center justify-center font-semibold text-gray-700">
            <i class="fas fa-times mr-2"></i>Cancel
          </a>
        </div>
      </form>

      <!-- Contact Seller -->
      <div class="mt-6 pt-6 border-t border-gray-200">
        <p class="text-sm text-gray-600 mb-3">
          <i class="fas fa-question-circle mr-1"></i>
          Have questions or issues with the transfer?
        </p>
        <a href="<?= url('index.php?p=dashboard&page=message&seller_id=' . $transaction['seller_id'] . '&listing_id=' . $transaction['listing_id']) ?>"
          class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium">
          <i class="fas fa-comments"></i>
          Contact Seller
        </a>
      </div>
    </div>

    <!-- Help Section -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
      <h3 class="font-semibold text-blue-900 mb-2 flex items-center gap-2">
        <i class="fas fa-life-ring"></i>
        Need Help?
      </h3>
      <p class="text-sm text-blue-800 mb-3">
        If you're experiencing issues or haven't received the asset, please contact our support team before completing the order.
      </p>
      <a href="<?= url('index.php?p=dashboard&page=tickets') ?>"
        class="inline-flex items-center gap-2 text-blue-700 hover:text-blue-800 font-medium text-sm">
        <i class="fas fa-ticket-alt"></i>
        Open Support Ticket
      </a>
    </div>

  </div>
</section>

<script>
  // Enable submit button only when checkbox is checked
  document.getElementById('confirm_receipt').addEventListener('change', function() {
    document.getElementById('submitBtn').disabled = !this.checked;
  });

  // Auto-format OTP input (numbers only)
  document.getElementById('otp').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '');
  });

  // Confirm before submission
  document.querySelector('form').addEventListener('submit', function(e) {
    if (!confirm('Are you sure you want to complete this order? This action cannot be undone and will release funds to the seller.')) {
      e.preventDefault();
    }
  });
</script>