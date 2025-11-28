<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../middlewares/auth.php";
require_once __DIR__ . "/../includes/validation_helper.php";
require_once __DIR__ . "/../includes/flash_helper.php";
require_once __DIR__ . "/../includes/email_helper.php";

// Redirect if already logged in
redirect_if_authenticated();

// Prevent browser caching for security
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Get validation errors and old input
$validationErrors = FormValidator::getStoredErrors();
$oldInput = FormValidator::getOldInput();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid request. Please try again.');
        header('Location: forgot-password.php');
        exit;
    }
    
    $email = trim($_POST['email'] ?? '');
    
    // Validate email
    $errors = [];
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }
    
    if (!empty($errors)) {
        FormValidator::storeErrors($errors);
        FormValidator::storeOldInput($_POST);
        header('Location: forgot-password.php');
        exit;
    }
    
    // Check rate limiting - max 3 requests per hour per email
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as request_count 
        FROM password_resets 
        WHERE user_id = (SELECT id FROM users WHERE email = ?) 
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$email]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['request_count'] >= 3) {
        setFlashMessage('error', 'Too many reset requests. Please try again later.');
        header('Location: forgot-password.php');
        exit;
    }
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug logging (remove in production)
    error_log("Forgot Password - Email: $email, User found: " . ($user ? 'YES' : 'NO'));
    
    if ($user) {
        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Debug logging
        error_log("Generated token for user ID: " . $user['id']);
        
        // Store token in database
        $stmt = $pdo->prepare("
            INSERT INTO password_resets (user_id, token, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user['id'], $token, $expiresAt]);
        
        error_log("Token stored in database");
        
        // Send email
        error_log("Attempting to send email to: $email");
        $emailSent = sendPasswordResetEmail($email, $user['name'], $token);
        error_log("Email sent result: " . ($emailSent ? 'SUCCESS' : 'FAILED'));
        
        if ($emailSent) {
            setFlashMessage('success', 'Password reset link has been sent to your email address.');
        } else {
            setFlashMessage('error', 'Failed to send email. Please try again later.');
        }
    } else {
        // Don't reveal if email exists or not (security)
        error_log("User not found for email: $email");
        setFlashMessage('success', 'If an account exists with this email, you will receive a password reset link.');
    }
    
    header('Location: forgot-password.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Marketplace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet" />
    <style>
        .shake {
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .field-error {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
        }
    </style>
</head>
<body>
<section class="min-h-screen bg-gradient-to-br from-blue-50 via-purple-50 to-indigo-50 flex items-center justify-center px-4 py-8">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-3xl shadow-2xl p-8 lg:p-12">
            <!-- Header -->
            <div class="mb-8 text-center">
                <div class="w-16 h-16 mx-auto mb-4 bg-gradient-to-br from-blue-600 to-purple-600 rounded-2xl flex items-center justify-center">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-gray-900 mb-2">Forgot Password?</h2>
                <p class="text-gray-600">No worries! Enter your email and we'll send you reset instructions.</p>
            </div>
            
            <!-- Display Flash Messages -->
            <?php displayFlashMessages(); ?>
            
            <!-- Form -->
            <form class="space-y-6" method="POST" id="forgotPasswordForm" novalidate>
                <?php csrfTokenField(); ?>
                
                <!-- Email Field -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                            </svg>
                        </div>
                        <input type="email" 
                               name="email" 
                               id="email"
                               class="<?= inputErrorClass('email', $validationErrors, 'w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200') ?>" 
                               placeholder="Enter your email address" 
                               value="<?= oldValue('email') ?>"
                               autocomplete="email" />
                    </div>
                    <?php displayFieldError('email', $validationErrors); ?>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold py-3 px-4 rounded-xl hover:from-blue-700 hover:to-purple-700 transform hover:scale-[1.02] transition-all duration-200 shadow-lg">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Send Reset Link
                </button>
            </form>
            
            <!-- Back to Login -->
            <div class="mt-6 text-center">
                <a href="index.php?p=login" class="text-sm font-medium text-blue-600 hover:text-blue-500 transition-colors duration-200 inline-flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Login
                </a>
            </div>
        </div>
        
        <!-- Info Box -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-2xl p-4">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                <div class="text-sm text-blue-800">
                    <p class="font-semibold mb-1">What happens next?</p>
                    <ul class="list-disc list-inside space-y-1 text-blue-700">
                        <li>Check your email inbox</li>
                        <li>Click the reset link (valid for 1 hour)</li>
                        <li>Create your new password</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('forgotPasswordForm');
        const emailInput = document.getElementById('email');
        
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Clear previous errors
            clearFieldErrors();
            
            // Email validation
            if (!emailInput.value.trim()) {
                showFieldError(emailInput, 'Email is required');
                isValid = false;
            } else if (!isValidEmail(emailInput.value)) {
                showFieldError(emailInput, 'Please enter a valid email address');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                shakeForm(form);
            }
        });
        
        // Real-time validation
        emailInput.addEventListener('blur', function() {
            if (this.value.trim() && !isValidEmail(this.value)) {
                showFieldError(this, 'Please enter a valid email address');
            }
        });
        
        emailInput.addEventListener('input', function() {
            if (this.classList.contains('field-error')) {
                this.classList.remove('field-error');
                const errorDiv = this.parentNode.parentNode.querySelector('.text-red-600');
                if (errorDiv) {
                    errorDiv.remove();
                }
            }
        });
    });
    
    function showFieldError(field, message) {
        field.classList.add('field-error');
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'mt-1 text-sm text-red-600 flex items-center';
        errorDiv.innerHTML = `
            <svg class="w-4 h-4 mr-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
            ${message}
        `;
        
        field.parentNode.parentNode.appendChild(errorDiv);
    }
    
    function clearFieldErrors() {
        const errorDivs = document.querySelectorAll('.text-red-600');
        errorDivs.forEach(div => div.remove());
        
        const errorFields = document.querySelectorAll('.field-error');
        errorFields.forEach(field => field.classList.remove('field-error'));
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function shakeForm(form) {
        form.classList.add('shake');
        setTimeout(() => {
            form.classList.remove('shake');
        }, 500);
    }
</script>
</body>
</html>
