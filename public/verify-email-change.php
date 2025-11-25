<?php
/**
 * Email Change Verification Page
 * Verifies the new email address and completes the change
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/email_verification_helper.php';
require_once __DIR__ . '/../includes/flash_helper.php';

$token = $_GET['token'] ?? '';
$success = false;
$error = false;
$message = '';

if (empty($token)) {
    $error = true;
    $message = 'Invalid verification link';
} else {
    // Verify token
    $result = verifyEmailChangeToken($token);
    
    if ($result['success']) {
        $user = $result['user'];
        
        // Complete email change
        $changeResult = completeEmailChange(
            $user['id'],
            $user['email'],
            $user['pending_email'],
            $user['name']
        );
        
        if ($changeResult['success']) {
            $success = true;
            $message = 'Your email has been successfully changed to ' . htmlspecialchars($user['pending_email']);
        } else {
            $error = true;
            $message = $changeResult['message'];
        }
    } else {
        $error = true;
        $message = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Marketplace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-gradient-to-br from-blue-50 via-purple-50 to-indigo-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-2xl p-8 text-center">
            <?php if ($success): ?>
                <!-- Success State -->
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6 animate-bounce">
                    <i class="fas fa-check-circle text-green-600 text-4xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-3">Email Verified!</h1>
                <p class="text-gray-600 mb-6"><?= $message ?></p>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <p class="text-sm text-green-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        You can now use your new email address to log in to your account.
                    </p>
                </div>
                <a href="<?= BASE ?>index.php?p=login" 
                   class="inline-block bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold py-3 px-8 rounded-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-200 shadow-lg hover:shadow-xl">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Go to Login
                </a>
                
            <?php elseif ($error): ?>
                <!-- Error State -->
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-times-circle text-red-600 text-4xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-3">Verification Failed</h1>
                <p class="text-gray-600 mb-6"><?= $message ?></p>
                
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 text-left">
                    <p class="text-sm text-red-800 font-semibold mb-2">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Possible reasons:
                    </p>
                    <ul class="text-sm text-red-700 space-y-1 ml-6">
                        <li>• The verification link has expired (24 hours)</li>
                        <li>• The link has already been used</li>
                        <li>• The link is invalid or corrupted</li>
                    </ul>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="<?= BASE ?>index.php?p=dashboard&page=profile" 
                       class="inline-block bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg hover:bg-blue-700 transition-all duration-200">
                        <i class="fas fa-user-cog mr-2"></i>
                        Go to Profile
                    </a>
                    <a href="<?= BASE ?>index.php?p=login" 
                       class="inline-block border-2 border-blue-600 text-blue-600 font-semibold py-3 px-6 rounded-lg hover:bg-blue-50 transition-all duration-200">
                        <i class="fas fa-home mr-2"></i>
                        Go to Home
                    </a>
                </div>
            <?php endif; ?>
            
            <!-- Footer -->
            <div class="mt-8 pt-6 border-t border-gray-200">
                <p class="text-xs text-gray-500">
                    <i class="fas fa-shield-alt mr-1"></i>
                    This is a secure verification process
                </p>
            </div>
        </div>
        
        <!-- Help Text -->
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                Need help? 
                <a href="#" class="text-blue-600 hover:underline font-medium">Contact Support</a>
            </p>
        </div>
    </div>
</body>
</html>
