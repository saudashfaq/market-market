<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/log_helper.php';

use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\Facebook;

/**
 * Get Google OAuth Provider
 */
function getGoogleProvider() {
    return new Google([
        'clientId'     => GOOGLE_CLIENT_ID,
        'clientSecret' => GOOGLE_CLIENT_SECRET,
        'redirectUri'  => GOOGLE_REDIRECT_URI,
    ]);
}

/**
 * Get Facebook OAuth Provider
 */
function getFacebookProvider() {
    return new Facebook([
        'clientId'     => FACEBOOK_APP_ID,
        'clientSecret' => FACEBOOK_APP_SECRET,
        'redirectUri'  => FACEBOOK_REDIRECT_URI,
        'graphApiVersion' => 'v12.0',
    ]);
}

/**
 * Find or create user from OAuth data
 */
function findOrCreateOAuthUser($provider, $email, $name, $providerId, $avatar = null) {
    $pdo = db();
    
    // Check if user exists with this email
    $stmt = $pdo->prepare("SELECT id, name, email, role, profile_pic, oauth_provider, oauth_provider_id, avatar FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Update OAuth info if not set
        if (empty($user['oauth_provider'])) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET oauth_provider = :provider, 
                    oauth_provider_id = :provider_id,
                    avatar = :avatar
                WHERE id = :id
            ");
            $stmt->execute([
                ':provider' => $provider,
                ':provider_id' => $providerId,
                ':avatar' => $avatar,
                ':id' => $user['id']
            ]);
        }
        
        log_action("OAuth Login", "User logged in via {$provider}: {$email}", "auth", $user['id']);
        return $user;
    }
    
    // Create new user
    // Generate random password for OAuth users
    $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    
    // Check if email_verified column exists
    $columnExists = false;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'email_verified'");
        $columnExists = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $columnExists = false;
    }
    
    if ($columnExists) {
        // OAuth users are auto-verified since email is confirmed by OAuth provider
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, oauth_provider, oauth_provider_id, avatar, email_verified, created_at) 
            VALUES (:name, :email, :password, :provider, :provider_id, :avatar, 1, NOW())
        ");
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => $randomPassword,
            ':provider' => $provider,
            ':provider_id' => $providerId,
            ':avatar' => $avatar
        ]);
    } else {
        // Email verification not enabled
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, oauth_provider, oauth_provider_id, avatar, created_at) 
            VALUES (:name, :email, :password, :provider, :provider_id, :avatar, NOW())
        ");
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => $randomPassword,
            ':provider' => $provider,
            ':provider_id' => $providerId,
            ':avatar' => $avatar
        ]);
    }
    
    $userId = $pdo->lastInsertId();
    log_action("OAuth Registration", "New user registered via {$provider}: {$email}", "auth", $userId);
    
    // Send welcome email in background
    register_shutdown_function(function() use ($email, $name) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        require_once __DIR__ . '/email_helper.php';
        sendWelcomeEmail($email, $name);
    });
    
    // Fetch the newly created user with all fields
    $stmt = $pdo->prepare("SELECT id, name, email, role, profile_pic, oauth_provider, oauth_provider_id, avatar FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Login user via OAuth
 */
function loginOAuthUser($user) {
    $_SESSION['user'] = [
        'id'    => $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'] ?? 'user',
        'profile_picture' => $user['avatar'] ?? $user['profile_pic'] ?? null,
        'oauth_provider' => $user['oauth_provider'] ?? null,
    ];
}
