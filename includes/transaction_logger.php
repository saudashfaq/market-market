<?php
/**
 * Comprehensive Transaction & Payment Logging System
 * Logs all payment flows, OTP attempts, and API responses
 */

/**
 * Log transaction events with detailed context
 * 
 * @param string $event Event type (payment_initiated, escrow_created, otp_attempt, etc.)
 * @param array $data Event data
 * @param string $level Log level (info, warning, error, success)
 */
function log_transaction_event($event, $data = [], $level = 'info')
{
    $logFile = __DIR__ . '/../logs/transactions.log';
    $timestamp = date('Y-m-d H:i:s');
    
    // Prepare log entry
    $logEntry = [
        'timestamp' => $timestamp,
        'event' => $event,
        'level' => strtoupper($level),
        'data' => $data,
        'user_id' => $_SESSION['user']['id'] ?? null,
        'user_email' => $_SESSION['user']['email'] ?? null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Format log message
    $logMessage = sprintf(
        "[%s] [%s] %s\n%s\n%s\n",
        $timestamp,
        strtoupper($level),
        $event,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        str_repeat('-', 80)
    );
    
    // Ensure logs directory exists
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Write to log file
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Also log to PHP error log for critical events
    if (in_array($level, ['error', 'critical'])) {
        error_log("[TRANSACTION] $event: " . json_encode($data));
    }
    
    return true;
}

/**
 * Log payment initiation
 */
function log_payment_initiated($listing_id, $buyer_id, $seller_id, $amount, $is_offer = false, $offer_id = null)
{
    log_transaction_event('payment_initiated', [
        'listing_id' => $listing_id,
        'buyer_id' => $buyer_id,
        'seller_id' => $seller_id,
        'amount' => $amount,
        'is_offer_payment' => $is_offer,
        'offer_id' => $offer_id,
        'platform_fee' => round($amount * 0.05, 2),
        'total_amount' => $amount + round($amount * 0.05, 2)
    ], 'info');
}

/**
 * Log seller payout configuration
 */
function log_payout_config($seller_id, $payout_method, $config = [])
{
    $safeConfig = $config;
    // Mask sensitive data
    if (isset($safeConfig['account_number'])) {
        $safeConfig['account_number'] = '****' . substr($safeConfig['account_number'], -4);
    }
    
    log_transaction_event('payout_config_loaded', [
        'seller_id' => $seller_id,
        'payout_method' => $payout_method,
        'config' => $safeConfig
    ], 'info');
}

/**
 * Log escrow creation attempt
 */
function log_escrow_creation($listing_id, $amount, $buyer_details, $seller_details, $payout_config)
{
    $safePayoutConfig = $payout_config;
    if ($safePayoutConfig && isset($safePayoutConfig['account_number'])) {
        $safePayoutConfig['account_number'] = '****' . substr($safePayoutConfig['account_number'], -4);
    }
    
    log_transaction_event('escrow_creation_attempt', [
        'listing_id' => $listing_id,
        'amount' => $amount,
        'buyer_email' => $buyer_details['email'] ?? null,
        'seller_email' => $seller_details['email'] ?? null,
        'has_payout_config' => !empty($payout_config),
        'payout_config' => $safePayoutConfig
    ], 'info');
}

/**
 * Log escrow creation success
 */
function log_escrow_success($escrow_id, $transaction_ref, $payment_url, $provider)
{
    log_transaction_event('escrow_created_success', [
        'escrow_id' => $escrow_id,
        'transaction_ref' => $transaction_ref,
        'payment_url' => $payment_url,
        'provider' => $provider
    ], 'success');
}

/**
 * Log escrow creation failure
 */
function log_escrow_failure($error, $response = null)
{
    log_transaction_event('escrow_creation_failed', [
        'error' => $error,
        'api_response' => $response
    ], 'error');
}

/**
 * Log OTP submission attempt
 */
function log_otp_attempt($transaction_id, $escrow_id, $otp_length)
{
    log_transaction_event('otp_submission_attempt', [
        'transaction_id' => $transaction_id,
        'escrow_id' => $escrow_id,
        'otp_length' => $otp_length,
        'timestamp' => date('Y-m-d H:i:s')
    ], 'info');
}

/**
 * Log OTP verification success
 */
function log_otp_success($transaction_id, $escrow_id, $amount)
{
    log_transaction_event('otp_verification_success', [
        'transaction_id' => $transaction_id,
        'escrow_id' => $escrow_id,
        'amount' => $amount,
        'funds_released' => true
    ], 'success');
}

/**
 * Log OTP verification failure
 */
function log_otp_failure($transaction_id, $escrow_id, $error, $response = null)
{
    log_transaction_event('otp_verification_failed', [
        'transaction_id' => $transaction_id,
        'escrow_id' => $escrow_id,
        'error' => $error,
        'api_response' => $response
    ], 'error');
}

/**
 * Log API request/response
 */
function log_api_call($endpoint, $method, $payload, $response, $http_code)
{
    $safePayload = $payload;
    // Mask sensitive data in payload
    if (isset($safePayload['payout']['account_number'])) {
        $safePayload['payout']['account_number'] = '****' . substr($safePayload['payout']['account_number'], -4);
    }
    
    log_transaction_event('api_call', [
        'endpoint' => $endpoint,
        'method' => $method,
        'payload' => $safePayload,
        'http_code' => $http_code,
        'response' => $response
    ], $http_code >= 200 && $http_code < 300 ? 'info' : 'error');
}

/**
 * Log database transaction
 */
function log_db_transaction($action, $table, $data, $success = true)
{
    log_transaction_event('database_operation', [
        'action' => $action,
        'table' => $table,
        'data' => $data,
        'success' => $success
    ], $success ? 'info' : 'error');
}

/**
 * Log webhook received
 */
function log_webhook_received($event, $escrow_id, $status, $payload)
{
    log_transaction_event('webhook_received', [
        'event' => $event,
        'escrow_id' => $escrow_id,
        'status' => $status,
        'payload' => $payload
    ], 'info');
}

/**
 * Get recent transaction logs
 */
function get_recent_transaction_logs($limit = 100)
{
    $logFile = __DIR__ . '/../logs/transactions.log';
    
    if (!file_exists($logFile)) {
        return [];
    }
    
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $logs = [];
    $currentLog = '';
    
    foreach (array_reverse($lines) as $line) {
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line)) {
            if ($currentLog) {
                $logs[] = $currentLog;
                if (count($logs) >= $limit) break;
            }
            $currentLog = $line;
        } else {
            $currentLog .= "\n" . $line;
        }
    }
    
    if ($currentLog && count($logs) < $limit) {
        $logs[] = $currentLog;
    }
    
    return $logs;
}

/**
 * Search transaction logs
 */
function search_transaction_logs($search_term, $limit = 50)
{
    $logFile = __DIR__ . '/../logs/transactions.log';
    
    if (!file_exists($logFile)) {
        return [];
    }
    
    $content = file_get_contents($logFile);
    $entries = explode(str_repeat('-', 80), $content);
    
    $results = [];
    foreach (array_reverse($entries) as $entry) {
        if (stripos($entry, $search_term) !== false) {
            $results[] = trim($entry);
            if (count($results) >= $limit) break;
        }
    }
    
    return $results;
}
