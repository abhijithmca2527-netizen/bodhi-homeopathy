<?php
/**
 * Bodhi Homeopathy - Admin Report & Analytics Helper
 * Centralizes date range validation and metrics calculation
 * Shared by admin/reports.php and admin/report_pdf.php
 */

function get_report_date_range($fromInput = null, $toInput = null) {
    $today = date('Y-m-d');
    $defaultFrom = date('Y-m-01'); // First day of current month
    $defaultTo = date('Y-m-t');   // Last day of current month

    $from = trim($fromInput ?? '');
    $to = trim($toInput ?? '');

    $error = null;

    if (empty($from) && empty($to)) {
        $from = $defaultFrom;
        $to = $defaultTo;
    } elseif (empty($from) && !empty($to)) {
        $from = date('Y-m-01', strtotime($to));
    } elseif (!empty($from) && empty($to)) {
        $to = date('Y-m-t', strtotime($from));
    }

    // Validate date format YYYY-MM-DD
    $dFrom = DateTime::createFromFormat('Y-m-d', $from);
    $dTo = DateTime::createFromFormat('Y-m-d', $to);

    if (!$dFrom || $dFrom->format('Y-m-d') !== $from || !$dTo || $dTo->format('Y-m-d') !== $to) {
        $error = "Invalid date format specified. Please use YYYY-MM-DD.";
        $from = $defaultFrom;
        $to = $defaultTo;
    } elseif ($from > $to) {
        $error = "Invalid date range: 'From Date' (" . date('d M Y', strtotime($from)) . ") cannot be after 'To Date' (" . date('d M Y', strtotime($to)) . ").";
        $from = $defaultFrom;
        $to = $defaultTo;
    }

    return [
        'from' => $from,
        'to' => $to,
        'from_display' => date('d M Y', strtotime($from)),
        'to_display' => date('d M Y', strtotime($to)),
        'error' => $error,
        'is_default' => ($from === $defaultFrom && $to === $defaultTo)
    ];
}

function calculate_report_metrics(PDO $pdo, $from, $to) {
    // Start at 00:00:00 and end at 23:59:59
    $fromDateTime = $from . ' 00:00:00';
    $toDateTime = $to . ' 23:59:59';

    // 1. PHARMACY METRICS (Filtered by period)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `orders` WHERE `created_at` >= ? AND `created_at` <= ?");
    $stmt->execute([$fromDateTime, $toDateTime]);
    $totalOrders = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `orders` WHERE `payment_status` = 'Paid' AND `created_at` >= ? AND `created_at` <= ?");
    $stmt->execute([$fromDateTime, $toDateTime]);
    $paidOrders = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(`total_amount`), 0) FROM `orders` WHERE `payment_status` = 'Paid' AND `created_at` >= ? AND `created_at` <= ?");
    $stmt->execute([$fromDateTime, $toDateTime]);
    $paidRevenue = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `orders` WHERE `payment_status` = 'Pending' AND `created_at` >= ? AND `created_at` <= ?");
    $stmt->execute([$fromDateTime, $toDateTime]);
    $pendingOrders = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(`total_amount`), 0) FROM `orders` WHERE `payment_status` = 'Pending' AND `created_at` >= ? AND `created_at` <= ?");
    $stmt->execute([$fromDateTime, $toDateTime]);
    $pendingRevenue = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'Delivered' AND `created_at` >= ? AND `created_at` <= ?");
    $stmt->execute([$fromDateTime, $toDateTime]);
    $deliveredOrders = (int)$stmt->fetchColumn();

    // 2. CLINIC METRICS (Filtered by period)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `appointment` WHERE `appointment_date` >= ? AND `appointment_date` <= ?");
    $stmt->execute([$from, $to]);
    $totalAppointments = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `appointment` WHERE `appointment_date` >= ? AND `appointment_date` <= ? AND `status` = 'Cancelled'");
    $stmt->execute([$from, $to]);
    $cancelledAppointments = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `appointment` WHERE `appointment_date` >= ? AND `appointment_date` <= ? AND `status` IN ('Booked', 'Confirmed')");
    $stmt->execute([$from, $to]);
    $activeAppointments = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `consultations` WHERE `created_at` >= ? AND `created_at` <= ?");
    $stmt->execute([$fromDateTime, $toDateTime]);
    $completedConsultations = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `role` = 'Patient' AND `created_at` >= ? AND `created_at` <= ?");
    $stmt->execute([$fromDateTime, $toDateTime]);
    $newPatients = (int)$stmt->fetchColumn();

    // Total all-time patients count
    $stmt = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'Patient'");
    $totalPatientsAllTime = (int)$stmt->fetchColumn();

    // 3. CURRENT INVENTORY (Current live snapshot)
    $stmt = $pdo->query("SELECT COUNT(*) FROM `products` WHERE LOWER(`status`) = 'active'");
    $totalProducts = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COALESCE(SUM(`stock_quantity`), 0) FROM `products`");
    $currentUnitsInStock = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `products` WHERE `stock_quantity` <= 5 AND `stock_quantity` > 0");
    $lowStockProducts = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM `products` WHERE `stock_quantity` = 0");
    $outOfStockProducts = (int)$stmt->fetchColumn();

    // 4. PERIOD ORDERS LIST (For detailed summary)
    $stmt = $pdo->prepare("
        SELECT o.id, o.total_amount, o.payment_status, o.order_status, o.created_at, u.full_name, u.email
        FROM `orders` o
        JOIN `users` u ON u.id = o.user_id
        WHERE o.created_at >= ? AND o.created_at <= ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$fromDateTime, $toDateTime]);
    $periodOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. PERIOD APPOINTMENTS LIST (For detailed summary)
    $stmt = $pdo->prepare("
        SELECT a.id, a.patient_name, a.phone, a.appointment_date, a.time_slot, a.category, a.status
        FROM `appointment` a
        WHERE a.appointment_date >= ? AND a.appointment_date <= ?
        ORDER BY a.appointment_date ASC, a.id ASC
    ");
    $stmt->execute([$from, $to]);
    $periodAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'pharmacy' => [
            'total_orders' => $totalOrders,
            'paid_orders' => $paidOrders,
            'paid_revenue' => $paidRevenue,
            'pending_orders' => $pendingOrders,
            'pending_revenue' => $pendingRevenue,
            'delivered_orders' => $deliveredOrders,
            'orders_list' => $periodOrders
        ],
        'clinic' => [
            'total_appointments' => $totalAppointments,
            'active_appointments' => $activeAppointments,
            'cancelled_appointments' => $cancelledAppointments,
            'completed_consultations' => $completedConsultations,
            'new_patients' => $newPatients,
            'total_patients_all_time' => $totalPatientsAllTime,
            'appointments_list' => $periodAppointments
        ],
        'inventory' => [
            'total_products' => $totalProducts,
            'current_units_in_stock' => $currentUnitsInStock,
            'low_stock_products' => $lowStockProducts,
            'out_of_stock_products' => $outOfStockProducts
        ]
    ];
}
