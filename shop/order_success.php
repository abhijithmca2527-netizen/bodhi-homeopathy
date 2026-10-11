<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: Must be logged-in patient
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Order Confirmation | Bodhi Homeopathy Pharmacy";
include __DIR__ . '/../includes/header.php';

$userId = (int)$_SESSION['user_id'];
$orderId = filter_var($_GET['order_id'] ?? 0, FILTER_VALIDATE_INT);
$order = null;
$orderItems = [];

if ($orderId > 0) {
    try {
        // Query order with strict user ownership validation
        $stmt = $pdo->prepare("
            SELECT o.*, u.`full_name`, u.`email`, u.`phone`
            FROM `orders` o
            JOIN `users` u ON u.`id` = o.`user_id`
            WHERE o.`id` = ? AND o.`user_id` = ?
        ");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            if ($order['payment_status'] === 'Paid') {
                $clearCartStmt = $pdo->prepare("DELETE FROM `cart_items` WHERE `user_id` = ?");
                $clearCartStmt->execute([$userId]);
            }

            // Query order items
            $itemStmt = $pdo->prepare("
                SELECT oi.*, p.`name` AS `product_name`, p.`image_url`
                FROM `order_items` oi
                JOIN `products` p ON p.`id` = oi.`product_id`
                WHERE oi.`order_id` = ?
                ORDER BY oi.`id` ASC
            ");
            $itemStmt->execute([$orderId]);
            $orderItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $order = null;
    }
}
?>

<!-- Hero Banner -->
<section class="pro-contact-hero">
    <div class="hero-overlay-dark">
        <div class="container">
            <h1>Order Confirmation</h1>
            <p>Your classical homeopathy pharmacy order details</p>
        </div>
    </div>
</section>

<!-- Main Success Section -->
<section class="container" style="margin-top: 40px; margin-bottom: 80px;">

    <?php if ($order): ?>
        <div style="max-width: 800px; margin: 0 auto; background: #ffffff; border-radius: 14px; padding: 40px; border: 1px solid rgba(79, 111, 82, 0.15); box-shadow: 0 10px 35px rgba(53, 94, 59, 0.06);">
            
            <!-- Success Header -->
            <div style="text-align: center; margin-bottom: 35px;">
                <div style="width: 75px; height: 75px; border-radius: 50%; background: #DEF7EC; color: #03543F; display: flex; align-items: center; justify-content: center; font-size: 36px; margin: 0 auto 18px; border: 2px solid #BCF0DA;">
                    <i class="fas fa-check"></i>
                </div>
                <h2 style="color: var(--primary-dark); font-size: 26px; font-weight: 700; margin-bottom: 8px;">
                    <?php echo ($order['payment_status'] === 'Paid') ? 'Payment Successful!' : 'Order Placed Successfully!'; ?>
                </h2>
                <p style="color: #4A5568; font-size: 14.5px; line-height: 1.6; margin: 0;">
                    <?php echo ($order['payment_status'] === 'Paid') 
                        ? 'Thank you for choosing Bodhi Homeopathy. Your online payment has been confirmed and your remedy order is being processed.' 
                        : 'Thank you for choosing Bodhi Homeopathy. Your classical remedy order has been registered under your patient account.'; ?>
                </p>
            </div>

            <!-- Order Metadata Details Box -->
            <div style="background: #FAF9F6; border: 1px solid #E2E8F0; border-radius: 10px; padding: 22px 25px; margin-bottom: 30px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 18px;">
                    <div>
                        <span style="display: block; font-size: 11.5px; text-transform: uppercase; color: #718096; font-weight: 600; margin-bottom: 4px;">Order Number</span>
                        <strong class="order-id-display" style="font-size: 15px; color: var(--primary-dark);">#ORD-<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></strong>
                    </div>
                    <div>
                        <span style="display: block; font-size: 11.5px; text-transform: uppercase; color: #718096; font-weight: 600; margin-bottom: 4px;">Order Date</span>
                        <strong class="order-date-display" style="font-size: 13.5px; color: #2D3748;"><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></strong>
                    </div>
                    <div>
                        <span style="display: block; font-size: 11.5px; text-transform: uppercase; color: #718096; font-weight: 600; margin-bottom: 4px;">Payment Status</span>
                        <?php if ($order['payment_status'] === 'Paid'): ?>
                            <span class="payment-status-display" style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 15px; font-size: 12px; font-weight: 700; background: #DEF7EC; color: #03543F;">
                                <i class="fas fa-check-circle"></i> Paid
                            </span>
                        <?php elseif ($order['payment_status'] === 'Failed'): ?>
                            <span class="payment-status-display" style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 15px; font-size: 12px; font-weight: 700; background: #FDE8E8; color: #9B1C1C;">
                                <i class="fas fa-times-circle"></i> Failed
                            </span>
                        <?php else: ?>
                            <span class="payment-status-display" style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 15px; font-size: 12px; font-weight: 700; background: #FEF3C7; color: #92400E;">
                                <i class="fas fa-clock"></i> Pending
                            </span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span style="display: block; font-size: 11.5px; text-transform: uppercase; color: #718096; font-weight: 600; margin-bottom: 4px;">Payment Mode</span>
                        <span style="font-size: 13px; color: #2D3748; font-weight: 600;"><?php echo htmlspecialchars($order['payment_method']); ?></span>
                    </div>
                </div>

                <?php if ($order['payment_status'] !== 'Paid' && $order['payment_method'] === 'Cashfree'): ?>
                    <div style="margin-top: 16px; padding: 12px 16px; background: #FEF3C7; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <span style="font-size: 12.5px; color: #92400E;">
                            <i class="fas fa-exclamation-circle"></i> Online payment is pending or incomplete for this order.
                        </span>
                        <a href="<?php echo BASE_URL; ?>payment.php?order_id=<?php echo $order['id']; ?>" class="btn-book" style="padding: 6px 16px; font-size: 12px; border-radius: 20px;">
                            <i class="fas fa-credit-card"></i> Complete / Retry Payment
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Shipping Address Section -->
                <div style="margin-top: 18px; padding-top: 16px; border-top: 1px solid #E2E8F0;">
                    <span style="display: block; font-size: 11.5px; text-transform: uppercase; color: #718096; font-weight: 600; margin-bottom: 4px;">Delivery Address</span>
                    <p style="margin: 0; font-size: 13px; color: #4A5568; line-height: 1.5;">
                        <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                    </p>
                </div>
            </div>

            <!-- Ordered Remedies Table -->
            <div style="margin-bottom: 30px;">
                <h4 style="color: var(--primary-dark); font-size: 16px; font-weight: 700; margin: 0 0 14px 0;">Prescription Remedies Ordered</h4>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
                        <thead>
                            <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; text-align: left;">
                                <th style="padding: 10px 14px; color: #718096;">Product</th>
                                <th style="padding: 10px 14px; color: #718096; text-align: center;">Qty</th>
                                <th style="padding: 10px 14px; color: #718096; text-align: right;">Unit Price</th>
                                <th style="padding: 10px 14px; color: #718096; text-align: right;">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <?php $lineSubtotal = (float)$item['price'] * (int)$item['quantity']; ?>
                                <tr style="border-bottom: 1px solid #EDF2F7;">
                                    <td style="padding: 12px 14px; font-weight: 600; color: var(--primary-dark);">
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                    </td>
                                    <td style="padding: 12px 14px; text-align: center; color: #4A5568;">
                                        <?php echo (int)$item['quantity']; ?>
                                    </td>
                                    <td style="padding: 12px 14px; text-align: right; color: #718096;">
                                        ₹<?php echo number_format($item['price'], 2); ?>
                                    </td>
                                    <td style="padding: 12px 14px; text-align: right; font-weight: 600; color: #2D3748;">
                                        ₹<?php echo number_format($lineSubtotal, 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="padding: 16px 14px 6px; text-align: right; font-size: 13.5px; color: #718096;">Packaging & Handling:</td>
                                <td style="padding: 16px 14px 6px; text-align: right; font-weight: 600; color: #03543F;">FREE</td>
                            </tr>
                            <tr>
                                <td colspan="3" style="padding: 8px 14px 12px; text-align: right; font-size: 16px; font-weight: 700; color: var(--primary-dark);">Grand Total:</td>
                                <td style="padding: 8px 14px 12px; text-align: right; font-size: 18px; font-weight: 800; color: var(--secondary);">
                                    <span class="order-total-display">₹<?php echo number_format($order['total_amount'], 2); ?></span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo BASE_URL; ?>order_details.php?order_id=<?php echo $order['id']; ?>" class="btn-book" style="padding: 12px 26px; font-size: 13.5px; border-radius: 30px;">
                    <i class="fas fa-shipping-fast"></i> Track Order Status &rarr;
                </a>
                <a href="<?php echo BASE_URL; ?>my_orders.php" style="padding: 12px 24px; font-size: 13.5px; border-radius: 30px; background: #EEF6EF; color: var(--primary-dark); font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fas fa-shopping-bag"></i> View All Orders
                </a>
                <a href="<?php echo BASE_URL; ?>products.php" style="padding: 12px 24px; font-size: 13.5px; border-radius: 30px; background: #EDF2F7; color: #4A5568; font-weight: 600; text-decoration: none;">
                    Continue Shopping
                </a>
            </div>

        </div>
    <?php else: ?>
        <!-- Order Not Found or Access Denied Card -->
        <div style="text-align: center; padding: 60px 20px; background: #ffffff; border-radius: 14px; max-width: 550px; margin: 30px auto; border: 1px solid rgba(79, 111, 82, 0.12); box-shadow: 0 10px 35px rgba(53, 94, 59, 0.05);">
            <div style="width: 70px; height: 70px; border-radius: 50%; background: #FDE8E8; color: #9B1C1C; display: flex; align-items: center; justify-content: center; font-size: 30px; margin: 0 auto 18px;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2 style="color: var(--primary-dark); font-size: 22px; font-weight: 700; margin-bottom: 8px;">Order Details Not Found</h2>
            <p style="color: #718096; font-size: 14px; line-height: 1.5; margin-bottom: 22px;">
                The requested order reference could not be located or does not belong to your authenticated patient account.
            </p>
            <a href="<?php echo BASE_URL; ?>my_orders.php" class="btn-book" style="padding: 11px 24px; font-size: 13px; border-radius: 30px; text-decoration: none;">
                Go to My Orders
            </a>
        </div>
    <?php endif; ?>

</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
