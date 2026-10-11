<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: Cart requires logged-in patient
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Shopping Cart | Bodhi Homeopathy Pharmacy";
include __DIR__ . '/../includes/header.php';

$userId = (int)$_SESSION['user_id'];
$cartItems = [];
$cartTotal = 0.00;

try {
    $stmt = $pdo->prepare("
        SELECT 
            ci.`id` AS `cart_item_id`,
            ci.`quantity`,
            p.`id` AS `product_id`,
            p.`name`,
            p.`price`,
            p.`stock_quantity`,
            p.`image_url`,
            p.`description`,
            p.`status`
        FROM `cart_items` ci
        JOIN `products` p ON p.`id` = ci.`product_id`
        WHERE ci.`user_id` = ?
        ORDER BY ci.`id` ASC
    ");
    $stmt->execute([$userId]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cartItems as $item) {
        $subtotal = (float)$item['price'] * (int)$item['quantity'];
        $cartTotal += $subtotal;
    }
} catch (Exception $e) {
    $cartItems = [];
}

// Flash messages
$flashSuccess = $_SESSION['cart_success'] ?? '';
$flashError = $_SESSION['cart_error'] ?? ($_SESSION['checkout_error'] ?? '');
unset($_SESSION['cart_success'], $_SESSION['cart_error'], $_SESSION['checkout_error']);
?>

<!-- Hero Banner -->
<section class="pro-contact-hero">
    <div class="hero-overlay-dark">
        <div class="container">
            <h1>Pharmacy Cart</h1>
            <p>Review your selected classical remedies & formulations</p>
        </div>
    </div>
</section>

<!-- Main Cart Section -->
<section class="container" style="margin-top: 40px; margin-bottom: 80px;">

    <!-- Breadcrumb & Back Link -->
    <div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <a href="<?php echo BASE_URL; ?>products.php" style="color: var(--primary); text-decoration: none; font-size: 13.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fas fa-arrow-left"></i> Continue Shopping
        </a>
        <span style="font-size: 13px; color: #718096;">
            Patient: <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Patient'); ?></strong> (#PT-<?php echo str_pad($_SESSION['user_id'], 4, '0', STR_PAD_LEFT); ?>)
        </span>
    </div>

    <!-- Flash Alerts -->
    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success" style="background: #DEF7EC; color: #03543F; padding: 14px 18px; border-radius: 8px; font-size: 13.5px; margin-bottom: 22px; display: flex; align-items: center; gap: 10px; border: 1px solid #BCF0DA;">
            <i class="fas fa-check-circle" style="font-size: 16px;"></i> <?php echo $flashSuccess; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger" style="background: #FDE8E8; color: #9B1C1C; padding: 14px 18px; border-radius: 8px; font-size: 13.5px; margin-bottom: 22px; display: flex; align-items: center; gap: 10px; border: 1px solid #F8B4B4;">
            <i class="fas fa-exclamation-circle" style="font-size: 16px;"></i> <?php echo $flashError; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($cartItems)): ?>
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: start;">
            
            <!-- Cart Items List / Table -->
            <div style="background: #ffffff; border-radius: 12px; padding: 25px; border: 1px solid rgba(79, 111, 82, 0.12); box-shadow: 0 8px 25px rgba(53, 94, 59, 0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid #EDF2F7;">
                    <h3 style="color: var(--primary-dark); font-size: 18px; font-weight: 700; margin: 0;">Cart Items (<?php echo count($cartItems); ?>)</h3>
                    <span style="font-size: 12.5px; color: #718096;">Prices in INR (₹)</span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <?php foreach ($cartItems as $item): ?>
                        <?php 
                            $stock = (int)$item['stock_quantity'];
                            $qty = (int)$item['quantity'];
                            $unitPrice = (float)$item['price'];
                            $lineTotal = $unitPrice * $qty;
                            $imgFilename = !empty($item['image_url']) ? $item['image_url'] : 'a1.png';
                            $imgPath = BASE_URL . 'assets/images/' . htmlspecialchars($imgFilename);
                            $stockWarning = $qty > $stock;
                        ?>
                        <div class="cart-item-row" style="display: grid; grid-template-columns: 80px 1fr auto auto; gap: 18px; align-items: center; padding-bottom: 20px; border-bottom: 1px solid #F0F4F1;">
                            
                            <!-- Thumbnail -->
                            <div style="width: 80px; height: 80px; background: #FAF9F6; border-radius: 8px; border: 1px solid #E2E8F0; display: flex; align-items: center; justify-content: center; padding: 6px; overflow: hidden;">
                                <img src="<?php echo $imgPath; ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                     style="max-width: 100%; max-height: 100%; object-fit: contain;"
                                     onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/images/a1.png';">
                            </div>

                            <!-- Product Information -->
                            <div>
                                <h4 style="margin: 0 0 6px 0; font-size: 15px; font-weight: 700; color: var(--primary-dark);">
                                    <a href="<?php echo BASE_URL; ?>product_details.php?id=<?php echo $item['product_id']; ?>" style="color: inherit; text-decoration: none;">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </a>
                                </h4>
                                <div style="font-size: 13px; color: #718096; margin-bottom: 4px;">
                                    Unit Price: <strong style="color: var(--primary);">₹<?php echo number_format($unitPrice, 2); ?></strong>
                                </div>
                                <div>
                                    <?php if ($stockWarning): ?>
                                        <span style="font-size: 11px; color: #9B1C1C; font-weight: 600; background: #FDE8E8; padding: 2px 7px; border-radius: 10px;">
                                            <i class="fas fa-exclamation-triangle"></i> Only <?php echo $stock; ?> in stock
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: #03543F; font-weight: 600; background: #DEF7EC; padding: 2px 7px; border-radius: 10px;">
                                            <i class="fas fa-check"></i> Available (<?php echo $stock; ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Quantity Controls -->
                            <div style="display: flex; align-items: center; border: 1px solid #CBD5E0; border-radius: 6px; overflow: hidden; background: #FAF9F6;">
                                <!-- Minus Button -->
                                <form action="<?php echo BASE_URL; ?>cart_action.php" method="POST" style="margin: 0;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="update_qty">
                                    <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                    <input type="hidden" name="delta" value="-1">
                                    <button type="submit" aria-label="Decrease quantity" style="border: none; background: transparent; padding: 7px 11px; cursor: pointer; color: #4A5568; font-size: 12px;">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </form>

                                <!-- Quantity Display -->
                                <span class="item-qty-value" style="padding: 6px 10px; font-size: 13.5px; font-weight: 700; color: #2D3748; min-width: 28px; text-align: center;">
                                    <?php echo $qty; ?>
                                </span>

                                <!-- Plus Button -->
                                <form action="<?php echo BASE_URL; ?>cart_action.php" method="POST" style="margin: 0;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="update_qty">
                                    <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                    <input type="hidden" name="delta" value="1">
                                    <button type="submit" <?php echo $qty >= $stock ? 'disabled style="border: none; background: transparent; padding: 7px 11px; cursor: not-allowed; color: #CBD5E0; font-size: 12px;"' : 'style="border: none; background: transparent; padding: 7px 11px; cursor: pointer; color: #4A5568; font-size: 12px;"'; ?> aria-label="Increase quantity">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </form>
                            </div>

                            <!-- Line Subtotal & Remove -->
                            <div style="text-align: right;">
                                <div style="font-size: 16px; font-weight: 700; color: var(--secondary); margin-bottom: 6px;">
                                    ₹<?php echo number_format($lineTotal, 2); ?>
                                </div>
                                <form action="<?php echo BASE_URL; ?>cart_action.php" method="POST" style="margin: 0;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                    <button type="submit" class="btn-remove-item" style="border: none; background: transparent; color: #E53E3E; font-size: 12px; cursor: pointer; padding: 0; text-decoration: underline;">
                                        <i class="fas fa-trash-alt"></i> Remove
                                    </button>
                                </form>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

            </div>

            <!-- Order Summary Card -->
            <div style="background: #ffffff; border-radius: 12px; padding: 25px; border: 1px solid rgba(79, 111, 82, 0.12); box-shadow: 0 8px 25px rgba(53, 94, 59, 0.05); position: sticky; top: 90px;">
                <h3 style="color: var(--primary-dark); font-size: 17px; font-weight: 700; margin: 0 0 16px 0; padding-bottom: 12px; border-bottom: 1px solid #EDF2F7;">
                    Order Summary
                </h3>

                <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13.5px; color: #4A5568;">
                    <span>Remedy Subtotal</span>
                    <strong class="cart-subtotal" style="color: #2D3748;">₹<?php echo number_format($cartTotal, 2); ?></strong>
                </div>

                <div style="display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 13.5px; color: #4A5568;">
                    <span>Clinic Packaging & Prep</span>
                    <span style="color: #03543F; font-weight: 600;">FREE</span>
                </div>

                <div style="display: flex; justify-content: space-between; padding-top: 14px; border-top: 2px dashed #E2E8F0; margin-bottom: 22px; font-size: 16px; color: var(--primary-dark);">
                    <strong>Total Amount</strong>
                    <strong class="cart-total" style="color: var(--secondary); font-size: 20px;">₹<?php echo number_format($cartTotal, 2); ?></strong>
                </div>

                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <!-- Proceed to Checkout Button -->
                    <a href="<?php echo BASE_URL; ?>checkout.php" 
                       id="btnProceedCheckout"
                       class="btn-book" 
                       style="width: 100%; display: block; text-align: center; padding: 13px; border-radius: 30px; font-size: 14px; font-weight: 700; background: var(--primary); text-decoration: none;">
                        <i class="fas fa-shopping-bag"></i> Proceed to Checkout &rarr;
                    </a>

                    <a href="<?php echo BASE_URL; ?>products.php" style="display: block; text-align: center; padding: 11px; border: 1px solid #CBD5E0; border-radius: 30px; color: #4A5568; font-size: 13px; font-weight: 600; text-decoration: none;">
                        Browse More Products
                    </a>
                </div>

                <div style="margin-top: 20px; padding: 12px; background: #FAF9F6; border-radius: 6px; font-size: 11.5px; color: #718096; line-height: 1.4; border: 1px solid #E2E8F0;">
                    <i class="fas fa-shield-alt" style="color: var(--primary);"></i> 100% Genuine classical homeopathic remedies prepared under licensed clinical standards.
                </div>
            </div>

        </div>

    <?php else: ?>
        <!-- Empty Cart State -->
        <?php
            $recentOrderNotice = false;
            if (isset($userId) && $userId > 0) {
                try {
                    $rStmt = $pdo->prepare("SELECT `id` FROM `orders` WHERE `user_id` = ? AND `created_at` >= NOW() - INTERVAL 1 HOUR ORDER BY `id` DESC LIMIT 1");
                    $rStmt->execute([$userId]);
                    if ($rStmt->fetch()) {
                        $recentOrderNotice = true;
                    }
                } catch (Exception $e) {}
            }
        ?>
        <div style="text-align: center; padding: 50px 25px; background: #ffffff; border-radius: 14px; max-width: 600px; margin: 30px auto; border: 1px solid rgba(79, 111, 82, 0.12); box-shadow: 0 10px 35px rgba(53, 94, 59, 0.05);">
            <?php if ($recentOrderNotice || (isset($_GET['ordered']) && $_GET['ordered'] == '1')): ?>
                <div style="background: #DEF7EC; color: #03543F; padding: 12px 16px; border-radius: 8px; font-size: 13.5px; margin-bottom: 22px; display: flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid #BCF0DA;">
                    <i class="fas fa-check-circle" style="font-size: 16px;"></i> Your order was placed successfully. Purchased medicines can be viewed in My Orders.
                </div>
            <?php endif; ?>
            <div style="width: 75px; height: 75px; border-radius: 50%; background: #EEF6EF; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px;">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <h2 style="color: var(--primary-dark); font-size: 24px; font-weight: 700; margin-bottom: 8px;">Your Cart is Empty</h2>
            <p style="color: #718096; font-size: 14.5px; line-height: 1.6; margin-bottom: 25px;">
                No medicines are currently waiting for checkout.
            </p>
            <div style="display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo BASE_URL; ?>products.php" class="btn-book" style="padding: 12px 24px; font-size: 13.5px; border-radius: 30px; text-decoration: none;">
                    Browse Pharmacy
                </a>
                <a href="<?php echo BASE_URL; ?>my_orders.php" style="padding: 12px 24px; font-size: 13.5px; border-radius: 30px; background: #EEF6EF; color: var(--primary-dark); font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fas fa-shopping-bag"></i> View My Orders
                </a>
            </div>
        </div>
    <?php endif; ?>

</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
