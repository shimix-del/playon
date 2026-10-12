<?php
declare(strict_types=1);

/**
 * Play-On Mombasa - Admin Login Page
 * Features CSRF protection, Bcrypt authentication & Brute-Force Account Lockout
 */

require_once __DIR__ . '/auth.php';

// Redirect to dashboard if already authenticated
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$info = '';

// Check query messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'logged_out') {
        $info = 'You have been securely logged out. Karibu tena!';
    } elseif ($_GET['msg'] === 'session_expired') {
        $error = 'Your session has timed out due to inactivity. Please log in again.';
    }
}

// Process POST Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Invalid security session. Please refresh and try again.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Please enter both your admin username and password.';
        } else {
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("SELECT * FROM `admins` WHERE `username` = :u1 OR `email` = :u2 LIMIT 1");
                $stmt->execute([':u1' => $username, ':u2' => $username]);
                $admin = $stmt->fetch();

                if ($admin) {
                    $now = new DateTime();
                    $lockedUntil = $admin['locked_until'] ? new DateTime($admin['locked_until']) : null;

                    // Check brute-force lockout
                    if ($lockedUntil && $lockedUntil > $now) {
                        $diff = $lockedUntil->diff($now);
                        $mins = $diff->i + 1;
                        $error = "Account is temporarily locked due to repeated failed logins. Please try again in {$mins} minute(s).";
                    } else {
                        // Check password
                        if (password_verify($password, $admin['password_hash'])) {
                            // Successful Login: Reset failed attempts
                            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                            $updateStmt = $pdo->prepare("
                                UPDATE `admins` 
                                SET `failed_login_attempts` = 0, 
                                    `locked_until` = NULL, 
                                    `last_login_at` = NOW(), 
                                    `last_login_ip` = :ip 
                                WHERE `id` = :id
                            ");
                            $updateStmt->execute([':ip' => $ip, ':id' => $admin['id']]);

                            // Regenerate session ID to prevent fixation
                            session_regenerate_id(true);

                            $_SESSION['admin_logged_in'] = true;
                            $_SESSION['admin_id']        = (int)$admin['id'];
                            $_SESSION['admin_username']  = $admin['username'];
                            $_SESSION['admin_email']     = $admin['email'];
                            $_SESSION['admin_role']      = $admin['role'];
                            $_SESSION['admin_last_activity'] = time();

                            header('Location: index.php');
                            exit;
                        } else {
                            // Failed attempt: Increment counter
                            $attempts = (int)$admin['failed_login_attempts'] + 1;
                            $maxAttempts = (int)env('MAX_LOGIN_ATTEMPTS', 5);
                            $lockoutMins = (int)env('LOCKOUT_MINUTES', 15);

                            if ($attempts >= $maxAttempts) {
                                $lockStmt = $pdo->prepare("
                                    UPDATE `admins` 
                                    SET `failed_login_attempts` = :att, 
                                        `locked_until` = DATE_ADD(NOW(), INTERVAL :lock MINUTE) 
                                    WHERE `id` = :id
                                ");
                                $lockStmt->execute([':att' => $attempts, ':lock' => $lockoutMins, ':id' => $admin['id']]);
                                $error = "Maximum failed attempts reached. Account locked for {$lockoutMins} minutes.";
                            } else {
                                $pdo->prepare("UPDATE `admins` SET `failed_login_attempts` = :att WHERE `id` = :id")
                                    ->execute([':att' => $attempts, ':id' => $admin['id']]);
                                $remaining = $maxAttempts - $attempts;
                                $error = "Invalid username or password. ({$remaining} attempt(s) remaining before lockout).";
                            }
                        }
                    }
                } else {
                    // Constant-time sleep to mitigate timing attacks
                    password_verify($password, '$2y$10$abcdefghijklmnopqrstuvwxyzaBCDEFGHIJKLMNOPQRSTUVWXYZ12');
                    $error = 'Invalid username or password.';
                }
            } catch (Throwable $e) {
                error_log('Admin login error: ' . $e->getMessage());
                $error = 'A system error occurred. Please verify your database configuration.';
            }
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | Play-On Mombasa Booking System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>
  <div class="login-wrapper">
    <div class="login-card">
      <div style="font-size:2.8rem; margin-bottom:0.5rem;">🎈</div>
      <h1 class="login-title">Play-On Mombasa</h1>
      <p class="login-subtitle">Booking Management Portal (Mombasa Mall, 2nd Floor)</p>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger" style="text-align:left; font-size:0.88rem;">
          <span><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($info)): ?>
        <div class="alert alert-info" style="text-align:left; font-size:0.88rem;">
          <span><i class="fas fa-info-circle"></i> <?= htmlspecialchars($info) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="form-group">
          <label for="username"><i class="fas fa-user" style="color:var(--brand-green);"></i> Username or Email</label>
          <input type="text" id="username" name="username" class="form-control" placeholder="admin" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label for="password"><i class="fas fa-lock" style="color:var(--brand-green);"></i> Password</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="••••••••••••" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; padding:0.8rem; font-size:1rem; margin-top:0.5rem;">
          <i class="fas fa-sign-in-alt"></i> Log In to Dashboard
        </button>
      </form>

      <div style="margin-top:2rem; font-size:0.82rem; color:var(--text-muted); border-top:1px solid #EAEFEA; padding-top:1rem;">
        Default Admin User: <code>admin</code><br>
        <span style="display:inline-block; margin-top:6px;">Play-On Mombasa Mall • All Rights Reserved</span>
      </div>
    </div>
  </div>
</body>
</html>
