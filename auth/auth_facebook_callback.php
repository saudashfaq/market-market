<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/oauth_helper.php';
require_once __DIR__ . '/../includes/flash_helper.php';

try {
    // Check if credentials are configured
    if (empty(FACEBOOK_APP_ID) || FACEBOOK_APP_ID === 'your-facebook-app-id') {
        setErrorMessage('Facebook OAuth is not configured yet. Please contact administrator.');
        header('Location: ' . url('public/index.php?p=login'));
        exit;
    }

    $provider = getFacebookProvider();

    if (!isset($_GET['code'])) {
        // Redirect to Facebook OAuth
        $authUrl = $provider->getAuthorizationUrl([
            'scope' => ['email', 'public_profile']
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
    $fbUser = $provider->getResourceOwner($token);
    $userData = $fbUser->toArray();

    $email = $userData['email'] ?? null;
    $name = $userData['name'] ?? 'Facebook User';
    $facebookId = $userData['id'];
    $avatar = $userData['picture']['data']['url'] ?? null;

    if (!$email) {
        setErrorMessage('Could not retrieve email from Facebook. Please ensure email permission is granted.');
        header('Location: ' . url('public/index.php?p=login'));
        exit;
    }

    // Find or create user
    $user = findOrCreateOAuthUser('facebook', $email, $name, $facebookId, $avatar);

    // Login user
    loginOAuthUser($user);

    setSuccessMessage('Successfully logged in with Facebook!');
    header('Location: ' . url('public/index.php'));
    exit;
} catch (Exception $e) {
    error_log('Facebook OAuth Error: ' . $e->getMessage());
    setErrorMessage('Failed to login with Facebook. Please try again.');
    header('Location: ' . url('public/index.php?p=login'));
    exit;
}
