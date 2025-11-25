<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/flash_helper.php';
require_once __DIR__ . '/../../includes/validation_helper.php';
require_login();

$user = current_user();
$pdo = db();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setErrorMessage('Invalid request. Please try again.');
        header("Location: " . url('index.php?p=dashboard&page=payout_settings'));
        exit;
    }

    $validator = new FormValidator($_POST);
    
    $payoutMethod = $_POST['payout_method'] ?? 'wallet';
    
    if ($payoutMethod === 'bank_account') {
        $validator
            ->required('payout_bank_name', 'Bank name is required')
            ->required('payout_account_number', 'Account number is required')
            ->required('payout_account_name', 'Account name is required')
            ->required('payout_bank_code', 'Bank code is required');
    }
    
    if ($validator->fails()) {
        $validator->storeErrors();
        header("Location: " . url('index.php?p=dashboard&page=payout_settings'));
        exit;
    }
    
    try {
        if ($payoutMethod === 'wallet') {
            // Clear bank details if switching to wallet
            $stmt = $pdo->prepare("
                UPDATE users 
                SET payout_method = 'wallet',
                    payout_bank_name = NULL,
                    payout_account_number = NULL,
                    payout_account_name = NULL,
                    payout_bank_code = NULL
                WHERE id = ?
            ");
            $stmt->execute([$user['id']]);
        } else {
            // Save bank account details
            $stmt = $pdo->prepare("
                UPDATE users 
                SET payout_method = 'bank_account',
                    payout_bank_name = ?,
                    payout_account_number = ?,
                    payout_account_name = ?,
                    payout_bank_code = ?,
                    payout_currency = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['payout_bank_name'],
                $_POST['payout_account_number'],
                $_POST['payout_account_name'],
                $_POST['payout_bank_code'],
                $_POST['payout_currency'] ?? 'USD',
                $user['id']
            ]);
        }
        
        log_action("Payout Settings Updated", "User updated payout method to: {$payoutMethod}", "settings", $user['id']);
        setSuccessMessage("Payout settings updated successfully!");
        
    } catch (Exception $e) {
        setErrorMessage("Failed to update payout settings. Please try again.");
        error_log("Payout settings error: " . $e->getMessage());
    }
    
    header("Location: " . url('index.php?p=dashboard&page=payout_settings'));
    exit;
}

// Fetch current settings
$stmt = $pdo->prepare("
    SELECT payout_method, payout_bank_name, payout_account_number, 
           payout_account_name, payout_bank_code, payout_currency
    FROM users WHERE id = ?
");
$stmt->execute([$user['id']]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

$validationErrors = FormValidator::getStoredErrors();
$oldInput = FormValidator::getOldInput();
?>

<section class="text-gray-800 body-font py-12 px-4 sm:px-6 lg:px-8">
  <div class="max-w-4xl mx-auto">
    
    <!-- Header -->
    <div class="mb-10">
      <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-gray-900 flex items-center gap-2">
        <i class="fas fa-wallet text-green-600"></i>
        Payout Settings
      </h1>
      <p class="text-gray-600 mt-2">Configure how you want to receive payments from completed sales</p>
    </div>

    <?php displayFlashMessages(); ?>

    <!-- Payout Settings Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 md:p-8">
      
      <form method="POST" id="payoutForm">
        <?php csrfTokenField(); ?>
        
        <!-- Payout Method Selection -->
        <div class="mb-8">
          <label class="block text-sm font-semibold text-gray-700 mb-4">
            <i class="fas fa-money-check-alt mr-2"></i>Payout Method
          </label>
          
          <div class="grid md:grid-cols-2 gap-4">
            <!-- Wallet Option -->
            <label class="relative flex items-start p-4 border-2 rounded-lg cursor-pointer transition-all hover:border-blue-500 <?= ($settings['payout_method'] ?? 'wallet') === 'wallet' ? 'border-blue-500 bg-blue-50' : 'border-gray-200' ?>">
              <input type="radio" name="payout_method" value="wallet" 
                     class="mt-1 mr-3" 
                     <?= ($settings['payout_method'] ?? 'wallet') === 'wallet' ? 'checked' : '' ?>
                     onchange="toggleBankFields()">
              <div>
                <div class="font-semibold text-gray-900 flex items-center gap-2">
                  <i class="fas fa-wallet text-blue-600"></i>
                  PandaScrow Wallet
                </div>
                <p class="text-sm text-gray-600 mt-1">
                  Funds will be held in your PandaScrow wallet. You can manually withdraw later.
                </p>
              </div>
            </label>
            
            <!-- Bank Account Option -->
            <label class="relative flex items-start p-4 border-2 rounded-lg cursor-pointer transition-all hover:border-green-500 <?= ($settings['payout_method'] ?? 'wallet') === 'bank_account' ? 'border-green-500 bg-green-50' : 'border-gray-200' ?>">
              <input type="radio" name="payout_method" value="bank_account" 
                     class="mt-1 mr-3" 
                     <?= ($settings['payout_method'] ?? 'wallet') === 'bank_account' ? 'checked' : '' ?>
                     onchange="toggleBankFields()">
              <div>
                <div class="font-semibold text-gray-900 flex items-center gap-2">
                  <i class="fas fa-university text-green-600"></i>
                  Bank Account
                </div>
                <p class="text-sm text-gray-600 mt-1">
                  Funds will be automatically transferred to your bank account.
                </p>
              </div>
            </label>
          </div>
        </div>

        <!-- Bank Account Details (shown only when bank_account is selected) -->
        <div id="bankAccountFields" class="space-y-6 <?= ($settings['payout_method'] ?? 'wallet') === 'bank_account' ? '' : 'hidden' ?>">
          
          <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <div class="flex items-start gap-2">
              <i class="fas fa-info-circle text-yellow-600 mt-1"></i>
              <div class="text-sm text-yellow-800">
                <strong>Important:</strong> Ensure your bank details are correct. Incorrect details may result in failed transfers.
              </div>
            </div>
          </div>

          <!-- Bank Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-university mr-1"></i>Bank Name
            </label>
            <input type="text" 
                   name="payout_bank_name" 
                   value="<?= htmlspecialchars($settings['payout_bank_name'] ?? oldValue('payout_bank_name')) ?>"
                   placeholder="e.g., Chase Bank, Bank of America"
                   class="<?= inputErrorClass('payout_bank_name', $validationErrors, 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent') ?>">
            <?php displayFieldError('payout_bank_name', $validationErrors); ?>
          </div>

          <!-- Account Number -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-hashtag mr-1"></i>Account Number
            </label>
            <input type="text" 
                   name="payout_account_number" 
                   value="<?= htmlspecialchars($settings['payout_account_number'] ?? oldValue('payout_account_number')) ?>"
                   placeholder="Enter your account number"
                   class="<?= inputErrorClass('payout_account_number', $validationErrors, 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent') ?>">
            <?php displayFieldError('payout_account_number', $validationErrors); ?>
          </div>

          <!-- Account Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-user mr-1"></i>Account Name
            </label>
            <input type="text" 
                   name="payout_account_name" 
                   value="<?= htmlspecialchars($settings['payout_account_name'] ?? oldValue('payout_account_name')) ?>"
                   placeholder="Name as it appears on your account"
                   class="<?= inputErrorClass('payout_account_name', $validationErrors, 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent') ?>">
            <?php displayFieldError('payout_account_name', $validationErrors); ?>
          </div>

          <!-- Bank Code / Routing Number -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-code mr-1"></i>Bank Code / Routing Number
            </label>
            <input type="text" 
                   name="payout_bank_code" 
                   value="<?= htmlspecialchars($settings['payout_bank_code'] ?? oldValue('payout_bank_code')) ?>"
                   placeholder="e.g., 021000021"
                   class="<?= inputErrorClass('payout_bank_code', $validationErrors, 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent') ?>">
            <?php displayFieldError('payout_bank_code', $validationErrors); ?>
          </div>

          <!-- Currency -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-dollar-sign mr-1"></i>Currency
            </label>
            <select name="payout_currency" 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
              <option value="USD" <?= ($settings['payout_currency'] ?? 'USD') === 'USD' ? 'selected' : '' ?>>USD - US Dollar</option>
              <option value="EUR" <?= ($settings['payout_currency'] ?? 'USD') === 'EUR' ? 'selected' : '' ?>>EUR - Euro</option>
              <option value="GBP" <?= ($settings['payout_currency'] ?? 'USD') === 'GBP' ? 'selected' : '' ?>>GBP - British Pound</option>
              <option value="NGN" <?= ($settings['payout_currency'] ?? 'USD') === 'NGN' ? 'selected' : '' ?>>NGN - Nigerian Naira</option>
            </select>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="mt-8 flex gap-4">
          <button type="submit" 
                  class="flex-1 bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-6 rounded-lg font-semibold hover:opacity-90 transition-all flex items-center justify-center">
            <i class="fas fa-save mr-2"></i>
            Save Payout Settings
          </button>
          <a href="<?= url('index.php?p=dashboard&page=userDashboard') ?>" 
             class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center">
            <i class="fas fa-times mr-2"></i>Cancel
          </a>
        </div>
      </form>

    </div>

    <!-- Info Box -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
      <h3 class="font-semibold text-blue-900 mb-2 flex items-center gap-2">
        <i class="fas fa-shield-alt"></i>
        Security & Privacy
      </h3>
      <ul class="text-sm text-blue-800 space-y-1">
        <li>• Your bank details are encrypted and stored securely</li>
        <li>• Payouts are processed within 1-3 business days after escrow completion</li>
        <li>• Platform fees (5%) are automatically deducted before payout</li>
        <li>• You can change your payout method anytime</li>
      </ul>
    </div>

  </div>
</section>

<script>
function toggleBankFields() {
  const bankFields = document.getElementById('bankAccountFields');
  const bankAccountRadio = document.querySelector('input[name="payout_method"][value="bank_account"]');
  
  if (bankAccountRadio.checked) {
    bankFields.classList.remove('hidden');
  } else {
    bankFields.classList.add('hidden');
  }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', toggleBankFields);
</script>
