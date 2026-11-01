<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/video_helper.php';

$user = auth_user();
if (!$user) {
    http_response_code(401);
    die("Unauthorized.");
}

$video_id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$token = trim($_GET['t'] ?? '');

if ($video_id <= 0 || empty($token)) {
    http_response_code(400);
    die("Bad request: Missing parameters.");
}

// 1. Verify Timed HMAC Token
if (!lms_verify_key_token($token, $user['id'], $video_id)) {
    http_response_code(403);
    die("Forbidden: Invalid or expired key authorization token.");
}

$pdo = get_db();
if (!$pdo) {
    http_response_code(500);
    die("Database error.");
}

// 2. Student Single Active Session Enforcement
if ($user['role'] === 'student') {
    $current_sid = session_id();
    $sess_stmt = $pdo->prepare("SELECT session_id FROM lms_active_session WHERE user_id = ? LIMIT 1");
    $sess_stmt->execute([$user['id']]);
    $active = $sess_stmt->fetch();

    if (!$active || $active['session_id'] !== $current_sid) {
        http_response_code(401);
        die("Session displaced by another active login.");
    }
}

// 3. Rate Limiting and Burst Detection (>5 requests in 30 seconds)
$client_ip = get_client_ip();
$burst_key = "burst_key_{$user['id']}";
if (!check_rate_limit('video_key_burst', $burst_key, 5, 30)) {
    // Log to audit_log at most once per minute per user to prevent table flooding
    $alert_key = "key_burst_alert_{$user['id']}";
    if (check_rate_limit('video_key_burst_log', $alert_key, 1, 60)) {
        lms_audit_log($user['id'], 'key_burst_detected', 'videos', $video_id, [
            'ip' => $client_ip,
            'warning' => 'Unusual rapid key request rate detected'
        ]);
    }

    // Hard limit: block if > 20 requests in 30s
    if (!check_rate_limit('video_key_hard', $burst_key, 20, 30)) {
        http_response_code(429);
        die("Too many key requests. Please wait.");
    }
}

// 4. Retrieve and Decrypt Key using sodium secretbox
try {
    $stmt = $pdo->prepare("SELECT key_enc FROM videos WHERE id = ? LIMIT 1");
    $stmt->execute([$video_id]);
    $row = $stmt->fetch();

    if (!$row || empty($row['key_enc'])) {
        http_response_code(404);
        die("Encryption key record not found.");
    }

    $raw_key = lms_decrypt_video_key($row['key_enc']);
    if (!$raw_key || strlen($raw_key) !== 16) {
        http_response_code(500);
        die("Failed to decrypt video key.");
    }

    // 5. Output Raw 16-byte Binary AES Key (never logged or cached)
    header('Content-Type: application/octet-stream');
    header('Content-Length: 16');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    echo $raw_key;
    exit;

} catch (PDOException $e) {
    error_log("Key retrieval error: " . $e->getMessage());
    http_response_code(500);
    die("Internal server error delivering key.");
}
