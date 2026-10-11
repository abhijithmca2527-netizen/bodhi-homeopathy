<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: strictly enforce Admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Appointment Details | Bodhi Homeopathy";

// Validate appointment ID
$appId = (int)($_GET['id'] ?? 0);
$appointment = null;
$linkedPatient = null;
$msg = '';
$msgType = '';

// Status update POST handler
date_default_timezone_set('Asia/Kolkata');
$todayDateStr = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        $msg = "Invalid security token. Please try again.";
        $msgType = "error";
    } else {
        $targetId = (int)($_POST['appointment_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $validStatuses = ['Booked', 'Confirmed', 'Checked-In', 'In Consultation', 'Completed', 'Cancelled', 'No-Show', 'Pending'];

        if ($targetId > 0 && in_array($newStatus, $validStatuses, true)) {
            try {
                $checkStmt = $pdo->prepare("SELECT `appointment_date` FROM `appointment` WHERE `id` = ?");
                $checkStmt->execute([$targetId]);
                $apptDate = $checkStmt->fetchColumn();

                if ($apptDate !== $todayDateStr) {
                    $msg = "Only today's appointments can be updated.";
                    $msgType = "error";
                } else {
                    $stmt = $pdo->prepare("UPDATE `appointment` SET `status` = ? WHERE `id` = ?");
                    $stmt->execute([$newStatus, $targetId]);
                    $msg = "Appointment status successfully updated to '{$newStatus}'.";
                    $msgType = "success";
                }
            } catch (Exception $e) {
                error_log("Failed to update appointment status in admin view: " . $e->getMessage());
                $msg = "Failed to update appointment status. Please try again.";
                $msgType = "error";
            }
        }
    }
}

if ($appId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM `appointment` WHERE `id` = ?");
        $stmt->execute([$appId]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($appointment && !empty($appointment['user_id'])) {
            $stmt = $pdo->prepare("SELECT `id`, `full_name`, `email`, `phone`, `gender` FROM `users` WHERE `id` = ? AND `role` = 'Patient'");
            $stmt->execute([(int)$appointment['user_id']]);
            $linkedPatient = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $appointment = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $appointment ? "Appointment #APP-" . str_pad($appointment['id'], 4, '0', STR_PAD_LEFT) . " | " . $pageTitle : "Appointment Not Found | Bodhi Homeopathy"; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard.css?v=1.0.4">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .app-status-box {
            background: #FAFCFA;
            border: 1px solid var(--dash-border);
            border-radius: 8px;
            padding: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .status-select-btn {
            padding: 8px 14px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            border-radius: 6px;
            border: 1px solid var(--dash-border);
            background: var(--dash-white);
            color: var(--dash-text-dark);
            outline: none;
            cursor: pointer;
        }
        .status-select-btn:focus {
            border-color: var(--dash-primary);
        }
        .btn-update-status {
            padding: 8px 16px;
            background: var(--dash-primary);
            color: var(--dash-white);
            border: 1px solid var(--dash-primary);
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: var(--dash-transition);
        }
        .btn-update-status:hover {
            background: var(--dash-primary-hover);
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
                    <a href="<?php echo BASE_URL; ?>admin/appointments.php" class="dash-header-link" title="Back to Appointments Directory">
                        <i class="fas fa-arrow-left"></i> Back to Appointments
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

                <?php if ($appointment): ?>
                    <!-- Page Heading & Actions -->
                    <div class="dash-page-heading">
                        <div class="dash-page-title">
                            <h1>APPOINTMENT #APP-<?php echo str_pad($appointment['id'], 4, '0', STR_PAD_LEFT); ?></h1>
                            <p>Consultation schedule details, patient concerns, and status management.</p>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <a href="<?php echo BASE_URL; ?>admin/appointments.php" class="dash-btn-back">
                                <i class="fas fa-arrow-left"></i> All Appointments
                            </a>
                        </div>
                    </div>

                    <!-- 1. Appointment Overview Card -->
                    <section class="patient-profile-card">
                        <div class="patient-header-section">
                            <div class="patient-identity">
                                <div class="patient-big-avatar" style="background: #EFF6FF; color: #2563EB;">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="patient-name-block">
                                    <h2><?php echo htmlspecialchars($appointment['patient_name'] ?? 'N/A'); ?></h2>
                                    <div class="patient-meta-tags">
                                        <span class="badge-status badge-confirmed">
                                            <i class="fas fa-stethoscope"></i> Mode: <?php echo htmlspecialchars($appointment['mode'] ?? 'In-Clinic'); ?>
                                        </span>
                                        <span class="badge-status badge-booked">
                                            <i class="fas fa-calendar-alt"></i> Date: <?php echo !empty($appointment['appointment_date']) ? date('d M Y', strtotime($appointment['appointment_date'])) : 'N/A'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <?php 
                                    $st = strtolower($appointment['status'] ?? 'booked');
                                    $badgeClass = 'badge-booked';
                                    if ($st === 'confirmed' || $st === 'completed') $badgeClass = 'badge-confirmed';
                                    else if ($st === 'cancelled') $badgeClass = 'badge-cancelled';
                                    else if ($st === 'pending') $badgeClass = 'badge-pending';
                                ?>
                                <span class="badge-status <?php echo $badgeClass; ?>" style="font-size: 14px; padding: 6px 16px;">
                                    <i class="fas fa-circle" style="font-size: 8px;"></i> Status: <?php echo htmlspecialchars($appointment['status'] ?? 'Booked'); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Appointment Status Management Box -->
                        <?php if (!empty($appointment['appointment_date']) && $appointment['appointment_date'] === $todayDateStr): ?>
                            <div class="app-status-box">
                                <div>
                                    <strong style="font-size: 13.5px; color: var(--dash-primary);"><i class="fas fa-tasks"></i> Update Appointment Status</strong>
                                    <div style="font-size: 12px; color: var(--dash-text-muted);">Change status to confirm, complete, or cancel today's booking</div>
                                </div>
                                <form method="POST" action="appointment_view.php?id=<?php echo (int)$appointment['id']; ?>" style="display: flex; gap: 8px; align-items: center;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="appointment_id" value="<?php echo (int)$appointment['id']; ?>">
                                    <select name="status" class="status-select-btn" aria-label="Select appointment status">
                                        <option value="Booked" <?php echo ($appointment['status'] ?? '') === 'Booked' ? 'selected' : ''; ?>>Booked</option>
                                        <option value="Confirmed" <?php echo ($appointment['status'] ?? '') === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="Completed" <?php echo ($appointment['status'] ?? '') === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="Cancelled" <?php echo ($appointment['status'] ?? '') === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                    <button type="submit" class="btn-update-status">
                                        <i class="fas fa-save"></i> Save Status
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="app-status-box" style="background: #F7FAFC; border: 1px solid #E2E8F0;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <i class="fas fa-lock" style="font-size: 16px; color: #718096;"></i>
                                    <div>
                                        <strong style="font-size: 13.5px; color: #2D3748;">Status Read-Only</strong>
                                        <div style="font-size: 12px; color: #718096;">Only today's appointments can be updated by admin.</div>
                                    </div>
                                </div>
                                <span class="badge-status <?php echo $badgeClass; ?>" style="font-size: 13px; padding: 6px 14px;">
                                    <?php echo htmlspecialchars($appointment['status'] ?? 'Scheduled'); ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <!-- Read-Only Information Grid -->
                        <div class="patient-info-grid">
                            <div class="patient-info-box">
                                <span class="label"><i class="fas fa-user"></i> Patient Name</span>
                                <span class="value"><?php echo htmlspecialchars($appointment['patient_name'] ?? 'N/A'); ?></span>
                            </div>

                            <div class="patient-info-box">
                                <span class="label"><i class="fas fa-phone"></i> Contact Phone</span>
                                <span class="value"><?php echo htmlspecialchars(!empty($appointment['phone']) ? $appointment['phone'] : 'N/A'); ?></span>
                            </div>

                            <div class="patient-info-box">
                                <span class="label"><i class="fas fa-calendar-day"></i> Appointment Date</span>
                                <span class="value"><?php echo !empty($appointment['appointment_date']) ? date('d M Y', strtotime($appointment['appointment_date'])) : 'N/A'; ?></span>
                            </div>

                            <div class="patient-info-box">
                                <span class="label"><i class="fas fa-clock"></i> Time Slot</span>
                                <span class="value"><?php echo htmlspecialchars(!empty($appointment['time_slot']) ? $appointment['time_slot'] : 'Regular Hours'); ?></span>
                            </div>

                            <div class="patient-info-box">
                                <span class="label"><i class="fas fa-stethoscope"></i> Consultation Mode</span>
                                <span class="value"><?php echo htmlspecialchars(!empty($appointment['mode']) ? $appointment['mode'] : 'Direct Clinic Visit'); ?></span>
                            </div>

                            <div class="patient-info-box">
                                <span class="label"><i class="fas fa-heartbeat"></i> Treatment Category</span>
                                <span class="value"><?php echo htmlspecialchars(!empty($appointment['category']) ? $appointment['category'] : 'General Homeopathy'); ?></span>
                            </div>

                            <div class="patient-info-box">
                                <span class="label"><i class="fas fa-clock"></i> Booking Created At</span>
                                <span class="value"><?php echo !empty($appointment['created_at']) ? date('d M Y, h:i A', strtotime($appointment['created_at'])) : 'N/A'; ?></span>
                            </div>

                            <div class="patient-info-box">
                                <span class="label"><i class="fas fa-id-card"></i> Linked Patient Account</span>
                                <span class="value">
                                    <?php if ($linkedPatient): ?>
                                        <a href="<?php echo BASE_URL; ?>admin/patient_view.php?id=<?php echo (int)$linkedPatient['id']; ?>" class="dash-btn-view" style="font-size: 11.5px; padding: 3px 8px;">
                                            <i class="fas fa-user-check"></i> <?php echo htmlspecialchars($linkedPatient['full_name']); ?> (#PT-<?php echo str_pad($linkedPatient['id'], 4, '0', STR_PAD_LEFT); ?>)
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--dash-text-muted); font-size: 12.5px;">Guest / Unlinked Booking</span>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="patient-info-box" style="grid-column: 1 / -1;">
                                <span class="label"><i class="fas fa-notes-medical"></i> Chief Complaint / Patient Notes</span>
                                <span class="value" style="font-weight: normal; font-size: 13.5px; line-height: 1.6; color: var(--dash-text);">
                                    <?php echo nl2br(htmlspecialchars(!empty($appointment['notes']) ? $appointment['notes'] : 'No specific medical notes or chief complaints were submitted during booking.')); ?>
                                </span>
                            </div>
                        </div>
                    </section>

                <?php else: ?>
                    <!-- Appointment Not Found Message -->
                    <div class="dash-card" style="margin-top: 20px;">
                        <div class="dash-empty-state">
                            <i class="fas fa-calendar-times dash-empty-icon" style="color: #DC2626;"></i>
                            <div class="dash-empty-title" style="color: #DC2626;">Appointment not found.</div>
                            <div class="dash-empty-desc">
                                The requested appointment record does not exist or has been removed from the clinical schedule.
                            </div>
                            <div style="margin-top: 14px;">
                                <a href="<?php echo BASE_URL; ?>admin/appointments.php" class="dash-btn-back">
                                    <i class="fas fa-arrow-left"></i> Return to Appointments Directory
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
