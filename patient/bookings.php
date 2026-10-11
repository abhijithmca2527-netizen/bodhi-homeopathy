<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: require logged-in Patient
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "My Bookings | Bodhi Homeopathy";
include __DIR__ . '/../includes/header.php';

// Flash message check
$flashMsg = $_SESSION['patient_booking_msg'] ?? null;
$flashType = $_SESSION['patient_booking_msg_type'] ?? 'success';
unset($_SESSION['patient_booking_msg'], $_SESSION['patient_booking_msg_type']);

$stmt = $pdo->prepare("
    SELECT a.*, c.id AS cons_id, c.notes AS cons_notes, c.prescription AS cons_prescription, c.created_at AS cons_date
    FROM `appointment` a
    LEFT JOIN `consultations` c ON c.appointment_id = a.id
    WHERE a.`user_id` = ?
    ORDER BY a.`id` DESC
");
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper to evaluate cancellation eligibility
function check_patient_cancellation_eligibility($b) {
    $status = $b['status'] ?? 'Booked';
    if (!in_array($status, ['Booked', 'Confirmed'], true)) {
        return ['eligible' => false, 'reason' => 'Status is ' . $status];
    }

    if (!empty($b['cons_id']) || !empty($b['cons_prescription']) || !empty($b['cons_notes'])) {
        return ['eligible' => false, 'reason' => 'Consultation already recorded'];
    }

    $apptDate = $b['appointment_date'] ?? $b['date'] ?? '';
    $timeSlot = trim($b['time_slot'] ?? '');
    $now = time();
    $startTime = null;

    if (!empty($apptDate)) {
        if (!empty($timeSlot) && strtolower($timeSlot) !== 'regular hours') {
            $parts = explode('-', $timeSlot);
            $startTimeStr = trim($parts[0]);
            $parsed = strtotime($apptDate . ' ' . $startTimeStr);
            if ($parsed !== false) {
                $startTime = $parsed;
            }
        }

        if ($startTime !== null) {
            $cutoff = $startTime - (30 * 60); // 30 mins before
            if ($now > $cutoff) {
                return ['eligible' => false, 'reason' => 'Cancellation unavailable within 30 minutes of appointment'];
            }
        } else {
            $dateMidnight = strtotime($apptDate . ' 23:59:59');
            if ($now > $dateMidnight) {
                return ['eligible' => false, 'reason' => 'Appointment date has passed'];
            }
        }
    }

    return ['eligible' => true, 'reason' => ''];
}
?>

<style>
.btn-cancel-booking-outline {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #DC2626;
    background: #FFF5F5;
    border: 1.5px solid #FCA5A5;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s ease;
}
.btn-cancel-booking-outline:hover {
    background: #DC2626;
    color: #FFFFFF;
    border-color: #DC2626;
    box-shadow: 0 4px 10px rgba(220, 38, 38, 0.2);
}
.cancel-modal-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
.cancel-modal-backdrop.active {
    display: flex;
}
.cancel-modal-card {
    background: #FFFFFF;
    border-radius: 14px;
    max-width: 460px;
    width: 90%;
    padding: 28px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    animation: modalPop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes modalPop {
    from { opacity: 0; transform: scale(0.94); }
    to { opacity: 1; transform: scale(1); }
}
</style>

<section class="container" style="margin-top: 45px; margin-bottom: 80px;">
    
    <!-- Flash Notification Banner -->
    <?php if (!empty($flashMsg)): ?>
        <div style="padding: 14px 20px; border-radius: 10px; margin-bottom: 24px; font-size: 13.5px; font-weight: 500; display: flex; align-items: center; gap: 10px; <?php echo $flashType === 'success' ? 'background: #DEF7EC; color: #03543F; border: 1px solid #BCF0DA;' : 'background: #FDE8E8; color: #9B1C1C; border: 1px solid #FBD5D5;'; ?>">
            <i class="fas <?php echo $flashType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>" style="font-size: 16px;"></i>
            <span><?php echo htmlspecialchars($flashMsg); ?></span>
        </div>
    <?php endif; ?>

    <div class="description-card" style="padding: 35px; border-radius: 14px; background: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid rgba(79, 111, 82, 0.12);">
        
        <!-- Header Row -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px;">
            <div>
                <h2 class="title-green-left" style="color: var(--primary-dark); font-weight: 700; margin: 0; font-size: 22px;">My Bookings & Consultations</h2>
                <div class="accent-line-left" style="width: 50px; height: 3px; background: var(--secondary); margin-top: 8px;"></div>
            </div>
            <a href="<?php echo BASE_URL; ?>book.php" class="btn-book" style="padding: 10px 22px; font-size: 13px; border-radius: 30px; text-decoration: none;">
                <i class="fas fa-plus-circle"></i> Book New Appointment
            </a>
        </div>

        <?php if (empty($bookings)): ?>
            <div style="text-align: center; padding: 50px 20px; background: #FAF9F6; border-radius: 10px; border: 1px dashed #E2E8F0;">
                <div style="width: 60px; height: 60px; border-radius: 50%; background: #EEF6EF; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 26px; margin: 0 auto 16px;">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <h4 style="color: var(--primary-dark); margin-bottom: 6px; font-size: 18px; font-weight: 700;">No Appointments Booked Yet</h4>
                <p style="color: #718096; font-size: 13.5px; margin-bottom: 20px; max-width: 400px; margin-left: auto; margin-right: auto;">
                    You haven't scheduled any doctor consultations yet. Book a session with our chief physician.
                </p>
                <a href="<?php echo BASE_URL; ?>book.php" class="btn-book" style="font-size: 13px; padding: 10px 24px; border-radius: 30px; text-decoration: none;">
                    Book Consultation Now &rarr;
                </a>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
                    <thead>
                        <tr style="background: #F8FAFC; border-bottom: 2px solid #E2E8F0; text-align: left;">
                            <th style="padding: 14px 16px; color: #4A5568; font-weight: 600;">Ref / Date</th>
                            <th style="padding: 14px 16px; color: #4A5568; font-weight: 600;">Time Slot</th>
                            <th style="padding: 14px 16px; color: #4A5568; font-weight: 600;">Category & Symptoms</th>
                            <th style="padding: 14px 16px; color: #4A5568; font-weight: 600;">Attending Doctor</th>
                            <th style="padding: 14px 16px; color: #4A5568; font-weight: 600;">Status</th>
                            <th style="padding: 14px 16px; color: #4A5568; min-width: 200px; font-weight: 600;">Consultation & Prescription</th>
                            <th style="padding: 14px 16px; color: #4A5568; font-weight: 600; text-align: center; width: 130px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): 
                            $status = $b['status'] ?? 'Booked';
                            $badgeBg = '#FEF3C7';
                            $badgeColor = '#92400E';
                            if ($status === 'Confirmed') { $badgeBg = '#D1FAE5'; $badgeColor = '#065F46'; }
                            elseif ($status === 'Checked-In' || $status === 'Checked-in') { $badgeBg = '#E0F2FE'; $badgeColor = '#0369A1'; }
                            elseif ($status === 'In Consultation') { $badgeBg = '#EDE9FE'; $badgeColor = '#5B21B6'; }
                            elseif ($status === 'Completed') { $badgeBg = '#DEF7EC'; $badgeColor = '#03543F'; }
                            elseif (in_array($status, ['Cancelled', 'Rejected', 'No-Show'])) { $badgeBg = '#FDE8E8'; $badgeColor = '#9B1C1C'; }

                            $cancelEligibility = check_patient_cancellation_eligibility($b);
                        ?>
                            <tr style="border-bottom: 1px solid #E2E8F0; vertical-align: top;">
                                
                                <!-- Ref / Date -->
                                <td style="padding: 16px;">
                                    <div style="font-weight: 700; color: #355E3B; font-size: 13px;">
                                        #APP-<?php echo str_pad($b['id'], 4, '0', STR_PAD_LEFT); ?>
                                    </div>
                                    <div style="font-weight: 600; color: #2D3748; margin-top: 4px;">
                                        <?php echo date('d M Y', strtotime($b['appointment_date'] ?? $b['date'])); ?>
                                    </div>
                                    <small style="color: #718096; font-size: 11.5px;"><?php echo date('l', strtotime($b['appointment_date'] ?? $b['date'])); ?></small>
                                </td>

                                <!-- Time Slot -->
                                <td style="padding: 16px;">
                                    <div style="font-weight: 600; color: #2D3748;">
                                        <?php echo htmlspecialchars(!empty($b['time_slot']) ? $b['time_slot'] : '09:00 AM - 10:00 AM'); ?>
                                    </div>
                                </td>

                                <!-- Category & Symptoms -->
                                <td style="padding: 16px;">
                                    <div style="font-weight: 600; color: #355E3B;"><?php echo htmlspecialchars($b['category'] ?? 'General Health'); ?></div>
                                    <?php if (!empty($b['notes'])): ?>
                                        <div style="font-size: 12px; color: #718096; margin-top: 4px; max-width: 200px; line-height: 1.3;">
                                            <?php echo htmlspecialchars(mb_strimwidth($b['notes'], 0, 80, '...')); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Attending Doctor -->
                                <td style="padding: 16px;">
                                    <div style="font-weight: 600; color: #2D3748;">Dr. Shybu Raj</div>
                                    <small style="color: #718096; font-size: 11.5px;">Chief Physician</small>
                                </td>

                                <!-- Status -->
                                <td style="padding: 16px;">
                                    <span style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11.5px; font-weight: 700; background: <?php echo $badgeBg; ?>; color: <?php echo $badgeColor; ?>;">
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </td>

                                <!-- Consultation & Prescription -->
                                <td style="padding: 16px;">
                                    <?php if (!empty($b['cons_prescription']) || !empty($b['cons_notes'])): ?>
                                        <div style="background: #F0FDF4; border: 1px solid #BBF7D0; padding: 12px; border-radius: 8px; font-size: 12.5px; color: #166534;">
                                            <?php if (!empty($b['cons_prescription'])): ?>
                                                <div style="font-weight: 700; margin-bottom: 3px;"><i class="fas fa-prescription-bottle-alt"></i> Prescription:</div>
                                                <div style="background: #fff; padding: 8px 10px; border-radius: 4px; border: 1px solid #DCFCE7; color: #14532D; font-family: monospace; font-size: 12px; white-space: pre-wrap; margin-bottom: 6px;"><?php echo htmlspecialchars($b['cons_prescription']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($b['cons_notes'])): ?>
                                                <div style="font-weight: 700; margin-top: 6px; margin-bottom: 2px;"><i class="fas fa-notes-medical"></i> Doctor's Clinical Notes:</div>
                                                <div style="color: #27272A; font-size: 12px;"><?php echo nl2br(htmlspecialchars($b['cons_notes'])); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($b['cons_date'])): ?>
                                                <div style="margin-top: 8px; font-size: 11px; color: #15803D;">
                                                    <i class="far fa-clock"></i> Recorded: <?php echo date('d M Y, h:i A', strtotime($b['cons_date'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="display: flex; align-items: center; gap: 6px; color: #A0AEC0; font-size: 12.5px;">
                                            <i class="fas fa-hourglass-half"></i>
                                            <span><?php echo $status === 'Cancelled' ? 'Consultation cancelled' : 'Consultation in queue'; ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Action Column -->
                                <td style="padding: 16px; text-align: center; vertical-align: middle;">
                                    <?php if ($cancelEligibility['eligible']): ?>
                                        <button type="button" 
                                                class="btn-cancel-booking-outline"
                                                onclick="openCancelModal(<?php echo (int)$b['id']; ?>, '#APP-<?php echo str_pad($b['id'], 4, '0', STR_PAD_LEFT); ?>')">
                                            <i class="fas fa-times-circle"></i> Cancel Booking
                                        </button>
                                    <?php elseif ($status === 'Booked' || $status === 'Confirmed'): ?>
                                        <span style="font-size: 11px; color: #9B1C1C; background: #FDE8E8; padding: 4px 8px; border-radius: 6px; display: inline-block; line-height: 1.3;" title="<?php echo htmlspecialchars($cancelEligibility['reason']); ?>">
                                            <i class="fas fa-lock"></i> Cutoff passed (<30m)
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #CBD5E0; font-size: 14px;">—</span>
                                    <?php endif; ?>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</section>

<!-- Cancel Appointment Confirmation Modal -->
<div id="cancelModal" class="cancel-modal-backdrop" onclick="closeCancelModalOnBackdrop(event)">
    <div class="cancel-modal-card" onclick="event.stopPropagation()">
        
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
            <div style="width: 44px; height: 44px; border-radius: 50%; background: #FEE2E2; color: #DC2626; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 17px; font-weight: 700; color: #1E293B;">Confirm Cancellation</h3>
                <p style="margin: 2px 0 0 0; font-size: 12.5px; color: #64748B;">Clinic booking release confirmation</p>
            </div>
        </div>

        <p style="font-size: 14px; color: #334155; line-height: 1.5; margin-bottom: 20px;">
            Are you sure you want to cancel appointment <strong id="modalApptRefText" style="color: #DC2626;">#APP-XXXX</strong>?
            <br>
            <span style="font-size: 12.5px; color: #64748B; display: block; margin-top: 6px;">
                This will release your reserved consultation slot with the physician. This action cannot be undone.
            </span>
        </p>

        <form method="POST" action="<?php echo BASE_URL; ?>actions/cancel_appointment.php" id="cancelForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="appointment_id" id="modalAppointmentId" value="">

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" 
                        onclick="closeCancelModal()" 
                        style="padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; color: #475569; background: #F1F5F9; border: 1px solid #CBD5E1; cursor: pointer; transition: background 0.2s;">
                    Keep Appointment
                </button>
                <button type="submit" 
                        style="padding: 9px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; color: #FFFFFF; background: #DC2626; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25); transition: background 0.2s;">
                    <i class="fas fa-times-circle"></i> Yes, Cancel Appointment
                </button>
            </div>
        </form>

    </div>
</div>

<script>
function openCancelModal(apptId, apptRef) {
    document.getElementById('modalAppointmentId').value = apptId;
    document.getElementById('modalApptRefText').innerText = apptRef;
    document.getElementById('cancelModal').classList.add('active');
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.remove('active');
}

function closeCancelModalOnBackdrop(e) {
    if (e.target.id === 'cancelModal') {
        closeCancelModal();
    }
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCancelModal();
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
