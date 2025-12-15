if (typeof PollingManager === 'undefined') {
    class PollingManager {
      constructor() {
        this.lastCheckTimes = this.loadTimestamps();
        this.pollingInterval = null;
        this.isPolling = false;
        this.errorCount = 0;
        this.maxErrors = 5;
        this.baseInterval = 5000; // 5 seconds for better debugging
        this.currentInterval = this.baseInterval;
        this.renderCallbacks = {};
        this.isUserActive = true;
        
        console.log('🎯 PollingManager initialized');
        console.log('📊 Initial timestamps:', this.lastCheckTimes);
        
        // Initialize PathDetector if available
        this.initializePathDetector();
        
        // Track user activity
        this.setupActivityTracking();
      }

      // Initialize PathDetector utility
      initializePathDetector() {
        if (window.pathDetector) {
          console.log('✅ PathDetector available');
          // Enable debug mode in development
          if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            window.pathDetector.enableDebug();
          }
        } else {
          console.warn('⚠️ PathDetector not available - using fallback path detection');
        }
      }

      // Load timestamps from localStorage with fallback
      loadTimestamps() {
        try {
          const stored = localStorage.getItem('polling_timestamps');
          if (stored) {
            return JSON.parse(stored);
          }
        } catch (e) {
          console.warn('Failed to load stored timestamps:', e);
        }
        
        return {
          listings: '1970-01-01 00:00:00',
          offers: '1970-01-01 00:00:00', 
          orders: '1970-01-01 00:00:00',
          transactions: '1970-01-01 00:00:00',
          notifications: '1970-01-01 00:00:00'
        };
      }

      // Save timestamps to localStorage
      saveTimestamps() {
        try {
          localStorage.setItem('polling_timestamps', JSON.stringify(this.lastCheckTimes));
        } catch (e) {
          console.warn('Failed to save timestamps:', e);
        }
      }

      // Setup user activity tracking
      setupActivityTracking() {
        let activityTimer;
        const resetTimer = () => {
          this.isUserActive = true;
          clearTimeout(activityTimer);
          activityTimer = setTimeout(() => {
            this.isUserActive = false;
            console.log('User inactive - reducing polling frequency');
          }, 300000); // 5 minutes of inactivity
        };

        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'].forEach(event => {
          document.addEventListener(event, resetTimer, { passive: true });
        });
        
        resetTimer(); // Initialize
      }

      // Start polling with callbacks
      start(renderCallbacks = {}) {
        if (this.isPolling) {
          console.warn('⚠️ Polling already started');
          return;
        }

        this.renderCallbacks = renderCallbacks;
        this.isPolling = true;
        this.errorCount = 0;
        
        console.log('✅ Starting polling with ' + (this.baseInterval/1000) + '-second intervals');
        console.log('📋 Registered callbacks:', Object.keys(renderCallbacks));
        
        // Start first poll immediately
        this.fetchUpdates();
      }

      // Stop polling
      stop() {
        if (this.pollingInterval) {
          clearTimeout(this.pollingInterval);
          this.pollingInterval = null;
        }
        this.isPolling = false;
        console.log('Polling stopped');
      }

      // Schedule next poll with dynamic interval
      scheduleNextPoll() {
        if (!this.isPolling) return;

        // Adjust interval based on user activity and errors
        let interval = this.baseInterval;
        if (!this.isUserActive) {
          interval *= 3; // 30 seconds when inactive
        }
        if (this.errorCount > 0) {
          interval *= Math.min(Math.pow(2, this.errorCount), 8); // Exponential backoff, max 8x
        }

        this.pollingInterval = setTimeout(() => {
          this.fetchUpdates();
        }, interval);
      }

      // Fetch incremental updates
      async fetchUpdates() {
        if (!this.isPolling) return;

        console.log('🔄 Fetching updates...');
        console.log('📅 Last check times:', this.lastCheckTimes);

        try {
          // Build correct polling URL using centralized configuration
          let pollingUrl;
          
          if (window.POLLING_URL) {
             pollingUrl = window.POLLING_URL;
             console.log('📡 Polling URL (from config):', pollingUrl);
          } else if (window.API_BASE_PATH) {
            // ... (Legacy/Fallback logic if POLLING_URL missing) ...
            if (window.API_BASE_PATH.startsWith('http')) {
                pollingUrl = window.API_BASE_PATH + '/polling_integration.php';
            } else {
                pollingUrl = window.location.origin + window.API_BASE_PATH + '/polling_integration.php';
            }
            console.log('📡 Polling URL (using API_BASE_PATH fallback):', pollingUrl);
          } else if (window.pathDetector) {
            pollingUrl = window.pathDetector.buildApiUrl('/api/polling_integration.php');
            console.log('📡 Polling URL (PathDetector):', pollingUrl);
          } else {
            console.warn('⚠️ No URL config found, using emergency fallback');
            pollingUrl = window.location.origin + '/api/polling_integration.php';
          }
          
          console.log('📤 Sending payload:', JSON.stringify(this.lastCheckTimes));
          
          const response = await fetch(pollingUrl, {
            method: 'POST',
            headers: { 
              'Content-Type': 'application/json',
              'Cache-Control': 'no-cache'
            },
            credentials: 'same-origin',
            body: JSON.stringify(this.lastCheckTimes)
          });

          console.log('📡 Response status:', response.status, response.statusText);
          console.log('📡 Response headers:', {
            'content-type': response.headers.get('content-type'),
            'content-length': response.headers.get('content-length')
          });

          if (!response.ok) {
            const errorText = await response.text();
            console.error('❌ Response error (status ' + response.status + '):', errorText.substring(0, 500));
            console.error('❌ Failed URL:', pollingUrl);
            console.error('❌ Request payload:', JSON.stringify(this.lastCheckTimes));
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
          }

          const responseText = await response.text();
          console.log('📥 Raw response:', responseText.substring(0, 200));
          
          let result;
          try {
            result = JSON.parse(responseText);
            console.log('📦 Parsed response data:', result);
          } catch (parseError) {
            console.error('❌ JSON parse error:', parseError.message);
            console.error('❌ Response text:', responseText.substring(0, 500));
            throw new Error('Invalid JSON response');
          }
          
          if (!result.success) {
            throw new Error(result.message || result.error || 'Unknown error');
          }

          // Reset error count on successful request
          this.errorCount = 0;
          
          // Process new data
          this.handleResponse(result);
          
        } catch (error) {
          this.handleError(error);
        }

        // Schedule next poll
        this.scheduleNextPoll();
      }

      // Handle successful response
      handleResponse(result) {
        const { data, timestamps } = result;
        
        console.log('✅ Processing response...');
        
        if (!data || !timestamps) {
          console.warn('⚠️ Invalid response format:', result);
          return;
        }

        // Process each data type
        Object.keys(data).forEach(table => {
          const newRecords = data[table];
          console.log(`📊 ${table}:`, newRecords.length, 'records');
          
          if (Array.isArray(newRecords) && newRecords.length > 0) {
            console.log(`✨ Received ${newRecords.length} new ${table} records`);
            console.log(`📋 ${table} data:`, newRecords);
            
            // Special handling for notifications - update NotificationManager
            if (table === 'notifications' && window.notificationManager) {
              console.log('🔔 Updating NotificationManager with new notifications');
              window.notificationManager.handleNewNotifications(newRecords);
            }
            
            // Call render callback if provided
            if (this.renderCallbacks[table]) {
              console.log(`🎯 Calling ${table} callback...`);
              try {
                this.renderCallbacks[table](newRecords);
                console.log(`✅ ${table} callback completed`);
              } catch (e) {
                console.error(`❌ Error in ${table} callback:`, e);
              }
            } else {
              console.warn(`⚠️ No callback registered for ${table}`);
            }
          }
        });

        // Update timestamps
        console.log('🕐 Updating timestamps...');
        Object.keys(timestamps).forEach(table => {
          if (timestamps[table]) {
            console.log(`  ${table}: ${this.lastCheckTimes[table]} → ${timestamps[table]}`);
            this.lastCheckTimes[table] = timestamps[table];
          }
        });

        // Save timestamps to localStorage
        this.saveTimestamps();
        console.log('💾 Timestamps saved');
      }

      // Handle polling errors with retry logic
      async handleError(error) {
        this.errorCount++;
        console.error(`Polling error (${this.errorCount}/${this.maxErrors}):`, error);

        // If it's a 404 error and we have PathDetector, try alternative paths
        if (error.message.includes('404') && window.pathDetector && this.errorCount <= 2) {
          console.log('🔄 404 error detected, trying alternative path configurations...');
          
          try {
            // Reset PathDetector cache to force re-detection
            window.pathDetector.reset();
            
            // Try alternative path detection methods
            const alternativePaths = this.getAlternativePaths();
            
            for (const altPath of alternativePaths) {
              console.log(`🧪 Testing alternative path: ${altPath}`);
              
              const testUrl = window.location.origin + altPath + '/api/polling_integration.php';
              
              try {
                // Test the alternative path with a simple HEAD request
                const testResponse = await fetch(testUrl, { 
                  method: 'HEAD',
                  timeout: 5000 
                });
                
                if (testResponse.ok) {
                  console.log(`✅ Alternative path works: ${altPath}`);
                  
                  // Temporarily override PathDetector's cached result
                  window.pathDetector.cachedBasePath = altPath;
                  window.pathDetector.detectionMethod = 'error-recovery';
                  
                  // Retry the polling request immediately
                  setTimeout(() => this.fetchUpdates(), 1000);
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
        if (this.errorCount >= this.maxErrors) {
          console.error('Max polling errors reached. Stopping polling.');
          this.stop();
          
          // Show user notification with debugging info
          // const debugInfo = window.pathDetector ? window.pathDetector.getDetectionInfo() : null;
          // const message = debugInfo ? 
          //   `Connection lost. Current path: ${debugInfo.basePath || 'empty'}. Please refresh the page.` :
          //   'Connection lost. Please refresh the page.';
          
          this.showErrorNotification(message);
        }
      }

      // Get alternative paths to try when main path fails
      getAlternativePaths() {
        const hostname = window.location.hostname;
        const pathname = window.location.pathname;
        
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

      // Show error notification to user
      showErrorNotification(message) {
        // Create or update notification element
        let notification = document.getElementById('polling-error-notification');
        if (!notification) {
          notification = document.createElement('div');
          notification.id = 'polling-error-notification';
          notification.className = 'fixed top-4 right-4 bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
          document.body.appendChild(notification);
        }
        
        notification.innerHTML = `
          <div class="flex items-center gap-2">
            <i class="fas fa-exclamation-triangle"></i>
            <span>${message}</span>
            <button onclick="location.reload()" class="ml-2 bg-red-600 px-2 py-1 rounded text-sm hover:bg-red-700">
              Refresh
            </button>
          </div>
        `;
      }

      // Manual refresh method
      refresh() {
        console.log('Manual refresh triggered');
        this.errorCount = 0;
        if (!this.isPolling) {
          this.start(this.renderCallbacks);
        } else {
          this.fetchUpdates();
        }
      }

      // Reset timestamps for testing (forces re-fetch of all data)
      resetTimestamps() {
        console.log('🔄 Resetting timestamps to force data refresh...');
        this.lastCheckTimes = {
          listings: '1970-01-01 00:00:00',
          offers: '1970-01-01 00:00:00',
          orders: '1970-01-01 00:00:00',
          notifications: '1970-01-01 00:00:00'
        };
        this.saveTimestamps();
        console.log('✅ Timestamps reset. Next poll will fetch all recent data.');
        console.log('💡 Registered callbacks:', Object.keys(this.renderCallbacks));
        
        // Trigger immediate fetch
        if (this.isPolling) {
          this.fetchUpdates();
        } else {
          console.warn('⚠️ Polling is not running. Start polling first.');
        }
      }
      
      // Force fetch with specific timestamp (for testing)
      forceRefresh(hoursAgo = 24) {
        console.log(`🔄 Force refreshing data from last ${hoursAgo} hours...`);
        const date = new Date();
        date.setHours(date.getHours() - hoursAgo);
        const timestamp = date.toISOString().slice(0, 19).replace('T', ' ');
        
        this.lastCheckTimes = {
          listings: timestamp,
          offers: timestamp,
          orders: timestamp,
          notifications: timestamp
        };
        this.saveTimestamps();
        console.log('✅ Timestamps set to:', timestamp);
        console.log('💡 Registered callbacks:', Object.keys(this.renderCallbacks));
        
        if (this.isPolling) {
          this.fetchUpdates();
        } else {
          console.warn('⚠️ Polling is not running. Start polling first.');
        }
      }
    }

    // Global polling manager instance
    let pollingManager = null;

    // Legacy function for backward compatibility
    window.startPolling = function(renderCallbacks) {
      // If polling manager already exists and is polling, just update callbacks
      if (pollingManager && pollingManager.isPolling) {
        console.warn('⚠️ Polling already active, updating callbacks only');
        pollingManager.renderCallbacks = { ...pollingManager.renderCallbacks, ...renderCallbacks };
        return;
      }
      
      // Stop existing polling if any
      if (pollingManager) {
        console.log('🛑 Stopping existing polling manager');
        pollingManager.stop();
      }
      
      // Create new instance
      console.log('🆕 Creating new polling manager');
      pollingManager = new PollingManager();
      pollingManager.start(renderCallbacks);
      
      // Expose to window for debugging
      window.pollingManager = pollingManager;
    }

    // Export for modern usage
    window.PollingManager = PollingManager;
    window.pollingManager = pollingManager; // Expose global instance for debugging
}

// Generic helper functions for UI updates
// Check if PollingUIHelpers already exists
if (typeof PollingUIHelpers === 'undefined') {
  window.PollingUIHelpers = {
    // Generic function to add new records to any table
    addRecordToTable: function(record, tableSelector, recordRenderer) {
      const tbody = document.querySelector(`${tableSelector} tbody`);
      if (!tbody) return;
      
      const newRow = document.createElement('tr');
      newRow.className = 'hover:bg-gray-50 transition-all animate-fade-in';
      newRow.dataset.recordId = record.id;
      newRow.style.backgroundColor = '#f0f9ff'; // Light blue background for new items
      
      // Use custom renderer or default
      if (recordRenderer && typeof recordRenderer === 'function') {
        newRow.innerHTML = recordRenderer(record);
      } else {
        newRow.innerHTML = this.defaultRecordRenderer(record);
      }
      
      // Add to top of table
      tbody.insertBefore(newRow, tbody.firstChild);
      
      // Remove highlight after 5 seconds
      setTimeout(() => {
        newRow.style.backgroundColor = '';
      }, 5000);
    },
    
    // Default record renderer (basic implementation)
    defaultRecordRenderer: function(record) {
      return `
        <td class="py-4 px-5">
          <div class="flex items-center space-x-3">
            <div>
              <p class="font-semibold text-gray-800">${record.name || record.title || 'New Record'}</p>
              <p class="text-gray-500 text-xs">${record.category || record.type || 'N/A'}</p>
            </div>
          </div>
        </td>
        <td class="py-4 px-5">
          <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-semibold">
            ${record.status || 'Active'}
          </span>
        </td>
        <td class="py-4 px-5">
          <span class="text-gray-600">${new Date(record.created_at || Date.now()).toLocaleDateString()}</span>
        </td>
      `;
    },
    
    // Generic notification function
    showBriefNotification: function(message, type = 'success') {
      const notification = document.createElement('div');
      const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
      
      notification.className = `fixed top-4 right-4 ${bgColor} text-white px-4 py-2 rounded-lg shadow-lg z-50 animate-fade-in`;
      notification.innerHTML = `
        <div class="flex items-center gap-2">
          <i class="fas fa-${type === 'success' ? 'check' : 'exclamation-triangle'}"></i>
          <span>${message}</span>
        </div>
      `;
      
      document.body.appendChild(notification);
      
      // Auto-remove after 3 seconds
      setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
      }, 3000);
    },
    
    // Generic stats updater
    updateStatsCounter: function(selector, increment = 1) {
      const element = document.querySelector(selector);
      if (element) {
        const currentCount = parseInt(element.textContent.replace(/,/g, '')) || 0;
        element.textContent = (currentCount + increment).toLocaleString();
      }
    },
    
    // Generic duplicate checker - works with both tables and card containers
    checkForDuplicates: function(containerSelector, newRecords, idField = 'id') {
      const existingIds = new Set();
      
      // Check for table rows and card elements
      document.querySelectorAll(`${containerSelector} tbody tr, ${containerSelector} [data-record-id], ${containerSelector} [data-offer-id], ${containerSelector} [data-order-id], ${containerSelector} [data-listing-id]`).forEach(element => {
        const id = element.dataset.recordId || element.dataset.offerId || element.dataset.orderId || element.dataset.listingId;
        if (id) existingIds.add(parseInt(id));
      });

      console.log('🔍 Existing IDs found:', Array.from(existingIds));
      console.log('📦 New records to check:', newRecords.map(r => r[idField]));
      
      // Explicitly parseInt both sides to ensure safe comparison
      const uniqueRecords = newRecords.filter(record => !existingIds.has(parseInt(record[idField])));
      console.log('✅ Unique records after filtering:', uniqueRecords.length);
      
      return uniqueRecords;
    }
  };
}

// Add CSS for animations if not already present
if (!document.querySelector('#polling-animations')) {
  const style = document.createElement('style');
  style.id = 'polling-animations';
  style.textContent = `
    .animate-fade-in {
      animation: fadeIn 0.3s ease-in;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
  `;
  document.head.appendChild(style);
}

// Add global helpers for testing
window.resetPollingTimestamps = function() {
  if (typeof pollingManager !== 'undefined' && pollingManager) {
    pollingManager.resetTimestamps();
  } else {
    // If manager not ready, reset local storage directly
    try {
        const emptyTimes = {
            listings: '1970-01-01 00:00:00',
            offers: '1970-01-01 00:00:00',
            orders: '1970-01-01 00:00:00',
            transactions: '1970-01-01 00:00:00',
            notifications: '1970-01-01 00:00:00'
        };
        localStorage.setItem('polling_timestamps', JSON.stringify(emptyTimes));
        console.log('✅ Local storage reset directly. Refresh page to see changes.');
    } catch(e) { console.error(e); }
  }
};

window.forcePollingRefresh = function(hoursAgo = 24) {
  if (typeof pollingManager !== 'undefined' && pollingManager) {
    pollingManager.forceRefresh(hoursAgo);
  } else {
    console.warn('⚠️ Polling manager not initialized yet');
  }
};

window.checkPollingStatus = function() {
  if (typeof pollingManager !== 'undefined' && pollingManager) {
    console.log('📊 Polling Status:');
    console.log('  - Is polling:', pollingManager.isPolling);
    console.log('  - Error count:', pollingManager.errorCount);
    console.log('  - Registered callbacks:', Object.keys(pollingManager.renderCallbacks));
    console.log('  - Last check times:', pollingManager.lastCheckTimes);
    console.log('  - Current interval:', pollingManager.currentInterval + 'ms');
  } else {
    console.warn('⚠️ Polling manager not initialized yet');
  }
};
  