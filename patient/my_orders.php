<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: require logged-in Patient
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'Patient') {
    header("Location: " . BASE_URL . "products.php");
    exit;
}

$pageTitle = "My Prescription Orders & Tracking | Bodhi Homeopathy";
include __DIR__ . '/../includes/header.php';

$userId = (int)$_SESSION['user_id'];

// Filter parameters (Whitelisted for security)
$validOrderStatuses = ['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
$validPaymentStatuses = ['Paid', 'Pending', 'Failed'];

$selectedOrderStatus = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_SPECIAL_CHARS);
$selectedPaymentStatus = filter_input(INPUT_GET, 'payment', FILTER_SANITIZE_SPECIAL_CHARS);

$query = "SELECT * FROM `orders` WHERE `user_id` = ?";
$params = [$userId];

if (!empty($selectedOrderStatus) && in_array($selectedOrderStatus, $validOrderStatuses, true)) {
    $query .= " AND `order_status` = ?";
    $params[] = $selectedOrderStatus;
}

if (!empty($selectedPaymentStatus) && in_array($selectedPaymentStatus, $validPaymentStatuses, true)) {
    $query .= " AND `payment_status` = ?";
    $params[] = $selectedPaymentStatus;
}

$query .= " ORDER BY `created_at` DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $orders = [];
}
?>

<!-- Hero Banner -->
<section class="pro-contact-hero">
    <div class="hero-overlay-dark">
        <div class="container">
            <h1>My Pharmacy Orders</h1>
            <p>Track shipment progress and review past classical remedy prescriptions</p>
        </div>
    </div>
</section>

<!-- Main Order History Section -->
<section class="container" style="margin-top: 40px; margin-bottom: 80px;">
    
    <div class="description-card" style="padding: 35px; border-radius: 14px; background: #ffffff; box-shadow: 0 10px 30px rgba(53, 94, 59, 0.05); border: 1px solid rgba(79, 111, 82, 0.12);">
        
        <!-- Header & Action Row -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px;">
            <div>
                <h2 class="title-green-left" style="color: var(--primary-dark); font-weight: 700; margin: 0; font-size: 22px;">Order History & Tracking</h2>
                <div class="accent-line-left" style="width: 50px; height: 3px; background: var(--secondary); margin-top: 8px;"></div>
            </div>
            <a href="<?php echo BASE_URL; ?>products.php" class="btn-book" style="padding: 10px 22px; font-size: 13px; border-radius: 30px; text-decoration: none;">
                <i class="fas fa-plus-circle"></i> Browse Pharmacy Catalog
            </a>
        </div>

        <!-- Filter Controls -->
        <div style="background: #FAF9F6; border: 1px solid #E2E8F0; border-radius: 10px; padding: 16px 20px; margin-bottom: 25px;">
            <form method="GET" action="" style="display: flex; flex-wrap: wrap; align-items: center; gap: 14px;">
                
                <div style="display: flex; align-items: center; gap: 8px;">
                    <label for="filterStatus" style="font-size: 12.5px; font-weight: 600; color: #4A5568;">Fulfillment Status:</label>
                    <select id="filterStatus" name="status" onchange="this.form.submit()" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #CBD5E0; font-size: 12.5px; background: #ffffff; color: #2D3748; cursor: pointer;">
                        <option value="">All Fulfillment Statuses</option>
                        <?php foreach ($validOrderStatuses as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo ($selectedOrderStatus === $st) ? 'selected' : ''; ?>>
                                <?php echo $st; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; align-items: center; gap: 8px;">
                    <label for="filterPayment" style="font-size: 12.5px; font-weight: 600; color: #4A5568;">Payment Status:</label>
                    <select id="filterPayment" name="payment" onchange="this.form.submit()" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #CBD5E0; font-size: 12.5px; background: #ffffff; color: #2D3748; cursor: pointer;">
                        <option value="">All Payment States</option>
                        <?php foreach ($validPaymentStatuses as $pst): ?>
                            <option value="<?php echo $pst; ?>" <?php echo ($selectedPaymentStatus === $pst) ? 'selected' : ''; ?>>
                                <?php echo $pst; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!empty($selectedOrderStatus) || !empty($selectedPaymentStatus)): ?>
                    <a href="<?php echo BASE_URL; ?>my_orders.php" style="color: #E53E3E; font-size: 12px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                        <i class="fas fa-times-circle"></i> Clear Filters
                    </a>
                <?php endif; ?>

            </form>
        </div>

        <?php if (empty($orders)): ?>
            <!-- Empty State -->
            <div style="text-align: center; padding: 50px 20px; background: #FAF9F6; border-radius: 10px; border: 1px dashed #CBD5E0;">
                <div style="width: 65px; height: 65px; border-radius: 50%; background: #EEF6EF; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 16px;">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <h4 style="color: var(--primary-dark); font-size: 18px; font-weight: 700; margin-bottom: 6px;">
                    <?php echo (!empty($selectedOrderStatus) || !empty($selectedPaymentStatus)) ? 'No Orders Found' : "You haven't placed any orders yet."; ?>
                </h4>
                <p style="color: #718096; font-size: 13.5px; max-width: 450px; margin: 0 auto 20px; line-height: 1.5;">
                    <?php if (!empty($selectedOrderStatus) || !empty($selectedPaymentStatus)): ?>
                        No prescription orders match your selected filter criteria. Try clearing filters to see all orders.
                    <?php else: ?>
                        Browse our classical homeopathic remedies catalog to place your first prescription order.
                    <?php endif; ?>
                </p>
                <a href="<?php echo BASE_URL; ?>products.php" class="btn-book" style="font-size: 13px; padding: 10px 24px; border-radius: 30px; text-decoration: none;">
                    <i class="fas fa-shopping-basket"></i> Browse Products
                </a>
            </div>
        <?php else: ?>
            <!-- Orders Data Table -->
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
                    <thead>
                        <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; text-align: left;">
                            <th style="padding: 14px 16px; color: #718096; font-weight: 600;">Order ID</th>
                            <th style="padding: 14px 16px; color: #718096; font-weight: 600;">Date Placed</th>
                            <th style="padding: 14px 16px; color: #718096; font-weight: 600;">Total Amount</th>
                            <th style="padding: 14px 16px; color: #718096; font-weight: 600;">Payment Status</th>
                            <th style="padding: 14px 16px; color: #718096; font-weight: 600;">Fulfillment Status</th>
                            <th style="padding: 14px 16px; color: #718096; text-align: right; font-weight: 600;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <tr style="border-bottom: 1px solid #EDF2F7; transition: background 0.15s ease;" onmouseover="this.style.background='#FBFDFB';" onmouseout="this.style.background='transparent';">
                                
                                <td style="padding: 16px 16px; font-weight: 700; color: var(--primary-dark);">
                                    <a href="<?php echo BASE_URL; ?>order_details.php?order_id=<?php echo $o['id']; ?>" style="color: var(--primary-dark); text-decoration: underline;">
                                        #ORD-<?php echo str_pad($o['id'], 4, '0', STR_PAD_LEFT); ?>
                                    </a>
                                </td>

                                <td style="padding: 16px 16px; color: #4A5568;">
                                    <?php echo date('d M Y, h:i A', strtotime($o['created_at'])); ?>
                                </td>

                                <td style="padding: 16px 16px; font-weight: 700; color: var(--secondary); font-size: 14.5px;">
                                    ₹<?php echo number_format($o['total_amount'], 2); ?>
                                </td>

                                <td style="padding: 16px 16px;">
                                    <?php if ($o['payment_status'] === 'Paid'): ?>
                                        <span class="order-payment-status" style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 11.5px; font-weight: 700; background: #DEF7EC; color: #03543F;">
                                            <i class="fas fa-check-circle"></i> Paid
                                        </span>
                                    <?php elseif ($o['payment_status'] === 'Failed'): ?>
                                        <span class="order-payment-status" style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 11.5px; font-weight: 700; background: #FDE8E8; color: #9B1C1C;">
                                            <i class="fas fa-times-circle"></i> Failed
                                        </span>
                                    <?php else: ?>
                                        <span class="order-payment-status" style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 11.5px; font-weight: 700; background: #FEF3C7; color: #92400E;">
                                            <i class="fas fa-clock"></i> Pending
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 16px 16px;">
                                    <span class="order-fulfillment-status" style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11.5px; font-weight: 600; background: #FAF9F6; color: #2D3748; border: 1px solid #E2E8F0;">
                                        <?php echo htmlspecialchars($o['order_status']); ?>
                                    </span>
                                    <?php if (!empty($o['cancellation_status']) && $o['cancellation_status'] === 'Requested'): ?>
                                        <span style="display: block; margin-top: 4px; font-size: 10.5px; color: #92400E; font-weight: 700;">● Cancellation Requested</span>
                                    <?php elseif (!empty($o['cancellation_status']) && $o['cancellation_status'] === 'Approved'): ?>
                                        <?php if (($o['refund_status'] ?? '') === 'Refunded'): ?>
                                            <span style="display: block; margin-top: 4px; font-size: 10.5px; color: #03543F; font-weight: 700;">● Refunded</span>
                                        <?php elseif (($o['refund_status'] ?? '') === 'Pending'): ?>
                                            <span style="display: block; margin-top: 4px; font-size: 10.5px; color: #0369A1; font-weight: 700;">● Refund Processing</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 16px 16px; text-align: right; white-space: nowrap;">
                                    <?php if ($o['payment_status'] === 'Failed' && $o['payment_method'] === 'Cashfree'): ?>
                                        <a href="<?php echo BASE_URL; ?>payment.php?order_id=<?php echo $o['id']; ?>" class="btn-book" style="padding: 5px 14px; font-size: 11.5px; margin-right: 8px; border-radius: 15px; background: #9B1C1C; text-decoration: none;">
                                            <i class="fas fa-redo"></i> Retry
                                        </a>
                                    <?php elseif ($o['payment_status'] === 'Pending' && $o['payment_method'] === 'Cashfree'): ?>
                                        <a href="<?php echo BASE_URL; ?>payment.php?order_id=<?php echo $o['id']; ?>" class="btn-book" style="padding: 5px 14px; font-size: 11.5px; margin-right: 8px; border-radius: 15px; text-decoration: none;">
                                            <i class="fas fa-credit-card"></i> Pay Now
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?php echo BASE_URL; ?>order_details.php?order_id=<?php echo $o['id']; ?>" class="btn-view-order" style="display: inline-flex; align-items: center; gap: 4px; color: var(--primary); font-size: 12.5px; font-weight: 700; text-decoration: none; padding: 5px 10px; border-radius: 6px; background: #EEF6EF;">
                                        <i class="fas fa-eye"></i> View Order
                                    </a>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
