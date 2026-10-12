<?php
declare(strict_types=1);

/**
 * Play-On Mombasa - Admin Booking Action Handler
 * Handles status updates, detail edits, and deletions with CSRF verification.
 */

require_once __DIR__ . '/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bookings.php');
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    setFlash('danger', 'Security token expired. Please try again.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'bookings.php'));
    exit;
}

$action = $_POST['action'] ?? '';
$pdo = getDbConnection();

// ----------------------------------------------------------------------------
// Action 1: Quick Status Update
// ----------------------------------------------------------------------------
if ($action === 'update_status') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $newStatus = trim((string)($_POST['status'] ?? ''));

    if ($bookingId <= 0 || !in_array($newStatus, ['pending', 'confirmed', 'cancelled'], true)) {
        setFlash('danger', 'Invalid booking ID or status value.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'bookings.php'));
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE `bookings` SET `status` = :st, `updated_at` = NOW() WHERE `id` = :id");
        $stmt->execute([':st' => $newStatus, ':id' => $bookingId]);

        setFlash('success', "Booking #{$bookingId} status updated to " . strtoupper($newStatus) . " successfully!");
    } catch (Throwable $e) {
        error_log('Status update error: ' . $e->getMessage());
        setFlash('danger', 'Failed to update status: ' . $e->getMessage());
    }

    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'bookings.php'));
    exit;
}

// ----------------------------------------------------------------------------
// Action 2: Edit Full Booking Details
// ----------------------------------------------------------------------------
if ($action === 'edit_booking') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    if ($bookingId <= 0) {
        setFlash('danger', 'Invalid booking ID.');
        header('Location: bookings.php');
        exit;
    }

    $fullName       = trim((string)($_POST['full_name'] ?? ''));
    $phone          = trim((string)($_POST['phone'] ?? ''));
    $email          = trim((string)($_POST['email'] ?? ''));
    $bookingDate    = trim((string)($_POST['booking_date'] ?? ''));
    $sessionTime    = trim((string)($_POST['session_time'] ?? ''));
    $childrenCount  = max(1, (int)($_POST['children_count'] ?? 1));
    $adultsCount    = max(0, (int)($_POST['adults_count'] ?? 0));
    $estimatedTotal = trim((string)($_POST['estimated_total'] ?? 'KES 1,000'));
    $status         = in_array($_POST['status'] ?? '', ['pending', 'confirmed', 'cancelled'], true) ? $_POST['status'] : 'pending';
    $notes          = trim((string)($_POST['notes'] ?? ''));
    $adminNotes     = trim((string)($_POST['admin_notes'] ?? ''));

    if ($fullName === '' || $phone === '' || $bookingDate === '' || $sessionTime === '') {
        setFlash('danger', 'Full Name, Phone, Date, and Session are required fields.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'bookings.php'));
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE `bookings` SET
                `full_name`       = :name,
                `phone`           = :phone,
                `email`           = :email,
                `booking_date`    = :b_date,
                `session_time`    = :s_time,
                `children_count`  = :children,
                `adults_count`    = :adults,
                `estimated_total` = :total,
                `status`          = :status,
                `notes`           = :notes,
                `admin_notes`     = :admin_notes,
                `updated_at`      = NOW()
            WHERE `id` = :id
        ");

        $stmt->execute([
            ':name'        => $fullName,
            ':phone'       => $phone,
            ':email'       => $email,
            ':b_date'      => $bookingDate,
            ':s_time'      => $sessionTime,
            ':children'    => $childrenCount,
            ':adults'      => $adultsCount,
            ':total'       => $estimatedTotal,
            ':status'      => $status,
            ':notes'       => $notes !== '' ? $notes : null,
            ':admin_notes' => $adminNotes !== '' ? $adminNotes : null,
            ':id'          => $bookingId
        ]);

        setFlash('success', "Booking #{$bookingId} updated successfully!");
    } catch (Throwable $e) {
        error_log('Booking edit error: ' . $e->getMessage());
        setFlash('danger', 'Failed to save edits: ' . $e->getMessage());
    }

    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'bookings.php'));
    exit;
}

// ----------------------------------------------------------------------------
// Action 3: Delete Booking
// ----------------------------------------------------------------------------
if ($action === 'delete_booking') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    if ($bookingId <= 0) {
        setFlash('danger', 'Invalid booking ID.');
        header('Location: bookings.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM `bookings` WHERE `id` = :id");
        $stmt->execute([':id' => $bookingId]);

        setFlash('warning', "Booking #{$bookingId} has been permanently deleted.");
    } catch (Throwable $e) {
        error_log('Booking delete error: ' . $e->getMessage());
        setFlash('danger', 'Failed to delete booking: ' . $e->getMessage());
    }

    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'bookings.php'));
    exit;
}

header('Location: bookings.php');
exit;
