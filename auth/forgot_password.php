<?php
$pageTitle = "Forgot Password | Bodhi Homeopathy";
include __DIR__ . '/../includes/header.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT id FROM `users` WHERE `email` = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    // Generate token
                    $token = bin2hex(random_bytes(16)); // 32 hex chars
                    $hashed_token = hash('sha256', $token);
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // Store token hash in db
                    $stmt = $pdo->prepare("INSERT INTO `password_resets` (`email`, `token`, `expires_at`) VALUES (?, ?, ?)");
                    $stmt->execute([$email, $hashed_token, $expires_at]);

                    // Generate reset link using configurable APP_URL
                    $reset_link = rtrim(defined('APP_URL') ? APP_URL : BASE_URL, '/') . "/reset_password.php?token=" . $token;

                    $isSmtpConfigured = !empty(SMTP_HOST) && !empty(SMTP_USER) && !empty(SMTP_PASS) && !SMTP_TEST_MODE;

                    if ($isSmtpConfigured) {
                        try {
                            // Try to load PHPMailer files
                            require_once __DIR__ . '/../includes/PHPMailer/Exception.php';
                            require_once __DIR__ . '/../includes/PHPMailer/PHPMailer.php';
                            require_once __DIR__ . '/../includes/PHPMailer/SMTP.php';

                            $mail = new PHPMailer(true);
                            
                            // SMTP Settings from config
                            $mail->isSMTP();
                            $mail->Host       = SMTP_HOST;
                            $mail->SMTPAuth   = true;
                            $mail->Username   = SMTP_USER;
                            $mail->Password   = SMTP_PASS;
                            $mail->SMTPSecure = (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port       = defined('SMTP_PORT') ? SMTP_PORT : 587;

                            // Recipients
                            $fromAddress = (!empty(SMTP_FROM_EMAIL) && filter_var(SMTP_FROM_EMAIL, FILTER_VALIDATE_EMAIL)) ? SMTP_FROM_EMAIL : SMTP_USER;
                            $mail->setFrom($fromAddress, defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Bodhi Homeopathy');
                            $mail->addAddress($email);

                            // Branded Content
                            $mail->isHTML(true);
                            $mail->Subject = 'Password Reset Request | Bodhi Homeopathy Clinic';
                            $mail->Body    = "
                                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;'>
                                    <div style='background: #355E3B; padding: 20px; text-align: center; color: #ffffff;'>
                                        <h2 style='margin: 0; font-size: 22px; letter-spacing: 0.5px;'>Bodhi Homeopathy Clinic</h2>
                                        <p style='margin: 4px 0 0; font-size: 13px; opacity: 0.9;'>Personalized Classical Healthcare</p>
                                    </div>
                                    <div style='padding: 30px 25px; color: #333333; line-height: 1.6;'>
                                        <p style='font-size: 16px; margin-top: 0;'>Hello,</p>
                                        <p>We received a request to reset the password for your Bodhi Homeopathy patient account associated with <strong>" . htmlspecialchars($email) . "</strong>.</p>
                                        <p>Click the button below to choose a new secure password. This reset link is valid for <strong>1 hour</strong>.</p>
                                        <div style='text-align: center; margin: 30px 0;'>
                                            <a href='" . $reset_link . "' style='background: #E79A32; color: #ffffff; padding: 12px 28px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 4px 12px rgba(231,154,50,0.3);'>Reset Password</a>
                                        </div>
                                        <p style='font-size: 13px; color: #777;'>Or copy and paste this link in your browser:<br><span style='word-break: break-all; color: #355E3B;'>" . $reset_link . "</span></p>
                                        <hr style='border: none; border-top: 1px solid #eeeeee; margin: 25px 0;'>
                                        <p style='font-size: 12px; color: #888; margin-bottom: 0;'>If you did not request this password reset, please disregard this email. Your password will remain unchanged.</p>
                                    </div>
                                    <div style='background: #FAF9F6; padding: 15px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #f0f0f0;'>
                                        &copy; " . date('Y') . " Bodhi Homeopathy Clinic. All rights reserved.
                                    </div>
                                </div>
                            ";

                            $mail->send();
                        } catch (Exception $mailEx) {
                            // Invalidate token if email dispatch fails
                            error_log("Password reset mail sending failed: " . $mailEx->getMessage());
                            $delStmt = $pdo->prepare("DELETE FROM `password_resets` WHERE `token` = ?");
                            $delStmt->execute([$hashed_token]);
                        }
                    } else {
                        // Invalidate token if SMTP is unconfigured so no usable tokens linger
                        error_log("Password reset requested but SMTP is unconfigured.");
                        $delStmt = $pdo->prepare("DELETE FROM `password_resets` WHERE `token` = ?");
                        $delStmt->execute([$hashed_token]);
                    }
                }

                // Generic message to prevent email enumeration and protect user privacy
                $success = 'If an account exists for this email, a password reset link has been sent.';

            } catch (PDOException $e) {
                error_log("Database error during password reset: " . $e->getMessage());
                $error = 'A system error occurred. Please try again later.';
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
            <h2>Reset Your Password</h2>
            <p class="auth-subtitle">Enter your email and we'll send you a secure link to reset your password.</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="background:#FDE8E8; color:#9B1C1C; padding:12px 16px; border-radius:6px; margin-bottom:18px; font-size:13.5px; border-left:4px solid #E02424;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success" style="background:#DEF7EC; color:#03543F; padding:14px 16px; border-radius:6px; margin-bottom:18px; font-size:13.5px; border-left:4px solid #31C48D;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form action="forgot_password.php" method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                <div class="form-group-pro">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? sanitize_input($_POST['email']) : ''; ?>">
                </div>

                <button type="submit" class="btn-auth-submit">SEND RESET LINK</button>

                <p class="auth-switch">Remembered password? <a href="login.php">Log In here</a></p>
            </form>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
