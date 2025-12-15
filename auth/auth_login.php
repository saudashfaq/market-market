<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/flash_helper.php";
require_once __DIR__ . "/../includes/log_helper.php";
require_once __DIR__ . "/../includes/validation_helper.php";
require_once __DIR__ . "/../includes/popup_helper.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setErrorMessage("Invalid request. Please try again.");
        header("Location: " . url("public/index.php?p=login&tab=login"));
        exit;
    }

    // Create validator with form data
    $validator = new FormValidator($_POST);

    // Validate fields with professional messages
    $validator
        ->required('email', 'Email address is required')
        ->email('email', 'Please enter a valid email address')
        ->required('password', 'Password is required');

    // If validation fails, store errors and redirect
    if ($validator->fails()) {
        $validator->storeErrors();
        header("Location: " . url("public/index.php?p=login&tab=login"));
        exit;
    }

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        $pdo = db();

        // Check if email_verified column exists
        $columnExists = false;
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'email_verified'");
            $columnExists = $stmt->rowCount() > 0;
        } catch (Exception $e) {
            $columnExists = false;
        }

        if ($columnExists) {
            $stmt = $pdo->prepare("SELECT id, name, email, password, role, profile_pic, email_verified FROM users WHERE email = ?");
        } else {
            $stmt = $pdo->prepare("SELECT id, name, email, password, role, profile_pic FROM users WHERE email = ?");
        }

        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Check if email is verified (only if column exists)
            if ($columnExists && isset($user['email_verified']) && $user['email_verified'] == 0) {
                setWarningMessage("Please verify your email address to continue. You can resend the verification email from the signup page or contact support.");

                // Also set a popup with resend functionality
                require_once __DIR__ . "/../includes/popup_helper.php";
                setWarningPopup(
                    "Please verify your email address before logging in. Check your inbox and spam folder for the verification email.",
                    [
                        'title' => 'Email Verification Required',
                        'autoClose' => false,
                        'showResendOption' => true,
                        'userEmail' => $email
                    ]
                );
                header("Location: " . url("public/index.php?p=login&tab=login"));
                exit;
            }

            // Check if requires_password_change column exists and is set
            $requiresPasswordChange = false;
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'requires_password_change'");
                if ($stmt->rowCount() > 0) {
                    // Column exists, fetch the value
                    $stmt = $pdo->prepare("SELECT requires_password_change FROM users WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $requiresPasswordChange = isset($result['requires_password_change']) && $result['requires_password_change'] == 1;
                }
            } catch (Exception $e) {
                // Column doesn't exist or error, continue normally
                $requiresPasswordChange = false;
            }

            $_SESSION['user'] = [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'] ?? 'user',
                'profile_picture' => $user['profile_pic'],
            ];

            log_action("User logged in", "User logged in: {$user['name']} ({$user['role']})");

            // Clear old input
            FormValidator::clearOldInput();

            // If password change is required, redirect to profile page
            if ($requiresPasswordChange) {
                $_SESSION['password_change_required'] = true;
                setWarningMessage("For security reasons, you must change your password before continuing.");
                header("Location: " . url("public/index.php?p=dashboard&page=profile"));
                exit;
            }

            // Normal login flow - show success popup
            setSuccessPopup("Welcome back, " . $user['name'] . "!", [
                'title' => 'Login Successful',
                'autoClose' => true,
                'autoCloseTime' => 3000
            ]);

            if ($user['role'] === 'admin') {
                header("Location: " . url("public/index.php?p=dashboard&page=adminDashboard"));
            } elseif ($user['role'] === 'superadmin') {
                header("Location: " . url("public/index.php?p=dashboard&page=superAdminDashboard"));
            } else {
                header("Location: " . url("public/index.php?p=dashboard&page=userDashboard"));
            }
            exit;
        } else {
            // Store field-specific error for invalid credentials
            $validator = new FormValidator($_POST);
            $validator->custom('email', function () {
                return false;
            }, 'Invalid email or password');
            $validator->storeErrors();
            header("Location: " . url("public/index.php?p=login&tab=login"));
            exit;
        }
    } catch (PDOException $e) {
        // Log error in real apps, don't expose DB error
        setErrorMessage("Something went wrong. Please try again later.");
        header("Location: " . url("public/index.php?p=login&tab=login"));
        exit;
    }
}
