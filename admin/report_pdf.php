<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/report_helper.php';
require_once __DIR__ . '/../includes/fpdf.php';

// Security check: strictly enforce Admin role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$dateRange = get_report_date_range($_GET['from'] ?? null, $_GET['to'] ?? null);
$from = $dateRange['from'];
$to = $dateRange['to'];

$metrics = calculate_report_metrics($pdo, $from, $to);
$pharmacy = $metrics['pharmacy'];
$clinic = $metrics['clinic'];
$inventory = $metrics['inventory'];

// Custom PDF class with Bodhi branding
class BodhiReportPDF extends FPDF {
    public $reportPeriodText = '';
    public $generatedBy = 'Administrator';

    function Header() {
        // Top Brand Header Banner
        $this->SetFillColor(53, 94, 59); // Forest Green #355E3B
        $this->Rect(0, 0, 210, 26, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetXY(14, 6);
        $this->Cell(120, 8, 'BODHI HOMEOPATHY', 0, 0, 'L');

        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(231, 154, 50); // Gold Accent #E79A32
        $this->Cell(62, 8, 'CLINIC & PHARMACY REPORT', 0, 1, 'R');

        $this->SetFont('Helvetica', '', 8.5);
        $this->SetTextColor(220, 240, 225);
        $this->SetX(14);
        $this->Cell(182, 5, 'Comprehensive Executive Management & Clinical Analytics', 0, 1, 'L');

        $this->Ln(8);

        // Metadata Subheader Box
        $this->SetFillColor(247, 248, 244); // Cream/warm #F7F8F4
        $this->SetDrawColor(226, 232, 240); // Border #E2E8F0
        $this->Rect(14, 30, 182, 11, 'DF');

        $this->SetTextColor(53, 94, 59);
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetXY(18, 33);
        $this->Cell(28, 5, 'Report Period:', 0, 0, 'L');

        $this->SetTextColor(45, 55, 72);
        $this->SetFont('Helvetica', '', 8.5);
        $this->Cell(55, 5, $this->reportPeriodText, 0, 0, 'L');

        $this->SetTextColor(53, 94, 59);
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->Cell(28, 5, 'Generated On:', 0, 0, 'L');

        $this->SetTextColor(45, 55, 72);
        $this->SetFont('Helvetica', '', 8.5);
        $this->Cell(71, 5, date('d M Y, h:i A'), 0, 1, 'L');

        $this->Ln(6);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetDrawColor(226, 232, 240);
        $this->Line(14, $this->GetY(), 196, $this->GetY());
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(113, 128, 150);
        $this->Cell(95, 10, 'Bodhi Homeopathy Clinic Management System - Official Report', 0, 0, 'L');
        $this->Cell(87, 10, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R');
    }

    function SectionHeading($title, $iconText = '') {
        $this->SetFillColor(238, 246, 239); // Soft sage #EEF6EF
        $this->SetDrawColor(53, 94, 59);
        $this->SetTextColor(53, 94, 59);
        $this->SetFont('Helvetica', 'B', 10.5);
        $this->Rect($this->GetX(), $this->GetY(), 182, 8, 'F');
        $this->Line($this->GetX(), $this->GetY(), $this->GetX(), $this->GetY() + 8);
        $this->Cell(182, 8, '  ' . strtoupper($title), 0, 1, 'L');
        $this->Ln(3);
    }

    function StatCard($x, $y, $w, $h, $label, $value, $accentColor = [53, 94, 59]) {
        $this->SetXY($x, $y);
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(226, 232, 240);
        $this->Rect($x, $y, $w, $h, 'DF');

        // Top accent line
        $this->SetFillColor($accentColor[0], $accentColor[1], $accentColor[2]);
        $this->Rect($x, $y, $w, 1.8, 'F');

        // Label
        $this->SetXY($x + 3, $y + 3.5);
        $this->SetFont('Helvetica', 'B', 7.5);
        $this->SetTextColor(113, 128, 150);
        $this->Cell($w - 6, 4, strtoupper($label), 0, 1, 'L');

        // Value
        $this->SetXY($x + 3, $y + 8);
        $this->SetFont('Helvetica', 'B', 11.5);
        $this->SetTextColor($accentColor[0], $accentColor[1], $accentColor[2]);
        $this->Cell($w - 6, 6, $value, 0, 1, 'L');
    }
}

$pdf = new BodhiReportPDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->reportPeriodText = $dateRange['from_display'] . ' to ' . $dateRange['to_display'];
$pdf->SetTitle('Bodhi Homeopathy Clinic Report (' . $dateRange['from'] . ' to ' . $dateRange['to'] . ')');
$pdf->SetAuthor('Bodhi Homeopathy Clinic');
$pdf->SetMargins(14, 14, 14);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

// -------------------------------------------------------------
// SECTION 1: PHARMACY PERFORMANCE
// -------------------------------------------------------------
$pdf->SectionHeading('1. Pharmacy & Financial Summary (Selected Period)');

$startY = $pdf->GetY();
$cardW = 43;
$cardH = 17;
$gap = 3.3;

$pdf->StatCard(14, $startY, $cardW, $cardH, 'Total Orders', (string)$pharmacy['total_orders'], [30, 66, 159]);
$pdf->StatCard(14 + ($cardW + $gap), $startY, $cardW, $cardH, 'Paid Revenue', 'Rs. ' . number_format($pharmacy['paid_revenue'], 2), [3, 84, 63]);
$pdf->StatCard(14 + ($cardW + $gap) * 2, $startY, $cardW, $cardH, 'Pending Orders', (string)$pharmacy['pending_orders'], [146, 64, 14]);
$pdf->StatCard(14 + ($cardW + $gap) * 3, $startY, $cardW, $cardH, 'Delivered Orders', (string)$pharmacy['delivered_orders'], [107, 33, 168]);

$pdf->SetY($startY + $cardH + 6);

// -------------------------------------------------------------
// SECTION 2: CLINICAL OPERATIONS
// -------------------------------------------------------------
$pdf->SectionHeading('2. Clinical Operations & Appointments (Selected Period)');

$startY = $pdf->GetY();
$cardW3 = 58;
$gap3 = 4;

$pdf->StatCard(14, $startY, $cardW3, $cardH, 'Total Appointments', (string)$clinic['total_appointments'], [53, 94, 59]);
$pdf->StatCard(14 + ($cardW3 + $gap3), $startY, $cardW3, $cardH, 'Completed Consultations', (string)$clinic['completed_consultations'], [3, 84, 63]);
$pdf->StatCard(14 + ($cardW3 + $gap3) * 2, $startY, $cardW3, $cardH, 'New Registered Patients', (string)$clinic['new_patients'], [157, 23, 77]);

$pdf->SetY($startY + $cardH + 6);

// -------------------------------------------------------------
// SECTION 3: CURRENT INVENTORY SNAPSHOT
// -------------------------------------------------------------
$pdf->SectionHeading('3. Pharmacy Inventory Status (Current Live Snapshot)');

$startY = $pdf->GetY();
$pdf->StatCard(14, $startY, $cardW3, $cardH, 'Active Products', (string)$inventory['total_products'] . ' Items', [53, 94, 59]);
$pdf->StatCard(14 + ($cardW3 + $gap3), $startY, $cardW3, $cardH, 'Units in Stock', (string)$inventory['current_units_in_stock'] . ' Units', [180, 83, 9]);
$pdf->StatCard(14 + ($cardW3 + $gap3) * 2, $startY, $cardW3, $cardH, 'Low Stock Alerts', (string)$inventory['low_stock_products'] . ' Items', [155, 44, 44]);

$pdf->SetY($startY + $cardH + 8);

// -------------------------------------------------------------
// SECTION 4: RECENT ORDERS IN PERIOD (IF ANY)
// -------------------------------------------------------------
$pdf->SetFont('Helvetica', 'B', 9.5);
$pdf->SetTextColor(53, 94, 59);
$pdf->Cell(182, 6, 'Period Orders Breakdown:', 0, 1, 'L');
$pdf->Ln(1);

if (empty($pharmacy['orders_list'])) {
    $pdf->SetFont('Helvetica', 'I', 8.5);
    $pdf->SetTextColor(113, 128, 150);
    $pdf->Cell(182, 6, 'No pharmacy orders recorded during this selected date range.', 0, 1, 'L');
    $pdf->Ln(4);
} else {
    // Table Header
    $pdf->SetFillColor(240, 244, 248);
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetTextColor(74, 85, 104);
    $pdf->Cell(25, 6, 'Order ID', 1, 0, 'L', true);
    $pdf->Cell(45, 6, 'Customer', 1, 0, 'L', true);
    $pdf->Cell(35, 6, 'Date', 1, 0, 'L', true);
    $pdf->Cell(25, 6, 'Amount', 1, 0, 'R', true);
    $pdf->Cell(26, 6, 'Payment', 1, 0, 'C', true);
    $pdf->Cell(26, 6, 'Fulfillment', 1, 1, 'C', true);

    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(45, 55, 72);
    foreach ($pharmacy['orders_list'] as $ord) {
        $pdf->Cell(25, 5.5, '#ORD-' . str_pad($ord['id'], 4, '0', STR_PAD_LEFT), 1, 0, 'L');
        $pdf->Cell(45, 5.5, substr($ord['full_name'], 0, 22), 1, 0, 'L');
        $pdf->Cell(35, 5.5, date('d M Y, h:i A', strtotime($ord['created_at'])), 1, 0, 'L');
        $pdf->Cell(25, 5.5, 'Rs. ' . number_format($ord['total_amount'], 2), 1, 0, 'R');
        $pdf->Cell(26, 5.5, $ord['payment_status'], 1, 0, 'C');
        $pdf->Cell(26, 5.5, $ord['order_status'], 1, 1, 'C');
    }
    $pdf->Ln(4);
}

// -------------------------------------------------------------
// SECTION 5: CLINIC APPOINTMENTS IN PERIOD (IF ANY)
// -------------------------------------------------------------
$pdf->SetFont('Helvetica', 'B', 9.5);
$pdf->SetTextColor(53, 94, 59);
$pdf->Cell(182, 6, 'Period Appointments Breakdown:', 0, 1, 'L');
$pdf->Ln(1);

if (empty($clinic['appointments_list'])) {
    $pdf->SetFont('Helvetica', 'I', 8.5);
    $pdf->SetTextColor(113, 128, 150);
    $pdf->Cell(182, 6, 'No clinic appointments scheduled during this selected date range.', 0, 1, 'L');
} else {
    // Table Header
    $pdf->SetFillColor(240, 244, 248);
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetTextColor(74, 85, 104);
    $pdf->Cell(25, 6, 'Appt ID', 1, 0, 'L', true);
    $pdf->Cell(50, 6, 'Patient Name', 1, 0, 'L', true);
    $pdf->Cell(30, 6, 'Date', 1, 0, 'L', true);
    $pdf->Cell(35, 6, 'Time Slot', 1, 0, 'L', true);
    $pdf->Cell(42, 6, 'Status', 1, 1, 'C', true);

    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(45, 55, 72);
    foreach ($clinic['appointments_list'] as $apt) {
        $pdf->Cell(25, 5.5, '#APP-' . str_pad($apt['id'], 4, '0', STR_PAD_LEFT), 1, 0, 'L');
        $pdf->Cell(50, 5.5, substr($apt['patient_name'], 0, 25), 1, 0, 'L');
        $pdf->Cell(30, 5.5, date('d M Y', strtotime($apt['appointment_date'])), 1, 0, 'L');
        $pdf->Cell(35, 5.5, substr($apt['time_slot'] ?? '10:00 AM', 0, 18), 1, 0, 'L');
        $pdf->Cell(42, 5.5, $apt['status'] ?? 'Booked', 1, 1, 'C');
    }
}

$filename = 'bodhi_report_' . $from . '_to_' . $to . '.pdf';
$pdf->Output('I', $filename);
exit;
