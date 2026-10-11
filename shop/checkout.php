<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: Checkout requires logged-in patient
if (!isset($_SESSION['user_id'])) {
    echo "<script>
        alert('Please log in to continue to checkout.');
        window.location.href = '" . BASE_URL . "login.php';
    </script>";
    exit;
}

if (($_SESSION['role'] ?? '') !== 'Patient') {
    header("Location: " . BASE_URL . "products.php");
    exit;
}

$pageTitle = "Checkout | Bodhi Homeopathy Pharmacy";
include __DIR__ . '/../includes/header.php';

$userId = (int)$_SESSION['user_id'];

// Fetch patient contact details from users table
$patient = null;
try {
    $uStmt = $pdo->prepare("SELECT `full_name`, `email`, `phone`, `address` FROM `users` WHERE `id` = ?");
    $uStmt->execute([$userId]);
    $patient = $uStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patient = [];
}

// Fetch real cart items from MySQL
$cartItems = [];
$cartTotal = 0.00;
$hasStockIssue = false;
$hasInactiveIssue = false;

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
        if ((int)$item['quantity'] > (int)$item['stock_quantity']) {
            $hasStockIssue = true;
        }
        if ($item['status'] !== 'Active') {
            $hasInactiveIssue = true;
        }
    }
} catch (Exception $e) {
    $cartItems = [];
}

// Flash messages
$flashError = $_SESSION['checkout_error'] ?? '';
unset($_SESSION['checkout_error']);
?>

<!-- Hero Banner -->
<section class="pro-contact-hero">
    <div class="hero-overlay-dark">
        <div class="container">
            <h1>Checkout</h1>
            <p>Review and confirm your classical remedy prescription order</p>
        </div>
    </div>
</section>

<!-- Main Checkout Section -->
<section class="container" style="margin-top: 40px; margin-bottom: 80px;">

    <!-- Breadcrumb & Back Link -->
    <div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <a href="<?php echo BASE_URL; ?>cart.php" style="color: var(--primary); text-decoration: none; font-size: 13.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fas fa-arrow-left"></i> Return to Cart
        </a>
        <span style="font-size: 13px; color: #718096;">
            Patient: <strong><?php echo htmlspecialchars($patient['full_name'] ?? $_SESSION['full_name'] ?? 'Patient'); ?></strong> (#PT-<?php echo str_pad($userId, 4, '0', STR_PAD_LEFT); ?>)
        </span>
    </div>

    <!-- Flash Error -->
    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger" style="background: #FDE8E8; color: #9B1C1C; padding: 14px 18px; border-radius: 8px; font-size: 13.5px; margin-bottom: 22px; display: flex; align-items: center; gap: 10px; border: 1px solid #F8B4B4;">
            <i class="fas fa-exclamation-circle" style="font-size: 16px;"></i> <?php echo htmlspecialchars($flashError); ?>
        </div>
    <?php endif; ?>

    <?php if ($hasStockIssue): ?>
        <div class="alert alert-danger" style="background: #FDE8E8; color: #9B1C1C; padding: 14px 18px; border-radius: 8px; font-size: 13.5px; margin-bottom: 22px; display: flex; align-items: center; gap: 10px; border: 1px solid #F8B4B4;">
            <i class="fas fa-exclamation-triangle" style="font-size: 16px;"></i> One or more remedies in your cart exceed available clinic stock. Please update your cart before proceeding.
        </div>
    <?php endif; ?>

    <?php if ($hasInactiveIssue): ?>
        <div class="alert alert-danger" style="background: #FDE8E8; color: #9B1C1C; padding: 14px 18px; border-radius: 8px; font-size: 13.5px; margin-bottom: 22px; display: flex; align-items: center; gap: 10px; border: 1px solid #F8B4B4;">
            <i class="fas fa-exclamation-triangle" style="font-size: 16px;"></i> One or more remedies in your cart are currently inactive or unavailable. Please review your cart.
        </div>
    <?php endif; ?>

    <?php if (!empty($cartItems)): ?>
        <form action="<?php echo BASE_URL; ?>place_order.php" method="POST" id="checkoutForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div style="display: grid; grid-template-columns: 1.3fr 1fr; gap: 30px; align-items: start;">
                
                <!-- Left Column: Patient Contact & Delivery Details -->
                <div style="display: flex; flex-direction: column; gap: 25px;">
                    
                    <!-- Patient Contact Card -->
                    <div style="background: #ffffff; border-radius: 12px; padding: 25px; border: 1px solid rgba(79, 111, 82, 0.12); box-shadow: 0 8px 25px rgba(53, 94, 59, 0.05);">
                        <h3 style="color: var(--primary-dark); font-size: 17px; font-weight: 700; margin: 0 0 18px 0; padding-bottom: 10px; border-bottom: 1px solid #EDF2F7; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-user-circle" style="color: var(--primary);"></i> Patient Information
                        </h3>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 600; color: #718096; text-transform: uppercase; margin-bottom: 5px;">Full Name</label>
                                <input type="text" 
                                       value="<?php echo htmlspecialchars($patient['full_name'] ?? ''); ?>" 
                                       readonly 
                                       class="form-control-readonly"
                                       style="width: 100%; background: #FAF9F6; border: 1px solid #E2E8F0; padding: 10px 14px; border-radius: 6px; font-size: 13.5px; color: #2D3748; font-weight: 600;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 600; color: #718096; text-transform: uppercase; margin-bottom: 5px;">Contact Phone</label>
                                <input type="text" 
                                       value="<?php echo htmlspecialchars($patient['phone'] ?? ''); ?>" 
                                       readonly 
                                       class="form-control-readonly"
                                       style="width: 100%; background: #FAF9F6; border: 1px solid #E2E8F0; padding: 10px 14px; border-radius: 6px; font-size: 13.5px; color: #2D3748; font-weight: 600;">
                            </div>
                        </div>

                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #718096; text-transform: uppercase; margin-bottom: 5px;">Email Address</label>
                            <input type="email" 
                                   value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>" 
                                   readonly 
                                   class="form-control-readonly"
                                   style="width: 100%; background: #FAF9F6; border: 1px solid #E2E8F0; padding: 10px 14px; border-radius: 6px; font-size: 13.5px; color: #2D3748; font-weight: 600;">
                        </div>
                    </div>

                    <!-- Shipping Address Card -->
                    <div style="background: #ffffff; border-radius: 12px; padding: 25px; border: 1px solid rgba(79, 111, 82, 0.12); box-shadow: 0 8px 25px rgba(53, 94, 59, 0.05);">
                        <h3 style="color: var(--primary-dark); font-size: 17px; font-weight: 700; margin: 0 0 18px 0; padding-bottom: 10px; border-bottom: 1px solid #EDF2F7; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-truck" style="color: var(--primary);"></i> Delivery & Shipping Address
                        </h3>

                        <div>
                            <label for="shippingAddress" style="display: block; font-size: 13px; font-weight: 600; color: var(--primary-dark); margin-bottom: 8px;">
                                Postal Delivery Address <span style="color: #E53E3E;">*</span>
                            </label>
                            <textarea id="shippingAddress" 
                                      name="shipping_address" 
                                      rows="4" 
                                      required 
                                      placeholder="Enter complete house number, street, landmark, city, state, and pincode for medicine dispatch"
                                      style="width: 100%; border: 1px solid #CBD5E0; padding: 12px 14px; border-radius: 8px; font-size: 13.5px; font-family: inherit; line-height: 1.5; outline: none; transition: border 0.2s ease;"
                                      onfocus="this.style.borderColor='var(--primary)';"
                                      onblur="this.style.borderColor='#CBD5E0';"><?php echo htmlspecialchars($patient['address'] ?? ''); ?></textarea>
                            <span style="display: block; font-size: 11.5px; color: #718096; margin-top: 6px;">
                                Remedies are securely packed in moisture-proof containers for dispatch.
                            </span>
                        </div>
                    </div>

                    <!-- Hidden Online Payment Method Definition -->
                    <input type="hidden" name="payment_method" value="Online Payment">

                </div>

                <!-- Right Column: Order Summary Card -->
                <div style="background: #ffffff; border-radius: 12px; padding: 25px; border: 1px solid rgba(79, 111, 82, 0.12); box-shadow: 0 8px 25px rgba(53, 94, 59, 0.05); position: sticky; top: 90px;">
                    <h3 style="color: var(--primary-dark); font-size: 17px; font-weight: 700; margin: 0 0 16px 0; padding-bottom: 12px; border-bottom: 1px solid #EDF2F7; display: flex; justify-content: space-between; align-items: center;">
                        <span>Order Summary</span>
                        <span style="font-size: 12.5px; color: #718096; font-weight: normal;"><?php echo count($cartItems); ?> Items</span>
                    </h3>

                    <!-- Order Line Items -->
                    <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 20px; max-height: 280px; overflow-y: auto; padding-right: 5px;">
                        <?php foreach ($cartItems as $item): ?>
                            <?php 
                                $qty = (int)$item['quantity'];
                                $price = (float)$item['price'];
                                $lineTotal = $price * $qty;
                                $imgFilename = !empty($item['image_url']) ? $item['image_url'] : 'a1.png';
                            ?>
                            <div class="checkout-item-row" style="display: flex; justify-content: space-between; align-items: center; gap: 12px; padding-bottom: 12px; border-bottom: 1px solid #F0F4F1;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 42px; height: 42px; background: #FAF9F6; border-radius: 6px; border: 1px solid #E2E8F0; display: flex; align-items: center; justify-content: center; padding: 3px; overflow: hidden; flex-shrink: 0;">
                                        <img src="<?php echo BASE_URL . 'assets/images/' . htmlspecialchars($imgFilename); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                             style="max-width: 100%; max-height: 100%; object-fit: contain;"
                                             onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/images/a1.png';">
                                    </div>
                                    <div>
                                        <h5 class="checkout-product-name" style="margin: 0 0 2px 0; font-size: 13.5px; font-weight: 700; color: var(--primary-dark);">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </h5>
                                        <span style="font-size: 12px; color: #718096;">
                                            Qty: <strong class="checkout-item-qty"><?php echo $qty; ?></strong> &times; ₹<?php echo number_format($price, 2); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="checkout-item-subtotal" style="font-size: 13.5px; font-weight: 700; color: #2D3748; white-space: nowrap;">
                                    ₹<?php echo number_format($lineTotal, 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Financial Breakdown -->
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13.5px; color: #4A5568;">
                        <span>Remedies Subtotal</span>
                        <strong class="checkout-subtotal" style="color: #2D3748;">₹<?php echo number_format($cartTotal, 2); ?></strong>
                    </div>

                    <div style="display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 13.5px; color: #4A5568;">
                        <span>Clinic Packaging & Prep</span>
                        <span style="color: #03543F; font-weight: 600;">FREE</span>
                    </div>

                    <div style="display: flex; justify-content: space-between; padding-top: 14px; border-top: 2px dashed #E2E8F0; margin-bottom: 22px; font-size: 16px; color: var(--primary-dark);">
                        <strong>Total Amount</strong>
                        <strong class="checkout-grand-total" style="color: var(--secondary); font-size: 20px;">₹<?php echo number_format($cartTotal, 2); ?></strong>
                    </div>

                    <!-- Place Order Button -->
                    <?php if ($hasStockIssue || $hasInactiveIssue): ?>
                        <button type="button" 
                                disabled 
                                style="width: 100%; background: #CBD5E0; color: #718096; border: none; padding: 14px; border-radius: 30px; font-size: 14px; font-weight: 700; cursor: not-allowed; text-align: center;">
                            <i class="fas fa-ban"></i> Cannot Proceed (Stock/Status Issue)
                        </button>
                    <?php else: ?>
                        <button type="submit" 
                                id="btnPlaceOrder"
                                class="btn-book"
                                style="width: 100%; padding: 14px; border-radius: 30px; font-size: 14px; font-weight: 700; background: var(--primary); cursor: pointer; border: none; display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fas fa-check-circle"></i> Place Order
                        </button>
                    <?php endif; ?>

                    <div style="margin-top: 18px; text-align: center;">
                        <a href="<?php echo BASE_URL; ?>products.php" style="color: #718096; font-size: 12.5px; text-decoration: underline;">
                            Continue Shopping
                        </a>
                    </div>

                </div>

            </div>
        </form>

    <?php else: ?>
        <!-- Empty Cart State for Checkout -->
        <div style="text-align: center; padding: 60px 20px; background: #ffffff; border-radius: 14px; max-width: 600px; margin: 30px auto; border: 1px solid rgba(79, 111, 82, 0.12); box-shadow: 0 10px 35px rgba(53, 94, 59, 0.05);">
            <div style="width: 75px; height: 75px; border-radius: 50%; background: #EEF6EF; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px;">
                <i class="fas fa-shopping-bag"></i>
            </div>
            <h2 style="color: var(--primary-dark); font-size: 24px; font-weight: 700; margin-bottom: 10px;">Your Cart is Empty</h2>
            <p style="color: #718096; font-size: 14px; line-height: 1.6; margin-bottom: 25px;">
                You don't have any homeopathic products ready for checkout. Please browse our pharmacy to select classical remedies.
            </p>
            <a href="<?php echo BASE_URL; ?>products.php" class="btn-book" style="padding: 12px 28px; font-size: 13.5px; border-radius: 30px; text-decoration: none;">
                Browse Pharmacy Catalog &rarr;
            </a>
        </div>
    <?php endif; ?>

</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function() {
            var btn = document.getElementById('btnPlaceOrder');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Securing Order...';
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
