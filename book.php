<?php
require_once __DIR__ . '/includes/config.php';

// Authentication Check: Guests must NOT access book.php. Only logged-in Patients allowed.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Patient') {
    $_SESSION['redirect_after_login'] = 'book.php';
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Book Appointment | Bodhi Homeopathy";
include 'includes/header.php';

// Patient is authenticated - pre-fill name & phone from session and database
$logged_name = $_SESSION['full_name'] ?? '';
$logged_phone = '';
$is_logged_in = true;
try {
    $stmt = $pdo->prepare("SELECT `full_name`, `phone` FROM `users` WHERE `id` = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $uRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($uRow) {
        $logged_name = $uRow['full_name'] ?: $logged_name;
        $logged_phone = $uRow['phone'] ?: '';
    }
} catch (Exception $e) {
    $logged_phone = '';
}

date_default_timezone_set('Asia/Kolkata');
$selected_date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
if ($selected_date < date('Y-m-d')) {
    $selected_date = date('Y-m-d');
}
$initial_available_slots = get_available_clinic_slots($selected_date);
try {
    $occStmt = $pdo->prepare("SELECT DISTINCT `time_slot` FROM `appointment` WHERE `appointment_date` = ? AND `status` IN ('Booked', 'Confirmed')");
    $occStmt->execute([$selected_date]);
    $occSlots = $occStmt->fetchAll(PDO::FETCH_COLUMN);
    $initial_available_slots = array_values(array_diff($initial_available_slots, $occSlots));
} catch (Exception $e) {
    // Keep initial available slots
}
?>

<!-- Hero Section -->
<section class="pro-contact-hero">
    <div class="hero-overlay-dark">
        <div class="container">
            <h1>Book Appointment</h1>
            <p>Schedule your personalized homeopathic consultation with Dr. Shybu Raj</p>
        </div>
    </div>
</section>

<!-- Main Booking Form Section -->
<section class="booking-main-section" style="background: #FAF9F6; padding: 45px 0 75px;">
    <div class="container" style="max-width: 860px; margin: 0 auto; padding: 0 15px;">
        
        <div class="booking-actual-form" style="background: #ffffff; padding: 40px; border-radius: 14px; border: 1px solid rgba(79, 111, 82, 0.1); box-shadow: 0 10px 35px rgba(53, 94, 59, 0.06);">
            
            <div style="text-align: center; margin-bottom: 25px;">
                <h2 style="color: var(--primary-dark); font-size: 26px; font-weight: 700; margin-bottom: 6px;">Patient Consultation Form</h2>
                <div style="width: 50px; height: 3px; background: var(--secondary); margin: 0 auto 12px;"></div>
                <p style="color: #666; font-size: 13.5px; margin: 0;">
                    Fill in your details to secure your clinical appointment. Maximum 10 active appointments per day.
                </p>
            </div>

            <form action="save_appointment.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-pro-grid">
                    <div class="field-pro-group">
                        <label for="patient_name" style="font-size: 13px; font-weight: 600; color: #355E3B; margin-bottom: 6px; display: block;">Patient Full Name *</label>
                        <input type="text" 
                               id="patient_name" 
                               name="name" 
                               required 
                               value="<?php echo htmlspecialchars($logged_name); ?>" 
                               placeholder="Enter full name" 
                               <?php echo $is_logged_in && !empty($logged_name) ? 'readonly style="width: 100%; padding: 11px 15px; border: 1px solid #dcdcdc; border-radius: 8px; font-family: inherit; font-size: 13.5px; background: #F7F8F4; cursor: not-allowed;"' : 'style="width: 100%; padding: 11px 15px; border: 1px solid #dcdcdc; border-radius: 8px; font-family: inherit; font-size: 13.5px;"'; ?>>
                    </div>

                    <div class="field-pro-group">
                        <label for="patient_phone" style="font-size: 13px; font-weight: 600; color: #355E3B; margin-bottom: 6px; display: block;">Contact Phone Number *</label>
                        <input type="tel" 
                               id="patient_phone" 
                               name="phone" 
                               required 
                               value="<?php echo htmlspecialchars($logged_phone); ?>" 
                               placeholder="10-digit mobile number" 
                               <?php echo $is_logged_in && !empty($logged_phone) ? 'readonly style="width: 100%; padding: 11px 15px; border: 1px solid #dcdcdc; border-radius: 8px; font-family: inherit; font-size: 13.5px; background: #F7F8F4; cursor: not-allowed;"' : 'style="width: 100%; padding: 11px 15px; border: 1px solid #dcdcdc; border-radius: 8px; font-family: inherit; font-size: 13.5px;"'; ?>>
                    </div>
                </div>

                <div class="form-pro-grid">
                    <div class="field-pro-group">
                        <label for="appointment_date" style="font-size: 13px; font-weight: 600; color: #355E3B; margin-bottom: 6px; display: block;">Preferred Date *</label>
                        <input type="date" id="appointment_date" name="date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($selected_date); ?>" required style="width: 100%; padding: 11px 15px; border: 1px solid #dcdcdc; border-radius: 8px; font-family: inherit; font-size: 13.5px;">
                    </div>

                    <div class="field-pro-group">
                        <label for="time_slot" style="font-size: 13px; font-weight: 600; color: #355E3B; margin-bottom: 6px; display: block;">Preferred Time Slot *</label>
                        <select id="time_slot" name="time_slot" required <?php echo empty($initial_available_slots) ? 'disabled' : ''; ?> style="width: 100%; padding: 11px 15px; border: 1px solid #dcdcdc; border-radius: 8px; font-family: inherit; font-size: 13.5px; background: #fff;">
                            <option value="">Select an available time slot</option>
                            <?php foreach ($initial_available_slots as $slot): ?>
                                <option value="<?php echo htmlspecialchars($slot); ?>"><?php echo htmlspecialchars(format_slot_label($slot)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="no_slots_alert" style="display: <?php echo empty($initial_available_slots) ? 'block' : 'none'; ?>; margin-bottom: 20px; background: #FDE8E8; color: #9B1C1C; padding: 12px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; border: 1px solid #F8B4B4;">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
                    <span id="no_slots_text">No appointment slots are available for today. Please select another date.</span>
                </div>

                <div class="field-pro-group" style="margin-bottom: 20px;">
                    <label for="health_category" style="font-size: 13px; font-weight: 600; color: #355E3B; margin-bottom: 6px; display: block;">Treatment Category *</label>
                    <select id="health_category" name="category" style="width: 100%; padding: 11px 15px; border: 1px solid #dcdcdc; border-radius: 8px; font-family: inherit; font-size: 13.5px; background: #fff;">
                        <option value="General Health">General Health</option>
                        <option value="Thyroid">Thyroid & Hormonal Balance</option>
                        <option value="Skin Care">Skin Care & Allergies</option>
                        <option value="Arthritis">Arthritis & Joint Disorders</option>
                        <option value="Pediatric">Pediatric Care</option>
                    </select>
                </div>

                <div class="field-pro-group">
                    <label for="appointment_notes" style="font-size: 13px; font-weight: 600; color: #355E3B; margin-bottom: 6px; display: block;">Medical Notes & Symptoms (Optional)</label>
                    <textarea id="appointment_notes" name="notes" rows="4" placeholder="Briefly describe your symptoms or past treatment history..." style="width: 100%; padding: 11px 15px; border: 1px solid #dcdcdc; border-radius: 8px; font-family: inherit; font-size: 13.5px; resize: vertical;"></textarea>
                </div>

                <button type="submit" id="submit_booking_btn" name="submit" class="booking-confirm-btn" <?php echo empty($initial_available_slots) ? 'disabled style="width: 100%; background: #ccc; color: #666; border: none; padding: 14px; border-radius: 50px; font-size: 14.5px; font-weight: 600; cursor: not-allowed; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 10px;"' : 'style="width: 100%; background: var(--secondary); color: #fff; border: none; padding: 14px; border-radius: 50px; font-size: 14.5px; font-weight: 600; cursor: pointer; transition: all 0.25s ease; box-shadow: 0 6px 20px rgba(231,154,50,.35); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 10px;"'; ?>>
                    CONFIRM & BOOK APPOINTMENT
                </button>
            </form>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var dateInput = document.getElementById('appointment_date');
    var slotSelect = document.getElementById('time_slot');
    var alertBox = document.getElementById('no_slots_alert');
    var alertText = document.getElementById('no_slots_text');
    var submitBtn = document.getElementById('submit_booking_btn');

    function updateSlots(dateVal) {
        if (!dateVal) return;

        slotSelect.disabled = true;
        slotSelect.innerHTML = '<option value="">Loading available slots...</option>';

        fetch('actions/get_available_slots.php?date=' + encodeURIComponent(dateVal))
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                slotSelect.innerHTML = '<option value="">Select an available time slot</option>';
                
                if (data.success && data.slots && data.slots.length > 0) {
                    data.slots.forEach(function(slot) {
                        var opt = document.createElement('option');
                        opt.value = slot.value;
                        opt.textContent = slot.label;
                        slotSelect.appendChild(opt);
                    });

                    slotSelect.disabled = false;
                    alertBox.style.display = 'none';
                    submitBtn.disabled = false;
                    submitBtn.style.background = 'var(--secondary)';
                    submitBtn.style.color = '#fff';
                    submitBtn.style.cursor = 'pointer';
                    submitBtn.style.boxShadow = '0 6px 20px rgba(231,154,50,.35)';
                } else {
                    var isToday = (dateVal === data.today);
                    if (isToday) {
                        alertText.textContent = 'No appointment slots are available for today. Please select another date.';
                    } else {
                        alertText.textContent = 'No appointment slots are available for the selected date. Please choose another date.';
                    }
                    alertBox.style.display = 'block';
                    slotSelect.disabled = true;
                    submitBtn.disabled = true;
                    submitBtn.style.background = '#ccc';
                    submitBtn.style.color = '#666';
                    submitBtn.style.cursor = 'not-allowed';
                    submitBtn.style.boxShadow = 'none';
                }
            })
            .catch(function(err) {
                console.error('Failed to fetch available slots:', err);
                slotSelect.disabled = false;
            });
    }

    if (dateInput) {
        dateInput.addEventListener('change', function() {
            updateSlots(this.value);
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>