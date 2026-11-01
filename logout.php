<?php
require_once __DIR__ . '/db.php';

$user = auth_user();
if ($user) {
    $uid = $user['id'];
    $pdo = get_db();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("DELETE FROM lms_active_session WHERE user_id = ?");
            $stmt->execute([$uid]);
        } catch (PDOException $e) {
            // Table may not exist yet
        }
    }
    lms_audit_log($uid, 'user_logout', 'lms_users', $uid, []);
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}
session_destroy();

header("Location: /login.php");
exit;
