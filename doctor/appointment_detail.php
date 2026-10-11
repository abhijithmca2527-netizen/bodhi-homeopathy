<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: strictly enforce Doctor role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Consultation & Appointment Details | Bodhi Homeopathy";
$doctorId = (int)$_SESSION['user_id'];
$appId = (int)($_GET['id'] ?? 0);

$appointment = null;
$patientUser = null;
$consultation = null;
$msg = '';
$msgType = '';

if ($appId > 0) {
    try {
        // Fetch appointment details
        $stmt = $pdo->prepare("SELECT * FROM `appointment` WHERE `id` = ?");
        $stmt->execute([$appId]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($appointment) {
            // Fetch linked patient user if user_id is set
            if (!empty($appointment['user_id'])) {
                $stmt = $pdo->prepare("SELECT `id`, `full_name`, `email`, `phone`, `gender`, `created_at` FROM `users` WHERE `id` = ? AND `role` = 'Patient'");
                $stmt->execute([(int)$appointment['user_id']]);
                $patientUser = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            // Fetch existing consultation record if present
            $stmt = $pdo->prepare("SELECT * FROM `consultations` WHERE `appointment_id` = ?");
            $stmt->execute([$appId]);
            $consultation = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $appointment = null;
    }
}

// POST Handler: Save / Complete Consultation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_consultation' && $appointment) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        $msg = "Invalid security token. Please try again.";
        $msgType = "error";
    } elseif (($appointment['status'] ?? '') === 'Cancelled') {
        $msg = "Consultation cannot be recorded for cancelled appointments.";
        $msgType = "error";
    } else {
        $notes = trim($_POST['notes'] ?? '');
        $prescription = trim($_POST['prescription'] ?? '');
        $markCompleted = isset($_POST['complete_consultation']);
        $patientId = !empty($appointment['user_id']) ? (int)$appointment['user_id'] : null;

        try {
            // Check if consultation record already exists (unique constraint on appointment_id)
            $stmt = $pdo->prepare("SELECT `id` FROM `consultations` WHERE `appointment_id` = ?");
            $stmt->execute([$appId]);
            $existingConsId = $stmt->fetchColumn();

            if ($existingConsId) {
                $stmt = $pdo->prepare("UPDATE `consultations` SET `doctor_id` = ?, `patient_id` = ?, `notes` = ?, `prescription` = ? WHERE `appointment_id` = ?");
                $stmt->execute([$doctorId, $patientId, $notes, $prescription, $appId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO `consultations` (`appointment_id`, `doctor_id`, `patient_id`, `notes`, `prescription`) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$appId, $doctorId, $patientId, $notes, $prescription]);
            }

            // Update status if completing consultation
            if ($markCompleted) {
                $stmt = $pdo->prepare("UPDATE `appointment` SET `status` = 'Completed' WHERE `id` = ?");
                $stmt->execute([$appId]);
                $appointment['status'] = 'Completed';
                $msg = "Consultation completed successfully. Prescription is now active for the patient.";
            } else {
                if (in_array($appointment['status'] ?? '', ['Booked', 'Confirmed', 'Checked-In', 'Checked-in'])) {
                    $stmt = $pdo->prepare("UPDATE `appointment` SET `status` = 'In Consultation' WHERE `id` = ?");
                    $stmt->execute([$appId]);
                    $appointment['status'] = 'In Consultation';
                }
                $msg = "Consultation notes and prescription draft saved successfully.";
            }
            $msgType = "success";

            // Refresh consultation state
            $stmt = $pdo->prepare("SELECT * FROM `consultations` WHERE `appointment_id` = ?");
            $stmt->execute([$appId]);
            $consultation = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Failed to save doctor consultation: " . $e->getMessage());
            $msg = "Failed to save consultation. Please try again.";
            $msgType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $appointment ? "Consultation #APP-" . str_pad($appointment['id'], 4, '0', STR_PAD_LEFT) . " | " . $pageTitle : "Appointment Not Found | Bodhi Homeopathy"; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard.css?v=1.0.4">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .consultation-card {
            background: var(--dash-white);
            border: 1px solid var(--dash-border);
            border-radius: 12px;
            padding: 24px;
            margin-top: 24px;
            box-shadow: var(--dash-shadow);
        }
        .consultation-form-group {
            margin-bottom: 20px;
        }
        .consultation-label {
            display: block;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--dash-text-dark);
            margin-bottom: 8px;
        }
        .consultation-textarea {
            width: 100%;
            min-height: 120px;
            padding: 12px 14px;
            font-size: 13.5px;
            font-family: 'Poppins', sans-serif;
            border: 1px solid var(--dash-border);
            border-radius: 8px;
            background: var(--dash-bg);
            color: var(--dash-text-dark);
            resize: vertical;
            outline: none;
            transition: var(--dash-transition);
        }
        .consultation-textarea:focus {
            border-color: var(--dash-primary);
            background: var(--dash-white);
            box-shadow: 0 0 0 3px rgba(53, 94, 59, 0.1);
        }
        .action-btn-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 24px;
        }
        .btn-complete-consultation {
            padding: 10px 22px;
            background: var(--dash-primary);
            color: var(--dash-white);
            border: 1px solid var(--dash-primary);
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: var(--dash-transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-complete-consultation:hover {
            background: var(--dash-primary-hover);
        }
        .btn-save-draft {
            padding: 10px 20px;
            background: var(--dash-white);
            color: var(--dash-text-dark);
            border: 1px solid var(--dash-border);
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: var(--dash-transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-save-draft:hover {
            border-color: var(--dash-primary);
            color: var(--dash-primary);
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
                    <a href="<?php echo BASE_URL; ?>doctor/appointments.php" class="dash-header-link" title="Back to Today's Appointments">
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
                    <!-- Page Heading & Navigation -->
                    <div class="dash-page-heading">
                        <div class="dash-page-title">
                            <h1>CONSULTATION #APP-<?php echo str_pad($appointment['id'], 4, '0', STR_PAD_LEFT); ?></h1>
                            <p>Patient clinical observation, diagnostic notes, and homeopathic prescription entry.</p>
                        </div>
                        <div>
                            <a href="<?php echo BASE_URL; ?>doctor/appointments.php" class="dash-btn-back">
                                <i class="fas fa-arrow-left"></i> Appointments Queue
                            </a>
                        </div>
                    </div>

                    <!-- 1. Patient & Appointment Summary Card -->
                    <section class="patient-profile-card">
                        <div class="patient-header-section">
                            <div class="patient-identity">
                                <div class="patient-big-avatar" style="background: #F0FDF4; color: #166534;">
                                    <i class="fas fa-user-injured"></i>
                                </div>
                                <div class="patient-name-block">
                                    <h2><?php echo htmlspecialchars($appointment['patient_name'] ?? 'N/A'); ?></h2>
                                    <div class="patient-meta-tags">
                                        <span class="badge-status badge-confirmed">
                                            <i class="fas fa-stethoscope"></i> Mode: <?php echo htmlspecialchars($appointment['mode'] ?? 'In-Clinic'); ?>
                                        </span>
                                        <span class="badge-status badge-booked">
                                            <i class="fas fa-calendar-alt"></i> Scheduled: <?php echo !empty($appointment['appointment_date']) ? date('d M Y', strtotime($appointment['appointment_date'])) : 'N/A'; ?>
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

                        <!-- Read-Only Clinical Details Grid -->
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
                                <span class="label"><i class="fas fa-heartbeat"></i> Speciality Category</span>
                                <span class="value"><?php echo htmlspecialchars(!empty($appointment['category']) ? $appointment['category'] : 'General Homeopathy'); ?></span>
                            </div>

                            <?php if ($patientUser): ?>
                                <div class="patient-info-box">
                                    <span class="label"><i class="fas fa-envelope"></i> Patient Email</span>
                                    <span class="value"><?php echo htmlspecialchars($patientUser['email']); ?></span>
                                </div>
                                <div class="patient-info-box">
                                    <span class="label"><i class="fas fa-venus-mars"></i> Registered Gender</span>
                                    <span class="value"><?php echo htmlspecialchars(!empty($patientUser['gender']) ? ucfirst($patientUser['gender']) : 'Not Specified'); ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="patient-info-box" style="grid-column: 1 / -1;">
                                <span class="label"><i class="fas fa-notes-medical"></i> Patient Chief Complaint / Initial Notes</span>
                                <span class="value" style="font-weight: normal; font-size: 13.5px; line-height: 1.6; color: var(--dash-text);">
                                    <?php echo nl2br(htmlspecialchars(!empty($appointment['notes']) ? $appointment['notes'] : 'No specific chief complaints were recorded during booking.')); ?>
                                </span>
                            </div>
                        </div>
                    </section>

                    <!-- 2. Clinical Consultation & Prescription Form -->
                    <section class="consultation-card">
                        <div style="margin-bottom: 20px;">
                            <h2 style="font-size: 18px; font-weight: 700; color: var(--dash-primary); margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-stethoscope"></i> Clinical Consultation & Homeopathic Prescription
                            </h2>
                            <p style="font-size: 12.5px; color: var(--dash-text-muted);">
                                Record physician observations, diagnostic findings, and medicine prescriptions for this appointment.
                            </p>
                        </div>

                        <?php if (($appointment['status'] ?? '') === 'Cancelled'): ?>
                            <div class="alert-banner alert-error" style="margin-bottom: 0;">
                                <i class="fas fa-ban"></i>
                                <span><strong>Appointment Cancelled:</strong> Consultation notes and prescriptions cannot be conducted for cancelled appointments.</span>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="appointment_detail.php?id=<?php echo (int)$appointment['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="action" value="save_consultation">

                                <!-- Clinical Notes Field -->
                                <div class="consultation-form-group">
                                    <label for="notes" class="consultation-label">
                                        <i class="fas fa-clipboard-list" style="color: var(--dash-primary);"></i> Clinical Notes & Diagnostic Observations
                                    </label>
                                    <textarea id="notes" 
                                              name="notes" 
                                              class="consultation-textarea" 
                                              placeholder="Enter consultation notes, symptoms observed, diagnostic findings, and clinical recommendations..."><?php echo htmlspecialchars($consultation['notes'] ?? ''); ?></textarea>
                                </div>

                                <!-- Prescription Field -->
                                <div class="consultation-form-group">
                                    <label for="prescription" class="consultation-label">
                                        <i class="fas fa-file-prescription" style="color: var(--dash-secondary);"></i> Prescription & Dosage Instructions
                                    </label>
                                    <textarea id="prescription" 
                                              name="prescription" 
                                              class="consultation-textarea" 
                                              placeholder="Enter prescribed homeopathic remedies, potency, dosage frequency, and patient instructions..."><?php echo htmlspecialchars($consultation['prescription'] ?? ''); ?></textarea>
                                </div>

                                <!-- Action Buttons -->
                                <div class="action-btn-group">
                                    <button type="submit" name="complete_consultation" value="1" class="btn-complete-consultation">
                                        <i class="fas fa-check-circle"></i> Complete Consultation
                                    </button>
                                    <button type="submit" name="save_draft" value="1" class="btn-save-draft">
                                        <i class="fas fa-save"></i> Save Draft Notes
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </section>

                <?php else: ?>
                    <!-- Appointment Not Found State -->
                    <div class="dash-card" style="margin-top: 20px;">
                        <div class="dash-empty-state">
                            <i class="fas fa-calendar-times dash-empty-icon" style="color: #DC2626;"></i>
                            <div class="dash-empty-title" style="color: #DC2626;">Appointment not found.</div>
                            <div class="dash-empty-desc">
                                The requested consultation record does not exist or has been removed from the clinical queue.
                            </div>
                            <div style="margin-top: 14px;">
                                <a href="<?php echo BASE_URL; ?>doctor/appointments.php" class="dash-btn-back">
                                    <i class="fas fa-arrow-left"></i> Return to Today's Appointments
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
