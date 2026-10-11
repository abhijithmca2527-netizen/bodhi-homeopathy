<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: strictly enforce Admin role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Order Details & Fulfillment | Bodhi Admin";

$rawOrderId = $_GET['order_id'] ?? $_GET['id'] ?? '';
$orderId = filter_var($rawOrderId, FILTER_VALIDATE_INT);
$order = null;
$orderItems = [];

$validOrderStatuses = ['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];

// Allowed state transitions map
$allowedTransitions = [
    'Pending' => ['Confirmed', 'Processing', 'Cancelled'],
    'Confirmed' => ['Processing', 'Shipped', 'Cancelled'],
    'Processing' => ['Shipped', 'Delivered', 'Cancelled'],
    'Shipped' => ['Delivered'],
    'Delivered' => [], // Final state
    'Cancelled' => []  // Final state
];

$msg = '';
$msgType = '';

// POST Handler: Update Fulfillment Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Security token expired. Please reload and submit again.";
        $msgType = "error";
    } else {
        $submittedOrderId = filter_var($_POST['order_id'] ?? 0, FILTER_VALIDATE_INT);
        $newStatus = trim($_POST['new_status'] ?? '');

        if ($submittedOrderId > 0 && in_array($newStatus, $validOrderStatuses, true)) {
            try {
                $stmt = $pdo->prepare("SELECT `order_status` FROM `orders` WHERE `id` = ?");
                $stmt->execute([$submittedOrderId]);
                $currStatus = $stmt->fetchColumn();

                if ($currStatus === false) {
                    $msg = "Order not found in database.";
                    $msgType = "error";
                } elseif ($currStatus === $newStatus) {
                    $msg = "Order is already set to status '{$newStatus}'.";
                    $msgType = "info";
                } else {
                    $allowedNext = $allowedTransitions[$currStatus] ?? [];
                    if (!in_array($newStatus, $allowedNext, true) && $currStatus !== 'Pending') {
                        $msg = "Invalid transition: Cannot move order from '{$currStatus}' to '{$newStatus}'.";
                        $msgType = "error";
                    } else {
                        $uStmt = $pdo->prepare("UPDATE `orders` SET `order_status` = ? WHERE `id` = ?");
                        $uStmt->execute([$newStatus, $submittedOrderId]);
                        $msg = "Order status updated to '{$newStatus}' successfully.";
                        $msgType = "success";
                    }
                }
            } catch (Exception $e) {
                $msg = "Failed to update order status: " . $e->getMessage();
                $msgType = "error";
            }
        } else {
            $msg = "Invalid status value provided.";
            $msgType = "error";
        }
    }
}

// Fetch Order and Items
if ($orderId !== false && $orderId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, u.`full_name` AS `customer_name`, u.`email` AS `customer_email`, u.`phone` AS `customer_phone`
            FROM `orders` o
            JOIN `users` u ON u.`id` = o.`user_id`
            WHERE o.`id` = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            $itemStmt = $pdo->prepare("
                SELECT oi.*, p.`name` AS `product_name`, p.`image_url`, p.`price` AS `current_catalog_price`
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard.css?v=1.0.6">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .alert-banner {
            padding: 12px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #DEF7EC;
            color: #03543F;
            border: 1px solid #BCF0DA;
        }
        .alert-error {
            background: #FDE8E8;
            color: #9B1C1C;
            border: 1px solid #FBD5D5;
        }
        .alert-info {
            background: #E1EFFE;
            color: #1E429F;
            border: 1px solid #B4C6FC;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 25px;
            align-items: start;
        }
        @media (max-width: 900px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="dashboard-body">

    <!-- Mobile Backdrop -->
    <div class="dash-backdrop" id="dashBackdrop" onclick="toggleSidebar()"></div>

    <div class="dashboard-layout">
        
        <!-- Sidebar Navigation -->
        <aside class="dash-sidebar" id="dashSidebar">
            <div class="dash-brand">
                <img src="<?php echo BASE_URL; ?>assets/images/lgo.png" alt="Bodhi Logo">
                <div class="dash-brand-text">
                    <span class="dash-brand-title">Bodhi Admin</span>
                    <span class="dash-brand-subtitle">Clinic Management</span>
                </div>
            </div>

            <nav class="dash-nav">
                <span class="dash-nav-section">Core Modules</span>
                <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="dash-nav-link">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
                <a href="<?php echo BASE_URL; ?>admin/patients.php" class="dash-nav-link">
                    <i class="fas fa-users"></i> Patients
                </a>
                <a href="<?php echo BASE_URL; ?>admin/appointments.php" class="dash-nav-link">
                    <i class="fas fa-calendar-check"></i> Appointments
                </a>
                <a href="<?php echo BASE_URL; ?>admin/products.php" class="dash-nav-link">
                    <i class="fas fa-pills"></i> Products / Medicines
                </a>
                <a href="<?php echo BASE_URL; ?>admin/orders.php" class="dash-nav-link active">
                    <i class="fas fa-shopping-bag"></i> Orders
                </a>

            </nav>

            <div class="dash-sidebar-footer">
                <div class="dash-user-badge">
                    <div class="dash-avatar-circle">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="dash-user-info">
                        <div class="dash-user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                        <div class="dash-user-role">Administrator</div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="dash-main">
            
            <!-- Top Header Bar -->
            <header class="dash-header">
                <div class="dash-header-left">
                    <button class="dash-menu-btn" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="dash-portal-title">
                        <i class="fas fa-file-invoice"></i> Order Management
                        <span class="dash-header-badge">Admin Portal</span>
                    </div>
                </div>

                <div class="dash-header-right">
                    <a href="<?php echo BASE_URL; ?>admin/orders.php" class="dash-header-link">
                        <i class="fas fa-arrow-left"></i> Back to Orders
                    </a>
                    <a href="<?php echo BASE_URL; ?>logout.php" class="dash-btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </header>

            <!-- Main Body -->
            <main class="dash-body">
                
                <?php if ($order): ?>
                    
                    <!-- Page Heading -->
                    <div class="dash-page-heading" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <div class="dash-page-title">
                            <h1 class="order-id-display">Order #ORD-<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></h1>
                            <p>Placed on <strong style="color: #2D3748;"><?php echo date('F j, Y \a\t h:i A', strtotime($order['created_at'])); ?></strong></p>
                        </div>
                        <div>
                            <span class="order-status-badge" style="background: var(--dash-primary-light); color: var(--dash-primary-dark); padding: 8px 16px; border-radius: 20px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fas fa-shipping-fast"></i> Status: <?php echo htmlspecialchars($order['order_status']); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Alert Messages -->
                    <?php if (!empty($msg)): ?>
                        <div class="alert-banner <?php echo $msgType === 'success' ? 'alert-success' : ($msgType === 'error' ? 'alert-error' : 'alert-info'); ?>">
                            <i class="fas <?php echo $msgType === 'success' ? 'fa-check-circle' : ($msgType === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle'); ?>"></i>
                            <span><?php echo htmlspecialchars($msg); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="details-grid">
                        
                        <!-- Left Column: Ordered Items Table & Fulfillment Form -->
                        <div style="display: flex; flex-direction: column; gap: 24px;">
                            
                            <!-- 1. Cancellation Request Approval Box -->
                            <?php if (($order['cancellation_status'] ?? 'None') === 'Requested'): ?>
                                <div class="dash-card" style="border: 2px solid #F6AD55; background: #FFFAF0;">
                                    <div class="dash-card-header">
                                        <div class="dash-card-title" style="color: #DD6B20;">
                                            <i class="fas fa-exclamation-triangle"></i> Customer Order Cancellation Request
                                        </div>
                                    </div>
                                    <p style="font-size: 13px; color: #4A5568; margin-bottom: 8px;">
                                        Reason provided by customer: <em>"<?php echo htmlspecialchars($order['cancellation_reason'] ?: 'No specific reason provided'); ?>"</em>
                                    </p>
                                    <p style="font-size: 12.5px; color: #718096; margin-bottom: 15px;">
                                        Approving this request will automatically cancel the order, restore inventory stock exactly once, and initiate a server-side Cashfree refund for paid online orders.
                                    </p>
                                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                        <form action="<?php echo BASE_URL; ?>actions/process_refund.php" method="POST" style="margin: 0;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                            <input type="hidden" name="admin_action" value="approve">
                                            <button type="submit" style="background: #E53E3E; color: #fff; border: none; padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                                <i class="fas fa-check-circle"></i> Approve Cancellation & Process Refund
                                            </button>
                                        </form>
                                        <form action="<?php echo BASE_URL; ?>actions/process_refund.php" method="POST" style="margin: 0;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                            <input type="hidden" name="admin_action" value="reject">
                                            <button type="submit" style="background: #EDF2F7; color: #4A5568; border: 1px solid #CBD5E0; padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                                <i class="fas fa-times-circle"></i> Reject Cancellation Request
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- 2. Cancellation & Refund Status Card -->
                            <?php if (($order['cancellation_status'] ?? '') === 'Approved'): ?>
                                <div class="dash-card" style="border: 1px solid #C6F6D5; background: #F0FFF4;">
                                    <div class="dash-card-header">
                                        <div class="dash-card-title" style="color: #22543D;">
                                            <i class="fas fa-undo"></i> Cancellation & Refund Status
                                        </div>
                                    </div>
                                    <div style="font-size: 13px; color: #2C7A7B; display: flex; flex-direction: column; gap: 6px;">
                                        <div>Cancellation Status: <strong>Approved</strong></div>
                                        <div>Refund Status: <strong><?php echo htmlspecialchars($order['refund_status'] ?? 'Pending'); ?></strong></div>
                                        <div>Refund Amount: <strong>₹<?php echo number_format($order['total_amount'], 2); ?></strong></div>
                                        <div>Stock Restored: <strong><?php echo !empty($order['stock_restored']) ? 'Yes (Inventory Restored Once)' : 'No'; ?></strong></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- 3. Fulfillment Status Management Card -->
                            <div class="dash-card">
                                <div class="dash-card-header">
                                    <div class="dash-card-title">
                                        <i class="fas fa-tasks"></i> Update Fulfillment Status
                                    </div>
                                </div>
                                <form method="POST" action="" style="display: flex; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 10px;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="update_order_status">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    
                                    <label for="newStatus" style="font-size: 13px; font-weight: 600; color: #4A5568;">Status:</label>
                                    <select id="newStatus" name="new_status" class="form-control" style="width: auto; min-width: 180px; padding: 8px 12px; border: 1px solid var(--dash-border); border-radius: 6px; font-size: 13px; font-weight: 600;">
                                        <?php foreach ($validOrderStatuses as $st): ?>
                                            <option value="<?php echo $st; ?>" <?php echo $order['order_status'] === $st ? 'selected' : ''; ?>>
                                                <?php echo $st; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <button type="submit" class="btn-dash-primary" style="background: var(--dash-primary); color: #fff; border: none; padding: 9px 20px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                        <i class="fas fa-check"></i> Update Status
                                    </button>
                                </form>
                                <small style="display: block; color: #718096; font-size: 11.5px; margin-top: 10px;">
                                    * Updating fulfillment state will immediately reflect on the patient's order tracking timeline. Stock is preserved.
                                </small>
                            </div>

                            <!-- 2. Ordered Remedies Table Card -->
                            <div class="dash-card">
                                <div class="dash-card-header">
                                    <div class="dash-card-title">
                                        <i class="fas fa-prescription-bottle-alt"></i> Ordered Medicines (<?php echo count($orderItems); ?> Line Items)
                                    </div>
                                </div>

                                <div class="dash-table-wrapper">
                                    <table class="dash-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 50px;">Image</th>
                                                <th>Product</th>
                                                <th style="text-align: center;">Qty</th>
                                                <th style="text-align: right;">Unit Price</th>
                                                <th style="text-align: right;">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $calculatedSubtotal = 0.00;
                                            foreach ($orderItems as $item): 
                                                $qty = (int)$item['quantity'];
                                                $historicalPrice = (float)$item['price']; // Stored purchase price
                                                $lineSubtotal = $historicalPrice * $qty;
                                                $calculatedSubtotal += $lineSubtotal;
                                                $imgFile = !empty($item['image_url']) ? $item['image_url'] : 'a1.png';
                                            ?>
                                                <tr>
                                                    <td>
                                                        <div style="width: 40px; height: 40px; background: #FAF9F6; border-radius: 6px; border: 1px solid #E2E8F0; display: flex; align-items: center; justify-content: center; padding: 2px; overflow: hidden;">
                                                            <img src="<?php echo BASE_URL . 'assets/images/' . htmlspecialchars($imgFile); ?>" 
                                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                                                 style="max-width: 100%; max-height: 100%; object-fit: contain;"
                                                                 onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/images/a1.png';">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <strong class="item-product-name" style="color: var(--dash-primary-dark); font-size: 13px; display: block;">
                                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                                        </strong>
                                                        <small style="color: #718096; font-size: 11px;">
                                                            Product ID: #PROD-<?php echo $item['product_id']; ?>
                                                        </small>
                                                    </td>
                                                    <td class="item-quantity-val" style="text-align: center; font-weight: 600; color: #2D3748;">
                                                        <?php echo $qty; ?>
                                                    </td>
                                                    <td class="item-unit-price-val" style="text-align: right; color: #718096; font-size: 13px;">
                                                        ₹<?php echo number_format($historicalPrice, 2); ?>
                                                    </td>
                                                    <td class="item-subtotal-val" style="text-align: right; font-weight: 700; color: #2D3748; font-size: 13.5px;">
                                                        ₹<?php echo number_format($lineSubtotal, 2); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="4" style="text-align: right; padding-top: 14px; font-size: 13px; color: #718096;">Remedies Subtotal:</td>
                                                <td style="text-align: right; padding-top: 14px; font-weight: 600; color: #2D3748;">₹<?php echo number_format($calculatedSubtotal, 2); ?></td>
                                            </tr>
                                            <tr>
                                                <td colspan="4" style="text-align: right; font-size: 13px; color: #718096;">Packaging & Handling:</td>
                                                <td style="text-align: right; font-weight: 600; color: #03543F;">FREE</td>
                                            </tr>
                                            <tr>
                                                <td colspan="4" style="text-align: right; font-size: 15px; font-weight: 700; color: var(--dash-primary-dark);">Grand Total:</td>
                                                <td class="order-total-val" style="text-align: right; font-size: 17px; font-weight: 800; color: var(--dash-secondary);">
                                                    ₹<?php echo number_format($order['total_amount'], 2); ?>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                        </div>

                        <!-- Right Column: Customer Details, Payment & Shipping -->
                        <div style="display: flex; flex-direction: column; gap: 24px;">
                            
                            <!-- Customer Information Card -->
                            <div class="dash-card">
                                <div class="dash-card-header">
                                    <div class="dash-card-title">
                                        <i class="fas fa-user-injured"></i> Customer Information
                                    </div>
                                </div>
                                <div style="font-size: 13px; line-height: 1.6; color: #4A5568;">
                                    <strong class="customer-name-val" style="font-size: 14.5px; color: var(--dash-primary-dark); display: block; margin-bottom: 4px;">
                                        <?php echo htmlspecialchars($order['customer_name']); ?>
                                    </strong>
                                    <div><i class="fas fa-envelope" style="width: 16px; color: #718096;"></i> <span class="customer-email-val"><?php echo htmlspecialchars($order['customer_email']); ?></span></div>
                                    <div><i class="fas fa-phone" style="width: 16px; color: #718096;"></i> <span class="customer-phone-val"><?php echo htmlspecialchars($order['customer_phone']); ?></span></div>
                                    <div style="margin-top: 6px; padding-top: 6px; border-top: 1px dashed #E2E8F0; font-size: 11.5px; color: #718096;">
                                        Patient Account ID: <strong>#PAT-<?php echo str_pad($order['user_id'], 4, '0', STR_PAD_LEFT); ?></strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Information Card -->
                            <div class="dash-card">
                                <div class="dash-card-header">
                                    <div class="dash-card-title">
                                        <i class="fas fa-credit-card"></i> Payment Information
                                    </div>
                                </div>
                                <div style="font-size: 13px; line-height: 1.8; color: #4A5568;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span style="color: #718096;">Payment Status:</span>
                                        <?php if ($order['payment_status'] === 'Paid'): ?>
                                            <span class="badge-payment-paid" style="background: #DEF7EC; color: #03543F; padding: 3px 10px; border-radius: 12px; font-weight: 700; font-size: 11.5px;">
                                                <i class="fas fa-check-circle"></i> Paid
                                            </span>
                                        <?php elseif ($order['payment_status'] === 'Failed'): ?>
                                            <span class="badge-payment-failed" style="background: #FDE8E8; color: #9B1C1C; padding: 3px 10px; border-radius: 12px; font-weight: 700; font-size: 11.5px;">
                                                <i class="fas fa-times-circle"></i> Failed
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-payment-pending" style="background: #FEF3C7; color: #92400E; padding: 3px 10px; border-radius: 12px; font-weight: 700; font-size: 11.5px;">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span style="color: #718096;">Payment Method:</span>
                                        <strong style="color: #2D3748;"><?php echo htmlspecialchars($order['payment_method']); ?></strong>
                                    </div>
                                    <?php if (!empty($order['cashfree_order_id'])): ?>
                                        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 6px; border-top: 1px dashed #E2E8F0;">
                                            <span style="color: #718096;">Cashfree Ref:</span>
                                            <code style="background: #EDF2F7; padding: 2px 6px; border-radius: 4px; font-size: 11px; color: #4A5568;"><?php echo htmlspecialchars($order['cashfree_order_id']); ?></code>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Delivery Destination Card -->
                            <div class="dash-card">
                                <div class="dash-card-header">
                                    <div class="dash-card-title">
                                        <i class="fas fa-map-marker-alt"></i> Shipping Address
                                    </div>
                                </div>
                                <div class="shipping-address-val" style="font-size: 13px; line-height: 1.6; color: #4A5568;">
                                    <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                                </div>
                            </div>

                        </div>

                    </div>

                <?php else: ?>
                    
                    <!-- Order Not Found Card -->
                    <div class="dash-card" style="text-align: center; padding: 60px 20px; max-width: 550px; margin: 40px auto;">
                        <div style="width: 70px; height: 70px; border-radius: 50%; background: #FDE8E8; color: #9B1C1C; display: flex; align-items: center; justify-content: center; font-size: 30px; margin: 0 auto 18px;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h2 style="color: var(--dash-primary-dark); font-size: 20px; font-weight: 700; margin-bottom: 8px;">Order Not Found</h2>
                        <p style="color: #718096; font-size: 13.5px; margin-bottom: 22px;">
                            The requested pharmacy order reference does not exist in the database or an invalid order ID parameter was provided.
                        </p>
                        <a href="<?php echo BASE_URL; ?>admin/orders.php" class="btn-dash-primary" style="background: var(--dash-primary); color: #fff; padding: 10px 24px; border-radius: 8px; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas fa-arrow-left"></i> Return to Orders List
                        </a>
                    </div>

                <?php endif; ?>

            </main>
        </div>
    </div>

    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('dashSidebar');
        const backdrop = document.getElementById('dashBackdrop');
        sidebar.classList.toggle('active');
        backdrop.classList.toggle('active');
    }
    </script>
</body>
</html>
