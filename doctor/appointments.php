<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: strictly enforce Doctor role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Today's Appointments | Bodhi Homeopathy";
$today = date('Y-m-d');
$doctorId = (int)$_SESSION['user_id'];

// Search and filter parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

try {
    // 1. Fetch Today's Queue (Active Queue by default, exclude Cancelled unless filtered)
    $todayQuery = "SELECT * FROM `appointment` WHERE `appointment_date` = ?";
    $todayParams = [$today];

    if (!empty($search)) {
        $todayQuery .= " AND (`patient_name` LIKE ? OR `phone` LIKE ? OR `category` LIKE ?)";
        $like = '%' . $search . '%';
        $todayParams[] = $like;
        $todayParams[] = $like;
        $todayParams[] = $like;
    }

    if (!empty($statusFilter) && $statusFilter !== 'all') {
        $todayQuery .= " AND `status` = ?";
        $todayParams[] = $statusFilter;
    } else {
        $todayQuery .= " AND `status` != 'Cancelled'";
    }

    $todayQuery .= " ORDER BY `id` DESC";
    $stmt = $pdo->prepare($todayQuery);
    $stmt->execute($todayParams);
    $todayAppointmentsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Upcoming / Other Appointments
    $otherQuery = "SELECT * FROM `appointment` WHERE `appointment_date` != ?";
    $otherParams = [$today];

    if (!empty($search)) {
        $otherQuery .= " AND (`patient_name` LIKE ? OR `phone` LIKE ? OR `category` LIKE ?)";
        $like = '%' . $search . '%';
        $otherParams[] = $like;
        $otherParams[] = $like;
        $otherParams[] = $like;
    }

    if (!empty($statusFilter) && $statusFilter !== 'all') {
        $otherQuery .= " AND `status` = ?";
        $otherParams[] = $statusFilter;
    }

    $otherQuery .= " ORDER BY `appointment_date` ASC, `id` ASC";
    $stmt = $pdo->prepare($otherQuery);
    $stmt->execute($otherParams);
    $otherAppointmentsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Summary Counts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `appointment` WHERE `appointment_date` = ? AND `status` != 'Cancelled'");
    $stmt->execute([$today]);
    $todayCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `appointment` WHERE `status` IN ('Booked', 'Confirmed')");
    $activeCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `appointment` WHERE `status` = 'Completed'");
    $completedCount = (int)$stmt->fetchColumn();

} catch (Exception $e) {
    $todayAppointmentsList = [];
    $otherAppointmentsList = [];
    $todayCount = 0;
    $activeCount = 0;
    $completedCount = 0;
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
            margin-bottom: 16px;
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
                    <span class="dash-brand-title">Doctor Portal</span>
                    <span class="dash-brand-subtitle">Clinical Management</span>
                </div>
            </div>

            <nav class="dash-nav">
                <span class="dash-nav-section">Clinical Practice</span>
                <a href="<?php echo BASE_URL; ?>doctor/dashboard.php" class="dash-nav-link">
                    <i class="fas fa-stethoscope"></i> Dashboard
                </a>
                <a href="<?php echo BASE_URL; ?>doctor/appointments.php" class="dash-nav-link active">
                    <i class="fas fa-calendar-day"></i> Today's Appointments
                </a>
                <a href="<?php echo BASE_URL; ?>doctor/patients.php" class="dash-nav-link">
                    <i class="fas fa-user-injured"></i> Patients
                </a>
                <a href="<?php echo BASE_URL; ?>doctor/prescriptions.php" class="dash-nav-link">
                    <i class="fas fa-file-prescription"></i> Prescriptions
                </a>

                <span class="dash-nav-section" style="margin-top: 10px;">Preferences</span>
                <a href="<?php echo BASE_URL; ?>doctor/settings.php" class="dash-nav-link">
                    <i class="fas fa-user-cog"></i> Settings
                </a>
            </nav>

            <div class="dash-sidebar-footer">
                <div class="dash-user-badge">
                    <div class="dash-avatar-circle" style="background: var(--dash-secondary-light); color: var(--dash-secondary);">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="dash-user-info">
                        <div class="dash-user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Dr. Shybu Raj'); ?></div>
                        <div class="dash-user-role">Chief Physician</div>
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
                        <i class="fas fa-user-md"></i> Doctor Portal
                        <span class="dash-header-badge" style="background: var(--dash-secondary-light); color: var(--dash-secondary);">Chief Physician</span>
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
                
                <!-- Page Heading -->
                <div class="dash-page-heading">
                    <div class="dash-page-title">
                        <h1>TODAY'S APPOINTMENTS</h1>
                        <p>View and manage your scheduled patient consultations.</p>
                    </div>
                    <div class="dash-date-pill">
                        <i class="fas fa-calendar-day"></i>
                        <span>Today: <?php echo date('d M Y'); ?> (<?php echo $todayCount; ?> Scheduled)</span>
                    </div>
                </div>

                <!-- 1. Real Database Summary Cards -->
                <section class="dash-stats-grid" aria-label="Clinical Statistics">
                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Today's Queue</span>
                            <span class="dash-stat-value"><?php echo $todayCount; ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-clock"></i> Scheduled Today</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-green">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Active / Pending</span>
                            <span class="dash-stat-value"><?php echo $activeCount; ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-hourglass-half"></i> Ready for Consultation</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-orange">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Completed Consultations</span>
                            <span class="dash-stat-value"><?php echo $completedCount; ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-check-double"></i> Prescriptions Issued</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-blue">
                            <i class="fas fa-notes-medical"></i>
                        </div>
                    </div>
                </section>

                <!-- Search and Filters Section -->
                <section class="dash-card">
                    <div class="dash-card-header">
                        <div>
                            <h2 class="dash-card-title"><i class="fas fa-search"></i> Filter Clinical Appointments</h2>
                            <span class="dash-card-subtitle">Filter by patient name, contact phone, or consultation status</span>
                        </div>

                        <form method="GET" action="appointments.php" class="dash-search-bar-wrap">
                            <?php if (!empty($statusFilter)): ?>
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                            <?php endif; ?>

                            <div class="dash-search-box">
                                <i class="fas fa-search dash-search-icon"></i>
                                <input type="text" 
                                       name="search" 
                                       class="dash-search-input" 
                                       placeholder="Search patient name, phone..." 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       aria-label="Search appointments">
                                <?php if (!empty($search)): ?>
                                    <a href="appointments.php" class="dash-search-clear" title="Clear search">
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
                            All Statuses
                        </a>
                        <a href="appointments.php?<?php echo http_build_query(array_merge($_GET, ['status' => 'Booked'])); ?>" class="filter-chip <?php echo $statusFilter === 'Booked' ? 'active' : ''; ?>">
                            Booked
                        </a>
                        <a href="appointments.php?<?php echo http_build_query(array_merge($_GET, ['status' => 'Confirmed'])); ?>" class="filter-chip <?php echo $statusFilter === 'Confirmed' ? 'active' : ''; ?>">
                            Confirmed
                        </a>
                        <a href="appointments.php?<?php echo http_build_query(array_merge($_GET, ['status' => 'Completed'])); ?>" class="filter-chip <?php echo $statusFilter === 'Completed' ? 'active' : ''; ?>">
                            Completed
                        </a>
                        <a href="appointments.php?<?php echo http_build_query(array_merge($_GET, ['status' => 'Cancelled'])); ?>" class="filter-chip <?php echo $statusFilter === 'Cancelled' ? 'active' : ''; ?>">
                            Cancelled
                        </a>
                    </div>
                </section>

                <!-- 2. Today's Appointments Section -->
                <section class="dash-card" style="margin-top: 20px;">
                    <div class="dash-card-header">
                        <div>
                            <h2 class="dash-card-title"><i class="fas fa-calendar-day" style="color: var(--dash-primary);"></i> Today's Schedule Queue</h2>
                            <span class="dash-card-subtitle">Scheduled consultations for <?php echo date('d M Y'); ?></span>
                        </div>
                    </div>

                    <div class="dash-table-wrapper">
                        <?php if (!empty($todayAppointmentsList)): ?>
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th>Time Slot</th>
                                        <th>Patient</th>
                                        <th>Phone</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($todayAppointmentsList as $app): ?>
                                        <tr>
                                            <td>
                                                <strong style="color: var(--dash-text-dark);">
                                                    <?php echo htmlspecialchars(!empty($app['time_slot']) ? $app['time_slot'] : 'Regular Hours'); ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <strong style="color: var(--dash-text-dark); font-size: 13.5px;"><?php echo htmlspecialchars($app['patient_name'] ?? 'N/A'); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars(!empty($app['phone']) ? $app['phone'] : 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars(!empty($app['category']) ? $app['category'] : 'General'); ?></td>
                                            <td>
                                                <?php 
                                                    $st = strtolower($app['status'] ?? 'booked');
                                                    $badgeClass = 'badge-booked';
                                                    if ($st === 'confirmed' || $st === 'completed') $badgeClass = 'badge-confirmed';
                                                    else if ($st === 'cancelled') $badgeClass = 'badge-cancelled';
                                                    else if ($st === 'pending') $badgeClass = 'badge-pending';
                                                ?>
                                                <span class="badge-status <?php echo $badgeClass; ?>">
                                                    <?php echo htmlspecialchars($app['status'] ?? 'Booked'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?php echo BASE_URL; ?>doctor/appointment_detail.php?id=<?php echo (int)$app['id']; ?>" class="dash-btn-view">
                                                    <i class="fas fa-stethoscope"></i> View / Consult
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="dash-empty-state" style="padding: 30px 15px;">
                                <i class="fas fa-calendar-check dash-empty-icon"></i>
                                <div class="dash-empty-title">No appointments scheduled for today.</div>
                                <div class="dash-empty-desc">There are no patient consultations queued for today's date.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- 3. Upcoming / Other Appointments Section -->
                <section class="dash-card" style="margin-top: 24px;">
                    <div class="dash-card-header">
                        <div>
                            <h2 class="dash-card-title"><i class="fas fa-history" style="color: var(--dash-secondary);"></i> All & Upcoming Appointments</h2>
                            <span class="dash-card-subtitle">Historical and upcoming patient consultation bookings</span>
                        </div>
                    </div>

                    <div class="dash-table-wrapper">
                        <?php if (!empty($otherAppointmentsList)): ?>
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time Slot</th>
                                        <th>Patient</th>
                                        <th>Phone</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($otherAppointmentsList as $app): ?>
                                        <tr>
                                            <td>
                                                <strong style="color: var(--dash-primary);">
                                                    <?php echo !empty($app['appointment_date']) ? date('d M Y', strtotime($app['appointment_date'])) : 'N/A'; ?>
                                                </strong>
                                            </td>
                                            <td><?php echo htmlspecialchars(!empty($app['time_slot']) ? $app['time_slot'] : 'Regular Hours'); ?></td>
                                            <td>
                                                <strong style="color: var(--dash-text-dark);"><?php echo htmlspecialchars($app['patient_name'] ?? 'N/A'); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars(!empty($app['phone']) ? $app['phone'] : 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars(!empty($app['category']) ? $app['category'] : 'General'); ?></td>
                                            <td>
                                                <?php 
                                                    $st = strtolower($app['status'] ?? 'booked');
                                                    $badgeClass = 'badge-booked';
                                                    if ($st === 'confirmed' || $st === 'completed') $badgeClass = 'badge-confirmed';
                                                    else if ($st === 'cancelled') $badgeClass = 'badge-cancelled';
                                                    else if ($st === 'pending') $badgeClass = 'badge-pending';
                                                ?>
                                                <span class="badge-status <?php echo $badgeClass; ?>">
                                                    <?php echo htmlspecialchars($app['status'] ?? 'Booked'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?php echo BASE_URL; ?>doctor/appointment_detail.php?id=<?php echo (int)$app['id']; ?>" class="dash-btn-view">
                                                    <i class="fas fa-stethoscope"></i> View / Consult
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="dash-empty-state" style="padding: 30px 15px;">
                                <i class="fas fa-folder-open dash-empty-icon"></i>
                                <div class="dash-empty-title">No upcoming appointments.</div>
                                <div class="dash-empty-desc">No additional scheduled appointments match the query filter.</div>
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
