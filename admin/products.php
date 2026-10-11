<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: strictly enforce Admin role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Product & Inventory Management | Bodhi Admin";

// CSRF & Status Toggle POST Handler
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Security token expired. Please reload and try again.";
        $msgType = "error";
    } elseif ($_POST['action'] === 'toggle_status') {
        $prodId = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
        $newStatus = trim($_POST['status'] ?? '');
        $validStatuses = ['Active', 'Inactive'];

        if ($prodId > 0 && in_array($newStatus, $validStatuses, true)) {
            try {
                $stmt = $pdo->prepare("UPDATE `products` SET `status` = ? WHERE `id` = ?");
                $stmt->execute([$newStatus, $prodId]);
                $msg = "Product #{$prodId} status updated to '{$newStatus}' successfully.";
                $msgType = "success";
            } catch (Exception $e) {
                $msg = "Failed to update product status: " . $e->getMessage();
                $msgType = "error";
            }
        }
    } elseif ($_POST['action'] === 'quick_stock') {
        $prodId = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
        $newStock = filter_var($_POST['stock_quantity'] ?? 0, FILTER_VALIDATE_INT);

        if ($prodId > 0 && $newStock !== false && $newStock >= 0) {
            try {
                $stmt = $pdo->prepare("UPDATE `products` SET `stock_quantity` = ? WHERE `id` = ?");
                $stmt->execute([$newStock, $prodId]);
                $msg = "Product #{$prodId} stock updated to {$newStock} units successfully.";
                $msgType = "success";
            } catch (Exception $e) {
                $msg = "Failed to update inventory stock: " . $e->getMessage();
                $msgType = "error";
            }
        } else {
            $msg = "Invalid stock quantity specified. Must be an integer >= 0.";
            $msgType = "error";
        }
    }
}

// Flash messages from add/edit pages
if (isset($_SESSION['admin_product_msg'])) {
    $msg = $_SESSION['admin_product_msg'];
    $msgType = $_SESSION['admin_product_msg_type'] ?? 'success';
    unset($_SESSION['admin_product_msg'], $_SESSION['admin_product_msg_type']);
}

// Search and filter parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$stockFilter = trim($_GET['stock'] ?? '');

$lowStockThreshold = 5; // Configured low stock threshold

try {
    // 1. Live Database Summary Metrics
    $stmt = $pdo->query("SELECT COUNT(*) FROM `products`");
    $totalProducts = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `products` WHERE `status` = 'Active'");
    $activeProducts = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `products` WHERE `status` = 'Inactive'");
    $inactiveProducts = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `products` WHERE `stock_quantity` = 0");
    $outOfStockCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `products` WHERE `stock_quantity` > 0 AND `stock_quantity` <= ?");
    $stmt->execute([$lowStockThreshold]);
    $lowStockCount = (int)$stmt->fetchColumn();

    // 2. Build Filtered Query
    $query = "SELECT * FROM `products` WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $query .= " AND (`name` LIKE ? OR `description` LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }

    if (!empty($statusFilter) && in_array($statusFilter, ['Active', 'Inactive'], true)) {
        $query .= " AND `status` = ?";
        $params[] = $statusFilter;
    }

    if ($stockFilter === 'out') {
        $query .= " AND `stock_quantity` = 0";
    } elseif ($stockFilter === 'low') {
        $query .= " AND `stock_quantity` > 0 AND `stock_quantity` <= ?";
        $params[] = $lowStockThreshold;
    } elseif ($stockFilter === 'instock') {
        $query .= " AND `stock_quantity` > ?";
        $params[] = $lowStockThreshold;
    }

    $query .= " ORDER BY `id` ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $productsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $totalProducts = 0;
    $activeProducts = 0;
    $inactiveProducts = 0;
    $outOfStockCount = 0;
    $lowStockCount = 0;
    $productsList = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard.css?v=1.0.5">
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
        .quick-status-form {
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
                <a href="<?php echo BASE_URL; ?>admin/products.php" class="dash-nav-link active">
                    <i class="fas fa-pills"></i> Products / Medicines
                </a>
                <a href="<?php echo BASE_URL; ?>admin/orders.php" class="dash-nav-link">
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
                        <i class="fas fa-pills"></i> Pharmacy & Inventory Management
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
                
                <!-- Page Heading & Action Button -->
                <div class="dash-page-heading" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div class="dash-page-title">
                        <h1 class="page-main-heading">Product Management</h1>
                        <p>Manage medicines, pricing, availability, and inventory levels</p>
                    </div>
                    <a href="<?php echo BASE_URL; ?>admin/product_add.php" class="btn-dash-primary" style="background: var(--dash-primary); color: #fff; padding: 10px 22px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(53, 94, 59, 0.2);">
                        <i class="fas fa-plus-circle"></i> Add New Product
                    </a>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($msg)): ?>
                    <div class="alert-banner <?php echo $msgType === 'success' ? 'alert-success' : 'alert-error'; ?>">
                        <i class="fas <?php echo $msgType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                        <span><?php echo htmlspecialchars($msg); ?></span>
                    </div>
                <?php endif; ?>

                <!-- 4 Real Database Summary Metrics -->
                <div class="dash-stats-grid">
                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Total Products</span>
                            <span class="dash-stat-value count-total-products"><?php echo number_format($totalProducts); ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-boxes"></i> All registered catalog items</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-green">
                            <i class="fas fa-prescription-bottle-alt"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Active Products</span>
                            <span class="dash-stat-value count-active-products"><?php echo number_format($activeProducts); ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-check"></i> Available on storefront</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-blue">
                            <i class="fas fa-toggle-on"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Out of Stock</span>
                            <span class="dash-stat-value count-outofstock-products"><?php echo number_format($outOfStockCount); ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-ban"></i> 0 inventory units left</span>
                        </div>
                        <div class="dash-stat-icon" style="background: #FDE8E8; color: #9B1C1C;">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Low Stock</span>
                            <span class="dash-stat-value count-lowstock-products"><?php echo number_format($lowStockCount); ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-exclamation-triangle"></i> &le; <?php echo $lowStockThreshold; ?> units remaining</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-orange">
                            <i class="fas fa-layer-group"></i>
                        </div>
                    </div>
                </div>

                <!-- Search & Filters Card -->
                <div class="dash-card" style="margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                        
                        <!-- Search Form -->
                        <form method="GET" action="" style="display: flex; gap: 8px; flex-grow: 1; max-width: 420px;">
                            <div style="position: relative; width: 100%;">
                                <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #A0AEC0; font-size: 13px;"></i>
                                <input type="text" 
                                       name="search" 
                                       class="search-input"
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search medicines by name or indication..." 
                                       style="width: 100%; padding: 8px 12px 8px 34px; border: 1px solid var(--dash-border); border-radius: 6px; font-size: 13px; font-family: inherit; outline: none;">
                            </div>
                            <?php if (!empty($statusFilter)): ?>
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                            <?php endif; ?>
                            <?php if (!empty($stockFilter)): ?>
                                <input type="hidden" name="stock" value="<?php echo htmlspecialchars($stockFilter); ?>">
                            <?php endif; ?>
                            <button type="submit" class="btn-search" style="padding: 8px 16px; background: var(--dash-primary); color: #fff; border: none; border-radius: 6px; font-size: 12.5px; font-weight: 600; cursor: pointer;">
                                Search
                            </button>
                            <?php if (!empty($search) || !empty($statusFilter) || !empty($stockFilter)): ?>
                                <a href="<?php echo BASE_URL; ?>admin/products.php" style="padding: 8px 12px; background: #EDF2F7; color: #4A5568; border-radius: 6px; font-size: 12.5px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center;">
                                    Clear
                                </a>
                            <?php endif; ?>
                        </form>

                        <!-- Filter Chips -->
                        <div class="filter-chip-group">
                            <span style="font-size: 12px; font-weight: 600; color: #718096; margin-right: 4px;">Status:</span>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>" 
                               class="filter-chip <?php echo empty($statusFilter) ? 'active' : ''; ?>">
                               All
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'Active'])); ?>" 
                               class="filter-chip <?php echo ($statusFilter === 'Active') ? 'active' : ''; ?>">
                               Active (<?php echo $activeProducts; ?>)
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'Inactive'])); ?>" 
                               class="filter-chip <?php echo ($statusFilter === 'Inactive') ? 'active' : ''; ?>">
                               Inactive (<?php echo $inactiveProducts; ?>)
                            </a>

                            <span style="font-size: 12px; font-weight: 600; color: #718096; margin-left: 8px; margin-right: 4px;">Stock:</span>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['stock' => 'out'])); ?>" 
                               class="filter-chip <?php echo ($stockFilter === 'out') ? 'active' : ''; ?>">
                               Out of Stock (<?php echo $outOfStockCount; ?>)
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['stock' => 'low'])); ?>" 
                               class="filter-chip <?php echo ($stockFilter === 'low') ? 'active' : ''; ?>">
                               Low Stock (<?php echo $lowStockCount; ?>)
                            </a>
                        </div>

                    </div>
                </div>

                <!-- Products Table Card -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <div>
                            <div class="dash-card-title">
                                <i class="fas fa-tablets"></i> Pharmacy Products Inventory
                            </div>
                            <div class="dash-card-subtitle">
                                Showing <?php echo count($productsList); ?> remedy <?php echo count($productsList) === 1 ? 'item' : 'items'; ?>
                            </div>
                        </div>
                    </div>

                    <div class="dash-table-wrapper" style="overflow-x: auto;">
                        <table class="dash-table products-table" style="width: 100%; min-width: 850px; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="width: 65px; text-align: center;">Image</th>
                                    <th style="min-width: 160px; width: 22%;">Product Name</th>
                                    <th style="min-width: 220px; width: 32%;">Description</th>
                                    <th style="width: 100px; text-align: right; white-space: nowrap;">Price</th>
                                    <th style="width: 130px; text-align: center; white-space: nowrap;">Stock</th>
                                    <th style="width: 110px; text-align: center; white-space: nowrap;">Status</th>
                                    <th style="text-align: right; width: 120px; white-space: nowrap;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($productsList)): ?>
                                    <?php foreach ($productsList as $p): ?>
                                        <?php 
                                            $imgFile = !empty($p['image_url']) ? $p['image_url'] : 'a1.png';
                                            $stockQty = (int)$p['stock_quantity'];
                                            $isOut = ($stockQty === 0);
                                            $isLow = ($stockQty > 0 && $stockQty <= $lowStockThreshold);
                                        ?>
                                        <tr class="product-row" data-id="<?php echo $p['id']; ?>" style="border-bottom: 1px solid #EDF2F7;">
                                             
                                            <!-- Product Thumbnail -->
                                            <td style="text-align: center; vertical-align: middle;">
                                                <div style="width: 44px; height: 44px; background: #FAF9F6; border-radius: 6px; border: 1px solid #E2E8F0; display: inline-flex; align-items: center; justify-content: center; padding: 2px; overflow: hidden;">
                                                    <img src="<?php echo BASE_URL . 'assets/images/' . htmlspecialchars($imgFile); ?>" 
                                                         alt="<?php echo htmlspecialchars($p['name']); ?>" 
                                                         style="max-width: 100%; max-height: 100%; object-fit: contain;"
                                                         onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/images/a1.png';">
                                                </div>
                                            </td>

                                            <!-- Name & Category -->
                                            <td style="vertical-align: middle;">
                                                <strong class="product-name" style="color: var(--dash-primary-dark); font-size: 13.5px; display: block; line-height: 1.3;">
                                                    <?php echo htmlspecialchars($p['name']); ?>
                                                </strong>
                                                <small style="color: #718096; font-size: 11px;">
                                                    ID: #PROD-<?php echo str_pad($p['id'], 3, '0', STR_PAD_LEFT); ?>
                                                </small>
                                            </td>

                                            <!-- Description (Clamped to 2 lines max with tooltip) -->
                                            <td style="vertical-align: middle;">
                                                <div style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; color: #4A5568; font-size: 12px; line-height: 1.45; max-width: 320px;" title="<?php echo htmlspecialchars($p['description']); ?>">
                                                    <?php echo htmlspecialchars($p['description']); ?>
                                                </div>
                                            </td>

                                            <!-- Price -->
                                            <td style="text-align: right; vertical-align: middle; font-weight: 700; color: var(--dash-secondary); font-size: 14px; white-space: nowrap;">
                                                ₹<?php echo number_format($p['price'], 2); ?>
                                            </td>

                                            <!-- Stock Quantity & Badges -->
                                            <td style="text-align: center; vertical-align: middle; white-space: nowrap;">
                                                <div style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                                                    <span class="product-stock-qty" style="font-weight: 700; font-size: 13px; color: <?php echo $isOut ? '#9B1C1C' : ($isLow ? '#92400E' : '#2D3748'); ?>;">
                                                        <?php echo $stockQty; ?>
                                                    </span>
                                                    <?php if ($isOut): ?>
                                                        <span style="font-size: 10.5px; font-weight: 700; background: #FDE8E8; color: #9B1C1C; padding: 2px 7px; border-radius: 10px;">
                                                            Out
                                                        </span>
                                                    <?php elseif ($isLow): ?>
                                                        <span style="font-size: 10.5px; font-weight: 700; background: #FEF3C7; color: #92400E; padding: 2px 7px; border-radius: 10px;">
                                                            Low
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="font-size: 10.5px; font-weight: 600; background: #DEF7EC; color: #03543F; padding: 2px 7px; border-radius: 10px;">
                                                            In Stock
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <!-- Status & Toggle Form -->
                                            <td style="text-align: center; vertical-align: middle; white-space: nowrap;">
                                                <form method="POST" action="" class="quick-status-form">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                                    <select name="status" class="quick-status-select" onchange="this.form.submit()" style="font-weight: 600; <?php echo $p['status'] === 'Active' ? 'color: #03543F; background: #DEF7EC; border-color: #BCF0DA;' : 'color: #9B1C1C; background: #FDE8E8; border-color: #FBD5D5;'; ?>">
                                                        <option value="Active" <?php echo $p['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="Inactive" <?php echo $p['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                    </select>
                                                </form>
                                            </td>

                                            <!-- Actions -->
                                            <td style="text-align: right; white-space: nowrap;">
                                                <a href="<?php echo BASE_URL; ?>admin/product_edit.php?id=<?php echo $p['id']; ?>" class="btn-edit-product" style="display: inline-flex; align-items: center; gap: 4px; padding: 5px 12px; border-radius: 6px; background: #EEF6EF; color: var(--dash-primary); font-size: 12px; font-weight: 600; text-decoration: none;">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="<?php echo BASE_URL; ?>product_details.php?id=<?php echo $p['id']; ?>" target="_blank" style="display: inline-flex; align-items: center; padding: 5px 8px; border-radius: 6px; background: #EDF2F7; color: #718096; font-size: 12px; text-decoration: none; margin-left: 4px;" title="View on storefront">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            </td>

                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 50px 20px; color: #718096;">
                                            <i class="fas fa-search" style="font-size: 32px; color: #CBD5E0; margin-bottom: 12px; display: block;"></i>
                                            <strong style="font-size: 15px; color: #2D3748;">No medicines found matching criteria.</strong>
                                            <p style="margin: 6px 0 0 0; font-size: 13px; color: #A0AEC0;">
                                                Try adjusting search keywords or resetting status filters.
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
