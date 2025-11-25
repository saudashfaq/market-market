<?php
/**
 * Flash Message Helper Functions
 * Provides session-based flash messaging with auto-removal after display
 */


/**
 * Set a flash message
 * @param string $type - Type of message (success, error, warning, info)
 * @param string $message - The message content
 */
function setFlashMessage($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
        'timestamp' => time()
    ];
}

/**
 * Get all flash messages and remove them from session
 * @return array - Array of flash messages
 */
function getFlashMessages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    
    // Clear messages after retrieving them
    unset($_SESSION['flash_messages']);
    
    return $messages;
}

/**
 * Check if there are any flash messages
 * @return bool
 */
function hasFlashMessages() {
    return !empty($_SESSION['flash_messages']);
}

/**
 * Display flash messages with Tailwind CSS styling
 * @param bool $autoHide - Whether to auto-hide messages after 3-4 seconds
 */
function displayFlashMessages($autoHide = true) {
    $messages = getFlashMessages();
    
    if (empty($messages)) {
        return;
    }
    
    echo '<div id="flash-messages-container" class="fixed top-6 left-1/2 transform -translate-x-1/2 z-50 space-y-3">
    <style>
        /* Ensure flash messages maintain solid background colors */
        [id^="flash-message-"] {
            animation: none !important;
        }
        [id^="flash-message-"]:not(.closing) {
            opacity: 1 !important;
            background-color: inherit !important;
        }
    </style>';
    
    foreach ($messages as $index => $flash) {
        $type = $flash['type'];
        $message = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
        
        // Define CSS classes based on message type with solid backgrounds
        $typeClasses = [
            'success' => 'bg-green-50 border-green-500 text-green-800',
            'error' => 'bg-red-50 border-red-500 text-red-800',
            'warning' => 'bg-yellow-50 border-yellow-500 text-yellow-800',
            'info' => 'bg-blue-50 border-blue-500 text-blue-800'
        ];
        
        $classes = $typeClasses[$type] ?? $typeClasses['info'];
        
        // Get solid background color for inline style
        $bgColors = [
            'success' => 'background-color: rgb(240 253 244) !important;', // green-50
            'error' => 'background-color: rgb(254 242 242) !important;',   // red-50
            'warning' => 'background-color: rgb(254 252 232) !important;', // yellow-50
            'info' => 'background-color: rgb(239 246 255) !important;'     // blue-50
        ];
        
        $bgStyle = $bgColors[$type] ?? $bgColors['info'];
        
        echo '<div id="flash-message-' . $index . '" class="' . $classes . ' border-l-4 p-5 rounded-xl shadow-2xl min-w-96 max-w-lg transform scale-100 opacity-100 backdrop-blur-sm" style="' . $bgStyle . '">';
        echo '<div class="flex items-center justify-between">';
        echo '<div class="flex items-center">';
        
        // Add icon based on type
        $icons = [
            'success' => '<div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white font-bold mr-3">✓</div>',
            'error' => '<div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center text-white font-bold mr-3">✕</div>',
            'warning' => '<div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center text-white font-bold mr-3">⚠</div>',
            'info' => '<div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold mr-3">ℹ</div>'
        ];
        
        $icon = $icons[$type] ?? $icons['info'];
        echo $icon;
        echo '<div class="flex-1"><span class="text-lg font-medium">' . $message . '</span></div>';
        echo '</div>';
        
        // Close button
        echo '<button onclick="closeFlashMessage(' . $index . ')" class="ml-4 w-6 h-6 bg-gray-200 hover:bg-gray-300 rounded-full flex items-center justify-center text-gray-600 hover:text-gray-800 font-bold transition-colors duration-200">&times;</button>';
        echo '</div>';
        echo '</div>';
    }
    
    echo '</div>';
    
    // Add JavaScript for auto-hide and manual close
    if ($autoHide) {
        echo '<script>
        function closeFlashMessage(index) {
            const message = document.getElementById("flash-message-" + index);
            if (message) {
                // Add closing class and transition only when closing
                message.classList.add("closing");
                message.style.transition = "all 0.3s ease";
                message.classList.add("scale-95", "opacity-0");
                setTimeout(() => {
                    message.remove();
                    checkIfContainerEmpty();
                }, 300);
            }
        }
        
        function checkIfContainerEmpty() {
            const container = document.getElementById("flash-messages-container");
            if (container && container.children.length === 0) {
                container.remove();
            }
        }
        
        // Auto-hide messages after 3.5 seconds with staggered timing
        document.addEventListener("DOMContentLoaded", function() {
            const messages = document.querySelectorAll("[id^=\'flash-message-\']");
            messages.forEach((message, index) => {
                // Auto-hide with staggered timing for multiple messages
                setTimeout(() => {
                    closeFlashMessage(index);
                }, 3500 + (index * 500)); // Each message stays 500ms longer
            });
        });
        </script>';
    }
}

/**
 * Convenience functions for different message types
 */
function setSuccessMessage($message) {
    setFlashMessage('success', $message);
}

function setErrorMessage($message) {
    setFlashMessage('error', $message);
}

function setWarningMessage($message) {
    setFlashMessage('warning', $message);
}

function setInfoMessage($message) {
    setFlashMessage('info', $message);
}
?>
