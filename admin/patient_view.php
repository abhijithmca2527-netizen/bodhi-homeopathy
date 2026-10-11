<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: strictly enforce Admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Patient Details | Bodhi Homeopathy";

// Validate patient ID from GET parameter
$patientId = (int)($_GET['id'] ?? 0);
$patient = null;
$appointments = [];
$totalAppointmentsCount = 0;

if ($patientId > 0) {
    try {
        // Query patient details securely without selecting password/hash
        $stmt = $pdo->prepare("SELECT `id`, `full_name`, `gender`, `email`, `phone`, `role`, `address`, `created_at` FROM `users` WHERE `id` = ? AND `role` = 'Patient'");
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($patient) {
            // Query appointment history for this patient
            $stmt = $pdo->prepare("SELECT * FROM `appointment` WHERE `user_id` = ? ORDER BY `id` DESC");
            $stmt->execute([$patientId]);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totalAppointmentsCount = count($appointments);
        }
    } catch (Exception $e) {
        $patient = null;
        $appointments = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $patient ? htmlspecialchars($patient['full_name']) . " | " . $pageTitle : "Patient Not Found | Bodhi Homeopathy"; ?></title>
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
                    <a href="<?php echo BASE_URL; ?>admin/patients.php" class="dash-header-link" title="Back to Patients Directory">
                        <i class="fas fa-arrow-left"></i> Back to Patients
                    </a>
                    <a href="<?php echo BASE_URL; ?>logout.php" class="dash-btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </header>

            <!-- Main Content Body -->
            <main class="dash-body">
                
                <?php if ($patient): ?>
                    <!-- Page Heading & Actions -->
                    <div class="dash-page-heading">
                        <div class="dash-page-title">
                            <h1>PATIENT PROFILE</h1>
                            <p>Detailed medical account overview and recorded clinical appointments.</p>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <a href="<?php echo BASE_URL; ?>admin/patients.php" class="dash-btn-back">
                                <i class="fas fa-arrow-left"></i> All Patients
                            </a>
                        </div>
                    </div>

                    <!-- 1. Patient Profile Summary Card -->
                    <section class="patient-profile-card">
                        <div class="patient-header-section">
                            <div class="patient-identity">
                                <div class="patient-big-avatar">
                                    <?php 
                                        $initials = strtoupper(substr($patient['full_name'] ?? 'P', 0, 1));
                                        echo htmlspecialchars($initials);
                                    ?>
                                </div>
                                <div class="patient-name-block">
                                    <h2><?php echo htmlspecialchars($patient['full_name']); ?></h2>
                                    <div class="patient-meta-tags">
                                        <span class="badge-status badge-confirmed">
                                            <i class="fas fa-id-badge"></i> Patient ID: #PT-<?php echo str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?>
                                        </span>
                                        <span class="badge-status badge-booked">
                                            <i class="fas fa-calendar-alt"></i> Member Since: <?php echo date('d M Y', strtotime($patient['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <span class="badge-status badge-confirmed" style="font-size: 13px; padding: 6px 14px;">
                                    <i class="fas fa-calendar-check"></i> <?php echo $totalAppointmentsCount; ?> Appointment<?php echo $totalAppointmentsCount === 1 ? '' : 's'; ?> Total
                                </span>
                            </div>
                        </div>

                        <!-- Read-Only Patient Info Grid -->
                        <div class="patient-info-grid">
                            <div class="patient-info-box">
                                <span class="label"><i class="fas fa-envelope"></i> Email Address</span>
                                <span class="value"><?php echo htmlspecialchars($patient['email']); ?></span>
                            </div>

                            <div class="patient-info-box">
                                <span class="label"><i class="fas fa-phone"></i> Contact Phone</span>
                                <span class="value"><?php echo htmlspecialchars(!empty($patient['phone']) ? $patient['phone'] : 'N/A'); ?></span>
                            </div>

                            <div class="patient-info-box">
                                <span class="label"><i class="fas fa-venus-mars"></i> Gender</span>
                                <span class="value"><?php echo htmlspecialchars(!empty($patient['gender']) ? $patient['gender'] : 'Not Specified'); ?></span>
                            </div>

                            <div class="patient-info-box">
                                <span class="label"><i class="fas fa-user-tag"></i> Role</span>
                                <span class="value"><?php echo htmlspecialchars($patient['role']); ?> (Read-Only)</span>
                            </div>

                            <div class="patient-info-box" style="grid-column: 1 / -1;">
                                <span class="label"><i class="fas fa-map-marker-alt"></i> Residential Address</span>
                                <span class="value"><?php echo htmlspecialchars(!empty($patient['address']) ? $patient['address'] : 'No address specified in patient records.'); ?></span>
                            </div>
                        </div>
                    </section>

                    <!-- 2. Appointment History Section -->
                    <section class="dash-card">
                        <div class="dash-card-header">
                            <div>
                                <h2 class="dash-card-title"><i class="fas fa-history"></i> Appointment History</h2>
                                <span class="dash-card-subtitle">Chronological record of scheduled consultations and appointments</span>
                            </div>
                            <span class="badge-status badge-booked">
                                <i class="fas fa-notes-medical"></i> <?php echo count($appointments); ?> Recorded
                            </span>
                        </div>

                        <div class="dash-table-wrapper">
                            <?php if (!empty($appointments)): ?>
                                <table class="dash-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Appointment Date</th>
                                            <th>Time Slot</th>
                                            <th>Consultation Mode</th>
                                            <th>Category / Concern</th>
                                            <th>Status</th>
                                            <th>Chief Complaint / Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $idx = 1; foreach ($appointments as $app): ?>
                                            <tr>
                                                <td><strong>#<?php echo $idx++; ?></strong></td>
                                                <td>
                                                    <strong style="color: var(--dash-primary);">
                                                        <?php echo !empty($app['appointment_date']) ? date('d M Y', strtotime($app['appointment_date'])) : 'N/A'; ?>
                                                    </strong>
                                                </td>
                                                <td><?php echo htmlspecialchars(!empty($app['time_slot']) ? $app['time_slot'] : 'Regular Hours'); ?></td>
                                                <td>
                                                    <span class="badge-status badge-confirmed">
                                                        <i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($app['mode'] ?? 'In-Clinic'); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars(!empty($app['category']) ? $app['category'] : 'General Homeopathy'); ?></td>
                                                <td>
                                                    <?php 
                                                        $st = strtolower($app['status'] ?? 'pending');
                                                        $badgeClass = 'badge-pending';
                                                        if ($st === 'confirmed' || $st === 'booked') $badgeClass = 'badge-confirmed';
                                                        else if ($st === 'completed') $badgeClass = 'badge-confirmed';
                                                        else if ($st === 'cancelled') $badgeClass = 'badge-cancelled';
                                                    ?>
                                                    <span class="badge-status <?php echo $badgeClass; ?>">
                                                        <i class="fas fa-circle" style="font-size: 8px;"></i>
                                                        <?php echo htmlspecialchars($app['status'] ?? 'Booked'); ?>
                                                    </span>
                                                </td>
                                                <td style="max-width: 280px; white-space: normal;">
                                                    <span style="font-size: 12.5px; color: var(--dash-text);">
                                                        <?php echo htmlspecialchars(!empty($app['notes']) ? $app['notes'] : 'No specific notes recorded.'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="dash-empty-state">
                                    <i class="fas fa-calendar-times dash-empty-icon"></i>
                                    <div class="dash-empty-title">No appointments found.</div>
                                    <div class="dash-empty-desc">This patient has not booked any consultations yet.</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                <?php else: ?>
                    <!-- Patient Not Found Message -->
                    <div class="dash-card" style="margin-top: 20px;">
                        <div class="dash-empty-state">
                            <i class="fas fa-user-slash dash-empty-icon" style="color: #DC2626;"></i>
                            <div class="dash-empty-title" style="color: #DC2626;">Patient not found.</div>
                            <div class="dash-empty-desc">
                                The requested patient record does not exist or has been removed from the registry.
                            </div>
                            <div style="margin-top: 14px;">
                                <a href="<?php echo BASE_URL; ?>admin/patients.php" class="dash-btn-back">
                                    <i class="fas fa-arrow-left"></i> Return to Patients Directory
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

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
