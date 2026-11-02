<?php
/**
 * VLSI LMS : Anti-Piracy and Screen-Capture Violation Intake Endpoint
 * Handles automated client security violation beacons and focus deterrents.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/video_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$user = auth_user();
if (!$user) {
    json_res(['error' => 'Unauthorized'], 401);
}

// 1. Support both form-urlencoded POST and JSON raw payload (used by navigator.sendBeacon)
$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true);

$video_id = 0;
$violation_type = '';
$details = '';

if (is_array($json_data)) {
    $video_id = !empty($json_data['video_id']) ? (int)$json_data['video_id'] : 0;
    $violation_type = trim($json_data['violation_type'] ?? '');
    $details = trim($json_data['details'] ?? '');
} else {
    $video_id = !empty($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
    $violation_type = trim($_POST['violation_type'] ?? '');
    $details = trim($_POST['details'] ?? '');
}

// Whitelist recognized violation types to prevent garbage injections
$allowed_types = [
    'window_blur',
    'tab_hidden',
    'print_screen_key',
    'screenshot_shortcut',
    'screen_share_attempt',
    'devtools_opened',
    'dom_tampering',
    'debugger_trap',
    'canvas_tampering'
];

if (!in_array($violation_type, $allowed_types, true)) {
    $violation_type = 'unknown_suspicious_activity';
}

$pdo = get_db();
if (!$pdo) {
    json_res(['error' => 'Database connection failed'], 500);
}

$client_ip = get_client_ip();
$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

try {
    // 2. Insert incident into security_violations table
    $stmt = $pdo->prepare("INSERT INTO security_violations (user_id, video_id, violation_type, details, ip, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $user['id'],
        $video_id > 0 ? $video_id : null,
        $violation_type,
        substr($details, 0, 1000),
        $client_ip,
        $user_agent
    ]);

    // 3. Log event into master audit log
    lms_audit_log($user['id'], 'security_violation', 'videos', $video_id > 0 ? $video_id : null, [
        'type' => $violation_type,
        'details' => $details,
        'ip' => $client_ip
    ], $client_ip);

    // 4. Count violations in the last 24 hours
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM security_violations 
        WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $count_stmt->execute([$user['id']]);
    $recent_count = (int)$count_stmt->fetchColumn();

    // 5. Automated Enforcement: Block user if 5 or more violations within 24 hours
    if ($recent_count >= 5 && $user['role'] === 'student') {
        $block_stmt = $pdo->prepare("UPDATE lms_users SET status = 'blocked' WHERE id = ?");
        $block_stmt->execute([$user['id']]);

        // Revoke active sessions immediately
        $sess_stmt = $pdo->prepare("DELETE FROM lms_active_session WHERE user_id = ?");
        $sess_stmt->execute([$user['id']]);

        lms_audit_log($user['id'], 'account_auto_blocked', 'lms_users', $user['id'], [
            'reason' => 'Automated block: excessive anti-piracy security violations',
            'violation_count' => $recent_count
        ], $client_ip);

        json_res([
            'status' => 'blocked',
            'action' => 'terminate',
            'message' => 'Your account has been suspended due to repeated screen recording or capture violations. Contact admin@vlsiinsights.com for review.'
        ], 403);
    }

    json_res([
        'status' => 'logged',
        'action' => 'warning',
        'count' => $recent_count,
        'max_allowed' => 5
    ]);

} catch (PDOException $e) {
    error_log("Security violation intake error: " . $e->getMessage());
    json_res(['error' => 'Failed to record event'], 500);
}
