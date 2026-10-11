<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    exit("Direct access not permitted.");
}

// Central Clinic Timezone Configuration (India Standard Time)
date_default_timezone_set('Asia/Kolkata');
define('CLINIC_TIMEZONE', 'Asia/Kolkata');

// 1. Session Setup
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// 2. Error Reporting & Security Hardening
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 3. Load Local Environment Configuration (.env)
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
        list($key, $val) = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val, " \t\n\r\0\x0B\"'");
        putenv("{$key}={$val}");
        $_ENV[$key] = $val;
        $_SERVER[$key] = $val;
    }
}

// 4. Database Configurations
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'bodhi');

// 5. Base Paths & URL
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost/bodhi/');
define('APP_URL', rtrim(getenv('APP_URL') ?: (getenv('BASE_URL') ?: 'http://localhost/bodhi'), '/'));

// 6. SMTP Mailer Settings (PHPMailer - Gmail SMTP)
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ? (int)getenv('SMTP_PORT') : 587);
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: (getenv('SMTP_USER') ?: 'no-reply@bodhihomeopathy.com'));
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Bodhi Homeopathy');
define('SMTP_TEST_MODE', getenv('SMTP_TEST_MODE') !== false ? (getenv('SMTP_TEST_MODE') === 'true' || getenv('SMTP_TEST_MODE') === '1') : empty(trim(SMTP_USER)));

// 7. Cashfree Payment Gateway Settings
define('CASHFREE_MODE', getenv('CASHFREE_MODE') ?: 'sandbox');
define('CASHFREE_ENVIRONMENT', CASHFREE_MODE);
define('CASHFREE_CLIENT_ID', getenv('CASHFREE_CLIENT_ID') ?: '');
define('CASHFREE_CLIENT_SECRET', getenv('CASHFREE_CLIENT_SECRET') ?: '');
define('CASHFREE_API_VERSION', getenv('CASHFREE_API_VERSION') ?: '2025-01-01');

// Backward-compatibility aliases
define('CASHFREE_APP_ID', CASHFREE_CLIENT_ID);
define('CASHFREE_SECRET_KEY', CASHFREE_CLIENT_SECRET);
define('CASHFREE_API_ENDPOINT', CASHFREE_ENVIRONMENT === 'production' ? 'https://api.cashfree.com/pg/' : 'https://sandbox.cashfree.com/pg/');
define('CASHFREE_TEST_MODE', CASHFREE_ENVIRONMENT === 'sandbox');

// 8. Clinic Time Slots (Single Doctor)
define('CLINIC_TIME_SLOTS', [
    '09:00 AM - 10:00 AM',
    '10:00 AM - 11:00 AM',
    '11:00 AM - 12:00 PM',
    '02:00 PM - 03:00 PM',
    '03:00 PM - 04:00 PM',
    '04:00 PM - 05:00 PM',
    '05:00 PM - 06:00 PM'
]);

// 9. Security Helpers
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// 10. Time Slot Helpers & Same-Day 30-Minute Lead Time Rule
function format_slot_label($slotStr) {
    $parts = explode('-', $slotStr);
    $startTimeStr = trim($parts[0] ?? '');
    $time = strtotime($startTimeStr);
    $hour = (int)date('H', $time);
    
    if ($hour < 12) {
        $period = '(Morning)';
    } elseif ($hour < 16) {
        $period = '(Afternoon)';
    } else {
        $period = '(Evening)';
    }
    
    return $slotStr . ' ' . $period;
}

function get_available_clinic_slots($dateStr) {
    date_default_timezone_set('Asia/Kolkata');
    $allSlots = CLINIC_TIME_SLOTS;
    $todayStr = date('Y-m-d');

    // Reject past dates
    if ($dateStr < $todayStr) {
        return [];
    }

    // Future dates show all configured slots
    if ($dateStr > $todayStr) {
        return $allSlots;
    }

    // For TODAY: slot start time must be > current time + 30 minutes
    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $minLeadTime = (clone $now)->modify('+30 minutes');

    $available = [];
    foreach ($allSlots as $slot) {
        $parts = explode('-', $slot);
        $startTimeStr = trim($parts[0] ?? '');
        $slotStartDateTime = DateTime::createFromFormat('Y-m-d g:i A', $dateStr . ' ' . $startTimeStr, new DateTimeZone('Asia/Kolkata'));
        
        if (!$slotStartDateTime) {
            $slotStartDateTime = new DateTime($dateStr . ' ' . $startTimeStr, new DateTimeZone('Asia/Kolkata'));
        }

        if ($slotStartDateTime && $slotStartDateTime > $minLeadTime) {
            $available[] = $slot;
        }
    }

    return $available;
}
?>
