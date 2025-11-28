<?php
require_once __DIR__ . "/../../config.php";
require_login();

$pdo = db();
$currentUser = current_user();
$logged_in_user_id = $currentUser['id'] ?? 0;

// Handle AJAX requests for real-time updates
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_offers') {
    header('Content-Type: application/json');
    
    $sql = "
        SELECT 
            o.*,
            l.name AS listing_name,
            l.type AS listing_category,
            l.asking_price AS listing_price,
            b.name AS buyer_name,
            b.email AS buyer_email,
            s.name AS seller_name,
            (SELECT MAX(amount) FROM offers WHERE listing_id = o.listing_id AND status = 'pending') AS max_offer_amount,
            (SELECT COUNT(*) FROM offers WHERE listing_id = o.listing_id AND status = 'pending') AS total_offers_count
        FROM offers o
        LEFT JOIN listings l ON o.listing_id = l.id
        LEFT JOIN users b ON o.user_id = b.id
        LEFT JOIN users s ON o.seller_id = s.id
        WHERE o.seller_id = ?
        ORDER BY o.created_at DESC
        LIMIT 50
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$logged_in_user_id]);
    $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'offers' => $offers,
        'count' => count($offers),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Get initial offers data
$sql = "
    SELECT 
        o.*,
        l.name AS listing_name,
        l.type AS listing_category,
        l.asking_price AS listing_price,
        b.name AS buyer_name,
        b.email AS buyer_email,
        s.name AS seller_name,
        (SELECT MAX(amount) FROM offers WHERE listing_id = o.listing_id AND status = 'pending') AS max_offer_amount,
        (SELECT COUNT(*) FROM offers WHERE listing_id = o.listing_id AND status = 'pending') AS total_offers_count
    FROM offers o
    LEFT JOIN listings l ON o.listing_id = l.id
    LEFT JOIN users b ON o.user_id = b.id
    LEFT JOIN users s ON o.seller_id = s.id
    WHERE o.seller_id = ?
    ORDER BY o.created_at DESC
    LIMIT 50
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$logged_in_user_id]);
$offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-time Offers - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet" />
    <style>
        .offer-row {
            transition: all 0.3s ease;
        }
        .offer-row.new {
            background-color: #dbeafe !important;
            animation: highlight 2s ease-out;
        }
        @keyframes highlight {
            0% { background-color: #3b82f6; color: white; }
            100% { background-color: #dbeafe; color: inherit; }
        }
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-pending { background-color: #f59e0b; }
        .status-accepted { background-color: #10b981; }
        .status-rejected { background-color: #ef4444; }
    </style>
</head>
<body class="bg-gray-50">

<div class="max-w-7xl mx-auto p-4 sm:p-6 md:p-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 gap-3">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center gap-3">
            <i class="fas fa-handshake text-blue-600"></i> 
            Real-time Offers (<span id="offerCount"><?= count($offers) ?></span>)
        </h1>
        <div class="flex items-center gap-4">
            <div class="text-sm text-gray-500">
                <span class="status-indicator status-pending"></span>
                <span id="pendingCount">0</span> Pending
            </div>
            <div class="text-sm text-gray-500">
                <span class="status-indicator status-accepted"></span>
                <span id="acceptedCount">0</span> Accepted
            </div>
            <div class="text-sm text-gray-500">
                <span class="status-indicator status-rejected"></span>
                <span id="rejectedCount">0</span> Rejected
            </div>
            <div class="text-xs text-gray-400">
                Last updated: <span id="lastUpdated">Now</span>
            </div>
        </div>
    </div>

    <!-- Real-time Status -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div id="connectionStatus" class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-sm font-medium text-gray-700">Real-time Updates Active</span>
            </div>
            <button onclick="forceRefresh()" class="text-sm bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1 rounded-lg transition-colors">
                <i class="fas fa-sync-alt mr-1"></i> Refresh Now
            </button>
        </div>
    </div>

    <!-- Offers Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-left text-gray-700">
                <thead class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-xs uppercase">
                    <tr>
                        <th class="px-6 py-3">#</th>
                        <th class="px-6 py-3">Listing</th>
                        <th class="px-6 py-3">Buyer</th>
                        <th class="px-6 py-3">Amount</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Date</th>
                        <th class="px-6 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody id="offersTableBody" class="divide-y divide-gray-100">
                    <!-- Offers will be loaded here -->
                </tbody>
            </table>
        </div>
        
        <!-- Empty State -->
        <div id="emptyState" class="text-center py-16 text-gray-500" style="display: none;">
            <i class="fas fa-handshake-slash text-5xl text-gray-300 mb-4"></i>
            <h3 class="text-lg font-semibold text-gray-600 mb-2">No Offers Yet</h3>
            <p class="text-gray-500 text-sm">You haven't received any offers yet.</p>
        </div>
    </div>
</div>

<script>
let currentOffers = [];
let refreshInterval;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Real-time offers page loaded');
    loadOffers();
    startRealTimeUpdates();
});

// Load offers from server
function loadOffers() {
    console.log('📡 Loading offers...');
    
    fetch('?ajax=get_offers', {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        console.log('📦 Received offers:', data);
        
        if (data.success) {
            updateOffersTable(data.offers);
            updateStatusCounts(data.offers);
            updateLastUpdated();
            setConnectionStatus(true);
        } else {
            console.error('❌ Failed to load offers');
            setConnectionStatus(false);
        }
    })
    .catch(error => {
        console.error('❌ Error loading offers:', error);
        setConnectionStatus(false);
    });
}

// Update offers table
function updateOffersTable(offers) {
    const tbody = document.getElementById('offersTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (offers.length === 0) {
        tbody.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    
    emptyState.style.display = 'none';
    
    // Check for new offers
    const newOfferIds = offers.filter(offer => 
        !currentOffers.find(current => current.id === offer.id)
    ).map(offer => offer.id);
    
    currentOffers = offers;
    
    tbody.innerHTML = offers.map((offer, index) => {
        const isNew = newOfferIds.includes(offer.id);
        const isLowerOffer = (offer.total_offers_count > 1 && offer.amount < offer.max_offer_amount);
        const isHighestOffer = (offer.amount == offer.max_offer_amount && offer.total_offers_count > 1);
        
        return `
            <tr class="offer-row hover:bg-gray-50 transition-colors ${isNew ? 'new' : ''}" data-offer-id="${offer.id}">
                <td class="px-6 py-4 font-semibold text-gray-600">${index + 1}</td>
                <td class="px-6 py-4">
                    <div class="flex items-center">
                        <i class="fas fa-file-alt mr-2 text-blue-500"></i>
                        <div>
                            <div class="font-medium">${escapeHtml(offer.listing_name || 'N/A')}</div>
                            ${offer.total_offers_count > 1 ? `
                                <div class="text-xs text-orange-600 mt-1">
                                    <i class="fas fa-users mr-1"></i>
                                    ${offer.total_offers_count} competing offers
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div>
                        <div class="font-medium">${escapeHtml(offer.buyer_name || 'User')}</div>
                        <div class="text-xs text-gray-500">${escapeHtml(offer.buyer_email || '-')}</div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center">
                        <i class="fas fa-hand-holding-usd mr-1 text-blue-600"></i>
                        <span class="font-semibold text-blue-600">
                            $${parseFloat(offer.amount).toLocaleString()}
                        </span>
                        ${isHighestOffer ? '<span class="ml-2 text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">Highest</span>' : ''}
                        ${isLowerOffer ? '<span class="ml-2 text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full">Lower</span>' : ''}
                    </div>
                </td>
                <td class="px-6 py-4">
                    ${getStatusBadge(offer.status)}
                </td>
                <td class="px-6 py-4 text-xs text-gray-500">
                    <i class="fas fa-calendar-day mr-1"></i>
                    ${formatDate(offer.created_at)}
                </td>
                <td class="px-6 py-4">
                    ${getActionButtons(offer)}
                </td>
            </tr>
        `;
    }).join('');
    
    // Show notification for new offers
    if (newOfferIds.length > 0) {
        showNotification(`${newOfferIds.length} new offer(s) received!`, 'success');
    }
}

// Get status badge HTML
function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="bg-yellow-100 text-yellow-700 text-xs px-3 py-1 rounded-full font-medium"><i class="fas fa-clock mr-1"></i>Pending</span>',
        'accepted': '<span class="bg-green-100 text-green-700 text-xs px-3 py-1 rounded-full font-medium"><i class="fas fa-check-circle mr-1"></i>Accepted</span>',
        'rejected': '<span class="bg-gray-100 text-gray-600 text-xs px-3 py-1 rounded-full font-medium"><i class="fas fa-ban mr-1"></i>Rejected</span>'
    };
    return badges[status] || `<span class="bg-gray-100 text-gray-600 text-xs px-3 py-1 rounded-full font-medium">${status}</span>`;
}

// Get action buttons HTML
function getActionButtons(offer) {
    if (offer.status === 'pending') {
        return `
            <div class="flex gap-2">
                <a href="index.php?p=dashboard&page=offer_action&action=accept&id=${offer.id}" 
                   onclick="return confirm('Accept offer of $${parseFloat(offer.amount).toLocaleString()} for &quot;${escapeHtml(offer.listing_name)}&quot;?')"
                   class="text-green-600 hover:text-green-800 text-lg" 
                   title="Accept this offer">
                    <i class="fas fa-check-circle"></i>
                </a>
                <a href="index.php?p=dashboard&page=offer_action&action=reject&id=${offer.id}" 
                   onclick="return confirm('Reject offer of $${parseFloat(offer.amount).toLocaleString()} for &quot;${escapeHtml(offer.listing_name)}&quot;?')"
                   class="text-red-600 hover:text-red-800 text-lg" 
                   title="Reject this offer">
                    <i class="fas fa-times-circle"></i>
                </a>
            </div>
        `;
    } else {
        return '<span class="text-gray-400 text-sm"><i class="fas fa-check mr-1"></i>Done</span>';
    }
}

// Update status counts
function updateStatusCounts(offers) {
    const counts = {
        pending: 0,
        accepted: 0,
        rejected: 0
    };
    
    offers.forEach(offer => {
        if (counts.hasOwnProperty(offer.status)) {
            counts[offer.status]++;
        }
    });
    
    document.getElementById('pendingCount').textContent = counts.pending;
    document.getElementById('acceptedCount').textContent = counts.accepted;
    document.getElementById('rejectedCount').textContent = counts.rejected;
    document.getElementById('offerCount').textContent = offers.length;
}

// Update last updated time
function updateLastUpdated() {
    const now = new Date();
    document.getElementById('lastUpdated').textContent = now.toLocaleTimeString();
}

// Set connection status
function setConnectionStatus(connected) {
    const statusEl = document.getElementById('connectionStatus');
    if (connected) {
        statusEl.className = 'w-3 h-3 bg-green-500 rounded-full animate-pulse';
    } else {
        statusEl.className = 'w-3 h-3 bg-red-500 rounded-full';
    }
}

// Start real-time updates
function startRealTimeUpdates() {
    console.log('🔄 Starting real-time updates...');
    
    // Refresh every 5 seconds
    refreshInterval = setInterval(() => {
        loadOffers();
    }, 5000);
}

// Force refresh
function forceRefresh() {
    console.log('🔄 Force refresh triggered');
    loadOffers();
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 
        'bg-blue-500'
    }`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Utility functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
    });
}

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});
</script>

</body>
</html>