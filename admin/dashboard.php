<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: strictly enforce Admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Admin Dashboard | Bodhi Homeopathy";
$today = date('Y-m-d');

// --- DATABASE-DRIVEN STATISTICS ---
try {
    // 1. Today's Appointments Count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `appointment` WHERE `appointment_date` = ? AND `status` != 'Cancelled'");
    $stmt->execute([$today]);
    $todayAppointmentsCount = (int)$stmt->fetchColumn();

    // 2. Total Registered Patients
    $stmt = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'Patient'");
    $totalPatients = (int)$stmt->fetchColumn();

    // 3. Pending Orders Count
    $stmt = $pdo->query("SELECT COUNT(*) FROM `orders` WHERE `order_status` IN ('Pending', 'Processing')");
    $pendingOrdersCount = (int)$stmt->fetchColumn();

    // 4. Low Stock Medicines Count (stock <= 10)
    $stmt = $pdo->query("SELECT COUNT(*) FROM `products` WHERE `stock_quantity` <= 10 AND `status` = 'Active'");
    $lowStockMedicinesCount = (int)$stmt->fetchColumn();

    // 5. Today's Appointments List
    $stmt = $pdo->prepare("SELECT * FROM `appointment` WHERE `appointment_date` = ? ORDER BY `id` DESC");
    $stmt->execute([$today]);
    $todayAppointmentsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $todayAppointmentsCount = 0;
    $totalPatients = 0;
    $pendingOrdersCount = 0;
    $lowStockMedicinesCount = 0;
    $todayAppointmentsList = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard.css?v=1.0.2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="dash-nav-link active">
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
                        <i class="fas fa-clinic-medical"></i> Clinic Administration
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

            <!-- Dashboard Content Body -->
            <main class="dash-body">
                
                <!-- Page Heading -->
                <div class="dash-page-heading">
                    <div class="dash-page-title">
                        <h1>Admin Dashboard</h1>
                        <p>Clinic overview and daily operations</p>
                    </div>
                    <div class="dash-date-pill">
                        <i class="far fa-calendar-alt"></i>
                        <span><?php echo date('l, F j, Y'); ?></span>
                    </div>
                </div>

                <!-- 4 Overview Metric Cards -->
                <div class="dash-stats-grid">
                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Today's Appointments</span>
                            <span class="dash-stat-value"><?php echo number_format($todayAppointmentsCount); ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-calendar-day"></i> Active daily queue</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-green">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Total Patients</span>
                            <span class="dash-stat-value"><?php echo number_format($totalPatients); ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-users"></i> Registered patient accounts</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-blue">
                            <i class="fas fa-user-injured"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Pending Orders</span>
                            <span class="dash-stat-value"><?php echo number_format($pendingOrdersCount); ?></span>
                            <span class="dash-stat-subtext" style="color: var(--dash-secondary);"><i class="fas fa-truck-loading"></i> Awaiting dispatch</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-orange">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Low Stock Medicines</span>
                            <span class="dash-stat-value"><?php echo number_format($lowStockMedicinesCount); ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-exclamation-triangle"></i> Stock <= 10</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-purple">
                            <i class="fas fa-pills"></i>
                        </div>
                    </div>
                </div>

                <!-- Section: Today's Appointments Table -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <div>
                            <div class="dash-card-title">
                                <i class="fas fa-calendar-check"></i> Today's Appointments
                            </div>
                            <div class="dash-card-subtitle">Real-time schedule for <?php echo date('F j, Y'); ?></div>
                        </div>
                    </div>
                    
                    <div class="dash-table-wrapper">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Time Slot</th>
                                    <th>Mode</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($todayAppointmentsList)): ?>
                                    <?php foreach ($todayAppointmentsList as $app): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($app['patient_name']); ?></strong><br>
                                                <small style="color: #718096;"><?php echo htmlspecialchars($app['phone'] ?? ''); ?></small>
                                            </td>
                                            <td>Dr. Shybu Raj</td>
                                            <td><?php echo htmlspecialchars($app['time_slot'] ?? '10:00 AM - 11:00 AM'); ?></td>
                                            <td><span style="font-weight: 500;"><?php echo htmlspecialchars($app['mode'] ?? 'Direct Clinic Visit'); ?></span></td>
                                            <td><?php echo htmlspecialchars($app['category'] ?? 'General Health'); ?></td>
                                            <td>
                                                <?php 
                                                $status = $app['status'] ?? 'Booked';
                                                $badgeClass = 'badge-booked';
                                                $badgeIcon = 'fa-calendar';
                                                if (strtolower($status) === 'confirmed') {
                                                    $badgeClass = 'badge-confirmed';
                                                    $badgeIcon = 'fa-check';
                                                } elseif (strtolower($status) === 'completed') {
                                                    $badgeClass = 'badge-completed';
                                                    $badgeIcon = 'fa-check-double';
                                                }
                                                ?>
                                                <span class="badge-status <?php echo $badgeClass; ?>">
                                                    <i class="fas <?php echo $badgeIcon; ?>"></i> <?php echo htmlspecialchars($status); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 40px 20px; color: #718096;">
                                            <i class="fas fa-calendar-times" style="font-size: 28px; color: #A0AEC0; margin-bottom: 10px; display: block;"></i>
                                            <strong>No appointments scheduled for today.</strong>
                                            <div style="font-size: 12.5px; color: #A0AEC0; margin-top: 4px;">New bookings made by patients will appear here in real-time.</div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Section: Recent Activity & 4 Core Management Areas -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
                    
                    <!-- Recent Activity -->
                    <div class="dash-card">
                        <div class="dash-card-header">
                            <div>
                                <div class="dash-card-title">
                                    <i class="fas fa-history"></i> Recent Activity
                                </div>
                                <div class="dash-card-subtitle">Latest administrative operations</div>
                            </div>
                        </div>

                        <div class="dash-timeline">
                            <div style="text-align: center; padding: 40px 20px; color: #718096;">
                                <i class="fas fa-history" style="font-size: 30px; color: #CBD5E0; margin-bottom: 10px; display: block;"></i>
                                <p style="margin: 0; font-size: 13.5px; font-weight: 500;">No recent administrative activity.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Core Management Areas -->
                    <div class="dash-card">
                        <div class="dash-card-header">
                            <div>
                                <div class="dash-card-title">
                                    <i class="fas fa-cubes"></i> Core Management Areas
                                </div>
                                <div class="dash-card-subtitle">Hospital administration overview</div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                            <div class="dash-concept-card" style="padding: 16px;">
                                <div class="dash-concept-header">
                                    <div class="dash-concept-icon"><i class="fas fa-users"></i></div>
                                    <div class="dash-concept-title" style="font-size: 14px;">User Records</div>
                                </div>
                                <div class="dash-concept-desc" style="font-size: 12px;">Review accounts, contact details, and logs.</div>
                                <div class="dash-concept-meta"><?php echo number_format($totalPatients); ?> Patients</div>
                            </div>

                            <div class="dash-concept-card" style="padding: 16px;">
                                <div class="dash-concept-header">
                                    <div class="dash-concept-icon" style="background: var(--dash-secondary-light); color: var(--dash-secondary);"><i class="fas fa-calendar-alt"></i></div>
                                    <div class="dash-concept-title" style="font-size: 14px;">Appointments</div>
                                </div>
                                <div class="dash-concept-desc" style="font-size: 12px;">Slot management and daily 10-booking limits.</div>
                                <div class="dash-concept-meta"><?php echo number_format($todayAppointmentsCount); ?> Today</div>
                            </div>

                            <div class="dash-concept-card" style="padding: 16px;">
                                <div class="dash-concept-header">
                                    <div class="dash-concept-icon" style="background: #EFF6FF; color: #2563EB;"><i class="fas fa-pills"></i></div>
                                    <div class="dash-concept-title" style="font-size: 14px;">Store & Stock</div>
                                </div>
                                <div class="dash-concept-desc" style="font-size: 12px;">Medicine inventory and price controls.</div>
                                <div class="dash-concept-meta"><?php echo number_format($totalProducts); ?> Formulations</div>
                            </div>

                            <div class="dash-concept-card" style="padding: 16px;">
                                <div class="dash-concept-header">
                                    <div class="dash-concept-icon" style="background: #F5F3FF; color: #7C3AED;"><i class="fas fa-chart-pie"></i></div>
                                    <div class="dash-concept-title" style="font-size: 14px;">Reports & Orders</div>
                                </div>
                                <div class="dash-concept-desc" style="font-size: 12px;">Financial statements and courier routes.</div>
                                <div class="dash-concept-meta"><?php echo number_format($totalOrders); ?> Orders</div>
                            </div>
                        </div>
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
