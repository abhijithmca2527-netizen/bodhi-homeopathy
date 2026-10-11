<?php
$pageTitle = "Reset Password | Bodhi Homeopathy";
include __DIR__ . '/../includes/header.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$email = '';

if (empty($token)) {
    $error = 'Invalid token or reset request link.';
} else {
    $hashed_token = hash('sha256', $token);
    try {
        // Verify token exists and is not expired
        $stmt = $pdo->prepare("SELECT * FROM `password_resets` WHERE `token` = ? AND `expires_at` > ?");
        $stmt->execute([$hashed_token, date('Y-m-d H:i:s')]);
        $reset_record = $stmt->fetch();

        if (!$reset_record) {
            $error = 'The password reset link is invalid or has expired.';
        } else {
            $email = $reset_record['email'];
        }
    } catch (PDOException $e) {
        error_log("Password reset verification error: " . $e->getMessage());
        $error = 'The password reset link is invalid or has expired.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate password
        if (empty($password)) {
            $error = 'Password field is required.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error = 'Password must contain at least one number.';
        } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $error = 'Password must contain at least one special character.';
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Update user password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `email` = ?");
                $stmt->execute([$hashed_password, $email]);

                // 2. Delete all reset tokens for this email
                $stmt = $pdo->prepare("DELETE FROM `password_resets` WHERE `email` = ?");
                $stmt->execute([$email]);

                $pdo->commit();
                $success = 'Password has been updated successfully! You can now log in.';
                // Inhibit showing form again
                $email = ''; 
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Password update error: " . $e->getMessage());
                $error = 'Failed to update password. Please try again.';
            }
        }
    }
}
?>

<section class="auth-section">
    <div class="auth-container">
        <div class="auth-logo-wrapper">
            <a href="index.php"><img src="assets/images/lgo.png" alt="Bodhi Logo"></a>
        </div>
        <div class="auth-card">
            <h2>Set New Password</h2>
            <p class="auth-subtitle">Choose a strong password to protect your account.</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
                <p class="auth-switch" style="text-align: center; margin-top: 20px;"><a href="login.php" class="btn-book" style="color: white !important;">GO TO LOGIN</a></p>
            <?php endif; ?>

            <?php if (!empty($email) && empty($success)): ?>
                <form action="reset_password.php" method="POST" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="token" value="<?php echo sanitize_input($token); ?>">

                    <div class="form-group-pro password-field-container">
                        <label for="password">New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="password" name="password" required autocomplete="new-password">
                            <button type="button" class="toggle-password" tabindex="-1" aria-label="Toggle password visibility" onclick="togglePasswordVisibility('password')">
                                <i class="fas fa-eye" id="password-eye"></i>
                            </button>
                        </div>
                        <small class="form-help">Min. 8 characters, 1 uppercase, 1 number, and 1 special character.</small>
                    </div>

                    <div class="form-group-pro password-field-container">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                            <button type="button" class="toggle-password" tabindex="-1" aria-label="Toggle password visibility" onclick="togglePasswordVisibility('confirm_password')">
                                <i class="fas fa-eye" id="confirm_password-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-auth-submit">RESET PASSWORD</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
function togglePasswordVisibility(fieldId) {
    const field = document.getElementById(fieldId);
    const eye = document.getElementById(fieldId + '-eye');
    if (!field || !eye) return;
    if (field.type === 'password') {
        field.type = 'text';
        eye.classList.remove('fa-eye');
        eye.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        eye.classList.remove('fa-eye-slash');
        eye.classList.add('fa-eye');
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
