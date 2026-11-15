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

    // 4. Log event into master audit log
    lms_audit_log($user['id'], 'security_violation', 'videos', $video_id > 0 ? $video_id : null, [
        'type' => $violation_type,
        'details' => $details,
        'ip' => $client_ip
    ], $client_ip);

    // Note: Automated account blocking on routine browser events has been disabled.
    // Screenshots are allowed and accidental window blurs will not suspend accounts.
    json_res([
        'status' => 'logged',
        'type' => $violation_type
    ]);

} catch (PDOException $e) {
    error_log("Security violation intake error: " . $e->getMessage());
    json_res(['error' => 'Failed to record event'], 500);
}

