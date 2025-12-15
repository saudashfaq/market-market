<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/flash_helper.php";

// Get token from URL
$token = $_GET['token'] ?? '';

// Initialize variables
$tokenValid = false;
$tokenExpired = false;
$alreadyVerified = false;
$userName = '';

// Validate token
if (!empty($token)) {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT id, name, email, email_verified, email_verification_expires_at 
        FROM users 
        WHERE email_verification_token = ?
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $userName = $user['name'];

        // Check if already verified
        if ($user['email_verified'] == 1) {
            $alreadyVerified = true;
        } else {
            // Check if token has expired
            $expiresAt = strtotime($user['email_verification_expires_at']);
            $now = time();

            if ($now > $expiresAt) {
                $tokenExpired = true;
            } else {
                // Token is valid - verify the email
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET email_verified = 1, 
                        email_verification_token = NULL,
                        email_verification_expires_at = NULL
                    WHERE id = ?
                ");
                $stmt->execute([$user['id']]);

                // Set token as valid for display
                $tokenValid = true;

                // Log the verification
                error_log("Email verified successfully for user: " . $user['email']);

                // Send welcome email after successful verification
                require_once __DIR__ . '/../includes/email_helper.php';
                sendWelcomeEmail($user['email'], $user['name']);

                // Set success message and redirect to login (DO NOT auto-login)
                require_once __DIR__ . '/../includes/flash_helper.php';
                setSuccessMessage("Email verified successfully! You can now login to your account.");

                // Set flag to show verification success
                $tokenValid = true;
            }
        }
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
    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>

<body>
    <section class="min-h-screen bg-gradient-to-br from-blue-50 via-purple-50 to-indigo-50 flex items-center justify-center px-4 py-8">
        <div class="w-full max-w-md fade-in">
            <div class="bg-white rounded-3xl shadow-2xl p-8 lg:p-12">

                <?php if ($tokenValid): ?>
                    <!-- Email Verified Successfully -->
                    <div class="text-center">
                        <div class="w-20 h-20 mx-auto mb-6 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center">
                            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <h2 class="text-3xl font-bold text-gray-900 mb-3">Email Verified!</h2>
                        <p class="text-gray-600 mb-2">
                            Congratulations, <strong><?= htmlspecialchars($userName) ?></strong>!
                        </p>
                        <p class="text-gray-600 mb-8">
                            Your email has been successfully verified. Please login to access your account.
                        </p>

                        <div class="space-y-3">
                            <a href="<?= url('login') ?>" class="block w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold py-3 px-6 rounded-xl hover:from-blue-700 hover:to-purple-700 transform hover:scale-[1.02] transition-all duration-200 shadow-lg">
                                <i class="fas fa-sign-in-alt mr-2"></i>
                                Login to Your Account
                            </a>
                            <a href="<?= url('home') ?>" class="block w-full bg-gray-100 text-gray-700 font-semibold py-3 px-6 rounded-xl hover:bg-gray-200 transition-all duration-200">
                                <i class="fas fa-home mr-2"></i>
                                Go to Homepage
                            </a>
                        </div>
                    </div>

                <?php elseif ($alreadyVerified): ?>
                    <!-- Already Verified -->
                    <div class="text-center">
                        <div class="w-20 h-20 mx-auto mb-6 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-3xl font-bold text-gray-900 mb-3">Already Verified</h2>
                        <p class="text-gray-600 mb-8">
                            Your email has already been verified. You can login to your account.
                        </p>
                        <a href="<?= url('login') ?>" class="inline-block bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold py-3 px-6 rounded-xl hover:from-blue-700 hover:to-purple-700 transform hover:scale-[1.02] transition-all duration-200 shadow-lg">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Go to Login
                        </a>
                    </div>

                <?php elseif ($tokenExpired): ?>
                    <!-- Token Expired -->
                    <div class="text-center">
                        <div class="w-20 h-20 mx-auto mb-6 bg-orange-100 rounded-full flex items-center justify-center">
                            <svg class="w-10 h-10 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-3xl font-bold text-gray-900 mb-3">Link Expired</h2>
                        <p class="text-gray-600 mb-8">
                            This verification link has expired. Please request a new verification email.
                        </p>
                        <a href="<?= url('resend-verification') ?>" class="inline-block bg-gradient-to-r from-orange-500 to-red-500 text-white font-semibold py-3 px-6 rounded-xl hover:from-orange-600 hover:to-red-600 transform hover:scale-[1.02] transition-all duration-200 shadow-lg">
                            <i class="fas fa-redo mr-2"></i>
                            Resend Verification Email
                        </a>
                    </div>

                <?php else: ?>
                    <!-- Invalid Token -->
                    <div class="text-center">
                        <div class="w-20 h-20 mx-auto mb-6 bg-red-100 rounded-full flex items-center justify-center">
                            <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </div>
                        <h2 class="text-3xl font-bold text-gray-900 mb-3">Invalid Link</h2>
                        <p class="text-gray-600 mb-8">
                            This verification link is invalid or has already been used.
                        </p>
                        <div class="space-y-3">
                            <a href="<?= url('resend-verification') ?>" class="block w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold py-3 px-6 rounded-xl hover:from-blue-700 hover:to-purple-700 transform hover:scale-[1.02] transition-all duration-200 shadow-lg">
                                <i class="fas fa-redo mr-2"></i>
                                Resend Verification Email
                            </a>
                            <a href="<?= url('login') ?>" class="block w-full bg-gray-100 text-gray-700 font-semibold py-3 px-6 rounded-xl hover:bg-gray-200 transition-all duration-200">
                                <i class="fas fa-sign-in-alt mr-2"></i>
                                Go to Login
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Help Box -->
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-2xl p-4">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                    <div class="text-sm text-blue-800">
                        <p class="font-semibold mb-1">Need Help?</p>
                        <p class="text-blue-700">
                            If you're having trouble verifying your email, please contact our support team.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>

</html>