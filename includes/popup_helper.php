<?php
/**
 * Popup Helper - Server side popup message management
 * Alert ki jagah modern popup system
 */

class PopupHelper {
    
    public static function setPopup($message, $type = 'info', $options = []) {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $_SESSION['popup_message'] = [
            'message' => $message,
            'type' => $type,
            'options' => $options
        ];
    }
    
    public static function setSuccess($message, $options = []) {
        self::setPopup($message, 'success', $options);
    }
    
    public static function setError($message, $options = []) {
        self::setPopup($message, 'error', $options);
    }
    
    public static function setWarning($message, $options = []) {
        self::setPopup($message, 'warning', $options);
    }
    
    public static function setInfo($message, $options = []) {
        self::setPopup($message, 'info', $options);
    }
    
    public static function hasPopup() {
        if (!isset($_SESSION)) {
            session_start();
        }
        return isset($_SESSION['popup_message']);
    }
    
    public static function getPopup() {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        if (isset($_SESSION['popup_message'])) {
            $popup = $_SESSION['popup_message'];
            unset($_SESSION['popup_message']);
            return $popup;
        }
        
        return null;
    }
    
    public static function renderPopupScript() {
        if (self::hasPopup()) {
            $popup = self::getPopup();
            $message = addslashes($popup['message']);
            $type = $popup['type'];
            $options = json_encode($popup['options']);
            
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    showPopup('$message', '$type', $options);
                });
            </script>";
        }
    }
    
    // Flash message compatibility
    public static function setFlash($message, $type = 'info') {
        self::setPopup($message, $type);
    }
    
    public static function getFlash() {
        return self::getPopup();
    }
}

// Convenience functions
function setPopup($message, $type = 'info', $options = []) {
    PopupHelper::setPopup($message, $type, $options);
}

function setSuccessPopup($message, $options = []) {
    PopupHelper::setSuccess($message, $options);
}

function setErrorPopup($message, $options = []) {
    PopupHelper::setError($message, $options);
}

function setWarningPopup($message, $options = []) {
    PopupHelper::setWarning($message, $options);
}

function setInfoPopup($message, $options = []) {
    PopupHelper::setInfo($message, $options);
}

function renderPopup() {
    PopupHelper::renderPopupScript();
}
?>