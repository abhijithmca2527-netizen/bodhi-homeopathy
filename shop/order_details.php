<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: Must be logged-in patient
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'Patient') {
    header("Location: " . BASE_URL . "products.php");
    exit;
}

$pageTitle = "Order Tracking & Details | Bodhi Homeopathy Pharmacy";
include __DIR__ . '/../includes/header.php';

$userId = (int)$_SESSION['user_id'];
$rawOrderId = $_GET['order_id'] ?? ($_GET['id'] ?? '');
$orderId = filter_var($rawOrderId, FILTER_VALIDATE_INT);
$order = null;
$orderItems = [];

if ($orderId !== false && $orderId > 0) {
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
            // Query historical order items
            $itemStmt = $pdo->prepare("
                SELECT oi.*, p.`name` AS `product_name`, p.`image_url`, p.`price` AS `current_product_price`
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

// Tracking step status helper
function get_tracking_stage(string $orderStatus, string $paymentStatus): int {
    $st = strtolower(trim($orderStatus));
    $pst = strtolower(trim($paymentStatus));

    if ($st === 'cancelled') return -1;
    if ($st === 'delivered') return 5;
    if ($st === 'shipped') return 4;
    if ($st === 'processing') return 3;
    if ($st === 'confirmed' || $pst === 'paid') return 2;
    return 1; // Pending / Order Placed
}

$currentStage = $order ? get_tracking_stage($order['order_status'], $order['payment_status']) : 0;
?>

<!-- Hero Banner -->
<section class="pro-contact-hero">
    <div class="hero-overlay-dark">
        <div class="container">
            <h1>Order Tracking & Details</h1>
            <p>Live progress tracking and itemized invoice details</p>
        </div>
    </div>
</section>

<!-- Main Order Details Section -->
<section class="container" style="margin-top: 40px; margin-bottom: 80px;">

    <!-- Top Navigation Links -->
    <div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <a href="<?php echo BASE_URL; ?>my_orders.php" style="color: var(--primary); text-decoration: none; font-size: 13.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fas fa-arrow-left"></i> Back to My Orders
        </a>
        <?php if ($order): ?>
            <span style="font-size: 13px; color: #718096;">
                Order Reference: <strong class="order-id-val" style="color: var(--primary-dark);">#ORD-<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></strong>
            </span>
        <?php endif; ?>
    </div>

    <?php if ($order): ?>
        <div style="display: flex; flex-direction: column; gap: 30px;">

            <!-- 1. Visual Order Tracking Timeline Card -->
            <div style="background: #ffffff; border-radius: 14px; padding: 30px; border: 1px solid rgba(79, 111, 82, 0.15); box-shadow: 0 10px 30px rgba(53, 94, 59, 0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #EDF2F7;">
                    <div>
                        <h3 style="color: var(--primary-dark); font-size: 18px; font-weight: 700; margin: 0 0 4px 0; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-shipping-fast" style="color: var(--primary);"></i> Order Fulfillment Status
                        </h3>
                        <span style="font-size: 13px; color: #718096;">
                            Current State: <strong class="order-status-val" style="color: var(--primary-dark);"><?php echo htmlspecialchars($order['order_status']); ?></strong>
                        </span>
                    </div>
                    <div>
                        <?php if ($order['order_status'] === 'Delivered'): ?>
                            <span style="background: #DEF7EC; color: #03543F; padding: 6px 14px; border-radius: 20px; font-size: 12.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fas fa-check-circle"></i> Package Delivered
                            </span>
                        <?php elseif ($order['order_status'] === 'Cancelled'): ?>
                            <span style="background: #FDE8E8; color: #9B1C1C; padding: 6px 14px; border-radius: 20px; font-size: 12.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fas fa-times-circle"></i> Order Cancelled
                            </span>
                        <?php else: ?>
                            <span style="background: #EEF6EF; color: #2D3748; padding: 6px 14px; border-radius: 20px; font-size: 12.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fas fa-clock" style="color: var(--primary);"></i> In Progress
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($order['order_status'] === 'Cancelled'): ?>
                    <!-- Cancelled Notice -->
                    <div style="background: #FAF5F5; border: 1px solid #FEB2B2; border-radius: 10px; padding: 20px; text-align: center;">
                        <i class="fas fa-ban" style="color: #E53E3E; font-size: 32px; margin-bottom: 10px;"></i>
                        <h4 style="color: #9B1C1C; margin: 0 0 6px 0; font-size: 16px;">This order has been cancelled</h4>
                        <p style="color: #718096; font-size: 13px; margin: 0;">If you have any questions regarding cancellation, please contact our clinic support.</p>
                    </div>
                <?php else: ?>
                    <!-- Standard 5-Step Order Stepper -->
                    <div class="order-timeline-stepper" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; position: relative; margin-top: 10px;">
                        
                        <!-- Step 1: Order Placed -->
                        <?php $s1Done = ($currentStage >= 1); ?>
                        <div class="timeline-step" style="text-align: center; position: relative;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 700; background: <?php echo $s1Done ? '#03543F' : '#E2E8F0'; ?>; color: <?php echo $s1Done ? '#ffffff' : '#718096'; ?>; box-shadow: <?php echo $s1Done ? '0 0 0 4px #DEF7EC' : 'none'; ?>;">
                                <i class="fas <?php echo $s1Done ? 'fa-check' : 'fa-clipboard-check'; ?>"></i>
                            </div>
                            <strong style="display: block; font-size: 13px; color: <?php echo $s1Done ? 'var(--primary-dark)' : '#718096'; ?>;">Order Placed</strong>
                            <span style="font-size: 11px; color: #718096;"><?php echo date('d M, h:i A', strtotime($order['created_at'])); ?></span>
                        </div>

                        <!-- Step 2: Confirmed -->
                        <?php $s2Done = ($currentStage >= 2); ?>
                        <div class="timeline-step" style="text-align: center; position: relative;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 700; background: <?php echo $s2Done ? '#03543F' : '#FAF9F6'; ?>; color: <?php echo $s2Done ? '#ffffff' : '#A0AEC0'; ?>; border: <?php echo $s2Done ? 'none' : '2px solid #CBD5E0'; ?>; box-shadow: <?php echo $s2Done ? '0 0 0 4px #DEF7EC' : 'none'; ?>;">
                                <i class="fas <?php echo $s2Done ? 'fa-check' : 'fa-receipt'; ?>"></i>
                            </div>
                            <strong style="display: block; font-size: 13px; color: <?php echo $s2Done ? 'var(--primary-dark)' : '#718096'; ?>;">Confirmed</strong>
                            <span style="font-size: 11px; color: #718096;"><?php echo $s2Done ? 'Prescription Verified' : 'Pending Verification'; ?></span>
                        </div>

                        <!-- Step 3: Processing -->
                        <?php $s3Done = ($currentStage >= 3); ?>
                        <div class="timeline-step" style="text-align: center; position: relative;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 700; background: <?php echo $s3Done ? '#03543F' : '#FAF9F6'; ?>; color: <?php echo $s3Done ? '#ffffff' : '#A0AEC0'; ?>; border: <?php echo $s3Done ? 'none' : '2px solid #CBD5E0'; ?>; box-shadow: <?php echo $s3Done ? '0 0 0 4px #DEF7EC' : 'none'; ?>;">
                                <i class="fas <?php echo $s3Done ? 'fa-check' : 'fa-mortar-pestle'; ?>"></i>
                            </div>
                            <strong style="display: block; font-size: 13px; color: <?php echo $s3Done ? 'var(--primary-dark)' : '#718096'; ?>;">Processing</strong>
                            <span style="font-size: 11px; color: #718096;"><?php echo $s3Done ? 'Dispensed & Packed' : 'Pharmacy Prep'; ?></span>
                        </div>

                        <!-- Step 4: Shipped -->
                        <?php $s4Done = ($currentStage >= 4); ?>
                        <div class="timeline-step" style="text-align: center; position: relative;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 700; background: <?php echo $s4Done ? '#03543F' : '#FAF9F6'; ?>; color: <?php echo $s4Done ? '#ffffff' : '#A0AEC0'; ?>; border: <?php echo $s4Done ? 'none' : '2px solid #CBD5E0'; ?>; box-shadow: <?php echo $s4Done ? '0 0 0 4px #DEF7EC' : 'none'; ?>;">
                                <i class="fas <?php echo $s4Done ? 'fa-check' : 'fa-truck'; ?>"></i>
                            </div>
                            <strong style="display: block; font-size: 13px; color: <?php echo $s4Done ? 'var(--primary-dark)' : '#718096'; ?>;">Shipped</strong>
                            <span style="font-size: 11px; color: #718096;"><?php echo $s4Done ? 'In Transit / Dispatched' : 'Pending Dispatch'; ?></span>
                        </div>

                        <!-- Step 5: Delivered -->
                        <?php $s5Done = ($currentStage >= 5); ?>
                        <div class="timeline-step" style="text-align: center; position: relative;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 700; background: <?php echo $s5Done ? '#03543F' : '#FAF9F6'; ?>; color: <?php echo $s5Done ? '#ffffff' : '#A0AEC0'; ?>; border: <?php echo $s5Done ? 'none' : '2px solid #CBD5E0'; ?>; box-shadow: <?php echo $s5Done ? '0 0 0 4px #DEF7EC' : 'none'; ?>;">
                                <i class="fas <?php echo $s5Done ? 'fa-check' : 'fa-home'; ?>"></i>
                            </div>
                            <strong style="display: block; font-size: 13px; color: <?php echo $s5Done ? 'var(--primary-dark)' : '#718096'; ?>;">Delivered</strong>
                            <span style="font-size: 11px; color: #718096;"><?php echo $s5Done ? 'Received by Patient' : 'Awaiting Delivery'; ?></span>
                        </div>

                    </div>
                <?php endif; ?>
            </div>

            <!-- 2. Two-Column Layout: Items Table (Left) + Invoice Summary & Address (Right) -->
            <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 30px; align-items: start;">
                
                <!-- Left Column: Ordered Remedies -->
                <div style="background: #ffffff; border-radius: 14px; padding: 30px; border: 1px solid rgba(79, 111, 82, 0.15); box-shadow: 0 10px 30px rgba(53, 94, 59, 0.05);">
                    <h3 style="color: var(--primary-dark); font-size: 17px; font-weight: 700; margin: 0 0 18px 0; padding-bottom: 12px; border-bottom: 1px solid #EDF2F7; display: flex; justify-content: space-between; align-items: center;">
                        <span><i class="fas fa-prescription-bottle-alt" style="color: var(--primary);"></i> Prescribed Items</span>
                        <span style="font-size: 12.5px; color: #718096; font-weight: normal;"><?php echo count($orderItems); ?> Line Items</span>
                    </h3>

                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
                            <thead>
                                <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; text-align: left;">
                                    <th style="padding: 12px 14px; color: #718096;">Product</th>
                                    <th style="padding: 12px 14px; color: #718096; text-align: center;">Qty</th>
                                    <th style="padding: 12px 14px; color: #718096; text-align: right;">Unit Price</th>
                                    <th style="padding: 12px 14px; color: #718096; text-align: right;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $calculatedSubtotal = 0.00;
                                foreach ($orderItems as $item): 
                                    $qty = (int)$item['quantity'];
                                    $historicalPrice = (float)$item['price']; // Stored order item price
                                    $lineSubtotal = $historicalPrice * $qty;
                                    $calculatedSubtotal += $lineSubtotal;
                                    $imgFile = !empty($item['image_url']) ? $item['image_url'] : 'a1.png';
                                ?>
                                    <tr style="border-bottom: 1px solid #EDF2F7;">
                                        <td style="padding: 14px 14px;">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="width: 44px; height: 44px; background: #FAF9F6; border-radius: 6px; border: 1px solid #E2E8F0; display: flex; align-items: center; justify-content: center; padding: 3px; overflow: hidden; flex-shrink: 0;">
                                                    <img src="<?php echo BASE_URL . 'assets/images/' . htmlspecialchars($imgFile); ?>" 
                                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                                         style="max-width: 100%; max-height: 100%; object-fit: contain;"
                                                         onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/images/a1.png';">
                                                </div>
                                                <div>
                                                    <strong class="item-product-name" style="color: var(--primary-dark); font-size: 13.5px; display: block;">
                                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                                    </strong>
                                                    <span style="font-size: 11.5px; color: #718096;">
                                                        Classical Remedy
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="item-quantity-val" style="padding: 14px 14px; text-align: center; color: #2D3748; font-weight: 600;">
                                            <?php echo $qty; ?>
                                        </td>
                                        <td class="item-unit-price-val" style="padding: 14px 14px; text-align: right; color: #718096;">
                                            ₹<?php echo number_format($historicalPrice, 2); ?>
                                        </td>
                                        <td class="item-subtotal-val" style="padding: 14px 14px; text-align: right; font-weight: 700; color: #2D3748;">
                                            ₹<?php echo number_format($lineSubtotal, 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 20px; padding: 12px 16px; background: #FAF9F6; border-radius: 8px; font-size: 12px; color: #718096; display: flex; align-items: center; gap: 8px; border: 1px solid #E2E8F0;">
                        <i class="fas fa-shield-alt" style="color: var(--primary); font-size: 14px;"></i>
                        <span>Historical order invoices preserve the exact remedy prices active at the moment of order placement.</span>
                    </div>
                </div>

                <!-- Right Column: Order Summary, Payment & Delivery Details -->
                <div style="display: flex; flex-direction: column; gap: 25px;">
                    
                    <!-- Financial Summary Box -->
                    <div style="background: #ffffff; border-radius: 14px; padding: 25px; border: 1px solid rgba(79, 111, 82, 0.15); box-shadow: 0 10px 30px rgba(53, 94, 59, 0.05);">
                        <h3 style="color: var(--primary-dark); font-size: 17px; font-weight: 700; margin: 0 0 16px 0; padding-bottom: 10px; border-bottom: 1px solid #EDF2F7;">
                            Financial Summary
                        </h3>

                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13.5px; color: #4A5568;">
                            <span>Remedies Subtotal</span>
                            <strong style="color: #2D3748;">₹<?php echo number_format($calculatedSubtotal, 2); ?></strong>
                        </div>

                        <div style="display: flex; justify-content: space-between; margin-bottom: 14px; font-size: 13.5px; color: #4A5568;">
                            <span>Packaging & Clinic Handling</span>
                            <span style="color: #03543F; font-weight: 600;">FREE</span>
                        </div>

                        <div style="display: flex; justify-content: space-between; padding-top: 12px; border-top: 2px dashed #E2E8F0; margin-bottom: 18px; font-size: 16px; color: var(--primary-dark);">
                            <strong>Grand Total Amount</strong>
                            <strong class="order-total-val" style="color: var(--secondary); font-size: 20px;">₹<?php echo number_format($order['total_amount'], 2); ?></strong>
                        </div>

                        <!-- Payment Metadata Section -->
                        <div style="background: #FAF9F6; border-radius: 8px; padding: 14px; border: 1px solid #E2E8F0; margin-bottom: 18px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 12.5px;">
                                <span style="color: #718096;">Payment Status:</span>
                                <?php if ($order['payment_status'] === 'Paid'): ?>
                                    <span class="payment-status-val" style="background: #DEF7EC; color: #03543F; padding: 3px 10px; border-radius: 12px; font-weight: 700; font-size: 11.5px;">
                                        <i class="fas fa-check-circle"></i> Paid
                                    </span>
                                <?php elseif ($order['payment_status'] === 'Failed'): ?>
                                    <span class="payment-status-val" style="background: #FDE8E8; color: #9B1C1C; padding: 3px 10px; border-radius: 12px; font-weight: 700; font-size: 11.5px;">
                                        <i class="fas fa-times-circle"></i> Failed
                                    </span>
                                <?php else: ?>
                                    <span class="payment-status-val" style="background: #FEF3C7; color: #92400E; padding: 3px 10px; border-radius: 12px; font-weight: 700; font-size: 11.5px;">
                                        <i class="fas fa-clock"></i> Pending
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 12.5px;">
                                <span style="color: #718096;">Payment Method:</span>
                                <strong style="color: #2D3748; font-size: 12px;"><?php echo htmlspecialchars($order['payment_method']); ?></strong>
                            </div>

                            <?php if (!empty($order['cashfree_order_id'])): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; padding-top: 6px; border-top: 1px dashed #E2E8F0;">
                                    <span style="color: #718096;">Gateway Ref:</span>
                                    <code style="background: #EDF2F7; padding: 2px 6px; border-radius: 4px; font-size: 11px; color: #4A5568;"><?php echo htmlspecialchars($order['cashfree_order_id']); ?></code>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Action Buttons & Order Cancellation Section -->
                        <?php if ($order['payment_status'] !== 'Paid' && $order['payment_method'] === 'Cashfree' && $order['order_status'] !== 'Cancelled'): ?>
                            <a href="<?php echo BASE_URL; ?>payment.php?order_id=<?php echo $order['id']; ?>" class="btn-book" style="width: 100%; display: block; text-align: center; padding: 12px; border-radius: 30px; font-size: 13.5px; font-weight: 700; background: var(--primary); text-decoration: none; margin-bottom: 10px;">
                                <i class="fas fa-credit-card"></i> Complete / Retry Payment
                            </a>
                        <?php endif; ?>

                        <?php 
                            $cancStatus = $order['cancellation_status'] ?? 'None';
                            $refStatus = $order['refund_status'] ?? 'None';
                            $canCancel = !in_array($order['order_status'], ['Shipped', 'Delivered', 'Cancelled']) && $cancStatus === 'None';
                        ?>

                        <?php if ($canCancel): ?>
                            <div style="margin-bottom: 12px; margin-top: 10px;">
                                <button type="button" onclick="document.getElementById('cancel_order_form').style.display = (document.getElementById('cancel_order_form').style.display === 'none' ? 'block' : 'none');" style="width: 100%; background: #FFF5F5; color: #C53030; border: 1px solid #FEB2B2; padding: 10px; border-radius: 30px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                    <i class="fas fa-times-circle"></i> Request Order Cancellation
                                </button>
                                
                                <form id="cancel_order_form" action="<?php echo BASE_URL; ?>actions/request_cancellation.php" method="POST" style="display: none; margin-top: 10px; background: #FAF9F6; border: 1px solid #E2E8F0; padding: 14px; border-radius: 8px;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                    <label for="cancel_reason" style="font-size: 12px; font-weight: 600; color: #4A5568; display: block; margin-bottom: 4px;">Reason for Cancellation (Optional):</label>
                                    <textarea id="cancel_reason" name="reason" rows="2" placeholder="Tell us why you wish to cancel..." style="width: 100%; padding: 8px; border: 1px solid #CBD5E0; border-radius: 6px; font-size: 12px; margin-bottom: 8px;"></textarea>
                                    <button type="submit" style="width: 100%; background: #E53E3E; color: #fff; border: none; padding: 9px; border-radius: 6px; font-size: 12.5px; font-weight: 600; cursor: pointer;">
                                        Confirm Cancellation Request
                                    </button>
                                </form>
                            </div>
                        <?php elseif ($cancStatus === 'Requested'): ?>
                            <div style="margin-bottom: 12px; background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; padding: 10px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-clock"></i>
                                <span>Cancellation Requested — Pending Admin Approval</span>
                            </div>
                        <?php elseif ($cancStatus === 'Approved'): ?>
                            <div style="margin-bottom: 12px; background: #DEF7EC; color: #03543F; border: 1px solid #BCF0DA; padding: 10px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-check-circle"></i>
                                <span>
                                    <?php if ($refStatus === 'Refunded'): ?>
                                        Cancelled & Refunded. Your refund has been processed.
                                    <?php elseif ($refStatus === 'Pending'): ?>
                                        Cancellation Approved — Refund Processing. Your refund has been initiated to the original payment method.
                                    <?php else: ?>
                                        Order Cancelled Successfully.
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <a href="<?php echo BASE_URL; ?>products.php" style="display: block; text-align: center; padding: 10px; border: 1px solid #CBD5E0; border-radius: 30px; color: #4A5568; font-size: 13px; font-weight: 600; text-decoration: none;">
                            Browse More Products
                        </a>
                    </div>

                    <!-- Delivery Details Card -->
                    <div style="background: #ffffff; border-radius: 14px; padding: 25px; border: 1px solid rgba(79, 111, 82, 0.15); box-shadow: 0 10px 30px rgba(53, 94, 59, 0.05);">
                        <h3 style="color: var(--primary-dark); font-size: 16px; font-weight: 700; margin: 0 0 14px 0; padding-bottom: 8px; border-bottom: 1px solid #EDF2F7; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-map-marker-alt" style="color: var(--primary);"></i> Shipping Destination
                        </h3>

                        <div style="font-size: 13px; line-height: 1.6; color: #4A5568;">
                            <strong style="color: var(--primary-dark); display: block; margin-bottom: 4px;"><?php echo htmlspecialchars($order['full_name']); ?></strong>
                            <p class="shipping-address-val" style="margin: 0 0 8px 0; color: #4A5568;">
                                <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                            </p>
                            <span style="font-size: 12px; color: #718096; display: block;">
                                Contact: <?php echo htmlspecialchars($order['phone']); ?> | <?php echo htmlspecialchars($order['email']); ?>
                            </span>
                        </div>
                    </div>

                </div>

            </div>

        </div>

    <?php else: ?>
        <!-- Order Not Found or Access Denied Card (IDOR Protected) -->
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
