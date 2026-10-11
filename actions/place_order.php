<?php
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "cart.php");
    exit;
}

// Security Check: Must be authenticated Patient
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Patient') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

// CSRF Security Verification: Missing/wrong CSRF token must reject the request without database changes
$csrfToken = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    $_SESSION['checkout_error'] = "Invalid security token. Please try again.";
    header("Location: " . BASE_URL . "checkout.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$shippingAddress = trim($_POST['shipping_address'] ?? '');

if (empty($shippingAddress)) {
    $_SESSION['checkout_error'] = "Please provide a valid delivery address for your medicine dispatch.";
    header("Location: " . BASE_URL . "checkout.php");
    exit;
}

try {
    // Begin atomic database transaction
    $pdo->beginTransaction();

    // 1. Lock and fetch real cart items for the patient
    $stmt = $pdo->prepare("
        SELECT 
            ci.`id` AS `cart_item_id`,
            ci.`quantity`,
            p.`id` AS `product_id`,
            p.`name`,
            p.`price`,
            p.`stock_quantity`,
            p.`status`
        FROM `cart_items` ci
        JOIN `products` p ON p.`id` = ci.`product_id`
        WHERE ci.`user_id` = ?
        FOR UPDATE
    ");
    $stmt->execute([$userId]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Validate cart is not empty
    if (empty($cartItems)) {
        $pdo->rollBack();
        $_SESSION['checkout_error'] = "Your cart is empty. Please add remedies before checkout.";
        header("Location: " . BASE_URL . "cart.php");
        exit;
    }

    // 3. Strict Server-Side Validation: Product Status, Stock Availability & Total Calculation
    $grandTotal = 0.00;
    foreach ($cartItems as $item) {
        $productId = (int)$item['product_id'];
        $productName = $item['name'];
        $orderQty = (int)$item['quantity'];
        $stockQty = (int)$item['stock_quantity'];
        $unitPrice = (float)$item['price'];

        // Verify status
        if ($item['status'] !== 'Active') {
            $pdo->rollBack();
            $_SESSION['checkout_error'] = "The remedy '" . htmlspecialchars($productName) . "' is currently unavailable.";
            header("Location: " . BASE_URL . "cart.php");
            exit;
        }

        // Verify stock
        if ($orderQty > $stockQty) {
            $pdo->rollBack();
            $_SESSION['checkout_error'] = "'" . htmlspecialchars($productName) . "' is no longer available in the requested quantity (Only {$stockQty} units remaining).";
            header("Location: " . BASE_URL . "cart.php");
            exit;
        }

        $lineSubtotal = $unitPrice * $orderQty;
        $grandTotal += $lineSubtotal;
    }

    // 3b. Duplicate Order Protection:
    // Prevent double-click, browser refresh, or repeated POST from creating duplicate local orders and double-deducting stock.
    // If an identical pending order was created within the last 2 minutes for this patient, redirect directly to its payment session.
    $dupCheckStmt = $pdo->prepare("
        SELECT `id` 
        FROM `orders` 
        WHERE `user_id` = ? 
          AND `payment_status` = 'Pending' 
          AND `order_status` = 'Pending'
          AND `total_amount` = ? 
          AND `created_at` >= (NOW() - INTERVAL 2 MINUTE)
        ORDER BY `id` DESC LIMIT 1
    ");
    $dupCheckStmt->execute([$userId, $grandTotal]);
    $existingPendingOrder = $dupCheckStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingPendingOrder) {
        $pdo->rollBack();
        header("Location: " . BASE_URL . "payment.php?order_id=" . (int)$existingPendingOrder['id']);
        exit;
    }

    // Online Payment Only
    $paymentMethod = 'Online Payment';

    // 4. Create Order Record in orders table
    $orderStmt = $pdo->prepare("
        INSERT INTO `orders` 
        (`user_id`, `total_amount`, `payment_status`, `order_status`, `payment_method`, `shipping_address`, `created_at`) 
        VALUES (?, ?, 'Pending', 'Pending', ?, ?, NOW())
    ");
    $orderStmt->execute([$userId, $grandTotal, $paymentMethod, $shippingAddress]);
    $orderId = (int)$pdo->lastInsertId();

    // 5. Insert Line Items into order_items & Deduct Stock from products
    $itemStmt = $pdo->prepare("
        INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`) 
        VALUES (?, ?, ?, ?)
    ");
    $stockStmt = $pdo->prepare("
        UPDATE `products` 
        SET `stock_quantity` = `stock_quantity` - ? 
        WHERE `id` = ?
    ");

    foreach ($cartItems as $item) {
        $itemStmt->execute([
            $orderId, 
            (int)$item['product_id'], 
            (int)$item['quantity'], 
            (float)$item['price']
        ]);

        $stockStmt->execute([
            (int)$item['quantity'], 
            (int)$item['product_id']
        ]);
    }

    // Note: Cart items are retained until payment is confirmed server-side (as per business logic rules)

    // 6. Update address in users table if previously empty
    $updateAddrStmt = $pdo->prepare("UPDATE `users` SET `address` = ? WHERE `id` = ? AND (`address` IS NULL OR `address` = '')");
    $updateAddrStmt->execute([$shippingAddress, $userId]);

    // 7. Commit atomic transaction
    $pdo->commit();

    // 8. Redirect directly to secure online payment
    header("Location: " . BASE_URL . "payment.php?order_id=" . $orderId);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Order processing failed: " . $e->getMessage());
    $_SESSION['checkout_error'] = "Order processing failed. Please try again or contact clinic support.";
    header("Location: " . BASE_URL . "checkout.php");
    exit;
}
