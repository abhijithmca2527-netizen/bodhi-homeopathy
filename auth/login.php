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
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            try {
                // Fetch user
                $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `email` = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Password is correct, start session
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];

                    // Dynamic redirect based on role
                    if ($user['role'] === 'Admin') {
                        header("Location: " . BASE_URL . "admin/dashboard.php");
                    } elseif ($user['role'] === 'Doctor') {
                        header("Location: " . BASE_URL . "doctor/dashboard.php");
                    } else {
                        header("Location: " . BASE_URL . "index.php");
                    }
                    exit;
                } else {
                    $error = 'Invalid email or password.';
                }
            } catch (PDOException $e) {
                error_log("Login authentication error: " . $e->getMessage());
                $error = 'A system error occurred. Please try again later.';
            }
        }
    }
}

$pageTitle = "Login | Bodhi Homeopathy";
include __DIR__ . '/../includes/header.php';
?>

<section class="auth-section">
    <div class="auth-container">
        <div class="auth-logo-wrapper">
            <a href="<?php echo BASE_URL; ?>index.php"><img src="<?php echo BASE_URL; ?>assets/images/lgo.png" alt="Bodhi Logo"></a>
        </div>
        <div class="auth-card">
            <h2>Welcome Back</h2>
            <p class="auth-subtitle">Log in to manage appointments, orders, and your medical profile.</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="<?php echo BASE_URL; ?>login.php" method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                <div class="form-group-pro">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? sanitize_input($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group-pro password-field-container">
                    <div class="label-wrapper-forgot">
                        <label for="password">Password</label>
                        <a href="<?php echo BASE_URL; ?>forgot_password.php" class="forgot-link">Forgot Password?</a>
                    </div>
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password" required autocomplete="current-password">
                        <button type="button" class="toggle-password" tabindex="-1" aria-label="Toggle password visibility" onclick="togglePasswordVisibility('password')">
                            <i class="fas fa-eye" id="password-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-auth-submit">LOG IN</button>

                <p class="auth-switch">Don't have an account yet? <a href="<?php echo BASE_URL; ?>register.php">Register here</a></p>
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
