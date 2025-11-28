<?php
require_once __DIR__ . '/../config.php';

// === Error + Log setup ===
error_reporting(E_ALL);
ini_set('display_errors', 1);

function debug_log($msg, $data = null)
{
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $msg";
    if ($data !== null) $log .= " | " . print_r($data, true);
    $log .= "\n";
    file_put_contents(__DIR__ . '/debug_pandascrow.log', $log, FILE_APPEND);
    echo $log;
}

// === Helper: Pandascrow API Request ===
// function pandascrow_api_request($method, $endpoint, $data = [], $auth = false)
// {
//     $url = rtrim(PANDASCROW_BASE_URL, '/') . $endpoint;
//     $headers = [
//         "Accept: application/json",
//         "Token: " . PANDASCROW_PUBLIC_KEY,
//         "Content-Type: application/json"
//     ];

//     // Auth
//     if ($auth !== false) {
//         $token = null;
//         if (is_string($auth)) {
//             $token = $auth;
//         } else {
//             $token = get_pandascrow_token();
//         }

//         if (!$token) {
//             debug_log("❌ Auth token missing");
//             return ['success' => false, 'error' => 'Auth token missing'];
//         }
//         $headers[] = "Authorization: Bearer $token";
//     }

//     $ch = curl_init($url);
//     $opts = [
//         CURLOPT_RETURNTRANSFER => true,
//         CURLOPT_CUSTOMREQUEST => strtoupper($method),
//         CURLOPT_HTTPHEADER => $headers,
//         CURLOPT_TIMEOUT => 30,
//         CURLOPT_SSL_VERIFYPEER => false,
//     ];

//     if (!empty($data)) {
//         $opts[CURLOPT_POSTFIELDS] = json_encode($data);
//         debug_log("📦 Request to $endpoint", $data);
//     }

//     curl_setopt_array($ch, $opts);
//     $response = curl_exec($ch);
//     $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//     $err = curl_error($ch);
//     curl_close($ch);

//     if ($err) {
//         debug_log("❌ cURL Error", $err);
//         return ['success' => false, 'error' => $err];
//     }

//     debug_log("📡 Raw Response ($code)", $response);

//     $json = json_decode($response, true);
//     if (json_last_error() !== JSON_ERROR_NONE) {
//         debug_log("⚠️ Response is not JSON. HTTP Code: $code");

//         if ($code >= 200 && $code < 300) {
//             return ['success' => true, 'raw' => $response, 'http_code' => $code];
//         }

//         return ['success' => false, 'error' => 'Invalid JSON response', 'raw' => $response, 'http_code' => $code];
//     }

//     debug_log("📡 JSON Response ($code)", $json);

//     if ($code >= 200 && $code < 300) {
//         return ['success' => true, 'data' => $json, 'http_code' => $code];
//     }

//     return ['success' => false, 'error' => $json['message'] ?? $json['data']['message'] ?? "HTTP $code", 'data' => $json, 'http_code' => $code];
// }

// // === Token Helpers ===
// function get_pandascrow_token_for_user($email, $password)
// {
//     $res = pandascrow_api_request('POST', '/login', compact('email', 'password'), false);
//     if ($res['success'] && !empty($res['data']['data']['token'])) {
//         return $res['data']['data']['token'];
//     }
//     return null;
// }

// function get_pandascrow_token()
// {
//     if (!defined('PANDASCROW_DEFAULT_EMAIL') || !defined('PANDASCROW_DEFAULT_PASSWORD')) {
//         debug_log("⚠️ Default credentials missing in config");
//         return null;
//     }
//     return get_pandascrow_token_for_user(PANDASCROW_DEFAULT_EMAIL, PANDASCROW_DEFAULT_PASSWORD);
// }

// // === Email Verification Functions ===
// function verify_pandascrow_email($userUuid, $verificationCode, $auth = false)
// {
//     $res = pandascrow_api_request('POST', '/verify/email', [
//         'uuid' => $userUuid,
//         'token' => $verificationCode
//     ], $auth);

//     if ($res['success']) {
//         debug_log("✅ Email verified successfully for UUID: $userUuid");
//         return ['success' => true];
//     }

//     return ['success' => false, 'error' => $res['error'] ?? 'Email verification failed'];
// }

// function try_verification_codes($userUuid, $partnerToken)
// {
//     // Meeting ke according verification codes
//     $codes = ['12345678', '00000000'];

//     foreach ($codes as $code) {
//         debug_log("🔄 Trying verification code: $code for UUID: $userUuid");
//         $result = verify_pandascrow_email($userUuid, $code, $partnerToken);

//         if ($result['success']) {
//             debug_log("✅ Verification successful with code: $code");
//             return ['success' => true, 'code' => $code];
//         } else {
//             debug_log("❌ Verification failed with code: $code - " . ($result['error'] ?? ''));
//         }
//     }

//     return ['success' => false, 'error' => 'All verification codes failed'];
// }

// // === User Creation with Verification ===
// function create_and_verify_pandascrow_user($email, $name, $partnerToken)
// {
//     debug_log("👤 Handling user: $email");

//     // 🔍 STEP 1: Pehle local database me check karen
//     $existingUuid = get_local_user_pandascrow_uuid($email);
//     if ($existingUuid) {
//         debug_log("✅ Found existing UUID in local DB: $existingUuid");
//         return ['success' => true, 'uuid' => $existingUuid, 'existing' => true];
//     }

//     // 🆕 STEP 2: Naya user create karen
//     debug_log("🆕 Creating new Pandascrow user: $email");
//     $userRes = pandascrow_api_request('POST', '/signup', [
//         'name' => $name,
//         'email' => $email,
//         'password' => PANDASCROW_DEFAULT_USER_PASSWORD,
//         'phoneNumber' => '+18000000000'
//     ], $partnerToken);

//     // 🔄 STEP 3: Handle existing user on Pandascrow
//     if (!$userRes['success']) {
//         $errorMessage = $userRes['data']['data']['message'] ?? $userRes['error'] ?? '';

//         if (stripos($errorMessage, 'already registered') !== false) {
//             debug_log("⚠️ User exists on Pandascrow - trying login...");

//             $loginRes = pandascrow_api_request('POST', '/login', [
//                 'email' => $email,
//                 'password' => PANDASCROW_DEFAULT_USER_PASSWORD
//             ], false);

//             if ($loginRes['success'] && !empty($loginRes['data']['data']['user']['uuid'])) {
//                 $userUuid = $loginRes['data']['data']['user']['uuid'];
//                 debug_log("✅ Logged in existing Pandascrow user: $userUuid");

//                 // 💾 IMPORTANT: UUID LOCAL ME SAVE KAREN
//                 save_pandascrow_uuid_to_local_user($email, $userUuid);
//                 return ['success' => true, 'uuid' => $userUuid, 'existing' => true];
//             }
//         }
//         return ['success' => false, 'error' => 'User setup failed'];
//     }

//     // ✅ STEP 4: New user created successfully
//     $userUuid = $userRes['data']['data']['user']['uuid'] ?? null;
//     if (!$userUuid) {
//         return ['success' => false, 'error' => 'User UUID not found'];
//     }

//     debug_log("✅ New Pandascrow user created: $userUuid");

//     // 💾 IMPORTANT: NEW UUID LOCAL ME SAVE KAREN
//     save_pandascrow_uuid_to_local_user($email, $userUuid);

//     return [
//         'success' => true,
//         'uuid' => $userUuid,
//         'existing' => false
//     ];
// }

// /**
//  * ✅ Save Pandascrow UUID to local user record
//  */
// function save_pandascrow_uuid_to_local_user($email, $pandascrowUuid)
// {
//     $pdo = db();
//     try {
//         $stmt = $pdo->prepare("UPDATE users SET pandascrow_uuid = ? WHERE email = ?");
//         $result = $stmt->execute([$pandascrowUuid, $email]);

//         if ($stmt->rowCount() > 0) {
//             error_log("💾 Saved Pandascrow UUID to local user: $pandascrowUuid");
//             return true;
//         } else {
//             error_log("⚠️ Local user not found for email: $email");
//             return false;
//         }
//     } catch (Exception $e) {
//         error_log("❌ Error saving Pandascrow UUID: " . $e->getMessage());
//         return false;
//     }
// }

// /**
//  * ✅ Get existing UUID from local database
//  */
// function get_local_user_pandascrow_uuid($email)
// {
//     $pdo = db();
//     $stmt = $pdo->prepare("SELECT pandascrow_uuid FROM users WHERE email = ?");
//     $stmt->execute([$email]);
//     $user = $stmt->fetch(PDO::FETCH_ASSOC);

//     return $user['pandascrow_uuid'] ?? null;
// }

// function create_pandascrow_escrow($amount, $title, $description, $auth, $buyerUuid, $sellerUuid)
// {
//     $payload = [
//         "uuid" => "468cfad2-6800-486f-a08b-0a77d2e5b0e7",
//         "escrow_type" => "onetime",
//         "initiator_role" => "buyer",
//         "initiator_id" => $buyerUuid,
//         "receiver_id" => $sellerUuid,
//         "title" => $title,
//         "currency" => "USD",
//         "description" => $description,
//         "acceptance_criteria" => "Work must meet all specifications and requirements",
//         "inspection_period" => "3",
//         "delivery_date" => date('Y-m-d', strtotime('+7 days')),
//         "how_dispute_is_handled" => "platform",
//         "who_pay_fees" => "both",
//         "amount" => $amount,
//         "dispute_window" => "7",
//         "buyer_details" => [
//             "name" => "Dummy Buyer",
//             "email" => "dummybuyer@gmail.com",
//             "phone" => "+9234733848",
//         ],
//         "seller_details" => [
//             "name" => "Dummy Seller",
//             "email" => "dummyseller@gmail.com",
//             "phone" => "+9234733849",
//         ],

//         "callback_url" => "https://359412d604de.ngrok-free.app/marketplace/public/webhook.php",

//     ];

//     debug_log("📦 Escrow Payload", $payload);

//     return pandascrow_api_request('POST', '/escrow/initialize', $payload, $auth);
// }


// function update_pandascrow_webhook($auth)
// {
//     $payload = [
//         "uuid" => "468cfad2-6800-486f-a08b-0a77d2e5b0e7",
//         "webhook_url" => "https://359412d604de.ngrok-free.app/marketplace/public/webhook.php", // ✅ correct
//         "ip_whitelist" => ""
//     ];

//     debug_log("🔁 Updating Pandascrow webhook", $payload);

//     return pandascrow_api_request('POST', '/application', $payload, $auth);
// }

function create_pandascrow_escrow($amount, $title, $description, $buyerDetails = [], $sellerDetails = [], $sellerPayoutConfig = null)
{
    $payload = [
        "uuid" => PANDASCROW_UUID,
        "escrow_type" => "onetime",
        "initiator_role" => "buyer",
        "initiator_id" => PANDASCROW_UUID,
        "receiver_id" => null,
        "title" => $title,
        "currency" => "USD",
        "description" => $description,
        "acceptance_criteria" => "Work must meet specifications.",
        "inspection_period" => "3",
        "delivery_date" => date('Y-m-d', strtotime('+7 days')),
        "how_dispute_is_handled" => "platform",
        "who_pay_fees" => "buyer",
        "amount" => $amount,
        "dispute_window" => "7",
        "buyer_details" => [
            "name" => "saud",
            "email" => "saudz0413@gmail.com",
            "phone" => "08021325996",
        ],
        "seller_details" => !empty($sellerDetails) ? $sellerDetails : [
            "name" => "Dummy Seller",
            "email" => "dummyseller@gmail.com",
            "phone" => "+9234733849",
        ],
        "callback_url" => url('/webhook.php')
    ];
    
    // ✅ Add seller payout configuration if provided (for automatic settlement)
    // This allows funds to be automatically transferred to seller's account after escrow completion
    if ($sellerPayoutConfig && is_array($sellerPayoutConfig)) {
        $payload['payout'] = $sellerPayoutConfig;
    }

    $headers = [
        "Accept: application/json",
        "Content-Type: application/json",
        "Token: " . PANDASCROW_PUBLIC_KEY,
    ];

    $ch = curl_init(PANDASCROW_BASE_URL . "/escrow/initialize");
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
        "[" . date('Y-m-d H:i:s') . "] /escrow/initialize ($httpCode): " . $response . PHP_EOL, FILE_APPEND);

    $data = json_decode($response, true);

    if ($httpCode === 200 && isset($data['status']) && $data['status'] === true) {
        return [
            'success' => true,
            'data' => [
                'escrow_id' => $data['data']['escrow_id'] ?? null,
                'payment_url' => $data['data']['payment_url'] ?? null,
                'transaction_ref' => $data['data']['transaction_ref'] ?? null,
                'provider' => $data['data']['provider'] ?? null
            ]
        ];
    }

    return [
        'success' => false,
        'error' => $data['message'] ?? 'Unknown error',
        'raw' => $data
    ];
}


/**
 * Complete escrow transaction with OTP
 * This releases funds to seller after buyer confirms receipt
 */
function complete_pandascrow_escrow($escrowId, $otp)
{
    $payload = [
        "escrow_id" => $escrowId,
        "otp" => $otp
    ];

    $headers = [
        "Accept: application/json",
        "Content-Type: application/json",
        "Token: " . PANDASCROW_PUBLIC_KEY,
    ];


    $ch = curl_init(PANDASCROW_BASE_URL . "/escrow/complete");
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
        "[" . date('Y-m-d H:i:s') . "] /escrow/complete ($httpCode): " . $response . PHP_EOL, FILE_APPEND);

    $data = json_decode($response, true);

    if ($httpCode === 200 && isset($data['status']) && $data['status'] === true) {
        return [
            'success' => true,
            'message' => $data['message'] ?? 'Escrow completed successfully',
            'data' => $data['data'] ?? []
        ];
    }

    return [
        'success' => false,
        'error' => $data['message'] ?? 'Unknown error',
        'raw' => $data
    ];
}

/**
 * Get escrow transaction details
 */
function get_pandascrow_escrow_details($escrowId)
{
    $headers = [
        "Accept: application/json",
        "Token: " . PANDASCROW_PUBLIC_KEY,
    ];

    $ch = curl_init(PANDASCROW_BASE_URL . "/escrow/{$escrowId}");
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
        'error' => $data['message'] ?? 'Unknown error'
    ];
}

