<?php
/**
 * Simplified Offers Page - Guaranteed to Work
 * This is a clean, simple version without complex JavaScript
 */

require_once __DIR__ . "/../../config.php";
require_login();

$pdo = db();
$currentUser = current_user();
$logged_in_user_id = $currentUser['id'] ?? 0;

// Get pagination parameters
$page = isset($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Setup search and filter conditions
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$whereClause = 'WHERE o.seller_id = ?';
$params = [$logged_in_user_id];

if ($search) {
    $whereClause .= ' AND (l.name LIKE ? OR b.name LIKE ? OR b.email LIKE ?)';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}
if ($status) {
    $whereClause .= ' AND o.status = ?';
    $params[] = $status;
}

// Get total count
$countSql = "
    SELECT COUNT(*) as total
    FROM offers o
    LEFT JOIN listings l ON o.listing_id = l.id
    LEFT JOIN users b ON o.user_id = b.id
    $whereClause
";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();

// Calculate pagination
$totalPages = ceil($totalItems / $perPage);
$page = min($page, max(1, $totalPages));

// Get offers data
$sql = "
    SELECT 
        o.*,
        l.name AS listing_name,
        l.type AS listing_category,
        l.asking_price AS listing_price,
        b.name AS buyer_name,
        b.email AS buyer_email,
        b.profile_pic AS buyer_profile_pic,
        s.name AS seller_name,
        s.email AS seller_email,
        (SELECT MAX(amount) FROM offers WHERE listing_id = o.listing_id AND status = 'pending') AS max_offer_amount,
        (SELECT COUNT(*) FROM offers WHERE listing_id = o.listing_id AND status = 'pending') AS total_offers_count
    FROM offers o
    LEFT JOIN listings l ON o.listing_id = l.id
    LEFT JOIN users b ON o.user_id = b.id
    LEFT JOIN users s ON o.seller_id = s.id
    $whereClause
    ORDER BY o.created_at DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to safely display values
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Offers - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-50">

<div class="max-w-7xl mx-auto p-4 sm:p-6 md:p-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 gap-3">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center gap-3">
            <i class="fas fa-handshake text-blue-600"></i> 
            My Offers (<span><?= $totalItems ?></span>)
        </h1>
        <div class="text-sm text-gray-500">
            <i class="fas fa-calendar-alt mr-2"></i>Updated on <?= date("d M Y") ?>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <input type="hidden" name="p" value="dashboard">
            <input type="hidden" name="page" value="offers_simple">
            
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fa fa-search mr-1"></i>Search Offers
                </label>
                <input type="text" name="search" value="<?= e($search) ?>" 
                       placeholder="Search by listing name, buyer name or email..." 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            
            <div class="min-w-[150px]">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fa fa-filter mr-1"></i>Status
                </label>
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Status</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="accepted" <?= $status === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            
            <div class="flex gap-2">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                    <i class="fa fa-search mr-2"></i>Filter
                </button>
                <a href="?p=dashboard&page=offers_simple" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors flex items-center">
                    <i class="fa fa-times mr-2"></i>Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Offers Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-left text-gray-700">
                <thead class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-xs uppercase">
                    <tr>
                        <th class="px-6 py-3">#</th>
                        <th class="px-6 py-3">Listing</th>
                        <th class="px-6 py-3">Category</th>
                        <th class="px-6 py-3 hidden md:table-cell">Asking Price</th>
                        <th class="px-6 py-3">Offer Amount</th>
                        <th class="px-6 py-3 hidden sm:table-cell">Buyer</th>
                        <th class="px-6 py-3 hidden lg:table-cell">Message</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3 hidden md:table-cell">Date</th>
                        <th class="px-6 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (count($offers) > 0): ?>
                        <?php foreach ($offers as $index => $offer): ?>
                            <?php 
                            $isLowerOffer = ($offer['total_offers_count'] > 1 && $offer['amount'] < $offer['max_offer_amount']);
                            $isHighestOffer = ($offer['amount'] == $offer['max_offer_amount'] && $offer['total_offers_count'] > 1);
                            ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <!-- # -->
                                <td class="px-6 py-4 font-semibold text-gray-600">
                                    <?= $offset + $index + 1 ?>
                                </td>

                                <!-- Listing -->
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <i class="fas fa-file-alt mr-2 text-blue-500"></i>
                                        <div>
                                            <div class="font-medium"><?= e($offer['listing_name']) ?></div>
                                            <?php if ($offer['total_offers_count'] > 1): ?>
                                                <div class="text-xs text-orange-600 mt-1">
                                                    <i class="fas fa-users mr-1"></i>
                                                    <?= $offer['total_offers_count'] ?> competing offers
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- Category -->
                                <td class="px-6 py-4">
                                    <i class="fas fa-folder mr-1 text-orange-500"></i>
                                    <?= e(ucfirst($offer['listing_category'] ?? 'N/A')) ?>
                                </td>

                                <!-- Asking Price -->
                                <td class="px-6 py-4 font-medium hidden md:table-cell">
                                    <i class="fas fa-dollar-sign mr-1 text-green-500"></i>
                                    $<?= number_format($offer['listing_price'] ?? 0, 2) ?>
                                </td>

                                <!-- Offer Amount -->
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <i class="fas fa-hand-holding-usd mr-1 text-blue-600"></i>
                                        <span class="font-semibold text-blue-600">
                                            $<?= number_format($offer['amount'], 2) ?>
                                        </span>
                                        <?php if ($isHighestOffer): ?>
                                            <span class="ml-2 text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">Highest</span>
                                        <?php elseif ($isLowerOffer): ?>
                                            <span class="ml-2 text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full">Lower</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Buyer -->
                                <td class="px-6 py-4 hidden sm:table-cell">
                                    <div>
                                        <div class="font-medium"><?= e($offer['buyer_name'] ?? 'User') ?></div>
                                        <div class="text-xs text-gray-500"><?= e($offer['buyer_email'] ?? '-') ?></div>
                                    </div>
                                </td>

                                <!-- Message -->
                                <td class="px-6 py-4 hidden lg:table-cell max-w-xs">
                                    <?php if (!empty($offer['message'])): ?>
                                        <div class="text-sm italic text-gray-700">
                                            <i class="fas fa-comment-dots text-blue-400 mr-1"></i>
                                            "<?= e(substr($offer['message'], 0, 80)) ?><?= strlen($offer['message']) > 80 ? '...' : '' ?>"
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400">
                                            <i class="fas fa-comment-slash mr-1"></i>No message
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status -->
                                <td class="px-6 py-4">
                                    <?php if ($offer['status'] === 'pending'): ?>
                                        <span class="bg-yellow-100 text-yellow-700 text-xs px-3 py-1 rounded-full font-medium">
                                            <i class="fas fa-clock mr-1"></i>Pending
                                        </span>
                                    <?php elseif ($offer['status'] === 'accepted'): ?>
                                        <span class="bg-green-100 text-green-700 text-xs px-3 py-1 rounded-full font-medium">
                                            <i class="fas fa-check-circle mr-1"></i>Accepted
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-gray-100 text-gray-600 text-xs px-3 py-1 rounded-full font-medium">
                                            <i class="fas fa-ban mr-1"></i><?= ucfirst(e($offer['status'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Date -->
                                <td class="px-6 py-4 text-xs text-gray-500 hidden md:table-cell">
                                    <i class="fas fa-calendar-day mr-1"></i>
                                    <?= date("d M Y", strtotime($offer['created_at'])) ?>
                                </td>

                                <!-- Actions -->
                                <td class="px-6 py-4">
                                    <?php if ($offer['status'] === 'pending'): ?>
                                        <div class="flex gap-2">
                                            <!-- Accept Button -->
                                            <a href="index.php?p=dashboard&page=offer_action&action=accept&id=<?= $offer['id'] ?>" 
                                               onclick="return confirm('Accept offer of $<?= number_format($offer['amount'], 2) ?> for &quot;<?= e($offer['listing_name']) ?>&quot;?')"
                                               class="text-green-600 hover:text-green-800 text-lg" 
                                               title="Accept this offer">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                            
                                            <!-- Reject Button -->
                                            <a href="index.php?p=dashboard&page=offer_action&action=reject&id=<?= $offer['id'] ?>" 
                                               onclick="return confirm('Reject offer of $<?= number_format($offer['amount'], 2) ?> for &quot;<?= e($offer['listing_name']) ?>&quot;?')"
                                               class="text-red-600 hover:text-red-800 text-lg" 
                                               title="Reject this offer">
                                                <i class="fas fa-times-circle"></i>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-sm">
                                            <i class="fas fa-check mr-1"></i>Done
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center py-16 text-gray-500">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-handshake-slash text-5xl text-gray-300 mb-4"></i>
                                    <h3 class="text-lg font-semibold text-gray-600 mb-2">No Offers Found</h3>
                                    <p class="text-gray-500 text-sm">
                                        <?php if ($search || $status): ?>
                                            No offers match your search criteria.
                                        <?php else: ?>
                                            You haven't received any offers yet.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing <?= $offset + 1 ?> to <?= min($offset + $perPage, $totalItems) ?> of <?= $totalItems ?> results
                    </div>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?p=dashboard&page=offers_simple&pg=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $status ? '&status=' . urlencode($status) : '' ?>" 
                               class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?p=dashboard&page=offers_simple&pg=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $status ? '&status=' . urlencode($status) : '' ?>" 
                               class="px-3 py-2 <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50' ?> rounded-lg">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?p=dashboard&page=offers_simple&pg=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $status ? '&status=' . urlencode($status) : '' ?>" 
                               class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Simple success/error message handling
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide flash messages after 5 seconds
    const alerts = document.querySelectorAll('.bg-green-50, .bg-red-50, .bg-yellow-50');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s ease-out';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        }, 5000);
    });
});
</script>

</body>
</html>