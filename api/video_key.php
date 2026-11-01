<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/video_helper.php';

$user = auth_user();
if (!$user) {
    http_response_code(401);
    header('X-Content-Type-Options: nosniff');
    die("Unauthorized.");
}

$video_id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$token = trim($_GET['t'] ?? '');

if ($video_id <= 0 || empty($token)) {
    http_response_code(400);
    header('X-Content-Type-Options: nosniff');
    die("Bad request.");
}

// 1. Verify Timed HMAC Token bound to user and video
if (!lms_verify_key_token($token, $user['id'], $video_id)) {
    http_response_code(403);
    header('X-Content-Type-Options: nosniff');
    die("Access denied.");
}

$pdo = get_db();
if (!$pdo) {
    http_response_code(500);
    header('X-Content-Type-Options: nosniff');
    die("Server error.");
}

// 2. Continuous access check (verifies status, role, trial or active enrollment)
$access = lms_check_video_access($user, $video_id, $pdo);
if (!$access['allowed'] || !$access['video']) {
    http_response_code(403);
    header('X-Content-Type-Options: nosniff');
    die("Access denied.");
}

// 3. Close PHP session write lock immediately to prevent blocking parallel requests
session_write_close();

// 4. Rate Limiting and Burst Detection (>5 requests in 30 seconds)
$client_ip = get_client_ip();
$burst_key = "burst_key_{$user['id']}";
if (!check_rate_limit('video_key_burst', $burst_key, 5, 30)) {
    // Log burst alert at most once per minute per user to prevent table flooding
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
        header('X-Content-Type-Options: nosniff');
        die("Too many requests.");
    }
}

// 5. Retrieve and Decrypt Key using sodium secretbox
try {
    $video = $access['video'];
    if (empty($video['key_enc'])) {
        http_response_code(404);
        header('X-Content-Type-Options: nosniff');
        die("Key not found.");
    }

    $raw_key = lms_decrypt_video_key($video['key_enc']);
    if (!$raw_key || strlen($raw_key) !== 16) {
        http_response_code(500);
        header('X-Content-Type-Options: nosniff');
        die("Decryption failed.");
    }

    // Output Raw 16-byte Binary AES Key (never logged or cached)
    header('Content-Type: application/octet-stream');
    header('Content-Length: 16');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    echo $raw_key;
    exit;

} catch (PDOException $e) {
    error_log("Key retrieval error: " . $e->getMessage());
    http_response_code(500);
    header('X-Content-Type-Options: nosniff');
    die("Server error delivering key.");
}
