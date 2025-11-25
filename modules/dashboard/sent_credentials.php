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

// Fetch seller's transactions where credentials were submitted
$sql = "
    SELECT t.*, 
           l.name as listing_name,
           l.category,
           l.type as listing_type,
           buyer.name as buyer_name,
           buyer.email as buyer_email,
           lc.id as credentials_id,
           lc.created_at as credentials_submitted_at
    FROM transactions t
    JOIN listings l ON t.listing_id = l.id
    JOIN users buyer ON t.buyer_id = buyer.id
    LEFT JOIN listing_credentials lc ON t.id = lc.transaction_id
    WHERE t.seller_id = :seller_id
    AND t.transfer_status IN ('paid', 'credentials_submitted', 'verified', 'disputed')
    ORDER BY t.created_at DESC
";

$countSql = "
    SELECT COUNT(*) as total
    FROM transactions t
    WHERE t.seller_id = :seller_id
    AND t.transfer_status IN ('paid', 'credentials_submitted', 'verified', 'disputed')
";

$params = [':seller_id' => $user_id];
$result = getCustomPaginationData($pdo, $sql, $countSql, $params, $page, $perPage);
$transactions = $result['data'];
$pagination = $result['pagination'];
?>

<section class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl lg:text-4xl font-bold text-gray-900 tracking-tight mb-2">
                <i class="fas fa-paper-plane text-blue-600 mr-3"></i>
                Sent Credentials
            </h1>
            <p class="text-lg text-gray-600">
                Track credentials you've submitted to buyers
            </p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <?php
            $stats = [
                'pending' => 0,
                'submitted' => 0,
                'verified' => 0,
                'disputed' => 0
            ];
            
            foreach ($transactions as $txn) {
                if (empty($txn['credentials_id'])) {
                    $stats['pending']++;
                } elseif ($txn['transfer_status'] === 'credentials_submitted') {
                    $stats['submitted']++;
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
                        <p class="text-sm text-gray-600 mb-1">Pending</p>
                        <p class="text-3xl font-bold text-yellow-600"><?= $stats['pending'] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Submitted</p>
                        <p class="text-3xl font-bold text-blue-600"><?= $stats['submitted'] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-paper-plane text-blue-600 text-xl"></i>
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
                    <i class="fas fa-paper-plane text-3xl text-gray-400"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No transactions yet</h3>
                <p class="text-gray-600">Credentials you submit will appear here.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($transactions as $txn): ?>
                    <?php
                    $hasCredentials = !empty($txn['credentials_id']);
                    $transferStatus = $txn['transfer_status'] ?? 'paid';
                    
                    $statusColors = [
                        'paid' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                        'credentials_submitted' => 'bg-blue-100 text-blue-800 border-blue-200',
                        'verified' => 'bg-green-100 text-green-800 border-green-200',
                        'disputed' => 'bg-red-100 text-red-800 border-red-200'
                    ];
                    
                    $statusIcons = [
                        'paid' => 'clock',
                        'credentials_submitted' => 'paper-plane',
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
                                            Buyer: <?= htmlspecialchars($txn['buyer_name']) ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-calendar mr-1"></i>
                                            <?= date('M j, Y', strtotime($txn['created_at'])) ?>
                                        </span>
                                        <span class="font-semibold text-green-600">
                                            <i class="fas fa-dollar-sign mr-1"></i>
                                            $<?= number_format($txn['seller_amount'], 2) ?>
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
                                <!-- Need to submit credentials -->
                                <div class="flex items-center justify-between p-4 bg-yellow-50 border border-yellow-200 rounded-xl">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 bg-yellow-500 rounded-full flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-exclamation text-white text-xl"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-yellow-900">Action Required</p>
                                            <p class="text-sm text-yellow-700">You need to submit access credentials within 48 hours.</p>
                                        </div>
                                    </div>
                                    <a href="index.php?p=dashboard&page=submit_credentials&transaction_id=<?= $txn['id'] ?>" 
                                       class="px-6 py-3 bg-yellow-600 text-white rounded-xl font-semibold hover:bg-yellow-700 transition-colors whitespace-nowrap">
                                        <i class="fas fa-paper-plane mr-2"></i>
                                        Submit Now
                                    </a>
                                </div>
                            <?php else: ?>
                                <!-- Credentials submitted -->
                                <div class="space-y-4">
                                    <div class="p-4 bg-green-50 border border-green-200 rounded-xl">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
                                                <i class="fas fa-check text-white"></i>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-green-900">Credentials Submitted</p>
                                                <p class="text-sm text-green-700">
                                                    Submitted on <?= date('M j, Y g:i A', strtotime($txn['credentials_submitted_at'])) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($transferStatus === 'verified'): ?>
                                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl">
                                            <p class="text-blue-800">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                <strong>Payment Released:</strong> Buyer has confirmed receipt. Payment will be transferred to your account.
                                            </p>
                                        </div>
                                    <?php elseif ($transferStatus === 'disputed'): ?>
                                        <div class="p-4 bg-red-50 border border-red-200 rounded-xl">
                                            <p class="text-red-800">
                                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                                <strong>Issue Reported:</strong> Buyer has reported an issue. Please check your messages.
                                            </p>
                                        </div>
                                    <?php else: ?>
                                        <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl">
                                            <p class="text-gray-700">
                                                <i class="fas fa-hourglass-half mr-2"></i>
                                                Waiting for buyer to verify credentials (7 days verification period)
                                            </p>
                                        </div>
                                    <?php endif; ?>
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
