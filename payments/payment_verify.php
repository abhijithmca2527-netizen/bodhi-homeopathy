<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/cashfree.php';

header('Content-Type: application/json; charset=utf-8');

// Security Check: Must be logged-in patient
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Patient') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$orderId = filter_var($_REQUEST['order_id'] ?? 0, FILTER_VALIDATE_INT);

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid Order ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM `orders` WHERE `id` = ? AND `user_id` = ?");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order Not Found']);
        exit;
    }

    if ($order['payment_status'] === 'Paid') {
        echo json_encode([
            'success' => true,
            'order_id' => $orderId,
            'payment_status' => 'Paid',
            'order_status' => $order['order_status']
        ]);
        exit;
    }

    $currentStatus = $order['payment_status'];

    if (is_cashfree_configured() && !empty($order['cashfree_order_id'])) {
        $cfOrderId = $order['cashfree_order_id'];
        $paymentsRes = get_cashfree_order_payments($cfOrderId);

        if ($paymentsRes['success'] && is_array($paymentsRes['data'])) {
            foreach ($paymentsRes['data'] as $payment) {
                $pStatus = strtoupper($payment['payment_status'] ?? '');
                if ($pStatus === 'SUCCESS') {
                    $currentStatus = 'Paid';
                    $updateStmt = $pdo->prepare("UPDATE `orders` SET `payment_status` = 'Paid' WHERE `id` = ?");
                    $updateStmt->execute([$orderId]);
                    $clearCartStmt = $pdo->prepare("DELETE FROM `cart_items` WHERE `user_id` = ?");
                    $clearCartStmt->execute([$userId]);
                    break;
                } elseif (in_array($pStatus, ['FAILED', 'CANCELLED', 'USER_DROPPED'], true)) {
                    $currentStatus = 'Failed';
                    $updateStmt = $pdo->prepare("UPDATE `orders` SET `payment_status` = 'Failed' WHERE `id` = ? AND `payment_status` != 'Paid'");
                    $updateStmt->execute([$orderId]);
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'payment_status' => $currentStatus,
        'order_status' => $order['order_status']
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
