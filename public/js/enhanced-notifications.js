/**
 * Enhanced Notifications System
 * Extends the basic popup system with verification-specific functionality
 */

// Enhanced popup function that supports resend functionality
function showEnhancedPopup(message, type, options = {}) {
    // Use existing popup system as base
    if (typeof showPopup === 'function') {
        const popup = showPopup(message, type, options);
        
        // Add resend functionality if enabled
        if (options.showResendOption && options.userEmail) {
            addResendButton(popup, options.userEmail);
        }
        
        // Enhanced styling for verification notifications
        if (type === 'success' && options.title && options.title.includes('Registration')) {
            enhanceVerificationPopup(popup);
        }
        
        return popup;
    } else {
        // Fallback if main popup system not available
        console.warn('Main popup system not available, using fallback');
        showFallbackNotification(message, type);
    }
}

// Add resend button to popup
function addResendButton(popup, email) {
    if (!popup || !email) return;
    
    // Find the popup content area
    const contentArea = popup.querySelector('.popup-content') || 
                       popup.querySelector('.popup-body') || 
                       popup;
    
    if (!contentArea) return;
    
    // Create resend button container
    const resendContainer = document.createElement('div');
    resendContainer.className = 'mt-4 pt-3 border-t border-gray-200';
    
    // Create resend button
    const resendButton = document.createElement('button');
    resendButton.textContent = 'Resend Verification Email';
    resendButton.className = 'w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200 font-medium';
    resendButton.id = 'resend-verification-btn';
    
    // Add click handler
    resendButton.onclick = function() {
        resendVerificationEmail(email, resendButton);
    };
    
    resendContainer.appendChild(resendButton);
    contentArea.appendChild(resendContainer);
}

// Enhanced styling for verification popups
function enhanceVerificationPopup(popup) {
    if (!popup) return;
    
    // Add verification-specific classes
    popup.classList.add('verification-popup');
    
    // Ensure minimum width for better readability
    popup.style.minWidth = '400px';
    popup.style.maxWidth = '500px';
    
    // Add email icon to title if present
    const titleElement = popup.querySelector('.popup-title') || popup.querySelector('h3');
    if (titleElement) {
        const emailIcon = document.createElement('span');
        emailIcon.innerHTML = '📧 ';
        emailIcon.style.marginRight = '8px';
        titleElement.insertBefore(emailIcon, titleElement.firstChild);
    }
}

// Resend verification email function
function resendVerificationEmail(email, button) {
    if (!email || !button) return;
    
    // Disable button and show loading state
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Sending...';
    button.classList.add('opacity-50', 'cursor-not-allowed');
    
    // Get CSRF token
    const csrfToken = getCsrfToken();
    if (!csrfToken) {
        console.error('CSRF token not found');
        resetResendButton(button, originalText);
        showFallbackNotification('Security token not found. Please refresh the page and try again.', 'error');
        return;
    }
    
    // Create form data
    const formData = new FormData();
    formData.append('email', email);
    formData.append('csrf_token', csrfToken);
    
    // Send resend request
    fetch(getBaseUrl() + 'public/resend-verification.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (response.ok) {
            // Redirect to see the flash messages
            window.location.reload();
        } else {
            throw new Error('Network response was not ok');
        }
    })
    .catch(error => {
        console.error('Resend failed:', error);
        resetResendButton(button, originalText);
        showFallbackNotification('Failed to resend verification email. Please try again.', 'error');
    });
}

// Reset resend button to original state
function resetResendButton(button, originalText) {
    if (!button) return;
    
    button.disabled = false;
    button.textContent = originalText;
    button.classList.remove('opacity-50', 'cursor-not-allowed');
}

// Get CSRF token from meta tag or form
function getCsrfToken() {
    // Try to get from meta tag first
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        return metaToken.getAttribute('content');
    }
    
    // Try to get from hidden input in any form
    const hiddenInput = document.querySelector('input[name="csrf_token"]');
    if (hiddenInput) {
        return hiddenInput.value;
    }
    
    // Try to get from global variable if set
    if (typeof window.csrfToken !== 'undefined') {
        return window.csrfToken;
    }
    
    return null;
}

// Get base URL for API calls
function getBaseUrl() {
    // Try to get from global variable
    if (typeof window.baseUrl !== 'undefined') {
        return window.baseUrl;
    }
    
    // Try to get from meta tag
    const baseMeta = document.querySelector('meta[name="base-url"]');
    if (baseMeta) {
        return baseMeta.getAttribute('content');
    }
    
    // Fallback to current origin
    return window.location.origin + '/';
}

// Fallback notification system for when main popup system fails
function showFallbackNotification(message, type) {
    // Create simple notification
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm ${getFallbackClasses(type)}`;
    notification.innerHTML = `
        <div class="flex items-center">
            <span class="flex-1">${escapeHtml(message)}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-lg font-bold">&times;</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Get CSS classes for fallback notifications
function getFallbackClasses(type) {
    const classes = {
        'success': 'bg-green-100 border border-green-400 text-green-700',
        'error': 'bg-red-100 border border-red-400 text-red-700',
        'warning': 'bg-yellow-100 border border-yellow-400 text-yellow-700',
        'info': 'bg-blue-100 border border-blue-400 text-blue-700'
    };
    return classes[type] || classes['info'];
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize enhanced notifications when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Override the global showPopup function if it exists
    if (typeof window.showPopup === 'function') {
        window.originalShowPopup = window.showPopup;
        window.showPopup = function(message, type, options) {
            return showEnhancedPopup(message, type, options);
        };
    }
    
    console.log('Enhanced notifications system initialized');
});