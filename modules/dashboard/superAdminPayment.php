<?php
// Check for export FIRST
if (isset($_GET['export'])) {
    ob_start(); // Start output buffering
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/pagination_helper.php';
require_once __DIR__ . '/../../includes/export_helper.php';
require_login();


// If export, clean any buffered output
if (isset($_GET['export'])) {
    ob_end_clean();
}


$pdo = db();

// Get pagination parameters
$page = getCurrentPage('pg');
$perPage = 10;

// Get current tab
$activeTab = $_GET['tab'] ?? 'transactions';
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

// Check for real payment data from database
$hasPaymentData = false;
$hasTransactionData = false;
$payments = [];
$transactions = [];
$paymentsPagination = null;
$transactionsPagination = null;

// Setup conditions for search and filters
$whereClause = '';
$params = [];

if ($search) {
    $whereClause .= ' AND (u.name LIKE :search OR u.email LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if ($status) {
    $whereClause .= ' AND status = :status';
    $params[':status'] = $status;
}

// Try to fetch real payment data
try {
    // Check if payments table exists and has data
    $checkPaymentsStmt = $pdo->query("SELECT COUNT(*) FROM payments");
    $totalPaymentsCount = $checkPaymentsStmt->fetchColumn();

    if ($totalPaymentsCount > 0) {
        $hasPaymentData = true;

        if ($activeTab === 'payments') {
            $paymentsSql = "
                SELECT 
                    p.id AS payment_id,
                    p.amount,
                    p.commission,
                    p.status,
                    p.created_at AS payment_date,
                    p.payment_method,
                    buyer.name AS buyer_name,
                    buyer.email AS buyer_email,
                    seller.name AS seller_name,
                    seller.email AS seller_email
                FROM payments p
                LEFT JOIN users buyer ON p.buyer_id = buyer.id
                LEFT JOIN users seller ON p.seller_id = seller.id
                WHERE 1=1 $whereClause
                ORDER BY p.created_at DESC
            ";

            $paymentsCountSql = "
                SELECT COUNT(*) as total
                FROM payments p
                LEFT JOIN users buyer ON p.buyer_id = buyer.id
                LEFT JOIN users seller ON p.seller_id = seller.id
                WHERE 1=1 $whereClause
            ";

            try {
                // Handle export request FIRST (before any output)
                if (isset($_GET['export']) && $activeTab === 'payments') {
                    $exportStmt = $pdo->prepare($paymentsSql);
                    $exportStmt->execute($params);
                    $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
                    handleExportRequest($exportData, 'Payments Report');
                }

                $paymentsResult = getCustomPaginationData($pdo, $paymentsSql, $paymentsCountSql, $params, $page, $perPage);
                $payments = $paymentsResult['data'];
                $paymentsPagination = $paymentsResult['pagination'];
            } catch (Exception $e) {
                $payments = [];
                $paymentsPagination = null;
            }
        }
    }
} catch (Exception $e) {
    $hasPaymentData = false;
}
try {
    // Check if orders table exists and has data
    $checkOrdersStmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $totalOrdersCount = $checkOrdersStmt->fetchColumn();

    if ($totalOrdersCount > 0) {
        $hasTransactionData = true;

        if ($activeTab === 'transactions') {
            $transactionsSql = "
                SELECT 
                    o.id AS transaction_id,
                    o.amount,
                    o.platform_fee,
                    o.total,
                    o.status,
                    o.created_at AS transaction_date,
                    o.updated_at,
                    buyer.name AS buyer_name,
                    buyer.email AS buyer_email,
                    seller.name AS seller_name,
                    seller.email AS seller_email,
                    l.name AS listing_name
                FROM orders o
                LEFT JOIN users buyer ON o.buyer_id = buyer.id
                LEFT JOIN users seller ON o.seller_id = seller.id
                LEFT JOIN listings l ON o.listing_id = l.id
                WHERE 1=1 $whereClause
                ORDER BY o.created_at DESC
            ";

            $transactionsCountSql = "
                SELECT COUNT(*) as total
                FROM orders o
                LEFT JOIN users buyer ON o.buyer_id = buyer.id
                LEFT JOIN users seller ON o.seller_id = seller.id
                LEFT JOIN listings l ON o.listing_id = l.id
                WHERE 1=1 $whereClause
            ";

            try {
                // Handle export request FIRST (before any output)
                if (isset($_GET['export']) && $activeTab === 'transactions') {
                    $exportStmt = $pdo->prepare($transactionsSql);
                    $exportStmt->execute($params);
                    $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
                    handleExportRequest($exportData, 'Transactions Report');
                }

                $transactionsResult = getCustomPaginationData($pdo, $transactionsSql, $transactionsCountSql, $params, $page, $perPage);
                $transactions = $transactionsResult['data'];
                $transactionsPagination = $transactionsResult['pagination'];
            } catch (Exception $e) {
                $transactions = [];
                $transactionsPagination = null;
            }
        }
    }
} catch (Exception $e) {
    $hasTransactionData = false;
}

// Calculate stats from real data or set defaults
$totalEscrow = 0;
$totalCommission = 0;
$monthlyRevenue = 0;
$commissionRate = 5;

try {
    if ($hasPaymentData) {
        $escrowStmt = $pdo->query("SELECT SUM(amount) FROM payments WHERE status IN ('authorized', 'pending')");
        $totalEscrow = $escrowStmt->fetchColumn() ?: 0;

        $commissionStmt = $pdo->query("SELECT SUM(commission) FROM payments WHERE status = 'completed'");
        $totalCommission = $commissionStmt->fetchColumn() ?: 0;

        $revenueStmt = $pdo->query("SELECT SUM(commission) FROM payments WHERE status = 'completed' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
        $monthlyRevenue = $revenueStmt->fetchColumn() ?: 0;
    }
} catch (Exception $e) {
    // Use default values if queries fail
}

// Determine if we should show empty state
$showEmptyState = ($activeTab === 'payments' && !$hasPaymentData) || ($activeTab === 'transactions' && !$hasTransactionData);
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');



    .tab-active {
        background-color: #3b82f6;
        color: white;
    }

    .hover-row:hover {
        background-color: #f8fafc;
    }

    .stat-card {
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
</style>

<div class="max-w-7xl mx-auto p-4 md:p-6">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Payments & Finance</h1>
            <p class="text-gray-500 mt-1">Monitor transactions, revenue, and manage financial settings</p>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1.5 rounded-full">
                <i class="fa fa-chart-line mr-1.5"></i> Live Updates
            </span>
            <?= getExportButton($activeTab) ?>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
        <div class="stat-card bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Total Escrow</p>
                    <h2 id="total-escrow-amount" class="text-2xl md:text-3xl font-bold text-gray-900 mt-1">$<?= number_format($totalEscrow, 2) ?></h2>
                    <?php if ($totalEscrow > 0): ?>
                        <p class="text-green-500 text-sm font-semibold mt-2 flex items-center">
                            <i class="fa fa-shield-alt mr-1"></i> Funds in escrow
                        </p>
                    <?php else: ?>
                        <p class="text-gray-400 text-sm mt-2">No funds in escrow</p>
                    <?php endif; ?>
                </div>
                <div class="bg-blue-100 text-blue-500 p-3 rounded-lg">
                    <i class="fa-solid fa-wallet text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Commission Earned</p>
                    <h2 id="total-commission-amount" class="text-2xl md:text-3xl font-bold text-gray-900 mt-1">$<?= number_format($totalCommission, 2) ?></h2>
                    <?php if ($totalCommission > 0): ?>
                        <p class="text-green-500 text-sm font-semibold mt-2 flex items-center">
                            <i class="fa fa-dollar-sign mr-1"></i> Total earnings
                        </p>
                    <?php else: ?>
                        <p class="text-gray-400 text-sm mt-2">No commissions yet</p>
                    <?php endif; ?>
                </div>
                <div class="bg-green-100 text-green-500 p-3 rounded-lg">
                    <i class="fa-solid fa-percent text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Monthly Revenue</p>
                    <h2 id="monthly-revenue-amount" class="text-2xl md:text-3xl font-bold text-gray-900 mt-1">$<?= number_format($monthlyRevenue, 2) ?></h2>
                    <?php if ($monthlyRevenue > 0): ?>
                        <p class="text-green-500 text-sm font-semibold mt-2 flex items-center">
                            <i class="fa fa-chart-line mr-1"></i> This month
                        </p>
                    <?php else: ?>
                        <p class="text-gray-400 text-sm mt-2">No revenue this month</p>
                    <?php endif; ?>
                </div>
                <div class="bg-purple-100 text-purple-500 p-3 rounded-lg">
                    <i class="fa-solid fa-chart-line text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Commission Rate</p>
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mt-1"><?= $commissionRate ?>%</h2>
                    <p class="text-blue-500 text-sm font-semibold mt-2 flex items-center">
                        <i class="fa fa-cog mr-1"></i> Platform fee
                    </p>
                </div>
                <div class="bg-orange-100 text-orange-500 p-3 rounded-lg">
                    <i class="fa-solid fa-gear text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs and Content Section -->
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-4">
            <div class="flex flex-wrap gap-2">
                <button class="tab-btn px-4 py-2 rounded-lg text-sm font-medium <?= $activeTab === 'payments' ? 'bg-blue-600 text-white' : 'text-gray-600 bg-gray-100 hover:bg-gray-200' ?> transition-colors" data-tab="payments">
                    <i class="fa-solid fa-credit-card mr-2"></i> Payments
                    <?php
                    $paymentCount = 0;
                    try {
                        $paymentCount = $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn();
                    } catch (Exception $e) {
                        $paymentCount = 0;
                    }
                    ?>
                    <span class="ml-2 <?= $activeTab === 'payments' ? 'bg-blue-500' : 'bg-gray-500' ?> text-white text-xs font-medium px-2 py-0.5 rounded-full"><?= $paymentCount ?></span>
                </button>
                <button class="tab-btn px-4 py-2 rounded-lg text-sm font-medium <?= $activeTab === 'transactions' ? 'bg-blue-600 text-white' : 'text-gray-600 bg-gray-100 hover:bg-gray-200' ?> transition-colors" data-tab="transactions">
                    <i class="fa-solid fa-exchange-alt mr-2"></i> Transactions
                    <?php
                    $transactionCount = 0;
                    try {
                        $transactionCount = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
                    } catch (Exception $e) {
                        $transactionCount = 0;
                    }
                    ?>
                    <span class="ml-2 <?= $activeTab === 'transactions' ? 'bg-blue-500' : 'bg-gray-500' ?> text-white text-xs font-medium px-2 py-0.5 rounded-full"><?= $transactionCount ?></span>
                </button>
                <button class="tab-btn px-4 py-2 rounded-lg text-sm font-medium <?= $activeTab === 'settings' ? 'bg-blue-600 text-white' : 'text-gray-600 bg-gray-100 hover:bg-gray-200' ?> transition-colors" data-tab="settings">
                    <i class="fa-solid fa-cog mr-2"></i> Settings
                </button>
            </div>

            <div class="flex items-center gap-3">
                <div class="relative">
                    <input type="text" placeholder="Search payments..." class="w-64 pl-10 pr-4 py-2 rounded-lg border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-gray-400"></i>
                </div>
                <select class="px-3 py-2 rounded-lg border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option>Last 7 days</option>
                    <option>Last 30 days</option>
                    <option>Last 90 days</option>
                    <option>Custom range</option>
                </select>
            </div>
        </div>

        <!-- Payments Tab -->
        <div id="payments" class="tab-content <?= $activeTab !== 'payments' ? 'hidden' : '' ?>">
            <?php if ($showEmptyState && $activeTab === 'payments'): ?>
                <!-- Empty State for Payments -->
                <div class="text-center py-16">
                    <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-credit-card text-blue-500 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">No Payment Records Found</h3>
                    <p class="text-gray-600 mb-6 max-w-md mx-auto">
                        Payment records will appear here when transactions are processed through the platform.
                    </p>
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 max-w-lg mx-auto">
                        <p class="text-sm text-blue-800 font-medium">
                            <i class="fas fa-info-circle mr-2"></i>
                            Payment system is ready to track transactions, commissions, and escrow funds.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-gray-600 border-b">
                                <th class="py-3 px-4 font-medium">Payment ID</th>
                                <th class="py-3 px-4 font-medium">Buyer</th>
                                <th class="py-3 px-4 font-medium">Seller</th>
                                <th class="py-3 px-4 font-medium">Amount</th>
                                <th class="py-3 px-4 font-medium">Commission</th>
                                <th class="py-3 px-4 font-medium">Status</th>
                                <th class="py-3 px-4 font-medium">Date</th>
                                <th class="py-3 px-4 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <?php if ($hasPaymentData && count($payments) > 0): ?>
                                <?php foreach ($payments as $payment): ?>
                                    <tr class="hover-row transition-colors">
                                        <td class="py-4 px-4">
                                            <div class="font-medium text-gray-800">PAY<?= str_pad($payment['payment_id'], 3, '0', STR_PAD_LEFT) ?></div>
                                            <div class="text-xs text-gray-500 mt-1">Payment ID</div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="font-medium text-gray-800"><?= htmlspecialchars($payment['buyer_name'] ?? 'Unknown') ?></div>
                                            <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($payment['buyer_email'] ?? '') ?></div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="font-medium text-gray-800"><?= htmlspecialchars($payment['seller_name'] ?? 'Unknown') ?></div>
                                            <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($payment['seller_email'] ?? '') ?></div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="font-semibold text-gray-800">$<?= number_format($payment['amount'], 2) ?></div>
                                            <div class="text-xs text-gray-500 mt-1">Net: $<?= number_format($payment['amount'] - $payment['commission'], 2) ?></div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="font-semibold text-gray-800">$<?= number_format($payment['commission'], 2) ?></div>
                                            <div class="text-xs text-gray-500 mt-1"><?= round(($payment['commission'] / $payment['amount']) * 100, 1) ?>%</div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <?php
                                            $statusMap = [
                                                'pending' => 'bg-yellow-100 text-yellow-700',
                                                'authorized' => 'bg-blue-100 text-blue-700',
                                                'completed' => 'bg-green-100 text-green-700',
                                                'failed' => 'bg-red-100 text-red-700',
                                                'refunded' => 'bg-gray-100 text-gray-700'
                                            ];
                                            $statusClass = $statusMap[$payment['status']] ?? 'bg-gray-100 text-gray-700';
                                            ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full <?= $statusClass ?> text-xs font-medium">
                                                <i class="fa fa-circle mr-1.5"></i> <?= ucfirst($payment['status']) ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-gray-600"><?= date('M j, Y', strtotime($payment['payment_date'])) ?></td>
                                        <td class="py-4 px-4">
                                            <button class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                                                <i class="fa fa-eye mr-1.5"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-16 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                                <i class="fas fa-credit-card text-gray-400 text-2xl"></i>
                                            </div>
                                            <h3 class="text-lg font-medium text-gray-900 mb-2">No Payment Records</h3>
                                            <p class="text-gray-500 mb-4">Payment records will appear here when transactions are processed</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

        </div>

        <!-- Pagination -->
        <?php if ($hasPaymentData && $paymentsPagination): ?>
            <div class="flex flex-col md:flex-row justify-between items-center mt-6 pt-4 border-t border-gray-200">
                <p class="text-sm text-gray-500 mb-4 md:mb-0">
                    Showing <?= $paymentsPagination['start_item'] ?> to <?= $paymentsPagination['end_item'] ?> of <?= $paymentsPagination['total_items'] ?> payments
                </p>
                <?php
                    $extraParams = ['tab' => 'payments'];
                    if ($search) $extraParams['search'] = $search;
                    if ($status) $extraParams['status'] = $status;
                    echo renderPagination($paymentsPagination, url('dashboard/superAdminPayment'), $extraParams, 'pg');
                ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    </div>

    <!-- Transactions Tab -->
    <div id="transactions" class="tab-content <?= $activeTab !== 'transactions' ? 'hidden' : '' ?>">
        <?php if ($showEmptyState && $activeTab === 'transactions'): ?>
            <!-- Empty State for Transactions -->
            <div class="text-center py-16">
                <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-exchange-alt text-green-500 text-3xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-3">No Transaction Records Found</h3>
                <p class="text-gray-600 mb-6 max-w-md mx-auto">
                    Transaction records will appear here when financial activities occur on the platform.
                </p>
                <div class="bg-green-50 border border-green-200 rounded-xl p-4 max-w-lg mx-auto">
                    <p class="text-sm text-green-800 font-medium">
                        <i class="fas fa-info-circle mr-2"></i>
                        Transaction system is ready to track all financial activities and movements.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-gray-600 border-b">
                            <th class="py-3 px-4 font-medium">Order ID</th>
                            <th class="py-3 px-4 font-medium">Buyer / Seller</th>
                            <th class="py-3 px-4 font-medium">Listing</th>
                            <th class="py-3 px-4 font-medium">Amount</th>
                            <th class="py-3 px-4 font-medium">Platform Fee</th>
                            <th class="py-3 px-4 font-medium">Date</th>
                            <th class="py-3 px-4 font-medium">Status</th>
                            <th class="py-3 px-4 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <?php if ($hasTransactionData && count($transactions) > 0): ?>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr class="hover-row transition-colors">
                                    <td class="py-4 px-4">
                                        <div class="font-medium text-gray-800">ORD-<?= str_pad($transaction['transaction_id'], 3, '0', STR_PAD_LEFT) ?></div>
                                        <div class="text-xs text-gray-500 mt-1">Transaction</div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-medium text-gray-800"><?= htmlspecialchars($transaction['buyer_name'] ?? 'Unknown') ?></div>
                                        <div class="text-xs text-gray-500 mt-1">→ <?= htmlspecialchars($transaction['seller_name'] ?? 'Unknown') ?></div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-medium text-gray-800"><?= htmlspecialchars($transaction['listing_name'] ?? 'N/A') ?></div>
                                        <div class="text-xs text-gray-500 mt-1">Business listing</div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-semibold text-gray-800">$<?= number_format($transaction['amount'], 2) ?></div>
                                        <div class="text-xs text-gray-500 mt-1">Total: $<?= number_format($transaction['total'], 2) ?></div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-semibold text-green-600">$<?= number_format($transaction['platform_fee'], 2) ?></div>
                                        <div class="text-xs text-gray-500 mt-1"><?= round(($transaction['platform_fee'] / $transaction['amount']) * 100, 1) ?>%</div>
                                    </td>
                                    <td class="py-4 px-4 text-gray-600">
                                        <div><?= date('M j, Y', strtotime($transaction['transaction_date'])) ?></div>
                                        <div class="text-xs text-gray-500 mt-1"><?= date('H:i', strtotime($transaction['transaction_date'])) ?></div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <?php
                                        $statusMap = [
                                            'pending_payment' => 'bg-yellow-100 text-yellow-700',
                                            'paid' => 'bg-blue-100 text-blue-700',
                                            'completed' => 'bg-green-100 text-green-700',
                                            'in_progress' => 'bg-blue-100 text-blue-700',
                                            'cancelled' => 'bg-red-100 text-red-700',
                                            'refunded' => 'bg-purple-100 text-purple-700'
                                        ];
                                        $statusClass = $statusMap[$transaction['status']] ?? 'bg-gray-100 text-gray-700';
                                        ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full <?= $statusClass ?> text-xs font-medium">
                                            <i class="fa fa-circle mr-1.5"></i> <?= ucfirst(str_replace('_', ' ', $transaction['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <button class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                                            <i class="fa fa-eye mr-1.5"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                            <i class="fas fa-exchange-alt text-gray-400 text-2xl"></i>
                                        </div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Transaction Records</h3>
                                        <p class="text-gray-500 mb-4">Transaction records will appear here when financial activities occur</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($hasTransactionData && $transactionsPagination): ?>
                <div class="flex flex-col md:flex-row justify-between items-center mt-6 pt-4 border-t border-gray-200">
                    <p class="text-sm text-gray-500 mb-4 md:mb-0">
                        Showing <?= $transactionsPagination['start_item'] ?> to <?= $transactionsPagination['end_item'] ?> of <?= $transactionsPagination['total_items'] ?> transactions
                    </p>
                    <?php
                    $extraParams = ['tab' => 'transactions'];
                    if ($search) $extraParams['search'] = $search;
                    if ($status) $extraParams['status'] = $status;
                    echo renderPagination($transactionsPagination, url('dashboard/superAdminPayment'), $extraParams, 'pg');
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Settings Tab -->
    <div id="settings" class="tab-content hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Commission Settings -->
            <div class="bg-gray-50 rounded-xl p-4 md:p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Commission Settings</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Commission Rate (%)</label>
                        <div class="flex gap-3 items-center">
                            <input id="commissionRate" type="number" value="5" class="w-24 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                            <button id="updateRateBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">Update Rate</button>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">This rate applies to all new transactions</p>
                    </div>

                    <div class="pt-4 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Revenue Summary</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">This Month</span>
                                <span class="font-semibold text-green-600">$1,250</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Last Month</span>
                                <span class="font-semibold text-gray-700">$980</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Quarter-to-Date</span>
                                <span class="font-semibold text-gray-700">$3,450</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payout Settings -->
            <div class="bg-gray-50 rounded-xl p-4 md:p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Payout Settings</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between bg-white p-4 rounded-lg border border-gray-200">
                        <div>
                            <div class="text-sm font-medium text-gray-700">Automatic Payouts</div>
                            <div class="text-xs text-gray-500">Enable automatic payouts when minimum is reached</div>
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer-checked:bg-green-600 transition"></div>
                                <span class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition peer-checked:translate-x-5"></span>
                            </label>
                            <div class="relative">
                                <input type="number" value="100" class="w-20 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                                <span class="absolute right-3 top-2 text-gray-500 text-sm">$</span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Payout Methods</h4>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between bg-white p-3 rounded-lg border border-gray-200">
                                <div class="flex items-center">
                                    <i class="fa fa-university text-blue-500 mr-3"></i>
                                    <span class="text-sm text-gray-700">Bank Transfer</span>
                                </div>
                                <span class="text-xs text-green-500 font-medium">Active</span>
                            </div>
                            <div class="flex items-center justify-between bg-white p-3 rounded-lg border border-gray-200">
                                <div class="flex items-center">
                                    <i class="fa fa-paypal text-blue-500 mr-3"></i>
                                    <span class="text-sm text-gray-700">PayPal</span>
                                </div>
                                <span class="text-xs text-green-500 font-medium">Active</span>
                            </div>
                            <div class="flex items-center justify-between bg-white p-3 rounded-lg border border-gray-200">
                                <div class="flex items-center">
                                    <i class="fa fa-credit-card text-blue-500 mr-3"></i>
                                    <span class="text-sm text-gray-700">Stripe</span>
                                </div>
                                <span class="text-xs text-gray-500 font-medium">Inactive</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Summary Section -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
    <!-- Payment Status Summary -->
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Status Summary</h3>
        <div class="space-y-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full bg-blue-500 mr-3"></div>
                    <span class="text-sm text-gray-600">Authorized</span>
                </div>
                <span class="text-sm font-medium">12 payments</span>
            </div>
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full bg-green-500 mr-3"></div>
                    <span class="text-sm text-gray-600">Released</span>
                </div>
                <span class="text-sm font-medium">8 payments</span>
            </div>
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full bg-yellow-500 mr-3"></div>
                    <span class="text-sm text-gray-600">Pending</span>
                </div>
                <span class="text-sm font-medium">3 payments</span>
            </div>
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full bg-red-500 mr-3"></div>
                    <span class="text-sm text-gray-600">Failed</span>
                </div>
                <span class="text-sm font-medium">1 payment</span>
            </div>
        </div>
    </div>


</div>
</div>

<script>
    const tabButtons = document.querySelectorAll('[data-tab]');
    const tabs = document.querySelectorAll('.tab-content');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            tabButtons.forEach(b => {
                b.classList.remove('tab-active', 'bg-blue-600', 'text-white');
                b.classList.add('text-gray-600', 'bg-gray-100');
            });
            btn.classList.add('tab-active', 'bg-blue-600', 'text-white');
            btn.classList.remove('text-gray-600', 'bg-gray-100');

            const target = btn.dataset.tab;
            tabs.forEach(t => t.classList.add('hidden'));
            document.getElementById(target).classList.remove('hidden');
        });
    });
</script>
<!-- Polling scripts already loaded in dashboard.php -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof startPolling === 'function') {
            startPolling({
                payments: function(newPayments) {
                    if (newPayments && newPayments.length > 0) {
                        PollingUIHelpers.showBriefNotification(`${newPayments.length} new payments received`);

                        // Update stats (rudimentary increment as we don't have full server recalc)
                        // We can just add the amounts from new payments
                        newPayments.forEach(p => {
                            if (p.status === 'authorized' || p.status === 'pending') {
                                updateStatAmount('total-escrow-amount', parseFloat(p.amount));
                            }
                            if (p.status === 'completed') {
                                updateStatAmount('total-commission-amount', parseFloat(p.commission));
                                // crude check for current month
                                const d = new Date(p.payment_date);
                                const now = new Date();
                                if (d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear()) {
                                    updateStatAmount('monthly-revenue-amount', parseFloat(p.commission));
                                }
                            }
                        });

                        // Update Table
                        const tbody = document.querySelector('#payments table tbody');
                        if (tbody) {
                            newPayments.forEach(p => {
                                if (!document.querySelector(`tr[data-payment-id="${p.payment_id}"]`)) { // simplistic duplicate check
                                    // PollingUIHelpers.addRecordToTable too generic? 
                                    // We construct row manually for custom layout
                                    const tr = document.createElement('tr');
                                    tr.className = 'hover-row transition-colors bg-blue-50';
                                    tr.innerHTML = `
                                    <td class="py-4 px-4">
                                        <div class="font-medium text-gray-800">PAY${String(p.payment_id).padStart(3, '0')}</div>
                                        <div class="text-xs text-gray-500 mt-1">Payment ID</div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-medium text-gray-800">${p.buyer_name || 'Unknown'}</div>
                                        <div class="text-xs text-gray-500 mt-1">${p.buyer_email || ''}</div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-medium text-gray-800">${p.seller_name || 'Unknown'}</div>
                                        <div class="text-xs text-gray-500 mt-1">${p.seller_email || ''}</div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-semibold text-gray-800">$${parseFloat(p.amount).toFixed(2)}</div>
                                        <div class="text-xs text-gray-500 mt-1">Net: $${(parseFloat(p.amount) - parseFloat(p.commission)).toFixed(2)}</div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="font-semibold text-gray-800">$${parseFloat(p.commission).toFixed(2)}</div>
                                        <div class="text-xs text-gray-500 mt-1">${((p.commission/p.amount)*100).toFixed(1)}%</div>
                                    </td>
                                    <td class="py-4 px-4">
                                         <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-medium">
                                            <i class="fa fa-circle mr-1.5"></i> ${p.status.charAt(0).toUpperCase() + p.status.slice(1)}
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 text-gray-600">${new Date(p.payment_date).toLocaleDateString()}</td>
                                    <td class="py-4 px-4">
                                        <button class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                                            <i class="fa fa-eye mr-1.5"></i> View
                                        </button>
                                    </td>
                                `;
                                    tbody.insertBefore(tr, tbody.firstChild);
                                }
                            });
                            // Remove empty row if exists
                            const emptyRow = tbody.querySelector('td[colspan="8"]');
                            if (emptyRow) emptyRow.parentElement.remove();
                        }
                    }
                },
                orders: function(newTransactions) {
                    if (newTransactions && newTransactions.length > 0) {
                        PollingUIHelpers.showBriefNotification(`${newTransactions.length} new transactions`);
                        const tbody = document.querySelector('#transactions table tbody');
                        if (tbody) {
                            newTransactions.forEach(t => {
                                // Assuming we don't duplicate check exhaustively here aside from ID
                                const tr = document.createElement('tr');
                                tr.className = 'hover-row transition-colors bg-green-50';
                                tr.innerHTML = `
                                <td class="py-4 px-4">
                                    <div class="font-medium text-gray-800">ORD-${String(t.transaction_id || t.id).padStart(3, '0')}</div>
                                    <div class="text-xs text-gray-500 mt-1">Transaction</div>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="font-medium text-gray-800">${t.buyer_name || 'Unknown'}</div>
                                    <div class="text-xs text-gray-500 mt-1">→ ${t.seller_name || 'Unknown'}</div>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="font-medium text-gray-800">${t.listing_name || 'N/A'}</div>
                                    <div class="text-xs text-gray-500 mt-1">Business listing</div>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="font-semibold text-gray-800">$${parseFloat(t.amount).toFixed(2)}</div>
                                    <div class="text-xs text-gray-500 mt-1">Total: $${parseFloat(t.total || t.amount).toFixed(2)}</div>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="font-semibold text-green-600">$${parseFloat(t.platform_fee || 0).toFixed(2)}</div>
                                </td>
                                <td class="py-4 px-4 text-gray-600">
                                    <div>${new Date(t.created_at || t.transaction_date).toLocaleDateString()}</div>
                                    <div class="text-xs text-gray-500 mt-1">${new Date(t.created_at || t.transaction_date).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-yellow-100 text-yellow-700 text-xs font-medium">
                                        <i class="fa fa-circle mr-1.5"></i> ${t.status}
                                    </span>
                                </td>
                                <td class="py-4 px-4">
                                    <button class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                                        <i class="fa fa-eye mr-1.5"></i> View
                                    </button>
                                </td>
                             `;
                                tbody.insertBefore(tr, tbody.firstChild);
                            });
                            const emptyRow = tbody.querySelector('td[colspan="6"]');
                            if (emptyRow) emptyRow.parentElement.remove();
                        }
                    }
                }
            });
        }
    });

    function updateStatAmount(id, add) {
        const el = document.getElementById(id);
        if (!el) return;
        let current = parseFloat(el.textContent.replace(/[^0-9.-]+/g, "")) || 0;
        let newVal = current + add;
        el.textContent = '$' + newVal.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        el.classList.add('text-green-600');
        setTimeout(() => el.classList.remove('text-green-600'), 2000);
    }