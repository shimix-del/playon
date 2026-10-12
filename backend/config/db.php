<?php
declare(strict_types=1);

/**
 * Play-On Mombasa - Database Connection & Configuration Helper
 * Robust PDO Singleton with .env parsing for cPanel PHP 8+ environments
 */

// Load .env configuration
(function () {
    $envPath = __DIR__ . '/.env';
    
    // Also check one level above if user placed .env outside public web root
    if (!file_exists($envPath)) {
        $parentEnv = dirname(__DIR__, 2) . '/.env';
        if (file_exists($parentEnv)) {
            $envPath = $parentEnv;
        }
    }

    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                [$key, $val] = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);
                // Strip outer quotes if present
                if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                    $val = substr($val, 1, -1);
                }
                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $val;
                    putenv("{$key}={$val}");
                }
            }
        }
    }
})();

/**
 * Retrieve environment variable with optional fallback
 */
function env(string $key, mixed $default = null): mixed {
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }
    $val = getenv($key);
    return $val !== false ? $val : $default;
}

/**
 * Return shared PDO database connection instance
 */
function getDbConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $host    = env('DB_HOST', 'localhost');
        $db      = env('DB_NAME', 'mombasaM_booking');
        $user    = env('DB_USER', 'mombasaM_admin');
        $pass    = env('DB_PASS', 'playon@4000');
        $charset = env('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci, time_zone = '+03:00'"
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Log full error internally without leaking credentials to client
            error_log('Play-On DB Connection Error: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed. Please check your credentials in config/.env.');
        }
    }

    return $pdo;
}
