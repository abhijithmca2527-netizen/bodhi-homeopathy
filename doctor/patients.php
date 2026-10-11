<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: strictly enforce Doctor role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Doctor Portal - Patients | Bodhi Homeopathy";
$doctorId = (int)$_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');

try {
    $query = "
        SELECT u.id, u.full_name, u.gender, u.email, u.phone, u.created_at,
               COUNT(DISTINCT a.id) AS total_appointments,
               COUNT(DISTINCT c.id) AS total_consultations,
               MAX(a.appointment_date) AS last_appointment_date
        FROM `users` u
        LEFT JOIN `appointment` a ON a.user_id = u.id
        LEFT JOIN `consultations` c ON c.patient_id = u.id
        WHERE u.role = 'Patient'
    ";
    $params = [];

    if (!empty($search)) {
        $query .= " AND (u.full_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
        $like = '%' . $search . '%';
        $params = [$like, $like, $like];
    }

    $query .= " GROUP BY u.id, u.full_name, u.gender, u.email, u.phone, u.created_at ORDER BY last_appointment_date DESC, u.id DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stats
    $totalPatientCount = count($patients);

} catch (Exception $e) {
    $patients = [];
    $totalPatientCount = 0;
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
                <a href="<?php echo BASE_URL; ?>doctor/patients.php" class="dash-nav-link active">
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
                        <i class="fas fa-user-injured"></i> Patient Medical Records
                        <span class="dash-header-badge" style="background: var(--dash-secondary-light); color: var(--dash-secondary);">Doctor Workspace</span>
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
                        <h2>Clinic Patients Directory</h2>
                        <p>View registered patients with clinical consultations and appointment history</p>
                    </div>
                    <div class="dash-header-actions">
                        <span class="dash-badge-outline" style="padding: 8px 16px; border-radius: 20px; font-weight: 600; background: #fff; border: 1px solid var(--dash-border); font-size: 13px; color: var(--dash-primary);">
                            <i class="fas fa-users"></i> Total Patients: <strong><?php echo $totalPatientCount; ?></strong>
                        </span>
                    </div>
                </div>

                <!-- Search Filter Form -->
                <div class="dash-search-card" style="margin-bottom: 20px;">
                    <form method="GET" action="patients.php" class="dash-search-form" style="display: flex; gap: 10px; max-width: 600px;">
                        <div style="flex: 1; position: relative;">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by patient name, phone, or email..." class="dash-search-input" style="width: 100%; padding: 10px 14px 10px 38px; border-radius: 8px; border: 1px solid var(--dash-border); font-size: 13.5px;">
                            <i class="fas fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #999;"></i>
                        </div>
                        <button type="submit" class="dash-btn-search" style="padding: 10px 20px; background: var(--dash-primary); color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="patients.php" style="padding: 10px 16px; background: #eee; color: #555; text-decoration: none; border-radius: 8px; font-size: 13px; display: inline-flex; align-items: center;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Patients Table Card -->
                <div class="dash-table-card">
                    <div class="dash-table-header">
                        <h3><i class="fas fa-list-alt"></i> Patient Records List</h3>
                    </div>

                    <?php if (empty($patients)): ?>
                        <div class="dash-empty-state" style="padding: 50px 20px; text-align: center;">
                            <i class="fas fa-user-slash" style="font-size: 40px; color: #ccc; margin-bottom: 12px;"></i>
                            <h4>No Patient Records Found</h4>
                            <p style="color: #888; font-size: 13.5px;">No patients match your search criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th>Patient ID</th>
                                        <th>Full Name & Gender</th>
                                        <th>Contact Information</th>
                                        <th>Appointments</th>
                                        <th>Consultations</th>
                                        <th>Last Visit</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patients as $p): ?>
                                        <tr>
                                            <td>
                                                <span class="dash-id-badge">#PT-<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                            </td>
                                            <td>
                                                <div class="patient-name-cell">
                                                    <strong><?php echo htmlspecialchars($p['full_name']); ?></strong>
                                                    <span class="gender-pill"><?php echo htmlspecialchars($p['gender'] ?? 'N/A'); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 13px; color: var(--dash-text-dark);"><i class="fas fa-phone-alt" style="color: var(--dash-primary); font-size: 11px; margin-right: 4px;"></i> <?php echo htmlspecialchars($p['phone']); ?></div>
                                                <div style="font-size: 12px; color: var(--dash-text-muted);"><i class="fas fa-envelope" style="font-size: 11px; margin-right: 4px;"></i> <?php echo htmlspecialchars($p['email']); ?></div>
                                            </td>
                                            <td>
                                                <span class="badge-status badge-confirmed" style="font-size: 12px;">
                                                    <?php echo (int)$p['total_appointments']; ?> Booked
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge-status badge-booked" style="font-size: 12px;">
                                                    <?php echo (int)$p['total_consultations']; ?> Completed
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-size: 12.5px; color: var(--dash-text-dark);">
                                                    <?php echo !empty($p['last_appointment_date']) ? date('d M Y', strtotime($p['last_appointment_date'])) : 'None'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?php echo BASE_URL; ?>doctor/appointments.php?search=<?php echo urlencode($p['full_name']); ?>" class="dash-btn-action-view" style="padding: 6px 12px; font-size: 12px; text-decoration: none; border-radius: 6px; background: #EEF6EF; color: #355E3B; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                                    <i class="fas fa-history"></i> View Queue
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
