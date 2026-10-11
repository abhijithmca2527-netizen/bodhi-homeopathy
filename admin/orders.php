<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: strictly enforce Admin role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Orders Management & Fulfillment | Bodhi Admin";

$validOrderStatuses = ['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
$validPaymentStatuses = ['Paid', 'Pending', 'Failed'];

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

// POST Handler: Quick Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Security token expired. Please reload and try again.";
        $msgType = "error";
    } else {
        $orderId = filter_var($_POST['order_id'] ?? 0, FILTER_VALIDATE_INT);
        $newStatus = trim($_POST['new_status'] ?? '');

        if ($orderId > 0 && in_array($newStatus, $validOrderStatuses, true)) {
            try {
                // Fetch current order status
                $stmt = $pdo->prepare("SELECT `order_status` FROM `orders` WHERE `id` = ?");
                $stmt->execute([$orderId]);
                $currStatus = $stmt->fetchColumn();

                if ($currStatus === false) {
                    $msg = "Order #{$orderId} not found.";
                    $msgType = "error";
                } elseif ($currStatus === $newStatus) {
                    $msg = "Order #{$orderId} is already set to '{$newStatus}'.";
                    $msgType = "info";
                } else {
                    // Check transition rules
                    $allowedNext = $allowedTransitions[$currStatus] ?? [];
                    if (!in_array($newStatus, $allowedNext, true) && $currStatus !== 'Pending') {
                        $msg = "Cannot transition order from '{$currStatus}' to '{$newStatus}'.";
                        $msgType = "error";
                    } else {
                        // Update order status without modifying payment_status or deducting stock
                        $uStmt = $pdo->prepare("UPDATE `orders` SET `order_status` = ? WHERE `id` = ?");
                        $uStmt->execute([$newStatus, $orderId]);
                        $msg = "Order #ORD-" . str_pad($orderId, 4, '0', STR_PAD_LEFT) . " status updated to '{$newStatus}' successfully.";
                        $msgType = "success";
                    }
                }
            } catch (Exception $e) {
                $msg = "Failed to update order status: " . $e->getMessage();
                $msgType = "error";
            }
        } else {
            $msg = "Invalid status update request.";
            $msgType = "error";
        }
    }
}

// Flash messages from details page
if (isset($_SESSION['admin_order_msg'])) {
    $msg = $_SESSION['admin_order_msg'];
    $msgType = $_SESSION['admin_order_msg_type'] ?? 'success';
    unset($_SESSION['admin_order_msg'], $_SESSION['admin_order_msg_type']);
}

// Search and filter parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$paymentFilter = trim($_GET['payment'] ?? '');

try {
    // 1. Live Database Summary Statistics
    $stmt = $pdo->query("SELECT COUNT(*) FROM `orders`");
    $totalOrders = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'Pending'");
    $pendingOrders = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'Confirmed'");
    $confirmedOrders = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'Processing'");
    $processingOrders = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'Shipped'");
    $shippedOrders = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'Delivered'");
    $deliveredOrders = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'Cancelled'");
    $cancelledOrders = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `orders` WHERE `payment_status` = 'Paid'");
    $paidOrders = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `orders` WHERE `payment_status` = 'Pending'");
    $pendingPaymentOrders = (int)$stmt->fetchColumn();

    // 2. Build Filtered Query
    $query = "
        SELECT o.*, u.`full_name` AS `customer_name`, u.`email` AS `customer_email`, u.`phone` AS `customer_phone`
        FROM `orders` o
        JOIN `users` u ON u.`id` = o.`user_id`
        WHERE 1=1
    ";
    $params = [];

    if (!empty($search)) {
        // Strip #ORD- prefix if entered by user (e.g. #ORD-0005 -> 5)
        $cleanSearch = preg_replace('/^#?ORD-0*/i', '', $search);
        $query .= " AND (
            o.`id` = ? OR 
            u.`full_name` LIKE ? OR 
            u.`email` LIKE ? OR 
            u.`phone` LIKE ? OR 
            o.`cashfree_order_id` LIKE ?
        )";
        $searchOrderId = is_numeric($cleanSearch) ? (int)$cleanSearch : 0;
        $like = '%' . $search . '%';
        $params[] = $searchOrderId;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if (!empty($statusFilter) && in_array($statusFilter, $validOrderStatuses, true)) {
        $query .= " AND o.`order_status` = ?";
        $params[] = $statusFilter;
    }

    if (!empty($paymentFilter) && in_array($paymentFilter, $validPaymentStatuses, true)) {
        $query .= " AND o.`payment_status` = ?";
        $params[] = $paymentFilter;
    }

    $query .= " ORDER BY o.`created_at` DESC, o.`id` DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $ordersList = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $totalOrders = 0;
    $pendingOrders = 0;
    $confirmedOrders = 0;
    $processingOrders = 0;
    $shippedOrders = 0;
    $deliveredOrders = 0;
    $cancelledOrders = 0;
    $paidOrders = 0;
    $pendingPaymentOrders = 0;
    $ordersList = [];
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
        .filter-chip-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .filter-chip {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--dash-border);
            color: var(--dash-text);
            background: var(--dash-white);
            transition: var(--dash-transition);
        }
        .filter-chip:hover {
            border-color: var(--dash-primary);
            color: var(--dash-primary);
            background: var(--dash-primary-light);
        }
        .filter-chip.active {
            background: var(--dash-primary);
            color: var(--dash-white);
            border-color: var(--dash-primary);
        }
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
        .quick-status-select {
            padding: 4px 8px;
            font-size: 12px;
            font-family: 'Poppins', sans-serif;
            border: 1px solid var(--dash-border);
            border-radius: 6px;
            background: var(--dash-white);
            color: var(--dash-text-dark);
            outline: none;
            cursor: pointer;
            transition: var(--dash-transition);
        }
        .quick-status-select:focus {
            border-color: var(--dash-primary);
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
                        <i class="fas fa-boxes"></i> Orders & Pharmacy Fulfillment
                        <span class="dash-header-badge">Admin Portal</span>
                    </div>
                </div>

                <div class="dash-header-right">
                    <a href="<?php echo BASE_URL; ?>products.php" class="dash-header-link" target="_blank" title="View Patient Storefront">
                        <i class="fas fa-store"></i> View Storefront
                    </a>
                    <a href="<?php echo BASE_URL; ?>logout.php" class="dash-btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </header>

            <!-- Main Body -->
            <main class="dash-body">
                
                <!-- Page Heading -->
                <div class="dash-page-heading">
                    <div class="dash-page-title">
                        <h1 class="page-main-heading">Order Management</h1>
                        <p>Manage customer orders, payment verification, and delivery fulfillment</p>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($msg)): ?>
                    <div class="alert-banner <?php echo $msgType === 'success' ? 'alert-success' : ($msgType === 'error' ? 'alert-error' : 'alert-info'); ?>">
                        <i class="fas <?php echo $msgType === 'success' ? 'fa-check-circle' : ($msgType === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle'); ?>"></i>
                        <span><?php echo htmlspecialchars($msg); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Real Database Summary Metrics -->
                <div class="dash-stats-grid">
                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Total Orders</span>
                            <span class="dash-stat-value count-total-orders"><?php echo number_format($totalOrders); ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-shopping-bag"></i> All registered orders</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-green">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Paid Orders</span>
                            <span class="dash-stat-value count-paid-orders"><?php echo number_format($paidOrders); ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-check-circle"></i> Confirmed payments</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-blue">
                            <i class="fas fa-money-check-alt"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Processing / Confirmed</span>
                            <span class="dash-stat-value count-processing-orders"><?php echo number_format($processingOrders + $confirmedOrders); ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-mortar-pestle"></i> In preparation</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-orange">
                            <i class="fas fa-box-open"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Delivered Orders</span>
                            <span class="dash-stat-value count-delivered-orders"><?php echo number_format($deliveredOrders); ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-truck"></i> Completed deliveries</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-purple">
                            <i class="fas fa-home"></i>
                        </div>
                    </div>
                </div>

                <!-- Search & Filters Card -->
                <div class="dash-card" style="margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                        
                        <!-- Search Form -->
                        <form method="GET" action="" style="display: flex; gap: 8px; flex-grow: 1; max-width: 440px;">
                            <div style="position: relative; width: 100%;">
                                <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #A0AEC0; font-size: 13px;"></i>
                                <input type="text" 
                                       name="search" 
                                       class="search-input"
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by Order ID, customer, email, phone..." 
                                       style="width: 100%; padding: 8px 12px 8px 34px; border: 1px solid var(--dash-border); border-radius: 6px; font-size: 13px; font-family: inherit; outline: none;">
                            </div>
                            <?php if (!empty($statusFilter)): ?>
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                            <?php endif; ?>
                            <?php if (!empty($paymentFilter)): ?>
                                <input type="hidden" name="payment" value="<?php echo htmlspecialchars($paymentFilter); ?>">
                            <?php endif; ?>
                            <button type="submit" class="btn-search" style="padding: 8px 16px; background: var(--dash-primary); color: #fff; border: none; border-radius: 6px; font-size: 12.5px; font-weight: 600; cursor: pointer;">
                                Search
                            </button>
                            <?php if (!empty($search) || !empty($statusFilter) || !empty($paymentFilter)): ?>
                                <a href="<?php echo BASE_URL; ?>admin/orders.php" style="padding: 8px 12px; background: #EDF2F7; color: #4A5568; border-radius: 6px; font-size: 12.5px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center;">
                                    Clear
                                </a>
                            <?php endif; ?>
                        </form>

                        <!-- Filter Chips -->
                        <div class="filter-chip-group">
                            <span style="font-size: 12px; font-weight: 600; color: #718096; margin-right: 4px;">Status:</span>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>" 
                               class="filter-chip <?php echo empty($statusFilter) ? 'active' : ''; ?>">
                               All (<?php echo $totalOrders; ?>)
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'Pending'])); ?>" 
                               class="filter-chip <?php echo ($statusFilter === 'Pending') ? 'active' : ''; ?>">
                               Pending (<?php echo $pendingOrders; ?>)
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'Confirmed'])); ?>" 
                               class="filter-chip <?php echo ($statusFilter === 'Confirmed') ? 'active' : ''; ?>">
                               Confirmed (<?php echo $confirmedOrders; ?>)
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'Processing'])); ?>" 
                               class="filter-chip <?php echo ($statusFilter === 'Processing') ? 'active' : ''; ?>">
                               Processing (<?php echo $processingOrders; ?>)
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'Shipped'])); ?>" 
                               class="filter-chip <?php echo ($statusFilter === 'Shipped') ? 'active' : ''; ?>">
                               Shipped (<?php echo $shippedOrders; ?>)
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'Delivered'])); ?>" 
                               class="filter-chip <?php echo ($statusFilter === 'Delivered') ? 'active' : ''; ?>">
                               Delivered (<?php echo $deliveredOrders; ?>)
                            </a>

                            <span style="font-size: 12px; font-weight: 600; color: #718096; margin-left: 8px; margin-right: 4px;">Payment:</span>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['payment' => 'Paid'])); ?>" 
                               class="filter-chip <?php echo ($paymentFilter === 'Paid') ? 'active' : ''; ?>">
                               Paid (<?php echo $paidOrders; ?>)
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['payment' => 'Pending'])); ?>" 
                               class="filter-chip <?php echo ($paymentFilter === 'Pending') ? 'active' : ''; ?>">
                               Pending Pay (<?php echo $pendingPaymentOrders; ?>)
                            </a>
                        </div>

                    </div>
                </div>

                <!-- Orders Table Card -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <div>
                            <div class="dash-card-title">
                                <i class="fas fa-list-alt"></i> Pharmacy Orders List
                            </div>
                            <div class="dash-card-subtitle">
                                Showing <?php echo count($ordersList); ?> order <?php echo count($ordersList) === 1 ? 'record' : 'records'; ?>
                            </div>
                        </div>
                    </div>

                    <div class="dash-table-wrapper">
                        <table class="dash-table orders-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Placed Date</th>
                                    <th>Grand Total</th>
                                    <th>Payment</th>
                                    <th>Fulfillment</th>
                                    <th style="text-align: right; width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($ordersList)): ?>
                                    <?php foreach ($ordersList as $o): ?>
                                        <tr class="order-row" data-id="<?php echo $o['id']; ?>">
                                            
                                            <!-- Order ID -->
                                            <td style="font-weight: 700; color: var(--dash-primary-dark); white-space: nowrap;">
                                                <a href="<?php echo BASE_URL; ?>admin/order_details.php?order_id=<?php echo $o['id']; ?>" style="color: var(--dash-primary-dark); text-decoration: underline;">
                                                    #ORD-<?php echo str_pad($o['id'], 4, '0', STR_PAD_LEFT); ?>
                                                </a>
                                            </td>

                                            <!-- Customer -->
                                            <td>
                                                <strong class="order-customer-name" style="color: #2D3748; font-size: 13px; display: block;">
                                                    <?php echo htmlspecialchars($o['customer_name']); ?>
                                                </strong>
                                                <small style="color: #718096; font-size: 11px;">
                                                    <?php echo htmlspecialchars($o['customer_email']); ?> | <?php echo htmlspecialchars($o['customer_phone']); ?>
                                                </small>
                                            </td>

                                            <!-- Placed Date -->
                                            <td style="color: #4A5568; font-size: 12.5px; white-space: nowrap;">
                                                <?php echo date('d M Y, h:i A', strtotime($o['created_at'])); ?>
                                            </td>

                                            <!-- Grand Total -->
                                            <td class="order-total-amount" style="font-weight: 700; color: var(--dash-secondary); font-size: 14px; white-space: nowrap;">
                                                ₹<?php echo number_format($o['total_amount'], 2); ?>
                                            </td>

                                            <!-- Payment Status -->
                                            <td>
                                                <?php if ($o['payment_status'] === 'Paid'): ?>
                                                    <span class="badge-payment-paid" style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 15px; font-size: 11px; font-weight: 700; background: #DEF7EC; color: #03543F;">
                                                        <i class="fas fa-check-circle"></i> Paid
                                                    </span>
                                                <?php elseif ($o['payment_status'] === 'Failed'): ?>
                                                    <span class="badge-payment-failed" style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 15px; font-size: 11px; font-weight: 700; background: #FDE8E8; color: #9B1C1C;">
                                                        <i class="fas fa-times-circle"></i> Failed
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge-payment-pending" style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 15px; font-size: 11px; font-weight: 700; background: #FEF3C7; color: #92400E;">
                                                        <i class="fas fa-clock"></i> Pending
                                                    </span>
                                                <?php endif; ?>
                                                <small style="display: block; color: #718096; font-size: 10.5px; margin-top: 2px;">
                                                    <?php echo htmlspecialchars($o['payment_method']); ?>
                                                </small>
                                            </td>

                                            <!-- Fulfillment Status -->
                                            <td>
                                                <span class="order-fulfillment-badge" style="display: inline-block; padding: 4px 10px; border-radius: 15px; font-size: 11.5px; font-weight: 600; background: #FAF9F6; color: #2D3748; border: 1px solid #E2E8F0;">
                                                    <?php echo htmlspecialchars($o['order_status']); ?>
                                                </span>
                                            </td>

                                            <!-- Actions -->
                                            <td style="text-align: right; white-space: nowrap;">
                                                <a href="<?php echo BASE_URL; ?>admin/order_details.php?order_id=<?php echo $o['id']; ?>" class="btn-view-order-details" style="display: inline-flex; align-items: center; gap: 4px; padding: 5px 12px; border-radius: 6px; background: #EEF6EF; color: var(--dash-primary); font-size: 12px; font-weight: 600; text-decoration: none;">
                                                    <i class="fas fa-eye"></i> Details
                                                </a>
                                            </td>

                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 50px 20px; color: #718096;">
                                            <i class="fas fa-box-open" style="font-size: 36px; color: #CBD5E0; margin-bottom: 12px; display: block;"></i>
                                            <strong style="font-size: 15px; color: #2D3748;">
                                                <?php echo ($totalOrders === 0) ? 'No pharmacy orders have been placed yet.' : 'No orders found matching criteria.'; ?>
                                            </strong>
                                            <p style="margin: 6px 0 0 0; font-size: 13px; color: #718096;">
                                                <?php echo ($totalOrders === 0) ? 'New remedy prescription orders placed by patients will appear here.' : 'Try adjusting search keywords or clearing status filters.'; ?>
                                            </p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

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
