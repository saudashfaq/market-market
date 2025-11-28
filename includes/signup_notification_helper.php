<?php
/**
 * Signup Notification Helper
 * Enhanced notification system for user signup and email verification
 */

require_once __DIR__ . '/flash_helper.php';
require_once __DIR__ . '/popup_helper.php';

class SignupNotificationHelper {
    
    /**
     * Set enhanced notifications for successful signup with email verification
     * @param string $email User's email address
     * @param string $name User's name
     */
    public static function setVerificationNotifications($email, $name) {
        // Set enhanced popup notification
        setSuccessPopup(
            "Welcome {$name}! We've sent a verification email to {$email}. Please check your inbox and spam folder, then click the verification link to activate your account.",
            [
                'title' => 'Registration Successful - Check Your Email',
                'autoClose' => false,
                'showResendOption' => true,
                'userEmail' => $email,
                'userName' => $name
            ]
        );
        
        // Set persistent flash message as backup
        setSuccessMessage(
            "Account created successfully! Please check your email ({$email}) for a verification link. Don't forget to check your spam folder if you don't see it in your inbox."
        );
    }
    
    /**
     * Set welcome notifications for signup without email verification
     * @param string $name User's name
     */
    public static function setWelcomeNotifications($name) {
        setSuccessPopup(
            "Welcome {$name}! Your account has been created and you're now logged in.",
            [
                'title' => 'Welcome to MarketPlace',
                'autoClose' => true,
                'autoCloseTime' => 4000
            ]
        );
    }
    
    /**
     * Set error notifications when email sending fails
     * @param string $email User's email address
     * @param string $name User's name
     */
    public static function setEmailFailureNotifications($email, $name = '') {
        setErrorPopup(
            "Your account was created, but we couldn't send the verification email to {$email}. Please contact support or try resending the verification email.",
            [
                'title' => 'Email Sending Failed',
                'autoClose' => false,
                'showResendOption' => true,
                'userEmail' => $email,
                'userName' => $name
            ]
        );
        
        // Set persistent flash message as backup
        setErrorMessage(
            "Account created but verification email failed to send to {$email}. Please contact support or try resending the verification email."
        );
    }
    
    /**
     * Set notifications for successful email resend
     * @param string $email User's email address
     */
    public static function setResendSuccessNotifications($email) {
        setSuccessPopup(
            "Verification email sent successfully to {$email}! Please check your inbox and spam folder.",
            [
                'title' => 'Verification Email Sent',
                'autoClose' => true,
                'autoCloseTime' => 5000
            ]
        );
        
        setSuccessMessage(
            "Verification email sent successfully! Please check your email ({$email}) and spam folder."
        );
    }
    
    /**
     * Set notifications for resend failures
     * @param string $email User's email address
     */
    public static function setResendFailureNotifications($email) {
        setErrorPopup(
            "Failed to send verification email to {$email}. Please try again later or contact support.",
            [
                'title' => 'Email Sending Failed',
                'autoClose' => false
            ]
        );
        
        setErrorMessage(
            "Failed to send verification email. Please try again later or contact support."
        );
    }
    
    /**
     * Set rate limiting notifications
     * @param int $waitMinutes Minutes to wait before next attempt
     */
    public static function setRateLimitNotifications($waitMinutes = 5) {
        setWarningPopup(
            "Too many verification email requests. Please wait {$waitMinutes} minutes before requesting another verification email.",
            [
                'title' => 'Rate Limit Exceeded',
                'autoClose' => false
            ]
        );
        
        setWarningMessage(
            "Please wait {$waitMinutes} minutes before requesting another verification email."
        );
    }
}

// Convenience functions for backward compatibility
function setVerificationNotifications($email, $name) {
    SignupNotificationHelper::setVerificationNotifications($email, $name);
}

function setWelcomeNotifications($name) {
    SignupNotificationHelper::setWelcomeNotifications($name);
}

function setEmailFailureNotifications($email, $name = '') {
    SignupNotificationHelper::setEmailFailureNotifications($email, $name);
}
?>