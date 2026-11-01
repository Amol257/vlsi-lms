<?php
/**
 * VLSI LMS : Central Bootstrap and Security Initialization
 * Loaded by db.php and all entry points.
 */

// 1. Error Reporting Configuration
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// 2. Load Configuration
function lms_config(?string $key = null, $default = null) {
    static $config = null;
    if ($config === null) {
        $parent_config = dirname(__DIR__, 2) . '/config.local.php';
        $local_config = dirname(__DIR__) . '/config.local.php';

        if (file_exists($parent_config)) {
            $config = require $parent_config;
        } elseif (file_exists($local_config)) {
            $config = require $local_config;
        } elseif (file_exists(dirname(__DIR__) . '/config.example.php')) {
            $config = require dirname(__DIR__) . '/config.example.php';
        } else {
            $config = [];
        }
    }

    if ($key === null) {
        return $config;
    }

    $segments = explode('.', $key);
    $data = $config;
    foreach ($segments as $segment) {
        if (!is_array($data) || !array_key_exists($segment, $data)) {
            return $default;
        }
        $data = $data[$segment];
    }
    return $data;
}

// 3. Strict Session Setup
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// 4. Session Timeouts: 8 Hour Idle Timeout, 24 Hour Absolute Timeout
$now = time();
$idle_limit = (int)lms_config('app.session_idle_timeout', 28800);
$absolute_limit = (int)lms_config('app.session_absolute_timeout', 86400);

if (isset($_SESSION['lms_user'])) {
    if (isset($_SESSION['auth_time']) && ($now - $_SESSION['auth_time'] > $absolute_limit)) {
        session_unset();
        session_destroy();
        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Session expired. Please sign in again.']);
            exit;
        }
        header('Location: /login.php?reason=expired');
        exit;
    }

    if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity'] > $idle_limit)) {
        session_unset();
        session_destroy();
        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Session timed out due to inactivity.']);
            exit;
        }
        header('Location: /login.php?reason=idle');
        exit;
    }
    $_SESSION['last_activity'] = $now;
}

// 5. CSRF Protection Helpers
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_token(): string {
    return generate_csrf_token();
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(): void {
    $token = $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? $_GET['csrf_token']
        ?? '';

    if (!verify_csrf_token($token)) {
        http_response_code(403);
        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid or expired CSRF token.']);
            exit;
        }
        die('Forbidden: invalid CSRF token.');
    }
}

// 6. Output Escaping & JSON Helpers
function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function json_res(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// 7. Database Connection (PDO)
function get_db(): ?PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $db_cfg = lms_config('db', []);
    $host = $db_cfg['host'] ?? 'localhost';
    $name = $db_cfg['name'] ?? '';
    $user = $db_cfg['user'] ?? '';
    $pass = $db_cfg['pass'] ?? '';
    $charset = $db_cfg['charset'] ?? 'utf8mb4';

    if (empty($name) || empty($user) || $pass === 'CHANGE_ME_DATABASE_PASSWORD') {
        return null;
    }

    try {
        $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        return null;
    }
}

// 8. MySQL-backed Rate Limiter
function check_rate_limit(string $action, string $identifier, int $max_attempts, int $window_seconds): bool {
    $pdo = get_db();
    if (!$pdo) {
        // If DB is offline, fail closed for security sensitive endpoints
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT hits, reset_at FROM rate_limits WHERE action = ? AND identifier = ? LIMIT 1");
        $stmt->execute([$action, $identifier]);
        $row = $stmt->fetch();
        $now = time();

        if ($row) {
            $reset_time = strtotime($row['reset_at']);
            if ($now < $reset_time) {
                if ((int)$row['hits'] >= $max_attempts) {
                    return false;
                }
                $upd = $pdo->prepare("UPDATE rate_limits SET hits = hits + 1 WHERE action = ? AND identifier = ?");
                $upd->execute([$action, $identifier]);
                return true;
            } else {
                $reset_at = date('Y-m-d H:i:s', $now + $window_seconds);
                $upd = $pdo->prepare("UPDATE rate_limits SET hits = 1, first_hit_at = NOW(), reset_at = ? WHERE action = ? AND identifier = ?");
                $upd->execute([$reset_at, $action, $identifier]);
                return true;
            }
        } else {
            $reset_at = date('Y-m-d H:i:s', $now + $window_seconds);
            $ins = $pdo->prepare("INSERT INTO rate_limits (action, identifier, hits, first_hit_at, reset_at) VALUES (?, ?, 1, NOW(), ?)");
            $ins->execute([$action, $identifier, $reset_at]);
            return true;
        }
    } catch (PDOException $e) {
        // If rate_limits table does not exist yet, create it on demand
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `rate_limits` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `action` varchar(64) NOT NULL,
                `identifier` varchar(191) NOT NULL,
                `hits` int(11) NOT NULL DEFAULT 1,
                `first_hit_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `reset_at` timestamp NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_action_id` (`action`, `identifier`),
                KEY `idx_reset_at` (`reset_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            return true;
        } catch (Exception $ex) {
            error_log("Rate limiting error: " . $ex->getMessage());
            return true;
        }
    }
}

// 9. Client IP Resolver Helper
function get_client_ip(): string {
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = explode(',', $_SERVER[$key])[0];
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
