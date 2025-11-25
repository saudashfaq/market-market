<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/flash_helper.php';
require_once __DIR__ . '/../../includes/validation_helper.php';
require_login();

$user = current_user();
$pdo = db();

// Fetch existing payout settings
$stmt = $pdo->prepare("
    SELECT payout_method, payout_bank_name, payout_account_number, 
           payout_account_name, payout_bank_code, payout_currency,
           payout_routing_number, payout_swift_code
    FROM users WHERE id = ?
");
$stmt->execute([$user['id']]);
$payoutSettings = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF Check
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setErrorMessage('Invalid request. Please try again.');
        header("Location: " . url('index.php?p=dashboard&page=payment_settings'));
        exit;
    }

    $payoutMethod = $_POST['payout_method'] ?? 'wallet';
    
    // Validation
    $validator = new FormValidator($_POST);
    $validator->required('payout_method', 'Payout method is required');
    
    // If bank account selected, validate bank details
    if ($payoutMethod === 'bank_account') {
        $validator
            ->required('bank_name', 'Bank name is required')
            ->required('account_number', 'Account number is required')
            ->required('account_name', 'Account holder name is required')
            ->required('bank_code', 'Bank code is required')
            ->required('currency', 'Currency is required');
    }
    
    if ($validator->fails()) {
        $validator->storeErrors();
        header("Location: " . url('index.php?p=dashboard&page=payment_settings'));
        exit;
    }

    try {
        if ($payoutMethod === 'wallet') {
            // Clear bank details if switching to wallet
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET payout_method = 'wallet',
                    payout_bank_name = NULL,
                    payout_account_number = NULL,
                    payout_account_name = NULL,
                    payout_bank_code = NULL,
                    payout_currency = NULL,
                    payout_routing_number = NULL,
                    payout_swift_code = NULL
                WHERE id = ?
            ");
            $updateStmt->execute([$user['id']]);
            
        } else {
            // Save bank account details
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET payout_method = 'bank_account',
                    payout_bank_name = ?,
                    payout_account_number = ?,
                    payout_account_name = ?,
                    payout_bank_code = ?,
                    payout_currency = ?,
                    payout_routing_number = ?,
                    payout_swift_code = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                $_POST['bank_name'],
                $_POST['account_number'],
                $_POST['account_name'],
                $_POST['bank_code'],
                $_POST['currency'],
                $_POST['routing_number'] ?? null,
                $_POST['swift_code'] ?? null,
                $user['id']
            ]);
        }

        // Log action
        log_action(
            "Payment Settings Updated",
            "User updated payout method to: {$payoutMethod}",
            "user",
            $user['id']
        );

        setSuccessMessage('Payment settings updated successfully!');
        header("Location: " . url('index.php?p=dashboard&page=payment_settings'));
        exit;

    } catch (Exception $e) {
        setErrorMessage('Failed to update payment settings: ' . $e->getMessage());
        error_log("Payment settings error: " . $e->getMessage());
        header("Location: " . url('index.php?p=dashboard&page=payment_settings'));
        exit;
    }
}

$validationErrors = FormValidator::getStoredErrors();
?>

<section class="text-gray-800 body-font py-12 px-4 sm:px-6 lg:px-8">
  <div class="max-w-4xl mx-auto">

    <!-- Header -->
    <div class="mb-10">
      <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-gray-900 flex items-center gap-2">
        <i class="fas fa-university text-green-600"></i>
        Payment Settings
      </h1>
      <p class="text-gray-600 mt-2">Configure how you want to receive payments from sales</p>
    </div>

    <?php displayFlashMessages(); ?>

    <!-- Current Status -->
    <?php if ($payoutSettings && $payoutSettings['payout_method']): ?>
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 mb-6">
      <div class="flex items-start gap-3">
        <i class="fas fa-info-circle text-blue-600 text-xl mt-1"></i>
        <div>
          <h3 class="font-bold text-blue-900 mb-2">Current Payout Method</h3>
          <p class="text-sm text-blue-800">
            <?php if ($payoutSettings['payout_method'] === 'wallet'): ?>
              <i class="fas fa-wallet mr-2"></i>PandaScrow Wallet (Manual Withdrawal)
            <?php else: ?>
              <i class="fas fa-university mr-2"></i>Direct Bank Transfer
              <br><span class="ml-6">Bank: <?= htmlspecialchars($payoutSettings['payout_bank_name']) ?></span>
              <br><span class="ml-6">Account: ****<?= substr($payoutSettings['payout_account_number'], -4) ?></span>
            <?php endif; ?>
          </p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Payment Settings Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 md:p-8">
      
      <form method="POST" class="space-y-8">
        <?php csrfTokenField(); ?>

        <!-- Payout Method Selection -->
        <div>
          <h2 class="text-xl font-bold text-gray-900 mb-4">Choose Payout Method</h2>
          
          <div class="space-y-4">
            <!-- Wallet Option -->
            <label class="flex items-start gap-4 p-5 border-2 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors <?= (!$payoutSettings || $payoutSettings['payout_method'] === 'wallet') ? 'border-green-500 bg-green-50' : 'border-gray-200' ?>">
              <input type="radio" 
                     name="payout_method" 
                     value="wallet" 
                     class="mt-1 w-5 h-5 text-green-600"
                     <?= (!$payoutSettings || $payoutSettings['payout_method'] === 'wallet') ? 'checked' : '' ?>
                     onchange="toggleBankFields(false)">
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-2">
                  <i class="fas fa-wallet text-green-600 text-xl"></i>
                  <span class="font-bold text-gray-900">PandaScrow Wallet</span>
                  <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full">Default</span>
                </div>
                <p class="text-sm text-gray-600">
                  Funds will be held in your PandaScrow wallet. You can withdraw manually anytime.
                </p>
                <ul class="text-xs text-gray-500 mt-2 space-y-1">
                  <li>✓ No setup required</li>
                  <li>✓ Withdraw anytime</li>
                  <li>✓ Multiple withdrawal options</li>
                </ul>
              </div>
            </label>

            <!-- Bank Account Option -->
            <label class="flex items-start gap-4 p-5 border-2 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors <?= ($payoutSettings && $payoutSettings['payout_method'] === 'bank_account') ? 'border-green-500 bg-green-50' : 'border-gray-200' ?>">
              <input type="radio" 
                     name="payout_method" 
                     value="bank_account" 
                     class="mt-1 w-5 h-5 text-green-600"
                     <?= ($payoutSettings && $payoutSettings['payout_method'] === 'bank_account') ? 'checked' : '' ?>
                     onchange="toggleBankFields(true)">
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-2">
                  <i class="fas fa-university text-green-600 text-xl"></i>
                  <span class="font-bold text-gray-900">Direct Bank Transfer</span>
                  <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">Recommended</span>
                </div>
                <p class="text-sm text-gray-600">
                  Automatic transfer to your bank account after buyer confirms delivery.
                </p>
                <ul class="text-xs text-gray-500 mt-2 space-y-1">
                  <li>✓ Automatic payouts</li>
                  <li>✓ Faster access to funds</li>
                  <li>✓ No manual withdrawal needed</li>
                </ul>
              </div>
            </label>
          </div>
        </div>

        <!-- Bank Account Details (Hidden by default) -->
        <div id="bankFields" class="space-y-6 <?= (!$payoutSettings || $payoutSettings['payout_method'] === 'wallet') ? 'hidden' : '' ?>">
          
          <div class="border-t pt-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
              <i class="fas fa-building text-blue-600"></i>
              Bank Account Information
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              
              <!-- Bank Name -->
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  <i class="fas fa-university mr-1"></i>Bank Name *
                </label>
                <input type="text" 
                       name="bank_name" 
                       value="<?= htmlspecialchars($payoutSettings['payout_bank_name'] ?? '') ?>"
                       placeholder="e.g., Chase Bank, Bank of America"
                       class="<?= inputErrorClass('bank_name', $validationErrors, 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent') ?>">
                <?php displayFieldError('bank_name', $validationErrors); ?>
              </div>

              <!-- Account Holder Name -->
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  <i class="fas fa-user mr-1"></i>Account Holder Name *
                </label>
                <input type="text" 
                       name="account_name" 
                       value="<?= htmlspecialchars($payoutSettings['payout_account_name'] ?? $user['name']) ?>"
                       placeholder="Full name as per bank records"
                       class="<?= inputErrorClass('account_name', $validationErrors, 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent') ?>">
                <?php displayFieldError('account_name', $validationErrors); ?>
              </div>

              <!-- Account Number -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  <i class="fas fa-hashtag mr-1"></i>Account Number *
                </label>
                <input type="text" 
                       name="account_number" 
                       value="<?= htmlspecialchars($payoutSettings['payout_account_number'] ?? '') ?>"
                       placeholder="Enter account number"
                       class="<?= inputErrorClass('account_number', $validationErrors, 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent') ?>">
                <?php displayFieldError('account_number', $validationErrors); ?>
              </div>

              <!-- Bank Code / Routing Number -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  <i class="fas fa-code mr-1"></i>Bank Code / Sort Code *
                </label>
                <input type="text" 
                       name="bank_code" 
                       value="<?= htmlspecialchars($payoutSettings['payout_bank_code'] ?? '') ?>"
                       placeholder="e.g., 011000015"
                       class="<?= inputErrorClass('bank_code', $validationErrors, 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent') ?>">
                <?php displayFieldError('bank_code', $validationErrors); ?>
                <p class="text-xs text-gray-500 mt-1">Also known as routing number or sort code</p>
              </div>

              <!-- Currency -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  <i class="fas fa-dollar-sign mr-1"></i>Currency *
                </label>
                <select name="currency" 
                        class="<?= inputErrorClass('currency', $validationErrors, 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent') ?>">
                  <option value="">Select currency...</option>
                  <option value="USD" <?= ($payoutSettings['payout_currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD - US Dollar</option>
                  <option value="EUR" <?= ($payoutSettings['payout_currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR - Euro</option>
                  <option value="GBP" <?= ($payoutSettings['payout_currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP - British Pound</option>
                  <option value="NGN" <?= ($payoutSettings['payout_currency'] ?? '') === 'NGN' ? 'selected' : '' ?>>NGN - Nigerian Naira</option>
                  <option value="CAD" <?= ($payoutSettings['payout_currency'] ?? '') === 'CAD' ? 'selected' : '' ?>>CAD - Canadian Dollar</option>
                </select>
                <?php displayFieldError('currency', $validationErrors); ?>
              </div>

              <!-- Routing Number (Optional) -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  <i class="fas fa-route mr-1"></i>Routing Number (Optional)
                </label>
                <input type="text" 
                       name="routing_number" 
                       value="<?= htmlspecialchars($payoutSettings['payout_routing_number'] ?? '') ?>"
                       placeholder="For US banks"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                <p class="text-xs text-gray-500 mt-1">Required for US bank accounts</p>
              </div>

              <!-- SWIFT Code (Optional) -->
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  <i class="fas fa-globe mr-1"></i>SWIFT/BIC Code (Optional)
                </label>
                <input type="text" 
                       name="swift_code" 
                       value="<?= htmlspecialchars($payoutSettings['payout_swift_code'] ?? '') ?>"
                       placeholder="For international transfers"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                <p class="text-xs text-gray-500 mt-1">Required for international bank transfers</p>
              </div>

            </div>
          </div>

          <!-- Security Notice -->
          <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-start gap-3">
              <i class="fas fa-shield-alt text-yellow-600 text-xl mt-1"></i>
              <div>
                <h4 class="font-semibold text-yellow-900 mb-1">Security Notice</h4>
                <p class="text-sm text-yellow-800">
                  Your bank details are encrypted and securely stored. They will only be used for automatic payouts after successful transactions.
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t">
          <button type="submit" 
                  class="flex-1 bg-gradient-to-r from-green-600 to-emerald-600 text-white py-3 px-6 rounded-lg font-semibold hover:opacity-90 transition-all flex items-center justify-center">
            <i class="fas fa-save mr-2"></i>
            Save Payment Settings
          </button>
          <a href="<?= url('index.php?p=dashboard') ?>" 
             class="flex-1 px-6 py-3 border-2 border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center justify-center font-semibold text-gray-700">
            <i class="fas fa-times mr-2"></i>Cancel
          </a>
        </div>
      </form>
    </div>

    <!-- Help Section -->
    <div class="mt-6 bg-gray-50 border border-gray-200 rounded-lg p-6">
      <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
        <i class="fas fa-question-circle text-blue-600"></i>
        Need Help?
      </h3>
      <div class="space-y-2 text-sm text-gray-700">
        <p><strong>Where do I find my bank code?</strong><br>
        Check your bank statement or contact your bank. It's also called routing number (US) or sort code (UK).</p>
        
        <p><strong>When will I receive payments?</strong><br>
        Funds are automatically transferred 1-3 business days after buyer confirms delivery.</p>
        
        <p><strong>Can I change my payout method later?</strong><br>
        Yes, you can update your payment settings anytime from this page.</p>
      </div>
    </div>

  </div>
</section>

<script>
function toggleBankFields(show) {
  const bankFields = document.getElementById('bankFields');
  if (show) {
    bankFields.classList.remove('hidden');
  } else {
    bankFields.classList.add('hidden');
  }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  const bankRadio = document.querySelector('input[name="payout_method"][value="bank_account"]');
  if (bankRadio && bankRadio.checked) {
    toggleBankFields(true);
  }
});
</script>
