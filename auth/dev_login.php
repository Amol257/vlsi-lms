<?php
require_once __DIR__ . '/../db.php';

// Strictly restrict to localhost development
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if ($ip !== '127.0.0.1' && $ip !== '::1' && $ip !== 'localhost') {
    http_response_code(403);
    die('Forbidden: Dev login only allowed on localhost.');
}

$role = $_GET['role'] ?? ($_GET['as'] ?? 'student');
$pdo = get_db();
if (!$pdo) {
    die('Database connection error.');
}

if ($role === 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM lms_users WHERE role = 'admin' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("SELECT * FROM lms_users WHERE role = 'student' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch();
}

if (!$user) {
    die('No user found for role ' . htmlspecialchars($role));
}

// Auto-unblock and clear violations in local dev
$pdo->prepare("UPDATE lms_users SET status = 'active' WHERE id = ?")->execute([$user['id']]);
$pdo->prepare("DELETE FROM security_violations WHERE user_id = ?")->execute([$user['id']]);
$user['status'] = 'active';

// Establish local session
$_SESSION['lms_user'] = $user;
$_SESSION['auth_time'] = time();
$_SESSION['last_activity'] = time();

$sid = session_id();
$pdo->prepare("INSERT INTO lms_active_session (user_id, session_id, ip, user_agent, last_seen) 
    VALUES (?, ?, ?, ?, NOW()) 
    ON DUPLICATE KEY UPDATE session_id = ?, last_seen = NOW()")->execute([
    $user['id'], $sid, $ip, $_SERVER['HTTP_USER_AGENT'] ?? 'LocalDev', $sid
]);

$vid = !empty($_GET['id']) ? (int)$_GET['id'] : 2;

$default_redirect = ($user['role'] === 'admin') ? '/admin/videos.php' : ('/student/watch.php?id=' . $vid);
$redirect = !empty($_GET['redirect']) ? str_replace('%3F', '?', (string)$_GET['redirect']) : $default_redirect;
header("Location: " . $redirect);
exit;

