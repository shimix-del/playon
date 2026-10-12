<?php
declare(strict_types=1);

/**
 * Play-On Mombasa - Export Bookings to CSV
 * Streams filtered bookings as an Excel-compatible UTF-8 CSV file.
 */

require_once __DIR__ . '/auth.php';
requireAdmin();

$pdo = getDbConnection();

// Filters
$status   = trim((string)($_GET['status'] ?? ''));
$search   = trim((string)($_GET['search'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo   = trim((string)($_GET['date_to'] ?? ''));

$whereClauses = ['1=1'];
$params = [];

if ($status !== '' && in_array($status, ['pending', 'confirmed', 'cancelled'], true)) {
    $whereClauses[] = "`status` = :st";
    $params[':st'] = $status;
}

if ($search !== '') {
    $whereClauses[] = "(`booking_ref` LIKE :sq1 OR `full_name` LIKE :sq2 OR `email` LIKE :sq3 OR `phone` LIKE :sq4)";
    $params[':sq1'] = "%{$search}%";
    $params[':sq2'] = "%{$search}%";
    $params[':sq3'] = "%{$search}%";
    $params[':sq4'] = "%{$search}%";
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $whereClauses[] = "`booking_date` >= :dfrom";
    $params[':dfrom'] = $dateFrom;
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $whereClauses[] = "`booking_date` <= :dto";
    $params[':dto'] = $dateTo;
}

$whereSql = implode(' AND ', $whereClauses);

$stmt = $pdo->prepare("SELECT * FROM `bookings` WHERE {$whereSql} ORDER BY `booking_date` DESC, `id` DESC");
$stmt->execute($params);

$filename = 'playon_bookings_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Prepend UTF-8 BOM for Microsoft Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// CSV Header Row
fputcsv($output, [
    'ID',
    'Booking Reference',
    'Parent / Guardian Name',
    'Phone / WhatsApp',
    'Email Address',
    'Package / Booking Type',
    'Visit Date',
    'Session Time',
    'Children (1-15 yrs)',
    'Adults',
    'Estimated Total',
    'Status',
    'Special Requests / Notes',
    'Staff Internal Notes',
    'IP Address',
    'Submitted At'
]);

while ($row = $stmt->fetch()) {
    fputcsv($output, [
        $row['id'],
        $row['booking_ref'],
        $row['full_name'],
        $row['phone'],
        $row['email'],
        $row['booking_type_label'] ?? $row['booking_type'],
        $row['booking_date'],
        $row['session_time'],
        $row['children_count'],
        $row['adults_count'],
        $row['estimated_total'],
        strtoupper($row['status']),
        $row['notes'] ?? '',
        $row['admin_notes'] ?? '',
        $row['ip_address'] ?? '',
        $row['created_at']
    ]);
}

fclose($output);
exit;
