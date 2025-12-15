<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/flash_helper.php';
require_once __DIR__ . '/../../includes/validation_helper.php';
require_login();

$user = current_user();
$pdo = db();

$transaction_id = $_GET['transaction_id'] ?? $_GET['id'] ?? null;
if (!$transaction_id) {
  setErrorMessage('Invalid transaction.');
  header("Location: " . url('index.php?p=dashboard&page=my_sales'));
  exit;
}

// Fetch transaction details (seller only)
$stmt = $pdo->prepare("
    SELECT t.*, l.name AS listing_name, l.type AS category,
           b.name AS buyer_name, b.email AS buyer_email
    FROM transactions t
    JOIN listings l ON t.listing_id = l.id
    JOIN users b ON t.buyer_id = b.id
    WHERE t.id = ? AND t.seller_id = ?
");
$stmt->execute([$transaction_id, $user['id']]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
  setErrorMessage('Transaction not found or you do not have permission.');
  header("Location: " . url('index.php?p=dashboard&page=my_sales'));
  exit;
}

// Check if payment already released
if ($transaction['transfer_status'] === 'released') {
  setErrorMessage('Payment has already been released. Credentials cannot be modified.');
  header("Location: " . url('index.php?p=dashboard&page=my_sales'));
  exit;
}

// Check if credentials already submitted
if (!empty($transaction['credentials'])) {
  setErrorMessage('Credentials have already been submitted for this transaction.');
  header("Location: " . url('index.php?p=dashboard&page=my_sales'));
  exit;
}


// Check if transaction payment is received (transfer_status should be 'paid')
if ($transaction['transfer_status'] !== 'paid') {
  setErrorMessage('Cannot submit credentials. Payment must be received first. Current transfer status: ' . $transaction['transfer_status']);
  header("Location: " . url('index.php?p=dashboard&page=my_sales'));

  exit;
}

// Check if credentials already submitted or payment already released
if ($transaction['transfer_status'] === 'released') {
  setErrorMessage('Payment has already been released. Credentials cannot be modified.');
  header("Location: " . url('index.php?p=dashboard&page=my_sales'));
  exit;
}

if ($transaction['transfer_status'] === 'credentials_submitted') {
  setErrorMessage('Credentials have already been submitted for this transaction.');
  header("Location: " . url('index.php?p=dashboard&page=my_sales'));
  exit;
}

// Handle form submission
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // CSRF Check
  if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setErrorMessage('Invalid request. Please try again.');
    header("Location: " . url('index.php?p=dashboard&page=submit_credentials&transaction_id=' . $transaction_id));
    exit;
  }

  // Validation
  $validator = new FormValidator($_POST);
  $validator
    ->required('credentials_type', 'Credentials type is required')
    ->required('access_url', 'Access URL is required')
    ->required('username', 'Username/Email is required')
    ->required('password', 'Password is required');

  if ($validator->fails()) {
    $validator->storeErrors();
    header("Location: " . url('index.php?p=dashboard&page=submit_credentials&transaction_id=' . $transaction_id));
    exit;
  }

  try {

    // Prepare credentials JSON data
    $credentialsData = [
      'type' => $_POST['credentials_type'],
      'access_url' => $_POST['access_url'],
      'username' => $_POST['username'],
      'password' => $_POST['password'],
      'additional_info' => $_POST['additional_info'] ?? ''
    ];
    $credentialsJson = json_encode($credentialsData);

    // Insert into listing_credentials table
    $insertStmt = $pdo->prepare("
            INSERT INTO listing_credentials 
            (transaction_id, credentials_data, created_at, updated_at)
            VALUES (?, ?, NOW(), NOW())
        ");
    $insertStmt->execute([$transaction_id, $credentialsJson]);

    // Update transaction transfer_status
    $updateStmt = $pdo->prepare("
            UPDATE transactions 
            SET transfer_status = 'credentials_submitted'
            WHERE id = ?
        ");
    $updateStmt->execute([$transaction_id]);

    // Log action
    log_action(
      "Credentials Submitted",
      "Seller submitted credentials for Transaction ID: {$transaction_id}, Listing: {$transaction['listing_name']}",
      "transaction",
      $user['id']
    );

    // Notify buyer
    require_once __DIR__ . '/../../includes/notification_helper.php';
    createNotification(
      $transaction['buyer_id'],
      'transaction',
      'Credentials Received',
      "Seller has submitted access credentials for '{$transaction['listing_name']}'. Please verify and confirm receipt.",
      $transaction_id,
      'transaction'
    );

    // Send email notification asynchronously
    register_shutdown_function(function () use ($transaction, $credentialsData) {
      if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
      }
      require_once __DIR__ . '/../../includes/email_helper.php';
      sendCredentialsSubmittedEmail(
        $transaction['buyer_email'],
        $transaction['buyer_name'],
        $transaction['listing_name'],
        $credentialsData
      );
    });

    setSuccessMessage('Credentials submitted successfully! Buyer has been notified.');
    header("Location: " . url('index.php?p=dashboard&page=my_sales'));
    exit;
  } catch (Exception $e) {
    setErrorMessage('Failed to submit credentials: ' . $e->getMessage());
    error_log("Submit credentials error: " . $e->getMessage());
    header("Location: " . url('index.php?p=dashboard&page=submit_credentials&transaction_id=' . $transaction_id));
    exit;
  }
}


$validationErrors = FormValidator::getStoredErrors();
// $oldInput = FormValidator::getOldInput();
?>

<section class="text-gray-800 body-font py-12 px-4 sm:px-6 lg:px-8">
  <div class="max-w-3xl mx-auto">

    <!-- Header -->
    <div class="mb-10">
      <a href="<?= url('index.php?p=dashboard&page=my_sales') ?>"
        class="text-blue-600 hover:text-blue-700 flex items-center gap-2 mb-4">
        <i class="fas fa-arrow-left"></i>
        Back to My Sales
      </a>
      <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-gray-900 flex items-center gap-2">
        <i class="fas fa-key text-purple-600"></i>
        Submit Access Credentials
      </h1>
      <p class="text-gray-600 mt-2">Provide buyer with access to the digital asset</p>
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
          <span class="text-gray-600">Buyer:</span>
          <span class="font-semibold text-gray-900"><?= htmlspecialchars($transaction['buyer_name']) ?></span>
        </div>
        <div class="flex justify-between py-2">
          <span class="text-gray-600">Amount:</span>
          <span class="font-semibold text-green-600">$<?= number_format($transaction['amount'], 2) ?></span>
        </div>
      </div>
    </div>

    <!-- Important Notice -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 mb-6">
      <div class="flex items-start gap-3">
        <i class="fas fa-info-circle text-blue-600 text-xl mt-1"></i>
        <div>
          <h3 class="font-bold text-blue-900 mb-2">Important Instructions</h3>
          <ul class="text-sm text-blue-800 space-y-1">
            <li>• Provide accurate login credentials for the digital asset</li>
            <li>• Ensure all access details are correct before submitting</li>
            <li>• Buyer will verify access before confirming receipt</li>
            <li>• Funds will be released after buyer confirmation</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Credentials Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 md:p-8">
      <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
        <i class="fas fa-lock text-purple-600"></i>
        Access Credentials
      </h2>

      <form method="POST" class="space-y-6">
        <?php csrfTokenField(); ?>

        <!-- Credentials Type -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fas fa-tag mr-1"></i>Credentials Type
          </label>
          <select name="credentials_type"
            class="<?= inputErrorClass('credentials_type', $validationErrors, 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent') ?>"
            required>
            <option value="">Select type...</option>
            <option value="website" <?= oldValue('credentials_type') === 'website' ? 'selected' : '' ?>>Website Login</option>
            <option value="youtube" <?= oldValue('credentials_type') === 'youtube' ? 'selected' : '' ?>>YouTube Channel</option>
            <option value="social_media" <?= oldValue('credentials_type') === 'social_media' ? 'selected' : '' ?>>Social Media Account</option>
            <option value="domain" <?= oldValue('credentials_type') === 'domain' ? 'selected' : '' ?>>Domain Transfer</option>
            <option value="saas" <?= oldValue('credentials_type') === 'saas' ? 'selected' : '' ?>>SaaS Application</option>
            <option value="other" <?= oldValue('credentials_type') === 'other' ? 'selected' : '' ?>>Other</option>
          </select>
          <?php displayFieldError('credentials_type', $validationErrors); ?>
        </div>

        <!-- Access URL -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fas fa-link mr-1"></i>Access URL / Login Page
          </label>
          <input type="url"
            name="access_url"
            value="<?= htmlspecialchars(oldValue('access_url')) ?>"
            placeholder="https://example.com/login"
            class="<?= inputErrorClass('access_url', $validationErrors, 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent') ?>"
            required>
          <?php displayFieldError('access_url', $validationErrors); ?>
        </div>

        <!-- Username/Email -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fas fa-user mr-1"></i>Username / Email
          </label>
          <input type="text"
            name="username"
            value="<?= htmlspecialchars(oldValue('username')) ?>"
            placeholder="Enter username or email"
            class="<?= inputErrorClass('username', $validationErrors, 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent') ?>"
            required>
          <?php displayFieldError('username', $validationErrors); ?>
        </div>

        <!-- Password -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fas fa-key mr-1"></i>Password
          </label>
          <div class="relative">
            <input type="password"
              name="password"
              id="password"
              value="<?= htmlspecialchars(oldValue('password')) ?>"
              placeholder="Enter password"
              class="<?= inputErrorClass('password', $validationErrors, 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent pr-12') ?>"
              required>
            <button type="button"
              onclick="togglePassword('password')"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
              <i class="fas fa-eye"></i>
            </button>
          </div>
          <?php displayFieldError('password', $validationErrors); ?>
        </div>

        <!-- Additional Information -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fas fa-info-circle mr-1"></i>Additional Information (Optional)
          </label>
          <textarea name="additional_info"
            rows="4"
            placeholder="Any additional instructions, 2FA codes, recovery emails, etc."
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"><?= htmlspecialchars(oldValue('additional_info')) ?></textarea>
          <p class="text-xs text-gray-500 mt-1">
            Include any extra details the buyer needs to access the asset
          </p>
        </div>

        <!-- Confirmation -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox"
              name="confirm_accuracy"
              class="mt-1 w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500"
              required>
            <span class="text-sm text-gray-700">
              I confirm that all credentials provided are accurate and will grant the buyer full access to the digital asset as described in the listing.
            </span>
          </label>
        </div>

        <!-- Submit Button -->
        <div class="flex flex-col sm:flex-row gap-4">
          <button type="submit"
            class="flex-1 bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-3 px-6 rounded-lg font-semibold hover:opacity-90 transition-all flex items-center justify-center">
            <i class="fas fa-paper-plane mr-2"></i>
            Submit Credentials to Buyer
          </button>
          <a href="<?= url('index.php?p=dashboard&page=my_sales') ?>"
            class="flex-1 px-6 py-3 border-2 border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center justify-center font-semibold text-gray-700">
            <i class="fas fa-times mr-2"></i>Cancel
          </a>
        </div>
      </form>
    </div>

    <!-- Security Notice -->
    <div class="mt-6 bg-green-50 border border-green-200 rounded-lg p-6">
      <h3 class="font-semibold text-green-900 mb-2 flex items-center gap-2">
        <i class="fas fa-shield-alt"></i>
        Security & Privacy
      </h3>
      <ul class="text-sm text-green-800 space-y-1">
        <li>• Credentials are encrypted and securely stored</li>
        <li>• Only the buyer can view these credentials</li>
        <li>• Funds remain in escrow until buyer confirms receipt</li>
        <li>• You'll be notified when buyer confirms</li>
      </ul>
    </div>

  </div>
</section>

<script>
  function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = field.nextElementSibling.querySelector('i');

    if (field.type === 'password') {
      field.type = 'text';
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
    } else {
      field.type = 'password';
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
    }
  }
</script>