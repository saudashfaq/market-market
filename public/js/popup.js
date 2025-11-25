/**
 * Generic Popup System - Alert ki jagah modern popup
 * Usage: showPopup(message, type, options)
 */

class PopupManager {
    constructor() {
        this.createPopupContainer();
        this.injectStyles();
    }

    createPopupContainer() {
        if (!document.getElementById('popup-container')) {
            const container = document.createElement('div');
            container.id = 'popup-container';
            container.className = 'popup-container';
            document.body.appendChild(container);
        }
    }

    injectStyles() {
        if (!document.getElementById('popup-styles')) {
            const style = document.createElement('style');
            style.id = 'popup-styles';
            style.textContent = `
                .popup-container {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    z-index: 10000;
                    pointer-events: none;
                }

                .popup-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    opacity: 0;
                    transition: opacity 0.3s ease;
                    pointer-events: all;
                }

                .popup-overlay.show {
                    opacity: 1;
                }

                .popup-modal {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%) scale(0.7);
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                    max-width: 400px;
                    width: 90%;
                    transition: all 0.3s ease;
                    pointer-events: all;
                }

                .popup-modal.show {
                    transform: translate(-50%, -50%) scale(1);
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

                .popup-icon.success { background: #28a745; }
                .popup-icon.error { background: #dc3545; }
                .popup-icon.warning { background: #ffc107; color: #333; }
                .popup-icon.info { background: #17a2b8; }

                .popup-title {
                    font-weight: 600;
                    font-size: 16px;
                    color: #333;
                }

                .popup-body {
                    padding: 15px 20px;
                    color: #666;
                    line-height: 1.5;
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

                .popup-close {
                    position: absolute;
                    top: 10px;
                    right: 15px;
                    background: none;
                    border: none;
                    font-size: 20px;
                    cursor: pointer;
                    color: #999;
                    padding: 0;
                    width: 30px;
                    height: 30px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .popup-close:hover {
                    color: #333;
                }

                @media (max-width: 480px) {
                    .popup-modal {
                        width: 95%;
                        margin: 20px;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }

    show(message, type = 'info', options = {}) {
        const defaults = {
            title: this.getDefaultTitle(type),
            showConfirm: true,
            showCancel: false,
            confirmText: 'OK',
            cancelText: 'Cancel',
            onConfirm: null,
            onCancel: null,
            autoClose: false,
            autoCloseTime: 3000
        };

        const config = { ...defaults, ...options };
        
        return new Promise((resolve) => {
            const overlay = this.createOverlay();
            const modal = this.createModal(message, type, config, resolve);
            
            // Add modal to overlay first
            overlay.appendChild(modal);
            document.getElementById('popup-container').appendChild(overlay);

            // Attach event listeners after DOM insertion
            this.attachEventListeners(modal, overlay, config, resolve);

            // Show animation
            setTimeout(() => {
                overlay.classList.add('show');
                modal.classList.add('show');
            }, 10);

            // Auto close
            if (config.autoClose) {
                setTimeout(() => {
                    this.close(overlay, resolve, true);
                }, config.autoCloseTime);
            }
        });
    }

    createOverlay() {
        const overlay = document.createElement('div');
        overlay.className = 'popup-overlay';
        return overlay;
    }

    createModal(message, type, config, resolve) {
        const modal = document.createElement('div');
        modal.className = 'popup-modal';

        const iconSymbol = this.getIconSymbol(type);
        
        modal.innerHTML = `
            <button class="popup-close">&times;</button>
            <div class="popup-header">
                <div class="popup-icon ${type}">${iconSymbol}</div>
                <div class="popup-title">${config.title}</div>
            </div>
            <div class="popup-body">${message}</div>
            <div class="popup-footer">
                ${config.showCancel ? `<button class="popup-btn popup-btn-secondary popup-cancel">${config.cancelText}</button>` : ''}
                ${config.showConfirm ? `<button class="popup-btn popup-btn-primary popup-confirm">${config.confirmText}</button>` : ''}
            </div>
        `;

        return modal;
    }

    attachEventListeners(modal, overlay, config, resolve) {
        const closeBtn = modal.querySelector('.popup-close');
        const confirmBtn = modal.querySelector('.popup-confirm');
        const cancelBtn = modal.querySelector('.popup-cancel');

        console.log('Attaching event listeners:', { closeBtn, confirmBtn, cancelBtn });

        // Close button
        if (closeBtn) {
            closeBtn.addEventListener('click', (e) => {
                console.log('Close button clicked');
                e.preventDefault();
                e.stopPropagation();
                this.close(overlay, resolve, false);
            });
        }
        
        // Confirm button
        if (confirmBtn) {
            confirmBtn.addEventListener('click', (e) => {
                console.log('Confirm button clicked');
                e.preventDefault();
                e.stopPropagation();
                
                // Execute callback first
                if (config.onConfirm) {
                    console.log('Executing onConfirm callback');
                    try {
                        const result = config.onConfirm();
                        
                        // If callback returns false, don't close popup
                        if (result === false) {
                            console.log('Callback returned false, keeping popup open');
                            return;
                        }
                    } catch (error) {
                        console.error('Error in onConfirm callback:', error);
                    }
                }
                
                // Close popup and resolve with true
                this.close(overlay, resolve, true);
            });
        }

        // Cancel button
        if (cancelBtn) {
            cancelBtn.addEventListener('click', (e) => {
                console.log('Cancel button clicked');
                e.preventDefault();
                e.stopPropagation();
                
                // Execute callback first
                if (config.onCancel) {
                    console.log('Executing onCancel callback');
                    config.onCancel();
                }
                
                // Then close popup
                this.close(overlay, resolve, false);
            });
        }

        // Close on overlay click
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                console.log('Overlay clicked');
                this.close(overlay, resolve, false);
            }
        });
    }

    close(overlay, resolve, result) {
        console.log('Closing popup with result:', result);
        const modal = overlay.querySelector('.popup-modal');
        
        overlay.classList.remove('show');
        modal.classList.remove('show');

        setTimeout(() => {
            overlay.remove();
            console.log('Resolving popup with result:', result);
            resolve(result);
        }, 300);
    }

    getDefaultTitle(type) {
        const titles = {
            success: 'Success',
            error: 'Error',
            warning: 'Warning',
            info: 'Information'
        };
        return titles[type] || 'Notification';
    }

    getIconSymbol(type) {
        const icons = {
            success: '✓',
            error: '✕',
            warning: '!',
            info: 'i'
        };
        return icons[type] || 'i';
    }
}

// Global instance
const popupManager = new PopupManager();

// Global functions - Alert ki jagah use karein
function showPopup(message, type = 'info', options = {}) {
    return popupManager.show(message, type, options);
}

function showAlert(message, options = {}) {
    return showPopup(message, 'info', options);
}

function showSuccess(message, options = {}) {
    return showPopup(message, 'success', options);
}

function showError(message, options = {}) {
    return showPopup(message, 'error', options);
}

function showWarning(message, options = {}) {
    return showPopup(message, 'warning', options);
}

function showConfirm(message, options = {}) {
    return showPopup(message, 'warning', {
        showCancel: true,
        ...options
    });
}

// Replace native alert function
window.originalAlert = window.alert;
window.alert = function(message) {
    showAlert(message);
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new PopupManager();
    });
} else {
    new PopupManager();
}