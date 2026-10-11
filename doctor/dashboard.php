<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: strictly enforce Doctor role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Doctor Dashboard | Bodhi Homeopathy";
$today = date('Y-m-d');
$doctorId = $_SESSION['user_id'];

// --- DATABASE-DRIVEN STATISTICS ---
try {
    // 1. Today's Appointments Count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `appointment` WHERE `appointment_date` = ? AND `status` != 'Cancelled'");
    $stmt->execute([$today]);
    $todayAppointmentsCount = (int)$stmt->fetchColumn();

    // 2. Pending Today (Booked or Confirmed)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `appointment` WHERE `appointment_date` = ? AND `status` IN ('Booked', 'Confirmed')");
    $stmt->execute([$today]);
    $pendingTodayCount = (int)$stmt->fetchColumn();

    // 3. Completed Today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `appointment` WHERE `appointment_date` = ? AND `status` = 'Completed'");
    $stmt->execute([$today]);
    $completedTodayCount = (int)$stmt->fetchColumn();

    // 4. Total Registered Patients
    $stmt = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'Patient'");
    $totalPatients = (int)$stmt->fetchColumn();

    // 5. Today's Consultations Schedule List (Active Queue)
    $stmt = $pdo->prepare("SELECT * FROM `appointment` WHERE `appointment_date` = ? AND `status` != 'Cancelled' ORDER BY `id` DESC");
    $stmt->execute([$today]);
    $todayAppointmentsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Recent Patients
    $stmt = $pdo->query("SELECT `id`, `full_name`, `gender`, `phone`, `created_at` FROM `users` WHERE `role` = 'Patient' ORDER BY `id` DESC LIMIT 4");
    $recentPatients = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $todayAppointmentsCount = 0;
    $pendingTodayCount = 0;
    $completedTodayCount = 0;
    $totalPatients = 0;
    $todayAppointmentsList = [];
    $recentPatients = [];
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
                    <span class="dash-brand-title">Doctor Portal</span>
                    <span class="dash-brand-subtitle">Clinical Management</span>
                </div>
            </div>

            <nav class="dash-nav">
                <span class="dash-nav-section">Clinical Practice</span>
                <a href="<?php echo BASE_URL; ?>doctor/dashboard.php" class="dash-nav-link active">
                    <i class="fas fa-stethoscope"></i> Dashboard
                </a>
                <a href="<?php echo BASE_URL; ?>doctor/appointments.php" class="dash-nav-link">
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
                        <div class="dash-user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
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
                    <button class="dash-menu-btn" onclick="toggleSidebar()">
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

            <!-- Dashboard Content Body -->
            <main class="dash-body">
                
                <!-- Page Heading -->
                <div class="dash-page-heading">
                    <div class="dash-page-title">
                        <h1>Doctor Dashboard</h1>
                        <p>Today's clinical overview and patient consultations</p>
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
                            <span class="dash-stat-subtext"><i class="fas fa-user-clock"></i> Total active queue</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-green">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Pending Today</span>
                            <span class="dash-stat-value"><?php echo number_format($pendingTodayCount); ?></span>
                            <span class="dash-stat-subtext" style="color: var(--dash-secondary);"><i class="fas fa-hourglass-half"></i> Awaiting consultation</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-orange">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Completed Today</span>
                            <span class="dash-stat-value"><?php echo number_format($completedTodayCount); ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-check-circle"></i> Consultations done</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-purple">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>

                    <div class="dash-stat-card">
                        <div class="dash-stat-info">
                            <span class="dash-stat-label">Total Patients</span>
                            <span class="dash-stat-value"><?php echo number_format($totalPatients); ?></span>
                            <span class="dash-stat-subtext"><i class="fas fa-users"></i> Registered patients</span>
                        </div>
                        <div class="dash-stat-icon dash-icon-blue">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    </div>
                </div>

                <!-- Section 1: Today's Appointments Table -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <div>
                            <div class="dash-card-title">
                                <i class="fas fa-calendar-day"></i> Today's Consultations Schedule
                            </div>
                            <div class="dash-card-subtitle">Patients scheduled for <?php echo htmlspecialchars($_SESSION['full_name']); ?> (<?php echo date('F j, Y'); ?>)</div>
                        </div>
                    </div>
                    
                    <div class="dash-table-wrapper">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Patient</th>
                                    <th>Consultation Type</th>
                                    <th>Mode</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($todayAppointmentsList)): ?>
                                    <?php foreach ($todayAppointmentsList as $app): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($app['time_slot'] ?? '10:00 AM - 11:00 AM'); ?></strong></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($app['patient_name']); ?></strong><br>
                                                <small style="color: #718096;">Phone: <?php echo htmlspecialchars($app['phone'] ?? ''); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($app['category'] ?? 'General Health'); ?></td>
                                            <td><span style="font-weight: 500;"><?php echo htmlspecialchars($app['mode'] ?? 'Direct Clinic Visit'); ?></span></td>
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
                                        <td colspan="5" style="text-align: center; padding: 40px 20px; color: #718096;">
                                            <i class="fas fa-calendar-times" style="font-size: 28px; color: #A0AEC0; margin-bottom: 10px; display: block;"></i>
                                            <strong>No consultations scheduled for today.</strong>
                                            <div style="font-size: 12.5px; color: #A0AEC0; margin-top: 4px;">Patient consultations scheduled for today will appear here in real-time.</div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Section 2 & 3: Upcoming Patients & Clinical Notes Preview -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
                    
                    <!-- Upcoming / Recent Patients -->
                    <div class="dash-card">
                        <div class="dash-card-header">
                            <div>
                                <div class="dash-card-title">
                                    <i class="fas fa-user-friends"></i> Recent Registered Patients
                                </div>
                                <div class="dash-card-subtitle">Quick access patient summaries</div>
                            </div>
                        </div>

                        <div class="dash-timeline">
                            <?php if (!empty($recentPatients)): ?>
                                <?php foreach ($recentPatients as $rp): ?>
                                    <div class="dash-timeline-item">
                                        <div class="dash-timeline-icon"><i class="fas fa-user"></i></div>
                                        <div class="dash-timeline-content">
                                            <div class="dash-timeline-title">
                                                <?php echo htmlspecialchars($rp['full_name']); ?> • <?php echo htmlspecialchars($rp['gender'] ?? 'Patient'); ?>
                                            </div>
                                            <div class="dash-timeline-desc">
                                                Phone: <?php echo htmlspecialchars($rp['phone']); ?>
                                            </div>
                                            <div class="dash-timeline-time">
                                                Registered: <?php echo !empty($rp['created_at']) ? date('M j, Y', strtotime($rp['created_at'])) : 'Active Account'; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 35px 20px; color: #718096;">
                                    <i class="fas fa-user-slash" style="font-size: 28px; color: #CBD5E0; margin-bottom: 10px; display: block;"></i>
                                    <p style="margin: 0; font-size: 13px;">No patient records found.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Consultation & Prescriptions Workflow Area -->
                    <div class="dash-card">
                        <div class="dash-card-header">
                            <div>
                                <div class="dash-card-title">
                                    <i class="fas fa-file-medical"></i> Consultation & Prescriptions
                                </div>
                                <div class="dash-card-subtitle">Clinical recording workspace</div>
                            </div>
                        </div>

                        <div style="background: #FAFCFA; border: 1px dashed var(--dash-border); border-radius: 8px; padding: 24px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; height: 100%;">
                            <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--dash-primary-light); color: var(--dash-primary); display: flex; align-items: center; justify-content: center; font-size: 22px;">
                                <i class="fas fa-stethoscope"></i>
                            </div>
                            <h4 style="color: var(--dash-primary-dark); font-size: 15px; font-weight: 700; margin: 0;">Clinical Consultation Workflow</h4>
                            <p style="color: var(--dash-text-muted); font-size: 13px; max-width: 320px; line-height: 1.5; margin: 0;">
                                Select an active appointment from today's schedule to enter diagnostic observations, add homeopathic remedies, and finalize patient prescriptions.
                            </p>
                            <span style="display: inline-block; background: var(--dash-primary-light); color: var(--dash-primary); font-size: 12px; font-weight: 600; padding: 4px 12px; border-radius: 20px;">
                                Module active in Doctor Phase
                            </span>
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
