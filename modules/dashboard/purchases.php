<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/encryption_helper.php';
require_once __DIR__ . '/../../includes/pagination_helper.php';

require_login();
$user = current_user();
$user_id = $user['id'];
$pdo = db();

// Get pagination parameters
$page = getCurrentPage('pg');
$perPage = 10;

// Fetch buyer's transactions with credentials
$sql = "
    SELECT t.*, 
           l.name as listing_name,
           l.category,
           l.type as listing_type,
           seller.name as seller_name,
           seller.email as seller_email,
           lc.id as credentials_id,
           lc.created_at as credentials_submitted_at
    FROM transactions t
    JOIN listings l ON t.listing_id = l.id
    JOIN users seller ON t.seller_id = seller.id
    LEFT JOIN listing_credentials lc ON t.id = lc.transaction_id
    WHERE t.buyer_id = :buyer_id
    AND t.status = 'paid'
    ORDER BY t.created_at DESC
";

$countSql = "
    SELECT COUNT(*) as total
    FROM transactions t
    WHERE t.buyer_id = :buyer_id
    AND t.status = 'paid'
";

$params = [':buyer_id' => $user_id];
$result = getCustomPaginationData($pdo, $sql, $countSql, $params, $page, $perPage);
$purchases = $result['data'];
$pagination = $result['pagination'];
?>

<section class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl lg:text-4xl font-bold text-gray-900 tracking-tight mb-2">
                My Purchases
            </h1>
            <p class="text-lg text-gray-600">
                View and access your purchased digital assets
            </p>
        </div>

        <!-- Purchases List -->
        <?php if (empty($purchases)): ?>
            <div class="bg-white/80 backdrop-blur-xl border border-gray-100 rounded-2xl shadow-lg p-16 text-center">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-shopping-bag text-3xl text-gray-400"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No purchases yet</h3>
                <p class="text-gray-600 mb-6">Browse listings and make your first purchase.</p>
                <a href="index.php?p=home" 
                   class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 inline-flex items-center">
                    <i class="fas fa-search mr-2"></i> Browse Listings
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($purchases as $purchase): ?>
                    <?php
                    $hasCredentials = !empty($purchase['credentials_id']);
                    $transferStatus = $purchase['transfer_status'] ?? 'awaiting_credentials';
                    
                    // Calculate verification deadline (7 days from credential submission)
                    $verificationDeadline = null;
                    $daysRemaining = 0;
                    if ($hasCredentials && $purchase['credentials_submitted_at']) {
                        $verificationDeadline = date('Y-m-d H:i:s', strtotime($purchase['credentials_submitted_at'] . ' +7 days'));
                        $timeRemaining = strtotime($verificationDeadline) - time();
                        $daysRemaining = max(0, ceil($timeRemaining / 86400));
                    }
                    
                    $statusColors = [
                        'awaiting_credentials' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                        'credentials_submitted' => 'bg-blue-100 text-blue-800 border-blue-200',
                        'verified' => 'bg-green-100 text-green-800 border-green-200',
                        'disputed' => 'bg-red-100 text-red-800 border-red-200'
                    ];
                    
                    $statusIcons = [
                        'awaiting_credentials' => 'clock',
                        'credentials_submitted' => 'key',
                        'verified' => 'check-circle',
                        'disputed' => 'exclamation-triangle'
                    ];
                    ?>
                    
                    <div class="bg-white/80 backdrop-blur-xl border border-gray-100 rounded-2xl shadow-lg overflow-hidden">
                        <!-- Header -->
                        <div class="p-6 border-b border-gray-100">
                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                                <div class="flex-1">
                                    <h3 class="text-xl font-bold text-gray-900 mb-2">
                                        <?= htmlspecialchars($purchase['listing_name']) ?>
                                    </h3>
                                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                                        <span>
                                            <i class="fas fa-hashtag mr-1"></i>
                                            Transaction #<?= $purchase['id'] ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-user mr-1"></i>
                                            Seller: <?= htmlspecialchars($purchase['seller_name']) ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-calendar mr-1"></i>
                                            <?= date('M j, Y', strtotime($purchase['created_at'])) ?>
                                        </span>
                                        <span class="font-semibold text-green-600">
                                            <i class="fas fa-dollar-sign mr-1"></i>
                                            $<?= number_format($purchase['amount'], 2) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div>
                                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium border <?= $statusColors[$transferStatus] ?? 'bg-gray-100 text-gray-800 border-gray-200' ?>">
                                        <i class="fas fa-<?= $statusIcons[$transferStatus] ?? 'question' ?> mr-2"></i>
                                        <?= ucwords(str_replace('_', ' ', $transferStatus)) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="p-6">
                            <?php if (!$hasCredentials): ?>
                                <!-- Waiting for credentials -->
                                <div class="flex items-center gap-4 p-4 bg-yellow-50 border border-yellow-200 rounded-xl">
                                    <div class="w-12 h-12 bg-yellow-500 rounded-full flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-hourglass-half text-white text-xl"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-yellow-900">Waiting for Credentials</p>
                                        <p class="text-sm text-yellow-700">The seller has 48 hours to submit access credentials.</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Credentials available -->
                                <div class="space-y-4">
                                    <!-- Verification Period Notice -->
                                    <?php if ($transferStatus === 'credentials_submitted' && $daysRemaining > 0): ?>
                                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl">
                                            <div class="flex items-center gap-3 mb-3">
                                                <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-clock text-white"></i>
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-blue-900">Verification Period</p>
                                                    <p class="text-sm text-blue-700">
                                                        <?= $daysRemaining ?> day<?= $daysRemaining > 1 ? 's' : '' ?> remaining to verify credentials
                                                    </p>
                                                </div>
                                            </div>
                                            <p class="text-xs text-blue-600">
                                                Deadline: <?= date('M j, Y g:i A', strtotime($verificationDeadline)) ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- View Credentials Button -->
                                    <div class="flex gap-3">
                                        <button onclick="viewCredentials(<?= $purchase['id'] ?>)" 
                                                class="flex-1 bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-xl font-semibold shadow-lg hover:shadow-2xl transition-all duration-300">
                                            <i class="fas fa-key mr-2"></i>
                                            View Access Credentials
                                        </button>
                                        
                                        <?php if ($transferStatus === 'credentials_submitted'): ?>
                                            <button onclick="confirmCredentials(<?= $purchase['id'] ?>)" 
                                                    class="px-6 py-3 bg-green-600 text-white rounded-xl font-semibold hover:bg-green-700 transition-colors">
                                                <i class="fas fa-check mr-2"></i>
                                                Confirm Receipt
                                            </button>
                                            <button onclick="reportIssue(<?= $purchase['id'] ?>)" 
                                                    class="px-6 py-3 bg-red-600 text-white rounded-xl font-semibold hover:bg-red-700 transition-colors">
                                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                                Report Issue
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Credentials Display Area (Hidden by default) -->
                                    <div id="credentials-<?= $purchase['id'] ?>" class="hidden mt-4 p-6 bg-gray-50 border border-gray-200 rounded-xl">
                                        <div class="flex items-center justify-between mb-4">
                                            <h4 class="font-semibold text-gray-900">
                                                <i class="fas fa-shield-alt text-blue-600 mr-2"></i>
                                                Encrypted Credentials
                                            </h4>
                                            <button onclick="hideCredentials(<?= $purchase['id'] ?>)" 
                                                    class="text-gray-500 hover:text-gray-700">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                        <div id="credentials-content-<?= $purchase['id'] ?>" class="space-y-3">
                                            <!-- Credentials will be loaded here via AJAX -->
                                            <div class="text-center py-4">
                                                <i class="fas fa-spinner fa-spin text-blue-600 text-2xl"></i>
                                                <p class="text-sm text-gray-600 mt-2">Loading credentials...</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mt-6">
                    <div class="flex items-center gap-2">
                        <?php if ($pagination['current_page'] > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['pg' => $pagination['current_page'] - 1])) ?>" 
                               class="px-4 py-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                <i class="fas fa-chevron-left mr-1"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['pg' => $i])) ?>" 
                               class="px-4 py-2 <?= $i === $pagination['current_page'] ? 'bg-blue-600 text-white' : 'bg-white border border-gray-200 hover:bg-gray-50' ?> rounded-lg transition-colors">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['pg' => $pagination['current_page'] + 1])) ?>" 
                               class="px-4 py-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                Next <i class="fas fa-chevron-right ml-1"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-sm text-gray-500">
                        Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</section>

<script>
// View credentials
function viewCredentials(transactionId) {
    const container = document.getElementById('credentials-' + transactionId);
    const content = document.getElementById('credentials-content-' + transactionId);
    
    // Show container
    container.classList.remove('hidden');
    
    // Fetch credentials via AJAX
    fetch('<?= url('api/view_credentials.php') ?>?transaction_id=' + transactionId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayCredentials(transactionId, data.credentials);
            } else {
                content.innerHTML = `
                    <div class="text-center py-4 text-red-600">
                        <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                        <p>${data.error || 'Failed to load credentials'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `
                <div class="text-center py-4 text-red-600">
                    <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                    <p>Network error. Please try again.</p>
                </div>
            `;
        });
}

// Display credentials
function displayCredentials(transactionId, credentials) {
    const content = document.getElementById('credentials-content-' + transactionId);
    let html = '';
    
    for (const [key, value] of Object.entries(credentials)) {
        if (key === 'category' || key === 'submitted_at' || key === 'submitted_by') continue;
        
        const label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        const fieldId = 'field-' + transactionId + '-' + key;
        
        html += `
            <div class="bg-white p-4 rounded-lg border border-gray-200">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium text-gray-700">${label}</label>
                    <button onclick="copyToClipboard('${fieldId}')" 
                            class="text-blue-600 hover:text-blue-800 text-sm">
                        <i class="fas fa-copy mr-1"></i> Copy
                    </button>
                </div>
                <input type="text" id="${fieldId}" value="${value}" readonly
                       class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded text-sm font-mono">
            </div>
        `;
    }
    
    content.innerHTML = html;
}

// Hide credentials
function hideCredentials(transactionId) {
    const container = document.getElementById('credentials-' + transactionId);
    container.classList.add('hidden');
}

// Copy to clipboard
function copyToClipboard(fieldId) {
    const field = document.getElementById(fieldId);
    field.select();
    document.execCommand('copy');
    
    // Show feedback
    const btn = event.currentTarget;
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check mr-1"></i> Copied!';
    btn.classList.add('text-green-600');
    
    setTimeout(() => {
        btn.innerHTML = originalHTML;
        btn.classList.remove('text-green-600');
    }, 2000);
}

// Confirm credentials
function confirmCredentials(transactionId) {
    if (!confirm('Are you sure you want to confirm receipt of these credentials? This will release payment to the seller.')) {
        return;
    }
    
    fetch('<?= url('api/confirm_credentials.php') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            transaction_id: transactionId,
            csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Credentials confirmed! Payment has been released to the seller.');
            location.reload();
        } else {
            alert('❌ Error: ' + (data.error || 'Failed to confirm credentials'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Network error. Please try again.');
    });
}

// Report issue
function reportIssue(transactionId) {
    const reason = prompt('Please describe the issue with the credentials:');
    
    if (!reason || reason.trim() === '') {
        return;
    }
    
    fetch('<?= url('api/report_credential_issue.php') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            transaction_id: transactionId,
            reason: reason,
            csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Issue reported. The seller and admin have been notified.');
            location.reload();
        } else {
            alert('❌ Error: ' + (data.error || 'Failed to report issue'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Network error. Please try again.');
    });
}
</script>

<!-- Add Transaction Polling -->
<script src="<?= BASE ?>js/polling.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Purchases page - initializing transaction polling');
    
    if (typeof startPolling === 'undefined') {
        console.error('❌ startPolling function not found! polling.js not loaded properly.');
        return;
    }
    
    // Initialize polling for transactions
    startPolling({
        transactions: (newTransactions) => {
            console.log('✅ Transactions callback triggered!');
            console.log('Processing new transactions:', newTransactions);
            
            // Reload page when transaction status changes
            if (newTransactions.length > 0) {
                console.log('🔄 Transaction status updated - reloading page');
                location.reload();
            }
        }
    });
    
    console.log('✅ Transaction polling started for Purchases page');
});
</script>
