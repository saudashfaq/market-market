<?php
/**
 * PandaScrow Wallet API Integration
 * For managing platform wallet, checking balance, and requesting payouts
 */

require_once __DIR__ . '/../config.php';

/**
 * Get platform wallet balance
 * Shows available funds, locked funds, and total balance
 */
function get_platform_wallet_balance($range = 'today')
{
    $url = PANDASCROW_BASE_URL . "/wallet/balance";
    $params = [
        'uuid' => PANDASCROW_UUID,
        'range' => $range // today, 1week, 1month, 1year
    ];
    
    $url .= '?' . http_build_query($params);
    
    $headers = [
        "Accept: application/json",
        "Token: " . PANDASCROW_PUBLIC_KEY,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    file_put_contents(__DIR__ . '/debug_pandascrow.log', 
        "[" . date('Y-m-d H:i:s') . "] /wallet/balance ($httpCode): " . $response . PHP_EOL, FILE_APPEND);

    $data = json_decode($response, true);

    if ($httpCode === 200 && isset($data['status']) && $data['status'] === true) {
        return [
            'success' => true,
            'data' => $data['data'] ?? []
        ];
    }

    return [
        'success' => false,
        'error' => $data['message'] ?? 'Failed to fetch wallet balance'
    ];
}

/**
 * Get platform wallet transaction history
 * Shows all deposits, payouts, and escrow transactions
 */
function get_platform_wallet_transactions($currency = 'USD')
{
    $url = PANDASCROW_BASE_URL . "/wallet";
    $params = [
        'uuid' => PANDASCROW_UUID,
        'currency' => $currency
    ];
    
    $url .= '?' . http_build_query($params);
    
    $headers = [
        "Accept: application/json",
        "Token: " . PANDASCROW_PUBLIC_KEY,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode === 200 && isset($data['status']) && $data['status'] === true) {
        return [
            'success' => true,
            'data' => $data['data'] ?? []
        ];
    }

    return [
        'success' => false,
        'error' => $data['message'] ?? 'Failed to fetch transactions'
    ];
}

/**
 * Validate Nigerian bank account before payout
 * Returns account holder name for verification
 */
function validate_bank_account($accountNumber, $bankCode)
{
    $url = PANDASCROW_BASE_URL . "/bank/validate";
    $params = [
        'uuid' => PANDASCROW_UUID,
        'account_number' => $accountNumber,
        'bank_code' => $bankCode
    ];
    
    $url .= '?' . http_build_query($params);
    
    $headers = [
        "Accept: application/json",
        "Token: " . PANDASCROW_PUBLIC_KEY,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode === 200 && isset($data['status']) && $data['status'] === true) {
        return [
            'success' => true,
            'account_name' => $data['data']['account_name'] ?? null,
            'account_number' => $data['data']['account_number'] ?? null
        ];
    }

    return [
        'success' => false,
        'error' => $data['message'] ?? 'Account validation failed'
    ];
}

/**
 * Get list of supported banks
 * Useful for bank selection dropdown
 */
function get_supported_banks()
{
    $url = PANDASCROW_BASE_URL . "/bank/lists";
    
    $headers = [
        "Accept: application/json",
        "Token: " . PANDASCROW_PUBLIC_KEY,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode === 200 && isset($data['status']) && $data['status'] === true) {
        return [
            'success' => true,
            'banks' => $data['data'] ?? []
        ];
    }

    return [
        'success' => false,
        'error' => $data['message'] ?? 'Failed to fetch banks'
    ];
}

/**
 * Request payout from platform wallet to bank account
 * This withdraws your commission earnings
 */
function request_platform_payout($amount, $currency, $bankDetails)
{
    $payload = [
        'uuid' => uniqid('payout_', true), // Unique payout ID
        'wallet_id' => null, // Will be auto-detected from PANDASCROW_UUID
        'amount' => $amount,
        'currency' => $currency,
        'method' => 'bank_transfer',
        'destination' => [
            [
                'bank_code' => $bankDetails['bank_code'],
                'account_number' => $bankDetails['account_number'],
                'account_name' => $bankDetails['account_name'],
                'bank_name' => $bankDetails['bank_name'] ?? null
            ]
        ]
    ];

    $headers = [
        "Accept: application/json",
        "Content-Type: application/json",
        "Token: " . PANDASCROW_PUBLIC_KEY,
    ];

    $ch = curl_init(PANDASCROW_BASE_URL . "/wallet/payout");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    file_put_contents(__DIR__ . '/debug_pandascrow.log', 
        "[" . date('Y-m-d H:i:s') . "] /wallet/payout ($httpCode): " . $response . PHP_EOL, FILE_APPEND);

    $data = json_decode($response, true);

    if ($httpCode === 200 && isset($data['status']) && $data['status'] === true) {
        return [
            'success' => true,
            'message' => 'Payout request successful',
            'data' => $data['data'] ?? []
        ];
    }

    return [
        'success' => false,
        'error' => $data['message'] ?? 'Payout request failed',
        'raw' => $data
    ];
}

/**
 * Fund platform wallet (for testing or adding balance)
 * In production, this happens automatically from escrow commissions
 */
function fund_platform_wallet($amount, $currency = 'USD', $vat = null)
{
    $payload = [
        'uuid' => PANDASCROW_UUID,
        'currency' => $currency,
        'amount' => $amount
    ];
    
    // VAT required for NGN wallets (7.5%)
    if ($currency === 'NGN' && $vat !== null) {
        $payload['vat'] = $vat;
    }

    $headers = [
        "Accept: application/json",
        "Content-Type: application/json",
        "Token: " . PANDASCROW_PUBLIC_KEY,
    ];

    $ch = curl_init(PANDASCROW_BASE_URL . "/wallet/deposit");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode === 200 && isset($data['status']) && $data['status'] === true) {
        return [
            'success' => true,
            'data' => $data['data'] ?? []
        ];
    }

    return [
        'success' => false,
        'error' => $data['message'] ?? 'Wallet funding failed'
    ];
}
