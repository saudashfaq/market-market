<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/validation_helper.php";
require_once __DIR__ . "/../includes/flash_helper.php";
require_once __DIR__ . "/../includes/email_helper.php";

// Get validation errors and old input
$validationErrors = FormValidator::getStoredErrors();
$oldInput = FormValidator::getOldInput();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid request. Please try again.');
        header('Location: resend-verification.php');
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
        header('Location: resend-verification.php');
        exit;
    }
    
    // Check if user exists
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, name, email_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Check if already verified
        if ($user['email_verified'] == 1) {
            setFlashMessage('info', 'Your email is already verified. You can login now.');
            header('Location: ../index.php?p=login&tab=login');
            exit;
        }
        
        // Generate new verification token
        $verificationToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Update user with new token
        $stmt = $pdo->prepare("
            UPDATE users 
            SET email_verification_token = ?, 
                email_verification_expires_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$verificationToken, $expiresAt, $user['id']]);
        
        // Send verification email
        $emailSent = sendEmailVerificationEmail($email, $user['name'], $verificationToken);
        
        if ($emailSent) {
            setFlashMessage('success', 'Verification email has been sent! Please check your inbox.');
        } else {
            setFlashMessage('error', 'Failed to send email. Please try again later.');
        }
    } else {
        // Don't reveal if email exists or not (security)
        setFlashMessage('success', 'If an account exists with this email, you will receive a verification link.');
    }
    
    header('Location: resend-verification.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification Email - Marketplace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet" />
</head>
<body>
<section class="min-h-screen bg-gradient-to-br from-blue-50 via-purple-50 to-indigo-50 flex items-center justify-center px-4 py-8">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-3xl shadow-2xl p-8 lg:p-12">
            <!-- Header -->
            <div class="mb-8 text-center">
                <div class="w-16 h-16 mx-auto mb-4 bg-gradient-to-br from-blue-600 to-purple-600 rounded-2xl flex items-center justify-center">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-gray-900 mb-2">Resend Verification</h2>
                <p class="text-gray-600">Enter your email to receive a new verification link</p>
            </div>
            
            <!-- Display Flash Messages -->
            <?php displayFlashMessages(); ?>
            
            <!-- Form -->
            <form class="space-y-6" method="POST" novalidate>
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
                    Resend Verification Email
                </button>
            </form>
            
            <!-- Links -->
            <div class="mt-6 text-center space-y-2">
                <a href="../index.php?p=login&tab=login" class="block text-sm font-medium text-blue-600 hover:text-blue-500 transition-colors duration-200">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <p class="font-semibold mb-1">Check your spam folder</p>
                    <p class="text-blue-700">
                        If you don't see the email in your inbox, please check your spam or junk folder.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
</body>
</html>
