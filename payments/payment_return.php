<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/cashfree.php';

// Security Check: Must be logged-in patient
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'Patient') {
    header("Location: " . BASE_URL . "products.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$orderId = filter_var($_GET['order_id'] ?? 0, FILTER_VALIDATE_INT);

if ($orderId <= 0) {
    header("Location: " . BASE_URL . "my_orders.php");
    exit;
}

try {
    // 1. Fetch local order with strict user ownership validation
    $stmt = $pdo->prepare("SELECT * FROM `orders` WHERE `id` = ? AND `user_id` = ?");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header("Location: " . BASE_URL . "my_orders.php");
        exit;
    }

    // 2. If already Paid, simply redirect to order confirmation
    if ($order['payment_status'] === 'Paid') {
        header("Location: " . BASE_URL . "order_success.php?order_id=" . $orderId);
        exit;
    }

    // 3. Server-side verification with Cashfree API
    if (is_cashfree_configured() && !empty($order['cashfree_order_id'])) {
        $cfOrderId = $order['cashfree_order_id'];

        // Retrieve payments for this Cashfree order
        $paymentsRes = get_cashfree_order_payments($cfOrderId);
        $isPaid = false;
        $isFailed = false;

        if ($paymentsRes['success'] && is_array($paymentsRes['data'])) {
            foreach ($paymentsRes['data'] as $payment) {
                $pStatus = strtoupper($payment['payment_status'] ?? '');
                if ($pStatus === 'SUCCESS') {
                    $isPaid = true;
                    break;
                } elseif (in_array($pStatus, ['FAILED', 'CANCELLED', 'USER_DROPPED'], true)) {
                    $isFailed = true;
                }
            }
        } else {
            // Check top-level order status as fallback
            $orderRes = get_cashfree_order($cfOrderId);
            if ($orderRes['success'] && strtoupper($orderRes['data']['order_status'] ?? '') === 'PAID') {
                $isPaid = true;
            }
        }

        // 4. Update local payment status safely & clear cart on confirmed payment
        if ($isPaid) {
            $updateStmt = $pdo->prepare("UPDATE `orders` SET `payment_status` = 'Paid' WHERE `id` = ?");
            $updateStmt->execute([$orderId]);

            // Clear cart only after confirmed successful payment
            $clearCartStmt = $pdo->prepare("DELETE FROM `cart_items` WHERE `user_id` = ?");
            $clearCartStmt->execute([$userId]);
        } elseif ($isFailed) {
            $updateStmt = $pdo->prepare("UPDATE `orders` SET `payment_status` = 'Failed' WHERE `id` = ? AND `payment_status` != 'Paid'");
            $updateStmt->execute([$orderId]);
        }
    }

} catch (Exception $e) {
    // Log exception safely without breaking user flow
    error_log("Payment return handler error: " . $e->getMessage());
}

// Redirect to order confirmation page
header("Location: " . BASE_URL . "order_success.php?order_id=" . $orderId);
exit;
