<?php
/**
 * Form Validation Helper
 * Provides field-specific validation with professional error messages
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class FormValidator {
    private $errors = [];
    private $data = [];
    
    public function __construct($data = []) {
        $this->data = $data;
    }
    
    /**
     * Validate required field
     */
    public function required($field, $message = null) {
        $value = trim($this->data[$field] ?? '');
        if (empty($value)) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' is required';
        }
        return $this;
    }
    
    /**
     * Validate email format
     */
    public function email($field, $message = null) {
        $value = trim($this->data[$field] ?? '');
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?? 'Please enter a valid email address';
        }
        return $this;
    }
    
    /**
     * Validate minimum length
     */
    public function minLength($field, $length, $message = null) {
        $value = $this->data[$field] ?? '';
        if (!empty($value) && strlen($value) < $length) {
            $this->errors[$field] = $message ?? ucfirst($field) . " must be at least {$length} characters long";
        }
        return $this;
    }
    
    /**
     * Validate password confirmation
     */
    public function confirmPassword($passwordField, $confirmField, $message = null) {
        $password = $this->data[$passwordField] ?? '';
        $confirm = $this->data[$confirmField] ?? '';
        
        if (!empty($password) && !empty($confirm) && $password !== $confirm) {
            $this->errors[$confirmField] = $message ?? 'Passwords do not match';
        }
        return $this;
    }
    
    /**
     * Validate name format (letters, spaces, hyphens only)
     */
    public function name($field, $message = null) {
        $value = trim($this->data[$field] ?? '');
        if (!empty($value) && !preg_match('/^[a-zA-Z\s\-\'\.]+$/', $value)) {
            $this->errors[$field] = $message ?? 'Name can only contain letters, spaces, hyphens and apostrophes';
        }
        return $this;
    }
    
    /**
     * Custom validation rule
     */
    public function custom($field, $callback, $message) {
        $value = $this->data[$field] ?? '';
        if (!$callback($value)) {
            $this->errors[$field] = $message;
        }
        return $this;
    }
    
    /**
     * Check if validation passed
     */
    public function passes() {
        return empty($this->errors);
    }
    
    /**
     * Check if validation failed
     */
    public function fails() {
        return !empty($this->errors);
    }
    
    /**
     * Get all errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get error for specific field
     */
    public function getError($field) {
        return $this->errors[$field] ?? null;
    }
    
    /**
     * Check if field has error
     */
    public function hasError($field) {
        return isset($this->errors[$field]);
    }
    
    /**
     * Store errors in session for display
     */
    public function storeErrors() {
        if (!empty($this->errors)) {
            $_SESSION['validation_errors'] = $this->errors;
            $_SESSION['old_input'] = $this->data;
        }
    }
    
    /**
     * Get stored validation errors from session
     */
    public static function getStoredErrors() {
        $errors = $_SESSION['validation_errors'] ?? [];
        unset($_SESSION['validation_errors']);
        return $errors;
    }
    
    /**
     * Get old input values from session
     */
    public static function getOldInput($field = null) {
        $oldInput = $_SESSION['old_input'] ?? [];
        
        if ($field === null) {
            unset($_SESSION['old_input']);
            return $oldInput;
        }
        
        return $oldInput[$field] ?? '';
    }
    
    /**
     * Clear old input from session
     */
    public static function clearOldInput() {
        unset($_SESSION['old_input']);
    }
}

/**
 * Helper function to display field error
 */
function displayFieldError($field, $errors = null) {
    if ($errors === null) {
        $errors = FormValidator::getStoredErrors();
    }
    
    if (isset($errors[$field])) {
        echo '<div class="mt-1 text-sm text-red-600 flex items-center">';
        echo '<svg class="w-4 h-4 mr-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">';
        echo '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>';
        echo '</svg>';
        echo htmlspecialchars($errors[$field]);
        echo '</div>';
    }
}

/**
 * Helper function to get old input value
 */
function oldValue($field, $default = '') {
    return htmlspecialchars(FormValidator::getOldInput($field) ?: $default);
}

/**
 * Helper function to add error class to input
 */
function inputErrorClass($field, $errors = null, $normalClass = '', $errorClass = 'border-red-500 focus:ring-red-500') {
    if ($errors === null) {
        $errors = $_SESSION['validation_errors'] ?? [];
    }
    
    if (isset($errors[$field])) {
        return $normalClass . ' ' . $errorClass;
    }
    
    return $normalClass;
}

/**
 * Generate CSRF token and store in session
 */
function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * Validate CSRF token against session
 */
function validateCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        error_log("CSRF validation failed: Session token: " . (isset($_SESSION['csrf_token']) ? 'SET' : 'NOT SET') . ", Received token: " . ($token ? 'PROVIDED' : 'EMPTY'));
        return false;
    }
    
    $isValid = hash_equals($_SESSION['csrf_token'], $token);
    
    error_log("CSRF validation result: " . ($isValid ? 'VALID' : 'INVALID'));
    
    // Don't regenerate token immediately to allow for form resubmissions
    // Only regenerate on successful form processing
    
    return $isValid;
}

/**
 * Get current CSRF token from session
 */
function getCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        return generateCsrfToken();
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Output CSRF token as hidden input field
 */
function csrfTokenField() {
    $token = getCsrfToken();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Check if there are any validation errors
 */
function hasValidationErrors() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return !empty($_SESSION['validation_errors']);
}

/**
 * Get all validation errors
 */
function getAllValidationErrors() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['validation_errors'] ?? [];
}

/**
 * Get validation error for a specific field
 */
function getValidationError($field) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['validation_errors'][$field] ?? null;
}

/**
 * Clear all validation errors
 */
function clearValidationErrors() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['validation_errors']);
}
?>