<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/pagination_helper.php';

require_login();
$user = current_user();
$user_id = $user['id'];
$pdo = db();

// Get pagination parameters
$page = getCurrentPage('pg');
$perPage = 10;

// Fetch buyer's transactions where credentials were received
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
    AND t.transfer_status IN ('paid', 'credentials_submitted', 'verified', 'disputed')
    ORDER BY t.created_at DESC
";

$countSql = "
    SELECT COUNT(*) as total
    FROM transactions t
    WHERE t.buyer_id = :buyer_id
    AND t.transfer_status IN ('paid', 'credentials_submitted', 'verified', 'disputed')
";

$params = [':buyer_id' => $user_id];
$result = getCustomPaginationData($pdo, $sql, $countSql, $params, $page, $perPage);
$transactions = $result['data'];
$pagination = $result['pagination'];
?>

<section class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl lg:text-4xl font-bold text-gray-900 tracking-tight mb-2">
                <i class="fas fa-inbox text-blue-600 mr-3"></i>
                Received Credentials
            </h1>
            <p class="text-lg text-gray-600">
                Access credentials from your purchases
            </p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <?php
            $stats = [
                'waiting' => 0,
                'received' => 0,
                'verified' => 0,
                'disputed' => 0
            ];
            
            foreach ($transactions as $txn) {
                if (empty($txn['credentials_id'])) {
                    $stats['waiting']++;
                } elseif ($txn['transfer_status'] === 'credentials_submitted') {
                    $stats['received']++;
                } elseif ($txn['transfer_status'] === 'verified') {
                    $stats['verified']++;
                } elseif ($txn['transfer_status'] === 'disputed') {
                    $stats['disputed']++;
                }
            }
            ?>
            
            <div class="bg-white rounded-2xl shadow-lg p-6 border border-yellow-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Waiting</p>
                        <p class="text-3xl font-bold text-yellow-600"><?= $stats['waiting'] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-hourglass-half text-yellow-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Received</p>
                        <p class="text-3xl font-bold text-blue-600"><?= $stats['received'] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-inbox text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Verified</p>
                        <p class="text-3xl font-bold text-green-600"><?= $stats['verified'] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-6 border border-red-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Disputed</p>
                        <p class="text-3xl font-bold text-red-600"><?= $stats['disputed'] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions List -->
        <?php if (empty($transactions)): ?>
            <div class="bg-white/80 backdrop-blur-xl border border-gray-100 rounded-2xl shadow-lg p-16 text-center">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-inbox text-3xl text-gray-400"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No credentials yet</h3>
                <p class="text-gray-600">Credentials from your purchases will appear here.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($transactions as $txn): ?>
                    <?php
                    $hasCredentials = !empty($txn['credentials_id']);
                    $transferStatus = $txn['transfer_status'] ?? 'paid';
                    
                    // Calculate verification deadline
                    $verificationDeadline = null;
                    $daysRemaining = 0;
                    if ($hasCredentials && $txn['credentials_submitted_at']) {
                        $verificationDeadline = date('Y-m-d H:i:s', strtotime($txn['credentials_submitted_at'] . ' +7 days'));
                        $timeRemaining = strtotime($verificationDeadline) - time();
                        $daysRemaining = max(0, ceil($timeRemaining / 86400));
                    }
                    
                    $statusColors = [
                        'paid' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                        'credentials_submitted' => 'bg-blue-100 text-blue-800 border-blue-200',
                        'verified' => 'bg-green-100 text-green-800 border-green-200',
                        'disputed' => 'bg-red-100 text-red-800 border-red-200'
                    ];
                    
                    $statusIcons = [
                        'paid' => 'hourglass-half',
                        'credentials_submitted' => 'inbox',
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
                                        <?= htmlspecialchars($txn['listing_name']) ?>
                                    </h3>
                                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                                        <span>
                                            <i class="fas fa-hashtag mr-1"></i>
                                            Transaction #<?= $txn['id'] ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-user mr-1"></i>
                                            Seller: <?= htmlspecialchars($txn['seller_name']) ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-calendar mr-1"></i>
                                            <?= date('M j, Y', strtotime($txn['created_at'])) ?>
                                        </span>
                                        <span class="font-semibold text-green-600">
                                            <i class="fas fa-dollar-sign mr-1"></i>
                                            $<?= number_format($txn['amount'], 2) ?>
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
                                                        <?= $daysRemaining ?> day<?= $daysRemaining > 1 ? 's' : '' ?> remaining to verify
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Action Buttons -->
                                    <div class="flex gap-3">
                                        <a href="index.php?p=dashboard&page=view_credentials&transaction_id=<?= $txn['id'] ?>" 
                                           class="flex-1 bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-xl font-semibold shadow-lg hover:shadow-2xl transition-all duration-300 text-center">
                                            <i class="fas fa-key mr-2"></i>
                                            View Credentials
                                        </a>
                                        
                                        <?php if ($transferStatus === 'credentials_submitted'): ?>
                                            <button onclick="confirmCredentials(<?= $txn['id'] ?>)" 
                                                    class="px-6 py-3 bg-green-600 text-white rounded-xl font-semibold hover:bg-green-700 transition-colors">
                                                <i class="fas fa-check mr-2"></i>
                                                Confirm
                                            </button>
                                            <button onclick="reportIssue(<?= $txn['id'] ?>)" 
                                                    class="px-6 py-3 bg-red-600 text-white rounded-xl font-semibold hover:bg-red-700 transition-colors">
                                                <i class="fas fa-flag mr-2"></i>
                                                Report
                                            </button>
                                        <?php endif; ?>
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
// Confirm credentials
function confirmCredentials(transactionId) {
    if (!confirm('Are you sure you want to confirm receipt? This will release payment to the seller.')) {
        return;
    }
    
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
            alert('✅ Credentials confirmed! Payment released to seller.');
            location.reload();
        } else {
            alert('❌ Error: ' + (data.error || 'Failed'));
        }
    })
    .catch(error => {
        alert('❌ Network error');
    });
}

// Report issue
function reportIssue(transactionId) {
    const reason = prompt('Please describe the issue:');
    if (!reason || reason.trim() === '') return;
    
    fetch('<?= BASE ?>api/report_credential_issue.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            transaction_id: transactionId,
            reason: reason,
            csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Issue reported successfully.');
            location.reload();
        } else {
            alert('❌ Error: ' + (data.error || 'Failed'));
        }
    })
    .catch(error => {
        alert('❌ Network error');
    });
}
</script>
