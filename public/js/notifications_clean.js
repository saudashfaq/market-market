/**
 * Clean Notifications System - Version 2.0
 * Completely rewritten to avoid any path or caching issues
 */

(function() {
    'use strict';
    
    console.log('🔔 Clean Notifications System Loading...');
    
    // Configuration
    const CONFIG = {
        apiUrl: '/marketplace/api/notifications_api.php',
        pollInterval: 10000,
        debug: true
    };
    
    // Debug logging
    function debugLog(message, data = null) {
        if (CONFIG.debug) {
            console.log(`[NotificationSystem] ${message}`, data || '');
        }
    }
    
    // Main notification system class
    class CleanNotificationSystem {
        constructor() {
            this.pollTimer = null;
            this.isInitialized = false;
            
            debugLog('Initializing Clean Notification System');
            this.init();
        }
        
        init() {
            if (this.isInitialized) {
                debugLog('Already initialized, skipping');
                return;
            }
            
            debugLog('Starting initialization...');
            
            // Wait for DOM to be ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setup());
            } else {
                this.setup();
            }
        }
        
        setup() {
            debugLog('Setting up notification system');
            
            // Find notification elements
            this.elements = {
                desktopBtn: document.getElementById('notificationBtn'),
                desktopDropdown: document.getElementById('notificationDropdown'),
                desktopList: document.getElementById('notificationList'),
                desktopBadge: document.getElementById('notificationBadge'),
                mobileBtn: document.getElementById('mobileNotificationBtn'),
                mobileDropdown: document.getElementById('mobileNotificationDropdown'),
                mobileList: document.getElementById('mobileNotificationList'),
                mobileBadge: document.getElementById('mobileNotificationBadge')
            };
            
            debugLog('Found elements:', Object.keys(this.elements).filter(key => this.elements[key]));
            
            // Setup event listeners
            this.setupEventListeners();
            
            // Start polling
            this.startPolling();
            
            this.isInitialized = true;
            debugLog('✅ Notification system initialized successfully');
        }
        
        setupEventListeners() {
            // Desktop notification button
            if (this.elements.desktopBtn && this.elements.desktopDropdown) {
                this.elements.desktopBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.toggleDropdown('desktop');
                });
                debugLog('✅ Desktop button listener added');
            }
            
            // Mobile notification button
            if (this.elements.mobileBtn && this.elements.mobileDropdown) {
                this.elements.mobileBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.toggleDropdown('mobile');
                });
                debugLog('✅ Mobile button listener added');
            }
            
            // Close dropdowns on outside click
            document.addEventListener('click', (e) => {
                if (this.elements.desktopDropdown && 
                    !this.elements.desktopBtn?.contains(e.target) && 
                    !this.elements.desktopDropdown.contains(e.target)) {
                    this.elements.desktopDropdown.classList.add('hidden');
                }
                
                if (this.elements.mobileDropdown && 
                    !this.elements.mobileBtn?.contains(e.target) && 
                    !this.elements.mobileDropdown.contains(e.target)) {
                    this.elements.mobileDropdown.classList.add('hidden');
                }
            });
        }
        
        toggleDropdown(type) {
            const dropdown = type === 'desktop' ? this.elements.desktopDropdown : this.elements.mobileDropdown;
            const list = type === 'desktop' ? this.elements.desktopList : this.elements.mobileList;
            
            if (!dropdown) return;
            
            const isHidden = dropdown.classList.contains('hidden');
            dropdown.classList.toggle('hidden');
            
            if (isHidden) {
                // Add proper dropdown styling and structure
                this.setupDropdownStructure(dropdown, type);
                
                if (list) {
                    debugLog(`Loading ${type} notifications...`);
                    this.loadNotifications(list);
                }
            }
        }
        
        setupDropdownStructure(dropdown, type) {
            // Setup mark all as read button if it exists
            const markAllBtn = dropdown.querySelector('.mark-all-read-btn');
            if (markAllBtn) {
                markAllBtn.onclick = () => this.markAllAsRead();
            }
            
            // Ensure list element is properly referenced
            const listElement = dropdown.querySelector('#notificationList, #mobileNotificationList');
            if (listElement) {
                if (type === 'desktop') {
                    this.elements.desktopList = listElement;
                } else {
                    this.elements.mobileList = listElement;
                }
            }
        }
        
        startPolling() {
            // Initial load
            this.updateNotificationCount();
            
            // Poll every 10 seconds
            this.pollTimer = setInterval(() => {
                this.updateNotificationCount();
            }, CONFIG.pollInterval);
            
            debugLog('✅ Polling started');
        }
        
        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
                debugLog('⏹️ Polling stopped');
            }
        }
        
        async updateNotificationCount() {
            try {
                const url = `${CONFIG.apiUrl}?action=count&_t=${Date.now()}`;
                debugLog('Fetching count from:', url);
                
                const response = await this.makeRequest(url);
                
                if (response.success) {
                    this.updateBadges(response.count);
                    debugLog('✅ Count updated:', response.count);
                } else {
                    debugLog('❌ Count API error:', response.error);
                }
            } catch (error) {
                debugLog('❌ Count fetch error:', error.message);
            }
        }
        
        async loadNotifications(listElement) {
            if (!listElement) return;
            
            try {
                const url = `${CONFIG.apiUrl}?action=list&limit=10&_t=${Date.now()}`;
                debugLog('Loading notifications from:', url);
                
                // Show loading state
                listElement.innerHTML = `
                    <li class="p-8 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 border-2 border-indigo-200 border-t-indigo-600 rounded-full animate-spin mb-3"></div>
                            <p class="text-sm font-medium">Loading notifications...</p>
                        </div>
                    </li>
                `;
                
                const response = await this.makeRequest(url);
                
                if (response.success) {
                    this.renderNotifications(response.notifications, listElement);
                    debugLog('✅ Notifications loaded:', response.notifications.length);
                } else {
                    this.showError(listElement, response.error || 'Failed to load notifications');
                    debugLog('❌ List API error:', response.error);
                }
            } catch (error) {
                this.showError(listElement, error.message);
                debugLog('❌ List fetch error:', error.message);
            }
        }
        
        async makeRequest(url, options = {}) {
            const defaultOptions = {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            };
            
            const finalOptions = { ...defaultOptions, ...options };
            
            debugLog('Making request:', { url, options: finalOptions });
            
            const response = await fetch(url, finalOptions);
            
            debugLog('Response status:', response.status);
            debugLog('Response headers:', [...response.headers.entries()]);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                debugLog('❌ Non-JSON response:', text.substring(0, 200));
                throw new Error('Server returned HTML instead of JSON. Check if you are logged in.');
            }
            
            const data = await response.json();
            debugLog('Response data:', data);
            
            return data;
        }
        
        updateBadges(count) {
            const badges = [this.elements.desktopBadge, this.elements.mobileBadge].filter(Boolean);
            
            debugLog(`Updating ${badges.length} badges with count: ${count}`);
            
            badges.forEach(badge => {
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.classList.remove('hidden');
                    badge.style.display = 'flex';
                    
                    // Add animation for new notifications
                    badge.classList.add('animate-pulse');
                    setTimeout(() => {
                        badge.classList.remove('animate-pulse');
                    }, 2000);
                    
                    // Ensure proper styling
                    badge.className = badge.className.replace(/hidden/g, '');
                    if (!badge.className.includes('absolute')) {
                        badge.className += ' absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-medium shadow-lg';
                    }
                } else {
                    badge.classList.add('hidden');
                    badge.style.display = 'none';
                }
            });
            
            // Update bell icon animation
            const bellIcons = document.querySelectorAll('#notificationBtn i, #mobileNotificationBtn i');
            bellIcons.forEach(icon => {
                if (count > 0) {
                    icon.classList.add('text-blue-600');
                    icon.classList.remove('text-gray-600');
                } else {
                    icon.classList.add('text-gray-600');
                    icon.classList.remove('text-blue-600');
                }
            });
        }
        
        renderNotifications(notifications, listElement) {
            if (!listElement) return;
            
            if (notifications.length === 0) {
                listElement.innerHTML = `
                    <li class="p-12 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-full flex items-center justify-center">
                            <i class="fa-solid fa-bell-slash text-2xl text-indigo-400"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-900 mb-1">All caught up!</h3>
                        <p class="text-xs text-gray-500">No new notifications to show</p>
                    </li>
                `;
                return;
            }
            
            const html = notifications.map(notif => {
                const { icon, bgColor, textColor } = this.getNotificationStyle(notif.type);
                const timeAgo = this.getTimeAgo(notif.created_at);
                const isUnread = notif.is_read == 0;
                
                return `<li class="relative group hover:bg-gray-50 transition-all duration-200 ${isUnread ? 'bg-blue-50/30' : ''}" data-id="${notif.id}">${isUnread ? '<div class="absolute left-0 top-0 bottom-0 w-1 bg-indigo-500"></div>' : ''}<div class="p-4 ${isUnread ? 'pl-6' : 'pl-4'}"><div class="flex items-start gap-3"><div class="w-10 h-10 ${bgColor} rounded-full flex items-center justify-center flex-shrink-0 shadow-sm"><i class="${icon} ${textColor} text-sm"></i></div><div class="flex-1 min-w-0"><h4 class="text-sm font-semibold leading-5 ${isUnread ? 'text-gray-900' : 'text-gray-700'}">${this.escapeHtml(notif.title)}</h4><p class="text-xs text-gray-600 mt-1.5 leading-relaxed">${this.escapeHtml(notif.message)}</p><div class="flex items-center gap-2 mt-2.5"><span class="text-xs text-gray-500 font-medium">${timeAgo}</span>${isUnread ? '<span class="w-1.5 h-1.5 bg-indigo-500 rounded-full"></span>' : ''}</div></div></div></div></li>`;
            }).join('');
            
            listElement.innerHTML = html;
        }
        
        getNotificationStyle(type) {
            const styles = {
                'listing': {
                    icon: 'fa-solid fa-tag',
                    bgColor: 'bg-green-100',
                    textColor: 'text-green-600'
                },
                'offer': {
                    icon: 'fa-solid fa-handshake',
                    bgColor: 'bg-blue-100',
                    textColor: 'text-blue-600'
                },
                'order': {
                    icon: 'fa-solid fa-shopping-cart',
                    bgColor: 'bg-purple-100',
                    textColor: 'text-purple-600'
                },
                'message': {
                    icon: 'fa-solid fa-envelope',
                    bgColor: 'bg-yellow-100',
                    textColor: 'text-yellow-600'
                },
                'payment': {
                    icon: 'fa-solid fa-credit-card',
                    bgColor: 'bg-emerald-100',
                    textColor: 'text-emerald-600'
                },
                'system': {
                    icon: 'fa-solid fa-cog',
                    bgColor: 'bg-gray-100',
                    textColor: 'text-gray-600'
                },
                'bid': {
                    icon: 'fa-solid fa-gavel',
                    bgColor: 'bg-orange-100',
                    textColor: 'text-orange-600'
                },
                'auction': {
                    icon: 'fa-solid fa-clock',
                    bgColor: 'bg-red-100',
                    textColor: 'text-red-600'
                }
            };
            
            return styles[type] || {
                icon: 'fa-solid fa-bell',
                bgColor: 'bg-blue-100',
                textColor: 'text-blue-600'
            };
        }
        
        showError(listElement, message) {
            if (!listElement) return;
            
            listElement.innerHTML = `
                <li class="p-8 text-center">
                    <div class="flex flex-col items-center">
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mb-3">
                            <i class="fa-solid fa-exclamation-triangle text-xl text-red-500"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-900 mb-1">Failed to load</h3>
                        <p class="text-xs text-gray-500 mb-3">${message}</p>
                        <button 
                            onclick="window.cleanNotificationSystem.updateNotificationCount()" 
                            class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-full transition-colors"
                        >
                            <i class="fa-solid fa-refresh mr-1"></i>
                            Try again
                        </button>
                    </div>
                </li>
            `;
        }
        
        async markAsRead(notificationId) {
            try {
                debugLog('Marking notification as read:', notificationId);
                
                const formData = new FormData();
                formData.append('action', 'mark_read');
                formData.append('id', notificationId);
                
                const response = await this.makeRequest(CONFIG.apiUrl, {
                    method: 'POST',
                    body: formData
                });
                
                if (response.success) {
                    debugLog('✅ Notification marked as read');
                    this.updateNotificationCount();
                    
                    // Reload open dropdowns
                    if (this.elements.desktopList && !this.elements.desktopDropdown?.classList.contains('hidden')) {
                        this.loadNotifications(this.elements.desktopList);
                    }
                    if (this.elements.mobileList && !this.elements.mobileDropdown?.classList.contains('hidden')) {
                        this.loadNotifications(this.elements.mobileList);
                    }
                } else {
                    debugLog('❌ Mark read failed:', response.error);
                }
            } catch (error) {
                debugLog('❌ Mark read error:', error.message);
            }
        }
        
        async markAllAsRead() {
            try {
                debugLog('Marking all notifications as read');
                
                const formData = new FormData();
                formData.append('action', 'mark_all_read');
                
                const response = await this.makeRequest(CONFIG.apiUrl, {
                    method: 'POST',
                    body: formData
                });
                
                if (response.success) {
                    debugLog('✅ All notifications marked as read');
                    this.updateNotificationCount();
                    
                    // Reload open dropdowns
                    if (this.elements.desktopList && !this.elements.desktopDropdown?.classList.contains('hidden')) {
                        this.loadNotifications(this.elements.desktopList);
                    }
                    if (this.elements.mobileList && !this.elements.mobileDropdown?.classList.contains('hidden')) {
                        this.loadNotifications(this.elements.mobileList);
                    }
                    
                    // Show success message
                    this.showToast('All notifications marked as read!', 'success');
                } else {
                    debugLog('❌ Mark all read failed:', response.error);
                    this.showToast('Failed to mark all as read', 'error');
                }
            } catch (error) {
                debugLog('❌ Mark all read error:', error.message);
                this.showToast('Error marking notifications as read', 'error');
            }
        }
        
        showToast(message, type = 'info') {
            const toast = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
            
            toast.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300`;
            toast.innerHTML = `
                <div class="flex items-center gap-2">
                    <i class="fa-solid ${type === 'success' ? 'fa-check' : type === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            // Animate in
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
            }, 100);
            
            // Animate out and remove
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        getTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            
            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + ' mins ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
            if (seconds < 604800) return Math.floor(seconds / 86400) + ' days ago';
            return date.toLocaleDateString();
        }
        
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
    }
    
    // Initialize the system
    window.cleanNotificationSystem = new CleanNotificationSystem();
    
    debugLog('🚀 Clean Notification System script loaded');
    
})();