<?php
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "products.php");
    exit;
}

$action = trim($_POST['action'] ?? '');
$returnUrl = $_POST['return_url'] ?? (BASE_URL . 'cart.php');

// Security check: Must be authenticated
if (!isset($_SESSION['user_id'])) {
    // If guest clicks Add to Cart, redirect to login page with notice
    echo "<script>
        alert('Please log in to add products to your cart.');
        window.location.href = '" . BASE_URL . "login.php';
    </script>";
    exit;
}

// CSRF Security Verification
$csrfToken = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    $_SESSION['cart_error'] = "Invalid security token. Please try again.";
    header("Location: " . BASE_URL . "cart.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

// ==========================================
// 1. ACTION: ADD TO CART
// ==========================================
if ($action === 'add') {
    $productId = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
    $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT);
    if ($quantity < 1) $quantity = 1;

    if (!$productId || $productId <= 0) {
        $_SESSION['cart_error'] = "Invalid product selection.";
        header("Location: " . BASE_URL . "products.php");
        exit;
    }

    try {
        // Query REAL product from database
        $stmt = $pdo->prepare("SELECT `id`, `name`, `price`, `stock_quantity`, `status` FROM `products` WHERE `id` = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product || $product['status'] !== 'Active') {
            $_SESSION['cart_error'] = "The selected remedy is currently unavailable.";
            header("Location: " . BASE_URL . "products.php");
            exit;
        }

        $stock = (int)$product['stock_quantity'];
        if ($stock <= 0) {
            $_SESSION['cart_error'] = htmlspecialchars($product['name']) . " is currently out of stock.";
            header("Location: " . BASE_URL . "products.php");
            exit;
        }

        // Check if already in patient's cart
        $stmt = $pdo->prepare("SELECT `id`, `quantity` FROM `cart_items` WHERE `user_id` = ? AND `product_id` = ?");
        $stmt->execute([$userId, $productId]);
        $existingCart = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingCart) {
            $newQuantity = (int)$existingCart['quantity'] + $quantity;
            if ($newQuantity > $stock) {
                $_SESSION['cart_error'] = "Cannot add more. Only {$stock} units of " . htmlspecialchars($product['name']) . " are available in stock.";
            } else {
                $uStmt = $pdo->prepare("UPDATE `cart_items` SET `quantity` = ? WHERE `id` = ? AND `user_id` = ?");
                $uStmt->execute([$newQuantity, $existingCart['id'], $userId]);
                $_SESSION['cart_success'] = "Updated quantity for " . htmlspecialchars($product['name']) . " in your cart.";
            }
        } else {
            if ($quantity > $stock) {
                $_SESSION['cart_error'] = "Only {$stock} units of " . htmlspecialchars($product['name']) . " are available in stock.";
            } else {
                $iStmt = $pdo->prepare("INSERT INTO `cart_items` (`user_id`, `product_id`, `quantity`) VALUES (?, ?, ?)");
                $iStmt->execute([$userId, $productId, $quantity]);
                $_SESSION['cart_success'] = htmlspecialchars($product['name']) . " added to your cart!";
            }
        }

        header("Location: " . BASE_URL . "cart.php");
        exit;

    } catch (Exception $e) {
        error_log("Cart add error: " . $e->getMessage());
        $_SESSION['cart_error'] = "An error occurred while updating your cart. Please try again.";
        header("Location: " . BASE_URL . "cart.php");
        exit;
    }
}

// ==========================================
// 2. ACTION: UPDATE QUANTITY (DELTA +1 / -1)
// ==========================================
if ($action === 'update_qty') {
    $cartItemId = filter_var($_POST['cart_item_id'] ?? 0, FILTER_VALIDATE_INT);
    $delta = filter_var($_POST['delta'] ?? 0, FILTER_VALIDATE_INT);

    if (!$cartItemId || $cartItemId <= 0 || ($delta !== 1 && $delta !== -1)) {
        $_SESSION['cart_error'] = "Invalid cart update request.";
        header("Location: " . BASE_URL . "cart.php");
        exit;
    }

    try {
        // Query item with strict ownership validation
        $stmt = $pdo->prepare("
            SELECT ci.`id`, ci.`quantity`, p.`name`, p.`stock_quantity`, p.`status`
            FROM `cart_items` ci
            JOIN `products` p ON p.`id` = ci.`product_id`
            WHERE ci.`id` = ? AND ci.`user_id` = ?
        ");
        $stmt->execute([$cartItemId, $userId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            $_SESSION['cart_error'] = "Cart item not found or unauthorized access.";
            header("Location: " . BASE_URL . "cart.php");
            exit;
        }

        $currentQty = (int)$item['quantity'];
        $stock = (int)$item['stock_quantity'];

        if ($delta === 1) {
            // Increase
            if ($currentQty + 1 > $stock) {
                $_SESSION['cart_error'] = "Cannot add more. Only {$stock} units of " . htmlspecialchars($item['name']) . " are available.";
            } else {
                $uStmt = $pdo->prepare("UPDATE `cart_items` SET `quantity` = `quantity` + 1 WHERE `id` = ? AND `user_id` = ?");
                $uStmt->execute([$cartItemId, $userId]);
            }
        } elseif ($delta === -1) {
            // Decrease
            if ($currentQty <= 1) {
                // If quantity reaches 0, remove item
                $dStmt = $pdo->prepare("DELETE FROM `cart_items` WHERE `id` = ? AND `user_id` = ?");
                $dStmt->execute([$cartItemId, $userId]);
                $_SESSION['cart_success'] = "Removed " . htmlspecialchars($item['name']) . " from your cart.";
            } else {
                $uStmt = $pdo->prepare("UPDATE `cart_items` SET `quantity` = `quantity` - 1 WHERE `id` = ? AND `user_id` = ?");
                $uStmt->execute([$cartItemId, $userId]);
            }
        }

        header("Location: " . BASE_URL . "cart.php");
        exit;

    } catch (Exception $e) {
        error_log("Cart update quantity error: " . $e->getMessage());
        $_SESSION['cart_error'] = "An error occurred while updating item quantity. Please try again.";
        header("Location: " . BASE_URL . "cart.php");
        exit;
    }
}

// ==========================================
// 3. ACTION: REMOVE ITEM
// ==========================================
if ($action === 'remove') {
    $cartItemId = filter_var($_POST['cart_item_id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$cartItemId || $cartItemId <= 0) {
        $_SESSION['cart_error'] = "Invalid cart item selection.";
        header("Location: " . BASE_URL . "cart.php");
        exit;
    }

    try {
        // Strict ownership deletion
        $stmt = $pdo->prepare("DELETE FROM `cart_items` WHERE `id` = ? AND `user_id` = ?");
        $stmt->execute([$cartItemId, $userId]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['cart_success'] = "Item removed from your cart.";
        } else {
            $_SESSION['cart_error'] = "Item not found or unauthorized.";
        }

        header("Location: " . BASE_URL . "cart.php");
        exit;

    } catch (Exception $e) {
        error_log("Cart remove item error: " . $e->getMessage());
        $_SESSION['cart_error'] = "An error occurred while removing the item. Please try again.";
        header("Location: " . BASE_URL . "cart.php");
        exit;
    }
}

// Fallback
header("Location: " . BASE_URL . "cart.php");
exit;
