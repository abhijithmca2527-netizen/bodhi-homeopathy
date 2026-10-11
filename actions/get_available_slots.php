<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

$dateStr = trim($_GET['date'] ?? $_POST['date'] ?? date('Y-m-d'));

if (empty($dateStr)) {
    $dateStr = date('Y-m-d');
}

date_default_timezone_set('Asia/Kolkata');
$todayStr = date('Y-m-d');

if ($dateStr < $todayStr) {
    echo json_encode([
        'success' => false,
        'date' => $dateStr,
        'today' => $todayStr,
        'message' => 'Appointment date cannot be in the past.',
        'slots' => []
    ]);
    exit;
}

$availableSlots = get_available_clinic_slots($dateStr);

// Single-Doctor rule: Filter out slots already booked or confirmed by any patient on this date
$occupiedSlots = [];
try {
    $stmt = $pdo->prepare("SELECT DISTINCT `time_slot` FROM `appointment` WHERE `appointment_date` = ? AND `status` IN ('Booked', 'Confirmed')");
    $stmt->execute([$dateStr]);
    $occupiedSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Fallback to time-slot lead-time availability if query fails
}

$formattedSlots = [];

foreach ($availableSlots as $slot) {
    if (!in_array($slot, $occupiedSlots, true)) {
        $formattedSlots[] = [
            'value' => $slot,
            'label' => format_slot_label($slot)
        ];
    }
}

echo json_encode([
    'success' => true,
    'date' => $dateStr,
    'today' => $todayStr,
    'count' => count($formattedSlots),
    'slots' => $formattedSlots
]);
exit;
