<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../middlewares/auth.php";
require_once __DIR__ . "/../includes/validation_helper.php";
require_once __DIR__ . "/../includes/flash_helper.php";

// Redirect if already logged in
redirect_if_authenticated();

// Prevent browser caching for security
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Get token from URL
$token = $_GET['token'] ?? '';

// Get validation errors and old input
$validationErrors = FormValidator::getStoredErrors();

// Initialize variables
$tokenValid = false;
$tokenExpired = false;
$userId = null;

// Validate token
if (!empty($token)) {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT user_id, expires_at 
        FROM password_resets 
        WHERE token = ?
    ");
    $stmt->execute([$token]);
    $resetData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resetData) {
        // Check if token has expired
        $expiresAt = strtotime($resetData['expires_at']);
        $now = time();

        if ($now > $expiresAt) {
            $tokenExpired = true;
        } else {
            $tokenValid = true;
            $userId = $resetData['user_id'];
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    // Verify CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid request. Please try again.');
        header('Location: ' . url('reset-password/' . urlencode($token)));
        exit;
    }

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate passwords
    $errors = [];

    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters long';
    }

    if (empty($confirmPassword)) {
        $errors['confirm_password'] = 'Please confirm your password';
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match';
    }

    if (!empty($errors)) {
        FormValidator::storeErrors($errors);
        header('Location: ' . url('reset-password/' . urlencode($token)));
        exit;
    }

    // Update password and reset requires_password_change flag
    $pdo = db();
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Check if requires_password_change column exists and reset it
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'requires_password_change'");
        if ($stmt->rowCount() > 0) {
            // Column exists, update both password and flag
            $stmt = $pdo->prepare("UPDATE users SET password = ?, requires_password_change = 0 WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);
        } else {
            // Column doesn't exist, just update password
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);
        }
    } catch (Exception $e) {
        // Fallback to just updating password
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
    }

    // Get user details for email
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Delete used token
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);

    // Send password changed confirmation email
    require_once __DIR__ . '/../includes/email_helper.php';
    sendPasswordChangedEmail($user['email'], $user['name']);

    // Set success message and redirect to login
    setFlashMessage('success', 'Your password has been reset successfully. Please login with your new password.');
    header('Location: ' . url('login'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Marketplace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet" />
    <style>
        .shake {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px);
            }

            75% {
                transform: translateX(5px);
            }
        }

        .field-error {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
        }

        .password-strength {
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
    </style>
</head>

<body>
    <section class="min-h-screen bg-gradient-to-br from-blue-50 via-purple-50 to-indigo-50 flex items-center justify-center px-4 py-8">
        <div class="w-full max-w-md">
            <div class="bg-white rounded-3xl shadow-2xl p-8 lg:p-12">

                <?php if (!$tokenValid): ?>
                    <!-- Invalid/Expired Token -->
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto mb-4 bg-red-100 rounded-2xl flex items-center justify-center">
                            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">
                            <?= $tokenExpired ? 'Link Expired' : 'Invalid Link' ?>
                        </h2>
                        <p class="text-gray-600 mb-6">
                            <?php if ($tokenExpired): ?>
                                This password reset link has expired. Please request a new one.
                            <?php else: ?>
                                This password reset link is invalid or has already been used.
                            <?php endif; ?>
                        </p>
                        <a href="<?= url('forgotPassword') ?>" class="inline-block bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold py-3 px-6 rounded-xl hover:from-blue-700 hover:to-purple-700 transform hover:scale-[1.02] transition-all duration-200 shadow-lg">
                            <i class="fas fa-redo mr-2"></i>
                            Request New Link
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Valid Token - Show Reset Form -->
                    <div class="mb-8 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <h2 class="text-3xl font-bold text-gray-900 mb-2">Create New Password</h2>
                        <p class="text-gray-600">Enter your new password below</p>
                    </div>

                    <!-- Display Flash Messages -->
                    <?php displayFlashMessages(); ?>

                    <!-- Form -->
                    <form class="space-y-6" method="POST" id="resetPasswordForm" novalidate>
                        <?php csrfTokenField(); ?>

                        <!-- Password Field -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                </div>
                                <input type="password"
                                    name="password"
                                    id="password"
                                    class="<?= inputErrorClass('password', $validationErrors, 'w-full pl-10 pr-12 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200') ?>"
                                    placeholder="Enter new password" />
                                <button type="button"
                                    onclick="togglePassword('password')"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <svg class="h-5 w-5 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" id="password-eye">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                            </div>
                            <!-- Password Strength Indicator -->
                            <div class="mt-2" id="strength-container" style="display: none;">
                                <div class="password-strength bg-gray-200" id="strength-bar"></div>
                                <p class="text-xs text-gray-500 mt-1" id="strength-text">Password strength</p>
                            </div>
                            <?php displayFieldError('password', $validationErrors); ?>
                            <!-- Password Requirements -->
                            <div class="mt-2 space-y-1" id="password-requirements" style="display: none;">
                                <p class="text-xs font-medium text-gray-600 mb-1">Password must contain:</p>
                                <div class="flex items-center text-xs" id="req-length">
                                    <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    <span class="text-gray-500">At least 6 characters</span>
                                </div>
                                <div class="flex items-center text-xs" id="req-letter">
                                    <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    <span class="text-gray-500">One letter (a-z or A-Z)</span>
                                </div>
                                <div class="flex items-center text-xs" id="req-number">
                                    <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    <span class="text-gray-500">One number (0-9)</span>
                                </div>
                            </div>
                        </div>

                        <!-- Confirm Password Field -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <input type="password"
                                    name="confirm_password"
                                    id="confirm_password"
                                    class="<?= inputErrorClass('confirm_password', $validationErrors, 'w-full pl-10 pr-12 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200') ?>"
                                    placeholder="Confirm new password" />
                                <button type="button"
                                    onclick="togglePassword('confirm_password')"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <svg class="h-5 w-5 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                            </div>
                            <?php displayFieldError('confirm_password', $validationErrors); ?>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold py-3 px-4 rounded-xl hover:from-blue-700 hover:to-purple-700 transform hover:scale-[1.02] transition-all duration-200 shadow-lg">
                            <i class="fas fa-check-circle mr-2"></i>
                            Reset Password
                        </button>
                    </form>

                    <!-- Back to Login -->
                    <div class="mt-6 text-center">
                        <a href="<?= url('login') ?>" class="text-sm font-medium text-blue-600 hover:text-blue-500 transition-colors duration-200 inline-flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back to Login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            field.type = field.type === 'password' ? 'text' : 'password';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetPasswordForm');
            if (!form) return;

            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm_password');
            const strengthBar = document.getElementById('strength-bar');
            const strengthText = document.getElementById('strength-text');

            // Password strength checker
            const strengthContainer = document.getElementById('strength-container');
            const requirementsContainer = document.getElementById('password-requirements');
            const reqLength = document.getElementById('req-length');
            const reqLetter = document.getElementById('req-letter');
            const reqNumber = document.getElementById('req-number');

            passwordInput.addEventListener('input', function() {
                const password = this.value;

                // Show/hide strength indicator and requirements
                if (password.length > 0) {
                    strengthContainer.style.display = 'block';
                    requirementsContainer.style.display = 'block';
                } else {
                    strengthContainer.style.display = 'none';
                    requirementsContainer.style.display = 'none';
                    return;
                }

                // Check requirements
                const hasLength = password.length >= 6;
                const hasLetter = /[a-zA-Z]/.test(password);
                const hasNumber = /[0-9]/.test(password);

                // Update requirement indicators
                updateRequirement(reqLength, hasLength);
                updateRequirement(reqLetter, hasLetter);
                updateRequirement(reqNumber, hasNumber);

                let strength = 0;

                // Simple strength calculation
                if (password.length >= 6) strength++;
                if (password.length >= 8) strength++;
                if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
                if (password.match(/[0-9]/)) strength++;
                if (password.match(/[^a-zA-Z0-9]/)) strength++;

                const colors = ['#ef4444', '#f59e0b', '#eab308', '#84cc16', '#22c55e'];
                const texts = ['Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
                const widths = ['20%', '40%', '60%', '80%', '100%'];

                strengthBar.style.width = widths[strength - 1] || '20%';
                strengthBar.style.backgroundColor = colors[strength - 1] || colors[0];
                strengthText.textContent = texts[strength - 1] || texts[0];
                strengthText.style.color = colors[strength - 1] || colors[0];
            });

            function updateRequirement(element, isMet) {
                const svg = element.querySelector('svg');
                const span = element.querySelector('span');

                if (isMet) {
                    // Show checkmark
                    svg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>';
                    svg.classList.remove('text-gray-400');
                    svg.classList.add('text-green-500');
                    span.classList.remove('text-gray-500');
                    span.classList.add('text-green-600');
                } else {
                    // Show X mark
                    svg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
                    svg.classList.remove('text-green-500');
                    svg.classList.add('text-gray-400');
                    span.classList.remove('text-green-600');
                    span.classList.add('text-green-500');
                }
            }

            // Form validation
            form.addEventListener('submit', function(e) {
                let isValid = true;
                clearFieldErrors();

                const password = passwordInput.value;
                const confirmPassword = confirmInput.value;

                // Password validation
                if (!password) {
                    showFieldError(passwordInput, 'Password is required');
                    isValid = false;
                } else if (password.length < 6) {
                    showFieldError(passwordInput, 'Password must be at least 6 characters long');
                    isValid = false;
                }

                // Confirm password validation
                if (!confirmPassword) {
                    showFieldError(confirmInput, 'Please confirm your password');
                    isValid = false;
                } else if (password !== confirmPassword) {
                    showFieldError(confirmInput, 'Passwords do not match');
                    isValid = false;
                }

                if (!isValid) {
                    e.preventDefault();
                    shakeForm(form);
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
            errorDivs.forEach(div => {
                if (div.classList.contains('flex')) {
                    div.remove();
                }
            });

            const errorFields = document.querySelectorAll('.field-error');
            errorFields.forEach(field => field.classList.remove('field-error'));
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