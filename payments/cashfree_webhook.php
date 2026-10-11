<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/cashfree.php';

header('Content-Type: application/json; charset=utf-8');

// Webhook endpoint only accepts POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? ($_SERVER['HTTP_X_CASHFREE_TIMESTAMP'] ?? '');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? ($_SERVER['HTTP_X_CASHFREE_SIGNATURE'] ?? '');

// 1. Webhook Signature Verification
if (!verify_cashfree_webhook($rawBody, $timestamp, $signature)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid webhook signature']);
    exit;
}

$payload = json_decode($rawBody, true);
if (!$payload || !is_array($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
    exit;
}

try {
    // 2. Extract Cashfree Order ID and Payment Status from payload
    $cfOrderId = $payload['data']['order']['order_id'] ?? ($payload['orderId'] ?? '');
    $paymentStatus = strtoupper($payload['data']['payment']['payment_status'] ?? ($payload['txStatus'] ?? ''));

    if (empty($cfOrderId)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing order_id in webhook payload']);
        exit;
    }

    // 3. Locate local order in database using cashfree_order_id
    $stmt = $pdo->prepare("SELECT * FROM `orders` WHERE `cashfree_order_id` = ?");
    $stmt->execute([$cfOrderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        // Return 200 to acknowledge webhook even if order not found locally to prevent Cashfree retrying indefinitely
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'message' => 'Order not found in database']);
        exit;
    }

    // 4. Idempotency Check: If order is already Paid, do not update again
    if ($order['payment_status'] === 'Paid') {
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Order already marked as Paid']);
        exit;
    }

    // 5. Update Payment Status safely & clear cart on confirmed success
    if ($paymentStatus === 'SUCCESS') {
        $updateStmt = $pdo->prepare("UPDATE `orders` SET `payment_status` = 'Paid' WHERE `id` = ?");
        $updateStmt->execute([$order['id']]);
        $clearCartStmt = $pdo->prepare("DELETE FROM `cart_items` WHERE `user_id` = ?");
        $clearCartStmt->execute([$order['user_id']]);
    } elseif (in_array($paymentStatus, ['FAILED', 'CANCELLED', 'USER_DROPPED', 'FAILURE'], true)) {
        $updateStmt = $pdo->prepare("UPDATE `orders` SET `payment_status` = 'Failed' WHERE `id` = ? AND `payment_status` != 'Paid'");
        $updateStmt->execute([$order['id']]);
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Webhook processed successfully']);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Webhook processing failed: ' . $e->getMessage()]);
    exit;
}
