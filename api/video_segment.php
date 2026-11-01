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

// Verify timed HMAC token bound to user and video
if (!lms_verify_key_token($token, $user['id'], $video_id)) {
    http_response_code(403);
    die("Forbidden: Expired or invalid token.");
}

$pdo = get_db();
if (!$pdo) {
    http_response_code(500);
    die("Database error.");
}

// Single session check for students
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

// Release session lock immediately so parallel segment downloads do not stall PHP sessions
session_write_close();

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

    if (!file_exists($segment_path) || !is_readable($segment_path)) {
        http_response_code(404);
        die("Segment file not found.");
    }

    $filesize = filesize($segment_path);
    $start = 0;
    $end = $filesize - 1;
    $is_range = false;

    // Check for HTTP Range requests
    if (isset($_SERVER['HTTP_RANGE'])) {
        if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches)) {
            $start = (int)$matches[1];
            if (!empty($matches[2])) {
                $end = (int)$matches[2];
            }
            if ($start > $end || $start >= $filesize) {
                http_response_code(416);
                header("Content-Range: bytes */{$filesize}");
                exit;
            }
            if ($end >= $filesize) {
                $end = $filesize - 1;
            }
            $is_range = true;
        }
    }

    $length = $end - $start + 1;

    // Disable output buffering and zlib compression
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    @ini_set('zlib.output_compression', 'Off');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Accept-Ranges: bytes');
    header('Content-Type: video/mp2t');
    header('Cache-Control: private, max-age=3600');

    if ($is_range) {
        http_response_code(206);
        header("Content-Range: bytes {$start}-{$end}/{$filesize}");
    } else {
        http_response_code(200);
    }
    header("Content-Length: {$length}");

    // Stream file in 64 KB chunks
    $fp = fopen($segment_path, 'rb');
    if ($fp) {
        if ($start > 0) {
            fseek($fp, $start);
        }
        $bytes_left = $length;
        $chunk_size = 65536; // 64 KB
        while (!feof($fp) && $bytes_left > 0) {
            $read_len = min($chunk_size, $bytes_left);
            $buffer = fread($fp, $read_len);
            if ($buffer === false) {
                break;
            }
            echo $buffer;
            flush();
            $bytes_left -= strlen($buffer);
        }
        fclose($fp);
    }
    exit;

} catch (Exception $e) {
    error_log("Segment stream error: " . $e->getMessage());
    http_response_code(500);
    die("Internal error streaming segment.");
}
