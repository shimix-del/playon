<?php
declare(strict_types=1);

/**
 * Play-On Mombasa - Admin Logout Handler
 */

require_once __DIR__ . '/auth.php';

// Unset all session variables
$_SESSION = [];

// Delete session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy session
session_destroy();

header('Location: login.php?msg=logged_out');
exit;
