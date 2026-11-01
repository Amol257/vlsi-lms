<?php
/**
 * VLSI LMS — CENTRAL DATABASE CONFIGURATION & AUTH HELPER
 * Edit DB credentials below to match your Hostinger MySQL Database.
 */

// ─── 1. HOSTINGER DATABASE CREDENTIALS ─────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'u123456789_lms');      // Replace with your Hostinger Database Name
define('DB_USER', 'u123456789_lmsuser');  // Replace with your Hostinger Database User
define('DB_PASS', 'YOUR_DATABASE_PASSWORD'); // Replace with your Hostinger Database Password

// ─── 2. SESSION INITIALIZATION ────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// ─── 3. PDO CONNECTION ────────────────────────────────────────────────────────
function get_db() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Return null if MySQL not yet configured
        return null;
    }
}

// ─── 4. AUTHENTICATION & ACCESS CONTROL HELPERS ────────────────────────────────
function auth_user() {
    return isset($_SESSION['lms_user']) ? $_SESSION['lms_user'] : null;
}

function require_role($allowed_roles = []) {
    $user = auth_user();
    if (!$user) {
        header("Location: /login.php");
        exit;
    }
    if (!empty($allowed_roles) && !in_array($user['role'], $allowed_roles)) {
        if ($user['role'] === 'admin') header("Location: /admin/index.php");
        elseif ($user['role'] === 'teacher') header("Location: /teacher/index.php");
        else header("Location: /student/index.php");
        exit;
    }
    return $user;
}

function json_res($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
