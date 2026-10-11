<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: require logged-in Patient
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "My Profile | Bodhi Homeopathy";
$userId = (int)$_SESSION['user_id'];
$msg = '';
$msgType = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        $msg = "Invalid security token. Please try again.";
        $msgType = "error";
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $gender = trim($_POST['gender'] ?? 'Other');
        $address = trim($_POST['address'] ?? '');

        if (empty($fullName) || empty($phone)) {
            $msg = "Full Name and Phone Number are required.";
            $msgType = "error";
        } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
            $msg = "Phone number must be between 10 and 15 digits.";
            $msgType = "error";
        } else {
            try {
                // Check unique phone for other users
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `phone` = ? AND `id` != ?");
                $stmt->execute([$phone, $userId]);
                if ($stmt->fetchColumn() > 0) {
                    $msg = "This phone number is already registered to another account.";
                    $msgType = "error";
                } else {
                    $stmt = $pdo->prepare("UPDATE `users` SET `full_name` = ?, `phone` = ?, `gender` = ?, `address` = ? WHERE `id` = ?");
                    $stmt->execute([$fullName, $phone, $gender, $address, $userId]);
                    $_SESSION['full_name'] = $fullName;
                    $msg = "Your profile details have been updated successfully.";
                    $msgType = "success";
                }
            } catch (Exception $e) {
                error_log("Profile update error: " . $e->getMessage());
                $msg = "An error occurred while updating profile. Please try again.";
                $msgType = "error";
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<section class="container" style="margin-top: 50px; margin-bottom: 80px; max-width: 820px;">
    <div class="description-card" style="padding: 40px; border-radius: 14px; background: #fff; box-shadow: 0 10px 35px rgba(53, 94, 59, 0.06); border: 1px solid rgba(79, 111, 82, 0.12);">
        
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; margin-bottom: 25px;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="width: 58px; height: 58px; border-radius: 50%; background: #EEF6EF; color: #355E3B; display: flex; align-items: center; justify-content: center; font-size: 26px;">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div>
                    <h2 style="color: var(--primary-dark); font-size: 22px; font-weight: 700; margin: 0;"><?php echo htmlspecialchars($user['full_name'] ?? $_SESSION['full_name']); ?></h2>
                    <span style="font-size: 12.5px; color: var(--secondary); font-weight: 600;">Registered Patient (#PT-<?php echo str_pad($userId, 4, '0', STR_PAD_LEFT); ?>)</span>
                </div>
            </div>
            <div>
                <span style="background: #FAF9F6; border: 1px solid #E2E8F0; padding: 6px 14px; border-radius: 20px; font-size: 12px; color: #718096;">
                    <i class="far fa-calendar-alt"></i> Member since <?php echo !empty($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : '2026'; ?>
                </span>
            </div>
        </div>

        <?php if (!empty($msg)): ?>
            <div style="margin-bottom: 20px; padding: 14px 18px; border-radius: 8px; font-size: 13.5px; <?php echo $msgType === 'success' ? 'background: #DEF7EC; color: #03543F; border-left: 4px solid #31C48D;' : 'background: #FDE8E8; color: #9B1C1C; border-left: 4px solid #E02424;'; ?>">
                <i class="fas fa-<?php echo $msgType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <!-- Edit Profile Form -->
        <form method="POST" action="profile.php">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="update_profile">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="font-size: 13px; font-weight: 600; color: #355E3B; margin-bottom: 6px; display: block;">Full Name *</label>
                    <input type="text" name="full_name" required value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid #dcdcdc; border-radius: 6px; font-family: inherit; font-size: 13.5px;">
                </div>

                <div>
                    <label style="font-size: 13px; font-weight: 600; color: #355E3B; margin-bottom: 6px; display: block;">Gender *</label>
                    <select name="gender" required style="width: 100%; padding: 10px 14px; border: 1px solid #dcdcdc; border-radius: 6px; font-family: inherit; font-size: 13.5px; background: #fff;">
                        <option value="Male" <?php echo ($user['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($user['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($user['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div>
                    <label style="font-size: 13px; font-weight: 600; color: #355E3B; margin-bottom: 6px; display: block;">Email Address (Account ID)</label>
                    <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly style="width: 100%; padding: 10px 14px; border: 1px solid #dcdcdc; border-radius: 6px; font-family: inherit; font-size: 13.5px; background: #F7F8F4; cursor: not-allowed;">
                </div>

                <div>
                    <label style="font-size: 13px; font-weight: 600; color: #355E3B; margin-bottom: 6px; display: block;">Contact Phone Number *</label>
                    <input type="tel" name="phone" required value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="10-digit number" style="width: 100%; padding: 10px 14px; border: 1px solid #dcdcdc; border-radius: 6px; font-family: inherit; font-size: 13.5px;">
                </div>
            </div>

            <div style="margin-bottom: 22px;">
                <label style="font-size: 13px; font-weight: 600; color: #355E3B; margin-bottom: 6px; display: block;">Residential / Delivery Address</label>
                <textarea name="address" rows="3" placeholder="Enter your full street address, city, state, pin code..." style="width: 100%; padding: 10px 14px; border: 1px solid #dcdcdc; border-radius: 6px; font-family: inherit; font-size: 13.5px; resize: vertical;"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; border-top: 1px solid #F0F0F0; padding-top: 20px;">
                <button type="submit" class="btn-book" style="padding: 10px 24px; font-size: 13.5px; border: none; cursor: pointer;">
                    Save Profile Details
                </button>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <a href="<?php echo BASE_URL; ?>bookings.php" style="color: #355E3B; font-size: 13px; font-weight: 600; text-decoration: none;"><i class="fas fa-calendar-check"></i> My Bookings</a>
                    <a href="<?php echo BASE_URL; ?>my_orders.php" style="color: #E79A32; font-size: 13px; font-weight: 600; text-decoration: none;"><i class="fas fa-shopping-bag"></i> My Orders</a>
                </div>
            </div>
        </form>

    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
