<?php
// Check for export FIRST - before any output
if (isset($_GET['export'])) {
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../includes/export_helper.php';
    
    ob_start();
    require_login();
    $user = current_user();
    $user_id = $user['id'];
    ob_end_clean();
    
    $pdo = db();
    
    $activeTab = $_GET['tab'] ?? 'sent';
    
    if ($activeTab === 'sent') {
        // Export sent credentials
        $exportSql = "
            SELECT t.id, t.amount, t.transfer_status, t.created_at,
                   l.name as listing_name, buyer.name as buyer_name, buyer.email as buyer_email
            FROM transactions t
            LEFT JOIN listings l ON t.listing_id = l.id
            LEFT JOIN users buyer ON t.buyer_id = buyer.id
            WHERE t.seller_id = :user_id AND t.status = 'paid'
            ORDER BY t.created_at DESC
        ";
    } else {
        // Export received credentials
        $exportSql = "
            SELECT t.id, t.amount, t.transfer_status, t.created_at,
                   l.name as listing_name, seller.name as seller_name, seller.email as seller_email
            FROM transactions t
            LEFT JOIN listings l ON t.listing_id = l.id
            LEFT JOIN users seller ON t.seller_id = seller.id
            WHERE t.buyer_id = :user_id AND t.status = 'paid'
            ORDER BY t.created_at DESC
        ";
    }
    
    $exportStmt = $pdo->prepare($exportSql);
    $exportStmt->execute([':user_id' => $user_id]);
    $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $title = $activeTab === 'sent' ? 'Sent Credentials Report' : 'Received Credentials Report';
    handleExportRequest($exportData, $title);
    exit;
}

require_once __DIR__ . '/../../config.php';

require_login();
$user = current_user();
$user_id = $user['id'];
$pdo = db();

$activeTab = $_GET['tab'] ?? 'sent';

// Get Sent Credentials - First check all paid transactions
$stmtSent = $pdo->prepare("
    SELECT t.*, l.name as listing_name, buyer.name as buyer_name,
           lc.id as credentials_id, lc.created_at as credentials_submitted_at
    FROM transactions t
    LEFT JOIN listings l ON t.listing_id = l.id
    LEFT JOIN users buyer ON t.buyer_id = buyer.id
    LEFT JOIN listing_credentials lc ON t.id = lc.transaction_id
    WHERE t.seller_id = ? AND t.transfer_status IN ('paid', 'credentials_submitted', 'verified', 'released')

    ORDER BY t.created_at DESC
");
$stmtSent->execute([$user_id]);
$sentCredentials = $stmtSent->fetchAll(PDO::FETCH_ASSOC);

// Get Received Credentials - First check all paid transactions
$stmtReceived = $pdo->prepare("
    SELECT t.*, l.name as listing_name, seller.name as seller_name,
           lc.id as credentials_id, lc.created_at as credentials_submitted_at
    FROM transactions t
    LEFT JOIN listings l ON t.listing_id = l.id
    LEFT JOIN users seller ON t.seller_id = seller.id
    LEFT JOIN listing_credentials lc ON t.id = lc.transaction_id
    WHERE t.buyer_id = ? AND t.transfer_status IN ('paid', 'credentials_submitted', 'verified', 'released')

    ORDER BY t.created_at DESC
");
$stmtReceived->execute([$user_id]);
$receivedCredentials = $stmtReceived->fetchAll(PDO::FETCH_ASSOC);

// Debug information
error_log("My Sales Debug - User ID: $user_id");
error_log("My Sales Debug - Sent Credentials Count: " . count($sentCredentials));
error_log("My Sales Debug - Received Credentials Count: " . count($receivedCredentials));

if (!empty($sentCredentials)) {
    error_log("My Sales Debug - First Sent Transaction: " . json_encode($sentCredentials[0]));
}
if (!empty($receivedCredentials)) {
    error_log("My Sales Debug - First Received Transaction: " . json_encode($receivedCredentials[0]));
}
?>

<?php
// Check if completely empty
$isEmpty = empty($sentCredentials) && empty($receivedCredentials);

// Get user role
$userRole = $user['role'] ?? 'user';
?>

<?php 
// Hide this page completely for admin/superadmin if empty
if ($isEmpty && ($userRole === 'admin' || $userRole === 'superadmin')): 
?>
    <!-- Admin/SuperAdmin Empty State - Hidden -->
    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-blue-50/30 flex items-center justify-center py-8">
        <div class="max-w-md mx-auto px-4 text-center">
            <div class="bg-white/80 backdrop-blur-sm rounded-3xl shadow-xl border border-gray-200 p-12">
                <div class="w-24 h-24 bg-gradient-to-br from-blue-100 to-indigo-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-3">No Credential Transfers</h2>
                <p class="text-gray-600">No credential transfers to monitor at this time</p>
            </div>
        </div>
    </div>
<?php elseif ($isEmpty): ?>
    <!-- User Empty State -->
    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-blue-50/30 flex items-center justify-center py-8">
        <div class="max-w-md mx-auto px-4 text-center">
            <div class="bg-white/80 backdrop-blur-sm rounded-3xl shadow-xl border border-gray-200 p-12">
                <div class="w-24 h-24 bg-gradient-to-br from-blue-100 to-indigo-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-3">No Credentials Yet</h2>
                <p class="text-gray-600">You haven't sent or received any credentials</p>
            </div>
        </div>
    </div>
<?php else: ?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 to-blue-50/30 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Enhanced Header -->
        <div class="mb-8 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-2xl shadow-lg border border-gray-100 mb-4">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
            </div>
            <div class="flex items-center justify-center gap-4">
              <h1 class="text-3xl font-bold text-gray-900">Credentials Management</h1>
              <?php require_once __DIR__ . '/../../includes/export_helper.php'; echo getExportButton('credentials'); ?>
            </div>
            <p class="mt-2 text-lg text-gray-600 max-w-2xl mx-auto">Securely manage and track all your credential transfers in one place</p>
            
            <!-- Stats Cards -->
            <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-3 max-w-4xl mx-auto">
                <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Sent Credentials</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= count($sentCredentials) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Received Credentials</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= count($receivedCredentials) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Verified</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= count(array_filter(array_merge($sentCredentials, $receivedCredentials), fn($item) => $item['transfer_status'] === 'verified')) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modern Tabs -->
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-sm border border-gray-200 p-2 mb-8 max-w-4xl mx-auto">
            <div class="flex space-x-1">
                <a href="?p=dashboard&page=my_sales&tab=sent" 
                   class="flex-1 flex items-center justify-center px-4 py-3 rounded-xl font-medium text-sm transition-all duration-200 <?= $activeTab === 'sent' ? 'bg-white text-blue-700 shadow-sm border border-blue-100' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50/50' ?>">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    Sent Credentials
                </a>
                <a href="?p=dashboard&page=my_sales&tab=received" 
                   class="flex-1 flex items-center justify-center px-4 py-3 rounded-xl font-medium text-sm transition-all duration-200 <?= $activeTab === 'received' ? 'bg-white text-blue-700 shadow-sm border border-blue-100' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50/50' ?>">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Received Credentials
                </a>
            </div>
        </div>

        <?php if ($activeTab === 'sent'): ?>
            <!-- SENT CREDENTIALS -->
            <?php if (empty($sentCredentials)): ?>
                <div class="text-center py-16 bg-white/80 backdrop-blur-sm rounded-2xl shadow-sm border border-gray-200">
                    <div class="max-w-md mx-auto">
                        <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No credentials sent yet</h3>
                        <p class="text-gray-500 mb-6">When you submit credentials for your sales, they'll appear here.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="grid gap-6 max-w-4xl mx-auto">
                    <?php foreach ($sentCredentials as $item): ?>
                        <?php
                        $hasCredentials = !empty($item['credentials_id']);
                        // Show action needed if no credentials submitted yet (regardless of payment status for now)
                        $needsAction = !$hasCredentials;
                        $isVerified = $item['transfer_status'] === 'verified';
                        ?>
                        
                        <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-sm border border-gray-200 p-6 transition-all duration-200 hover:shadow-md <?= $needsAction ? 'border-l-4 border-l-orange-500' : '' ?>">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-3">
                                        <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($item['listing_name']) ?></h3>
                                        <?php if ($isVerified): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                                Verified
                                            </span>
                                        <?php elseif ($hasCredentials): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">Sent</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 border border-orange-200">Action Required</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm text-gray-600 mb-4">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                            <span>Buyer: <?= htmlspecialchars($item['buyer_name']) ?></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <span><?= date('M j, Y', strtotime($item['created_at'])) ?></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8h6m-5 0a3 3 0 110 6H9l3 3m-3-6h6m6 1a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span class="font-medium text-gray-900">$<?= number_format($item['seller_amount'] ?? $item['amount'], 2) ?></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                                            </svg>
                                            <span>Transaction #<?= $item['id'] ?></span>
                                        </div>
                                    </div>

                                    <?php if ($needsAction): ?>
                                        <div class="bg-orange-50 border border-orange-200 rounded-xl p-4 mb-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center flex-shrink-0">
                                                    <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-orange-800">Action Required</p>
                                                    <p class="text-sm text-orange-700">Submit credentials to complete this transaction</p>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="index.php?p=dashboard&page=submit_credentials&transaction_id=<?= $item['id'] ?>" 
                                           class="inline-flex items-center px-6 py-3 bg-orange-600 text-white font-medium rounded-xl hover:bg-orange-700 transition-colors duration-200 shadow-sm">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Submit Credentials
                                        </a>
                                    <?php elseif ($hasCredentials): ?>
                                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                                            <p class="text-sm text-gray-600">
                                                <span class="font-medium">Submitted:</span> <?= date('M j, Y g:i A', strtotime($item['credentials_submitted_at'])) ?>
                                                <?php if ($item['transfer_status'] ==='paid'): ?>
                                                    • <span class="text-green-600 font-medium">Payment released</span>
                                                <?php else: ?>
                                                    • <span class="text-blue-600">Awaiting buyer verification</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- RECEIVED CREDENTIALS -->
            <?php if (empty($receivedCredentials)): ?>
                <div class="text-center py-16 bg-white/80 backdrop-blur-sm rounded-2xl shadow-sm border border-gray-200">
                    <div class="max-w-md mx-auto">
                        <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No credentials received</h3>
                        <p class="text-gray-500 mb-6">Credentials from your purchases will appear here once submitted by sellers.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="grid gap-6 max-w-4xl mx-auto">
                    <?php foreach ($receivedCredentials as $item): ?>
                        <?php 
                        $hasCredentials = !empty($item['credentials_id']); 
                        $isVerified = $item['transfer_status'] === 'verified';
                        ?>
                        
                        <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-sm border border-gray-200 p-6 transition-all duration-200 hover:shadow-md">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-3">
                                        <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($item['listing_name']) ?></h3>
                                        <?php if ($isVerified): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                                Verified
                                            </span>
                                        <?php elseif ($hasCredentials): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">Available</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">Waiting</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm text-gray-600 mb-4">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                            <span>Seller: <?= htmlspecialchars($item['seller_name']) ?></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <span><?= date('M j, Y', strtotime($item['created_at'])) ?></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8h6m-5 0a3 3 0 110 6H9l3 3m-3-6h6m6 1a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span class="font-medium text-gray-900">$<?= number_format($item['amount'], 2) ?></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                                            </svg>
                                            <span>Transaction #<?= $item['id'] ?></span>
                                        </div>
                                    </div>

                                    <?php if (!$hasCredentials): ?>
                                        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0">
                                                    <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-yellow-800">Awaiting Credentials</p>
                                                    <p class="text-sm text-yellow-700">Seller is preparing to send your credentials</p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 mb-4">
                                            <p class="text-sm text-gray-600">
                                                <span class="font-medium">Received:</span> <?= date('M j, Y g:i A', strtotime($item['credentials_submitted_at'])) ?>
                                            </p>
                                        </div>
                                        <div class="flex flex-wrap gap-3">
                                            <a href="index.php?p=dashboard&page=view_credentials&transaction_id=<?= $item['id'] ?>" 
                                               class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-medium rounded-xl hover:bg-blue-700 transition-colors duration-200 shadow-sm">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                                View Credentials
                                            </a>
                                            <?php if ($item['transfer_status'] === 'credentials_submitted'): ?>
                                                <a href="<?= url('index.php?p=dashboard&page=complete_order&id=' . $item['id']) ?>" 
                                                   class="inline-flex items-center px-6 py-3 bg-green-600 text-white font-medium rounded-xl hover:bg-green-700 transition-colors duration-200 shadow-sm animate-pulse">
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    Confirm Receipt (OTP Required)
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>



<?php endif; ?>




<!-- Transaction Polling Integration -->
<script>
const currentUserId = <?= $user_id ?>;
const activeTab = '<?= $activeTab ?>';

document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 My Sales page - initializing transaction polling');
    console.log('👤 User ID:', currentUserId);
    console.log('📑 Active Tab:', activeTab);
    
    // Check if global polling is available
    if (typeof window.globalPollingManager === 'undefined') {
        console.log('⏳ Waiting for global polling manager...');
        setTimeout(() => {
            if (window.globalPollingManager) {
                console.log('✅ Global polling manager found, adding custom callbacks');
                window.globalPollingManager.renderCallbacks.transactions = (newTransactions) => {
            console.log('✅ Transactions callback triggered!');
            console.log('📦 Received transactions:', newTransactions.length);
            
            // Filter transactions for current user
            const myTransactions = newTransactions.filter(t => {
                if (activeTab === 'sent') {
                    return t.seller_id == currentUserId;
                } else {
                    return t.buyer_id == currentUserId;
                }
            });
            
            console.log('👤 My transactions:', myTransactions.length);
            
                    if (myTransactions.length > 0) {
                        console.log('🔄 New transaction updates detected');
                        updateTransactionStats(myTransactions);
                        updateTransactionCards(myTransactions);
                        showNotification(`${myTransactions.length} transaction update${myTransactions.length > 1 ? 's' : ''}!`, 'info');
                    }
                };
            } else {
                console.error('❌ Global polling manager not available');
            }
        }, 2000);
        return;
    }
    
    // If global polling manager is already available
    if (window.globalPollingManager) {
        console.log('✅ Adding custom transaction callback to existing polling');
        window.globalPollingManager.renderCallbacks.transactions = (newTransactions) => {
            console.log('✅ Transactions callback triggered!');
            console.log('📦 Received transactions:', newTransactions.length);
            
            // Filter transactions for current user
            const myTransactions = newTransactions.filter(t => {
                if (activeTab === 'sent') {
                    return t.seller_id == currentUserId;
                } else {
                    return t.buyer_id == currentUserId;
                }
            });
            
            console.log('👤 My transactions:', myTransactions.length);
            
            if (myTransactions.length > 0) {
                console.log('🔄 New transaction updates detected');
                updateTransactionStats(myTransactions);
                updateTransactionCards(myTransactions);
                showNotification(`${myTransactions.length} transaction update${myTransactions.length > 1 ? 's' : ''}!`, 'info');
            }
        };
    }
    
    console.log('✅ Transaction polling started for My Sales page');
});

function updateTransactionStats(transactions) {
    // Update stats counters
    const verifiedCount = transactions.filter(t => t.transfer_status === 'verified').length;
    
    if (verifiedCount > 0) {
        const verifiedEl = document.querySelector('.text-purple-600').closest('.p-6').querySelector('.text-2xl');
        if (verifiedEl) {
            const currentCount = parseInt(verifiedEl.textContent) || 0;
            verifiedEl.textContent = currentCount + verifiedCount;
        }
    }
    
    // Update tab-specific counters
    if (activeTab === 'sent') {
        const sentEl = document.querySelector('.text-blue-600').closest('.p-6').querySelector('.text-2xl');
        if (sentEl) {
            const currentCount = parseInt(sentEl.textContent) || 0;
            sentEl.textContent = currentCount + transactions.length;
        }
    } else {
        const receivedEl = document.querySelector('.text-green-600').closest('.p-6').querySelector('.text-2xl');
        if (receivedEl) {
            const currentCount = parseInt(receivedEl.textContent) || 0;
            receivedEl.textContent = currentCount + transactions.length;
        }
    }
}

function updateTransactionCards(transactions) {
    const container = document.querySelector('.grid.gap-6.max-w-4xl');
    if (!container) {
        console.log('⚠️ Transaction container not found');
        return;
    }
    
    transactions.forEach(transaction => {
        // Check if transaction already exists
        const existingCard = Array.from(container.querySelectorAll('.bg-white\\/80')).find(card => {
            const txIdText = card.textContent;
            return txIdText.includes(`Transaction #${transaction.id}`);
        });
        
        if (existingCard) {
            console.log('🔄 Updating existing transaction:', transaction.id);
            updateExistingTransactionCard(existingCard, transaction);
        } else {
            console.log('➕ Adding new transaction:', transaction.id);
            addNewTransactionCard(container, transaction);
        }
    });
}

function updateExistingTransactionCard(card, transaction) {
    // Update status badge
    const statusBadge = card.querySelector('.inline-flex.items-center.px-2\\.5');
    if (statusBadge) {
        if (transaction.transfer_status === 'verified') {
            statusBadge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200';
            statusBadge.innerHTML = '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Verified';
        } else if (transaction.transfer_status === 'credentials_submitted') {
            statusBadge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200';
            statusBadge.textContent = activeTab === 'sent' ? 'Sent' : 'Available';
        }
    }
    
    // Highlight card
    card.style.backgroundColor = '#dbeafe';
    setTimeout(() => {
        card.style.backgroundColor = '';
    }, 3000);
}

function addNewTransactionCard(container, transaction) {
    // For simplicity, reload page for new transactions
    // You can implement full card creation here if needed
    console.log('🔄 New transaction detected - reloading page');
    setTimeout(() => location.reload(), 1000);
}

function showNotification(message, type = 'info') {
    const colors = {
        info: 'bg-blue-500',
        success: 'bg-green-500',
        warning: 'bg-yellow-500',
        error: 'bg-red-500'
    };
    
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-4 py-3 rounded-lg shadow-lg z-50 animate-fade-in`;
    notification.innerHTML = `
        <div class="flex items-center gap-2">
            <i class="fas fa-${type === 'success' ? 'check' : 'info'}-circle"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
</script>
