<?php
/**
 * Email Helper Functions
 * Procedural functions for sending emails using PHPMailer
 */

// Check if vendor autoload exists before requiring
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    error_log("PHPMailer vendor/autoload.php not found. Email functionality will be disabled.");
    // Email system not configured - functions will return false
    // Don't try to load PHPMailer classes
    return;
}

require_once $vendorAutoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send password reset email to user
 * 
 * @param string $userEmail User's email address
 * @param string $userName User's name
 * @param string $resetToken Reset token
 * @return bool True on success, false on failure
 */
function sendPasswordResetEmail($userEmail, $userName, $resetToken) {
    try {
        // Validate email
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid email address: $userEmail");
            return false;
        }

        // Create reset link
        $resetLink = url("reset-password.php?token=" . urlencode($resetToken));
        
        // Prepare template data
        $templateData = [
            'user_name' => $userName,
            'reset_link' => $resetLink,
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/'),
            'expiry_time' => '1 hour'
        ];

        // Get email template
        $htmlBody = getEmailTemplate('password_reset', $templateData);
        
        // Initialize PHPMailer
        $mail = new PHPMailer(true);
        
        // Enable verbose debug output (comment out in production)
        // $mail->SMTPDebug = 2;
        // $mail->Debugoutput = function($str, $level) {
        //     error_log("PHPMailer: $str");
        // };
        
        // SMTP Configuration for Gmail
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        
        // Additional Gmail settings
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Set charset
        $mail->CharSet = 'UTF-8';
        
        // Email settings
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($userEmail, $userName);
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        // Send email
        $result = $mail->send();
        
        if ($result) {
            error_log("Password reset email sent successfully to: $userEmail");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Load and process email template
 * 
 * @param string $templateName Template name (without .html extension)
 * @param array $data Associative array of placeholder => value pairs
 * @return string Processed HTML content
 */
function getEmailTemplate($templateName, $data = []) {
    $templatePath = __DIR__ . '/../templates/emails/' . $templateName . '.html';
    
    // Check if template exists
    if (!file_exists($templatePath)) {
        error_log("Email template not found: $templatePath");
        // Return basic fallback template
        return getFallbackTemplate($data);
    }
    
    // Load template
    $template = file_get_contents($templatePath);
    
    // Replace placeholders
    foreach ($data as $key => $value) {
        $placeholder = '{{' . $key . '}}';
        $template = str_replace($placeholder, htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), $template);
    }
    
    return $template;
}

/**
 * Fallback template if main template is not found
 * 
 * @param array $data Template data
 * @return string Basic HTML email
 */
function getFallbackTemplate($data) {
    $userName = $data['user_name'] ?? 'User';
    $resetLink = $data['reset_link'] ?? '#';
    $siteName = $data['site_name'] ?? 'Marketplace';
    
    return "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2>Password Reset Request</h2>
            <p>Hello $userName,</p>
            <p>We received a request to reset your password. Click the button below to reset it:</p>
            <p style='text-align: center; margin: 30px 0;'>
                <a href='$resetLink' style='background-color: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a>
            </p>
            <p>This link will expire in 1 hour.</p>
            <p>If you didn't request this, please ignore this email.</p>
            <hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>
            <p style='font-size: 12px; color: #666;'>© " . date('Y') . " $siteName. All rights reserved.</p>
        </div>
    </body>
    </html>
    ";
}


/**
 * Send listing approved email to seller
 * 
 * @param string $sellerEmail Seller's email address
 * @param string $sellerName Seller's name
 * @param array $listingData Listing information
 * @return bool True on success, false on failure
 */
function sendListingApprovedEmail($sellerEmail, $sellerName, $listingData) {
    try {
        // Validate email
        if (!filter_var($sellerEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid seller email address: $sellerEmail");
            return false;
        }

        // Prepare template data
        $templateData = [
            'seller_name' => $sellerName,
            'listing_id' => $listingData['id'] ?? '',
            'listing_title' => $listingData['title'] ?? 'Your Listing',
            'listing_category' => $listingData['category'] ?? 'N/A',
            'listing_price' => number_format($listingData['price'] ?? 0, 2),
            'listing_url' => $listingData['listing_url'] ?? url('index.php?p=listingDetail&id=' . ($listingData['id'] ?? '')),
            'dashboard_url' => url('index.php?p=my_listing'),
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        // Get email template
        $htmlBody = getEmailTemplate('listing_approved', $templateData);
        
        // Initialize PHPMailer
        $mail = new PHPMailer(true);
        
        // SMTP Configuration for Gmail
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        
        // Additional Gmail settings
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Set charset
        $mail->CharSet = 'UTF-8';
        
        // Email settings
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($sellerEmail, $sellerName);
        $mail->isHTML(true);
        $mail->Subject = 'Your Listing Has Been Approved - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        // Send email
        $result = $mail->send();
        
        if ($result) {
            error_log("Listing approved email sent successfully to: $sellerEmail for listing ID: " . ($listingData['id'] ?? 'N/A'));
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Listing approved email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send listing rejected email to seller
 * 
 * @param string $sellerEmail Seller's email address
 * @param string $sellerName Seller's name
 * @param array $listingData Listing information
 * @param string $reason Rejection reason
 * @return bool True on success, false on failure
 */
function sendListingRejectedEmail($sellerEmail, $sellerName, $listingData, $reason = '') {
    try {
        // Validate email
        if (!filter_var($sellerEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid seller email address: $sellerEmail");
            return false;
        }

        // Prepare template data
        $templateData = [
            'seller_name' => $sellerName,
            'listing_id' => $listingData['id'] ?? '',
            'listing_title' => $listingData['title'] ?? 'Your Listing',
            'rejection_reason' => $reason ?: 'Your listing does not meet our community guidelines. Please review and update your listing accordingly.',
            'edit_listing_url' => url('index.php?p=updateListing&id=' . ($listingData['id'] ?? '')),
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        // Get email template
        $htmlBody = getEmailTemplate('listing_rejected', $templateData);
        
        // Initialize PHPMailer
        $mail = new PHPMailer(true);
        
        // SMTP Configuration for Gmail
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        
        // Additional Gmail settings
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Set charset
        $mail->CharSet = 'UTF-8';
        
        // Email settings
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($sellerEmail, $sellerName);
        $mail->isHTML(true);
        $mail->Subject = 'Action Required: Update Your Listing - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        // Send email
        $result = $mail->send();
        
        if ($result) {
            error_log("Listing rejected email sent successfully to: $sellerEmail for listing ID: " . ($listingData['id'] ?? 'N/A'));
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Listing rejected email sending failed: " . $e->getMessage());
        return false;
    }
}


/**
 * Send welcome email to new user
 * 
 * @param string $userEmail User's email address
 * @param string $userName User's name
 * @return bool True on success, false on failure
 */
function sendWelcomeEmail($userEmail, $userName) {
    try {
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid user email address: $userEmail");
            return false;
        }

        $templateData = [
            'user_name' => $userName,
            'dashboard_url' => url('index.php?p=dashboard'),
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        $htmlBody = getEmailTemplate('welcome', $templateData);
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($userEmail, $userName);
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to ' . MAIL_FROM_NAME . '!';
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        $result = $mail->send();
        if ($result) {
            error_log("Welcome email sent successfully to: $userEmail");
        }
        return $result;
    } catch (Exception $e) {
        error_log("Welcome email sending failed: " . $e->getMessage());
        return false;
    }
}


/**
 * Send offer received email to seller
 */
function sendOfferReceivedEmail($sellerEmail, $sellerName, $offerData, $listingData) {
    try {
        if (!filter_var($sellerEmail, FILTER_VALIDATE_EMAIL)) return false;

        $templateData = [
            'seller_name' => $sellerName,
            'buyer_name' => $offerData['buyer_name'] ?? 'A buyer',
            'listing_title' => $listingData['title'] ?? 'Your listing',
            'listing_price' => number_format($listingData['price'] ?? 0, 2),
            'offer_amount' => number_format($offerData['amount'] ?? 0, 2),
            'offer_message' => $offerData['message'] ?? 'No message provided',
            'offer_url' => url('index.php?p=offers'),
            'dashboard_url' => url('index.php?p=dashboard'),
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        $htmlBody = getEmailTemplate('offer_received', $templateData);
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($sellerEmail, $sellerName);
        $mail->isHTML(true);
        $mail->Subject = 'New Offer on Your Listing - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Offer received email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send offer accepted email to buyer
 */
function sendOfferAcceptedEmail($buyerEmail, $buyerName, $offerData, $listingData) {
    try {
        if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) return false;

        $templateData = [
            'buyer_name' => $buyerName,
            'listing_title' => $listingData['title'] ?? 'Listing',
            'offer_amount' => number_format($offerData['amount'] ?? 0, 2),
            'payment_url' => url('index.php?p=payment&offer_id=' . ($offerData['id'] ?? '')),
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        $htmlBody = "
        <html><body style='font-family: Arial; padding: 20px;'>
            <h2 style='color: #10b981;'>Offer Accepted!</h2>
            <p>Hi {$buyerName},</p>
            <p>Great news! Your offer of <strong>\${$templateData['offer_amount']}</strong> for <strong>{$templateData['listing_title']}</strong> has been accepted!</p>
            <p style='margin: 30px 0;'><a href='{$templateData['payment_url']}' style='background: #10b981; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Complete Payment</a></p>
            <p>Complete your payment to finalize the purchase.</p>
            <hr style='margin: 30px 0;'>
            <p style='color: #666; font-size: 12px;'>© 2024 {$templateData['site_name']}</p>
        </body></html>";
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($buyerEmail, $buyerName);
        $mail->isHTML(true);
        $mail->Subject = 'Your Offer Was Accepted! - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Offer accepted email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send offer rejected email to buyer
 */
function sendOfferRejectedEmail($buyerEmail, $buyerName, $offerData, $listingData, $reason = '') {
    try {
        if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) return false;

        $reasonText = $reason ?: 'The seller has decided to decline your offer at this time.';
        $templateData = [
            'buyer_name' => $buyerName,
            'listing_title' => $listingData['title'] ?? 'Listing',
            'offer_amount' => number_format($offerData['amount'] ?? 0, 2),
            'reason' => $reasonText,
            'listing_url' => url('index.php?p=listingDetail&id=' . ($listingData['id'] ?? '')),
            'site_name' => MAIL_FROM_NAME
        ];

        $htmlBody = "
        <html><body style='font-family: Arial; padding: 20px;'>
            <h2 style='color: #f59e0b;'>Offer Update</h2>
            <p>Hi {$buyerName},</p>
            <p>Your offer of <strong>\${$templateData['offer_amount']}</strong> for <strong>{$templateData['listing_title']}</strong> was not accepted.</p>
            <p style='background: #fef3c7; padding: 15px; border-left: 4px solid #f59e0b;'>{$reasonText}</p>
            <p>Don't give up! You can make another offer or browse other listings.</p>
            <p style='margin: 30px 0;'><a href='{$templateData['listing_url']}' style='background: #3b82f6; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Listing</a></p>
            <hr style='margin: 30px 0;'>
            <p style='color: #666; font-size: 12px;'>© 2024 {$templateData['site_name']}</p>
        </body></html>";
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($buyerEmail, $buyerName);
        $mail->isHTML(true);
        $mail->Subject = 'Offer Update - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Offer rejected email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send payment confirmation email
 */
function sendPaymentConfirmationEmail($userEmail, $userName, $paymentData, $listingData, $userType = 'buyer') {
    try {
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) return false;

        $templateData = [
            'user_name' => $userName,
            'listing_title' => $listingData['title'] ?? 'Listing',
            'amount' => number_format($paymentData['amount'] ?? 0, 2),
            'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
            'order_url' => url('index.php?p=my_order'),
            'site_name' => MAIL_FROM_NAME
        ];

        $message = $userType === 'buyer' 
            ? "Your payment has been processed successfully!" 
            : "You've received a payment for your listing!";

        $htmlBody = "
        <html><body style='font-family: Arial; padding: 20px;'>
            <h2 style='color: #10b981;'>Payment Confirmed</h2>
            <p>Hi {$userName},</p>
            <p>{$message}</p>
            <div style='background: #f7fafc; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <p><strong>Listing:</strong> {$templateData['listing_title']}</p>
                <p><strong>Amount:</strong> \${$templateData['amount']}</p>
                <p><strong>Transaction ID:</strong> {$templateData['transaction_id']}</p>
            </div>
            <p style='margin: 30px 0;'><a href='{$templateData['order_url']}' style='background: #10b981; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Order Details</a></p>
            <hr style='margin: 30px 0;'>
            <p style='color: #666; font-size: 12px;'>© 2024 {$templateData['site_name']}</p>
        </body></html>";
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($userEmail, $userName);
        $mail->isHTML(true);
        $mail->Subject = 'Payment Confirmed - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Payment confirmation email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send new message notification email
 */
function sendNewMessageEmail($recipientEmail, $recipientName, $senderName, $messagePreview) {
    try {
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) return false;

        $templateData = [
            'recipient_name' => $recipientName,
            'sender_name' => $senderName,
            'message_preview' => substr($messagePreview, 0, 100) . (strlen($messagePreview) > 100 ? '...' : ''),
            'messages_url' => url('index.php?p=message'),
            'site_name' => MAIL_FROM_NAME
        ];

        $htmlBody = "
        <html><body style='font-family: Arial; padding: 20px;'>
            <h2 style='color: #3b82f6;'>💬 New Message</h2>
            <p>Hi {$recipientName},</p>
            <p><strong>{$senderName}</strong> sent you a message:</p>
            <div style='background: #eff6ff; padding: 15px; border-left: 4px solid #3b82f6; margin: 20px 0;'>
                <p style='margin: 0; color: #1e3a8a;'>{$templateData['message_preview']}</p>
            </div>
            <p style='margin: 30px 0;'><a href='{$templateData['messages_url']}' style='background: #3b82f6; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Message</a></p>
            <hr style='margin: 30px 0;'>
            <p style='color: #666; font-size: 12px;'>© 2024 {$templateData['site_name']}</p>
        </body></html>";
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->isHTML(true);
        $mail->Subject = '💬 New Message from ' . $senderName . ' - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("New message email failed: " . $e->getMessage());
        return false;
    }
}


/**
 * Send email verification email
 */
function sendEmailVerificationEmail($userEmail, $userName, $verificationToken) {
    try {
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) return false;

        $verificationLink = url("verify-email.php?token=" . urlencode($verificationToken));
        $templateData = [
            'user_name' => $userName,
            'verification_link' => $verificationLink,
            'expiry_time' => '24 hours',
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        $htmlBody = getEmailTemplate('email_verification', $templateData);
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($userEmail, $userName);
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email verification email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send password changed confirmation email
 */
function sendPasswordChangedEmail($userEmail, $userName) {
    try {
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) return false;

        $templateData = [
            'user_name' => $userName,
            'change_date' => date('F j, Y \a\t g:i A'),
            'support_email' => MAIL_FROM_ADDRESS,
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        $htmlBody = getEmailTemplate('password_changed', $templateData);
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($userEmail, $userName);
        $mail->isHTML(true);
        $mail->Subject = '🔒 Password Changed - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Password changed email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send listing sold email to seller
 */
function sendListingSoldEmail($sellerEmail, $sellerName, $listingData, $buyerName, $soldPrice) {
    try {
        if (!filter_var($sellerEmail, FILTER_VALIDATE_EMAIL)) return false;

        $templateData = [
            'seller_name' => $sellerName,
            'listing_title' => $listingData['title'] ?? 'Your listing',
            'sold_price' => number_format($soldPrice, 2),
            'buyer_name' => $buyerName,
            'sale_date' => date('F j, Y'),
            'order_url' => url('index.php?p=my_order'),
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        $htmlBody = getEmailTemplate('listing_sold', $templateData);
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($sellerEmail, $sellerName);
        $mail->isHTML(true);
        $mail->Subject = 'Your Listing Sold! - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Listing sold email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send listing expiring soon email
 */
function sendListingExpiringEmail($sellerEmail, $sellerName, $listingData, $daysRemaining) {
    try {
        if (!filter_var($sellerEmail, FILTER_VALIDATE_EMAIL)) return false;

        $expiryDate = date('F j, Y', strtotime("+{$daysRemaining} days"));
        $templateData = [
            'seller_name' => $sellerName,
            'listing_title' => $listingData['title'] ?? 'Your listing',
            'days_remaining' => $daysRemaining,
            'expiry_date' => $expiryDate,
            'renew_url' => url('index.php?p=updateListing&id=' . ($listingData['id'] ?? '')),
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        $htmlBody = getEmailTemplate('listing_expiring', $templateData);
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($sellerEmail, $sellerName);
        $mail->isHTML(true);
        $mail->Subject = '⏰ Listing Expiring Soon - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Listing expiring email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send counter offer email to buyer
 */
function sendCounterOfferEmail($buyerEmail, $buyerName, $offerData, $listingData, $sellerName) {
    try {
        if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) return false;

        $templateData = [
            'buyer_name' => $buyerName,
            'seller_name' => $sellerName,
            'listing_title' => $listingData['title'] ?? 'Listing',
            'original_offer' => number_format($offerData['original_amount'] ?? 0, 2),
            'counter_amount' => number_format($offerData['counter_amount'] ?? 0, 2),
            'counter_message' => $offerData['counter_message'] ?? 'No message provided',
            'offer_url' => url('index.php?p=offers'),
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        $htmlBody = getEmailTemplate('offer_counter', $templateData);
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($buyerEmail, $buyerName);
        $mail->isHTML(true);
        $mail->Subject = '🔄 Counter Offer Received - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Counter offer email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send payment failed email
 */
function sendPaymentFailedEmail($userEmail, $userName, $paymentData, $listingData) {
    try {
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) return false;

        $templateData = [
            'user_name' => $userName,
            'listing_title' => $listingData['title'] ?? 'Listing',
            'amount' => number_format($paymentData['amount'] ?? 0, 2),
            'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
            'failure_reason' => $paymentData['failure_reason'] ?? 'Payment could not be processed. Please check your payment method and try again.',
            'retry_payment_url' => url('index.php?p=payment&retry=1&offer_id=' . ($paymentData['offer_id'] ?? '')),
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        $htmlBody = getEmailTemplate('payment_failed', $templateData);
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($userEmail, $userName);
        $mail->isHTML(true);
        $mail->Subject = 'Payment Failed - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Payment failed email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send order confirmation email
 */
function sendOrderConfirmationEmail($userEmail, $userName, $orderData, $listingData, $userType = 'buyer') {
    try {
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) return false;

        $templateData = [
            'user_name' => $userName,
            'order_id' => $orderData['id'] ?? 'N/A',
            'listing_title' => $listingData['title'] ?? 'Listing',
            'amount' => number_format($orderData['amount'] ?? 0, 2),
            'order_date' => date('F j, Y'),
            'user_type' => $userType === 'buyer' ? 'Seller' : 'Buyer',
            'other_party_name' => $orderData['other_party_name'] ?? 'N/A',
            'order_url' => url('index.php?p=my_order'),
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        $htmlBody = getEmailTemplate('order_confirmation', $templateData);
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($userEmail, $userName);
        $mail->isHTML(true);
        $mail->Subject = 'Order Confirmed - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Order confirmation email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send order shipped email
 */
function sendOrderShippedEmail($buyerEmail, $buyerName, $orderData, $listingData) {
    try {
        if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) return false;

        $templateData = [
            'user_name' => $buyerName,
            'order_id' => $orderData['id'] ?? 'N/A',
            'listing_title' => $listingData['title'] ?? 'Listing',
            'tracking_number' => $orderData['tracking_number'] ?? 'N/A',
            'shipping_carrier' => $orderData['shipping_carrier'] ?? 'N/A',
            'estimated_delivery' => $orderData['estimated_delivery'] ?? 'TBD',
            'tracking_url' => $orderData['tracking_url'] ?? url('index.php?p=my_order'),
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        $htmlBody = getEmailTemplate('order_shipped', $templateData);
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($buyerEmail, $buyerName);
        $mail->isHTML(true);
        $mail->Subject = '📦 Order Shipped - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Order shipped email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send order delivered email
 */
function sendOrderDeliveredEmail($buyerEmail, $buyerName, $orderData, $listingData) {
    try {
        if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) return false;

        $templateData = [
            'user_name' => $buyerName,
            'order_id' => $orderData['id'] ?? 'N/A',
            'listing_title' => $listingData['title'] ?? 'Listing',
            'delivery_date' => date('F j, Y'),
            'review_url' => url('index.php?p=review&order_id=' . ($orderData['id'] ?? '')),
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        $htmlBody = getEmailTemplate('order_delivered', $templateData);
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($buyerEmail, $buyerName);
        $mail->isHTML(true);
        $mail->Subject = 'Order Delivered - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Order delivered email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send order cancelled email
 */
function sendOrderCancelledEmail($userEmail, $userName, $orderData, $listingData) {
    try {
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) return false;

        $refundMessage = isset($orderData['refund_amount']) && $orderData['refund_amount'] > 0 
            ? "A refund of $" . number_format($orderData['refund_amount'], 2) . " will be processed within 5-7 business days."
            : "No payment was processed for this order.";

        $templateData = [
            'user_name' => $userName,
            'order_id' => $orderData['id'] ?? 'N/A',
            'listing_title' => $listingData['title'] ?? 'Listing',
            'amount' => number_format($orderData['amount'] ?? 0, 2),
            'cancellation_reason' => $orderData['cancellation_reason'] ?? 'Order was cancelled',
            'refund_message' => $refundMessage,
            'browse_url' => url('index.php'),
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        $htmlBody = getEmailTemplate('order_cancelled', $templateData);
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($userEmail, $userName);
        $mail->isHTML(true);
        $mail->Subject = 'Order Cancelled - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Order cancelled email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send new listing notification to admin
 */
function sendAdminNewListingEmail($adminEmail, $listingData, $sellerName) {
    try {
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) return false;

        $templateData = [
            'listing_title' => $listingData['title'] ?? 'New Listing',
            'seller_name' => $sellerName,
            'listing_category' => $listingData['category'] ?? 'N/A',
            'listing_price' => number_format($listingData['price'] ?? 0, 2),
            'submission_date' => date('F j, Y \a\t g:i A'),
            'review_url' => url('index.php?p=dashboard&page=listingManagement'),
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        $htmlBody = getEmailTemplate('admin_new_listing', $templateData);
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($adminEmail);
        $mail->isHTML(true);
        $mail->Subject = 'New Listing Submitted - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Admin new listing email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send new user registration notification to admin
 */
function sendAdminNewUserEmail($adminEmail, $userData) {
    try {
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) return false;

        $templateData = [
            'user_name' => $userData['name'] ?? 'New User',
            'user_email' => $userData['email'] ?? 'N/A',
            'registration_date' => date('F j, Y \a\t g:i A'),
            'user_profile_url' => url('admin/users.php?id=' . ($userData['id'] ?? '')),
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        $htmlBody = getEmailTemplate('admin_new_user', $templateData);
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($adminEmail);
        $mail->isHTML(true);
        $mail->Subject = '👤 New User Registered - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Admin new user email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send dispute notification to admin
 */
function sendAdminDisputeEmail($adminEmail, $disputeData) {
    try {
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) return false;

        $templateData = [
            'order_id' => $disputeData['order_id'] ?? 'N/A',
            'raised_by' => $disputeData['raised_by'] ?? 'User',
            'against_user' => $disputeData['against_user'] ?? 'User',
            'dispute_reason' => $disputeData['reason'] ?? 'No reason provided',
            'dispute_date' => date('F j, Y \a\t g:i A'),
            'dispute_url' => url('admin/disputes.php?id=' . ($disputeData['id'] ?? '')),
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        $htmlBody = getEmailTemplate('admin_dispute', $templateData);
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($adminEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Dispute Raised - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Admin dispute email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send payment issue notification to admin
 */
function sendAdminPaymentIssueEmail($adminEmail, $paymentData) {
    try {
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) return false;

        $templateData = [
            'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
            'user_name' => $paymentData['user_name'] ?? 'User',
            'amount' => number_format($paymentData['amount'] ?? 0, 2),
            'issue_description' => $paymentData['issue_description'] ?? 'Payment processing issue',
            'issue_date' => date('F j, Y \a\t g:i A'),
            'payment_url' => url('admin/payments.php?id=' . ($paymentData['id'] ?? '')),
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/')
        ];

        $htmlBody = getEmailTemplate('admin_payment_issue', $templateData);
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($adminEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Payment Issue - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Admin payment issue email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification to all superadmins
 * Universal function for all superadmin notifications
 * 
 * @param string $subject Email subject
 * @param string $title Email title/heading
 * @param string $message Main message
 * @param array $details Key-value pairs of details to display
 * @param string|null $actionUrl Optional action button URL
 * @return bool True on success, false on failure
 */
function sendSuperAdminNotification($subject, $title, $message, $details = [], $actionUrl = null) {
    try {
        $pdo = db();
        
        // Get all superadmin emails
        $stmt = $pdo->query("
            SELECT email, name 
            FROM users 
            WHERE role IN ('admin', 'super_admin', 'superAdmin', 'superadmin')
        ");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($admins)) {
            error_log("⚠️ No superadmin emails found");
            return false;
        }
        
        // Build details HTML
        $detailsHtml = '';
        foreach ($details as $label => $value) {
            $detailsHtml .= "<tr>
                <td style='padding: 8px 12px; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #4b5563;'>{$label}:</td>
                <td style='padding: 8px 12px; border-bottom: 1px solid #e5e7eb; color: #1f2937;'>{$value}</td>
            </tr>";
        }
        
        // Build action button
        $actionButton = '';
        if ($actionUrl) {
            $actionButton = "
                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin: 30px 0;'>
                    <tr>
                        <td align='center'>
                            <a href='{$actionUrl}' target='_blank' style='display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4);'>
                                Take Action →
                            </a>
                        </td>
                    </tr>
                </table>
            ";
        }
        
        // Build HTML email
        $htmlBody = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$subject}</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; background-color: #f3f4f6;'>
            <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='background-color: #f3f4f6; padding: 40px 20px;'>
                <tr>
                    <td align='center'>
                        <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='max-width: 600px; background-color: #ffffff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08); overflow: hidden;'>
                            <!-- Header -->
                            <tr>
                                <td style='padding: 40px 40px 30px; text-align: center; background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);'>
                                    <h1 style='margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;'>{$title}</h1>
                                    <p style='margin: 12px 0 0; color: rgba(255, 255, 255, 0.9); font-size: 14px;'>SuperAdmin Notification</p>
                                </td>
                            </tr>
                            
                            <!-- Body -->
                            <tr>
                                <td style='padding: 40px;'>
                                    <p style='margin: 0 0 24px; color: #374151; font-size: 16px; line-height: 1.6;'>{$message}</p>
                                    
                                    <!-- Details Table -->
                                    <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin: 24px 0; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;'>
                                        {$detailsHtml}
                                    </table>
                                    
                                    {$actionButton}
                                    
                                    <div style='margin-top: 30px; padding: 16px; background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px;'>
                                        <p style='margin: 0; color: #92400e; font-size: 14px; line-height: 1.6;'>
                                            <strong>⚠️ Action Required:</strong> Please review and take appropriate action as soon as possible.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style='padding: 30px 40px; background-color: #f9fafb; border-top: 1px solid #e5e7eb;'>
                                    <p style='margin: 0; color: #6b7280; font-size: 13px; text-align: center; line-height: 1.6;'>
                                        This is an automated notification from your marketplace system.<br>
                                        © " . date('Y') . " " . MAIL_FROM_NAME . ". All rights reserved.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
        
        // Initialize PHPMailer
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        
        // Add all admins as recipients
        foreach ($admins as $admin) {
            $mail->addAddress($admin['email'], $admin['name']);
        }
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        $result = $mail->send();
        
        if ($result) {
            error_log("✅ SuperAdmin notification sent: {$subject} to " . count($admins) . " admin(s)");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("❌ SuperAdmin notification failed: " . $e->getMessage());
        return false;
    }
}


/**
 * Send ticket created notification to SuperAdmin
 */
function sendTicketCreatedEmail($adminEmail, $ticketData, $userData) {
    try {
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) return false;

        $ticketUrl = url('index.php?p=dashboard&page=admin_ticket_view&id=' . $ticketData['id']);
        
        $htmlBody = "
        <html><body style='font-family: Arial; padding: 20px;'>
            <h2 style='color: #170835;'>🎫 New Support Ticket</h2>
            <p>Hi Admin,</p>
            <p>A new support ticket has been created by <strong>{$userData['name']}</strong>.</p>
            <div style='background: #f7fafc; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #170835;'>
                <p><strong>Ticket ID:</strong> #{$ticketData['id']}</p>
                <p><strong>Subject:</strong> {$ticketData['subject']}</p>
                <p><strong>User:</strong> {$userData['name']} ({$userData['email']})</p>
                <p><strong>Message:</strong></p>
                <p style='background: white; padding: 10px; border-radius: 4px;'>{$ticketData['message']}</p>
            </div>
            <p style='margin: 30px 0;'><a href='{$ticketUrl}' style='background: #170835; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Ticket</a></p>
            <hr style='margin: 30px 0;'>
            <p style='color: #666; font-size: 12px;'>© 2024 " . MAIL_FROM_NAME . "</p>
        </body></html>";
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($adminEmail, 'Admin');
        $mail->isHTML(true);
        $mail->Subject = '🎫 New Support Ticket #' . $ticketData['id'] . ' - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Ticket created email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send ticket reply notification to user
 */
function sendTicketReplyToUser($userEmail, $userName, $ticketData, $replyData) {
    try {
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) return false;

        $ticketUrl = url('index.php?p=dashboard&page=view_ticket&id=' . $ticketData['id']);
        
        $htmlBody = "
        <html><body style='font-family: Arial; padding: 20px;'>
            <h2 style='color: #170835;'>💬 New Reply on Your Ticket</h2>
            <p>Hi {$userName},</p>
            <p>The support team has replied to your ticket <strong>#{$ticketData['id']}</strong>.</p>
            <div style='background: #f7fafc; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #170835;'>
                <p><strong>Ticket Subject:</strong> {$ticketData['subject']}</p>
                <p><strong>Reply:</strong></p>
                <p style='background: white; padding: 10px; border-radius: 4px;'>{$replyData['message']}</p>
            </div>
            <p style='margin: 30px 0;'><a href='{$ticketUrl}' style='background: #170835; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Ticket</a></p>
            <hr style='margin: 30px 0;'>
            <p style='color: #666; font-size: 12px;'>© 2024 " . MAIL_FROM_NAME . "</p>
        </body></html>";
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($userEmail, $userName);
        $mail->isHTML(true);
        $mail->Subject = '💬 New Reply on Ticket #' . $ticketData['id'] . ' - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Ticket reply to user email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send ticket reply notification to admin
 */
function sendTicketReplyToAdmin($adminEmail, $ticketData, $userData, $replyData) {
    try {
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) return false;

        $ticketUrl = url('index.php?p=dashboard&page=admin_ticket_view&id=' . $ticketData['id']);
        
        $htmlBody = "
        <html><body style='font-family: Arial; padding: 20px;'>
            <h2 style='color: #170835;'>💬 New Reply on Ticket</h2>
            <p>Hi Admin,</p>
            <p><strong>{$userData['name']}</strong> has replied to ticket <strong>#{$ticketData['id']}</strong>.</p>
            <div style='background: #f7fafc; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #170835;'>
                <p><strong>Ticket Subject:</strong> {$ticketData['subject']}</p>
                <p><strong>User:</strong> {$userData['name']} ({$userData['email']})</p>
                <p><strong>Reply:</strong></p>
                <p style='background: white; padding: 10px; border-radius: 4px;'>{$replyData['message']}</p>
            </div>
            <p style='margin: 30px 0;'><a href='{$ticketUrl}' style='background: #170835; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Ticket</a></p>
            <hr style='margin: 30px 0;'>
            <p style='color: #666; font-size: 12px;'>© 2024 " . MAIL_FROM_NAME . "</p>
        </body></html>";
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($adminEmail, 'Admin');
        $mail->isHTML(true);
        $mail->Subject = '💬 New Reply on Ticket #' . $ticketData['id'] . ' - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Ticket reply to admin email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send ticket closed notification to user
 */
function sendTicketClosedEmail($userEmail, $userName, $ticketData) {
    try {
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) return false;

        $ticketUrl = url('index.php?p=dashboard&page=view_ticket&id=' . $ticketData['id']);
        
        $htmlBody = "
        <html><body style='font-family: Arial; padding: 20px;'>
            <h2 style='color: #10b981;'>✅ Ticket Closed</h2>
            <p>Hi {$userName},</p>
            <p>Your support ticket <strong>#{$ticketData['id']}</strong> has been closed.</p>
            <div style='background: #f0fdf4; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #10b981;'>
                <p><strong>Ticket Subject:</strong> {$ticketData['subject']}</p>
                <p><strong>Status:</strong> Closed</p>
            </div>
            <p>If you need further assistance, feel free to create a new ticket.</p>
            <p style='margin: 30px 0;'><a href='{$ticketUrl}' style='background: #170835; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Ticket</a></p>
            <hr style='margin: 30px 0;'>
            <p style='color: #666; font-size: 12px;'>© 2024 " . MAIL_FROM_NAME . "</p>
        </body></html>";
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($userEmail, $userName);
        $mail->isHTML(true);
        $mail->Subject = '✅ Ticket #' . $ticketData['id'] . ' Closed - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Ticket closed email failed: " . $e->getMessage());
        return false;
    }
}


/**
 * Send new user credentials email
 * Sends welcome email to newly created users with their login credentials
 * 
 * @param string $userEmail User's email address
 * @param string $userName User's full name
 * @param string $defaultPassword The default password assigned (123456)
 * @param string $userRole User's role (user, admin, superadmin)
 * @return bool True on success, false on failure
 */
function sendNewUserCredentialsEmail($userEmail, $userName, $defaultPassword, $userRole) {
    try {
        // Validate email address
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid user email address for credentials: $userEmail");
            return false;
        }

        // Create login link
        $loginLink = url("index.php?p=login");
        
        // Prepare template data
        $templateData = [
            'user_name' => $userName,
            'user_email' => $userEmail,
            'default_password' => $defaultPassword,
            'user_role' => ucfirst($userRole),
            'login_link' => $loginLink,
            'site_name' => MAIL_FROM_NAME,
            'site_url' => rtrim(url(), '/'),
            'support_email' => MAIL_FROM_ADDRESS
        ];

        // Get email template
        $htmlBody = getEmailTemplate('new_user_credentials', $templateData);
        
        // Initialize PHPMailer
        $mail = new PHPMailer(true);
        
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        
        // Additional SMTP settings
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Set charset
        $mail->CharSet = 'UTF-8';
        
        // Email settings
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($userEmail, $userName);
        $mail->isHTML(true);
        $mail->Subject = '🎉 Welcome! Your Account Has Been Created - ' . MAIL_FROM_NAME;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        
        // Send email
        $result = $mail->send();
        
        if ($result) {
            error_log("✅ New user credentials email sent successfully to: $userEmail (Role: $userRole)");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("❌ New user credentials email sending failed: " . $e->getMessage());
        return false;
    }
}
