<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: strictly enforce Admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Appointments Management | Bodhi Homeopathy";
$today = date('Y-m-d');

// Quick status update POST handler
$msg = '';
$msgType = '';
date_default_timezone_set('Asia/Kolkata');
$todayDateStr = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        $msg = "Invalid security token. Please try again.";
        $msgType = "error";
    } else {
        $appId = (int)($_POST['appointment_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $validStatuses = ['Booked', 'Confirmed', 'Completed', 'Cancelled', 'Pending'];

        if ($appId > 0 && in_array($newStatus, $validStatuses, true)) {
            try {
                $checkStmt = $pdo->prepare("SELECT `appointment_date` FROM `appointment` WHERE `id` = ?");
                $checkStmt->execute([$appId]);
                $apptDate = $checkStmt->fetchColumn();

                if ($apptDate !== $todayDateStr) {
                    $msg = "Only today's appointments can be updated.";
                    $msgType = "error";
                } else {
                    $stmt = $pdo->prepare("UPDATE `appointment` SET `status` = ? WHERE `id` = ?");
                    $stmt->execute([$newStatus, $appId]);
                    $msg = "Appointment #{$appId} status updated to '{$newStatus}' successfully.";
                    $msgType = "success";
                }
            } catch (Exception $e) {
                error_log("Failed to update appointment status: " . $e->getMessage());
                $msg = "Failed to update status. Please try again.";
                $msgType = "error";
            }
        }
    }
}

// Search and filter parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$dateFilter = trim($_GET['date_filter'] ?? '');

try {
    // 1. Live Database Summary Statistics
    $stmt = $pdo->query("SELECT COUNT(*) FROM `appointment`");
    $totalAppointments = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `appointment` WHERE `appointment_date` = ?");
    $stmt->execute([$today]);
    $todayAppointments = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `appointment` WHERE `status` IN ('Booked', 'Pending')");
    $bookedCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `appointment` WHERE `status` = 'Confirmed'");
    $confirmedCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `appointment` WHERE `status` = 'Completed'");
    $completedCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `appointment` WHERE `status` = 'Cancelled'");
    $cancelledCount = (int)$stmt->fetchColumn();

    // 2. Build Filtered Query
    $query = "SELECT * FROM `appointment` WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $query .= " AND (`patient_name` LIKE ? OR `phone` LIKE ? OR `notes` LIKE ? OR `category` LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if (!empty($statusFilter) && $statusFilter !== 'all') {
        $query .= " AND `status` = ?";
        $params[] = $statusFilter;
    }

    if ($dateFilter === 'today') {
        $query .= " AND `appointment_date` = ?";
        $params[] = $today;
    } elseif ($dateFilter === 'upcoming') {
        $query .= " AND `appointment_date` >= ?";
        $params[] = $today;
    } elseif ($dateFilter === 'past') {
        $query .= " AND `appointment_date` < ?";
        $params[] = $today;
    }

    $query .= " ORDER BY `appointment_date` DESC, `id` DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $appointmentsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $totalAppointments = 0;
    $todayAppointments = 0;
    $bookedCount = 0;
    $confirmedCount = 0;
    $completedCount = 0;
    $cancelledCount = 0;
    $appointmentsList = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard.css?v=1.0.4">
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
        .alert-banner {
            padding: 12px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
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
                <a href="<?php echo BASE_URL; ?>admin/appointments.php" class="dash-nav-link active">
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
                        <div class="dash-user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Administrator'); ?></div>
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
                    <button class="dash-menu-btn" onclick="toggleSidebar()" aria-label="Toggle navigation">
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

            <!-- Main Content Body -->
            <main class="dash-body">
                
                <!-- Flash Notification Banner -->
                <?php if (!empty($msg)): ?>
                    <div class="alert-banner <?php echo $msgType === 'success' ? 'alert-success' : 'alert-error'; ?>">
                        <i class="fas <?php echo $msgType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        <span><?php echo htmlspecialchars($msg); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Page Heading -->
                <div class="dash-page-heading">
                    <div class="dash-page-title">
                        <h1>APPOINTMENTS</h1>
                        <p>Manage clinical consultation schedules, bookings, and appointment statuses.</p>
                    </div>
                    <div class="dash-date-pill">
                        <i class="fas fa-calendar-day"></i>
                        <span>Today: <?php echo date('d M Y'); ?> (<?php echo $todayAppointments; ?> Scheduled)</span>
                    </div>
                </div>

                <!-- 1. Real Database Summary Cards -->
                <section class="dash-stats-grid" aria-label="Appointment Statistics">
                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Total Appointments</span>
                            <span class="dash-stat-value"><?php echo $totalAppointments; ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-database"></i> All Recorded</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-green">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Today's Schedule</span>
                            <span class="dash-stat-value"><?php echo $todayAppointments; ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-clock"></i> Scheduled Today</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-orange">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Booked / Pending</span>
                            <span class="dash-stat-value"><?php echo $bookedCount; ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-hourglass-half"></i> Action Required</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-blue">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Confirmed / Done</span>
                            <span class="dash-stat-value"><?php echo ($confirmedCount + $completedCount); ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-check-double"></i> Active / Completed</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-purple">
                            <i class="fas fa-stethoscope"></i>
                        </div>
                    </div>
                </section>

                <!-- 2. Appointments Directory Card with Search & Filters -->
                <section class="dash-card">
                    <div class="dash-card-header">
                        <div>
                            <h2 class="dash-card-title"><i class="fas fa-list-alt"></i> Clinical Appointments Schedule</h2>
                            <span class="dash-card-subtitle">Real-time consultation bookings from patients</span>
                        </div>

                        <!-- Search Form -->
                        <form method="GET" action="appointments.php" class="dash-search-bar-wrap">
                            <?php if (!empty($statusFilter)): ?>
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                            <?php endif; ?>
                            <?php if (!empty($dateFilter)): ?>
                                <input type="hidden" name="date_filter" value="<?php echo htmlspecialchars($dateFilter); ?>">
                            <?php endif; ?>

                            <div class="dash-search-box">
                                <i class="fas fa-search dash-search-icon"></i>
                                <input type="text" 
                                       name="search" 
                                       class="dash-search-input" 
                                       placeholder="Search by patient name, phone, or concern..." 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       aria-label="Search appointments">
                                <?php if (!empty($search)): ?>
                                    <a href="appointments.php<?php echo !empty($statusFilter) ? '?status=' . urlencode($statusFilter) : ''; ?>" class="dash-search-clear" title="Clear search">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <button type="submit" class="dash-btn-search">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </form>
                    </div>

                    <!-- Status Filter Chips -->
                    <div class="filter-chip-group">
                        <span style="font-size: 12px; font-weight: 600; color: var(--dash-text-muted); margin-right: 4px;">Status:</span>
                        <a href="appointments.php?<?php echo http_build_query(array_merge($_GET, ['status' => 'all'])); ?>" class="filter-chip <?php echo empty($statusFilter) || $statusFilter === 'all' ? 'active' : ''; ?>">
                            All (<?php echo $totalAppointments; ?>)
                        </a>
                        <a href="appointments.php?<?php echo http_build_query(array_merge($_GET, ['status' => 'Booked'])); ?>" class="filter-chip <?php echo $statusFilter === 'Booked' ? 'active' : ''; ?>">
                            <i class="fas fa-bookmark" style="color: #2563EB;"></i> Booked (<?php echo $bookedCount; ?>)
                        </a>
                        <a href="appointments.php?<?php echo http_build_query(array_merge($_GET, ['status' => 'Confirmed'])); ?>" class="filter-chip <?php echo $statusFilter === 'Confirmed' ? 'active' : ''; ?>">
                            <i class="fas fa-check-circle" style="color: var(--dash-primary);"></i> Confirmed (<?php echo $confirmedCount; ?>)
                        </a>
                        <a href="appointments.php?<?php echo http_build_query(array_merge($_GET, ['status' => 'Completed'])); ?>" class="filter-chip <?php echo $statusFilter === 'Completed' ? 'active' : ''; ?>">
                            <i class="fas fa-check-double" style="color: #03543F;"></i> Completed (<?php echo $completedCount; ?>)
                        </a>
                        <a href="appointments.php?<?php echo http_build_query(array_merge($_GET, ['status' => 'Cancelled'])); ?>" class="filter-chip <?php echo $statusFilter === 'Cancelled' ? 'active' : ''; ?>">
                            <i class="fas fa-ban" style="color: #DC2626;"></i> Cancelled (<?php echo $cancelledCount; ?>)
                        </a>
                    </div>

                    <!-- Appointments Table -->
                    <div class="dash-table-wrapper">
                        <?php if (!empty($appointmentsList)): ?>
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Patient</th>
                                        <th>Phone</th>
                                        <th>Appointment Date</th>
                                        <th>Time Slot</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointmentsList as $app): ?>
                                        <tr>
                                            <td>
                                                <span style="font-weight: 700; color: var(--dash-primary);">
                                                    #APP-<?php echo str_pad($app['id'], 4, '0', STR_PAD_LEFT); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div class="dash-avatar-circle" style="width: 32px; height: 32px; font-size: 12px;">
                                                        <?php 
                                                            $pName = $app['patient_name'] ?? 'P';
                                                            echo htmlspecialchars(strtoupper(substr($pName, 0, 1)));
                                                        ?>
                                                    </div>
                                                    <div>
                                                        <strong style="color: var(--dash-text-dark); font-size: 13.5px;"><?php echo htmlspecialchars($app['patient_name'] ?? 'N/A'); ?></strong>
                                                        <?php if (!empty($app['user_id'])): ?>
                                                            <div style="font-size: 11px; color: var(--dash-text-muted);">
                                                                <a href="<?php echo BASE_URL; ?>admin/patient_view.php?id=<?php echo (int)$app['user_id']; ?>" style="color: var(--dash-primary); text-decoration: none;">
                                                                    <i class="fas fa-user-check"></i> Registered Patient #PT-<?php echo (int)$app['user_id']; ?>
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars(!empty($app['phone']) ? $app['phone'] : 'N/A'); ?></td>
                                            <td>
                                                <strong style="color: var(--dash-text-dark);">
                                                    <?php echo !empty($app['appointment_date']) ? date('d M Y', strtotime($app['appointment_date'])) : 'N/A'; ?>
                                                </strong>
                                                <?php if (!empty($app['appointment_date']) && $app['appointment_date'] === $today): ?>
                                                    <span style="display: block; font-size: 10.5px; color: var(--dash-secondary); font-weight: 700;">● TODAY</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500; color: #2D3748;"><?php echo htmlspecialchars(!empty($app['time_slot']) ? $app['time_slot'] : 'Regular Hours'); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars(!empty($app['category']) ? $app['category'] : 'General'); ?></td>
                                            <td>
                                                <?php 
                                                    $st = strtolower($app['status'] ?? 'booked');
                                                    $badgeClass = 'badge-booked';
                                                    if ($st === 'confirmed') $badgeClass = 'badge-confirmed';
                                                    else if ($st === 'completed') $badgeClass = 'badge-confirmed';
                                                    else if ($st === 'cancelled') $badgeClass = 'badge-cancelled';
                                                    else if ($st === 'pending') $badgeClass = 'badge-pending';
                                                ?>
                                                <form method="POST" action="appointments.php" class="quick-status-form">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="appointment_id" value="<?php echo (int)$app['id']; ?>">
                                                    <select name="status" class="quick-status-select" onchange="this.form.submit()" title="Change Status">
                                                        <option value="Booked" <?php echo $app['status'] === 'Booked' ? 'selected' : ''; ?>>Booked</option>
                                                        <option value="Confirmed" <?php echo $app['status'] === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                        <option value="Completed" <?php echo $app['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                        <option value="Cancelled" <?php echo $app['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                    </select>
                                                </form>
                                            </td>
                                            <td>
                                                <a href="<?php echo BASE_URL; ?>admin/appointment_view.php?id=<?php echo (int)$app['id']; ?>" class="dash-btn-view" title="View Details">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="dash-empty-state">
                                <i class="fas fa-calendar-times dash-empty-icon"></i>
                                <div class="dash-empty-title">No appointments found.</div>
                                <div class="dash-empty-desc">
                                    <?php if (!empty($search) || !empty($statusFilter)): ?>
                                        No appointments match your search criteria.
                                        <div style="margin-top: 12px;">
                                            <a href="appointments.php" class="dash-btn-back"><i class="fas fa-arrow-left"></i> View All Appointments</a>
                                        </div>
                                    <?php else: ?>
                                        There are currently no recorded appointment bookings in the database.
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

            </main>
        </div>
    </div>

    <!-- Responsive Sidebar Toggle Script -->
    <script>
        function toggleSidebar() {
            var sidebar = document.getElementById('dashSidebar');
            var backdrop = document.getElementById('dashBackdrop');
            if (sidebar && backdrop) {
                sidebar.classList.toggle('active');
                backdrop.classList.toggle('active');
            }
        }
    </script>
</body>
</html>
