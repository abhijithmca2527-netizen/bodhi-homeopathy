<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: strictly enforce Admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Admin Portal - Settings | Bodhi Homeopathy";
$adminId = (int)$_SESSION['user_id'];
$msg = '';
$msgType = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($fullName) || empty($phone)) {
        $msg = "Full Name and Phone Number are required.";
        $msgType = "error";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE `users` SET `full_name` = ?, `phone` = ? WHERE `id` = ? AND `role` = 'Admin'");
            $stmt->execute([$fullName, $phone, $adminId]);
            $_SESSION['full_name'] = $fullName;
            $msg = "Admin profile details updated successfully.";
            $msgType = "success";
        } catch (Exception $e) {
            $msg = "Error updating profile: " . $e->getMessage();
            $msgType = "error";
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        $msg = "All password fields are required.";
        $msgType = "error";
    } elseif ($newPass !== $confirmPass) {
        $msg = "New password and confirmation do not match.";
        $msgType = "error";
    } elseif (strlen($newPass) < 8) {
        $msg = "New password must be at least 8 characters long.";
        $msgType = "error";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT `password` FROM `users` WHERE `id` = ?");
            $stmt->execute([$adminId]);
            $hash = $stmt->fetchColumn();

            if ($hash && password_verify($currentPass, $hash)) {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `id` = ?");
                $stmt->execute([$newHash, $adminId]);
                $msg = "Admin password changed successfully.";
                $msgType = "success";
            } else {
                $msg = "Incorrect current password.";
                $msgType = "error";
            }
        } catch (Exception $e) {
            $msg = "Error changing password: " . $e->getMessage();
            $msgType = "error";
        }
    }
}

// Fetch admin details
$stmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
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
                <a href="<?php echo BASE_URL; ?>admin/appointments.php" class="dash-nav-link">
                    <i class="fas fa-calendar-check"></i> Appointments
                </a>
                <a href="<?php echo BASE_URL; ?>admin/products.php" class="dash-nav-link">
                    <i class="fas fa-pills"></i> Products / Medicines
                </a>
                <a href="<?php echo BASE_URL; ?>admin/orders.php" class="dash-nav-link">
                    <i class="fas fa-shopping-bag"></i> Orders
                </a>

                <span class="dash-nav-section" style="margin-top: 10px;">Analytics & System</span>
                <a href="<?php echo BASE_URL; ?>admin/reports.php" class="dash-nav-link">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <a href="<?php echo BASE_URL; ?>admin/settings.php" class="dash-nav-link active">
                    <i class="fas fa-sliders-h"></i> Settings
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
                        <i class="fas fa-sliders-h"></i> System Settings & Profile
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

            <main class="dash-content">
                
                <div class="dash-page-header">
                    <div class="dash-page-header-text">
                        <h2>Administrator Settings</h2>
                        <p>Configure administrator account credentials, profile details, and clinic preferences</p>
                    </div>
                </div>

                <?php if (!empty($msg)): ?>
                    <div class="dash-alert dash-alert-<?php echo $msgType; ?>" style="margin-bottom: 20px; padding: 14px 18px; border-radius: 8px; font-size: 13.5px; <?php echo $msgType === 'success' ? 'background: #DEF7EC; color: #03543F; border-left: 4px solid #31C48D;' : 'background: #FDE8E8; color: #9B1C1C; border-left: 4px solid #E02424;'; ?>">
                        <i class="fas fa-<?php echo $msgType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($msg); ?>
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start;">
                    
                    <!-- Admin Profile Form -->
                    <div class="dash-card" style="background: #fff; padding: 25px; border-radius: 12px; border: 1px solid var(--dash-border); box-shadow: var(--dash-shadow);">
                        <h3 style="color: var(--dash-primary); font-size: 16px; margin-bottom: 18px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-user-shield"></i> Administrator Profile
                        </h3>

                        <form method="POST" action="settings.php">
                            <input type="hidden" name="action" value="update_profile">

                            <div style="margin-bottom: 14px;">
                                <label style="font-size: 13px; font-weight: 600; color: #333; display: block; margin-bottom: 6px;">Administrator Name</label>
                                <input type="text" name="full_name" required value="<?php echo htmlspecialchars($admin['full_name'] ?? ''); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--dash-border); border-radius: 6px; font-family: inherit; font-size: 13.5px;">
                            </div>

                            <div style="margin-bottom: 14px;">
                                <label style="font-size: 13px; font-weight: 600; color: #333; display: block; margin-bottom: 6px;">Email Address (Read-only)</label>
                                <input type="email" value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" readonly style="width: 100%; padding: 10px 14px; border: 1px solid var(--dash-border); border-radius: 6px; font-family: inherit; font-size: 13.5px; background: #F7F8F4; cursor: not-allowed;">
                            </div>

                            <div style="margin-bottom: 18px;">
                                <label style="font-size: 13px; font-weight: 600; color: #333; display: block; margin-bottom: 6px;">Contact Phone</label>
                                <input type="tel" name="phone" required value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--dash-border); border-radius: 6px; font-family: inherit; font-size: 13.5px;">
                            </div>

                            <button type="submit" style="padding: 10px 20px; background: var(--dash-primary); color: #fff; border: none; border-radius: 6px; font-weight: 600; font-size: 13.5px; cursor: pointer;">
                                Save Administrator Profile
                            </button>
                        </form>
                    </div>

                    <!-- Change Password Form -->
                    <div class="dash-card" style="background: #fff; padding: 25px; border-radius: 12px; border: 1px solid var(--dash-border); box-shadow: var(--dash-shadow);">
                        <h3 style="color: var(--dash-primary); font-size: 16px; margin-bottom: 18px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-lock"></i> Security & Password
                        </h3>

                        <form method="POST" action="settings.php">
                            <input type="hidden" name="action" value="change_password">

                            <div style="margin-bottom: 14px;">
                                <label style="font-size: 13px; font-weight: 600; color: #333; display: block; margin-bottom: 6px;">Current Password</label>
                                <input type="password" name="current_password" required placeholder="Enter current password" style="width: 100%; padding: 10px 14px; border: 1px solid var(--dash-border); border-radius: 6px; font-family: inherit; font-size: 13.5px;">
                            </div>

                            <div style="margin-bottom: 14px;">
                                <label style="font-size: 13px; font-weight: 600; color: #333; display: block; margin-bottom: 6px;">New Password</label>
                                <input type="password" name="new_password" required placeholder="Minimum 8 characters" style="width: 100%; padding: 10px 14px; border: 1px solid var(--dash-border); border-radius: 6px; font-family: inherit; font-size: 13.5px;">
                            </div>

                            <div style="margin-bottom: 18px;">
                                <label style="font-size: 13px; font-weight: 600; color: #333; display: block; margin-bottom: 6px;">Confirm New Password</label>
                                <input type="password" name="confirm_password" required placeholder="Re-enter new password" style="width: 100%; padding: 10px 14px; border: 1px solid var(--dash-border); border-radius: 6px; font-family: inherit; font-size: 13.5px;">
                            </div>

                            <button type="submit" style="padding: 10px 20px; background: var(--dash-secondary); color: #fff; border: none; border-radius: 6px; font-weight: 600; font-size: 13.5px; cursor: pointer;">
                                Update Password
                            </button>
                        </form>
                    </div>

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
