<?php
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        echo "<script>alert('Invalid security token. Please try again.'); window.history.back();</script>";
        exit;
    }

    $userId = (int)$_SESSION['user_id'];
    $orderId = (int)$_POST['order_id'];
    $reason = trim($_POST['reason'] ?? 'Customer requested order cancellation.');

    if ($orderId <= 0) {
        header("Location: " . BASE_URL . "my_orders.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM `orders` WHERE `id` = ? AND `user_id` = ?");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            echo "<script>alert('Order not found or access denied.'); window.location.href = '" . BASE_URL . "my_orders.php';</script>";
            exit;
        }

        // Eligibility Check: Can only request before Shipped / Delivered / Cancelled
        $nonCancellableStatuses = ['Shipped', 'Delivered', 'Cancelled'];
        if (in_array($order['order_status'], $nonCancellableStatuses, true)) {
            echo "<script>alert('This order cannot be cancelled as it is already " . strtolower($order['order_status']) . ".'); window.history.back();</script>";
            exit;
        }

        if (($order['cancellation_status'] ?? 'None') !== 'None') {
            echo "<script>alert('A cancellation request is already pending or processed for this order.'); window.history.back();</script>";
            exit;
        }

        // Record cancellation request
        $updateStmt = $pdo->prepare("UPDATE `orders` SET `cancellation_status` = 'Requested', `cancellation_reason` = ? WHERE `id` = ? AND `user_id` = ?");
        $updateStmt->execute([$reason, $orderId, $userId]);

        echo "<script>alert('Your order cancellation request has been submitted successfully.'); window.location.href = '" . BASE_URL . "order_details.php?order_id=" . $orderId . "';</script>";
        exit;

    } catch (Exception $e) {
        error_log("Error submitting cancellation request: " . $e->getMessage());
        echo "<script>alert('An error occurred while submitting your cancellation request. Please try again.'); window.history.back();</script>";
        exit;
    }
} else {
    header("Location: " . BASE_URL . "my_orders.php");
    exit;
}
