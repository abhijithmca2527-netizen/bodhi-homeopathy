<?php
$pageTitle = "Appointment Confirmation | Bodhi Homeopathy";
include __DIR__ . '/../includes/header.php';

$appId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$appointment = null;

if ($appId > 0) {
    try {
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("SELECT * FROM `appointment` WHERE `id` = ? AND (`user_id` = ? OR `user_id` IS NULL)");
            $stmt->execute([$appId, (int)$_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM `appointment` WHERE `id` = ?");
            $stmt->execute([$appId]);
        }
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $appointment = null;
    }
}
?>

<section class="booking-confirmation-section" style="background: #FAF9F6; min-height: 80vh; padding: 50px 15px 80px; display: flex; align-items: center; justify-content: center;">
    <div class="container" style="max-width: 680px; margin: 0 auto; width: 100%;">
        
        <?php if ($appointment): ?>
            <div class="confirmation-card" style="background: #ffffff; border-radius: 14px; border: 1px solid rgba(79, 111, 82, 0.12); box-shadow: 0 12px 40px rgba(53, 94, 59, 0.08); overflow: hidden; text-align: center;">
                
                <!-- Success Header Banner -->
                <div style="background: linear-gradient(135deg, #355E3B, #244228); padding: 35px 25px; color: #ffffff;">
                    <div style="width: 64px; height: 64px; background: #ffffff; color: #355E3B; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                        <i class="fas fa-check"></i>
                    </div>
                    <h1 style="font-size: 24px; font-weight: 700; margin: 0 0 6px; letter-spacing: -0.3px;">Appointment Booked Successfully!</h1>
                    <p style="font-size: 14px; opacity: 0.9; margin: 0;">Your consultation request has been recorded in our clinic system.</p>
                </div>

                <div style="padding: 30px 35px; text-align: left;">
                    
                    <!-- Reference Badge -->
                    <div style="background: #EEF6EF; border: 1px solid #C6E2CC; border-radius: 8px; padding: 12px 18px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #355E3B; letter-spacing: 0.5px;">Appointment Reference</span>
                            <div style="font-size: 18px; font-weight: 700; color: #2D4F32;">#APP-<?php echo str_pad($appointment['id'], 4, '0', STR_PAD_LEFT); ?></div>
                        </div>
                        <div>
                            <span style="background: #E79A32; color: #ffffff; font-size: 12px; font-weight: 600; padding: 4px 12px; border-radius: 20px;">
                                <?php echo htmlspecialchars($appointment['status'] ?? 'Booked'); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Appointment Summary Details -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                        <div style="background: #FAF9F6; padding: 12px 16px; border-radius: 8px; border: 1px solid #eee;">
                            <span style="font-size: 11.5px; color: #777; display: block; margin-bottom: 2px;">Patient Name</span>
                            <strong style="font-size: 14px; color: #222;"><?php echo htmlspecialchars($appointment['patient_name']); ?></strong>
                        </div>
                        <div style="background: #FAF9F6; padding: 12px 16px; border-radius: 8px; border: 1px solid #eee;">
                            <span style="font-size: 11.5px; color: #777; display: block; margin-bottom: 2px;">Contact Phone</span>
                            <strong style="font-size: 14px; color: #222;"><?php echo htmlspecialchars($appointment['phone']); ?></strong>
                        </div>
                        <div style="background: #FAF9F6; padding: 12px 16px; border-radius: 8px; border: 1px solid #eee;">
                            <span style="font-size: 11.5px; color: #777; display: block; margin-bottom: 2px;">Appointment Date</span>
                            <strong style="font-size: 14px; color: #222;"><?php echo date('d M Y (l)', strtotime($appointment['appointment_date'])); ?></strong>
                        </div>
                        <div style="background: #FAF9F6; padding: 12px 16px; border-radius: 8px; border: 1px solid #eee;">
                            <span style="font-size: 11.5px; color: #777; display: block; margin-bottom: 2px;">Preferred Time Slot</span>
                            <strong style="font-size: 14px; color: #222;"><?php echo !empty($appointment['time_slot']) ? htmlspecialchars($appointment['time_slot']) : 'Regular Clinic Hours'; ?></strong>
                        </div>
                        <div style="background: #FAF9F6; padding: 12px 16px; border-radius: 8px; border: 1px solid #eee;">
                            <span style="font-size: 11.5px; color: #777; display: block; margin-bottom: 2px;">Consultation Category</span>
                            <strong style="font-size: 14px; color: #222;"><?php echo htmlspecialchars($appointment['category'] ?? 'General Health'); ?></strong>
                        </div>
                        <div style="background: #FAF9F6; padding: 12px 16px; border-radius: 8px; border: 1px solid #eee;">
                            <span style="font-size: 11.5px; color: #777; display: block; margin-bottom: 2px;">Consultation Mode</span>
                            <strong style="font-size: 14px; color: #222;"><?php echo htmlspecialchars($appointment['mode'] ?? 'Direct Clinic Visit'); ?></strong>
                        </div>
                    </div>

                    <?php if (!empty($appointment['notes'])): ?>
                        <div style="background: #FAF9F6; padding: 12px 16px; border-radius: 8px; border: 1px solid #eee; margin-bottom: 24px;">
                            <span style="font-size: 11.5px; color: #777; display: block; margin-bottom: 2px;">Patient Symptoms / Notes</span>
                            <p style="margin: 0; font-size: 13px; color: #444;"><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <div style="background: #FEF9EF; border: 1px solid #F8DFAD; border-radius: 8px; padding: 12px 16px; font-size: 12.5px; color: #8F5B0F; margin-bottom: 28px; line-height: 1.4;">
                        <i class="fas fa-info-circle" style="margin-right: 4px;"></i>
                        Our clinic reception will confirm your time slot. Please arrive 10 minutes prior to your scheduled consultation time.
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="<?php echo BASE_URL; ?>bookings.php" style="background: #355E3B; color: #ffffff; padding: 12px 24px; border-radius: 50px; font-size: 13.5px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(53,94,59,0.25);">
                                <i class="fas fa-calendar-check"></i> View My Bookings
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL; ?>book.php" style="background: #E79A32; color: #ffffff; padding: 12px 24px; border-radius: 50px; font-size: 13.5px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(231,154,50,0.25);">
                            <i class="fas fa-plus"></i> Book Another Appointment
                        </a>
                        <a href="<?php echo BASE_URL; ?>index.php" style="background: #ffffff; color: #555; border: 1px solid #dcdcdc; padding: 12px 22px; border-radius: 50px; font-size: 13.5px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-home"></i> Return Home
                        </a>
                    </div>

                </div>
            </div>
        <?php else: ?>
            <div style="background: #ffffff; border-radius: 14px; padding: 40px; text-align: center; border: 1px solid #eee; box-shadow: 0 8px 30px rgba(0,0,0,0.04);">
                <div style="font-size: 48px; color: #E79A32; margin-bottom: 16px;"><i class="fas fa-calendar-times"></i></div>
                <h2 style="color: #355E3B; font-size: 22px; margin-bottom: 10px;">Appointment Details Not Available</h2>
                <p style="color: #666; font-size: 14px; margin-bottom: 24px;">We could not locate the requested appointment confirmation record.</p>
                <a href="<?php echo BASE_URL; ?>book.php" style="background: #355E3B; color: #fff; padding: 10px 24px; border-radius: 50px; text-decoration: none; font-weight: 600; font-size: 13.5px;">Book an Appointment</a>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
