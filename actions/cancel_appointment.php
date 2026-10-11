<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: require logged-in Patient
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Patient') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "bookings.php");
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    $_SESSION['patient_booking_msg'] = "Invalid or expired security token. Please try again.";
    $_SESSION['patient_booking_msg_type'] = "error";
    header("Location: " . BASE_URL . "bookings.php");
    exit;
}

$appointmentId = (int)($_POST['appointment_id'] ?? 0);
$patientUserId = (int)$_SESSION['user_id'];

if ($appointmentId <= 0) {
    $_SESSION['patient_booking_msg'] = "Invalid appointment identifier.";
    $_SESSION['patient_booking_msg_type'] = "error";
    header("Location: " . BASE_URL . "bookings.php");
    exit;
}

try {
    // 1. Fetch appointment and check if consultation is recorded
    $stmt = $pdo->prepare("
        SELECT a.*, c.id AS cons_id, c.prescription AS cons_prescription, c.notes AS cons_notes
        FROM `appointment` a
        LEFT JOIN `consultations` c ON c.appointment_id = a.id
        WHERE a.id = ?
    ");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        $_SESSION['patient_booking_msg'] = "Appointment record not found.";
        $_SESSION['patient_booking_msg_type'] = "error";
        header("Location: " . BASE_URL . "bookings.php");
        exit;
    }

    // 2. Ownership Verification: MUST belong to logged-in patient
    if ((int)$appointment['user_id'] !== $patientUserId) {
        $_SESSION['patient_booking_msg'] = "Access denied: You are not authorized to cancel this appointment.";
        $_SESSION['patient_booking_msg_type'] = "error";
        header("Location: " . BASE_URL . "bookings.php");
        exit;
    }

    // 3. Status Eligibility Check: Only Booked or Confirmed
    $status = $appointment['status'] ?? 'Booked';
    if (!in_array($status, ['Booked', 'Confirmed'], true)) {
        $_SESSION['patient_booking_msg'] = "This appointment cannot be cancelled because its current status is '{$status}'.";
        $_SESSION['patient_booking_msg_type'] = "error";
        header("Location: " . BASE_URL . "bookings.php");
        exit;
    }

    // 4. Consultation Guard: Cannot cancel after consultation is recorded
    if (!empty($appointment['cons_id']) || !empty($appointment['cons_prescription']) || !empty($appointment['cons_notes'])) {
        $_SESSION['patient_booking_msg'] = "Cancellation unavailable: A clinical consultation or prescription has already been issued for this appointment.";
        $_SESSION['patient_booking_msg_type'] = "error";
        header("Location: " . BASE_URL . "bookings.php");
        exit;
    }

    // 5. 30-Minute Cancellation Time Rule
    $apptDate = $appointment['appointment_date'] ?? $appointment['date'] ?? '';
    $timeSlot = trim($appointment['time_slot'] ?? '');
    $now = time();
    $startTime = null;

    if (!empty($apptDate)) {
        if (!empty($timeSlot) && strtolower($timeSlot) !== 'regular hours') {
            // Extract start time, e.g. "09:00 AM - 10:00 AM" -> "09:00 AM"
            $parts = explode('-', $timeSlot);
            $startTimeStr = trim($parts[0]);
            $parsed = strtotime($apptDate . ' ' . $startTimeStr);
            if ($parsed !== false) {
                $startTime = $parsed;
            }
        }

        if ($startTime !== null) {
            // Cutoff: 30 minutes before start time
            $cutoff = $startTime - (30 * 60);
            if ($now > $cutoff) {
                $_SESSION['patient_booking_msg'] = "Cancellation unavailable within 30 minutes of the appointment scheduled start time.";
                $_SESSION['patient_booking_msg_type'] = "error";
                header("Location: " . BASE_URL . "bookings.php");
                exit;
            }
        } else {
            // Fallback for legacy slots without parseable time:
            // Allow cancellation only if appointment date is not in the past
            $dateMidnight = strtotime($apptDate . ' 23:59:59');
            if ($now > $dateMidnight) {
                $_SESSION['patient_booking_msg'] = "Cancellation unavailable for past appointment dates.";
                $_SESSION['patient_booking_msg_type'] = "error";
                header("Location: " . BASE_URL . "bookings.php");
                exit;
            }
        }
    }

    // 6. Update Status to 'Cancelled' (Do NOT delete row)
    $stmt = $pdo->prepare("UPDATE `appointment` SET `status` = 'Cancelled' WHERE `id` = ? AND `user_id` = ?");
    $stmt->execute([$appointmentId, $patientUserId]);

    $_SESSION['patient_booking_msg'] = "Your appointment #APP-" . str_pad($appointmentId, 4, '0', STR_PAD_LEFT) . " has been cancelled successfully.";
    $_SESSION['patient_booking_msg_type'] = "success";

} catch (Exception $e) {
    error_log("Database error while cancelling appointment: " . $e->getMessage());
    $_SESSION['patient_booking_msg'] = "An error occurred while cancelling your appointment. Please try again.";
    $_SESSION['patient_booking_msg_type'] = "error";
}

header("Location: " . BASE_URL . "bookings.php");
exit;
