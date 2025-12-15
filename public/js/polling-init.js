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
    let callbacks = getCallbacksForPage(currentPage);

    // MERGE PENDING CALLBACKS from inline scripts
    if (window.pendingCallbacks && Object.keys(window.pendingCallbacks).length > 0) {
        console.log('📥 Merging pending callbacks:', Object.keys(window.pendingCallbacks));
        callbacks = { ...callbacks, ...window.pendingCallbacks };
        window.pendingCallbacks = {}; // Clear after merging
    }
    
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
    // Explicitly check for currentUserId to be safe (as requested)
    if ((document.getElementById('notificationBtn') || document.getElementById('mobileNotificationBtn')) && window.currentUserId) {
        callbacks.notifications = handleNewNotifications;
    }
    
    // Page-specific callbacks
    switch (pageInfo.page) {
        case 'dashboard':
            switch (pageInfo.subpage) {
                case 'userDashboard':
                    callbacks.listings = handleUserDashboardListings;
                    callbacks.offers = handleUserDashboardOffers;
                    callbacks.orders = handleUserDashboardOrders;
                    break;

                case 'adminDashboard':
                    callbacks.listings = handleAdminDashboardListings;
                    callbacks.orders = handleAdminDashboardOrders;
                    break;

                case 'superAdminDashboard':
                     callbacks.listings = handleSuperAdminDashboardListings;
                     callbacks.offers = handleSuperAdminDashboardOffers;
                     callbacks.orders = handleSuperAdminDashboardOrders;
                     break;
                     
                case 'superAdminAudit':
                     // Callbacks handled via inline startPolling in the file
                     break;

                case 'superAdminOffers':
                    callbacks.offers = handleSuperAdminOffersOffers;
                    callbacks.orders = handleSuperAdminOffersOrders;
                    break;
                    
                case 'superAdminReports':
                    callbacks.listings = handleSuperAdminReportsListings;
                    callbacks.orders = handleSuperAdminReportsOrders;
                    break;
                    
                case 'superAdminPayment':
                    callbacks.orders = handleSuperAdminPaymentOrders;
                    break;
                    
                case 'superAdminDisputes':
                    callbacks.orders = handleSuperAdminDisputesOrders;
                    break;
                
                case 'superAdminDelligence':
                    callbacks.listings = handleSuperAdminDelligenceListings;
                    break;
                
                case 'adminPayments':
                case 'adminpayments':
                    callbacks.orders = handleAdminPaymentsOrders;
                    break;
                    
                case 'adminreports':
                    callbacks.listings = handleAdminReportsListings;
                    callbacks.orders = handleAdminReportsOrders;
                    break;
                    
                case 'admin_tickets':
                    callbacks.tickets = (newTickets) => {
                         if(newTickets.length > 0) {
                             showUpdateNotification(`${newTickets.length} new or updated ticket(s)!`, 'info');
                             setTimeout(() => location.reload(), 2000); 
                         }
                    };
                    break;
                
                case 'admin_ticket_view':
                    callbacks.tickets = (newTickets) => {
                         const currentId = parseInt(new URLSearchParams(window.location.search).get('id'));
                         if (newTickets.some(t => t.id == currentId)) {
                             showUpdateNotification(`Ticket #${currentId} updated!`, 'info');
                             setTimeout(() => location.reload(), 2000); 
                         }
                    };
                    break;
                    


                case 'my_sales':
                    callbacks.transactions = handleMySalesTransactions;
                    break;
                    
                case 'my_listing':
                    callbacks.offers = handleMyListingOffers;
                    callbacks.listings = handleMyListingListings;
                    break;
                    
                case 'offers':
                    callbacks.offers = handleOffersPageUpdate;
                    break;
                    
                case 'my_order':
                    callbacks.offers = handleMyOrderOffers;
                    break;

                case 'purchases':
                    callbacks.transactions = handleOrdersUpdate;
                    break;
                
                case 'listingverification':
                    callbacks.listings = handleListingVerificationUpdate;
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

// ==========================================
// Generic Handlers
// ==========================================

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

// ==========================================
// User Dashboard Handlers
// ==========================================

function handleUserDashboardListings(newListings) {
    const currentUserId = window.currentUserId;
    if (!currentUserId) return;

    // Filter listings created by current user
    const myListings = newListings.filter(listing => listing.user_id == currentUserId);

    if (myListings.length > 0) {
        console.log('📊 [UserDashboard] New listings:', myListings.length);
        
        // Update stats
        if (window.PollingUIHelpers) {
            window.PollingUIHelpers.updateStatsCounter('[data-stat="total-listings"]', myListings.length);
        }
        
        showUpdateNotification(`${myListings.length} new listing${myListings.length > 1 ? 's' : ''} added!`, 'success');
    }
}

function handleUserDashboardOffers(newOffers) {
    const currentUserId = window.currentUserId;
    if (!currentUserId) return;

    // Filter offers where current user is the buyer (user_id in offers table usually refers to the person making the offer)
    const myOffers = newOffers.filter(offer => offer.user_id == currentUserId);

    if (myOffers.length > 0) {
        console.log('📊 [UserDashboard] New offers:', myOffers.length);
        
        // Update total offers count
        if (window.PollingUIHelpers) {
            window.PollingUIHelpers.updateStatsCounter('[data-stat="total-offers"]', myOffers.length);
            
            // Update accepted offers if any
            const acceptedOffers = myOffers.filter(o => o.status === 'accepted');
            if (acceptedOffers.length > 0) {
                window.PollingUIHelpers.updateStatsCounter('[data-stat="accepted-offers"]', acceptedOffers.length);
            }
            
            // Add to Recent Offers list
             const container = document.querySelector('#recent-offers-container');
             if (container) {
                 // Sort by date desc
                 myOffers.sort((a,b) => new Date(b.created_at) - new Date(a.created_at));
                 
                 // Remove "No Offers" placeholder if exists
                 const placeholder = container.closest('.mb-10').querySelector('.bg-white.p-8.text-center');
                 if (placeholder && placeholder.textContent.includes("No Offers Yet")) {
                     placeholder.style.display = 'none';
                     container.style.display = 'grid'; // Ensure grid is visible
                 }

                 myOffers.slice(0, 2).forEach(offer => {
                     // Check if already exists
                     if(container.querySelector(`[data-offer-id="${offer.id}"]`)) return;

                     const card = createOfferCard(offer);
                     if(card) container.insertBefore(card, container.firstChild);
                 });
             }
        }

        showUpdateNotification(`${myOffers.length} new offer update${myOffers.length > 1 ? 's' : ''}!`, 'success');
    }
}

function handleUserDashboardOrders(newOrders) {
    const currentUserId = window.currentUserId;
    if (!currentUserId) return;

    // Filter orders where current user is the buyer
    const myOrders = newOrders.filter(order => order.buyer_id == currentUserId);

    if (myOrders.length > 0) {
        console.log('📊 [UserDashboard] New orders:', myOrders.length);
        
        if (window.PollingUIHelpers) {
            // Update completed orders count
            const completedOrders = myOrders.filter(o => o.status === 'completed');
            if (completedOrders.length > 0) {
                window.PollingUIHelpers.updateStatsCounter('[data-stat="completed-orders"]', completedOrders.length);

                // Update total spent
                const totalSpent = completedOrders.reduce((sum, order) => sum + parseFloat(order.amount || 0), 0);
                const spentEl = document.querySelector('[data-stat="total-spent"]');
                if (spentEl) {
                    const currentSpent = parseFloat(spentEl.textContent.replace(/[$,]/g, '')) || 0;
                    spentEl.textContent = '$' + (currentSpent + totalSpent).toLocaleString('en-US', {
                        minimumFractionDigits: 2
                    });
                }
            }

            // Add to Recent Orders
            const container = document.querySelector('#recent-orders-container'); // tbody
            if (container) {
                myOrders.forEach(order => {
                    const row = createOrderRow(order);
                    if(row) container.insertBefore(row, container.firstChild);
                });
            }
        }
        
        showUpdateNotification(`${myOrders.length} new order update${myOrders.length > 1 ? 's' : ''}!`, 'info');
    }
}

// Helpers for User Dashboard UI
function createOfferCard(offer) {
    const statusColors = {
        'pending': 'bg-yellow-100 text-yellow-700',
        'accepted': 'bg-green-100 text-green-700',
        'rejected': 'bg-red-100 text-red-700'
    };
    
    const card = document.createElement('div');
    card.className = 'bg-gradient-to-br from-blue-50 to-purple-50 rounded-xl p-4 border border-blue-200 animate-fade-in';
    card.setAttribute('data-offer-id', offer.id);
    card.style.backgroundColor = '#dbeafe';
    
    card.innerHTML = `
      <div class="flex justify-between items-start mb-2">
        <h3 class="font-semibold text-gray-900">${offer.listing_name || 'Listing'}</h3>
        <span class="text-xs px-2 py-1 rounded-full ${statusColors[offer.status] || 'bg-gray-100 text-gray-700'}">
          ${offer.status}
        </span>
      </div>
      <div class="text-sm text-gray-600">
        <div><i class="fas fa-dollar-sign mr-1"></i>$${parseFloat(offer.amount).toLocaleString()}</div>
        <div><i class="fas fa-user mr-1"></i>${offer.seller_name || 'Seller'}</div>
      </div>
    `;
    setTimeout(() => { card.style.backgroundColor = ''; }, 3000);
    return card;
}

function createOrderRow(order) {
    const statusColors = {
        'pending': 'text-orange-500 font-medium',
        'completed': 'text-green-600 font-medium',
        'cancelled': 'text-red-500 font-medium'
    };
    const statusIcons = {
        'pending': 'fa-hourglass-half',
        'completed': 'fa-check',
        'cancelled': 'fa-xmark'
    };

    const tr = document.createElement('tr');
    tr.className = 'border-t hover:bg-gray-50 transition animate-fade-in';
    tr.style.backgroundColor = '#f0f9ff';
    tr.dataset.orderId = order.id;

    tr.innerHTML = `
        <td class="px-4 sm:px-6 py-3 font-medium text-gray-900">
            <i class="fa-solid fa-list mr-1 text-blue-500"></i>
            ${order.listing_name || 'Listing'}
        </td>
        <td class="px-4 sm:px-6 py-3">
            <i class="fa-solid fa-user mr-1"></i>${order.seller_name || 'Seller'}
        </td>
        <td class="px-4 sm:px-6 py-3">
             <i class="fa-solid fa-dollar-sign mr-1 text-green-600"></i> ${parseFloat(order.amount).toFixed(2)}
        </td>
        <td class="px-4 sm:px-6 py-3">
             <span class="${statusColors[order.status] || 'text-gray-500'}">
                <i class="fa-solid ${statusIcons[order.status] || 'fa-circle'} mr-1"></i> ${order.status}
             </span>
        </td>
        <td class="px-4 sm:px-6 py-3 text-right text-gray-500">
            <i class="fa-regular fa-calendar mr-1"></i>
            ${new Date(order.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}
        </td>
    `;
    setTimeout(() => { tr.style.backgroundColor = ''; }, 3000);
    return tr;
}


// ==========================================
// Admin Dashboard Handlers
// ==========================================

function handleAdminDashboardListings(newListings) {
    if(newListings.length === 0) return;

    if (window.PollingUIHelpers) {
        // Update stats
        window.PollingUIHelpers.updateStatsCounter('[data-stat="total-listings"]', newListings.length);
        
        const pending = newListings.filter(l => l.status === 'pending');
        const approved = newListings.filter(l => l.status === 'approved');
        const rejected = newListings.filter(l => l.status === 'rejected');
        
        if (pending.length) {
            window.PollingUIHelpers.updateStatsCounter('[data-stat="pending-listings"]', pending.length);
            // Update Pending Actions
            updateAdminPendingActions('listing_verification', pending.length);
        }
        if (approved.length) window.PollingUIHelpers.updateStatsCounter('[data-stat="approved-listings"]', approved.length);
        if (rejected.length) window.PollingUIHelpers.updateStatsCounter('[data-stat="rejected-listings"]', rejected.length);

        // Add to Recent Activity on Admin Dashboard
        const activityContainer = document.getElementById('recent-activity-list');
        if (activityContainer) {
            newListings.slice(0, 3).forEach(listing => {
                const item = createAdminActivityItem(listing, 'listing');
                if(item) activityContainer.insertBefore(item, activityContainer.firstChild);
            });
            // Keep limit
            while(activityContainer.children.length > 10) activityContainer.removeChild(activityContainer.lastChild);
        }
    }
    
    showUpdateNotification(`${newListings.length} new listing(s) received`, 'info');
}

function handleAdminDashboardOrders(newOrders) {
    if(newOrders.length === 0) return;
    
    // Filter for pending or paid orders that need processing
    const actionableOrders = newOrders.filter(o => o.status === 'pending' || o.status === 'paid' || o.status === 'processing');
    
    if (actionableOrders.length > 0) {
        updateAdminPendingActions('order_processing', actionableOrders.length);
        
        // Also could update a "Total Orders" stat if it existed, but admin dash mainly shows listings stats
    }
    
    showUpdateNotification(`${newOrders.length} new order(s) received`, 'info');
}

// Helper for Admin Dashboard Pending Actions
function updateAdminPendingActions(actionType, incrementCount) {
    const container = document.getElementById('pending-actions-container');
    const countBadge = document.getElementById('pending-actions-count');
    // Logic specific to admin dashboard HTML structure...
    // (Simplified for brevity, assumes standard structure exists)
    
    // 1. Update global badge
    if(countBadge) {
        const current = parseInt(countBadge.textContent.match(/\d+/)?.[0] || 0);
        countBadge.textContent = `${current + incrementCount} items`;
    }
    // 2. Update specific action card
    if (container) {
        const actionCard = container.querySelector(`[data-action-type="${actionType}"]`);
        if (actionCard) {
             const descEl = actionCard.querySelector('[data-action-description]');
             if (descEl) {
                 const currentDescCount = parseInt(descEl.textContent.match(/\d+/)?.[0] || 0);
                 const newDescCount = currentDescCount + incrementCount;
                 descEl.textContent = descEl.textContent.replace(/\d+/, newDescCount);
             }
        } else {
            // Logic to add new card if it didn't exist? (Complexity might be high, usually reloading is safer if structure changed significantly)
             setTimeout(() => location.reload(), 2000); 
        }
    }
}

function createAdminActivityItem(data, type) {
    const div = document.createElement('div');
    div.className = 'flex items-start space-x-4 p-4 rounded-xl hover:bg-gray-50 transition-colors animate-fade-in';
    div.style.backgroundColor = '#dbeafe';
    
    const iconClass = data.status === 'approved' ? 'bg-green-100' : 'bg-blue-100';
    const iconColor = data.status === 'approved' ? 'text-green-600' : 'text-blue-600';
    const iconName = data.status === 'approved' ? 'fas fa-check' : 'fas fa-plus';
    
    div.innerHTML = `
        <div class="w-10 h-10 ${iconClass} rounded-full flex items-center justify-center flex-shrink-0">
          <i class="${iconName} ${iconColor}"></i>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-gray-900">New listing "${data.name || 'Unnamed'}"</p>
          <p class="text-sm text-gray-500">by ${data.user_name || 'Unknown'}</p>
          <p class="text-xs text-gray-400 mt-1">Just now</p>
        </div>
    `;
    setTimeout(() => { div.style.backgroundColor = ''; }, 3000);
    return div;
}

// ==========================================
// Super Admin Dashboard Handlers
// ==========================================

function handleSuperAdminDashboardListings(newListings) {
    if(newListings.length === 0) return;
    console.log('📋 [SuperAdmin] New listings:', newListings.length);

    const totalCountEl = document.getElementById('listings-total-count');
    if (totalCountEl) {
        const currentCount = parseInt(totalCountEl.dataset.count) || 0;
        const newCount = currentCount + newListings.length;
        totalCountEl.dataset.count = newCount;
        totalCountEl.textContent = newCount.toLocaleString();

        totalCountEl.classList.add('animate-pulse');
        setTimeout(() => totalCountEl.classList.remove('animate-pulse'), 1000);
    }

    // Separate listings by status
    const pendingListings = newListings.filter(l => l.status === 'pending' || !l.status);
    const approvedListings = newListings.filter(l => l.status === 'approved');
    const rejectedListings = newListings.filter(l => l.status === 'rejected');

    const badgesContainer = document.getElementById('listings-badges');
    if (badgesContainer) {
        // Handle pending
        if (pendingListings.length > 0) {
            updateSuperAdminBadge(badgesContainer, 'pending', pendingListings.length);
        }
        
        // Handle approved (and decrease pending)
        if (approvedListings.length > 0) {
             updateSuperAdminBadge(badgesContainer, 'approved', approvedListings.length);
             // Decrease pending
             updateSuperAdminBadge(badgesContainer, 'pending', -approvedListings.length);
        }

        // Handle rejected (and decrease pending)
        if (rejectedListings.length > 0) {
             updateSuperAdminBadge(badgesContainer, 'rejected', rejectedListings.length);
             // Decrease pending
             updateSuperAdminBadge(badgesContainer, 'pending', -rejectedListings.length);
        }
    }

    // Recent Activity
    const activityContainer = document.getElementById('recent-activity-list');
    if (activityContainer) {
        newListings.forEach(l => {
            const item = createSuperAdminActivityItem(l, 'listing');
            if(item) activityContainer.insertBefore(item, activityContainer.firstChild);
        });
        while(activityContainer.children.length > 10) activityContainer.removeChild(activityContainer.lastChild);
    }

    showUpdateNotification(`${newListings.length} new listing(s)!`, 'info');
}

function handleSuperAdminDashboardOffers(newOffers) {
    if(newOffers.length === 0) return;
    console.log('💰 [SuperAdmin] New offers:', newOffers.length);

    const totalEl = document.getElementById('offers-orders-total');
    const badge = document.getElementById('offers-badge');

    if (totalEl) {
        const currentOffers = parseInt(totalEl.dataset.offers) || 0;
        const currentOrders = parseInt(totalEl.dataset.orders) || 0;
        const newOffersCount = currentOffers + newOffers.length;
        totalEl.dataset.offers = newOffersCount;
        totalEl.textContent = (newOffersCount + currentOrders).toLocaleString();
        
        totalEl.classList.add('animate-pulse');
        setTimeout(() => totalEl.classList.remove('animate-pulse'), 1000);
    }

    if (badge) {
        const currentCount = parseInt(badge.dataset.count) || 0;
        const newCount = currentCount + newOffers.length;
        badge.dataset.count = newCount;
        const countEl = badge.querySelector('.count');
        if (countEl) countEl.textContent = newCount;
    }

    const activityContainer = document.getElementById('recent-activity-list');
    if (activityContainer) {
        newOffers.forEach(o => {
            const item = createSuperAdminActivityItem(o, 'offer');
            if(item) activityContainer.insertBefore(item, activityContainer.firstChild);
        });
        while(activityContainer.children.length > 10) activityContainer.removeChild(activityContainer.lastChild);
    }

    showUpdateNotification(`${newOffers.length} new offer(s)!`, 'info');
}


function handleSuperAdminOffersOffers(newOffers) {
    if(newOffers.length === 0) return;
    
    // Update Stats
    if (window.PollingUIHelpers) {
        window.PollingUIHelpers.updateStatsCounter('#total-offers-count', newOffers.length);
        const pending = newOffers.filter(o => o.status === 'pending');
        if (pending.length) {
            window.PollingUIHelpers.updateStatsCounter('#pending-offers-count', pending.length);
        }
    }

    const tbody = document.getElementById('offers-table-body');
    if (tbody) {
        newOffers.forEach(o => {
             // Avoid duplicates
             // Check if we can find a row with this ID. PHP uses OFF{id} text context usually, 
             // but let's try to identify by text content as we didn't add id attributes to TRs in PHP
             // Or better, just verify if we see the ID in the table
             const hasRow = Array.from(tbody.querySelectorAll('tr')).some(r => r.innerText.includes('OFF' + o.id));
             if (hasRow) return;

             // Remove empty message row if it exists
             const emptyRow = tbody.querySelector('td[colspan="7"]');
             if (emptyRow && tbody.rows.length === 1) tbody.removeChild(emptyRow.parentNode);

             const row = document.createElement('tr');
             row.className = 'hover:bg-gray-50 transition-colors animate-fade-in';
             row.style.backgroundColor = '#dbeafe';
             
             // Construct Status HTML
             let statusHtml = `<span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800'><i class='fa fa-clock mr-1.5'></i> ${o.status}</span>`;
             if (o.status === 'accepted') statusHtml = `<span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800'><i class='fa fa-check-circle mr-1.5'></i> Accepted</span>`;
             else if (o.status === 'rejected') statusHtml = `<span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800'><i class='fa fa-times-circle mr-1.5'></i> Rejected</span>`;

             const amount = parseFloat(o.amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
             const asking = parseFloat(o.asking_price || 0).toLocaleString(undefined, {minimumFractionDigits: 2});

             row.innerHTML = `
                  <td class="py-4 px-6"><div class="font-medium text-gray-900">OFF${o.id}</div></td>
                  <td class="py-4 px-6">
                    <div class="font-medium text-gray-900">${o.buyer_name || 'Buyer'}</div>
                    <div class="text-sm text-gray-500">${o.buyer_email || ''}</div>
                  </td>
                  <td class="py-4 px-6">
                    <div class="font-medium text-gray-900">${o.listing_name || 'Listing'}</div>
                    <div class="text-sm text-gray-500">Asking: $${asking}</div>
                  </td>
                  <td class="py-4 px-6">
                    <div class="font-semibold text-gray-900">$${amount}</div>
                  </td>
                  <td class="py-4 px-6">
                    ${statusHtml}
                  </td>
                  <td class="py-4 px-6 text-gray-600">
                    <div>${new Date().toLocaleDateString()}</div>
                    <div class="text-sm text-gray-500">Just now</div>
                  </td>
                  <td class="py-4 px-6 text-right">
                    <button onclick="viewOfferDetails(${o.id}, '${(o.buyer_name||'').replace(/'/g, "\\'")}', '${(o.listing_name||'').replace(/'/g, "\\'")}', '${(parseFloat(o.amount)||0).toFixed(2)}', '${o.status}', '${new Date(o.created_at).toLocaleString()}', '${(o.message||'').replace(/'/g, "\\'")}')" class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        View
                    </button>
                  </td>
             `;
             tbody.insertBefore(row, tbody.firstChild);
             setTimeout(() => row.style.backgroundColor = '', 3000);
        });
    }

    showUpdateNotification(`${newOffers.length} new offer(s) received!`);
}

function handleSuperAdminOffersOrders(newOrders) {
    if(newOrders.length === 0) return;

    // Update Stats
    if (window.PollingUIHelpers) {
        window.PollingUIHelpers.updateStatsCounter('#total-orders-count', newOrders.length);
        const completed = newOrders.filter(o => o.status === 'completed');
        if (completed.length) {
            window.PollingUIHelpers.updateStatsCounter('#completed-orders-count', completed.length);
        }
    }
    
    const tbody = document.getElementById('orders-table-body');
    if (tbody) {
        newOrders.forEach(o => {
             const hasRow = Array.from(tbody.querySelectorAll('tr')).some(r => r.innerText.includes('ORD' + o.id));
             if (hasRow) return;

             // Remove empty message row
             const emptyRow = tbody.querySelector('td[colspan="7"]');
             if (emptyRow && tbody.rows.length === 1) tbody.removeChild(emptyRow.parentNode);

             const row = document.createElement('tr');
             row.className = 'hover:bg-gray-50 transition-colors animate-fade-in';
             row.style.backgroundColor = '#dbeafe';
             
             let statusHtml = `<span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800'><i class='fa fa-cog mr-1.5'></i> ${o.status || 'Processing'}</span>`;
             if (o.status === 'completed') statusHtml = `<span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800'><i class='fa fa-check-circle mr-1.5'></i> Completed</span>`;

             const amount = parseFloat(o.amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
             const total = parseFloat(o.total || amount).toLocaleString(undefined, {minimumFractionDigits: 2});

             row.innerHTML = `
                  <td class="py-4 px-6"><div class="font-medium text-gray-900">ORD${o.id}</div></td>
                  <td class="py-4 px-6">
                    <div class="font-medium text-gray-900">${o.buyer_name || 'Buyer'}</div>
                    <div class="text-sm text-gray-500">${o.buyer_email || ''}</div>
                  </td>
                  <td class="py-4 px-6">
                    <div class="font-medium text-gray-900">${o.seller_name || 'Seller'}</div>
                    <div class="text-sm text-gray-500">${o.seller_email || ''}</div>
                  </td>
                  <td class="py-4 px-6">
                    <div class="font-semibold text-gray-900">$${amount}</div>
                    <div class="text-sm font-medium text-blue-600">Total: $${total}</div>
                  </td>
                  <td class="py-4 px-6">
                    ${statusHtml}
                  </td>
                  <td class="py-4 px-6 text-gray-600">
                    <div>${new Date().toLocaleDateString()}</div>
                    <div class="text-sm text-gray-500">Just now</div>
                  </td>
                  <td class="py-4 px-6 text-right">
                    <button onclick="viewOrderDetails(${o.id}, '${(o.buyer_name||'').replace(/'/g, "\\'")}', '${(o.seller_name||'').replace(/'/g, "\\'")}', '${(parseFloat(o.amount)||0).toFixed(2)}', '${(parseFloat(o.platform_fee)||0).toFixed(2)}', '${(parseFloat(o.total)||0).toFixed(2)}', '${o.status}', '${new Date(o.created_at).toLocaleString()}')" class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        View
                    </button>
                  </td>
             `;
             tbody.insertBefore(row, tbody.firstChild);
             setTimeout(() => row.style.backgroundColor = '', 3000);
        });
    }

    showUpdateNotification(`${newOrders.length} new order(s) received!`);
}

function handleSuperAdminDashboardOrders(newOrders) {
    if(newOrders.length === 0) return;
    console.log('📦 [SuperAdmin] New orders:', newOrders.length);

    const totalEl = document.getElementById('offers-orders-total');
    const badge = document.getElementById('orders-badge');

    if (totalEl) {
        const currentOffers = parseInt(totalEl.dataset.offers) || 0;
        const currentOrders = parseInt(totalEl.dataset.orders) || 0;
        const newOrdersCount = currentOrders + newOrders.length;
        totalEl.dataset.orders = newOrdersCount;
        totalEl.textContent = (currentOffers + newOrdersCount).toLocaleString();
        
        totalEl.classList.add('animate-pulse');
        setTimeout(() => totalEl.classList.remove('animate-pulse'), 1000);
    }

    if (badge) {
        const currentCount = parseInt(badge.dataset.count) || 0;
        const newCount = currentCount + newOrders.length;
        badge.dataset.count = newCount;
        const countEl = badge.querySelector('.count');
        if (countEl) countEl.textContent = newCount;
    }

    const activityContainer = document.getElementById('recent-activity-list');
    if (activityContainer) {
        newOrders.forEach(o => {
            const item = createSuperAdminActivityItem(o, 'order');
            if(item) activityContainer.insertBefore(item, activityContainer.firstChild);
        });
        while(activityContainer.children.length > 10) activityContainer.removeChild(activityContainer.lastChild);
    }

    showUpdateNotification(`${newOrders.length} new order(s)!`, 'success');
}

function handleAdminPaymentsOrders(newOrders) {
    if(newOrders.length === 0) return;
    showUpdateNotification(`${newOrders.length} new payment(s) received`, 'success');
    setTimeout(() => location.reload(), 2000);
}

function handleAdminReportsListings(newListings) {
    if(newListings.length === 0) return;
    const actualNew = newListings.filter(l => l.status === 'pending');
    if (actualNew.length > 0) {
        showUpdateNotification(`${actualNew.length} new listing(s)`, 'info');
         // Could use granular updates here but complex due to multiple tabs/sections. Reload for now or just notify.
         // Given implementation in adminreports.php, maybe reloading is fine.
    }
}

function handleAdminReportsOrders(newOrders) {
    if(newOrders.length === 0) return;
    showUpdateNotification(`${newOrders.length} new payment activity`, 'info');
}

function handleSuperAdminDelligenceListings(newListings) {
    if(newListings.length === 0) return;
    showUpdateNotification(`${newListings.length} new listing(s) for review`, 'info');
    setTimeout(() => location.reload(), 2000);
}

function handleSuperAdminReportsListings(newListings) {
    if (newListings.length === 0) return;
    console.log('📋 [Reports] New listings detected:', newListings.length);
    
    // Update total listings count
    if (window.PollingUIHelpers) {
        window.PollingUIHelpers.updateStatsCounter('#total-listings', newListings.length);
    }

    showUpdateNotification(`${newListings.length} new listing(s) added!`, 'info');
}

function handleSuperAdminPaymentOrders(newOrders) { 
    if (newOrders.length === 0) return;
    
    // Update total transactions count
    if (window.PollingUIHelpers) {
        // We need selectors to update the UI
        // In the PHP file: <span class="... text-xs font-medium ...">...</span> inside the transactions tab button
        // And stats cards
        
        // This is tricky because the PHP file uses specific logic to count.
        // For now, let's just do a reload as it is a complex financial page.
        showUpdateNotification(`${newOrders.length} new transaction(s) detected!`);
        setTimeout(() => location.reload(), 2000);
    }
}

function handleSuperAdminDisputesOrders(newOrders) {
    if(newOrders.length === 0) return;
    
    // Check for disputed orders
    const disputedOrders = newOrders.filter(o => o.status === 'disputed' || o.has_dispute);

    if (disputedOrders.length > 0) {
        showUpdateNotification(`${disputedOrders.length} new dispute(s) detected!`, 'error');
        setTimeout(() => location.reload(), 2000);
    }
}

function handleSuperAdminReportsOrders(newOrders) {
    if (newOrders.length === 0) return;
    console.log('📦 [Reports] New orders detected:', newOrders.length);

    // Update stats
    const completedCount = newOrders.filter(o => o.status === 'completed').length;
    if (completedCount > 0) {
        const revenueEl = document.getElementById('total-revenue');
        const ordersEl = document.getElementById('completed-orders');

        if (revenueEl && ordersEl) {
             // Update revenue
             const currentRevenue = parseFloat(revenueEl.getAttribute('data-value') || revenueEl.textContent.replace(/[^0-9.]/g, '')) || 0;
             const newRevenue = newOrders.reduce((sum, o) => sum + (o.status === 'completed' ? parseFloat(o.amount) : 0), 0);
             const totalRevenue = currentRevenue + newRevenue;
             
             revenueEl.setAttribute('data-value', totalRevenue);
             revenueEl.textContent = '$' + totalRevenue.toLocaleString();
             revenueEl.classList.add('animate-pulse');
             setTimeout(() => revenueEl.classList.remove('animate-pulse'), 1000);

             // Update orders count
             const currentOrders = parseInt(ordersEl.getAttribute('data-value') || ordersEl.textContent.replace(/[^0-9]/g, '')) || 0;
             const totalOrders = currentOrders + completedCount;
             
             ordersEl.setAttribute('data-value', totalOrders);
             ordersEl.textContent = totalOrders.toLocaleString();
             ordersEl.classList.add('animate-pulse');
             setTimeout(() => ordersEl.classList.remove('animate-pulse'), 1000);
        }
    }

    // Add new transactions to table
    const tableBody = document.getElementById('transactions-table');
    if (tableBody) {
        newOrders.slice(0, 5).forEach(order => {
             // Avoid duplicates
             if(document.getElementById(`row-ord-${order.id}`)) return;

             const row = document.createElement('tr');
             row.id = `row-ord-${order.id}`;
             row.className = 'hover:bg-gray-50 animate-fade-in';
             row.style.backgroundColor = '#dbeafe';

             const statusClass = order.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';

             row.innerHTML = `
                <td class="py-4 px-6">
                  <span class="font-medium text-gray-900">ORD${order.id}</span>
                </td>
                <td class="py-4 px-6">
                  <span class="text-gray-900">${order.listing_name || 'N/A'}</span>
                </td>
                <td class="py-4 px-6">
                  <span class="text-gray-700">${order.buyer_name || 'N/A'}</span>
                </td>
                <td class="py-4 px-6">
                  <span class="text-gray-700">${order.seller_name || 'N/A'}</span>
                </td>
                <td class="py-4 px-6">
                  <span class="font-semibold text-gray-900">$${parseFloat(order.amount).toLocaleString()}</span>
                </td>
                <td class="py-4 px-6">
                  <span class="px-2 py-1 rounded-full text-xs font-medium ${statusClass}">
                    ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                  </span>
                </td>
                <td class="py-4 px-6">
                  <span class="text-sm text-gray-600">Just now</span>
                </td>
              `;

              tableBody.insertBefore(row, tableBody.firstChild);
              setTimeout(() => { row.style.backgroundColor = ''; }, 5000);
        });

        // Keep only last 10 rows
        while (tableBody.children.length > 10) {
             tableBody.removeChild(tableBody.lastChild);
        }
    }

    showUpdateNotification(`${newOrders.length} new transaction(s) recorded!`, 'success');
}

function updateSuperAdminBadge(container, status, change) {
    let badge = container.querySelector(`[data-status="${status}"]`);
    
    if (!badge && change > 0) {
        // Create new badge
        const colors = {
            pending: 'bg-yellow-100 text-yellow-700',
            approved: 'bg-green-100 text-green-700',
            rejected: 'bg-red-100 text-red-700'
        };
        const labels = {
            pending: 'Pending',
            approved: 'Verified',
            rejected: 'Rejected'
        };
        badge = document.createElement('span');
        badge.className = `text-xs ${colors[status]} px-2 py-1 rounded-full`;
        badge.dataset.status = status;
        badge.dataset.count = 0;
        badge.innerHTML = `<span class="count">0</span> ${labels[status]}`;
        container.appendChild(badge);
    }

    if (badge) {
        const countEl = badge.querySelector('.count');
        const currentCount = parseInt(badge.dataset.count) || 0;
        const newCount = Math.max(0, currentCount + change);
        badge.dataset.count = newCount;
        if(countEl) countEl.textContent = newCount;
        
        // If count is 0 and it was created dynamically (or even if not), maybe hide it? 
        // Logic in PHP didn't remove it, just showed 0 or handled carefully. 
        // For now we keep it visible or 0.
        if (newCount === 0 && change < 0) {
            // If we decremented to 0, maybe hide?
            // badge.style.display = 'none'; 
        } else {
             badge.style.display = 'inline-block';
        }
    }
}

function createSuperAdminActivityItem(item, type) {
    let iconClass, iconName, description, subText;

    if (type === 'listing') {
        if (item.status === 'approved') {
            iconClass = 'bg-green-100 text-green-500';
            iconName = 'fa-solid fa-check-circle';
            description = `Listing "${item.name}" verified`;
            subText = item.asking_price ? `Asking price: $${Number(item.asking_price).toLocaleString()}` : 'New verified listing';
        } else {
            iconClass = 'bg-blue-100 text-blue-500';
            iconName = 'fa-solid fa-plus';
            description = `New listing "${item.name}" submitted`;
            subText = 'Pending verification by admin';
        }
    } else if (type === 'offer') {
        iconClass = 'bg-purple-100 text-purple-500';
        iconName = 'fa-solid fa-handshake';
        description = `New offer for "${item.listing_name || 'listing'}"`;
        subText = item.amount ? `Offer amount: $${Number(item.amount).toLocaleString()}` : 'New offer received';
    } else if (type === 'order') {
        if (item.status === 'completed') {
            iconClass = 'bg-green-100 text-green-500';
            iconName = 'fa-solid fa-dollar-sign';
            description = item.amount ? `Payment of $${Number(item.amount).toLocaleString()} processed successfully` : 'Payment processed';
            subText = item.listing_name ? `Order for "${item.listing_name}" completed` : 'Order completed';
        } else {
            iconClass = 'bg-blue-100 text-blue-500';
            iconName = 'fa-solid fa-shopping-cart';
            description = `New order for "${item.listing_name || 'listing'}"`;
            subText = item.amount ? `Amount: $${Number(item.amount).toLocaleString()}` : 'New order placed';
        }
    } else {
        return null;
    }

    const div = document.createElement('div');
    div.className = 'flex items-start p-3 rounded-lg hover:bg-gray-50 transition-colors bg-blue-50 animate-fade-in';
    div.innerHTML = `
        <div class="${iconClass} p-2 rounded-lg mr-3 mt-1">
          <i class="${iconName} text-sm"></i>
        </div>
        <div class="flex-1">
          <p class="text-sm font-medium text-gray-800">${description}</p>
          <p class="text-xs text-gray-500 mt-1">${subText}</p>
          ${item.user_name ? `<p class="text-xs text-gray-400 mt-1">by ${item.user_name}</p>` : ''}
        </div>
        <span class="text-xs text-gray-400 ml-2">Just now</span>
    `;
    
    setTimeout(() => {
        div.classList.remove('bg-blue-50');
    }, 5000);

    return div;
}

// ==========================================
// Other Handlers
// ==========================================

function handleMySalesTransactions(transactions) {
    console.log('💰 My sales transactions update:', transactions.length);
    if (transactions.length > 0) {
        showUpdateNotification(`${transactions.length} transaction update${transactions.length > 1 ? 's' : ''}!`);
        // Complex DOM structure, safer to reload
        setTimeout(() => location.reload(), 2000);
    }
}

function handleMyListingOffers(offers) {
    console.log('💰 My Listing - Offers callback triggered!', offers.length);
    const currentUserId = window.currentUserId;

    if (offers.length === 0) return;
    
    // Filter offers for current user's listings only (where user is seller)
    // Note: detailed offer objects usually have seller_id. 
    // If not, we might need to rely on the backend sending only relevant offers.
    // The previous inline code filtered by seller_id == currentUserId.
    let myOffers = offers;
    if (currentUserId) {
        myOffers = offers.filter(offer => offer.seller_id == currentUserId);
    }
    
    if (myOffers.length === 0) {
        console.log('ℹ️ No new offers for current user listings');
        return;
    }

    // Group offers by listing_id
    const offersByListing = {};
    myOffers.forEach(offer => {
      if (!offersByListing[offer.listing_id]) {
        offersByListing[offer.listing_id] = [];
      }
      offersByListing[offer.listing_id].push(offer);
    });

    // Notify for each
    myOffers.forEach(offer => {
      const isLowerOffer = (offer.total_offers_count > 1 && offer.amount < offer.max_offer_amount);
      const isHighestOffer = (offer.amount == offer.max_offer_amount && offer.total_offers_count > 1);

      // Show notification with badge
      let badgeText = '';
      let type = 'info';
      
      if (isHighestOffer) {
        badgeText = ' 🌟 (Highest)';
        type = 'success';
      } else if (isLowerOffer) {
        badgeText = ' ⬇️ (Lower)';
        type = 'warning';
      }

      if (window.PollingUIHelpers) {
          window.PollingUIHelpers.showBriefNotification(
            `New offer: $${parseFloat(offer.amount).toLocaleString()}${badgeText} for "${offer.listing_name}"`,
            type
          );
      }
    });

    // Reload to show new offers in the UI (since we don't have a dynamic offers table in my_listing.php, 
    // or maybe we do elsewhere, but reloading ensures state consistency)
    setTimeout(() => location.reload(), 2000); 
}

function handleMyOrderOffers(offers) {
    console.log('🛍️ My Orders - Offers callback triggered!', offers.length);
    const currentUserId = window.currentUserId;

    if (offers.length === 0) return;

    // Filter offers where current user is the buyer (user_id in offers table usually refers to the person making the offer)
    let myOffers = offers;
    if (currentUserId) {
        myOffers = offers.filter(offer => offer.user_id == currentUserId);
    }
    
    if (myOffers.length === 0) {
        console.log('ℹ️ No new offer updates for current user');
        return;
    }

    console.log('🎯 Processing', myOffers.length, 'offer updates for My Orders');

    myOffers.forEach(offer => {
        // Show notification
        if (window.PollingUIHelpers) {
            window.PollingUIHelpers.showBriefNotification(
                `Offer update: ${offer.listing_name} - $${parseFloat(offer.amount).toLocaleString()}`, 
                offer.status === 'accepted' ? 'success' : 'info'
            );
        }
        
        // Add or update in table
        addOrderOfferToTable(offer);
    });
}


function addOrderOfferToTable(offer) {
  const container = document.querySelector('.space-y-6');
  if (!container) return;
  
  // Check if offer already exists
  const existingCard = document.querySelector(`[data-offer-id="${offer.id}"]`);
  if (existingCard) {
    console.log('🔄 Updating existing offer card:', offer.id);
    updateExistingOrderCard(existingCard, offer);
    return;
  }
  
  // Status configuration
  const statusConfig = {
    'pending': { 
      color: 'bg-yellow-100 text-yellow-700', 
      icon: 'fas fa-clock',
      label: 'Waiting for Seller'
    },
    'accepted': { 
      color: 'bg-green-100 text-green-700', 
      icon: 'fas fa-check-circle',
      label: 'Accepted by Seller'
    },
    'rejected': { 
      color: 'bg-red-100 text-red-700', 
      icon: 'fas fa-times-circle',
      label: 'Rejected'
    },
    'withdrawn': { 
      color: 'bg-gray-100 text-gray-700', 
      icon: 'fas fa-minus-circle',
      label: 'Withdrawn'
    }
  };
  
  const statusInfo = statusConfig[offer.status] || statusConfig['pending'];
  const createdDate = new Date(offer.created_at).toLocaleDateString('en-US', { 
    day: 'numeric', month: 'short', year: 'numeric' 
  });
  
  // Create action buttons
  let actionButtons = '';
  if (offer.status === 'accepted') {
    actionButtons = `<a href="index.php?p=payment&id=${offer.listing_id}" class="px-3 sm:px-4 py-1.5 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-lg text-xs sm:text-sm font-medium hover:opacity-90 hover:scale-105 transition-transform duration-200 flex items-center"><i class="fas fa-credit-card mr-2"></i>Make Payment</a>`;
  } else if (offer.status === 'pending') {
    actionButtons = `<span class="px-3 sm:px-4 py-1.5 text-xs sm:text-sm font-medium border border-gray-300 rounded-lg text-gray-600 flex items-center"><i class="fas fa-clock mr-2"></i>Waiting for Seller</span>`;
  } else {
    actionButtons = `<span class="px-3 sm:px-4 py-1.5 text-xs sm:text-sm font-medium border border-gray-300 rounded-lg text-gray-600 flex items-center"><i class="${statusInfo.icon} mr-2"></i>${statusInfo.label}</span>`;
  }
  
  // Create new card
  const card = document.createElement('div');
  card.className = 'bg-white rounded-2xl shadow-md hover:shadow-lg transition duration-300 p-5 sm:p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border border-gray-100 animate-fade-in';
  card.dataset.offerId = offer.id;
  card.style.backgroundColor = '#dbeafe';
  
  card.innerHTML = `
    <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto">
      <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center text-white text-xl sm:text-2xl shadow-md">
        <i class="fas fa-handshake"></i>
      </div>
      <div class="flex-1">
        <h3 class="text-base sm:text-lg font-semibold text-gray-900 flex items-center flex-wrap">
          <i class="fas fa-file-alt mr-2 text-gray-400"></i>
          ${offer.listing_name || 'N/A'}
        </h3>
        <p class="text-sm text-gray-500 flex items-center flex-wrap mt-1">
          <i class="fas fa-user-tie mr-1"></i>
          Seller: <span class="font-medium text-gray-700 ml-1">${offer.seller_name || 'Seller'}</span>
          <span class="hidden sm:inline mx-2">•</span>
          <i class="fas fa-folder mr-1 sm:ml-2"></i>
          ${offer.category ? offer.category.charAt(0).toUpperCase() + offer.category.slice(1) : 'N/A'}
        </p>
        <p class="text-xs text-gray-400 mt-1 flex items-center">
          <i class="fas fa-calendar-alt mr-1"></i>
          Offer made on ${createdDate}
        </p>
        <div class="flex flex-wrap gap-2 sm:gap-3 mt-4">
          ${actionButtons}
          <a href="index.php?p=dashboard&page=message&seller_id=${offer.seller_id}&listing_id=${offer.listing_id}" class="px-3 sm:px-4 py-1.5 text-xs sm:text-sm font-medium border border-gray-300 rounded-lg hover:bg-gray-100 transition flex items-center">
            <i class="fas fa-comments mr-2"></i>Contact Seller
          </a>
          <a href="index.php?p=listingDetail&id=${offer.listing_id}" class="px-3 sm:px-4 py-1.5 text-xs sm:text-sm font-medium border border-gray-300 rounded-lg hover:bg-gray-100 transition flex items-center">
            <i class="fas fa-eye mr-2"></i>View Listing
          </a>
        </div>
      </div>
    </div>
    <div class="text-left sm:text-right w-full sm:w-auto">
      <p class="text-lg sm:text-xl font-bold text-gray-900 flex items-center justify-start sm:justify-end">
        <i class="fas fa-dollar-sign mr-1 text-green-500"></i>${parseFloat(offer.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}
      </p>
      <span class="text-xs ${statusInfo.color} px-3 py-1 rounded-full font-medium flex items-center justify-start sm:justify-end mt-2">
        <i class="${statusInfo.icon} mr-1"></i>
        ${statusInfo.label}
      </span>
    </div>
  `;
  
  // Add to top of container
  container.insertBefore(card, container.firstChild);
  
  // Remove highlight after 5 seconds
  setTimeout(() => {
    card.style.backgroundColor = '';
  }, 5000);
}

function updateExistingOrderCard(card, offer) {
  const statusConfig = {
    'accepted': { 
      color: 'bg-green-100 text-green-700', 
      icon: 'fas fa-check-circle',
      label: 'Accepted by Seller'
    },
    'rejected': { 
      color: 'bg-red-100 text-red-700', 
      icon: 'fas fa-times-circle',
      label: 'Rejected'
    },
    'pending': { 
      color: 'bg-yellow-100 text-yellow-700', 
      icon: 'fas fa-clock',
      label: 'Waiting for Seller'
    },
    'withdrawn': { 
      color: 'bg-gray-100 text-gray-700', 
      icon: 'fas fa-minus-circle',
      label: 'Withdrawn'
    }
  };
  const statusInfo = statusConfig[offer.status] || statusConfig['pending'];
  
  // Update status badge
  const statusBadge = card.querySelector('.text-xs.px-3.py-1.rounded-full');
  if (statusBadge) {
    statusBadge.className = `text-xs ${statusInfo.color} px-3 py-1 rounded-full font-medium flex items-center justify-start sm:justify-end mt-2`;
    statusBadge.innerHTML = `<i class="${statusInfo.icon} mr-1"></i>${statusInfo.label}`;
  }
  
  // Update action buttons
  const actionsDiv = card.querySelector('.flex.flex-wrap.gap-2');
  if (actionsDiv) {
    const firstButton = actionsDiv.querySelector('a, span, button');
    if (firstButton) {
      if (offer.status === 'accepted') {
        firstButton.outerHTML = `<a href="index.php?p=payment&id=${offer.listing_id}" class="px-3 sm:px-4 py-1.5 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-lg text-xs sm:text-sm font-medium hover:opacity-90 hover:scale-105 transition-transform duration-200 flex items-center"><i class="fas fa-credit-card mr-2"></i>Make Payment</a>`;
      } else if (offer.status === 'rejected') {
        firstButton.outerHTML = `<span class="px-3 sm:px-4 py-1.5 text-xs sm:text-sm font-medium border border-gray-300 rounded-lg text-gray-600 flex items-center"><i class="fas fa-times-circle mr-2"></i>Offer Rejected</span>`;
      } else if (offer.status === 'withdrawn') {
        firstButton.outerHTML = `<span class="px-3 sm:px-4 py-1.5 text-xs sm:text-sm font-medium border border-gray-300 rounded-lg text-gray-600 flex items-center"><i class="fas fa-minus-circle mr-2"></i>Offer Withdrawn</span>`;
      }
    }
  }
  
  // Highlight the card
  card.style.backgroundColor = offer.status === 'accepted' ? '#d1fae5' : '#fee2e2';
  setTimeout(() => { card.style.backgroundColor = ''; }, 3000);
}

function handleMyListingListings(userListings) {
    console.log('🎯 Processing new listings for user:', userListings);

    // Filter for current user if not already done (though polling-init usually passes all new items, 
    // we need to be careful. usage: handleUserDashboardListings does filtering. 
    // Here we should also filter if the backend returns all listings.)
    // Note: PollingManager typically passes *newly detected* items. 
    // We should double check if they belong to us? 
    // The previous inline script did: items.filter(item => item.user_id == currentUserId)
    const currentUserId = window.currentUserId;
    if (currentUserId) {
        userListings = userListings.filter(item => item.user_id == currentUserId);
    }

    if(userListings.length === 0) return;

    userListings.forEach(item => {
        // Show notification based on change type
        const isStatusUpdate = item.change_type === 'status_changed';
        
        let message = '';
        if (isStatusUpdate) {
            message = `Listing "${item.name}" status updated to ${item.status}`;
        } else {
            message = `New listing "${item.name}" has been added!`;
        }
        
        if (window.PollingUIHelpers) {
            window.PollingUIHelpers.showBriefNotification(message, isStatusUpdate ? 'info' : 'success');
        }

        // Add to updates container logic (optional, if container exists)
        const updatesContainer = document.querySelector('#listing-updates-container');
        if (updatesContainer) {
            const div = document.createElement('div');
            div.className = "p-4 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-xl shadow-sm mb-3 animate-pulse";
            div.innerHTML = `
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
                <i class="fas fa-plus text-white"></i>
                </div>
                <div class="flex-1">
                <p class="text-sm font-semibold text-green-800">${isStatusUpdate ? 'Listing Updated' : 'New Listing Added'}</p>
                <p class="text-xs text-green-600">${item.name} - $${parseFloat(item.asking_price || 0).toLocaleString()}</p>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="text-green-500 hover:text-green-700">
                <i class="fas fa-times"></i>
                </button>
            </div>
            `;
            updatesContainer.prepend(div);
            setTimeout(() => {
                if (div.parentElement) {
                    div.style.opacity = '0';
                    div.style.transform = 'translateX(100%)';
                    setTimeout(() => div.remove(), 300);
                }
            }, 10000);
        }
    });

    // Add or update listings in the table
    userListings.forEach(item => {
        console.log('📦 Processing listing:', item.name, 'ID:', item.id, 'Change type:', item.change_type);
        if (item.change_type === 'status_changed') {
            updateListingInTable(item);
        } else {
            addListingToTable(item);
        }
    });
}

function updateListingInTable(listing) {
    console.log('🔄 Updating existing listing:', listing.name, 'ID:', listing.id);

    const existingRow = document.querySelector(`tr[data-listing-id="${listing.id}"]`);
    if (!existingRow) {
        console.log('⚠️ Listing not found in table, adding as new');
        addListingToTable(listing);
        return;
    }

    // Update status badge (Cell 2)
    const statusCell = existingRow.querySelector('td:nth-child(2)');
    if (statusCell) {
        const statusColors = {
            'pending': 'bg-yellow-100 text-yellow-800 border-yellow-200',
            'approved': 'bg-green-100 text-green-800 border-green-200',
            'rejected': 'bg-red-100 text-red-800 border-red-200'
        };
        const statusIcons = {
            'pending': 'clock',
            'approved': 'check-circle',
            'rejected': 'times-circle'
        };

        const statusClass = statusColors[listing.status] || 'bg-gray-100 text-gray-800 border-gray-200';
        const statusIcon = statusIcons[listing.status] || 'question';

        statusCell.innerHTML = `
        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium border ${statusClass}">
            <i class="fas fa-${statusIcon} mr-1"></i>
            ${listing.status.charAt(0).toUpperCase() + listing.status.slice(1)}
        </span>
        `;
    }

    // Highlight row
    existingRow.style.backgroundColor = '#fef3c7';
    setTimeout(() => { existingRow.style.backgroundColor = ''; }, 3000);
}

function addListingToTable(listing) {
    console.log('🔧 Adding new listing to table:', listing.name);

    let tbody = document.querySelector('#listings-table-container table tbody') ||
                document.querySelector('.overflow-x-auto table tbody') ||
                document.querySelector('table tbody');

    if (!tbody) { 
        // Need to check if there is a hidden table structure for empty state
        const hiddenStructure = document.querySelector('#table-structure');
        if (hiddenStructure) {
             // We are in empty state, need to hide empty msg and show table
             const emptyMsg = document.querySelector('#empty-state-message');
             if(emptyMsg) emptyMsg.style.display = 'none';
             
             hiddenStructure.style.display = 'block';
             hiddenStructure.id = ''; // remove id so we don't pick it up as hidden next time? or just use it
             hiddenStructure.classList.add('bg-white/80', 'backdrop-blur-xl', 'border', 'border-gray-100', 'rounded-2xl', 'shadow-lg', 'overflow-hidden');
             tbody = hiddenStructure.querySelector('tbody');
        } else {
             console.error('❌ Table tbody not found!');
             return; 
        }
    }

    const existingRow = document.querySelector(`tr[data-listing-id="${listing.id}"]`);
    if (existingRow) return;

    const row = document.createElement('tr');
    row.className = 'hover:bg-blue-50/50 transition-colors animate-fade-in';
    row.dataset.listingId = listing.id;
    row.style.backgroundColor = '#dbeafe';

    // Status format
    const statusColors = {
        'pending': 'bg-yellow-100 text-yellow-800 border-yellow-200',
        'approved': 'bg-green-100 text-green-800 border-green-200',
        'rejected': 'bg-red-100 text-red-800 border-red-200'
    };
    const statusIcons = {
        'pending': 'clock',
        'approved': 'check-circle',
        'rejected': 'times-circle'
    };
    const statusClass = statusColors[listing.status] || 'bg-gray-100 text-gray-800 border-gray-200';
    const statusIcon = statusIcons[listing.status] || 'question';
    const listingType = listing.type || 'website';
    const typeIcon = listingType === 'youtube' ? 'play' : 'globe';

    row.innerHTML = `
    <td class="py-3 px-3">
        <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-gradient-to-br from-blue-100 to-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <i class="fas fa-${typeIcon} text-xl text-blue-600"></i>
        </div>
        <div class="min-w-0 flex-1">
            <h3 class="font-semibold text-gray-900 truncate">${listing.name || 'Untitled Listing'}</h3>
            <p class="text-sm text-gray-600 capitalize">${listingType}</p>
            <div class="flex items-center gap-2 mt-1">
            <span class="text-xs text-gray-500">
                <i class="fas fa-calendar mr-1"></i>
                ${new Date(listing.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
            </span>
            </div>
        </div>
        </div>
    </td>
    <td class="py-3 px-3">
        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium border ${statusClass}">
        <i class="fas fa-${statusIcon} mr-1"></i>
        ${listing.status.charAt(0).toUpperCase() + listing.status.slice(1)}
        </span>
    </td>
    <td class="py-3 px-3">
        <div class="font-semibold text-gray-900 text-sm">$${parseFloat(listing.asking_price).toLocaleString()}</div>
    </td>
    <td class="py-3 px-3">
        <div class="text-xs text-gray-600">
        ${listingType === 'youtube' ? `<div><i class="fas fa-users mr-1 text-red-500"></i> ${Number(listing.subscribers || 0).toLocaleString()}</div>` : ''}
        <div><i class="fas fa-birthday-cake mr-1 text-purple-500"></i> ${listing.site_age || 'N/A'}</div>
        </div>
    </td>
    <td class="py-3 px-3">
        <div class="font-medium text-green-600 text-sm">$${parseFloat(listing.monthly_revenue).toLocaleString()}/mo</div>
    </td>
    <td class="py-3 px-3">
        <div class="text-xs text-gray-400 flex items-center"><i class="fas fa-minus-circle mr-1"></i>No proofs</div>
    </td>
    <td class="py-3 px-3">
        <div class="flex items-center gap-2">
            <a href="index.php?p=listingDetail&id=${listing.id}" class="text-blue-600 hover:text-blue-800 transition-colors text-sm" title="View"><i class="fas fa-eye"></i></a>
            <a href="index.php?p=${listingType === 'youtube' ? 'updateYtListing' : 'updateWebListing'}&id=${listing.id}" class="text-green-600 hover:text-green-800 transition-colors text-sm" title="Edit"><i class="fas fa-edit"></i></a>
            <a href="#" class="text-red-600 hover:text-red-800 transition-colors text-sm" onclick="confirmDeleteListing(event, ${listing.id}, '${listing.name}')" title="Delete"><i class="fas fa-trash"></i></a>
        </div>
    </td>
    `;

    tbody.insertBefore(row, tbody.firstChild);
    setTimeout(() => { row.style.backgroundColor = ''; }, 3000);
}

function handleOffersPageUpdate(offers) {
    console.log('💼 Offers page update:', offers.length);
    if (offers.length > 0) {
        showUpdateNotification(`${offers.length} offers updated`);
        setTimeout(() => location.reload(), 1000);
    }
}

function handleOrdersUpdate(transactions) {
    console.log('🛒 Orders update:', transactions.length);
    if (transactions.length > 0) {
        showUpdateNotification(`${transactions.length} order update${transactions.length > 1 ? 's' : ''}!`);
        setTimeout(() => location.reload(), 2000);
    }
}

function handleListingVerificationUpdate(listings) {
     if (listings.length > 0) {
         showUpdateNotification(`${listings.length} listings updated`);
         // Simplest approach for admin table
         setTimeout(() => location.reload(), 2000);
     }
}

function handleListingPageUpdate(listings) {
    console.log('📋 Listing page update:', listings.length);
    if (listings.length > 0) {
         showUpdateNotification(`${listings.length} new listings available!`);
         // Ideally prepend to grid, but reload is safer for complex grids
        setTimeout(() => location.reload(), 1500);
    }
}

function handleHomePageUpdate(listings) {
    console.log('🏠 Home page update:', listings.length);
    
    if (listings.length > 0) {
        // Filter only approved listings for home page
        const approvedListings = listings.filter(listing => 
            listing.status === 'approved' &&
            listing.name && 
            listing.name.trim() !== ''
        );
        
        if (approvedListings.length > 0) {
            console.log(`🎉 Found ${approvedListings.length} new approved listings for home page`);
            showUpdateNotification(`${approvedListings.length} new listing${approvedListings.length > 1 ? 's' : ''} available!`);
            // Reload to update the grid correctly
            setTimeout(() => location.reload(), 2000);
        }
    }
}

function handleSuperAdminReportsListings(newListings) {
    if (newListings.length > 0) {
        if (window.PollingUIHelpers) {
            window.PollingUIHelpers.showBriefNotification(`${newListings.length} new listings`, 'info');
            window.PollingUIHelpers.updateStatsCounter('#total-listings', newListings.length);
        }
    }
}

function handleSuperAdminReportsOrders(newOrders) {
    let revenueIncrease = 0;
    let completedCount = 0;

    newOrders.forEach(o => {
        if (o.status === 'completed') {
            completedCount++;
            revenueIncrease += parseFloat(o.amount || 0);
        }
    });

    if (newOrders.length > 0) {
        if (window.PollingUIHelpers) {
            window.PollingUIHelpers.showBriefNotification(`${newOrders.length} new orders`, 'success');
        }
    }

    if (completedCount > 0 && window.PollingUIHelpers) {
         window.PollingUIHelpers.updateStatsCounter('#completed-orders', completedCount);
    }

    if (revenueIncrease > 0) {
        const el = document.getElementById('total-revenue');
        if (el) {
            const currentText = el.textContent.replace(/[$,\s]/g, '');
            const current = parseFloat(currentText) || 0;
            const newTotal = current + revenueIncrease;
            // Format back to currency string
            el.textContent = '$' + newTotal.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});

            // Animation
            el.classList.add('text-green-200');
            setTimeout(() => el.classList.remove('text-green-200'), 1000);
        }
    }

    // Add to recent transactions table
    const tbody = document.getElementById('transactions-table');
    if (tbody && newOrders.length > 0) {
        // Only if we have new orders
        newOrders.forEach(txn => {
             const row = document.createElement('tr');
             row.className = 'hover:bg-gray-50 bg-blue-50 animate-fade-in';
             
             // Check if data is strings or dates
             const dateStr = txn.created_at ? new Date(txn.created_at).toLocaleDateString() : 'Just now';
             const amountStr = (parseFloat(txn.amount)||0).toLocaleString();
             const statusStr = txn.status ? (txn.status.charAt(0).toUpperCase() + txn.status.slice(1)) : 'Unknown';
             const statusClass = (txn.status == 'completed') ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';

             row.innerHTML = `
                <td class="py-4 px-6">
                  <span class="font-medium text-gray-900">ORD${txn.id}</span>
                </td>
                <td class="py-4 px-6">
                  <span class="text-gray-900">${(txn.listing_name || 'N/A').replace(/'/g, "&#39;")}</span>
                </td>
                <td class="py-4 px-6">
                  <span class="text-gray-700">${(txn.buyer_name || 'N/A').replace(/'/g, "&#39;")}</span>
                </td>
                <td class="py-4 px-6">
                  <span class="text-gray-700">${(txn.seller_name || 'N/A').replace(/'/g, "&#39;")}</span>
                </td>
                <td class="py-4 px-6">
                  <span class="font-semibold text-gray-900">$${amountStr}</span>
                </td>
                <td class="py-4 px-6">
                  <span class="px-2 py-1 rounded-full text-xs font-medium ${statusClass}">
                    ${statusStr}
                  </span>
                </td>
                <td class="py-4 px-6">
                  <span class="text-sm text-gray-600">${dateStr}</span>
                </td>
             `;

             // Remove "No transactions" row if present
             if (tbody.children.length === 1 && tbody.firstElementChild.innerText.includes('No transactions')) {
                 tbody.innerHTML = '';
             }

             // Add as first child
             if (tbody.firstChild) {
               tbody.insertBefore(row, tbody.firstChild);
             } else {
               tbody.appendChild(row);
             }

             // Clean up highlight
             setTimeout(() => row.classList.remove('bg-blue-50'), 3000);
        });
        
        // Limit rows
        while (tbody.children.length > 10) {
            tbody.lastElementChild.remove();
        }
    }
}


// Utility functions
function showUpdateNotification(message, type = 'success') {
    if (window.PollingUIHelpers) {
        window.PollingUIHelpers.showBriefNotification(message, type);
    } else {
        console.log('📢', message);
    }
}

function showBrowserNotification(notification) {
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(notification.title, {
            body: notification.message
            // icon removed to prevent 404
        });
    }
}

// Legacy compatibility - Queue callbacks if manager not ready
window.pendingCallbacks = {};
window.startPolling = function(callbacks) {
    console.log('🔄 Legacy startPolling called', callbacks);
    if (window.globalPollingManager) {
        console.log('✅ Merging into active manager');
        window.globalPollingManager.renderCallbacks = { ...window.globalPollingManager.renderCallbacks, ...callbacks };
    } else {
        console.log('⏳ Manager not ready, queueing callbacks');
        window.pendingCallbacks = { ...window.pendingCallbacks, ...callbacks };
    }
};

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    console.log('🎯 DOM ready - initializing polling system');
    
    // Add visual debug indicator (small dot)
    const debugIndicator = document.createElement('div');
    debugIndicator.id = 'polling-status-indicator';
    debugIndicator.style.cssText = 'position: fixed; bottom: 10px; left: 10px; width: 8px; height: 8px; border-radius: 50%; background-color: #ffa500; z-index: 10000; pointer-events: none; opacity: 0.5;';
    debugIndicator.title = 'Polling Status: Initializing';
    document.body.appendChild(debugIndicator);

    setTimeout(() => {
        initializePolling();
        
        // Update indicator
        if (window.globalPollingManager && window.globalPollingManager.isPolling) {
            debugIndicator.style.backgroundColor = '#28a745'; // Green
            debugIndicator.title = 'Polling Active';
        } else {
            debugIndicator.style.backgroundColor = '#ccc'; // Gray
            debugIndicator.title = 'Polling Inactive';
        }
    }, 500); 
});

// Export for manual initialization
window.initializePolling = initializePolling;