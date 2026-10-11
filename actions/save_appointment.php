<?php
require_once __DIR__ . '/../includes/db.php';

// Method check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "book.php");
    exit;
}

// 1. Authentication Check: Only logged-in Patient users can book. Block guests immediately.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Patient') {
    http_response_code(403);
    echo "<script>alert('Please log in as a Patient to book an appointment.'); window.location.href = '" . BASE_URL . "login.php';</script>";
    exit;
}

// 2. CSRF Security Verification: Missing/wrong CSRF token must reject the request without database changes
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo "<script>alert('Invalid security token. Please try again.'); window.history.back();</script>";
    exit;
}

// 3. User ID strictly from session (never allow user_id = NULL)
$userId = (int)$_SESSION['user_id'];
if ($userId <= 0) {
    http_response_code(403);
    echo "<script>alert('Unauthorized: Invalid patient account.'); window.location.href = '" . BASE_URL . "login.php';</script>";
    exit;
}

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$date = trim($_POST['date'] ?? '');
$timeSlot = trim($_POST['time_slot'] ?? '');
$mode = 'Direct Clinic Visit';
$category = trim($_POST['category'] ?? 'General Health');
$notes = trim($_POST['notes'] ?? '');

// Fetch authoritative patient data from database
try {
    $stmt = $pdo->prepare("SELECT `full_name`, `phone` FROM `users` WHERE `id` = ?");
    $stmt->execute([$userId]);
    $uRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($uRow) {
        if (!empty($uRow['full_name'])) {
            $name = $uRow['full_name'];
        }
        if (!empty($uRow['phone'])) {
            $phone = $uRow['phone'];
        }
    }
} catch (Exception $e) {
    error_log("Failed to fetch patient user info for booking: " . $e->getMessage());
}

// Basic validation
if (empty($name) || empty($phone) || empty($date) || empty($timeSlot)) {
    echo "<script>alert('Please fill in all required fields (Name, Phone, Preferred Date, Preferred Time Slot).'); window.history.back();</script>";
    exit;
}

// Date validation: prevent booking past dates
date_default_timezone_set('Asia/Kolkata');
$today = date('Y-m-d');
if ($date < $today) {
    echo "<script>alert('Appointment date cannot be in the past. Please select a valid future date.'); window.history.back();</script>";
    exit;
}

// Slot validation: verify time slot is configured and currently available
if (!in_array($timeSlot, CLINIC_TIME_SLOTS, true)) {
    echo "<script>alert('Invalid time slot selected. Please choose a valid time slot.'); window.history.back();</script>";
    exit;
}

$availableSlots = get_available_clinic_slots($date);
if (!in_array($timeSlot, $availableSlots, true)) {
    echo "<script>alert('This appointment slot is no longer available. Please choose a later time.'); window.history.back();</script>";
    exit;
}

try {
    $pdo->beginTransaction();

    // 4. Enforce Single-Doctor Rule: Only ONE active appointment (Booked/Confirmed) per Date + Time Slot
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `appointment` WHERE `appointment_date` = ? AND `time_slot` = ? AND `status` IN ('Booked', 'Confirmed') FOR UPDATE");
    $stmt->execute([$date, $timeSlot]);
    $slotOccupiedCount = (int)$stmt->fetchColumn();

    if ($slotOccupiedCount > 0) {
        $pdo->rollBack();
        echo "<script>alert('This appointment slot has just been booked by another patient. Please choose a different time slot.'); window.history.back();</script>";
        exit;
    }

    // 5. Enforce One Active Appointment Per Patient Per Date Rule
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `appointment` WHERE `user_id` = ? AND `appointment_date` = ? AND `status` IN ('Booked', 'Confirmed') FOR UPDATE");
    $stmt->execute([$userId, $date]);
    $patientActiveToday = (int)$stmt->fetchColumn();

    if ($patientActiveToday > 0) {
        $pdo->rollBack();
        echo "<script>alert('You already have an appointment scheduled for this date.'); window.history.back();</script>";
        exit;
    }

    // 6. Enforce Daily Clinic Capacity Rule (Maximum 10 active bookings per day)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `appointment` WHERE `appointment_date` = ? AND `status` IN ('Booked', 'Confirmed') FOR UPDATE");
    $stmt->execute([$date]);
    $dailyActiveCount = (int)$stmt->fetchColumn();

    if ($dailyActiveCount >= 10) {
        $pdo->rollBack();
        echo "<script>alert('Daily appointment capacity (maximum 10 bookings) has been reached for this date. Please select another preferred date.'); window.history.back();</script>";
        exit;
    }

    // 7. Secure Insert via PDO Prepared Statement (user_id is strictly non-NULL integer from session)
    $stmt = $pdo->prepare("
        INSERT INTO `appointment` (`user_id`, `patient_name`, `phone`, `appointment_date`, `time_slot`, `mode`, `category`, `notes`, `status`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Booked')
    ");
    $stmt->execute([$userId, $name, $phone, $date, $timeSlot, $mode, $category, $notes]);
    $newApptId = (int)$pdo->lastInsertId();

    $pdo->commit();

    // 8. Clean redirect to professional appointment confirmation page
    header("Location: " . BASE_URL . "appointment_success.php?id=" . $newApptId);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error saving appointment: " . $e->getMessage());
    echo "<script>alert('An error occurred while booking your appointment. Please try again.'); window.history.back();</script>";
    exit;
}