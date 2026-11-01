<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/video_helper.php';

$user = auth_user();
if (!$user) {
    http_response_code(401);
    die("Unauthorized.");
}

$video_id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$segment = basename($_GET['s'] ?? '');
$token = trim($_GET['t'] ?? '');

if ($video_id <= 0 || empty($segment) || empty($token)) {
    http_response_code(400);
    die("Bad request: Missing parameters.");
}

// Verify valid segment naming format (e.g. segment_0001.ts)
if (!preg_match('/^segment_\d+\.ts$/i', $segment)) {
    http_response_code(400);
    die("Invalid segment file specification.");
}

// Verify timed HMAC token
if (!lms_verify_key_token($token, $user['id'], $video_id)) {
    http_response_code(403);
    die("Forbidden: Expired or invalid token.");
}

$pdo = get_db();
if (!$pdo) {
    http_response_code(500);
    die("Database error.");
}

try {
    $stmt = $pdo->prepare("SELECT hls_dir FROM videos WHERE id = ? LIMIT 1");
    $stmt->execute([$video_id]);
    $video = $stmt->fetch();

    if (!$video) {
        http_response_code(404);
        die("Video not found.");
    }

    $storage_base = lms_get_video_storage_dir();
    $hls_dir = basename($video['hls_dir']);
    $segment_path = $storage_base . '/' . $hls_dir . '/' . $segment;

    if (!file_exists($segment_path)) {
        http_response_code(404);
        die("Segment file not found.");
    }

    $filesize = filesize($segment_path);
    header('Content-Type: video/mp2t');
    header('Content-Length: ' . $filesize);
    header('Cache-Control: private, max-age=3600');

    // Stream file cleanly
    $fp = fopen($segment_path, 'rb');
    if ($fp) {
        while (!feof($fp)) {
            echo fread($fp, 65536);
            flush();
        }
        fclose($fp);
    }
    exit;

} catch (Exception $e) {
    error_log("Segment stream error: " . $e->getMessage());
    http_response_code(500);
    die("Internal error streaming segment.");
}
