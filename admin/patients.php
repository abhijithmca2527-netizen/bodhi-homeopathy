<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: strictly enforce Admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Patients Management | Bodhi Homeopathy";

// Handle search query safely with PDO prepared statement
$search = trim($_GET['search'] ?? '');

try {
    // 1. Database-Driven Summary Cards
    // Total Patients
    $stmt = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'Patient'");
    $totalPatients = (int)$stmt->fetchColumn();

    // New Patients This Month
    $stmt = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'Patient' AND MONTH(`created_at`) = MONTH(CURRENT_DATE()) AND YEAR(`created_at`) = YEAR(CURRENT_DATE())");
    $newThisMonth = (int)$stmt->fetchColumn();

    // Male Patients
    $stmt = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'Patient' AND (`gender` = 'Male' OR `gender` = 'M')");
    $malePatients = (int)$stmt->fetchColumn();

    // Female Patients
    $stmt = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'Patient' AND (`gender` = 'Female' OR `gender` = 'F')");
    $femalePatients = (int)$stmt->fetchColumn();

    // 2. Fetch Patients List with Appointment Count
    if (!empty($search)) {
        $like = '%' . $search . '%';
        $stmt = $pdo->prepare("
            SELECT u.`id`, u.`full_name`, u.`email`, u.`phone`, u.`gender`, u.`created_at`,
                   COUNT(a.`id`) AS `appointment_count`
            FROM `users` u
            LEFT JOIN `appointment` a ON (a.`user_id` = u.`id`)
            WHERE u.`role` = 'Patient' AND (u.`full_name` LIKE ? OR u.`email` LIKE ? OR u.`phone` LIKE ?)
            GROUP BY u.`id`
            ORDER BY u.`id` DESC
        ");
        $stmt->execute([$like, $like, $like]);
        $patientsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("
            SELECT u.`id`, u.`full_name`, u.`email`, u.`phone`, u.`gender`, u.`created_at`,
                   COUNT(a.`id`) AS `appointment_count`
            FROM `users` u
            LEFT JOIN `appointment` a ON (a.`user_id` = u.`id`)
            WHERE u.`role` = 'Patient'
            GROUP BY u.`id`
            ORDER BY u.`id` DESC
        ");
        $patientsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $totalPatients = 0;
    $newThisMonth = 0;
    $malePatients = 0;
    $femalePatients = 0;
    $patientsList = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard.css?v=1.0.3">
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
                <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="dash-nav-link">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
                <a href="<?php echo BASE_URL; ?>admin/patients.php" class="dash-nav-link active">
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
                
                <!-- Page Heading -->
                <div class="dash-page-heading">
                    <div class="dash-page-title">
                        <h1>PATIENTS</h1>
                        <p>Manage registered patients and view their appointment history.</p>
                    </div>
                    <div class="dash-date-pill">
                        <i class="fas fa-user-check"></i>
                        <span><?php echo $totalPatients; ?> Registered Patient<?php echo $totalPatients === 1 ? '' : 's'; ?></span>
                    </div>
                </div>

                <!-- 1. Real Database Summary Cards -->
                <section class="dash-stats-grid" aria-label="Patient Statistics">
                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Total Patients</span>
                            <span class="dash-stat-value"><?php echo $totalPatients; ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-database"></i> Registered in Database</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-green">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">New This Month</span>
                            <span class="dash-stat-value"><?php echo $newThisMonth; ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-calendar-alt"></i> Current Month</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-orange">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Male Patients</span>
                            <span class="dash-stat-value"><?php echo $malePatients; ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-mars"></i> Registered Male</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-blue">
                            <i class="fas fa-male"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Female Patients</span>
                            <span class="dash-stat-value"><?php echo $femalePatients; ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-venus"></i> Registered Female</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-purple">
                            <i class="fas fa-female"></i>
                        </div>
                    </div>
                </section>

                <!-- 2. Patients Table Card with Search -->
                <section class="dash-card">
                    <div class="dash-card-header">
                        <div>
                            <h2 class="dash-card-title"><i class="fas fa-address-book"></i> Registered Patients Directory</h2>
                            <span class="dash-card-subtitle">Complete registry of patient accounts and consultation records</span>
                        </div>

                        <!-- Real Database Search Box -->
                        <form method="GET" action="patients.php" class="dash-search-bar-wrap">
                            <div class="dash-search-box">
                                <i class="fas fa-search dash-search-icon"></i>
                                <input type="text" 
                                       name="search" 
                                       class="dash-search-input" 
                                       placeholder="Search patients by name, email or phone..." 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       aria-label="Search patients">
                                <?php if (!empty($search)): ?>
                                    <a href="patients.php" class="dash-search-clear" title="Clear search">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <button type="submit" class="dash-btn-search">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </form>
                    </div>

                    <!-- Patients Table / Empty State -->
                    <div class="dash-table-wrapper">
                        <?php if (!empty($patientsList)): ?>
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Gender</th>
                                        <th>Registered</th>
                                        <th>Appointments</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patientsList as $p): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div class="dash-avatar-circle" style="width: 32px; height: 32px; font-size: 12px;">
                                                        <?php 
                                                            $initials = strtoupper(substr($p['full_name'] ?? 'P', 0, 1));
                                                            echo htmlspecialchars($initials);
                                                        ?>
                                                    </div>
                                                    <div>
                                                        <strong style="color: var(--dash-text-dark); font-size: 13.5px;"><?php echo htmlspecialchars($p['full_name']); ?></strong>
                                                        <div style="font-size: 11px; color: var(--dash-text-muted);">ID: #PT-<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($p['email']); ?></td>
                                            <td><?php echo htmlspecialchars(!empty($p['phone']) ? $p['phone'] : 'N/A'); ?></td>
                                            <td>
                                                <span class="badge-status <?php echo strtolower($p['gender']) === 'male' ? 'badge-booked' : 'badge-confirmed'; ?>">
                                                    <i class="fas fa-<?php echo strtolower($p['gender']) === 'male' ? 'mars' : 'venus'; ?>"></i>
                                                    <?php echo htmlspecialchars($p['gender'] ?? 'Not Specified'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                    if (!empty($p['created_at'])) {
                                                        echo date('d M Y', strtotime($p['created_at']));
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge-status badge-confirmed" style="font-weight: 700;">
                                                    <i class="fas fa-calendar-check"></i> <?php echo (int)$p['appointment_count']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?php echo BASE_URL; ?>admin/patient_view.php?id=<?php echo (int)$p['id']; ?>" class="dash-btn-view" title="View Patient Details">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="dash-empty-state">
                                <i class="fas fa-user-slash dash-empty-icon"></i>
                                <div class="dash-empty-title">No patients found.</div>
                                <div class="dash-empty-desc">
                                    <?php if (!empty($search)): ?>
                                        No registered patient records match your search query for "<strong><?php echo htmlspecialchars($search); ?></strong>".
                                        <div style="margin-top: 12px;">
                                            <a href="patients.php" class="dash-btn-back"><i class="fas fa-arrow-left"></i> View All Patients</a>
                                        </div>
                                    <?php else: ?>
                                        There are currently no registered patient records in the database.
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
