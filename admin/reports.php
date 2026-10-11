<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/report_helper.php';

// Security check: strictly enforce Admin role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Admin Portal - Reports & Analytics | Bodhi Homeopathy";

$dateRange = get_report_date_range($_GET['from'] ?? null, $_GET['to'] ?? null);
$from = $dateRange['from'];
$to = $dateRange['to'];
$rangeError = $dateRange['error'];

try {
    $metrics = calculate_report_metrics($pdo, $from, $to);
    $pharmacy = $metrics['pharmacy'];
    $clinic = $metrics['clinic'];
    $inventory = $metrics['inventory'];
} catch (Exception $e) {
    $pharmacy = ['total_orders' => 0, 'paid_orders' => 0, 'paid_revenue' => 0, 'pending_orders' => 0, 'pending_revenue' => 0, 'delivered_orders' => 0, 'orders_list' => []];
    $clinic = ['total_appointments' => 0, 'completed_consultations' => 0, 'new_patients' => 0, 'total_patients_all_time' => 0, 'appointments_list' => []];
    $inventory = ['total_products' => 0, 'current_units_in_stock' => 0, 'low_stock_products' => 0, 'out_of_stock_products' => 0];
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
        .stat-card-custom {
            background: #fff;
            padding: 22px 20px;
            border-radius: 12px;
            border: 1px solid var(--dash-border);
            box-shadow: var(--dash-shadow);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: var(--dash-transition);
        }
        .stat-card-custom:hover {
            box-shadow: var(--dash-shadow-hover);
        }
        .stat-icon-custom {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        .report-section-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        .filter-card {
            background: #fff;
            padding: 20px 24px;
            border-radius: 12px;
            border: 1px solid var(--dash-border);
            box-shadow: var(--dash-shadow);
            margin-bottom: 25px;
        }
        .filter-form {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }
        .filter-input-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-input-group label {
            font-size: 12.5px;
            font-weight: 600;
            color: #4A5568;
            white-space: nowrap;
        }
        .filter-date-input {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid var(--dash-border);
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            color: #2D3748;
            background: #FAF9F6;
            outline: none;
            transition: var(--dash-transition);
        }
        .filter-date-input:focus {
            border-color: var(--dash-primary);
            background: #fff;
        }
        .btn-filter-apply {
            padding: 8px 18px;
            background: var(--dash-primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--dash-transition);
        }
        .btn-filter-apply:hover {
            background: var(--dash-primary-hover);
        }
        .btn-filter-clear {
            padding: 8px 14px;
            background: #EDF2F7;
            color: #4A5568;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: var(--dash-transition);
        }
        .btn-filter-clear:hover {
            background: #E2E8F0;
            color: #2D3748;
        }
        .btn-download-pdf {
            padding: 8px 18px;
            background: #E79A32;
            color: #fff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: auto;
            box-shadow: 0 4px 12px rgba(231, 154, 50, 0.25);
            transition: var(--dash-transition);
        }
        .btn-download-pdf:hover {
            background: #D98822;
            color: #fff;
        }
    </style>
</head>
<body class="dash-body">

    <div class="dash-layout">
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
                <a href="<?php echo BASE_URL; ?>admin/orders.php" class="dash-nav-link">
                    <i class="fas fa-shopping-bag"></i> Orders
                </a>

                <span class="dash-nav-section" style="margin-top: 10px;">Analytics & System</span>
                <a href="<?php echo BASE_URL; ?>admin/reports.php" class="dash-nav-link active">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <a href="<?php echo BASE_URL; ?>admin/settings.php" class="dash-nav-link">
                    <i class="fas fa-sliders-h"></i> Settings
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
                        <i class="fas fa-chart-line"></i> Clinic & Pharmacy Reports
                        <span class="dash-header-badge">Admin Portal</span>
                    </div>
                </div>

                <div class="dash-header-right">
                    <a href="<?php echo BASE_URL; ?>index.php" class="dash-header-link" target="_blank" title="Open Public Website">
                        <i class="fas fa-external-link-alt"></i> View Website
                    </a>
                    <a href="<?php echo BASE_URL; ?>logout.php" class="dash-btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </header>

            <main class="dash-content">
                
                <!-- Page Title -->
                <div class="dash-page-header" style="margin-bottom: 20px;">
                    <div class="dash-page-header-text">
                        <h2>Executive Analytics & Reports</h2>
                        <p>Real-time database analytics for pharmacy transactions, clinic bookings, and inventory</p>
                    </div>
                    <div class="dash-header-actions">
                        <span class="dash-badge-outline" style="padding: 8px 16px; border-radius: 20px; font-weight: 600; background: #fff; border: 1px solid var(--dash-border); font-size: 13px; color: var(--dash-primary);">
                            <i class="fas fa-calendar-alt"></i> Period: <strong><?php echo $dateRange['from_display']; ?> – <?php echo $dateRange['to_display']; ?></strong>
                        </span>
                    </div>
                </div>

                <!-- Date Range Filter Form -->
                <div class="filter-card">
                    <form method="GET" action="reports.php" class="filter-form">
                        
                        <div class="filter-input-group">
                            <label for="fromDate"><i class="fas fa-calendar-day" style="color: var(--dash-primary);"></i> From Date:</label>
                            <input type="date" id="fromDate" name="from" value="<?php echo htmlspecialchars($from); ?>" class="filter-date-input" required>
                        </div>

                        <div class="filter-input-group">
                            <label for="toDate"><i class="fas fa-calendar-day" style="color: var(--dash-primary);"></i> To Date:</label>
                            <input type="date" id="toDate" name="to" value="<?php echo htmlspecialchars($to); ?>" class="filter-date-input" required>
                        </div>

                        <button type="submit" class="btn-filter-apply">
                            <i class="fas fa-filter"></i> Apply Filter
                        </button>

                        <a href="reports.php" class="btn-filter-clear">
                            <i class="fas fa-undo"></i> Current Month
                        </a>

                        <a href="<?php echo BASE_URL; ?>admin/report_pdf.php?from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>" target="_blank" class="btn-download-pdf" title="Export this report as a branded PDF">
                            <i class="fas fa-file-pdf"></i> Download PDF
                        </a>

                    </form>

                    <?php if (!empty($rangeError)): ?>
                        <div style="margin-top: 12px; padding: 10px 14px; background: #FDE8E8; color: #9B1C1C; border-radius: 8px; font-size: 12.5px; border: 1px solid #FBD5D5;">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($rangeError); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 1. Pharmacy & Financial Performance -->
                <h3 style="font-size: 15.5px; color: var(--dash-primary); margin-bottom: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-shopping-basket"></i> 1. Pharmacy Performance (<?php echo $dateRange['from_display']; ?> – <?php echo $dateRange['to_display']; ?>)
                </h3>
                <div class="report-section-grid">
                    
                    <div class="stat-card-custom">
                        <div class="stat-icon-custom" style="background: #EBF5FF; color: #1E429F;">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--dash-text-muted); font-weight: 600; text-transform: uppercase;">Total Orders</div>
                            <div style="font-size: 22px; font-weight: 700; color: #1E429F;"><?php echo $pharmacy['total_orders']; ?></div>
                        </div>
                    </div>

                    <div class="stat-card-custom">
                        <div class="stat-icon-custom" style="background: #DEF7EC; color: #03543F;">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--dash-text-muted); font-weight: 600; text-transform: uppercase;">Paid Revenue</div>
                            <div style="font-size: 22px; font-weight: 700; color: #03543F;">₹<?php echo number_format($pharmacy['paid_revenue'], 2); ?></div>
                            <small style="font-size: 11px; color: #718096;"><?php echo $pharmacy['paid_orders']; ?> paid orders</small>
                        </div>
                    </div>

                    <div class="stat-card-custom">
                        <div class="stat-icon-custom" style="background: #FEF3C7; color: #92400E;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--dash-text-muted); font-weight: 600; text-transform: uppercase;">Pending Payments</div>
                            <div style="font-size: 22px; font-weight: 700; color: #92400E;"><?php echo $pharmacy['pending_orders']; ?></div>
                            <small style="font-size: 11px; color: #718096;">₹<?php echo number_format($pharmacy['pending_revenue'], 2); ?> value</small>
                        </div>
                    </div>

                    <div class="stat-card-custom">
                        <div class="stat-icon-custom" style="background: #F3E8FF; color: #6B21A8;">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--dash-text-muted); font-weight: 600; text-transform: uppercase;">Delivered Orders</div>
                            <div style="font-size: 22px; font-weight: 700; color: #6B21A8;"><?php echo $pharmacy['delivered_orders']; ?></div>
                        </div>
                    </div>

                </div>

                <!-- 2. Clinical & Appointment Performance -->
                <h3 style="font-size: 15.5px; color: var(--dash-primary); margin-bottom: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user-md"></i> 2. Clinical Operations (<?php echo $dateRange['from_display']; ?> – <?php echo $dateRange['to_display']; ?>)
                </h3>
                <div class="report-section-grid">
                    
                    <div class="stat-card-custom">
                        <div class="stat-icon-custom" style="background: #EEF6EF; color: #355E3B;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--dash-text-muted); font-weight: 600; text-transform: uppercase;">Appointments Scheduled</div>
                            <div style="font-size: 22px; font-weight: 700; color: #355E3B;"><?php echo $clinic['total_appointments']; ?></div>
                            <small style="font-size: 11px; color: #718096;"><?php echo $clinic['active_appointments']; ?> active · <?php echo $clinic['cancelled_appointments']; ?> cancelled</small>
                        </div>
                    </div>

                    <div class="stat-card-custom">
                        <div class="stat-icon-custom" style="background: #DEF7EC; color: #03543F;">
                            <i class="fas fa-stethoscope"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--dash-text-muted); font-weight: 600; text-transform: uppercase;">Completed Consultations</div>
                            <div style="font-size: 22px; font-weight: 700; color: #03543F;"><?php echo $clinic['completed_consultations']; ?></div>
                        </div>
                    </div>

                    <div class="stat-card-custom">
                        <div class="stat-icon-custom" style="background: #FDF2F8; color: #9D174D;">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--dash-text-muted); font-weight: 600; text-transform: uppercase;">New Registered Patients</div>
                            <div style="font-size: 22px; font-weight: 700; color: #9D174D;"><?php echo $clinic['new_patients']; ?></div>
                            <small style="font-size: 11px; color: #718096;"><?php echo $clinic['total_patients_all_time']; ?> total all-time</small>
                        </div>
                    </div>

                </div>

                <!-- 3. Current Live Inventory Snapshot -->
                <h3 style="font-size: 15.5px; color: var(--dash-primary); margin-bottom: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-boxes"></i> 3. Current Inventory Snapshot (Live Storefront)
                </h3>
                <div class="report-section-grid">
                    
                    <div class="stat-card-custom">
                        <div class="stat-icon-custom" style="background: #EEF6EF; color: #355E3B;">
                            <i class="fas fa-pills"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--dash-text-muted); font-weight: 600; text-transform: uppercase;">Active Remedy Items</div>
                            <div style="font-size: 22px; font-weight: 700; color: #355E3B;"><?php echo $inventory['total_products']; ?> Products</div>
                        </div>
                    </div>

                    <div class="stat-card-custom">
                        <div class="stat-icon-custom" style="background: #FFFBEB; color: #B45309;">
                            <i class="fas fa-cubes"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--dash-text-muted); font-weight: 600; text-transform: uppercase;">Total Units in Stock</div>
                            <div style="font-size: 22px; font-weight: 700; color: #B45309;"><?php echo $inventory['current_units_in_stock']; ?> Units</div>
                        </div>
                    </div>

                    <div class="stat-card-custom">
                        <div class="stat-icon-custom" style="background: #FEF2F2; color: #991B1B;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: var(--dash-text-muted); font-weight: 600; text-transform: uppercase;">Low Stock Alerts</div>
                            <div style="font-size: 22px; font-weight: 700; color: #991B1B;"><?php echo $inventory['low_stock_products']; ?> Items</div>
                            <small style="font-size: 11px; color: #718096;">Threshold <= 5 units</small>
                        </div>
                    </div>

                </div>

                <!-- 4. Detailed Period Breakdowns -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px;">
                    
                    <!-- Period Orders Table Card -->
                    <div class="dash-card" style="background: #fff; padding: 22px; border-radius: 12px; border: 1px solid var(--dash-border); box-shadow: var(--dash-shadow);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <h4 style="color: var(--dash-primary); font-size: 15px; margin: 0; font-weight: 700;">
                                <i class="fas fa-receipt"></i> Orders in Period (<?php echo count($pharmacy['orders_list']); ?>)
                            </h4>
                            <a href="<?php echo BASE_URL; ?>admin/orders.php" style="font-size: 12px; color: var(--dash-primary); text-decoration: none; font-weight: 600;">View All Orders &rarr;</a>
                        </div>

                        <?php if (empty($pharmacy['orders_list'])): ?>
                            <div style="text-align: center; padding: 35px 15px; color: #718096; background: #FAF9F6; border-radius: 8px; border: 1px dashed #CBD5E0;">
                                <i class="fas fa-shopping-bag" style="font-size: 28px; color: #CBD5E0; margin-bottom: 8px; display: block;"></i>
                                <strong style="font-size: 13.5px; color: #2D3748;">No orders recorded in this date range</strong>
                                <p style="font-size: 12px; color: #A0AEC0; margin: 4px 0 0 0;">New orders placed during this period will display here.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid #E2E8F0; text-align: left;">
                                            <th style="padding: 8px 10px; color: #718096;">Order ID</th>
                                            <th style="padding: 8px 10px; color: #718096;">Customer</th>
                                            <th style="padding: 8px 10px; color: #718096; text-align: right;">Amount</th>
                                            <th style="padding: 8px 10px; color: #718096; text-align: center;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pharmacy['orders_list'] as $o): ?>
                                            <tr style="border-bottom: 1px solid #EDF2F7;">
                                                <td style="padding: 8px 10px; font-weight: 600;">
                                                    <a href="<?php echo BASE_URL; ?>admin/order_details.php?order_id=<?php echo $o['id']; ?>" style="color: var(--dash-primary); text-decoration: none;">
                                                        #ORD-<?php echo str_pad($o['id'], 4, '0', STR_PAD_LEFT); ?>
                                                    </a>
                                                </td>
                                                <td style="padding: 8px 10px; color: #2D3748;"><?php echo htmlspecialchars($o['full_name']); ?></td>
                                                <td style="padding: 8px 10px; text-align: right; font-weight: 700; color: #03543F;">₹<?php echo number_format($o['total_amount'], 2); ?></td>
                                                <td style="padding: 8px 10px; text-align: center;">
                                                    <span style="font-size: 11px; padding: 2px 7px; border-radius: 10px; font-weight: 600; <?php echo $o['payment_status'] === 'Paid' ? 'background: #DEF7EC; color: #03543F;' : 'background: #FEF3C7; color: #92400E;'; ?>">
                                                        <?php echo htmlspecialchars($o['payment_status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Period Appointments Table Card -->
                    <div class="dash-card" style="background: #fff; padding: 22px; border-radius: 12px; border: 1px solid var(--dash-border); box-shadow: var(--dash-shadow);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <h4 style="color: var(--dash-primary); font-size: 15px; margin: 0; font-weight: 700;">
                                <i class="fas fa-calendar-alt"></i> Appointments in Period (<?php echo count($clinic['appointments_list']); ?>)
                            </h4>
                            <a href="<?php echo BASE_URL; ?>admin/appointments.php" style="font-size: 12px; color: var(--dash-primary); text-decoration: none; font-weight: 600;">View All Appointments &rarr;</a>
                        </div>

                        <?php if (empty($clinic['appointments_list'])): ?>
                            <div style="text-align: center; padding: 35px 15px; color: #718096; background: #FAF9F6; border-radius: 8px; border: 1px dashed #CBD5E0;">
                                <i class="fas fa-calendar-times" style="font-size: 28px; color: #CBD5E0; margin-bottom: 8px; display: block;"></i>
                                <strong style="font-size: 13.5px; color: #2D3748;">No appointments in this date range</strong>
                                <p style="font-size: 12px; color: #A0AEC0; margin: 4px 0 0 0;">Appointments booked for these dates will display here.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid #E2E8F0; text-align: left;">
                                            <th style="padding: 8px 10px; color: #718096;">Appt ID</th>
                                            <th style="padding: 8px 10px; color: #718096;">Patient</th>
                                            <th style="padding: 8px 10px; color: #718096;">Date & Time</th>
                                            <th style="padding: 8px 10px; color: #718096; text-align: center;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clinic['appointments_list'] as $a): ?>
                                            <tr style="border-bottom: 1px solid #EDF2F7;">
                                                <td style="padding: 8px 10px; font-weight: 600;">
                                                    #APP-<?php echo str_pad($a['id'], 4, '0', STR_PAD_LEFT); ?>
                                                </td>
                                                <td style="padding: 8px 10px; color: #2D3748;"><?php echo htmlspecialchars($a['patient_name']); ?></td>
                                                <td style="padding: 8px 10px; color: #718096;">
                                                    <?php echo date('d M Y', strtotime($a['appointment_date'])); ?>
                                                    <small style="display: block; color: var(--dash-primary);"><?php echo htmlspecialchars($a['time_slot'] ?? '10:00 AM'); ?></small>
                                                </td>
                                                <td style="padding: 8px 10px; text-align: center;">
                                                    <span style="font-size: 11px; padding: 2px 7px; border-radius: 10px; font-weight: 600; <?php echo $a['status'] === 'Completed' ? 'background: #DEF7EC; color: #03543F;' : ($a['status'] === 'Confirmed' ? 'background: #EEF6EF; color: #355E3B;' : 'background: #FEF3C7; color: #92400E;'); ?>">
                                                        <?php echo htmlspecialchars($a['status'] ?? 'Booked'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

            </main>
        </div>
    </div>

    <script>
    function toggleSidebar() {
        document.getElementById('dashSidebar').classList.toggle('active');
    }
    </script>
</body>
</html>
