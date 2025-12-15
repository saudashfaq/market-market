/**
 * Global Logout Confirmation System
 * Har jagah logout ke liye confirmation popup
 */

// Global logout confirmation function
function confirmLogout(event) {
    if (event && event.preventDefault) {
        event.preventDefault();
    }
    
    console.log('Logout confirmation triggered'); // Debug log
    
    showConfirm('Are you sure you want to logout?', {
        title: 'Confirm Logout',
        confirmText: 'Yes, Logout',
        cancelText: 'Cancel'
    }).then(result => {
        console.log('Logout confirmation result:', result); // Debug log
        if (result === true) {
            console.log('User confirmed logout - proceeding...');
            
            // User confirmed, show loading and redirect
            showSuccess('Logging out...', {
                title: 'Please wait',
                showConfirm: false,
                autoClose: true,
                autoCloseTime: 1000
            });
            
            setTimeout(() => {
                console.log('Redirecting to logout...');
                if (window.BASE) {
                    window.location.href = window.BASE + 'logout';
                } else {
                    window.location.href = 'logout';
}
            }, 500);
        } else {
            console.log('Logout cancelled by user');
        }
    }).catch(error => {
        console.error('Logout confirmation error:', error);
        if (window.BASE) {
            window.location.href = window.BASE + 'logout';
        } else {
            window.location.href = 'logout';
        }
    });
}

// Auto-attach to all logout links
document.addEventListener('DOMContentLoaded', function() {
    // Find all logout links and add confirmation
    const logoutLinks = document.querySelectorAll('a[href*="auth_logout"], a[href*="logout"]');
    
    logoutLinks.forEach(link => {
        link.addEventListener('click', confirmLogout);
    });
    
    // Also handle any logout buttons
    const logoutButtons = document.querySelectorAll('button[data-action="logout"]');
    
    logoutButtons.forEach(button => {
        button.addEventListener('click', confirmLogout);
    });
});

// Alternative function for direct use
function logoutWithConfirmation() {
    confirmLogout({ preventDefault: () => {} });
}