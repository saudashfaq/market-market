// Simple Real-Time Polling for Offers
console.log('🚀 Simple offers polling system starting...');

// Simple polling configuration
const POLLING_CONFIG = {
  interval: 10000, // 10 seconds
  maxRetries: 3,
  currentRetries: 0,
  isActive: true,
  lastCheck: '1970-01-01 00:00:00'
};

// Get current user ID from global variable (set by PHP)
let CURRENT_USER_ID = null;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
  // Get user ID from a global variable that should be set by PHP
  if (typeof window.currentUserId !== 'undefined') {
    CURRENT_USER_ID = window.currentUserId;
  } else {
    console.error('❌ Current user ID not found. Make sure it\'s set by PHP.');
    return;
  }

  console.log('👤 Current user ID:', CURRENT_USER_ID);
  
  // Initialize PathDetector if available
  initializePathDetector();

  // Start polling after 2 seconds
  setTimeout(() => {
    console.log('🚀 Starting initial offer check...');
    checkForNewOffers();
  }, 2000);

  // Set up regular polling
  setInterval(() => {
    if (POLLING_CONFIG.isActive) {
      checkForNewOffers();
    }
  }, POLLING_CONFIG.interval);

  console.log(`✅ Polling started with ${POLLING_CONFIG.interval / 1000}s intervals`);
});

// Simple polling function
function checkForNewOffers() {
  if (!POLLING_CONFIG.isActive) {
    console.log('⏸️ Polling is paused');
    return;
  }

  console.log('🔍 Checking for new offers...');

  // Build API URL using centralized PathDetector
  let apiUrl;
  
  if (window.pathDetector) {
    // Use PathDetector for consistent path detection
    apiUrl = window.pathDetector.buildApiUrl('/api/polling_integration.php');
    const detectionInfo = window.pathDetector.getDetectionInfo();
    
    console.log('📡 Using PathDetector for URL construction');
    console.log('📡 Detection info:', detectionInfo);
  } else {
    // Fallback to improved manual detection if PathDetector not available
    console.warn('⚠️ PathDetector not available, using fallback logic');
    const currentPath = window.location.pathname;
    let basePath = '';

    if (currentPath.includes('/public/')) {
      basePath = currentPath.substring(0, currentPath.indexOf('/public/'));
    } else if (currentPath.includes('/modules/')) {
      basePath = currentPath.substring(0, currentPath.indexOf('/modules/'));
    } else if (currentPath.includes('/index.php')) {
      basePath = currentPath.substring(0, currentPath.indexOf('/index.php'));
    } else {
      // Improved fallback logic for different environments
      const hostname = window.location.hostname;
      const ipPattern = /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/;
      
      if (ipPattern.test(hostname)) {
        // Production server with IP address - use empty base path
        basePath = '';
      } else if (hostname === 'localhost' || hostname === '127.0.0.1' || hostname.includes('local')) {
        // Development environment - use /marketplace
        basePath = '/marketplace';
      } else {
        // Default fallback
        basePath = '/marketplace';
      }
    }

    apiUrl = window.location.origin + basePath + '/api/polling_integration.php';
    console.log('📡 Fallback base path detected:', basePath);
  }
  const payload = {
    offers: POLLING_CONFIG.lastCheck
  };

  console.log('📡 API URL:', apiUrl);
  console.log('📤 Payload:', payload);

  fetch(apiUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Cache-Control': 'no-cache'
    },
    credentials: 'same-origin',
    body: JSON.stringify(payload)
  })
    .then(response => {
      console.log('📡 Response status:', response.status);
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(data => {
      console.log('📦 Response data:', data);

      if (data.success) {
        // Reset retry counter on success
        POLLING_CONFIG.currentRetries = 0;

        // Update timestamp
        if (data.timestamps && data.timestamps.offers) {
          POLLING_CONFIG.lastCheck = data.timestamps.offers;
          console.log('🕐 Updated timestamp to:', POLLING_CONFIG.lastCheck);
        }

        // Check for new offers
        if (data.data && data.data.offers && data.data.offers.length > 0) {
          console.log(`📊 Found ${data.data.offers.length} offers`);

          // Filter for offers where current user is the seller OR the buyer
          const myOffers = data.data.offers.filter(offer => {
            const isMyOffer = offer.seller_id == CURRENT_USER_ID || offer.user_id == CURRENT_USER_ID;
            console.log(`🔍 Offer ${offer.id}: seller_id=${offer.seller_id}, buyer_id=${offer.user_id}, match=${isMyOffer}`);
            return isMyOffer;
          });

          console.log(`👤 Found ${myOffers.length} relevant offers`);

          if (myOffers.length > 0) {
            console.log('🎉 New/Updated offers detected! Updating table...');
            showNewOfferNotification(myOffers.length);

            // Update table dynamically without reload
            updateOffersTable(myOffers);
          }
        } else {
          console.log('ℹ️ No new offers found');
        }
      } else {
        console.error('❌ API error:', data.message || data.error);
        handlePollingError(new Error(data.message || data.error || 'Unknown API error'));
      }
    })
    .catch(error => {
      console.error('❌ Polling error:', error);
      handlePollingError(error);
    });
}

// Function to update the offers table dynamically
function updateOffersTable(offers) {
  const tbody = document.querySelector('table tbody');
  if (!tbody) return;

  // Remove "No Offers Found" row if it exists
  const emptyRow = tbody.querySelector('tr td[colspan="10"]');
  if (emptyRow) {
    emptyRow.closest('tr').remove();
  }

  offers.forEach(offer => {
    // Check if row exists
    const existingRow = tbody.querySelector(`tr[data-record-id="${offer.id}"]`);

    if (existingRow) {
      // Update existing row
      console.log(`🔄 Updating existing row for offer ${offer.id}`);
      updateOfferRow(existingRow, offer);

      // Highlight update
      existingRow.classList.add('bg-yellow-50');
      setTimeout(() => existingRow.classList.remove('bg-yellow-50'), 3000);
    } else {
      // Create new row
      console.log(`➕ Adding new row for offer ${offer.id}`);
      const newRowHTML = generateOfferRowHTML(offer, tbody.children.length + 1);
      const newRow = document.createElement('tr');
      newRow.className = 'hover:bg-gray-50 transition-all duration-150 animate-fade-in bg-green-50';
      newRow.setAttribute('data-record-id', offer.id);
      newRow.innerHTML = newRowHTML;

      // Prepend to table
      tbody.insertBefore(newRow, tbody.firstChild);

      // Remove highlight after animation
      setTimeout(() => newRow.classList.remove('bg-green-50'), 3000);
    }
  });
}

// Helper to update an existing row
function updateOfferRow(row, offer) {
  // Update Status Column (8th column, index 7)
  const statusCell = row.children[7];
  if (statusCell) {
    let statusHTML = '';
    if (offer.status === 'pending') {
      statusHTML = `<span class="bg-yellow-100 text-yellow-700 text-[11px] sm:text-xs px-3 py-1 rounded-full font-medium inline-flex items-center"><i class="fas fa-clock mr-1"></i>Pending</span>`;
    } else if (offer.status === 'accepted') {
      statusHTML = `<span class="bg-green-100 text-green-700 text-[11px] sm:text-xs px-3 py-1 rounded-full font-medium inline-flex items-center"><i class="fas fa-check-circle mr-1"></i>Accepted</span>`;
    } else {
      statusHTML = `<span class="bg-gray-100 text-gray-600 text-[11px] sm:text-xs px-3 py-1 rounded-full font-medium inline-flex items-center"><i class="fas fa-ban mr-1"></i>${offer.status.charAt(0).toUpperCase() + offer.status.slice(1)}</span>`;
    }
    statusCell.innerHTML = statusHTML;
  }

  // Update Actions Column (10th column, index 9)
  const actionsCell = row.children[9];
  if (actionsCell) {
    actionsCell.innerHTML = generateActionsHTML(offer);
  }
}

// Helper to generate full row HTML
function generateOfferRowHTML(offer, index) {
  const isLowerOffer = (offer.total_offers_count > 1 && parseFloat(offer.amount) < parseFloat(offer.max_offer_amount));
  const isHighestOffer = (parseFloat(offer.amount) == parseFloat(offer.max_offer_amount) && offer.total_offers_count > 1);

  return `
    <td class="px-4 sm:px-6 py-3 font-semibold text-gray-600">${index}</td>
    <td class="px-4 sm:px-6 py-3">
        <div class="flex items-center">
            <i class="fas fa-file-alt mr-2 text-blue-500 ${offer.total_offers_count > 1 ? 'multiple-offers-indicator' : ''}"></i>
            <div class="flex flex-col">
                <span class="font-medium truncate">${offer.listing_name || 'Unknown Listing'}</span>
                ${offer.total_offers_count > 1 ? `
                    <span class="text-xs text-orange-600 flex items-center mt-1 font-medium">
                        <i class="fas fa-users mr-1"></i>
                        ${offer.total_offers_count} competing offers
                    </span>
                ` : ''}
            </div>
        </div>
    </td>
    <td class="px-4 sm:px-6 py-3 whitespace-nowrap">
        <i class="fas fa-folder mr-1 text-orange-500"></i>
        ${offer.listing_category ? offer.listing_category.charAt(0).toUpperCase() + offer.listing_category.slice(1) : 'N/A'}
    </td>
    <td class="px-4 sm:px-6 py-3 text-gray-800 font-medium hidden md:table-cell">
        <div class="flex items-center">
            <i class="fas fa-dollar-sign mr-1 text-green-500"></i>
            ${parseFloat(offer.listing_price || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
        </div>
    </td>
    <td class="px-4 sm:px-6 py-3 whitespace-nowrap">
        ${isLowerOffer ? `
            <div class="flex items-center text-gray-500">
                <i class="fas fa-hand-holding-usd mr-1 text-gray-400"></i>
                <span class="font-medium line-through">$${parseFloat(offer.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                <span class="ml-2 text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded-full">Lower</span>
            </div>
        ` : `
            <div class="flex items-center text-blue-600 font-semibold">
                <i class="fas fa-hand-holding-usd mr-1"></i>
                <span>$${parseFloat(offer.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                ${isHighestOffer ? `<span class="ml-2 text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">Highest</span>` : ''}
            </div>
        `}
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
                    <p class="text-sm italic text-gray-700 line-clamp-2" title="${offer.message.replace(/"/g, '&quot;')}">
                        "${offer.message}"
                    </p>
                </div>
            </div>
        ` : `
            <span class="text-gray-400"><i class="fas fa-comment-slash mr-1"></i>No message</span>
        `}
    </td>
    <td class="px-4 sm:px-6 py-3">
        ${offer.status === 'pending' ? `
            <span class="bg-yellow-100 text-yellow-700 text-[11px] sm:text-xs px-3 py-1 rounded-full font-medium inline-flex items-center">
                <i class="fas fa-clock mr-1"></i>Pending
            </span>
        ` : (offer.status === 'accepted' ? `
            <span class="bg-green-100 text-green-700 text-[11px] sm:text-xs px-3 py-1 rounded-full font-medium inline-flex items-center">
                <i class="fas fa-check-circle mr-1"></i>Accepted
            </span>
        ` : `
            <span class="bg-gray-100 text-gray-600 text-[11px] sm:text-xs px-3 py-1 rounded-full font-medium inline-flex items-center">
                <i class="fas fa-ban mr-1"></i>${offer.status.charAt(0).toUpperCase() + offer.status.slice(1)}
            </span>
        `)}
    </td>
    <td class="px-4 sm:px-6 py-3 text-xs text-gray-500 hidden md:table-cell whitespace-nowrap">
        <i class="fas fa-calendar-day mr-1"></i>
        ${new Date(offer.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}
    </td>
    <td class="px-4 sm:px-6 py-3">
        ${generateActionsHTML(offer)}
    </td>
  `;
}

// Helper to generate actions HTML
function generateActionsHTML(offer) {
  const isLowerOffer = (offer.total_offers_count > 1 && parseFloat(offer.amount) < parseFloat(offer.max_offer_amount));

  if (offer.status === 'pending') {
    if (isLowerOffer) {
      return `
        <div class="flex gap-2 sm:gap-3">
            <button class="text-blue-600 hover:text-blue-800 text-base sm:text-lg" title="View offer details"><i class="fas fa-eye"></i></button>
            <span class="text-gray-400 text-base sm:text-lg opacity-40 cursor-not-allowed" title="Cannot accept lower offer"><i class="fas fa-check-circle"></i></span>
            <button type="button" class="text-red-600 hover:text-red-800 text-base sm:text-lg border-none bg-transparent cursor-pointer" title="Reject this lower offer" data-offer-id="${offer.id}" onclick="handleRejectOfferFromData(this)"><i class="fas fa-times-circle"></i></button>
        </div>
        <p class="text-xs text-gray-500 mt-1">Lower offer</p>
      `;
    } else {
      return `
        <div class="flex gap-2 sm:gap-3">
            <button class="text-blue-600 hover:text-blue-800 text-base sm:text-lg" title="View offer details"><i class="fas fa-eye"></i></button>
            <button type="button" class="text-green-600 hover:text-green-800 text-base sm:text-lg border-none bg-transparent cursor-pointer" title="Accept this offer" data-offer-id="${offer.id}" data-listing-name="${offer.listing_name}" data-amount="${offer.amount}" data-total-offers="${offer.total_offers_count}" onclick="handleAcceptOfferFromData(this)"><i class="fas fa-check-circle"></i></button>
            <button type="button" class="text-red-600 hover:text-red-800 text-base sm:text-lg border-none bg-transparent cursor-pointer" title="Reject this offer" data-offer-id="${offer.id}" data-listing-name="${offer.listing_name}" data-amount="${offer.amount}" onclick="handleRejectOfferFromData(this)"><i class="fas fa-times-circle"></i></button>
        </div>
      `;
    }
  } else {
    return `
        <span class="text-gray-400 text-xs sm:text-sm flex items-center">
            <i class="fas fa-minus mr-1"></i>Done
        </span>
    `;
  }
}

// Handle polling errors with retry logic and path detection
async function handlePollingError(error) {
  POLLING_CONFIG.currentRetries++;
  console.error(`❌ Polling error (${POLLING_CONFIG.currentRetries}/${POLLING_CONFIG.maxRetries}):`, error.message);

  // If it's a 404 error and we have PathDetector, try alternative paths
  if (error.message.includes('404') && window.pathDetector && POLLING_CONFIG.currentRetries <= 2) {
    console.log('🔄 404 error detected, trying alternative path configurations...');
    
    try {
      // Reset PathDetector cache to force re-detection
      window.pathDetector.reset();
      
      // Try alternative path detection methods
      const alternativePaths = getAlternativePaths();
      
      for (const altPath of alternativePaths) {
        console.log(`🧪 Testing alternative path: ${altPath}`);
        
        const testUrl = window.location.origin + altPath + '/api/polling_integration.php';
        
        try {
          // Test the alternative path with a simple HEAD request
          const testResponse = await fetch(testUrl, { 
            method: 'HEAD',
            timeout: 5000 
          });
          
          if (testResponse.ok || testResponse.status === 401) { // 401 is also acceptable (auth required)
            console.log(`✅ Alternative path works: ${altPath}`);
            
            // Temporarily override PathDetector's cached result
            if (window.pathDetector) {
              window.pathDetector.cachedBasePath = altPath;
              window.pathDetector.detectionMethod = 'error-recovery';
            }
            
            // Retry the polling request immediately
            setTimeout(() => checkForNewOffers(), 1000);
            return;
          }
        } catch (testError) {
          console.log(`❌ Alternative path failed: ${altPath} - ${testError.message}`);
        }
      }
      
      console.log('⚠️ No alternative paths worked, continuing with normal error handling');
    } catch (retryError) {
      console.error('❌ Error during path retry logic:', retryError);
    }
  }

  // Normal error handling
  if (POLLING_CONFIG.currentRetries >= POLLING_CONFIG.maxRetries) {
    console.error('❌ Max retries reached. Stopping polling.');
    POLLING_CONFIG.isActive = false;
    
    // Show user notification with debugging info
    const debugInfo = window.pathDetector ? window.pathDetector.getDetectionInfo() : null;
    const message = debugInfo ? 
      `Connection lost. Current path: ${debugInfo.basePath || 'empty'}. Please refresh the page.` :
      'Connection lost. Please refresh the page.';
    
    showErrorNotification(message);
  }
}

// Get alternative paths to try when main path fails
function getAlternativePaths() {
  const hostname = window.location.hostname;
  
  // Common alternative paths based on different server configurations
  const alternatives = [];
  
  // If currently using empty path, try /marketplace
  if (window.pathDetector && window.pathDetector.cachedBasePath === '') {
    alternatives.push('/marketplace');
  }
  
  // If currently using /marketplace, try empty path
  if (window.pathDetector && window.pathDetector.cachedBasePath === '/marketplace') {
    alternatives.push('');
  }
  
  // Add other common patterns
  alternatives.push('/marketplace/public', '');
  
  // Remove duplicates and current path
  const currentPath = window.pathDetector ? window.pathDetector.cachedBasePath : '';
  return [...new Set(alternatives)].filter(path => path !== currentPath);
}

// Show new offer notification
function showNewOfferNotification(count) {
  // Remove any existing notification
  const existing = document.getElementById('new-offer-notification');
  if (existing) {
    existing.remove();
  }

  // Create notification
  const notification = document.createElement('div');
  notification.id = 'new-offer-notification';
  notification.style.cssText = `
    position: fixed; top: 20px; right: 20px; z-index: 9999;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white; padding: 16px 24px; border-radius: 12px;
    box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-weight: 600; font-size: 14px;
    animation: slideIn 0.3s ease-out;
    border: 2px solid rgba(255, 255, 255, 0.2);
  `;

  notification.innerHTML = `
    <div style="display: flex; align-items: center; gap: 12px;">
      <div style="font-size: 24px;">🎉</div>
      <div>
        <div style="font-weight: 700; margin-bottom: 4px;">
          ${count} New Offer${count > 1 ? 's' : ''} Received!
        </div>
        <div style="font-size: 12px; opacity: 0.9;">
          Table updated automatically
        </div>
      </div>
    </div>
  `;

  document.body.appendChild(notification);

  // Add animation styles if not already present
  if (!document.getElementById('notification-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
      @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
      }
    `;
    document.head.appendChild(style);
  }

  // Auto remove after 5 seconds
  setTimeout(() => {
    if (notification.parentNode) {
      notification.remove();
    }
  }, 5000);
}

// Show error notification
function showErrorNotification(message) {
  // Remove any existing notification
  const existing = document.getElementById('error-notification');
  if (existing) {
    existing.remove();
  }

  // Create notification
  const notification = document.createElement('div');
  notification.id = 'error-notification';
  notification.style.cssText = `
    position: fixed; top: 20px; right: 20px; z-index: 9999;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white; padding: 16px 24px; border-radius: 12px;
    box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-weight: 600; font-size: 14px;
    animation: slideIn 0.3s ease-out;
    border: 2px solid rgba(255, 255, 255, 0.2);
  `;

  notification.innerHTML = `
    <div style="display: flex; align-items: center; gap: 12px;">
      <div style="font-size: 20px;">⚠️</div>
      <div>
        <div style="font-weight: 700; margin-bottom: 4px;">Connection Error</div>
        <div style="font-size: 12px; opacity: 0.9;">${message}</div>
      </div>
      <button onclick="location.reload()" style="
        background: rgba(255, 255, 255, 0.2); border: none; color: white;
        padding: 6px 12px; border-radius: 6px; font-size: 12px;
        cursor: pointer; font-weight: 600; margin-left: 8px;
      ">
        Refresh
      </button>
    </div>
  `;

  document.body.appendChild(notification);
}

// Manual refresh function for testing
function testOfferPolling() {
  console.log('🧪 Manual test triggered');
  POLLING_CONFIG.currentRetries = 0;
  POLLING_CONFIG.isActive = true;
  checkForNewOffers();
}

// Make test function available globally
window.testOfferPolling = testOfferPolling;

// Pause/resume polling when page visibility changes
document.addEventListener('visibilitychange', function () {
  if (document.hidden) {
    console.log('📱 Page hidden, pausing polling');
    POLLING_CONFIG.isActive = false;
  } else {
    console.log('📱 Page visible, resuming polling');
    POLLING_CONFIG.isActive = true;
    POLLING_CONFIG.currentRetries = 0;
    // Check immediately when page becomes visible
    setTimeout(checkForNewOffers, 1000);
  }
});

console.log('✅ Simple offers polling system loaded');
console.log('🛠️ Test function: testOfferPolling()');