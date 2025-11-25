<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/encryption_helper.php';

require_login();
$user = current_user();
$user_id = $user['id'];
$pdo = db();

$transaction_id = $_GET['transaction_id'] ?? null;
if (!$transaction_id) {
    $_SESSION['error'] = "Invalid transaction ID.";
    header("Location: index.php?p=dashboard&page=my_sales&tab=received");
    exit;
}

// Fetch transaction
$stmt = $pdo->prepare("
    SELECT t.*, l.name AS listing_name, l.category, seller.name AS seller_name,
           lc.credentials_data, lc.created_at as credentials_submitted_at
    FROM transactions t
    JOIN listings l ON t.listing_id = l.id
    JOIN users seller ON t.seller_id = seller.id
    LEFT JOIN listing_credentials lc ON t.id = lc.transaction_id
    WHERE t.id = ? AND t.buyer_id = ?
");
$stmt->execute([$transaction_id, $user_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction || !$transaction['credentials_data']) {
    $_SESSION['error'] = "Credentials not available.";
    header("Location: index.php?p=dashboard&page=my_sales&tab=received");
    exit;
}

// Decrypt credentials
$encryptionKey = $transaction['encryption_key'];
if (ctype_xdigit($encryptionKey) && strlen($encryptionKey) === 32) {
    $encryptionKey = base64_encode(hex2bin($encryptionKey));
} elseif (strlen($encryptionKey) === 64 && ctype_xdigit($encryptionKey)) {
    $encryptionKey = base64_encode(hex2bin($encryptionKey));
}

$credentials = decryptCredentials($transaction['credentials_data'], $encryptionKey);
if ($credentials === false) {
    $_SESSION['error'] = "Failed to decrypt credentials.";
    header("Location: index.php?p=dashboard&page=my_sales&tab=received");
    exit;
}

logCredentialAccess($transaction_id, $user_id, 'view', $pdo);
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 to-blue-50/30 py-8">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Back Button -->
        <div class="mb-6">
            <a href="index.php?p=dashboard&page=my_sales&tab=received" 
               class="inline-flex items-center text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to My Sales
            </a>
        </div>

        <!-- Header Card -->
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg border border-gray-200 p-8 mb-6">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($transaction['listing_name']) ?></h1>
                            <p class="text-sm text-gray-500 mt-1"><?= ucfirst($transaction['category']) ?> Credentials</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                        <div class="bg-gray-50 rounded-xl p-3">
                            <p class="text-xs text-gray-500 mb-1">Transaction</p>
                            <p class="text-sm font-semibold text-gray-900">#<?= $transaction['id'] ?></p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-3">
                            <p class="text-xs text-gray-500 mb-1">Seller</p>
                            <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($transaction['seller_name']) ?></p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-3">
                            <p class="text-xs text-gray-500 mb-1">Amount</p>
                            <p class="text-sm font-semibold text-green-600">$<?= number_format($transaction['amount'], 2) ?></p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-3">
                            <p class="text-xs text-gray-500 mb-1">Received</p>
                            <p class="text-sm font-semibold text-gray-900"><?= date('M j, Y', strtotime($transaction['credentials_submitted_at'])) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Credentials Card -->
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-white">Secure Access Credentials</h2>
                            <p class="text-blue-100 text-sm">Encrypted with AES-256</p>
                        </div>
                    </div>
                    <button onclick="copyAllCredentials()" 
                            class="px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg font-medium transition-colors backdrop-blur-sm border border-white/30">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        Copy All
                    </button>
                </div>
            </div>

            <div class="p-8">
                <div class="grid gap-6">
                    <?php foreach ($credentials as $key => $value): ?>
                        <?php if (in_array($key, ['category', 'submitted_at', 'submitted_by'])) continue; ?>
                        
                        <div class="group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <?= ucwords(str_replace('_', ' ', $key)) ?>
                            </label>
                            <div class="relative">
                                <?php if (strpos($key, 'password') !== false): ?>
                                    <input type="password" 
                                           id="field_<?= $key ?>"
                                           value="<?= htmlspecialchars($value) ?>" 
                                           readonly
                                           class="w-full px-4 py-3 pr-24 bg-gray-50 border-2 border-gray-200 rounded-xl text-sm font-mono focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                                    <div class="absolute right-2 top-1/2 -translate-y-1/2 flex gap-1">
                                        <button onclick="togglePassword('field_<?= $key ?>', this)" 
                                                class="p-2 hover:bg-gray-200 rounded-lg transition-colors">
                                                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </button>
                                        <button onclick="copyToClipboard('field_<?= $key ?>', this)" 
                                                class="p-2 hover:bg-gray-200 rounded-lg transition-colors">
                                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <input type="text" 
                                           id="field_<?= $key ?>"
                                           value="<?= htmlspecialchars($value) ?>" 
                                           readonly
                                           class="w-full px-4 py-3 pr-14 bg-gray-50 border-2 border-gray-200 rounded-xl text-sm font-mono focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                                    <button onclick="copyToClipboard('field_<?= $key ?>', this)" 
                                            class="absolute right-2 top-1/2 -translate-y-1/2 p-2 hover:bg-gray-200 rounded-lg transition-colors">
                                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Additional Notes -->
                <?php if (!empty($credentials['additional_notes'])): ?>
                <div class="mt-8 bg-amber-50 border-2 border-amber-200 rounded-xl p-6">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-amber-900 mb-2">Additional Notes from Seller</p>
                            <p class="text-sm text-amber-800 whitespace-pre-wrap"><?= htmlspecialchars($credentials['additional_notes']) ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Security Notice -->
                <div class="mt-6 bg-blue-50 border-2 border-blue-200 rounded-xl p-6">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-blue-900 mb-1">Security Recommendation</p>
                            <p class="text-sm text-blue-700">For enhanced security, we recommend changing all passwords immediately after receiving access.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <?php if ($transaction['transfer_status'] === 'credentials_submitted'): ?>
        <div class="mt-6 bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Confirm Receipt</h3>
                    <p class="text-sm text-gray-600">Verify the credentials work correctly before confirming</p>
                </div>
                <button onclick="confirmCredentials(<?= $transaction_id ?>)" 
                        class="px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white font-semibold rounded-xl hover:shadow-lg transition-all">
                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Confirm & Release Payment
                </button>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
const credentials = <?= json_encode($credentials) ?>;

function togglePassword(fieldId, button) {
    const field = document.getElementById(fieldId);
    field.type = field.type === 'password' ? 'text' : 'password';
}

function copyToClipboard(fieldId, button) {
    const field = document.getElementById(fieldId);
    field.select();
    document.execCommand('copy');
    
    const originalHTML = button.innerHTML;
    button.innerHTML = '<svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
    setTimeout(() => button.innerHTML = originalHTML, 2000);
}

function copyAllCredentials() {
    let text = '';
    for (const [key, value] of Object.entries(credentials)) {
        if (!['category', 'submitted_at', 'submitted_by'].includes(key)) {
            text += key.replace(/_/g, ' ').toUpperCase() + ': ' + value + '\n';
        }
    }
    navigator.clipboard.writeText(text).then(() => {
        alert('✅ All credentials copied to clipboard!');
    });
}

function confirmCredentials(transactionId) {
    if (!confirm('Confirm receipt of credentials? This will release payment to the seller.')) return;
    
    fetch('<?= BASE ?>api/confirm_credentials.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            transaction_id: transactionId,
            csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Confirmed! Payment released to seller.');
            window.location.href = 'index.php?p=dashboard&page=my_sales&tab=received';
        } else {
            alert('❌ Error: ' + (data.error || 'Failed'));
        }
    })
    .catch(() => alert('❌ Network error'));
}
</script>
