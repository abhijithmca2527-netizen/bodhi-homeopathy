<?php
require_once __DIR__ . '/../includes/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'Admin') {
        header("Location: " . BASE_URL . "admin/dashboard.php");
    } elseif ($_SESSION['role'] === 'Doctor') {
        header("Location: " . BASE_URL . "doctor/dashboard.php");
    } else {
        header("Location: " . BASE_URL . "index.php");
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid CSRF token.';
    } else {
        $full_name = sanitize_input($_POST['full_name'] ?? '');
        $gender = sanitize_input($_POST['gender'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $phone = sanitize_input($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($full_name) || empty($gender) || empty($email) || empty($phone) || empty($password)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
            $error = 'Phone number must be between 10 and 15 digits.';
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
                // Check unique email
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `email` = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'This email is already registered.';
                } else {
                    // Check unique phone
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `phone` = ?");
                    $stmt->execute([$phone]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'This phone number is already registered.';
                    } else {
                        // Hash password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                        // Insert user
                        $stmt = $pdo->prepare("INSERT INTO `users` (`full_name`, `gender`, `email`, `phone`, `password`, `role`) VALUES (?, ?, ?, ?, ?, 'Patient')");
                        $stmt->execute([$full_name, $gender, $email, $phone, $hashed_password]);

                        $success = 'Registration successful! Your patient account has been created. You can now log in.';
                        // Clear form fields on success
                        $_POST = [];
                    }
                }
            } catch (PDOException $e) {
                $error = 'A system error occurred. Please try again.';
            }
        }
    }
}

$pageTitle = "Register | Bodhi Homeopathy";
include __DIR__ . '/../includes/header.php';
?>

<section class="auth-section">
    <div class="auth-container">
        <div class="auth-logo-wrapper">
            <a href="<?php echo BASE_URL; ?>index.php"><img src="<?php echo BASE_URL; ?>assets/images/lgo.png" alt="Bodhi Logo"></a>
        </div>
        <div class="auth-card">
            <h2>Create Account</h2>
            <p class="auth-subtitle">Join Bodhi Homeopathy for holistic healthcare & personalized treatment.</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="background:#FDE8E8; color:#9B1C1C; padding:12px 16px; border-radius:6px; margin-bottom:18px; font-size:13.5px; border-left:4px solid #E02424;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success" style="background:#DEF7EC; color:#03543F; padding:14px 16px; border-radius:6px; margin-bottom:18px; font-size:13.5px; border-left:4px solid #31C48D;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    <div style="margin-top: 10px;">
                        <a href="<?php echo BASE_URL; ?>login.php" style="display:inline-block; background:var(--auth-primary); color:#fff; padding:6px 14px; border-radius:4px; font-size:12.5px; font-weight:600; text-decoration:none;">Go to Login &rarr;</a>
                    </div>
                </div>
            <?php endif; ?>

            <form action="<?php echo BASE_URL; ?>register.php" method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                <div class="form-group-pro">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required placeholder="e.g. Rahul Sharma" value="<?php echo isset($_POST['full_name']) ? sanitize_input($_POST['full_name']) : ''; ?>">
                </div>

                <div class="form-group-pro">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="form-group-pro">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="e.g. rahul@example.com" value="<?php echo isset($_POST['email']) ? sanitize_input($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group-pro">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" required placeholder="e.g. 9876543210" value="<?php echo isset($_POST['phone']) ? sanitize_input($_POST['phone']) : ''; ?>">
                </div>

                <div class="form-group-pro password-field-container">
                    <label for="password">Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password" required autocomplete="new-password">
                        <button type="button" class="toggle-password" tabindex="-1" aria-label="Toggle password visibility" onclick="togglePasswordVisibility('password')">
                            <i class="fas fa-eye" id="password-eye"></i>
                        </button>
                    </div>
                    <small class="form-help">Min. 8 characters, 1 uppercase, 1 number, and 1 special character.</small>
                </div>

                <div class="form-group-pro password-field-container">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                        <button type="button" class="toggle-password" tabindex="-1" aria-label="Toggle password visibility" onclick="togglePasswordVisibility('confirm_password')">
                            <i class="fas fa-eye" id="confirm_password-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-auth-submit">REGISTER</button>

                <p class="auth-switch">Already have an account? <a href="<?php echo BASE_URL; ?>login.php">Log In here</a></p>
            </form>
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
