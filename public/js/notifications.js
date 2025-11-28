

class NotificationManager {
    constructor() {
        this.pollingInterval = 10000;
        this.pollingTimer = null;
        this.lastCount = 0;
        this.useGlobalPolling = typeof window.pollingManager !== 'undefined';
        
        this.init();
    }
    
    init() {
        // Check if global polling manager exists
        if (this.useGlobalPolling) {
            console.log('✅ Using global polling manager for notifications');
            // Don't start separate polling, will use global polling callbacks
        } else {
            console.log('⚠️ Global polling not found, using fallback polling');
            // Start fallback polling for notification count
            this.startPolling();
        }
        
        // Load notifications when dropdown opens
        this.setupDropdownListeners();
        
        // Setup mark as read handlers
        this.setupMarkReadHandlers();
        
        // Initial count load
        this.updateNotificationCount();
    }
    
    startPolling() {
        // Initial load
        this.updateNotificationCount();
        
        // Poll every 10 seconds
        this.pollingTimer = setInterval(() => {
            this.updateNotificationCount();
        }, this.pollingInterval);
    }
    
    stopPolling() {
        if (this.pollingTimer) {
            clearInterval(this.pollingTimer);
            this.pollingTimer = null;
        }
    }
    
    // Method to be called by global polling manager
    handleNewNotifications(notifications) {
        console.log('🔔 New notifications received:', notifications.length);
        
        // Update badge count
        this.updateNotificationCount();
        
        // Show browser notification if supported
        if (notifications.length > 0 && 'Notification' in window) {
            this.showBrowserNotification(notifications[0]);
        }
        
        // Play sound or show visual indicator
        this.showNotificationIndicator(notifications.length);
    }
    
    async updateNotificationCount() {
        try {
            console.log('Fetching notification count...');
            // Use API_BASE_PATH from global config
            const apiPath = window.API_BASE_PATH || '/marketplace/api';
            const response = await fetch(`${apiPath}/notifications_api.php?action=count`);
            const data = await response.json();
            console.log('Notification count response:', data);
            
            if (data.success) {
                this.updateBadge(data.count);
            }
        } catch (error) {
            console.error('Error fetching notification count:', error);
        }
    }
    
    updateBadge(count) {
        console.log('Updating badge with count:', count);
        const badges = document.querySelectorAll('#notificationBadge, #mobileNotificationBadge');
        console.log('Found badges:', badges.length);
        
        badges.forEach(badge => {
            console.log('Updating badge element:', badge);
            if (count > 0) {
                badge.textContent = count;
                badge.classList.remove('hidden');
                console.log('✓ Badge shown with count:', count);
            } else {
                badge.classList.add('hidden');
                console.log('✓ Badge hidden (count is 0)');
            }
        });
        
        this.lastCount = count;
    }
    
    setupDropdownListeners() {
        const desktopBtn = document.getElementById('notificationBtn');
        const desktopDropdown = document.getElementById('notificationDropdown');
        const mobileBtn = document.getElementById('mobileNotificationBtn');
        const mobileDropdown = document.getElementById('mobileNotificationDropdown');
        
        if (desktopBtn && desktopDropdown) {
            desktopBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const isHidden = desktopDropdown.classList.contains('hidden');
                
                console.log('Desktop notification button clicked, isHidden:', isHidden);
                
                // Toggle dropdown
                desktopDropdown.classList.toggle('hidden');
                desktopDropdown.classList.toggle('animate-slideUpFade');
                
                // Load notifications if opening
                if (isHidden) {
                    console.log('Loading desktop notifications...');
                    this.loadNotifications('notificationList');
                }
            });
        }
        
        if (mobileBtn && mobileDropdown) {
            mobileBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const isHidden = mobileDropdown.classList.contains('hidden');
                
                console.log('Mobile notification button clicked, isHidden:', isHidden);
                
                // Toggle dropdown
                mobileDropdown.classList.toggle('hidden');
                mobileDropdown.classList.toggle('animate-slideUpFade');
                
                // Load notifications if opening
                if (isHidden) {
                    console.log('Loading mobile notifications...');
                    this.loadNotifications('mobileNotificationList');
                }
            });
        }
        
        // Close dropdowns on outside click
        document.addEventListener('click', (e) => {
            if (desktopBtn && desktopDropdown && 
                !desktopBtn.contains(e.target) && 
                !desktopDropdown.contains(e.target)) {
                desktopDropdown.classList.add('hidden');
            }
            
            if (mobileBtn && mobileDropdown && 
                !mobileBtn.contains(e.target) && 
                !mobileDropdown.contains(e.target)) {
                mobileDropdown.classList.add('hidden');
            }
        });
    }
    
    async loadNotifications(listId) {
        try {
            console.log('Loading notifications for list:', listId);
            // Use API_BASE_PATH from global config
            const apiPath = window.API_BASE_PATH || '/marketplace/api';
            const response = await fetch(`${apiPath}/notifications_api.php?action=list&limit=10`);
            console.log('API response status:', response.status);
            
            const data = await response.json();
            console.log('API response data:', data);
            
            if (data.success) {
                console.log('Rendering', data.notifications.length, 'notifications');
                this.renderNotifications(data.notifications, listId);
            } else {
                console.error('API returned error:', data.error);
                const list = document.getElementById(listId);
                if (list) {
                    list.innerHTML = `
                        <li class="p-8 text-center text-red-500">
                            <i class="fa-solid fa-exclamation-triangle text-3xl mb-2"></i>
                            <p class="text-sm">Error: ${data.error || 'Failed to load'}</p>
                        </li>
                    `;
                }
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
            const list = document.getElementById(listId);
            if (list) {
                list.innerHTML = `
                    <li class="p-8 text-center text-red-500">
                        <i class="fa-solid fa-exclamation-triangle text-3xl mb-2"></i>
                        <p class="text-sm">Failed to load notifications</p>
                        <p class="text-xs mt-1">${error.message}</p>
                    </li>
                `;
            }
        }
    }
    
    renderNotifications(notifications, listId) {
        console.log('renderNotifications called with:', listId, notifications);
        const list = document.getElementById(listId);
        console.log('List element found:', list);
        
        if (!list) {
            console.error('List element not found:', listId);
            return;
        }
        
        if (notifications.length === 0) {
            list.innerHTML = `
                <li class="p-8 text-center text-gray-500">
                    <i class="fa-solid fa-bell-slash text-3xl mb-2"></i>
                    <p class="text-sm">No notifications yet</p>
                </li>
            `;
            return;
        }
        
        console.log('Generating HTML for', notifications.length, 'notifications');
        const html = notifications.map(notif => {
            const icon = this.getNotificationIcon(notif.type);
            const bgColor = this.getNotificationColor(notif.type);
            const timeAgo = this.getTimeAgo(notif.created_at);
            const unreadClass = notif.is_read == 0 ? 'bg-blue-50' : '';
            
            return `
                <li class="flex items-start gap-3 p-4 hover:bg-gray-50 transition ${unreadClass}" data-notification-id="${notif.id}">
                    <div class="w-10 h-10 ${bgColor} flex items-center justify-center rounded-full flex-shrink-0">
                        <i class="${icon}"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-800 font-medium">${this.escapeHtml(notif.title)}</p>
                        <p class="text-xs text-gray-500 mt-0.5">${timeAgo}</p>
                    </div>
                    ${notif.is_read == 0 ? `
                        <button class="mark-read-btn text-blue-600 hover:text-blue-800 text-xs flex-shrink-0" data-id="${notif.id}">
                            <i class="fa-solid fa-check"></i>
                        </button>
                    ` : ''}
                </li>
            `;
        }).join('');
        
        console.log('Generated HTML length:', html.length);
        list.innerHTML = html;
        console.log('HTML injected into list');
        
        // Attach click handlers to mark read buttons
        list.querySelectorAll('.mark-read-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = btn.dataset.id;
                this.markAsRead(id);
            });
        });
    }
    
    getNotificationIcon(type) {
        const icons = {
            'offer': 'fa-solid fa-handshake',
            'order': 'fa-solid fa-shopping-cart',
            'listing': 'fa-solid fa-tag',
            'message': 'fa-solid fa-envelope',
            'system': 'fa-solid fa-bell'
        };
        return icons[type] || 'fa-solid fa-bell';
    }
    
    getNotificationColor(type) {
        const colors = {
            'offer': 'bg-green-100 text-green-600',
            'order': 'bg-blue-100 text-blue-600',
            'listing': 'bg-purple-100 text-purple-600',
            'message': 'bg-yellow-100 text-yellow-600',
            'system': 'bg-gray-100 text-gray-600'
        };
        return colors[type] || 'bg-gray-100 text-gray-600';
    }
    
    getNotificationIconColor(type) {
        const colors = {
            'offer': 'notification-offer',
            'order': 'notification-message',
            'listing': 'notification-listing',
            'message': 'notification-message',
            'system': 'notification-system'
        };
        return colors[type] || 'notification-system';
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
        div.textContent = text;
        return div.innerHTML;
    }
    
    showBrowserNotification(notification) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(notification.title, {
                body: notification.message,
                icon: '/marketplace/public/images/logo.png',
                badge: '/marketplace/public/images/badge.png'
            });
        } else if ('Notification' in window && Notification.permission !== 'denied') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    new Notification(notification.title, {
                        body: notification.message,
                        icon: '/marketplace/public/images/logo.png'
                    });
                }
            });
        }
    }
    
    showNotificationIndicator(count) {
        // Animate the bell icon
        const bells = document.querySelectorAll('#notificationBtn i, #mobileNotificationBtn i');
        bells.forEach(bell => {
            bell.classList.add('fa-shake');
            setTimeout(() => {
                bell.classList.remove('fa-shake');
            }, 1000);
        });
        
        // Show toast notification
        this.showToast(`You have ${count} new notification${count > 1 ? 's' : ''}!`);
    }
    
    showToast(message) {
        const toast = document.createElement('div');
        toast.className = 'fixed top-20 right-4 bg-blue-600 text-white px-4 py-3 rounded-lg shadow-lg z-50 animate-fade-in';
        toast.innerHTML = `
            <div class="flex items-center gap-2">
                <i class="fas fa-bell"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    setupMarkReadHandlers() {
        // Mark all as read buttons
        const markAllBtns = document.querySelectorAll('.mark-all-read-btn');
        markAllBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                this.markAllAsRead();
            });
        });
    }
    
    async markAsRead(notificationId) {
        try {
            const formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('id', notificationId);
            
            // Use API_BASE_PATH from global config
            const apiPath = window.API_BASE_PATH || '/marketplace/api';
            const response = await fetch(`${apiPath}/notifications_api.php`, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Update UI
                this.updateNotificationCount();
                this.loadNotifications('notificationList');
                this.loadNotifications('mobileNotificationList');
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }
    
    async markAllAsRead() {
        try {
            const formData = new FormData();
            formData.append('action', 'mark_all_read');
            
            // Use API_BASE_PATH from global config
            const apiPath = window.API_BASE_PATH || '/marketplace/api';
            const response = await fetch(`${apiPath}/notifications_api.php`, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Update UI
                this.updateNotificationCount();
                this.loadNotifications('notificationList');
                this.loadNotifications('mobileNotificationList');
            }
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    console.log('=== Notification System Initializing ===');
    console.log('notificationBtn:', document.getElementById('notificationBtn'));
    console.log('mobileNotificationBtn:', document.getElementById('mobileNotificationBtn'));
    
    if (document.getElementById('notificationBtn') || document.getElementById('mobileNotificationBtn')) {
        console.log('✓ Notification buttons found, starting manager...');
        window.notificationManager = new NotificationManager();
        console.log('✓ NotificationManager initialized');
    } else {
        console.warn('⚠ No notification buttons found in DOM');
    }
});
