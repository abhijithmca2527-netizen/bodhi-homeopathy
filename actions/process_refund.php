<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/cashfree.php';

// Security check: strictly enforce Admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        echo "<script>alert('Invalid security token. Please try again.'); window.history.back();</script>";
        exit;
    }

    $orderId = (int)$_POST['order_id'];
    $adminAction = trim($_POST['admin_action'] ?? 'approve'); // 'approve' or 'reject'
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if ($orderId <= 0) {
        header("Location: " . BASE_URL . "admin/orders.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM `orders` WHERE `id` = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            echo "<script>alert('Order not found.'); window.location.href = '" . BASE_URL . "admin/orders.php';</script>";
            exit;
        }

        // Action: Reject Cancellation Request
        if ($adminAction === 'reject') {
            $updateStmt = $pdo->prepare("UPDATE `orders` SET `cancellation_status` = 'Rejected' WHERE `id` = ?");
            $updateStmt->execute([$orderId]);
            echo "<script>alert('Cancellation request rejected.'); window.location.href = '" . BASE_URL . "admin/order_details.php?id=" . $orderId . "';</script>";
            exit;
        }

        // Action: Approve Cancellation Request
        $pdo->beginTransaction();

        // 1. Idempotent Stock Restoration (Restore stock exactly ONCE)
        if (empty($order['stock_restored'])) {
            $itemsStmt = $pdo->prepare("SELECT `product_id`, `quantity` FROM `order_items` WHERE `order_id` = ?");
            $itemsStmt->execute([$orderId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            $stockUpdate = $pdo->prepare("UPDATE `products` SET `stock_quantity` = `stock_quantity` + ? WHERE `id` = ?");
            foreach ($items as $item) {
                $stockUpdate->execute([(int)$item['quantity'], (int)$item['product_id']]);
            }

            // Flag stock as restored to prevent duplicate inventory restoration
            $flagStmt = $pdo->prepare("UPDATE `orders` SET `stock_restored` = 1 WHERE `id` = ?");
            $flagStmt->execute([$orderId]);
        }

        // 2. Process Cashfree Server-Side Refund if Paid Online Order
        $isPaidOnline = ($order['payment_status'] === 'Paid' && (!empty($order['cashfree_order_id']) || $order['payment_method'] === 'Online Payment' || $order['payment_method'] === 'Cashfree'));
        $cfOrderId = $order['cashfree_order_id'] ?? ('bodhi_' . $orderId);

        if ($isPaidOnline) {
            // Check if a refund record already exists to prevent duplicate refunds
            $refCheck = $pdo->prepare("SELECT * FROM `refunds` WHERE `order_id` = ? AND `refund_status` IN ('Pending', 'SUCCESS', 'Refunded')");
            $refCheck->execute([$orderId]);
            $existingRefund = $refCheck->fetch(PDO::FETCH_ASSOC);

            if (!$existingRefund) {
                $merchantRefundId = 'REF-ORD-' . str_pad($orderId, 4, '0', STR_PAD_LEFT) . '-' . time();
                $refundAmount = (float)$order['total_amount'];

                // Execute server-side Cashfree API refund request
                $cfRes = create_cashfree_refund($cfOrderId, $merchantRefundId, $refundAmount, 'Order cancellation approved by admin');

                $gatewayRefundId = $cfRes['cf_refund_id'] ?? null;
                $cfStatus = strtoupper($cfRes['refund_status'] ?? ($cfRes['success'] ? 'PENDING' : 'FAILED'));
                
                $refundDbStatus = 'Pending';
                if ($cfStatus === 'SUCCESS' || $cfStatus === 'PROCESSED') {
                    $refundDbStatus = 'Refunded';
                } elseif (!$cfRes['success'] && strpos(strtolower($cfRes['error']), 'already') === false) {
                    $refundDbStatus = 'Failed';
                }

                // Insert into refunds table
                $insRef = $pdo->prepare("
                    INSERT INTO `refunds` (`order_id`, `cashfree_order_id`, `merchant_refund_id`, `cashfree_refund_id`, `refund_amount`, `refund_status`, `refund_reason`)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $insRef->execute([
                    $orderId,
                    $cfOrderId,
                    $merchantRefundId,
                    $gatewayRefundId,
                    $refundAmount,
                    $refundDbStatus,
                    'Admin approval cancellation: ' . $adminNotes
                ]);

                // Update orders table refund and order status
                $updateOrder = $pdo->prepare("UPDATE `orders` SET `cancellation_status` = 'Approved', `order_status` = 'Cancelled', `refund_status` = ? WHERE `id` = ?");
                $updateOrder->execute([$refundDbStatus, $orderId]);
            } else {
                // If refund already recorded, enforce status update
                $updateOrder = $pdo->prepare("UPDATE `orders` SET `cancellation_status` = 'Approved', `order_status` = 'Cancelled' WHERE `id` = ?");
                $updateOrder->execute([$orderId]);
            }
        } else {
            // Unpaid / Non-online / COD order cancellation
            $updateOrder = $pdo->prepare("UPDATE `orders` SET `cancellation_status` = 'Approved', `order_status` = 'Cancelled', `refund_status` = 'N/A' WHERE `id` = ?");
            $updateOrder->execute([$orderId]);
        }

        $pdo->commit();

        echo "<script>alert('Cancellation request approved successfully.'); window.location.href = '" . BASE_URL . "admin/order_details.php?id=" . $orderId . "';</script>";
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error processing refund/cancellation: " . $e->getMessage());
        echo "<script>alert('An error occurred while processing the refund or cancellation. Please try again.'); window.history.back();</script>";
        exit;
    }
} else {
    header("Location: " . BASE_URL . "admin/orders.php");
    exit;
}
