<?php
/**
 * Email Verification Helper Functions
 * Handles email change verification process
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send email using PHPMailer
 */
function sendEmail($to, $subject, $htmlBody) {
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate email verification token
 */
function generateEmailVerificationToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Send email verification link
 */
function sendEmailVerificationLink($newEmail, $userName, $token) {
    $verificationLink = BASE . "verify-email-change.php?token=" . urlencode($token);
    
    $subject = "Verify Your New Email Address";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; padding: 15px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔐 Verify Your New Email</h1>
            </div>
            <div class='content'>
                <p>Hi <strong>" . htmlspecialchars($userName) . "</strong>,</p>
                
                <p>You recently requested to change your email address. To complete this change, please verify your new email address by clicking the button below:</p>
                
                <div style='text-align: center;'>
                    <a href='" . $verificationLink . "' class='button'>Verify Email Address</a>
                </div>
                
                <p>Or copy and paste this link into your browser:</p>
                <p style='word-break: break-all; background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 5px;'>" . $verificationLink . "</p>
                
                <div class='warning'>
                    <strong>Important:</strong>
                    <ul>
                        <li>This link will expire in <strong>24 hours</strong></li>
                        <li>If you didn't request this change, please ignore this email</li>
                        <li>Your current email will remain active until verification</li>
                    </ul>
                </div>
                
                <p>If you have any questions, please contact our support team.</p>
                
                <p>Best regards,<br><strong>Marketplace Team</strong></p>
            </div>
            <div class='footer'>
                <p>This is an automated email. Please do not reply.</p>
                <p>&copy; " . date('Y') . " Marketplace. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($newEmail, $subject, $message);
}

/**
 * Send notification to old email about email change attempt
 */
function sendEmailChangeNotification($oldEmail, $userName, $newEmail) {
    $subject = "Email Change Request - Action Required";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .alert { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
            .info-box { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Email Change Request</h1>
            </div>
            <div class='content'>
                <p>Hi <strong>" . htmlspecialchars($userName) . "</strong>,</p>
                
                <div class='alert'>
                    <strong>Security Alert</strong>
                    <p>Someone requested to change the email address associated with your account.</p>
                </div>
                
                <div class='info-box'>
                    <strong>Change Details:</strong>
                    <ul>
                        <li><strong>Current Email:</strong> " . htmlspecialchars($oldEmail) . "</li>
                        <li><strong>New Email:</strong> " . htmlspecialchars($newEmail) . "</li>
                        <li><strong>Time:</strong> " . date('F j, Y, g:i a') . "</li>
                    </ul>
                </div>
                
                <p><strong>What happens next?</strong></p>
                <ul>
                    <li>A verification email has been sent to the new email address</li>
                    <li>Your current email (<strong>" . htmlspecialchars($oldEmail) . "</strong>) will remain active until the new email is verified</li>
                    <li>If the new email is not verified within 24 hours, this request will expire</li>
                </ul>
                
                <div class='alert'>
                    <strong>Didn't request this change?</strong>
                    <p>If you did not request this email change, please:</p>
                    <ol>
                        <li>Change your password immediately</li>
                        <li>Contact our support team</li>
                        <li>Review your account security settings</li>
                    </ol>
                    <p>The email change will NOT be completed without verification from the new email address.</p>
                </div>
                
                <p>Best regards,<br><strong>Marketplace Security Team</strong></p>
            </div>
            <div class='footer'>
                <p>This is an automated security notification.</p>
                <p>&copy; " . date('Y') . " Marketplace. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($oldEmail, $subject, $message);
}

/**
 * Send email change success notification
 */
function sendEmailChangeSuccessNotification($oldEmail, $userName, $newEmail) {
    $subject = "Email Address Successfully Changed";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Email Changed Successfully</h1>
            </div>
            <div class='content'>
                <p>Hi <strong>" . htmlspecialchars($userName) . "</strong>,</p>
                
                <div class='success'>
                    <strong>✓ Your email address has been successfully changed!</strong>
                </div>
                
                <p><strong>Change Details:</strong></p>
                <ul>
                    <li><strong>Old Email:</strong> " . htmlspecialchars($oldEmail) . "</li>
                    <li><strong>New Email:</strong> " . htmlspecialchars($newEmail) . "</li>
                    <li><strong>Changed On:</strong> " . date('F j, Y, g:i a') . "</li>
                </ul>
                
                <p>This is a confirmation that your email address has been updated. You will now use <strong>" . htmlspecialchars($newEmail) . "</strong> to log in to your account.</p>
                
                <p><strong>Security Reminder:</strong></p>
                <ul>
                    <li>This notification was sent to your old email for security purposes</li>
                    <li>If you did not make this change, contact support immediately</li>
                    <li>Consider enabling two-factor authentication for added security</li>
                </ul>
                
                <p>Best regards,<br><strong>Marketplace Team</strong></p>
            </div>
            <div class='footer'>
                <p>This is an automated notification sent to your old email address.</p>
                <p>&copy; " . date('Y') . " Marketplace. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($oldEmail, $subject, $message);
}

/**
 * Verify email change token
 */
function verifyEmailChangeToken($token) {
    $pdo = db();
    
    $stmt = $pdo->prepare("
        SELECT id, email, pending_email, name, email_verification_expires_at 
        FROM users 
        WHERE email_verification_token = ? 
        AND pending_email IS NOT NULL
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid verification token'];
    }
    
    // Check if token expired
    if (strtotime($user['email_verification_expires_at']) < time()) {
        return ['success' => false, 'message' => 'Verification link has expired'];
    }
    
    return ['success' => true, 'user' => $user];
}

/**
 * Complete email change
 */
function completeEmailChange($userId, $oldEmail, $newEmail, $userName) {
    $pdo = db();
    
    try {
        // Update email and clear verification fields
        $stmt = $pdo->prepare("
            UPDATE users 
            SET email = pending_email,
                pending_email = NULL,
                email_verification_token = NULL,
                email_verification_expires_at = NULL
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        
        // Send success notification to old email
        sendEmailChangeSuccessNotification($oldEmail, $userName, $newEmail);
        
        return ['success' => true, 'message' => 'Email changed successfully'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Failed to update email: ' . $e->getMessage()];
    }
}
