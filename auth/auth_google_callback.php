<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/oauth_helper.php';
require_once __DIR__ . '/../includes/flash_helper.php';

try {
    // Check if credentials are configured
    if (empty(GOOGLE_CLIENT_ID) || GOOGLE_CLIENT_ID === 'your-google-client-id') {
        setErrorMessage('Google OAuth is not configured yet. Please contact administrator.');
        header('Location: ' . url('public/index.php?p=login'));
        exit;
    }

    $provider = getGoogleProvider();

    if (!isset($_GET['code'])) {
        // Redirect to Google OAuth
        $authUrl = $provider->getAuthorizationUrl([
            'scope' => ['email', 'profile']
        ]);
        $_SESSION['oauth2state'] = $provider->getState();
        header('Location: ' . $authUrl);
        exit;
    }

    // Validate state
    if (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
        unset($_SESSION['oauth2state']);
        setErrorMessage('Invalid OAuth state. Please try again.');
        header('Location: ' . url('public/index.php?p=login'));
        exit;
    }

    // Get access token
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    // Get user details
    $googleUser = $provider->getResourceOwner($token);
    $userData = $googleUser->toArray();

    $email = $userData['email'] ?? null;
    $name = $userData['name'] ?? 'Google User';
    $googleId = $userData['sub'] ?? $userData['id'];
    $avatar = $userData['picture'] ?? null;

    if (!$email) {
        setErrorMessage('Could not retrieve email from Google. Please try again.');
        header('Location: ' . url('public/index.php?p=login'));
        exit;
    }

    // Find or create user
    $user = findOrCreateOAuthUser('google', $email, $name, $googleId, $avatar);

    // Login user
    loginOAuthUser($user);

    setSuccessMessage('Successfully logged in with Google!');
    header('Location: ' . url('public/index.php'));
    exit;
} catch (Exception $e) {
    error_log('Google OAuth Error: ' . $e->getMessage());
    setErrorMessage('Failed to login with Google. Please try again.');
    header('Location: ' . url('public/index.php?p=login'));
    exit;
}
