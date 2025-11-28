<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/validation_helper.php';
require_once __DIR__ . '/../../includes/flash_helper.php';
require_login();

$user = current_user();
$pdo = db();

// CSRF validation
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setErrorMessage('Invalid request. Please try again.');
    header("Location: index.php?p=dashboard&page=profile");
    exit;
}

// Create validator with form data
$validator = new FormValidator($_POST);

// Validate basic fields
$validator
    ->required('name', 'Full name is required')
    ->name('name', 'Name can only contain letters, spaces, hyphens and apostrophes')
    ->required('email', 'Email address is required')
    ->email('email', 'Please enter a valid email address');

// Validate password change if provided
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
    $validator
        ->required('current_password', 'Current password is required to change password')
        ->required('new_password', 'New password is required')
        ->minLength('new_password', 6, 'New password must be at least 6 characters long')
        ->required('confirm_password', 'Please confirm your new password')
        ->confirmPassword('new_password', 'confirm_password', 'New passwords do not match');
}

// Check if email is already taken by another user
if ($validator->passes()) {
    $email = trim($_POST['email']);
    if ($email !== $user['email']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user['id']]);
        if ($stmt->fetch()) {
            $validator->custom('email', function () {
                return false;
            }, 'This email address is already taken');
        }
    }
}

// Validate current password if password change is requested
if ($validator->passes() && !empty($current_password)) {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($current_password, $hash)) {
        $validator->custom('current_password', function () {
            return false;
        }, 'Current password is incorrect');
    }
}

// If validation fails, store errors and redirect back
if ($validator->fails()) {
    $validator->storeErrors();
    header("Location: index.php?p=dashboard&page=profile");
    exit;
}

// Validation passed, proceed with updates
$name = trim($_POST['name']);
$email = trim($_POST['email']);
$profile_pic = $user['profile_pic'] ?? null;

// Handle profile picture upload with validation
if (!empty($_FILES['profile_pic']['name'])) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if (!in_array($_FILES['profile_pic']['type'], $allowedTypes)) {
        setErrorMessage('Please upload a valid image file (JPEG, PNG, GIF, or WebP).');
        header("Location: index.php?p=dashboard&page=profile");
        exit;
    }

    if ($_FILES['profile_pic']['size'] > $maxSize) {
        setErrorMessage('Image file size must be less than 5MB.');
        header("Location: index.php?p=dashboard&page=profile");
        exit;
    }

    $targetDir = dirname(__DIR__, 2) . '/public/uploads/profile_pics/';
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
    $filename = 'user_' . $user['id'] . '_' . time() . '.' . $ext;
    $targetFile = $targetDir . $filename;

    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetFile)) {
        $profile_pic = 'uploads/profile_pics/' . $filename;
    } else {
        setErrorMessage('Failed to upload profile picture. Please try again.');
        header("Location: index.php?p=dashboard&page=profile");
        exit;
    }
}

// Update user info
$stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, profile_pic = ? WHERE id = ?");
$stmt->execute([$name, $email, $profile_pic, $user['id']]);

$passwordUpdated = false;

// Handle password change (if requested and validated)
if (!empty($current_password) && !empty($new_password)) {
    $newHash = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password and reset requires_password_change flag
    try {
        // Try to update both (optimistic)
        $stmt = $pdo->prepare("UPDATE users SET password = ?, requires_password_change = 0 WHERE id = ?");
        $stmt->execute([$newHash, $user['id']]);
    } catch (Exception $e) {
        // If it fails (likely because column doesn't exist), update only password
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $user['id']]);
    }

    $passwordUpdated = true;

    // Send password changed confirmation email
    require_once __DIR__ . '/../../includes/email_helper.php';
    sendPasswordChangedEmail($email, $name);
}

// Update session with fresh user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$_SESSION['user'] = $stmt->fetch(PDO::FETCH_ASSOC);

if ($passwordUpdated) {
    setSuccessMessage("Profile and password updated successfully!");
} else {
    setSuccessMessage("Profile updated successfully!");
}

header("Location: index.php?p=dashboard&page=profile");
exit;
