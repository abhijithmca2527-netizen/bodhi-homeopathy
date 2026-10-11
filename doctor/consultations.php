<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: strictly enforce Doctor role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Doctor Portal - Consultations | Bodhi Homeopathy";
$doctorId = (int)$_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');

try {
    $query = "
        SELECT c.*, a.appointment_date, a.time_slot, a.category, a.mode,
               COALESCE(u.full_name, a.patient_name) AS patient_name,
               COALESCE(u.phone, a.phone) AS patient_phone,
               u.gender, u.email
        FROM `consultations` c
        JOIN `appointment` a ON a.id = c.appointment_id
        LEFT JOIN `users` u ON u.id = c.patient_id
        WHERE c.doctor_id = ?
    ";
    $params = [$doctorId];

    if (!empty($search)) {
        $query .= " AND (a.patient_name LIKE ? OR u.full_name LIKE ? OR c.notes LIKE ? OR c.prescription LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $query .= " ORDER BY c.id DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalConsultations = count($consultations);

} catch (Exception $e) {
    $consultations = [];
    $totalConsultations = 0;
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
</head>
<body class="dash-body">

    <div class="dash-layout">
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
                <a href="<?php echo BASE_URL; ?>doctor/appointments.php" class="dash-nav-link">
                    <i class="fas fa-calendar-day"></i> Today's Appointments
                </a>
                <a href="<?php echo BASE_URL; ?>doctor/patients.php" class="dash-nav-link">
                    <i class="fas fa-user-injured"></i> Patients
                </a>
                <a href="<?php echo BASE_URL; ?>doctor/consultations.php" class="dash-nav-link active">
                    <i class="fas fa-notes-medical"></i> Consultations
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
                        <i class="fas fa-notes-medical"></i> Clinical Consultations History
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

            <main class="dash-content">
                
                <div class="dash-page-header">
                    <div class="dash-page-header-text">
                        <h2>Consultation History</h2>
                        <p>Complete record of diagnostic assessments, clinical notes, and remedy prescriptions</p>
                    </div>
                    <div class="dash-header-actions">
                        <span class="dash-badge-outline" style="padding: 8px 16px; border-radius: 20px; font-weight: 600; background: #fff; border: 1px solid var(--dash-border); font-size: 13px; color: var(--dash-primary);">
                            <i class="fas fa-file-medical-alt"></i> Total Consultations: <strong><?php echo $totalConsultations; ?></strong>
                        </span>
                    </div>
                </div>

                <!-- Search Filter Form -->
                <div class="dash-search-card" style="margin-bottom: 20px;">
                    <form method="GET" action="consultations.php" class="dash-search-form" style="display: flex; gap: 10px; max-width: 600px;">
                        <div style="flex: 1; position: relative;">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search consultations by patient name, notes, or remedy..." class="dash-search-input" style="width: 100%; padding: 10px 14px 10px 38px; border-radius: 8px; border: 1px solid var(--dash-border); font-size: 13.5px;">
                            <i class="fas fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #999;"></i>
                        </div>
                        <button type="submit" class="dash-btn-search" style="padding: 10px 20px; background: var(--dash-primary); color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="consultations.php" style="padding: 10px 16px; background: #eee; color: #555; text-decoration: none; border-radius: 8px; font-size: 13px; display: inline-flex; align-items: center;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Consultations Table Card -->
                <div class="dash-table-card">
                    <div class="dash-table-header">
                        <h3><i class="fas fa-stethoscope"></i> Completed Patient Consultations</h3>
                    </div>

                    <?php if (empty($consultations)): ?>
                        <div class="dash-empty-state" style="padding: 50px 20px; text-align: center;">
                            <i class="fas fa-notes-medical" style="font-size: 40px; color: #ccc; margin-bottom: 12px;"></i>
                            <h4>No Consultation Records Found</h4>
                            <p style="color: #888; font-size: 13.5px;">Start consultations from Today's Appointments queue.</p>
                            <a href="<?php echo BASE_URL; ?>doctor/appointments.php" class="dash-btn-primary" style="display: inline-block; margin-top: 10px; padding: 10px 20px; background: var(--dash-primary); color: #fff; text-decoration: none; border-radius: 6px;">Open Appointments Queue</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th>Ref / Date</th>
                                        <th>Patient Information</th>
                                        <th>Category & Mode</th>
                                        <th>Clinical Notes</th>
                                        <th>Prescription</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($consultations as $c): ?>
                                        <tr style="vertical-align: top;">
                                            <td>
                                                <span class="dash-id-badge">#APP-<?php echo str_pad($c['appointment_id'], 4, '0', STR_PAD_LEFT); ?></span>
                                                <div style="font-size: 12px; color: var(--dash-text-muted); margin-top: 4px;">
                                                    <?php echo date('d M Y', strtotime($c['created_at'])); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="patient-name-cell">
                                                    <strong><?php echo htmlspecialchars($c['patient_name']); ?></strong>
                                                    <span style="font-size: 12px; color: var(--dash-text-muted);"><?php echo htmlspecialchars($c['patient_phone']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600; color: var(--dash-primary); font-size: 13px;"><?php echo htmlspecialchars($c['category'] ?? 'General Health'); ?></div>
                                                <small style="color: #718096;"><?php echo htmlspecialchars($c['mode'] ?? 'Direct Clinic Visit'); ?></small>
                                            </td>
                                            <td style="max-width: 250px;">
                                                <div style="font-size: 12.5px; color: #333; line-height: 1.4; white-space: pre-wrap;"><?php echo htmlspecialchars(mb_strimwidth($c['notes'] ?? '', 0, 120, '...')); ?></div>
                                            </td>
                                            <td style="max-width: 250px;">
                                                <?php if (!empty($c['prescription'])): ?>
                                                    <div style="background: #F0FDF4; border: 1px solid #BBF7D0; padding: 8px 12px; border-radius: 6px; font-size: 12px; color: #166534; font-family: 'Poppins', sans-serif; white-space: pre-wrap; line-height: 1.5;">
                                                        <?php echo htmlspecialchars(mb_strimwidth($c['prescription'], 0, 100, '...')); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: #A0AEC0; font-size: 12px;">No prescription</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo BASE_URL; ?>doctor/appointment_detail.php?id=<?php echo (int)$c['appointment_id']; ?>" class="dash-btn-action-view" style="padding: 6px 12px; font-size: 12px; text-decoration: none; border-radius: 6px; background: #EEF6EF; color: #355E3B; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                                    <i class="fas fa-edit"></i> Edit / View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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
