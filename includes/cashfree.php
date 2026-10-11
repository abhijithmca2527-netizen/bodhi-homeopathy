<?php
require_once __DIR__ . '/config.php';

/**
 * Checks if Cashfree Sandbox / Production credentials are properly configured.
 */
function is_cashfree_configured(): bool {
    if (!defined('CASHFREE_CLIENT_ID') || !defined('CASHFREE_CLIENT_SECRET')) {
        return false;
    }
    $id = trim(CASHFREE_CLIENT_ID);
    $secret = trim(CASHFREE_CLIENT_SECRET);
    
    if (empty($id) || empty($secret)) {
        return false;
    }
    
    $placeholders = [
        'YOUR_SANDBOX_APP_ID',
        'YOUR_SANDBOX_CLIENT_ID',
        'YOUR_SANDBOX_SECRET_KEY',
        'YOUR_SANDBOX_SECRET',
        'YOUR_APP_ID',
        'YOUR_SECRET_KEY',
        'PUT_TEST_APP_ID_HERE',
        'PUT_TEST_SECRET_HERE',
        'PASTE_NEW_SANDBOX_APP_ID_HERE',
        'PASTE_NEW_SANDBOX_SECRET_HERE'
    ];
    
    if (in_array($id, $placeholders, true) || in_array($secret, $placeholders, true)) {
        return false;
    }
    
    return true;
}

/**
 * Returns the active Cashfree PG API base URL.
 */
function get_cashfree_base_url(): string {
    if (defined('CASHFREE_ENVIRONMENT') && CASHFREE_ENVIRONMENT === 'production') {
        return 'https://api.cashfree.com/pg';
    }
    return 'https://sandbox.cashfree.com/pg';
}

/**
 * Executes a cURL request to Cashfree PG API.
 */
function cashfree_api_request(string $endpoint, string $method = 'GET', ?array $data = null): array {
    if (!is_cashfree_configured()) {
        error_log("[CASHFREE] Sandbox credentials not configured or placeholder detected.");
        return [
            'success' => false,
            'http_code' => 0,
            'error' => 'Cashfree Sandbox credentials are not configured in includes/config.php.',
            'data' => null,
            'raw' => ''
        ];
    }

    $baseUrl = rtrim(get_cashfree_base_url(), '/');
    $url = $baseUrl . '/' . ltrim($endpoint, '/');
    $ch = curl_init();

    $headers = [
        'x-client-id: ' . trim(CASHFREE_CLIENT_ID),
        'x-client-secret: ' . trim(CASHFREE_CLIENT_SECRET),
        'x-api-version: ' . trim(CASHFREE_API_VERSION),
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    // SSL Certificate handling for local WAMP / Windows environment
    $caBundle = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
    if (!empty($caBundle) && file_exists($caBundle)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log("[CASHFREE] cURL Error to {$url}: " . $curlError);
        return [
            'success' => false,
            'http_code' => $httpCode,
            'error' => 'cURL Error: ' . $curlError,
            'data' => null,
            'raw' => ''
        ];
    }

    $decoded = json_decode($response, true);

    // Safe sanitized debug logging (Never log client secret or full credentials)
    error_log("[CASHFREE API] Endpoint: {$endpoint} | Method: {$method} | HTTP: {$httpCode} | Response: " . substr($response, 0, 300));

    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'http_code' => $httpCode,
            'data' => $decoded,
            'error' => '',
            'raw' => $response
        ];
    }

    $errorMsg = $decoded['message'] ?? ($decoded['error'] ?? 'Cashfree API request failed with status code ' . $httpCode);
    return [
        'success' => false,
        'http_code' => $httpCode,
        'error' => $errorMsg,
        'data' => $decoded,
        'raw' => $response
    ];
}

/**
 * Creates a Cashfree Order session for a local Bodhi order.
 */
function create_cashfree_order(array $order, array $patient): array {
    if (!is_cashfree_configured()) {
        return [
            'success' => false,
            'error' => 'Cashfree Sandbox credentials are not configured in includes/config.php.'
        ];
    }

    // Clean & normalize phone number (must be 10 digits for Indian standard)
    $cleanPhone = preg_replace('/[^0-9]/', '', $patient['phone'] ?? '');
    if (strlen($cleanPhone) === 12 && substr($cleanPhone, 0, 2) === '91') {
        $cleanPhone = substr($cleanPhone, 2);
    } elseif (strlen($cleanPhone) === 11 && substr($cleanPhone, 0, 1) === '0') {
        $cleanPhone = substr($cleanPhone, 1);
    }
    if (strlen($cleanPhone) !== 10) {
        $cleanPhone = '9876543210';
    }

    // Clean customer email
    $customerEmail = !empty($patient['email']) ? trim($patient['email']) : 'patient@bodhi.com';
    if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        $customerEmail = 'patient@bodhi.com';
    }

    // Create unique alphanumeric Cashfree order ID (under 45 characters)
    $cfOrderId = 'bodhi_' . (int)$order['id'] . '_' . time();
    $orderAmount = (float)$order['total_amount'];

    $payload = [
        'order_id' => $cfOrderId,
        'order_amount' => round($orderAmount, 2),
        'order_currency' => 'INR',
        'customer_details' => [
            'customer_id' => 'bodhi_user_' . (int)($order['user_id'] ?? ($patient['id'] ?? 1)),
            'customer_name' => !empty($patient['full_name']) ? trim($patient['full_name']) : 'Bodhi Patient',
            'customer_email' => $customerEmail,
            'customer_phone' => $cleanPhone
        ],
        'order_meta' => [
            'return_url' => BASE_URL . 'payment.php?order_id=' . (int)$order['id'] . '&cashfree_return=1&cf_id={order_id}'
        ],
        'order_note' => 'Bodhi Homeopathy Pharmacy Order #' . (int)$order['id']
    ];

    $res = cashfree_api_request('/orders', 'POST', $payload);

    if ($res['success'] && !empty($res['data']['payment_session_id'])) {
        error_log("[CASHFREE] Order session created successfully. Order ID: {$cfOrderId} | payment_session_id generated.");
        return [
            'success' => true,
            'payment_session_id' => $res['data']['payment_session_id'],
            'cf_order_id' => $res['data']['order_id'] ?? $cfOrderId,
            'order_status' => $res['data']['order_status'] ?? 'ACTIVE',
            'data' => $res['data']
        ];
    }

    error_log("[CASHFREE] Order session creation failed. Error: " . ($res['error'] ?? 'Unknown'));
    return [
        'success' => false,
        'error' => $res['error'] ?? 'Failed to initialize Cashfree order session.',
        'data' => $res['data'] ?? null
    ];
}

/**
 * Fetches order details from Cashfree by Cashfree order ID.
 */
function get_cashfree_order(string $cfOrderId): array {
    return cashfree_api_request('/orders/' . urlencode($cfOrderId), 'GET');
}

/**
 * Fetches all payment attempts and their status for a Cashfree order.
 */
function get_cashfree_order_payments(string $cfOrderId): array {
    return cashfree_api_request('/orders/' . urlencode($cfOrderId) . '/payments', 'GET');
}

/**
 * Verifies the HMAC-SHA256 signature of an incoming Cashfree webhook.
 */
function verify_cashfree_webhook(string $rawBody, string $timestamp, string $signature): bool {
    if (!defined('CASHFREE_CLIENT_SECRET') || empty(CASHFREE_CLIENT_SECRET)) {
        return false;
    }
    if (empty($timestamp) || empty($signature) || empty($rawBody)) {
        return false;
    }

    $secret = CASHFREE_CLIENT_SECRET;
    $computed = base64_encode(hash_hmac('sha256', $timestamp . $rawBody, $secret, true));

    return hash_equals($computed, $signature);
}

/**
 * Initiates a server-side refund via Cashfree PG API.
 * Endpoint: POST /orders/{order_id}/refunds
 */
function create_cashfree_refund(string $cfOrderId, string $refundId, float $amount, string $note = 'Order Cancellation'): array {
    if (!is_cashfree_configured()) {
        return [
            'success' => false,
            'error' => 'Cashfree Sandbox credentials are not configured.'
        ];
    }

    $payload = [
        'refund_id' => $refundId,
        'refund_amount' => round($amount, 2),
        'refund_note' => $note
    ];

    $res = cashfree_api_request('/orders/' . urlencode($cfOrderId) . '/refunds', 'POST', $payload);

    if ($res['success'] && !empty($res['data'])) {
        $cfRefundId = $res['data']['cf_refund_id'] ?? ($res['data']['refund_id'] ?? $refundId);
        $refundStatus = strtoupper($res['data']['refund_status'] ?? 'PENDING');
        return [
            'success' => true,
            'cf_refund_id' => (string)$cfRefundId,
            'refund_status' => $refundStatus,
            'data' => $res['data']
        ];
    }

    return [
        'success' => false,
        'error' => $res['error'] ?? 'Cashfree refund request failed.',
        'data' => $res['data'] ?? null
    ];
}

/**
 * Fetches status of a specific refund for a Cashfree order.
 * Endpoint: GET /orders/{order_id}/refunds/{refund_id}
 */
function get_cashfree_refund(string $cfOrderId, string $refundId): array {
    return cashfree_api_request('/orders/' . urlencode($cfOrderId) . '/refunds/' . urlencode($refundId), 'GET');
}

