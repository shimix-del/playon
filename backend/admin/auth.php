<?php
declare(strict_types=1);

/**
 * Play-On Mombasa - Admin Authentication & Session Guard
 */

require_once __DIR__ . '/../config/db.php';

// Configure session cookies with strict security flags
if (session_status() === PHP_SESSION_NONE) {
    $sessionLifetime = (int)env('SESSION_LIFETIME', 7200);
    ini_set('session.gc_maxlifetime', (string)$sessionLifetime);
    
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_name((string)env('SESSION_NAME', 'PLAYON_ADMIN_SESS'));
    session_start();
}

/**
 * Verify admin is logged in, else redirect to login.php
 */
function requireAdmin(): void {
    if (empty($_SESSION['admin_logged_in']) || empty($_SESSION['admin_id'])) {
        $loginUrl = 'login.php';
        header("Location: {$loginUrl}");
        exit;
    }

    // Check session timeout
    $maxIdle = (int)env('SESSION_LIFETIME', 7200);
    if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity'] > $maxIdle)) {
        session_unset();
        session_destroy();
        header('Location: login.php?msg=session_expired');
        exit;
    }

    $_SESSION['admin_last_activity'] = time();
}

/**
 * Return current logged in admin user info
 */
function getCurrentAdmin(): array {
    return [
        'id'       => $_SESSION['admin_id'] ?? 0,
        'username' => $_SESSION['admin_username'] ?? 'Admin',
        'email'    => $_SESSION['admin_email'] ?? '',
        'role'     => $_SESSION['admin_role'] ?? 'admin'
    ];
}

/**
 * Generate CSRF token for forms
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set flash alert message
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash alert message
 */
function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
