/**
 * Polling System Initialization
 * This script initializes polling for different pages
 */
// Global polling manager instance
window.globalPollingManager = null;
// Initialize polling based on current page
function initializePolling() {
    console.log('🚀 Initializing polling system...');
    // Check if polling.js is loaded
    if (typeof PollingManager === 'undefined') {
        console.error('❌ PollingManager not found! Make sure polling.js is loaded first.');
        return;
    }
    // Get current page info
    const currentPage = getCurrentPageInfo();
    console.log('📄 Current page:', currentPage);
    
    // Stop existing polling if any
    if (window.globalPollingManager) {
        console.log('🛑 Stopping existing polling');
        window.globalPollingManager.stop();
    }
    
    // Create new polling manager
    window.globalPollingManager = new PollingManager();
    
    // Setup callbacks based on current page
    const callbacks = getCallbacksForPage(currentPage);
    
    if (Object.keys(callbacks).length > 0) {
        console.log('✅ Starting polling with callbacks:', Object.keys(callbacks));
        window.globalPollingManager.start(callbacks);
    } else {
        console.log('ℹ️ No polling callbacks needed for this page');
    }
}

// Get current page information
function getCurrentPageInfo() {
    const url = window.location.href;
    const params = new URLSearchParams(window.location.search);
    
    return {
        url: url,
        page: params.get('p') || 'home',
        subpage: params.get('page') || null,
        tab: params.get('tab') || null,
        pathname: window.location.pathname
    };
}

// Get appropriate callbacks for current page
function getCallbacksForPage(pageInfo) {
    const callbacks = {};
    
    // Always include notifications for logged-in users
    if (document.getElementById('notificationBtn') || document.getElementById('mobileNotificationBtn')) {
        callbacks.notifications = handleNewNotifications;
    }
    
    // Page-specific callbacks
    switch (pageInfo.page) {
        case 'dashboard':
            switch (pageInfo.subpage) {
                case 'userDashboard':
                case 'adminDashboard':
                case 'superAdminDashboard':
                    callbacks.offers = handleNewOffers;
                    callbacks.transactions = handleNewTransactions;
                    if (pageInfo.subpage !== 'userDashboard') {
                        callbacks.listings = handleNewListings;
                    }
                    break;
                    
                case 'my_sales':
                    callbacks.transactions = handleMySalesTransactions;
                    break;
                    
                case 'my_listing':
                    callbacks.offers = handleMyListingOffers;
                    break;
                    
                case 'offers':
                    callbacks.offers = handleOffersPageUpdate;
                    break;
                    
                case 'my_order':
                case 'purchases':
                    callbacks.transactions = handleOrdersUpdate;
                    break;
            }
            break;
            
        case 'listing':
            callbacks.listings = handleListingPageUpdate;
            break;
            
        case 'home':
            callbacks.listings = handleHomePageUpdate;
            break;
    }
    
    return callbacks;
}

// Notification handlers
function handleNewNotifications(notifications) {
    console.log('🔔 New notifications:', notifications.length);
    
    // Update notification manager if available
    if (window.notificationManager) {
        window.notificationManager.handleNewNotifications(notifications);
    } else if (window.cleanNotificationSystem) {
        window.cleanNotificationSystem.updateNotificationCount();
    }
    
    // Show browser notification for first new notification
    if (notifications.length > 0) {
        showBrowserNotification(notifications[0]);
    }
}

// Offer handlers
function handleNewOffers(offers) {
    console.log('🤝 New offers:', offers.length);
    showUpdateNotification(`${offers.length} new offer${offers.length > 1 ? 's' : ''}!`);
    
    // Update offers table if present
    updateOffersTable(offers);
}

function handleMyListingOffers(offers) {
    console.log('📋 New offers for my listings:', offers.length);
    if (offers.length > 0) {
        showUpdateNotification(`You received ${offers.length} new offer${offers.length > 1 ? 's' : ''}!`);
        setTimeout(() => location.reload(), 2000);
    }
}

function handleOffersPageUpdate(offers) {
    console.log('💼 Offers page update:', offers.length);
    if (offers.length > 0) {
        setTimeout(() => location.reload(), 1000);
    }
}

// Transaction handlers
function handleNewTransactions(transactions) {
    console.log('💳 New transactions:', transactions.length);
    showUpdateNotification(`${transactions.length} new transaction${transactions.length > 1 ? 's' : ''}!`);
    
    // Update transactions table if present
    updateTransactionsTable(transactions);
}

function handleMySalesTransactions(transactions) {
    console.log('💰 My sales transactions update:', transactions.length);
    
    if (typeof updateTransactionStats === 'function') {
        updateTransactionStats(transactions);
    }
    if (typeof updateTransactionCards === 'function') {
        updateTransactionCards(transactions);
    }
    
    if (transactions.length > 0) {
        showUpdateNotification(`${transactions.length} transaction update${transactions.length > 1 ? 's' : ''}!`);
    }
}

function handleOrdersUpdate(transactions) {
    console.log('🛒 Orders update:', transactions.length);
    if (transactions.length > 0) {
        showUpdateNotification(`${transactions.length} order update${transactions.length > 1 ? 's' : ''}!`);
        setTimeout(() => location.reload(), 2000);
    }
}

// Listing handlers
function handleNewListings(listings) {
    console.log('📝 New listings:', listings.length);
    showUpdateNotification(`${listings.length} new listing${listings.length > 1 ? 's' : ''}!`);
    
    // Update listings table if present
    updateListingsTable(listings);
}

function handleListingPageUpdate(listings) {
    console.log('📋 Listing page update:', listings.length);
    if (listings.length > 0) {
        setTimeout(() => location.reload(), 1000);
    }
}

function handleHomePageUpdate(listings) {
    console.log('🏠 Home page update:', listings.length);
    
    if (listings.length > 0) {
        // Filter only approved listings for home page
        const approvedListings = listings.filter(listing => 
            listing.status === 'approved' &&
            listing.name && 
            listing.name.trim() !== '' &&
            !listing.name.toLowerCase().includes('test listing')
        );
        
        if (approvedListings.length > 0) {
            console.log(`🎉 Found ${approvedListings.length} new approved listings for home page`);
            
            // Check if addNewListingsToPage function exists (from home.php)
            if (typeof addNewListingsToPage === 'function') {
                addNewListingsToPage(approvedListings);
                showUpdateNotification(`${approvedListings.length} new listing${approvedListings.length > 1 ? 's' : ''} added!`);
            } else {
                // Fallback: show notification and reload
                showUpdateNotification(`${approvedListings.length} new listing${approvedListings.length > 1 ? 's' : ''} available!`);
                setTimeout(() => location.reload(), 2000);
            }
        }
    }
}

// UI Update functions
function updateOffersTable(offers) {
    const table = document.querySelector('#offersTable tbody, .offers-container');
    if (!table) return;
    
    offers.forEach(offer => {
        if (window.PollingUIHelpers) {
            window.PollingUIHelpers.addRecordToTable(offer, '#offersTable', renderOfferRow);
        }
    });
}

function updateTransactionsTable(transactions) {
    const table = document.querySelector('#transactionsTable tbody, .transactions-container');
    if (!table) return;
    
    transactions.forEach(transaction => {
        if (window.PollingUIHelpers) {
            window.PollingUIHelpers.addRecordToTable(transaction, '#transactionsTable', renderTransactionRow);
        }
    });
}

function updateListingsTable(listings) {
    const table = document.querySelector('#listingsTable tbody, .listings-container');
    if (!table) return;
    
    listings.forEach(listing => {
        if (window.PollingUIHelpers) {
            window.PollingUIHelpers.addRecordToTable(listing, '#listingsTable', renderListingRow);
        }
    });
}

// Row renderers (basic implementations)
function renderOfferRow(offer) {
    return `
        <td class="py-4 px-5">
            <div class="flex items-center space-x-3">
                <div>
                    <p class="font-semibold text-gray-800">${offer.listing_name || 'Unknown Listing'}</p>
                    <p class="text-gray-500 text-xs">by ${offer.buyer_name || 'Unknown Buyer'}</p>
                </div>
            </div>
        </td>
        <td class="py-4 px-5">
            <span class="text-green-600 font-bold">$${parseFloat(offer.amount || 0).toFixed(2)}</span>
        </td>
        <td class="py-4 px-5">
            <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-semibold">
                ${offer.status || 'pending'}
            </span>
        </td>
        <td class="py-4 px-5">
            <span class="text-gray-600">${new Date(offer.created_at).toLocaleDateString()}</span>
        </td>
    `;
}

function renderTransactionRow(transaction) {
    return `
        <td class="py-4 px-5">
            <div class="flex items-center space-x-3">
                <div>
                    <p class="font-semibold text-gray-800">${transaction.listing_name || 'Unknown Listing'}</p>
                    <p class="text-gray-500 text-xs">#${transaction.id}</p>
                </div>
            </div>
        </td>
        <td class="py-4 px-5">
            <span class="text-green-600 font-bold">$${parseFloat(transaction.amount || 0).toFixed(2)}</span>
        </td>
        <td class="py-4 px-5">
            <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-semibold">
                ${transaction.status || transaction.transfer_status || 'pending'}
            </span>
        </td>
        <td class="py-4 px-5">
            <span class="text-gray-600">${new Date(transaction.created_at).toLocaleDateString()}</span>
        </td>
    `;
}

function renderListingRow(listing) {
    return `
        <td class="py-4 px-5">
            <div class="flex items-center space-x-3">
                <div>
                    <p class="font-semibold text-gray-800">${listing.name || 'Unknown Listing'}</p>
                    <p class="text-gray-500 text-xs">${listing.category || 'N/A'}</p>
                </div>
            </div>
        </td>
        <td class="py-4 px-5">
            <span class="text-green-600 font-bold">$${parseFloat(listing.price || 0).toFixed(2)}</span>
        </td>
        <td class="py-4 px-5">
            <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-semibold">
                ${listing.status || 'active'}
            </span>
        </td>
        <td class="py-4 px-5">
            <span class="text-gray-600">${new Date(listing.created_at).toLocaleDateString()}</span>
        </td>
    `;
}

// Utility functions
function showUpdateNotification(message) {
    if (window.PollingUIHelpers) {
        window.PollingUIHelpers.showBriefNotification(message, 'success');
    } else {
        // Fallback notification
        console.log('📢', message);
    }
}

function showBrowserNotification(notification) {
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(notification.title, {
            body: notification.message,
            icon: '/marketplace/public/images/logo.png'
        });
    }
}

// Legacy compatibility
window.startPolling = function(callbacks) {
    console.log('🔄 Legacy startPolling called');
    if (window.globalPollingManager) {
        window.globalPollingManager.renderCallbacks = { ...window.globalPollingManager.renderCallbacks, ...callbacks };
    } else {
        initializePolling();
    }
};

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    console.log('🎯 DOM ready - initializing polling system');
    console.log('🔍 Checking if PollingManager exists:', typeof PollingManager);
    console.log('🔍 Current page URL:', window.location.href);
    setTimeout(() => {
        console.log('⏰ Delayed initialization starting...');
        initializePolling();
    }, 1000); // Small delay to ensure all scripts are loaded
});

// Export for manual initialization
window.initializePolling = initializePolling;