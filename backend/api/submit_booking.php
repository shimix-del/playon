<?php
declare(strict_types=1);

/**
 * Play-On Mombasa - Booking Submission API Endpoint
 * Endpoint: POST /api/submit_booking.php
 * Handles CORS, input sanitization, spam honeypot, IP rate-limiting, PDO insert & email triggering.
 */

// Strict error reporting for debugging without leaking into output
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../mailer.php';

// ----------------------------------------------------------------------------
// 1. CORS Management
// ----------------------------------------------------------------------------
$allowedOriginsRaw = env('ALLOWED_ORIGINS', 'https://shimix-del.github.io,http://localhost:8000,http://127.0.0.1:8000');
$allowedOrigins = array_map('trim', explode(',', (string)$allowedOriginsRaw));

$httpOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (!empty($httpOrigin)) {
    // Check if origin matches allowed list or is a submodule / trailing slash variant
    $matched = false;
    foreach ($allowedOrigins as $allowed) {
        if (strcasecmp($httpOrigin, $allowed) === 0 || str_starts_with($httpOrigin, 'https://shimix-del.github.io')) {
            $matched = true;
            header("Access-Control-Allow-Origin: {$httpOrigin}");
            break;
        }
    }
    if (!$matched) {
        // Fallback for primary production GitHub Pages site
        header('Access-Control-Allow-Origin: https://shimix-del.github.io');
    }
} else {
    // Default wildcard for server-to-server or cPanel preview
    header('Access-Control-Allow-Origin: https://shimix-del.github.io');
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Accept');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=UTF-8');

// Respond to preflight OPTIONS request immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Ensure POST method only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. Only POST requests are supported.'
    ]);
    exit;
}

// ----------------------------------------------------------------------------
// 2. Parse Request Payload (JSON or Form Data)
// ----------------------------------------------------------------------------
$rawInput = file_get_contents('php://input');
$data = [];

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false || (str_starts_with(trim($rawInput), '{') && str_ends_with(trim($rawInput), '}'))) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}

// Fallback to standard POST body
if (empty($data)) {
    $data = $_POST;
}

// Helper to safely get trimmed string
function getField(array $arr, string $key, string $default = ''): string {
    return isset($arr[$key]) ? trim((string)$arr[$key]) : $default;
}

// ----------------------------------------------------------------------------
// 3. Spam Honeypot Protection
// ----------------------------------------------------------------------------
// Hidden honeypot field - bots will fill this out, real users will not
$honeypot = getField($data, 'hp_website');
if ($honeypot !== '') {
    // Fake success response to confuse automated bots
    http_response_code(200);
    echo json_encode([
        'success'     => true,
        'booking_ref' => 'PO-' . date('ymd') . '-BOT',
        'message'     => 'Your request has been received.'
    ]);
    exit;
}

// ----------------------------------------------------------------------------
// 4. Client IP & Rate Limiting (Max 5 requests per 10 mins)
// ----------------------------------------------------------------------------
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] 
    ?? $_SERVER['HTTP_X_FORWARDED_FOR'] 
    ?? $_SERVER['REMOTE_ADDR'] 
    ?? '0.0.0.0';

// If multiple comma-separated IPs in X-Forwarded-For, take the first
if (strpos($ip, ',') !== false) {
    $ip = trim(explode(',', $ip)[0]);
}

try {
    $pdo = getDbConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to connect to booking database. Please try again shortly or contact WhatsApp +254 780 611 074.'
    ]);
    exit;
}

$now = time();
$windowSeconds = 600; // 10 minutes
$maxRequests   = 6;

try {
    // Prune stale rate limits older than 1 hour
    $pdo->prepare("DELETE FROM `rate_limits` WHERE `request_time` < :stale")->execute([':stale' => $now - 3600]);

    // Count recent attempts
    $stmtRate = $pdo->prepare("SELECT COUNT(*) AS `cnt` FROM `rate_limits` WHERE `ip_address` = :ip AND `endpoint` = 'submit_booking' AND `request_time` >= :cutoff");
    $stmtRate->execute([':ip' => $ip, ':cutoff' => $now - $windowSeconds]);
    $rateCount = (int)$stmtRate->fetchColumn();

    if ($rateCount >= $maxRequests) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too many booking attempts in a short period. Please wait 10 minutes or reach out directly on WhatsApp (+254 780 611 074).'
        ]);
        exit;
    }

    // Record this attempt
    $pdo->prepare("INSERT INTO `rate_limits` (`ip_address`, `endpoint`, `request_time`) VALUES (:ip, 'submit_booking', :t)")->execute([':ip' => $ip, ':t' => $now]);
} catch (Throwable $e) {
    // Fail soft if rate_limits table error occurs
    error_log('Rate limiting check warning: ' . $e->getMessage());
}

// ----------------------------------------------------------------------------
// 5. Input Validation & Sanitization
// ----------------------------------------------------------------------------
$errors = [];

// Full Name
$fullName = getField($data, 'parentName', getField($data, 'full_name'));
if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 150) {
    $errors['parentName'] = 'Please enter your valid full name (minimum 2 characters).';
}

// Phone Number
$phone = getField($data, 'parentPhone', getField($data, 'phone'));
// Remove unwanted formatting for sanity check
$cleanPhone = preg_replace('/[^\d+]/', '', $phone);
if (strlen($cleanPhone) < 7 || strlen($cleanPhone) > 20) {
    $errors['parentPhone'] = 'Please provide a valid phone or WhatsApp number (e.g. +254 7XX XXX XXX).';
}

// Email Address
$email = getField($data, 'parentEmail', getField($data, 'email'));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    $errors['parentEmail'] = 'Please enter a valid email address so we can send your confirmation pass.';
}

// Booking Type
$bookingType = getField($data, 'bookingType', getField($data, 'booking_type', 'session'));
$validTypes = [
    'session'  => 'Standard Session Pass',
    'birthday' => 'Birthday Party Package',
    'school'   => 'School / Kindergarten Group Trip',
    'private'  => 'Exclusive Private Venue Hire'
];
if (!array_key_exists($bookingType, $validTypes)) {
    $bookingType = 'session';
}
$bookingTypeLabel = $validTypes[$bookingType];

// Preferred Visit Date
$bookingDate = getField($data, 'bookingDate', getField($data, 'booking_date'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookingDate)) {
    $errors['bookingDate'] = 'Please select a valid visit date in YYYY-MM-DD format.';
} else {
    $today = date('Y-m-d');
    if ($bookingDate < $today) {
        $errors['bookingDate'] = 'The visit date cannot be in the past.';
    }
}

// Session Time
$sessionTime = getField($data, 'bookingSession', getField($data, 'session_time'));
if ($sessionTime === '' || strlen($sessionTime) > 100) {
    $errors['bookingSession'] = 'Please select a preferred play session time.';
}

// Guests Count
$childrenCount = (int)getField($data, 'childCount', getField($data, 'children_count', '1'));
if ($childrenCount < 1 || $childrenCount > 150) {
    $errors['childCount'] = 'Number of children must be at least 1.';
}

$adultsCount = (int)getField($data, 'adultCount', getField($data, 'adults_count', '0'));
if ($adultsCount < 0 || $adultsCount > 50) {
    $adultsCount = 0;
}

// Estimated Total
$estimatedTotal = getField($data, 'estimatedTotal', getField($data, 'estimated_total', 'KES 1,000'));
if (strlen($estimatedTotal) > 50) {
    $estimatedTotal = substr($estimatedTotal, 0, 50);
}

// Notes / Requests
$notes = getField($data, 'specialRequests', getField($data, 'notes'));
if (mb_strlen($notes) > 1500) {
    $notes = mb_substr($notes, 0, 1500);
}

// Return validation errors if any
if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please correct the highlighted form errors and try again.',
        'errors'  => $errors
    ]);
    exit;
}

// ----------------------------------------------------------------------------
// 6. Generate Unique Booking Reference
// ----------------------------------------------------------------------------
function generateBookingRef(PDO $pdo): string {
    $datePart = date('ymd');
    $attempts = 0;
    do {
        $attempts++;
        // 4 uppercase random characters
        $randPart = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $ref = "PO-{$datePart}-{$randPart}";

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `bookings` WHERE `booking_ref` = :ref");
        $stmt->execute([':ref' => $ref]);
        $exists = (int)$stmt->fetchColumn() > 0;
    } while ($exists && $attempts < 10);

    return $ref;
}

$bookingRef = generateBookingRef($pdo);
$userAgent  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

// ----------------------------------------------------------------------------
// 7. Insert Record into Database
// ----------------------------------------------------------------------------
try {
    $stmtInsert = $pdo->prepare("
        INSERT INTO `bookings` (
            `booking_ref`,
            `full_name`,
            `phone`,
            `email`,
            `booking_type`,
            `booking_type_label`,
            `booking_date`,
            `session_time`,
            `children_count`,
            `adults_count`,
            `estimated_total`,
            `notes`,
            `status`,
            `ip_address`,
            `user_agent`,
            `created_at`
        ) VALUES (
            :ref,
            :name,
            :phone,
            :email,
            :b_type,
            :b_type_label,
            :b_date,
            :s_time,
            :children,
            :adults,
            :total,
            :notes,
            'pending',
            :ip,
            :ua,
            NOW()
        )
    ");

    $stmtInsert->execute([
        ':ref'          => $bookingRef,
        ':name'         => $fullName,
        ':phone'        => $phone,
        ':email'        => $email,
        ':b_type'       => $bookingType,
        ':b_type_label' => $bookingTypeLabel,
        ':b_date'       => $bookingDate,
        ':s_time'       => $sessionTime,
        ':children'     => $childrenCount,
        ':adults'       => $adultsCount,
        ':total'        => $estimatedTotal,
        ':notes'        => $notes !== '' ? $notes : null,
        ':ip'           => $ip,
        ':ua'           => $userAgent
    ]);

    $bookingId = (int)$pdo->lastInsertId();
} catch (Throwable $e) {
    error_log('Database Insert Error in submit_booking.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save booking reservation to database. Please try again or book directly via WhatsApp.'
    ]);
    exit;
}

// ----------------------------------------------------------------------------
// 8. Trigger Email Notifications
// ----------------------------------------------------------------------------
$bookingData = [
    'booking_ref'        => $bookingRef,
    'full_name'          => $fullName,
    'phone'              => $phone,
    'email'              => $email,
    'booking_type'       => $bookingType,
    'booking_type_label' => $bookingTypeLabel,
    'booking_date'       => $bookingDate,
    'session_time'       => $sessionTime,
    'children_count'     => $childrenCount,
    'adults_count'       => $adultsCount,
    'estimated_total'    => $estimatedTotal,
    'notes'              => $notes
];

$emailStatus = sendBookingNotifications($pdo, $bookingId, $bookingData);

// ----------------------------------------------------------------------------
// 9. Return Success JSON Response
// ----------------------------------------------------------------------------
http_response_code(201);
echo json_encode([
    'success'     => true,
    'booking_ref' => $bookingRef,
    'message'     => 'Asante! Your booking reservation has been successfully received.',
    'booking'     => [
        'id'             => $bookingId,
        'booking_ref'    => $bookingRef,
        'full_name'      => $fullName,
        'phone'          => $phone,
        'email'          => $email,
        'booking_type'   => $bookingTypeLabel,
        'booking_date'   => $bookingDate,
        'session_time'   => $sessionTime,
        'children_count' => $childrenCount,
        'adults_count'   => $adultsCount,
        'estimated_total'=> $estimatedTotal
    ],
    'notifications' => [
        'admin_sent'    => $emailStatus['admin_sent'],
        'customer_sent' => $emailStatus['customer_sent']
    ]
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
