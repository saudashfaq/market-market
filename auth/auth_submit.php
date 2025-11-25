<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/log_helper.php";
require_once __DIR__ . "/../includes/flash_helper.php";
require_once __DIR__ . "/../includes/validation_helper.php";
require_once __DIR__ . "/../includes/popup_helper.php";

// form submit check
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setErrorMessage('Invalid request. Please try again.');
        header("Location: " . url('index.php?p=login&tab=signup'));
        exit;
    }

    // Create validator with form data
    $validator = new FormValidator($_POST);
    
    
    $validator
        ->required('name', 'Full name is required')
        ->name('name', 'Name can only contain letters, spaces, hyphens and apostrophes')
        ->required('email', 'Email address is required')
        ->email('email', 'Please enter a valid email address')
        ->required('password', 'Password is required')
        ->minLength('password', 6, 'Password must be at least 6 characters long')
        ->required('confirm_password', 'Please confirm your password')
        ->confirmPassword('password', 'confirm_password', 'Passwords do not match');

    // Check email duplication if basic validation passes
    if ($validator->passes()) {
        $email = trim($_POST['email']);
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $validator->custom('email', function() { return false; }, 'This email address is already registered');
        }
    }

    // If validation fails, store errors and redirect
    if ($validator->fails()) {
        $validator->storeErrors();
        header("Location: " . url('index.php?p=login&tab=signup'));
        exit;
    }

    // Insert user if validation passes
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if email_verified column exists
    $pdo = db();
    $columnExists = false;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'email_verified'");
        $columnExists = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $columnExists = false;
    }
    
    if ($columnExists) {
        // Email verification enabled
        $verificationToken = bin2hex(random_bytes(32));
        $verificationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, email_verified, email_verification_token, email_verification_expires_at) 
            VALUES (:n, :e, :p, 0, :token, :expires)
        ");
        $stmt->execute([
            ':n' => $name,
            ':e' => $email,
            ':p' => $hash,
            ':token' => $verificationToken,
            ':expires' => $verificationExpires
        ]);
        
        $newUserId = $pdo->lastInsertId();
        log_action("User Registered", "New user registered: {$name} ({$email})", "auth", $newUserId);
        
        // Send verification email and notify superadmin
        register_shutdown_function(function() use ($email, $name, $verificationToken, $newUserId, $pdo) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            require_once __DIR__ . '/../includes/email_helper.php';
            
            // Send verification email to user
            sendEmailVerificationEmail($email, $name, $verificationToken);
            
            // Notify superadmin about new user
            sendSuperAdminNotification(
                'New User Registered',
                'New User Account Created',
                'A new user has registered on your marketplace platform.',
                [
                    'User ID' => '#' . $newUserId,
                    'Name' => $name,
                    'Email' => $email,
                    'Registration Date' => date('F j, Y \a\t g:i A'),
                    'Status' => 'Email verification pending'
                ],
                url('index.php?p=dashboard&page=userManagement')
            );
        });
    } else {
        // Email verification disabled (columns not added yet)
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (:n, :e, :p)");
        $stmt->execute([
            ':n' => $name,
            ':e' => $email,
            ':p' => $hash
        ]);
        
        $newUserId = $pdo->lastInsertId();
        log_action("User Registered", "New user registered: {$name} ({$email})", "auth", $newUserId);
        
        // Send welcome email and notify superadmin
        register_shutdown_function(function() use ($email, $name, $newUserId, $pdo) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            require_once __DIR__ . '/../includes/email_helper.php';
            
            // Send welcome email to user
            sendWelcomeEmail($email, $name);
            
            // Notify superadmin about new user
            sendSuperAdminNotification(
                'New User Registered',
                'New User Account Created',
                'A new user has registered on your marketplace platform.',
                [
                    'User ID' => '#' . $newUserId,
                    'Name' => $name,
                    'Email' => $email,
                    'Registration Date' => date('F j, Y \a\t g:i A'),
                    'Status' => 'Active (no verification required)'
                ],
                url('index.php?p=dashboard&page=userManagement')
            );
        });
    }

    // Check if email verification is enabled
    if ($columnExists) {
        // Email verification enabled - don't auto-login
        FormValidator::clearOldInput();
        setSuccessPopup("Account created successfully! Please check your email to verify your account.", [
            'title' => 'Registration Successful',
            'autoClose' => false
        ]);
        header("Location: " . url('index.php?p=login&tab=login'));
        exit;
    } else {
        // Email verification disabled - auto-login
        $_SESSION['user'] = [
            'id'    => $newUserId,
            'name'  => $name,
            'email' => $email,
            'role'  => 'user',
            'profile_picture' => null,
        ];

        FormValidator::clearOldInput();
        setSuccessPopup("Welcome! Your account has been created successfully.", [
            'title' => 'Welcome to MarketPlace',
            'autoClose' => true,
            'autoCloseTime' => 4000
        ]);
        header("Location: " . url('index.php?p=dashboard&page=userDashboard'));
        exit;
    }
}

// If not POST request, redirect to signup
header("Location: " . url('index.php?p=login&tab=signup'));
exit;
