<?php
require_once __DIR__ . '/../db.php';

$user = auth_user();
if (!$user) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Teachers and admins are exempt from single active session heartbeat locks
if ($user['role'] !== 'student') {
    http_response_code(204);
    exit;
}

$pdo = get_db();
if (!$pdo) {
    // If DB temporarily unavailable, keep session alive
    http_response_code(204);
    exit;
}

$user_id = (int)$user['id'];
$current_session_id = session_id();
$client_ip = get_client_ip();

try {
    $stmt = $pdo->prepare("SELECT session_id FROM lms_active_session WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();

    if (!$row || $row['session_id'] !== $current_session_id) {
        // Displaced session or active lock was revoked
        session_unset();
        session_destroy();
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Session displaced on another device']);
        exit;
    }

    // Update last_seen and IP
    $upd = $pdo->prepare("UPDATE lms_active_session SET last_seen = NOW(), ip = ? WHERE user_id = ? AND session_id = ?");
    $upd->execute([$client_ip, $user_id, $current_session_id]);

    // Lazy cleanup of sessions older than 24 hours (run 2% of the time)
    if (random_int(1, 50) === 1) {
        $pdo->exec("DELETE FROM lms_active_session WHERE last_seen < NOW() - INTERVAL 24 HOUR");
    }

    http_response_code(204);
    exit;
} catch (PDOException $e) {
    error_log("Heartbeat error: " . $e->getMessage());
    http_response_code(204);
    exit;
}
