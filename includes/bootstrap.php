<?php
/**
 * VLSI LMS : Central Bootstrap and Security Initialization
 * Loaded by db.php and all entry points.
 */

// 1. PHP Runtime Environment Guard
if (PHP_VERSION_ID < 80000 || !extension_loaded('sodium')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    die("Server Configuration Error: VLSI LMS requires PHP 8.0 or higher with the 'sodium' extension enabled. In Hostinger hPanel, please set the PHP version for learn.vlsiinsights.com to PHP 8.2 and verify that extension=sodium is active.");
}

// 2. Error Reporting Configuration
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Kolkata');

// 2. Load Configuration
function lms_get_candidate_config_paths(): array {
    return [
        'custom_env'     => getenv('LMS_CONFIG') ?: null,
        'domain_parent'  => dirname(__DIR__, 3) . '/config.local.php', // /home/<user>/domains/<domain>/config.local.php
        'user_home'      => dirname(__DIR__, 5) . '/config.local.php', // /home/<user>/config.local.php
        'project_root'   => dirname(__DIR__) . '/config.local.php',     // Protected fallback inside repo root
        'example_config' => dirname(__DIR__) . '/config.example.php'
    ];
}

function lms_get_loaded_config_path(): ?string {
    return $GLOBALS['lms_loaded_config_file'] ?? null;
}

function lms_config(?string $key = null, $default = null) {
    static $config = null;
    if ($config === null) {
        $paths = lms_get_candidate_config_paths();
        $GLOBALS['lms_loaded_config_file'] = null;

        if (!empty($paths['custom_env']) && file_exists($paths['custom_env'])) {
            $config = require $paths['custom_env'];
            $GLOBALS['lms_loaded_config_file'] = $paths['custom_env'];
        } elseif (file_exists($paths['domain_parent'])) {
            $config = require $paths['domain_parent'];
            $GLOBALS['lms_loaded_config_file'] = $paths['domain_parent'];
        } elseif (file_exists($paths['user_home'])) {
            $config = require $paths['user_home'];
            $GLOBALS['lms_loaded_config_file'] = $paths['user_home'];
        } elseif (file_exists($paths['project_root'])) {
            $config = require $paths['project_root'];
            $GLOBALS['lms_loaded_config_file'] = $paths['project_root'];
        } elseif (file_exists($paths['example_config'])) {
            $config = require $paths['example_config'];
            $GLOBALS['lms_loaded_config_file'] = $paths['example_config'];
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

// 3. IP and Network Helpers
function lms_ip_in_range(string $ip, string $range): bool {
    $range = trim($range);
    if (!str_contains($range, '/')) {
        return $ip === $range;
    }
    [$subnet, $bits] = explode('/', $range, 2);
    $ip_bin = @inet_pton($ip);
    $subnet_bin = @inet_pton($subnet);
    if ($ip_bin === false || $subnet_bin === false || strlen($ip_bin) !== strlen($subnet_bin)) {
        return false;
    }
    $bits = (int)$bits;
    $max_bits = strlen($ip_bin) * 8;
    if ($bits < 0 || $bits > $max_bits) {
        return false;
    }
    $bytes = intdiv($bits, 8);
    $remainder_bits = $bits % 8;
    if ($bytes > 0 && substr($ip_bin, 0, $bytes) !== substr($subnet_bin, 0, $bytes)) {
        return false;
    }
    if ($remainder_bits > 0) {
        $mask = ~((1 << (8 - $remainder_bits)) - 1) & 0xFF;
        if ((ord($ip_bin[$bytes]) & $mask) !== (ord($subnet_bin[$bytes]) & $mask)) {
            return false;
        }
    }
    return true;
}

function lms_is_trusted_ip(string $ip, array $trusted_proxies): bool {
    foreach ($trusted_proxies as $range) {
        if (lms_ip_in_range($ip, $range)) {
            return true;
        }
    }
    return false;
}

// 4. Strict Session Setup
$idle_limit = (int)lms_config('app.session_idle_timeout', 28800);
$absolute_limit = (int)lms_config('app.session_absolute_timeout', 86400);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string)$idle_limit);

    // Optional custom private session storage directory
    $custom_sess_path = lms_config('app.session_save_path');
    if (empty($custom_sess_path)) {
        $auto_parent_sess = dirname(__DIR__, 3) . '/lms_sessions';
        if (is_dir($auto_parent_sess) && is_writable($auto_parent_sess)) {
            $custom_sess_path = $auto_parent_sess;
        }
    }
    if (!empty($custom_sess_path) && is_dir($custom_sess_path) && is_writable($custom_sess_path)) {
        session_save_path($custom_sess_path);
    }

    $remote_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $trusted_proxies = lms_config('app.trusted_proxies', []);
    if (!is_array($trusted_proxies)) {
        $trusted_proxies = array_filter(array_map('trim', explode(',', (string)$trusted_proxies)));
    }
    $is_trusted_proxy = lms_is_trusted_ip($remote_ip, $trusted_proxies);

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || ($is_trusted_proxy && isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

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

function lms_is_custom_session_path_active(): bool {
    $current = session_save_path();
    if (empty($current)) {
        return false;
    }
    // Check if path is outside default system temp folder
    $sys_temp = sys_get_temp_dir();
    if (rtrim($current, '/\\') === rtrim($sys_temp, '/\\') || $current === '/tmp') {
        return false;
    }
    return is_dir($current) && is_writable($current);
}

// 4. Session Timeouts: 8 Hour Idle Timeout, 24 Hour Absolute Timeout
$now = time();

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

    // Do not refresh last_activity on heartbeat pings so 8 hour idle timeout triggers if tab left idle
    $req_uri = $_SERVER['REQUEST_URI'] ?? '';
    if (!str_contains($req_uri, 'heartbeat.php')) {
        $_SESSION['last_activity'] = $now;
    }
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
    // Only accept POST body or HTTP header; never accept query parameters
    $token = $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';

    if (!verify_csrf_token($token)) {
        http_response_code(403);
        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid or missing CSRF token. Please refresh the page.']);
            exit;
        }
        die("403 Forbidden: Invalid CSRF token. Please return to the previous page and reload.");
    }
}

// 6. Security and Sanitization Utilities
function get_client_ip(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $trusted_proxies = lms_config('app.trusted_proxies', []);
    if (!is_array($trusted_proxies)) {
        $trusted_proxies = array_filter(array_map('trim', explode(',', (string)$trusted_proxies)));
    }

    if (empty($trusted_proxies) || !lms_is_trusted_ip($remote, $trusted_proxies)) {
        return $remote;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        for ($i = count($parts) - 1; $i >= 0; $i--) {
            if (!lms_is_trusted_ip($parts[$i], $trusted_proxies)) {
                $filtered = filter_var($parts[$i], FILTER_VALIDATE_IP);
                if ($filtered !== false) {
                    return $filtered;
                }
            }
        }
    }

    return $remote;
}

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
        $pdo->exec("SET time_zone = '+05:30'");
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        return null;
    }
}

// 8. MySQL-backed Atomic Rate Limiter
function check_rate_limit(string $action, string $identifier, int $max_attempts, int $window_seconds, bool $fail_closed = true): bool {
    $pdo = get_db();
    if (!$pdo) {
        return !$fail_closed;
    }

    if (strlen($identifier) > 128) {
        $identifier = hash('sha256', $identifier);
    }

    // 1% probabilistic cleanup of expired rate limit rows older than 1 hour
    if (mt_rand(1, 100) === 1) {
        try {
            $pdo->exec("DELETE FROM rate_limits WHERE reset_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        } catch (\Throwable $t) {
            // Silently ignore cleanup errors
        }
    }

    try {
        $now = time();
        $reset_at = date('Y-m-d H:i:s', $now + $window_seconds);

        // Atomic upsert with conditional reset in SQL
        $sql = "INSERT INTO rate_limits (action, identifier, hits, first_hit_at, reset_at)
                VALUES (?, ?, 1, NOW(), ?)
                ON DUPLICATE KEY UPDATE
                    hits = IF(reset_at <= NOW(), 1, hits + 1),
                    first_hit_at = IF(reset_at <= NOW(), NOW(), first_hit_at),
                    reset_at = IF(reset_at <= NOW(), VALUES(reset_at), reset_at)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$action, $identifier, $reset_at]);

        $sel = $pdo->prepare("SELECT hits FROM rate_limits WHERE action = ? AND identifier = ? LIMIT 1");
        $sel->execute([$action, $identifier]);
        $row = $sel->fetch();

        if ($row && (int)$row['hits'] > $max_attempts) {
            return false;
        }
        return true;
    } catch (\Throwable $e) {
        error_log("Rate limiting error: " . $e->getMessage());
        return !$fail_closed;
    }
}
