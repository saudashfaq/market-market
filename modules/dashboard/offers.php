<?php
require_once __DIR__ . "/../../config.php";
require_once __DIR__ . "/../../middlewares/auth.php";
require_once __DIR__ . '/../../includes/pagination_helper.php';

require_login();
$pdo = db();
$currentUser = current_user();
$logged_in_user_id = $currentUser['id'] ?? 0;


// Get pagination parameters
$page = getCurrentPage('pg');
$perPage = 10;

// Setup search and filter conditions
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$whereClause = 'WHERE o.seller_id = :uid';
$params = [':uid' => $logged_in_user_id];

if ($search) {
    $whereClause .= ' AND (l.name LIKE :search OR b.name LIKE :search OR b.email LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
if ($status) {
    $whereClause .= ' AND o.status = :status';
    $params[':status'] = $status;
}

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
    s.profile_pic AS seller_profile_pic,
    (SELECT MAX(amount) FROM offers WHERE listing_id = o.listing_id AND status = 'pending') AS max_offer_amount,
    (SELECT COUNT(*) FROM offers WHERE listing_id = o.listing_id AND status = 'pending') AS total_offers_count
  FROM offers o
  LEFT JOIN listings l ON o.listing_id = l.id
  LEFT JOIN users b ON o.user_id = b.id
  LEFT JOIN users s ON o.seller_id = s.id

  $whereClause
  ORDER BY o.created_at DESC
";

$countSql = "
  SELECT COUNT(*) as total
  FROM offers o
  LEFT JOIN listings l ON o.listing_id = l.id
  LEFT JOIN users b ON o.user_id = b.id
  LEFT JOIN users s ON o.seller_id = s.id
  $whereClause
";

// Get paginated data
$result = getCustomPaginationData($pdo, $sql, $countSql, $params, $page, $perPage);
$offers = $result['data'];
$pagination = $result['pagination'];

// Debug: Check first offer data (remove after testing)
if (!empty($offers) && isset($_GET['debug'])) {
    echo '<pre style="background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;">';
    echo "First Offer Data:\n";
    print_r($offers[0]);
    echo '</pre>';
}

?>

<div class="max-w-7xl mx-auto p-4 sm:p-6 md:p-8 bg-gradient-to-br from-gray-50 to-white min-h-screen">
    


    

    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 gap-3">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center gap-3">

            <i class="fas fa-handshake text-blue-600"></i> Offers Overview (<span class="total-offers-count"><?= $pagination['total_items'] ?></span>)

        </h1>
        <span class="text-sm text-gray-500 flex items-center">
            <i class="fas fa-calendar-alt mr-2"></i>Updated on <?= date("d M Y") ?>
        </span>
    </div>


    <!-- Search and Filter -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <input type="hidden" name="p" value="dashboard">
            <input type="hidden" name="page" value="offers">
            
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fa fa-search mr-1"></i>Search Offers
                </label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
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
                <a href="?p=dashboard&page=offers" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors flex items-center">
                    <i class="fa fa-times mr-2"></i>Clear
                </a>

            </div>
        </form>
    </div>


    <!-- Responsive Table -->
    <div class="overflow-x-auto bg-white shadow-xl rounded-2xl border border-gray-100">
        <table class="min-w-full text-xs sm:text-sm text-left text-gray-700">
            <thead class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white uppercase text-[10px] sm:text-xs">
                <tr>
                    <th class="px-4 sm:px-6 py-3">#</th>
                    <th class="px-4 sm:px-6 py-3">Listing</th>
                    <th class="px-4 sm:px-6 py-3">Category</th>
                    <th class="px-4 sm:px-6 py-3 hidden md:table-cell">Asking Price</th>
                    <th class="px-4 sm:px-6 py-3">Offer</th>
                    <th class="px-4 sm:px-6 py-3 hidden sm:table-cell">Buyer</th>
                    <th class="px-4 sm:px-6 py-3 hidden lg:table-cell">Message</th>
                    <th class="px-4 sm:px-6 py-3">Status</th>
                    <th class="px-4 sm:px-6 py-3 hidden md:table-cell">Date</th>
                    <th class="px-4 sm:px-6 py-3">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100">
                <?php if (count($offers) > 0): ?>
                    <?php foreach ($offers as $index => $offer): ?>
                        <?php 
                        // Check if this offer is lower than the highest offer for the same listing
                        $isLowerOffer = ($offer['total_offers_count'] > 1 && $offer['amount'] < $offer['max_offer_amount']);
                        $isHighestOffer = ($offer['amount'] == $offer['max_offer_amount'] && $offer['total_offers_count'] > 1);
                        $rowClass = $isLowerOffer ? 'lower-offer-row' : ($isHighestOffer ? 'highest-offer-glow' : '');
                        ?>
                        <tr class="hover:bg-gray-50 transition-all duration-150 <?= $rowClass ?>" data-record-id="<?= $offer['id'] ?>">
                            <!-- # -->
                            <td class="px-4 sm:px-6 py-3 font-semibold text-gray-600">
                                <?= $index + 1 ?>
                            </td>

                            <!-- Listing -->
                            <td class="px-4 sm:px-6 py-3">
                                <div class="flex items-center">
                                    <i class="fas fa-file-alt mr-2 text-blue-500 <?= $offer['total_offers_count'] > 1 ? 'multiple-offers-indicator' : '' ?>"></i>
                                    <div class="flex flex-col">
                                        <span class="font-medium truncate"><?= e($offer['listing_name']) ?></span>
                                        <?php if ($offer['total_offers_count'] > 1): ?>
                                            <span class="text-xs text-orange-600 flex items-center mt-1 font-medium">
                                                <i class="fas fa-users mr-1"></i>
                                                <?= $offer['total_offers_count'] ?> competing offers
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Category -->
                            <td class="px-4 sm:px-6 py-3 whitespace-nowrap">
                                <i class="fas fa-folder mr-1 text-orange-500"></i>
                                <?php 
                                // Debug: Show what we have
                                $category = $offer['listing_category'] ?? $offer['category'] ?? 'N/A';
                                echo e(ucfirst($category));
                                ?>
                            </td>

                            <!-- Asking Price -->
                            <td class="px-4 sm:px-6 py-3 text-gray-800 font-medium hidden md:table-cell">
                                <div class="flex items-center">
                                    <i class="fas fa-dollar-sign mr-1 text-green-500"></i>
                                    <?= e(number_format($offer['listing_price'], 2)) ?>
                                </div>
                            </td>

                            <!-- Offer -->
                            <td class="px-4 sm:px-6 py-3 whitespace-nowrap">
                                <?php if ($isLowerOffer): ?>
                                    <div class="flex items-center text-gray-500">
                                        <i class="fas fa-hand-holding-usd mr-1 text-gray-400"></i>
                                        <span class="font-medium line-through">$<?= e(number_format($offer['amount'], 2)) ?></span>
                                        <span class="ml-2 text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded-full">Lower</span>
                                    </div>
                                <?php else: ?>
                                    <div class="flex items-center text-blue-600 font-semibold">
                                        <i class="fas fa-hand-holding-usd mr-1"></i>
                                        <span>$<?= e(number_format($offer['amount'], 2)) ?></span>
                                        <?php if ($offer['amount'] == $offer['max_offer_amount'] && $offer['total_offers_count'] > 1): ?>
                                            <span class="ml-2 text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">Highest</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Buyer -->
                            <td class="px-4 sm:px-6 py-3 hidden sm:table-cell">
                                <div class="flex flex-col">
                                    <span class="font-medium text-gray-800"><?= e($offer['buyer_name'] ?? 'User') ?></span>
                                    <span class="text-[11px] text-gray-500"><?= e($offer['buyer_email'] ?? '-') ?></span>
                                </div>
                            </td>

                            <!-- Message -->
                            <td class="px-4 sm:px-6 py-3 text-gray-600 hidden lg:table-cell" style="max-width: 250px;">
                                <?php if (!empty($offer['message'])): ?>
                                    <div class="flex items-start gap-2">
                                        <i class="fas fa-comment-dots text-blue-400 flex-shrink-0 mt-1"></i>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm italic text-gray-700 line-clamp-2" title="<?= e($offer['message']) ?>">
                                                "<?= e($offer['message']) ?>"
                                            </p>
                                            <?php if (strlen($offer['message']) > 80): ?>
                                                <button onclick="showFullMessage(<?= $offer['id'] ?>, '<?= htmlspecialchars(addslashes($offer['message'])) ?>')" 
                                                        class="text-xs text-blue-600 hover:text-blue-800 mt-1 flex items-center">
                                                    <i class="fas fa-expand-alt mr-1"></i>Read more
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400"><i class="fas fa-comment-slash mr-1"></i>No message</span>
                                <?php endif; ?>
                            </td>

                            <!-- Status -->
                            <td class="px-4 sm:px-6 py-3">
                                <?php if ($offer['status'] === 'pending'): ?>
                                    <span class="bg-yellow-100 text-yellow-700 text-[11px] sm:text-xs px-3 py-1 rounded-full font-medium inline-flex items-center">
                                        <i class="fas fa-clock mr-1"></i>Pending
                                    </span>
                                <?php elseif ($offer['status'] === 'accepted'): ?>
                                    <span class="bg-green-100 text-green-700 text-[11px] sm:text-xs px-3 py-1 rounded-full font-medium inline-flex items-center">
                                        <i class="fas fa-check-circle mr-1"></i>Accepted
                                    </span>
                                <?php else: ?>
                                    <span class="bg-gray-100 text-gray-600 text-[11px] sm:text-xs px-3 py-1 rounded-full font-medium inline-flex items-center">
                                        <i class="fas fa-ban mr-1"></i><?= ucfirst(e($offer['status'])) ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Date -->
                            <td class="px-4 sm:px-6 py-3 text-xs text-gray-500 hidden md:table-cell whitespace-nowrap">
                                <i class="fas fa-calendar-day mr-1"></i>
                                <?= date("d M Y", strtotime($offer['created_at'])) ?>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 sm:px-6 py-3">
                                <?php if ($offer['status'] === 'pending'): ?>
                                    <?php if ($isLowerOffer): ?>
                                        <!-- Lower offer - disabled actions -->
                                        <div class="flex gap-2 sm:gap-3">
                                            <button onclick="showOfferDetails(<?= htmlspecialchars(json_encode($offer)) ?>)" 
                                                    class="text-blue-600 hover:text-blue-800 text-base sm:text-lg" 
                                                    title="View offer details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <span class="text-gray-400 text-base sm:text-lg opacity-40 cursor-not-allowed" title="Cannot accept lower offer">
                                                <i class="fas fa-check-circle"></i>
                                            </span>
                                            <button type="button"
                                               class="text-red-600 hover:text-red-800 text-base sm:text-lg border-none bg-transparent cursor-pointer" 
                                               title="Reject this lower offer"
                                               data-offer-id="<?= $offer['id'] ?>"
                                               data-listing-name="<?= htmlspecialchars($offer['listing_name']) ?>"
                                               data-amount="<?= $offer['amount'] ?>"
                                               onclick="handleRejectOfferFromData(this)">
                                                <i class="fas fa-times-circle"></i>
                                            </button>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">Lower offer</p>
                                    <?php else: ?>
                                        <!-- Normal or highest offer - enabled actions -->
                                        <div class="flex gap-2 sm:gap-3">
                                            <button onclick="showOfferDetails(<?= htmlspecialchars(json_encode($offer)) ?>)" 
                                                    class="text-blue-600 hover:text-blue-800 text-base sm:text-lg" 
                                                    title="View offer details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button"
                                               class="text-green-600 hover:text-green-800 text-base sm:text-lg border-none bg-transparent cursor-pointer" 
                                               title="Accept this offer"
                                               data-offer-id="<?= $offer['id'] ?>"
                                               data-listing-name="<?= htmlspecialchars($offer['listing_name']) ?>"
                                               data-amount="<?= $offer['amount'] ?>"
                                               data-total-offers="<?= $offer['total_offers_count'] ?>"
                                               onclick="handleAcceptOfferFromData(this)">
                                                <i class="fas fa-check-circle"></i>
                                            </button>
                                            <button type="button"
                                               class="text-red-600 hover:text-red-800 text-base sm:text-lg border-none bg-transparent cursor-pointer" 
                                               title="Reject this offer"
                                               data-offer-id="<?= $offer['id'] ?>"
                                               data-listing-name="<?= htmlspecialchars($offer['listing_name']) ?>"
                                               data-amount="<?= $offer['amount'] ?>"
                                               onclick="handleRejectOfferFromData(this)">
                                                <i class="fas fa-times-circle"></i>
                                            </button>
                                        </div>
                                        <?php if ($isHighestOffer): ?>
                                            <p class="text-xs text-green-600 mt-1 font-medium">
                                                <i class="fas fa-star mr-1"></i>Highest offer
                                            </p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs sm:text-sm flex items-center">
                                        <i class="fas fa-minus mr-1"></i>Done
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
                                <p class="text-gray-500 text-sm">You haven't received any offers yet.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        
        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200">
            <?php 
            $extraParams = ['p' => 'dashboard', 'page' => 'offers'];
            if ($search) $extraParams['search'] = $search;
            if ($status) $extraParams['status'] = $status;
            
            echo renderPagination($pagination, url('index.php'), $extraParams, 'pg'); 
            ?>
        </div>

    </div>
</div>


<!-- Load Popup JS -->
<script src="<?= BASE ?>public/js/popup.js"></script>

<!-- COMPREHENSIVE DEBUG AND FIX -->
<script>
console.log('🔧 Starting comprehensive debug...');

// Check if popup.js loaded
console.log('PopupManager available:', typeof PopupManager !== 'undefined');
console.log('showConfirm available:', typeof showConfirm !== 'undefined');
console.log('showPopup available:', typeof showPopup !== 'undefined');

// Test popup system immediately
setTimeout(function() {
    console.log('Testing popup system after 1 second...');
    console.log('showConfirm type:', typeof showConfirm);
    
    if (typeof showConfirm !== 'undefined') {
        console.log('✅ Popup system is ready');
    } else {
        console.log('❌ Popup system not ready');
    }
}, 1000);

// Simple test function
function testPopup() {
    console.log('Testing popup...');
    if (typeof showConfirm !== 'undefined') {
        showConfirm('Test popup working?', {
            title: 'Test',
            confirmText: 'Yes',
            cancelText: 'No'
        }).then(result => {
            console.log('Test result:', result);
            alert('Test result: ' + result);
        });
    } else {
        alert('showConfirm not available');
    }
}

// Handle Accept Offer
function handleAcceptOffer(offerId, listingName, amount, totalOffers) {
    console.log('🎯 handleAcceptOffer called:', { offerId, listingName, amount, totalOffers });
    
    if (typeof showConfirm !== 'undefined') {
        console.log('Using popup system');
        
        const formattedAmount = parseFloat(amount).toLocaleString();
        let message = `Accept offer of $${formattedAmount} for "${listingName}"?`;
        
        if (totalOffers > 1) {
            message += `\n\nThis will automatically reject all other ${totalOffers - 1} competing offers.`;
        }
        
        showConfirm(message, {
            title: 'Accept Offer',
            confirmText: 'Yes, Accept',
            cancelText: 'Cancel'
        }).then(function(result) {
            console.log('Popup result:', result);
            if (result === true) {
                console.log('Processing accept...');
                
                // Show loading popup
                if (typeof showSuccess !== 'undefined') {
                    showSuccess('Processing your request...', {
                        title: 'Please Wait',
                        autoClose: false
                    });
                }
                
                // Send AJAX request instead of redirect
                fetch(`index.php?p=dashboard&page=offer_action&action=accept&id=${offerId}`, {
                    method: 'GET',
                    credentials: 'same-origin'
                })
                .then(response => response.text())
                .then(data => {
                    console.log('Accept response:', data);
                    
                    // Show success message
                    if (typeof showSuccess !== 'undefined') {
                        showSuccess(`Offer of $${formattedAmount} has been accepted successfully!`, {
                            title: 'Offer Accepted',
                            autoClose: true,
                            autoCloseTime: 3000
                        });
                    }
                    
                    // Reload page after 2 seconds to show updated data
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                })
                .catch(error => {
                    console.error('Accept error:', error);
                    if (typeof showError !== 'undefined') {
                        showError('Error processing request. Please try again.', {
                            title: 'Error'
                        });
                    }
                });
            } else {
                console.log('User cancelled');
            }
        });
    } else {
        console.log('Popup system not available');
        const formattedAmount = parseFloat(amount).toLocaleString();
        if (confirm(`Accept offer of $${formattedAmount} for "${listingName}"?`)) {
            window.location.href = `index.php?p=dashboard&page=offer_action&action=accept&id=${offerId}`;
        }
    }
}

// Handle Reject Offer
function handleRejectOffer(offerId, listingName, amount) {
    console.log('🎯 handleRejectOffer called:', { offerId, listingName, amount });
    
    if (typeof showConfirm !== 'undefined') {
        console.log('Using popup system');
        
        const formattedAmount = parseFloat(amount).toLocaleString();
        const message = `Reject offer of $${formattedAmount} for "${listingName}"?`;
        
        showConfirm(message, {
            title: 'Reject Offer',
            confirmText: 'Yes, Reject',
            cancelText: 'Cancel'
        }).then(function(result) {
            console.log('Popup result:', result);
            if (result === true) {
                console.log('Processing reject...');
                
                // Show loading popup
                if (typeof showWarning !== 'undefined') {
                    showWarning('Processing your request...', {
                        title: 'Please Wait',
                        autoClose: false
                    });
                }
                
                // Send AJAX request instead of redirect
                fetch(`index.php?p=dashboard&page=offer_action&action=reject&id=${offerId}`, {
                    method: 'GET',
                    credentials: 'same-origin'
                })
                .then(response => response.text())
                .then(data => {
                    console.log('Reject response:', data);
                    
                    // Show success message
                    if (typeof showWarning !== 'undefined') {
                        showWarning(`Offer of $${formattedAmount} has been rejected.`, {
                            title: 'Offer Rejected',
                            autoClose: true,
                            autoCloseTime: 3000
                        });
                    }
                    
                    // Reload page after 2 seconds to show updated data
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                })
                .catch(error => {
                    console.error('Reject error:', error);
                    if (typeof showError !== 'undefined') {
                        showError('Error processing request. Please try again.', {
                            title: 'Error'
                        });
                    }
                });
            } else {
                console.log('User cancelled');
            }
        });
    } else {
        console.log('Popup system not available');
        const formattedAmount = parseFloat(amount).toLocaleString();
        if (confirm(`Reject offer of $${formattedAmount} for "${listingName}"?`)) {
            window.location.href = `index.php?p=dashboard&page=offer_action&action=reject&id=${offerId}`;
        }
    }
}

// New functions that read data from button attributes (safer approach)
function handleAcceptOfferFromData(button) {
    console.log('🎯 Accept button clicked from table');
    
    const offerId = button.getAttribute('data-offer-id');
    const listingName = button.getAttribute('data-listing-name');
    const amount = parseFloat(button.getAttribute('data-amount'));
    const totalOffers = parseInt(button.getAttribute('data-total-offers')) || 1;
    
    console.log('Data from button:', { offerId, listingName, amount, totalOffers });
    
    // Call the main function
    handleAcceptOffer(offerId, listingName, amount, totalOffers);
}

function handleRejectOfferFromData(button) {
    console.log('🎯 Reject button clicked from table');
    
    const offerId = button.getAttribute('data-offer-id');
    const listingName = button.getAttribute('data-listing-name');
    const amount = parseFloat(button.getAttribute('data-amount'));
    
    console.log('Data from button:', { offerId, listingName, amount });
    
    // Call the main function
    handleRejectOffer(offerId, listingName, amount);
}

// Make functions global
window.handleAcceptOffer = handleAcceptOffer;
window.handleRejectOffer = handleRejectOffer;
window.handleAcceptOfferFromData = handleAcceptOfferFromData;
window.handleRejectOfferFromData = handleRejectOfferFromData;
window.testPopup = testPopup;

console.log('✅ Debug functions loaded');
console.log('handleAcceptOffer available:', typeof handleAcceptOffer !== 'undefined');
console.log('handleRejectOffer available:', typeof handleRejectOffer !== 'undefined');
console.log('handleAcceptOfferFromData available:', typeof handleAcceptOfferFromData !== 'undefined');
console.log('handleRejectOfferFromData available:', typeof handleRejectOfferFromData !== 'undefined');
</script>

<!-- DIRECT FIX FOR BUTTONS -->
<script>
console.log('🔧 Direct button fix loading...');

// Simple functions that WILL work
function confirmAcceptOffer(event, offerId, listingName, amount, totalOffers) {
    console.log('✅ Accept clicked:', offerId, listingName, amount);
    
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    const message = `Accept offer of $${amount.toLocaleString()} for "${listingName}"?`;
    
    if (confirm(message)) {
        console.log('User confirmed, redirecting...');
        window.location.href = `index.php?p=dashboard&page=offer_action&action=accept&id=${offerId}`;
    }
    
    return false;
}

function confirmRejectOffer(event, offerId, listingName, amount) {
    console.log('✅ Reject clicked:', offerId, listingName, amount);
    
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    const message = `Reject offer of $${amount.toLocaleString()} for "${listingName}"?`;
    
    if (confirm(message)) {
        console.log('User confirmed, redirecting...');
        window.location.href = `index.php?p=dashboard&page=offer_action&action=reject&id=${offerId}`;
    }
    
    return false;
}

// Make sure functions are global
window.confirmAcceptOffer = confirmAcceptOffer;
window.confirmRejectOffer = confirmRejectOffer;

console.log('✅ Button functions loaded');

// Test if functions work
setTimeout(() => {
    console.log('Testing functions...');
    console.log('confirmAcceptOffer available:', typeof confirmAcceptOffer);
    console.log('confirmRejectOffer available:', typeof confirmRejectOffer);
}, 1000);
</script>

<!-- Fallback Popup System (in case main popup.js fails) -->
<script>
// Fallback popup system if main one fails to load
if (typeof showConfirm === 'undefined') {
    console.warn('⚠️ Main popup system not loaded, using fallback');
    
    window.showConfirm = function(message, options = {}) {
        return new Promise((resolve) => {
            const result = confirm(message);
            resolve(result);
        });
    };
    
    window.showSuccess = function(message, options = {}) {
        alert('✅ ' + message);
    };
    
    window.showWarning = function(message, options = {}) {
        alert('⚠️ ' + message);
    };
    
    window.showError = function(message, options = {}) {
        alert('❌ ' + message);
    };
}

// MAIN FUNCTIONS FOR ACCEPT/REJECT BUTTONS
function confirmAcceptOffer(event, offerId, listingName, amount, totalOffers) {
    console.log('🎯 Accept button clicked:', { offerId, listingName, amount, totalOffers });
    
    if (event && event.preventDefault) {
        event.preventDefault();
    }
    
    // Simple fallback first
    if (typeof showConfirm === 'undefined') {
        console.log('Using simple confirm dialog');
        const confirmed = confirm(`Accept offer of $${amount.toLocaleString()} for "${listingName}"?`);
        if (confirmed) {
            window.location.href = `index.php?p=dashboard&page=offer_action&action=accept&id=${offerId}`;
        }
        return;
    }
    
    // Use popup system
    console.log('Using popup system');
    const formattedAmount = parseFloat(amount).toLocaleString();
    let message = `Accept offer of $${formattedAmount} for "${listingName}"?`;
    
    if (totalOffers > 1) {
        message += `\n\nThis will automatically reject all other ${totalOffers - 1} competing offers for this listing.`;
    }
    
    showConfirm(message, {
        title: 'Accept Offer',
        confirmText: 'Yes, Accept',
        cancelText: 'Cancel'
    }).then(function(result) {
        console.log('Popup result:', result);
        if (result === true) {
            console.log('Redirecting to accept...');
            window.location.href = `index.php?p=dashboard&page=offer_action&action=accept&id=${offerId}`;
        }
    }).catch(function(error) {
        console.error('Popup error:', error);
        // Fallback
        if (confirm('Error with popup. Accept offer anyway?')) {
            window.location.href = `index.php?p=dashboard&page=offer_action&action=accept&id=${offerId}`;
        }
    });
}

function confirmRejectOffer(event, offerId, listingName, amount) {
    console.log('🎯 Reject button clicked:', { offerId, listingName, amount });
    
    if (event && event.preventDefault) {
        event.preventDefault();
    }
    
    // Simple fallback first
    if (typeof showConfirm === 'undefined') {
        console.log('Using simple confirm dialog');
        const confirmed = confirm(`Reject offer of $${amount.toLocaleString()} for "${listingName}"?`);
        if (confirmed) {
            window.location.href = `index.php?p=dashboard&page=offer_action&action=reject&id=${offerId}`;
        }
        return;
    }
    
    // Use popup system
    console.log('Using popup system');
    const formattedAmount = parseFloat(amount).toLocaleString();
    const message = `Reject offer of $${formattedAmount} for "${listingName}"?`;
    
    showConfirm(message, {
        title: 'Reject Offer',
        confirmText: 'Yes, Reject',
        cancelText: 'Cancel'
    }).then(function(result) {
        console.log('Popup result:', result);
        if (result === true) {
            console.log('Redirecting to reject...');
            window.location.href = `index.php?p=dashboard&page=offer_action&action=reject&id=${offerId}`;
        }
    }).catch(function(error) {
        console.error('Popup error:', error);
        // Fallback
        if (confirm('Error with popup. Reject offer anyway?')) {
            window.location.href = `index.php?p=dashboard&page=offer_action&action=reject&id=${offerId}`;
        }
    });
}
</script>

<!-- Inline Popup System -->
<style>
.popup-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
}

.popup-overlay.show {
  opacity: 1;
  visibility: visible;
}

.popup-modal {
  background: white;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  max-width: 400px;
  width: 90%;
  transform: scale(0.7);
  transition: all 0.3s ease;
}

.popup-overlay.show .popup-modal {
  transform: scale(1);
}

.popup-header {
  padding: 20px 20px 10px;
  border-bottom: 1px solid #eee;
  display: flex;
  align-items: center;
  gap: 10px;
}

.popup-icon {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
  font-size: 14px;
}

.popup-icon.warning { background: #ffc107; color: #333; }
.popup-icon.success { background: #28a745; }
.popup-icon.error { background: #dc3545; }

.popup-title {
  font-weight: 600;
  font-size: 16px;
  color: #333;
}

.popup-body {
  padding: 15px 20px;
  color: #666;
  line-height: 1.5;
  white-space: pre-line;
}

.popup-footer {
  padding: 10px 20px 20px;
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

.popup-btn {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s ease;
}

.popup-btn-primary {
  background: #007bff;
  color: white;
}

.popup-btn-primary:hover {
  background: #0056b3;
}

.popup-btn-secondary {
  background: #6c757d;
  color: white;
}

.popup-btn-secondary:hover {
  background: #545b62;
}
</style>

<script>
console.log('🚀 Loading inline popup system...');

// Simple inline popup system
window.showConfirm = function(message, options = {}) {
  return new Promise((resolve) => {
    const defaults = {
      title: 'Confirm',
      confirmText: 'OK',
      cancelText: 'Cancel'
    };
    const config = { ...defaults, ...options };
    
    // Create overlay
    const overlay = document.createElement('div');
    overlay.className = 'popup-overlay';
    
    // Create modal
    const modal = document.createElement('div');
    modal.className = 'popup-modal';
    modal.innerHTML = `
      <div class="popup-header">
        <div class="popup-icon warning">!</div>
        <div class="popup-title">${config.title}</div>
      </div>
      <div class="popup-body">${message}</div>
      <div class="popup-footer">
        <button class="popup-btn popup-btn-secondary popup-cancel">${config.cancelText}</button>
        <button class="popup-btn popup-btn-primary popup-confirm">${config.confirmText}</button>
      </div>
    `;
    
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    
    // Event listeners
    const confirmBtn = modal.querySelector('.popup-confirm');
    const cancelBtn = modal.querySelector('.popup-cancel');
    
    confirmBtn.addEventListener('click', () => {
      overlay.classList.remove('show');
      setTimeout(() => {
        overlay.remove();
        resolve(true);
      }, 300);
    });
    
    cancelBtn.addEventListener('click', () => {
      overlay.classList.remove('show');
      setTimeout(() => {
        overlay.remove();
        resolve(false);
      }, 300);
    });
    
    // Close on overlay click
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        overlay.classList.remove('show');
        setTimeout(() => {
          overlay.remove();
          resolve(false);
        }, 300);
      }
    });
    
    // Show animation
    setTimeout(() => {
      overlay.classList.add('show');
    }, 10);
  });
};

window.showSuccess = function(message, options = {}) {
  return showAlert(message, 'success', options);
};

window.showWarning = function(message, options = {}) {
  return showAlert(message, 'warning', options);
};

window.showError = function(message, options = {}) {
  return showAlert(message, 'error', options);
};

function showAlert(message, type = 'success', options = {}) {
  const defaults = {
    title: type.charAt(0).toUpperCase() + type.slice(1),
    autoClose: false,
    autoCloseTime: 3000
  };
  const config = { ...defaults, ...options };
  
  // Create overlay
  const overlay = document.createElement('div');
  overlay.className = 'popup-overlay';
  
  // Create modal
  const modal = document.createElement('div');
  modal.className = 'popup-modal';
  modal.innerHTML = `
    <div class="popup-header">
      <div class="popup-icon ${type}">${type === 'success' ? '✓' : type === 'warning' ? '!' : '✕'}</div>
      <div class="popup-title">${config.title}</div>
    </div>
    <div class="popup-body">${message}</div>
    <div class="popup-footer">
      <button class="popup-btn popup-btn-primary popup-ok">OK</button>
    </div>
  `;
  
  overlay.appendChild(modal);
  document.body.appendChild(overlay);
  
  // Event listener
  const okBtn = modal.querySelector('.popup-ok');
  okBtn.addEventListener('click', () => {
    overlay.classList.remove('show');
    setTimeout(() => {
      overlay.remove();
    }, 300);
  });
  
  // Close on overlay click
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) {
      overlay.classList.remove('show');
      setTimeout(() => {
        overlay.remove();
      }, 300);
    }
  });
  
  // Show animation
  setTimeout(() => {
    overlay.classList.add('show');
  }, 10);
  
  // Auto close
  if (config.autoClose) {
    setTimeout(() => {
      if (document.body.contains(overlay)) {
        overlay.classList.remove('show');
        setTimeout(() => {
          overlay.remove();
        }, 300);
      }
    }, config.autoCloseTime);
  }
}

console.log('✅ Inline popup system loaded successfully');

// Define functions immediately to ensure they're available
function confirmAcceptOffer(event, offerId, listingName, amount, totalOffers) {
  console.log('🎯 confirmAcceptOffer called with:', { event, offerId, listingName, amount, totalOffers });
  
  if (event && event.preventDefault) {
    event.preventDefault();
  }
  
  console.log('Accept offer confirmation triggered:', { offerId, listingName, amount, totalOffers });
  
  // Check popup system
  if (typeof showConfirm === 'undefined') {
    // Fallback to native confirm
    const confirmed = confirm(`Accept offer of $${amount.toLocaleString()} for "${listingName}"?`);
    if (confirmed) {
      window.location.href = `index.php?p=dashboard&page=offer_action&action=accept&id=${offerId}`;
    }
    return;
  }
  
  // Create confirmation message
  const formattedAmount = parseFloat(amount).toLocaleString();
  let message = `Accept offer of $${formattedAmount} for "${listingName}"?`;
  
  if (totalOffers > 1) {
    message += `\n\nThis will automatically reject all other ${totalOffers - 1} competing offers for this listing.`;
  }
  
  // Show confirmation popup
  showConfirm(message, {
    title: 'Accept Offer',
    confirmText: 'Yes, Accept',
    cancelText: 'Cancel'
  }).then(function(result) {
    console.log('Accept offer confirmation result:', result);
    if (result === true) {
      console.log('User confirmed accept - proceeding...');
      
      // Show loading message
      if (typeof showSuccess !== 'undefined') {
        showSuccess('Accepting offer...', {
          title: 'Processing',
          showConfirm: false,
          autoClose: true,
          autoCloseTime: 2000
        });
      }
      
      // Redirect to accept action after short delay
      setTimeout(function() {
        window.location.href = `index.php?p=dashboard&page=offer_action&action=accept&id=${offerId}`;
      }, 500);
    } else {
      console.log('Accept offer cancelled by user');
    }
  }).catch(function(error) {
    console.error('Accept offer confirmation error:', error);
    // Fallback - still allow accept on error
    if (confirm('Popup system error. Accept offer anyway?')) {
      window.location.href = `index.php?p=dashboard&page=offer_action&action=accept&id=${offerId}`;
    }
  });
}

function confirmRejectOffer(event, offerId, listingName, amount) {
  console.log('🎯 confirmRejectOffer called with:', { event, offerId, listingName, amount });
  
  if (event && event.preventDefault) {
    event.preventDefault();
  }
  
  console.log('Reject offer confirmation triggered:', { offerId, listingName, amount });
  
  // Check popup system
  if (typeof showConfirm === 'undefined') {
    // Fallback to native confirm
    const confirmed = confirm(`Reject offer of $${amount.toLocaleString()} for "${listingName}"?`);
    if (confirmed) {
      window.location.href = `index.php?p=dashboard&page=offer_action&action=reject&id=${offerId}`;
    }
    return;
  }
  
  // Create confirmation message
  const formattedAmount = parseFloat(amount).toLocaleString();
  const message = `Reject offer of $${formattedAmount} for "${listingName}"?`;
  
  // Show confirmation popup
  showConfirm(message, {
    title: 'Reject Offer',
    confirmText: 'Yes, Reject',
    cancelText: 'Cancel'
  }).then(function(result) {
    console.log('Reject offer confirmation result:', result);
    if (result === true) {
      console.log('User confirmed reject - proceeding...');
      
      // Show loading message
      if (typeof showWarning !== 'undefined') {
        showWarning('Rejecting offer...', {
          title: 'Processing',
          showConfirm: false,
          autoClose: true,
          autoCloseTime: 2000
        });
      }
      
      // Redirect to reject action after short delay
      setTimeout(function() {
        window.location.href = `index.php?p=dashboard&page=offer_action&action=reject&id=${offerId}`;
      }, 500);
    } else {
      console.log('Reject offer cancelled by user');
    }
  }).catch(function(error) {
    console.error('Reject offer confirmation error:', error);
    // Fallback - still allow reject on error
    if (confirm('Popup system error. Reject offer anyway?')) {
      window.location.href = `index.php?p=dashboard&page=offer_action&action=reject&id=${offerId}`;
    }
  });
}

// Ensure functions are available globally
window.confirmAcceptOffer = confirmAcceptOffer;
window.confirmRejectOffer = confirmRejectOffer;
</script>

<style>
/* Enhanced styling for offer comparison */
.lower-offer-row {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    opacity: 0.7;
    transition: all 0.3s ease;
}

.lower-offer-row:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.highest-offer-glow {
    box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.2);
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
}

.offer-badge {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

.multiple-offers-indicator {
    position: relative;
}

.multiple-offers-indicator::after {
    content: '';
    position: absolute;
    top: -2px;
    right: -2px;
    width: 8px;
    height: 8px;
    background: #f59e0b;
    border-radius: 50%;
    animation: blink 1.5s infinite;
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0; }
}

/* Line clamp for message truncation */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
}
</style>

<!-- Message Modal -->
<div id="messageModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[80vh] overflow-hidden">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-comment-dots text-blue-500 mr-3"></i>
                Buyer's Message
            </h3>
            <button onclick="closeMessageModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto max-h-[60vh]">
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg">
                <p id="modalMessageContent" class="text-gray-700 whitespace-pre-wrap leading-relaxed"></p>
            </div>
        </div>
        <div class="p-6 border-t border-gray-200 bg-gray-50 flex justify-end">
            <button onclick="closeMessageModal()" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Offer Details Modal -->
<div id="offerDetailsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between bg-gradient-to-r from-blue-600 to-indigo-600">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-file-invoice-dollar mr-3"></i>
                Offer Details
            </h3>
            <button onclick="closeOfferDetailsModal()" class="text-white hover:text-gray-200 transition-colors">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto max-h-[70vh]">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Listing Info -->
                <div class="bg-gray-50 rounded-xl p-5 border border-gray-200">
                    <h4 class="font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-file-alt text-blue-500 mr-2"></i>
                        Listing Information
                    </h4>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Name:</span>
                            <span class="font-medium text-gray-900" id="detailListingName">-</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Category:</span>
                            <span class="font-medium text-gray-900" id="detailCategory">-</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Asking Price:</span>
                            <span class="font-bold text-green-600" id="detailAskingPrice">-</span>
                        </div>
                    </div>
                </div>

                <!-- Buyer Info -->
                <div class="bg-gray-50 rounded-xl p-5 border border-gray-200">
                    <h4 class="font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-user text-purple-500 mr-2"></i>
                        Buyer Information
                    </h4>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Name:</span>
                            <span class="font-medium text-gray-900" id="detailBuyerName">-</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Email:</span>
                            <span class="font-medium text-gray-900 text-xs" id="detailBuyerEmail">-</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Offer Amount -->
            <div class="mt-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6 border-2 border-blue-200">
                <div class="text-center">
                    <p class="text-sm text-gray-600 mb-2">Offer Amount</p>
                    <p class="text-4xl font-bold text-blue-600" id="detailOfferAmount">$0</p>
                    <div class="mt-3 flex items-center justify-center gap-2" id="detailOfferBadge"></div>
                </div>
            </div>

            <!-- Message -->
            <div class="mt-6 bg-white rounded-xl p-5 border border-gray-200">
                <h4 class="font-semibold text-gray-900 mb-3 flex items-center">
                    <i class="fas fa-comment-dots text-blue-500 mr-2"></i>
                    Buyer's Message
                </h4>
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg">
                    <p id="detailMessage" class="text-gray-700 whitespace-pre-wrap leading-relaxed italic">-</p>
                </div>
            </div>

            <!-- Date & Status -->
            <div class="mt-6 grid grid-cols-2 gap-4">
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 text-center">
                    <i class="fas fa-calendar-alt text-gray-500 text-xl mb-2"></i>
                    <p class="text-xs text-gray-600 mb-1">Offer Date</p>
                    <p class="font-semibold text-gray-900" id="detailDate">-</p>
                </div>
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 text-center">
                    <i class="fas fa-info-circle text-gray-500 text-xl mb-2"></i>
                    <p class="text-xs text-gray-600 mb-1">Status</p>
                    <p class="font-semibold" id="detailStatus">-</p>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function showFullMessage(offerId, message) {
    document.getElementById('modalMessageContent').textContent = message;
    document.getElementById('messageModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeMessageModal() {
    document.getElementById('messageModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showOfferDetails(offer) {
    // Populate listing info
    document.getElementById('detailListingName').textContent = offer.listing_name || 'N/A';
    document.getElementById('detailCategory').textContent = offer.listing_category ? offer.listing_category.charAt(0).toUpperCase() + offer.listing_category.slice(1) : 'N/A';
    document.getElementById('detailAskingPrice').textContent = '$' + parseFloat(offer.listing_price || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
    
    // Populate buyer info
    document.getElementById('detailBuyerName').textContent = offer.buyer_name || 'N/A';
    document.getElementById('detailBuyerEmail').textContent = offer.buyer_email || 'N/A';
    
    // Populate offer amount
    document.getElementById('detailOfferAmount').textContent = '$' + parseFloat(offer.amount).toLocaleString('en-US', {minimumFractionDigits: 2});
    
    // Show badge if highest or lower offer
    const badgeContainer = document.getElementById('detailOfferBadge');
    if (offer.total_offers_count > 1) {
        if (offer.amount == offer.max_offer_amount) {
            badgeContainer.innerHTML = '<span class="bg-green-100 text-green-700 text-xs px-3 py-1 rounded-full font-medium"><i class="fas fa-star mr-1"></i>Highest Offer</span>';
        } else if (offer.amount < offer.max_offer_amount) {
            badgeContainer.innerHTML = '<span class="bg-gray-100 text-gray-600 text-xs px-3 py-1 rounded-full font-medium"><i class="fas fa-arrow-down mr-1"></i>Lower Offer</span>';
        }
    } else {
        badgeContainer.innerHTML = '';
    }
    
    // Populate message
    document.getElementById('detailMessage').textContent = offer.message || 'No message provided';
    
    // Populate date
    const date = new Date(offer.created_at);
    document.getElementById('detailDate').textContent = date.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
    
    // Populate status
    const statusEl = document.getElementById('detailStatus');
    const statusConfig = {
        'pending': { color: 'text-yellow-700', text: 'Pending' },
        'accepted': { color: 'text-green-700', text: 'Accepted' },
        'rejected': { color: 'text-red-700', text: 'Rejected' }
    };
    const status = statusConfig[offer.status] || { color: 'text-gray-700', text: offer.status };
    statusEl.className = 'font-semibold ' + status.color;
    statusEl.textContent = status.text;
    
    // Show modal
    document.getElementById('offerDetailsModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeOfferDetailsModal() {
    document.getElementById('offerDetailsModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMessageModal();
        closeOfferDetailsModal();
    }
});

// Close modals when clicking outside
document.getElementById('messageModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeMessageModal();
    }
});

document.getElementById('offerDetailsModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeOfferDetailsModal();
    }
});
</script>



<script>
document.addEventListener('DOMContentLoaded', function() {
  console.log('🚀 Offers page initialization started');
  
  // Immediate diagnostic check
  console.log('=== DIAGNOSTIC CHECK ===');
  console.log('showConfirm available:', typeof showConfirm !== 'undefined');
  console.log('confirmAcceptOffer available:', typeof confirmAcceptOffer !== 'undefined');
  console.log('confirmRejectOffer available:', typeof confirmRejectOffer !== 'undefined');
  console.log('popup.js loaded:', typeof PopupManager !== 'undefined');
  console.log('========================');
  
  // Check popup system first
  setTimeout(() => {
    console.log('🔍 Checking popup system...');
    const isWorking = checkPopupSystem();
    if (!isWorking) {
      console.error('❌ Popup system not working! Check console for errors.');
      // Show a test popup to verify fallback
      if (typeof showConfirm !== 'undefined') {
        console.log('✅ Fallback popup system is available');
      }
    } else {
      console.log('✅ Popup system is working correctly');
    }
  }, 1000);
  
  // Define BASE constant globally
  const BASE = "<?php echo BASE; ?>";
  console.log('🔧 BASE constant defined:', BASE);
  
  console.log('🚀 Offers page polling initialization started');
  
  // Wait for global polling manager to be available
  function initOffersPolling() {
    if (window.globalPollingManager) {
      console.log('✅ Global polling manager found, adding offers callbacks');
      setupOffersPolling();
    } else {
      console.log('⏳ Waiting for global polling manager...');
      setTimeout(initOffersPolling, 1000);
    }
  }
  
  function setupOffersPolling() {
  
  // Test manual API call first
  console.log('🔍 Testing manual API call...');
  const apiUrl = '<?= BASE ?>../includes/polling_integration.php';
  console.log('📍 API URL:', apiUrl);
  
  fetch(apiUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ offers: '1970-01-01 00:00:00' })
  })
  .then(response => response.json())
  .then(data => {
    console.log('📡 Manual API test result:', data);
    if (data.success && data.data.offers) {
      console.log(`📊 Found ${data.data.offers.length} offers in manual test`);
      console.log('📦 Sample offer data:', data.data.offers[0]);
    }
  })
  .catch(error => {
    console.error('❌ Manual API test failed:', error);
  });
  
    // Add offers specific callbacks to global polling manager
    window.globalPollingManager.renderCallbacks = {
      ...window.globalPollingManager.renderCallbacks,
      offers: (newOffers) => {
      console.log('✅ Offers callback triggered!');
      console.log('Processing new offers:', newOffers);
      console.log('Number of new offers:', newOffers.length);
      
      // Get current user ID for filtering
      const currentUserId = <?= $logged_in_user_id ?>;
      
      // Filter offers where current user is the seller
      const myOffers = newOffers.filter(offer => offer.seller_id == currentUserId);
      console.log('👤 My offers (as seller) filtered:', myOffers.length);
      
      if (myOffers.length === 0) {
        console.log('ℹ️ No new offers for current seller');
        return;
      }
      
      // Debug: Check what data we have in the first offer
      if (myOffers.length > 0) {
        console.log('🔍 First offer data:', myOffers[0]);
        console.log('📁 Category fields:', {
          listing_category: myOffers[0].listing_category,
          type: myOffers[0].type,
          category: myOffers[0].category
        });
      }
      
      // Use generic duplicate checker
      const uniqueOffers = window.PollingUIHelpers.checkForDuplicates('table', myOffers, 'id');
      console.log('Unique offers after duplicate check:', uniqueOffers.length);
      
      // Process automatic offer logic first
      processAutomaticOfferLogic(uniqueOffers);
      
      uniqueOffers.forEach(offer => {
        // Add new offer to table with proper styling (highest/lower badges)
        addOfferToTable(offer);
        
        // Update existing offers for the same listing
        updateExistingOffersForListing(offer.listing_id);
        
        // Show brief success notification
        window.PollingUIHelpers.showBriefNotification(`New offer: $${parseFloat(offer.amount).toLocaleString()} for "${offer.listing_name}"`, 'success');
        
        // Update stats using generic updater
        window.PollingUIHelpers.updateStatsCounter('.total-offers-count', 1); // Total offers count
        if (offer.status === 'pending') {
          window.PollingUIHelpers.updateStatsCounter('.grid .bg-white:first-child .text-2xl', 1); // Pending count
        }
      });
    }
    };
  }
  
  // Start offers polling
  setTimeout(initOffersPolling, 2000);
  
  // Function to update existing offers when a new offer arrives
  function updateExistingOffersForListing(listingId) {
    console.log('🔄 Updating existing offers for listing:', listingId);
    
    // Find all rows for this listing
    const allRows = document.querySelectorAll('table tbody tr');
    const listingRows = [];
    
    allRows.forEach(row => {
      const listingCell = row.querySelector('td:nth-child(2)');
      if (listingCell) {
        // Extract listing name or check data attribute
        const listingName = listingCell.textContent.trim();
        // We need to match by listing - for now we'll reload the row data
        listingRows.push(row);
      }
    });
    
    console.log('📋 Found rows to potentially update:', listingRows.length);
    
    // For now, we'll just log - full implementation would require fetching updated offer data
    // The server-side auto-processing should handle most cases
  }
  
  // Custom function to add offer to table with proper styling
  function addOfferToTable(offer) {
    const tbody = document.querySelector('table tbody');
    if (!tbody) return;
    
    // Check if this is highest or lower offer
    const isLowerOffer = (offer.total_offers_count > 1 && offer.amount < offer.max_offer_amount);
    const isHighestOffer = (offer.amount == offer.max_offer_amount && offer.total_offers_count > 1);
    const rowClass = isLowerOffer ? 'lower-offer-row' : (isHighestOffer ? 'highest-offer-glow' : '');
    
    const newRow = document.createElement('tr');
    newRow.className = `hover:bg-gray-50 transition-all animate-fade-in ${rowClass}`;
    newRow.dataset.recordId = offer.id;
    newRow.style.backgroundColor = '#f0f9ff'; // Light blue background for new items
    
    // Use custom renderer
    newRow.innerHTML = renderOfferRow(offer);
    
    // Add to top of table
    tbody.insertBefore(newRow, tbody.firstChild);
    
    // Remove highlight after 5 seconds
    setTimeout(() => {
      newRow.style.backgroundColor = '';
    }, 5000);
  }
  
  // Custom renderer for offer rows
  function renderOfferRow(offer) {
    // Create status configuration
    const statusConfig = {
      'pending': { color: 'bg-yellow-100 text-yellow-700', icon: 'fas fa-clock' },
      'accepted': { color: 'bg-green-100 text-green-700', icon: 'fas fa-check-circle' },
      'rejected': { color: 'bg-gray-100 text-gray-600', icon: 'fas fa-ban' }
    };
    const statusInfo = statusConfig[offer.status] || { color: 'bg-gray-100 text-gray-600', icon: 'fas fa-question-circle' };
    
    // Check if this is highest or lower offer
    const isLowerOffer = (offer.total_offers_count > 1 && offer.amount < offer.max_offer_amount);
    const isHighestOffer = (offer.amount == offer.max_offer_amount && offer.total_offers_count > 1);
    const rowClass = isLowerOffer ? 'lower-offer-row' : (isHighestOffer ? 'highest-offer-glow' : '');
    
    // Format date
    const createdDate = new Date(offer.created_at).toLocaleDateString('en-US', { 
      day: 'numeric', month: 'short', year: 'numeric' 
    });
    
    // Get current row count for index
    const currentRows = document.querySelectorAll('tbody tr').length;
    const rowIndex = currentRows + 1;
    
    return `
      <td class="px-4 sm:px-6 py-3 font-semibold text-gray-600">
        ${rowIndex}
      </td>
      <td class="px-4 sm:px-6 py-3">
        <div class="flex items-center gap-2">
          <i class="fas fa-file-alt mr-2 text-blue-500"></i>
          <div class="flex flex-col">
            <span class="font-medium truncate">${offer.listing_name || 'N/A'}</span>
            ${offer.total_offers_count > 1 ? `
              <span class="text-xs text-orange-600 flex items-center mt-1">
                <i class="fas fa-layer-group mr-1"></i>${offer.total_offers_count} competing offers
              </span>
            ` : ''}
          </div>
        </div>
      </td>
      <td class="px-4 sm:px-6 py-3 whitespace-nowrap">
        <i class="fas fa-folder mr-1 text-orange-500"></i>
        ${(offer.listing_category || offer.type || 'N/A').charAt(0).toUpperCase() + (offer.listing_category || offer.type || 'N/A').slice(1)}
      </td>
      <td class="px-4 sm:px-6 py-3 text-gray-800 font-medium hidden md:table-cell">
        <div class="flex items-center">
          <i class="fas fa-dollar-sign mr-1 text-green-500"></i>
          ${parseFloat(offer.listing_price || 0).toLocaleString()}
        </div>
      </td>
      <td class="px-4 sm:px-6 py-3 text-blue-600 font-semibold whitespace-nowrap">
        <div class="flex items-center gap-2">
          <i class="fas fa-hand-holding-usd mr-1"></i>
          <span>$${parseFloat(offer.amount).toLocaleString()}</span>
          ${isHighestOffer ? '<span class="ml-2 text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full font-medium">Highest</span>' : ''}
          ${isLowerOffer ? '<span class="ml-2 text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full font-medium">Lower</span>' : ''}
        </div>
      </td>
      <td class="px-4 sm:px-6 py-3 hidden sm:table-cell">
        <div class="flex flex-col">
          <span class="font-medium text-gray-800">${offer.buyer_name || 'User'}</span>
          <span class="text-[11px] text-gray-500">${offer.buyer_email || '-'}</span>
        </div>
      </td>
      <td class="px-4 sm:px-6 py-3 text-gray-600 hidden lg:table-cell" style="max-width: 250px;">
        ${offer.message ? `
          <div class="flex items-start gap-2">
            <i class="fas fa-comment-dots text-blue-400 flex-shrink-0 mt-1"></i>
            <div class="flex-1 min-w-0">
              <p class="text-sm italic text-gray-700 line-clamp-2" title="${offer.message}">
                "${offer.message}"
              </p>
              ${offer.message.length > 80 ? `
                <button onclick="showFullMessage(${offer.id}, '${offer.message.replace(/'/g, "\\'")})" class="text-xs text-blue-600 hover:text-blue-800 mt-1 flex items-center">
                  <i class="fas fa-expand-alt mr-1"></i>Read more
                </button>
              ` : ''}
            </div>
          </div>
        ` : '<span class="text-gray-400"><i class="fas fa-comment-slash mr-1"></i>No message</span>'}
      </td>
      <td class="px-4 sm:px-6 py-3">
        <span class="${statusInfo.color} text-[11px] sm:text-xs px-3 py-1 rounded-full font-medium inline-flex items-center">
          <i class="${statusInfo.icon} mr-1"></i>${offer.status.charAt(0).toUpperCase() + offer.status.slice(1)}
        </span>
      </td>
      <td class="px-4 sm:px-6 py-3 text-xs text-gray-500 hidden md:table-cell whitespace-nowrap">
        <i class="fas fa-calendar-day mr-1"></i>
        ${createdDate}
      </td>
      <td class="px-4 sm:px-6 py-3">

        ${offer.status === 'pending' ? (
          isLowerOffer ? `
            <!-- Lower offer - only reject button enabled -->
            <div class="flex flex-col gap-1">
              <div class="flex gap-2 sm:gap-3">
                <button onclick='showOfferDetails(${JSON.stringify(offer)})' class="text-blue-600 hover:text-blue-800 text-base sm:text-lg" title="View offer details">
                  <i class="fas fa-eye"></i>
                </button>
                <span class="text-gray-300 text-base sm:text-lg cursor-not-allowed" title="Cannot accept - lower offer">
                  <i class="fas fa-check-circle"></i>
                </span>
                <a href="index.php?p=dashboard&page=offer_action&action=reject&id=${offer.id}" class="text-red-600 hover:text-red-800 text-base sm:text-lg" title="Reject this lower offer" onclick="return confirm('Are you sure you want to reject this offer?')">
                  <i class="fas fa-times-circle"></i>
                </a>
              </div>
              <p class="text-xs text-gray-500 mt-1">Lower offer</p>
            </div>
          ` : `
            <!-- Normal or highest offer - all actions enabled -->
            <div class="flex flex-col gap-1">
              <div class="flex gap-2 sm:gap-3">
                <button onclick='showOfferDetails(${JSON.stringify(offer)})' class="text-blue-600 hover:text-blue-800 text-base sm:text-lg" title="View offer details">
                  <i class="fas fa-eye"></i>
                </button>
                <a href="index.php?p=dashboard&page=offer_action&action=accept&id=${offer.id}" class="text-green-600 hover:text-green-800 text-base sm:text-lg" title="Accept" onclick="return confirm('Are you sure you want to accept this offer? This will reject all other offers for this listing.')">
                  <i class="fas fa-check-circle"></i>
                </a>
                <a href="index.php?p=dashboard&page=offer_action&action=reject&id=${offer.id}" class="text-red-600 hover:text-red-800 text-base sm:text-lg" title="Reject" onclick="return confirm('Are you sure you want to reject this offer?')">
                  <i class="fas fa-times-circle"></i>
                </a>
              </div>
              ${isHighestOffer ? '<p class="text-xs text-green-600 mt-1 font-medium"><i class="fas fa-star mr-1"></i>Highest offer</p>' : ''}
            </div>
          `
        ) : `

          <span class="text-gray-400 text-xs sm:text-sm flex items-center">
            <i class="fas fa-minus mr-1"></i>Done
          </span>
        `}
      </td>
    `;
  }
  
  // Add manual refresh button
  const header = document.querySelector('.flex.items-start.justify-between');
  if (header) {
    const refreshBtn = document.createElement('button');
    refreshBtn.className = 'ml-2 px-3 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition-colors text-sm flex items-center';
    refreshBtn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i> Refresh';
    refreshBtn.onclick = () => {
      if (window.pollingManager) {
        window.pollingManager.refresh();
      }
      location.reload();
    };
    header.appendChild(refreshBtn);
  }
  
  // Function to automatically process offers when they come via polling
  function processAutomaticOfferLogic(newOffers) {
    console.log('🤖 Processing automatic offer logic for', newOffers.length, 'offers');
    
    // Group offers by listing_id
    const offersByListing = {};
    newOffers.forEach(offer => {
      if (!offersByListing[offer.listing_id]) {
        offersByListing[offer.listing_id] = [];
      }
      offersByListing[offer.listing_id].push(offer);
    });
    
    // Process each listing's offers
    Object.keys(offersByListing).forEach(listingId => {
      const listingOffers = offersByListing[listingId];
      console.log(`📋 Processing ${listingOffers.length} offers for listing ${listingId}`);
      
      if (listingOffers.length > 1) {
        // Find the highest offer
        const highestOffer = listingOffers.reduce((max, offer) => 
          parseFloat(offer.amount) > parseFloat(max.amount) ? offer : max
        );
        
        console.log(`💰 Highest offer: $${highestOffer.amount} (ID: ${highestOffer.id})`);
        
        // Auto-accept highest offer and reject others
        listingOffers.forEach(offer => {
          if (offer.id === highestOffer.id) {
            // Auto-accept highest offer
            console.log(`✅ Auto-accepting highest offer: $${offer.amount}`);
            autoProcessOffer(offer.id, 'accept', `Auto-accepted highest offer of $${offer.amount}`);
          } else {
            // Auto-reject lower offers
            console.log(`❌ Auto-rejecting lower offer: $${offer.amount}`);
            autoProcessOffer(offer.id, 'reject', `Auto-rejected - higher offer of $${highestOffer.amount} received`);
          }
        });
      } else if (listingOffers.length === 1) {
        console.log(`📝 Single offer for listing ${listingId}, no auto-processing needed`);
      }
    });
  }
  
  // Function to automatically process an offer (accept/reject)
  function autoProcessOffer(offerId, action, reason) {
    console.log(`🔄 Auto-processing offer ${offerId}: ${action}`);
    
    fetch(`index.php?p=dashboard&page=offer_action&action=${action}&id=${offerId}&auto=1`, {
      method: 'GET',
      credentials: 'same-origin'
    })
    .then(response => response.text())
    .then(result => {
      console.log(`✅ Auto-${action} completed for offer ${offerId}`);
      
      // Show notification
      const actionText = action === 'accept' ? 'accepted' : 'rejected';
      const actionColor = action === 'accept' ? 'success' : 'info';
      window.PollingUIHelpers.showBriefNotification(
        `Offer automatically ${actionText}: ${reason}`, 
        actionColor
      );
      
      // Update the offer status in the table
      updateOfferStatusInTable(offerId, action === 'accept' ? 'accepted' : 'rejected');
    })
    .catch(error => {
      console.error(`❌ Auto-${action} failed for offer ${offerId}:`, error);
    });
  }
  
  // Function to update offer status in the table
  function updateOfferStatusInTable(offerId, newStatus) {
    const row = document.querySelector(`tr[data-record-id="${offerId}"]`);
    if (row) {
      const statusCell = row.querySelector('td:nth-last-child(2)'); // Status column
      if (statusCell) {
        const statusConfig = {
          'accepted': { color: 'bg-green-100 text-green-700', icon: 'fas fa-check-circle' },
          'rejected': { color: 'bg-gray-100 text-gray-600', icon: 'fas fa-ban' }
        };
        const statusInfo = statusConfig[newStatus];
        
        statusCell.innerHTML = `
          <span class="${statusInfo.color} text-xs px-3 py-1 rounded-full font-medium inline-flex items-center">
            <i class="${statusInfo.icon} mr-1"></i>${newStatus.charAt(0).toUpperCase() + newStatus.slice(1)}
          </span>
        `;
        
        // Also disable action buttons for this row
        const actionsCell = row.querySelector('td:last-child');
        if (actionsCell && newStatus !== 'pending') {
          actionsCell.innerHTML = `
            <span class="text-gray-400 text-sm">
              <i class="fas fa-${newStatus === 'accepted' ? 'check' : 'times'} mr-1"></i>
              ${newStatus.charAt(0).toUpperCase() + newStatus.slice(1)}
            </span>
          `;
        }
      }
    }
  }
});

// Debug function to check popup system
function checkPopupSystem() {
  console.log('=== Popup System Debug ===');
  console.log('showConfirm available:', typeof showConfirm !== 'undefined');
  console.log('showSuccess available:', typeof showSuccess !== 'undefined');
  console.log('showWarning available:', typeof showWarning !== 'undefined');
  
  return typeof showConfirm !== 'undefined';
}

// Test popup system function
function testPopupSystem() {
  console.log('🧪 Testing popup system...');
  
  if (typeof showConfirm === 'undefined') {
    alert('❌ showConfirm function not found!');
    return;
  }
  
  showConfirm('Test popup working?', {
    title: 'Test Popup',
    confirmText: 'Yes, Working!',
    cancelText: 'Cancel'
  }).then(result => {
    if (result) {
      showSuccess('Popup system is working correctly!', {
        title: 'Success',
        autoClose: true,
        autoCloseTime: 2000
      });
    } else {
      showWarning('Test cancelled', {
        title: 'Cancelled',
        autoClose: true,
        autoCloseTime: 1500
      });
    }
  });
}

// Test accept/reject functions
function testAcceptReject() {
  console.log('🧪 Testing accept/reject functions...');
  
  // Test accept
  console.log('Testing confirmAcceptOffer...');
  confirmAcceptOffer(null, 999, 'Test Listing', 1000, 2);
}

// Accept offer confirmation function
function confirmAcceptOffer(event, offerId, listingName, amount, totalOffers) {
  console.log('🎯 confirmAcceptOffer called with:', { event, offerId, listingName, amount, totalOffers });
  
  if (event && event.preventDefault) {
    event.preventDefault();
  }
  
  console.log('Accept offer confirmation triggered:', { offerId, listingName, amount, totalOffers });
  
  // Check popup system
  if (!checkPopupSystem()) {
    // Fallback to native confirm
    const confirmed = confirm(`Accept offer of $${amount.toLocaleString()} for "${listingName}"?`);
    if (confirmed) {
      window.location.href = `index.php?p=dashboard&page=offer_action&action=accept&id=${offerId}`;
    }
    return;
  }
  
  // Create confirmation message
  const formattedAmount = parseFloat(amount).toLocaleString();
  let message = `Accept offer of $${formattedAmount} for "${listingName}"?`;
  
  if (totalOffers > 1) {
    message += `\n\nThis will automatically reject all other ${totalOffers - 1} competing offers for this listing.`;
  }
  
  // Show confirmation popup
  showConfirm(message, {
    title: 'Accept Offer',
    confirmText: 'Yes, Accept',
    cancelText: 'Cancel'
  }).then(function(result) {
    console.log('Accept offer confirmation result:', result);
    if (result === true) {
      console.log('User confirmed accept - proceeding...');
      
      // Show loading message
      showSuccess('Accepting offer...', {
        title: 'Processing',
        showConfirm: false,
        autoClose: true,
        autoCloseTime: 2000
      });
      
      // Redirect to accept action after short delay
      setTimeout(function() {
        window.location.href = `index.php?p=dashboard&page=offer_action&action=accept&id=${offerId}`;
      }, 500);
    } else {
      console.log('Accept offer cancelled by user');
    }
  }).catch(function(error) {
    console.error('Accept offer confirmation error:', error);
    // Fallback - still allow accept on error
    if (confirm('Popup system error. Accept offer anyway?')) {
      window.location.href = `index.php?p=dashboard&page=offer_action&action=accept&id=${offerId}`;
    }
  });
}

// Reject offer confirmation function
function confirmRejectOffer(event, offerId, listingName, amount) {
  console.log('🎯 confirmRejectOffer called with:', { event, offerId, listingName, amount });
  
  if (event && event.preventDefault) {
    event.preventDefault();
  }
  
  console.log('Reject offer confirmation triggered:', { offerId, listingName, amount });
  
  // Check popup system
  if (!checkPopupSystem()) {
    // Fallback to native confirm
    const confirmed = confirm(`Reject offer of $${amount.toLocaleString()} for "${listingName}"?`);
    if (confirmed) {
      window.location.href = `index.php?p=dashboard&page=offer_action&action=reject&id=${offerId}`;
    }
    return;
  }
  
  // Create confirmation message
  const formattedAmount = parseFloat(amount).toLocaleString();
  const message = `Reject offer of $${formattedAmount} for "${listingName}"?`;
  
  // Show confirmation popup
  showConfirm(message, {
    title: 'Reject Offer',
    confirmText: 'Yes, Reject',
    cancelText: 'Cancel'
  }).then(function(result) {
    console.log('Reject offer confirmation result:', result);
    if (result === true) {
      console.log('User confirmed reject - proceeding...');
      
      // Show loading message
      showWarning('Rejecting offer...', {
        title: 'Processing',
        showConfirm: false,
        autoClose: true,
        autoCloseTime: 2000
      });
      
      // Redirect to reject action after short delay
      setTimeout(function() {
        window.location.href = `index.php?p=dashboard&page=offer_action&action=reject&id=${offerId}`;
      }, 500);
    } else {
      console.log('Reject offer cancelled by user');
    }
  }).catch(function(error) {
    console.error('Reject offer confirmation error:', error);
    // Fallback - still allow reject on error
    if (confirm('Popup system error. Reject offer anyway?')) {
      window.location.href = `index.php?p=dashboard&page=offer_action&action=reject&id=${offerId}`;
    }
  });
}
</script>

<!-- DIRECT ACTION OVERRIDE - No Popups -->
<script>
console.log('🚀 DIRECT ACTION OVERRIDE - Disabling Popups');

// No notification function needed - completely silent actions

// Override Accept Offer - SILENT DIRECT ACTION (No Popup, No Alert, No Notification)
function handleAcceptOfferDirect(offerId, listingName, amount, totalOffers) {
    console.log('🎯 SILENT Accept - No UI feedback:', { offerId, listingName, amount, totalOffers });
    
    // Show loading state on button
    const button = document.querySelector(`[data-offer-id="${offerId}"][title*="Accept"]`);
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.style.opacity = '0.6';
    }
    
    // Send AJAX request directly without any confirmation or notification
    fetch(`index.php?p=dashboard&page=offer_action&action=accept&id=${offerId}`, {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => response.text())
    .then(data => {
        console.log('Accept response:', data);
        
        // No notification - just reload page immediately
        window.location.reload();
    })
    .catch(error => {
        console.error('Accept error:', error);
        
        // Reset button state on error (no notification)
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-check-circle"></i>';
            button.style.opacity = '1';
        }
    });
}

// Override Reject Offer - SILENT DIRECT ACTION (No Popup, No Alert, No Notification)
function handleRejectOfferDirect(offerId, listingName, amount) {
    console.log('🎯 SILENT Reject - No UI feedback:', { offerId, listingName, amount });
    
    // Show loading state on button
    const button = document.querySelector(`[data-offer-id="${offerId}"][title*="Reject"]`);
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.style.opacity = '0.6';
    }
    
    // Send AJAX request directly without any confirmation or notification
    fetch(`index.php?p=dashboard&page=offer_action&action=reject&id=${offerId}`, {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => response.text())
    .then(data => {
        console.log('Reject response:', data);
        
        // No notification - just reload page immediately
        window.location.reload();
    })
    .catch(error => {
        console.error('Reject error:', error);
        
        // Reset button state on error (no notification)
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-times-circle"></i>';
            button.style.opacity = '1';
        }
    });
}

// Override the existing functions with direct action versions
window.handleAcceptOffer = handleAcceptOfferDirect;
window.handleRejectOffer = handleRejectOfferDirect;

// Override the data functions too
window.handleAcceptOfferFromData = function(button) {
    const offerId = button.getAttribute('data-offer-id');
    const listingName = button.getAttribute('data-listing-name');
    const amount = parseFloat(button.getAttribute('data-amount'));
    const totalOffers = parseInt(button.getAttribute('data-total-offers')) || 1;
    
    handleAcceptOfferDirect(offerId, listingName, amount, totalOffers);
};

window.handleRejectOfferFromData = function(button) {
    const offerId = button.getAttribute('data-offer-id');
    const listingName = button.getAttribute('data-listing-name');
    const amount = parseFloat(button.getAttribute('data-amount'));
    
    handleRejectOfferDirect(offerId, listingName, amount);
};

console.log('✅ SILENT ACTION OVERRIDE COMPLETE - No Popups, No Alerts, No Notifications!');
console.log('Accept/Reject buttons will work completely silently with just page reload');
</script>
