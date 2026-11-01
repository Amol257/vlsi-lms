<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/video_helper.php';

$user = auth_user();
if (!$user) {
    http_response_code(401);
    header('X-Content-Type-Options: nosniff');
    die("Unauthorized.");
}

$current_sid = session_id();

// Release session lock immediately so parallel segment downloads never stall
session_write_close();

$video_id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$segment = basename($_GET['s'] ?? '');
$token = trim($_GET['t'] ?? '');

if ($video_id <= 0 || empty($segment) || empty($token)) {
    http_response_code(400);
    header('X-Content-Type-Options: nosniff');
    die("Bad request.");
}

// Verify valid segment naming format (e.g. segment_0001.ts)
if (!preg_match('/^segment_\d+\.ts$/i', $segment)) {
    http_response_code(400);
    header('X-Content-Type-Options: nosniff');
    die("Invalid segment file specification.");
}

// Verify timed HMAC token bound to user and video
if (!lms_verify_key_token($token, $user['id'], $video_id)) {
    http_response_code(403);
    header('X-Content-Type-Options: nosniff');
    die("Access denied.");
}

$pdo = get_db();
if (!$pdo) {
    http_response_code(500);
    header('X-Content-Type-Options: nosniff');
    die("Database error.");
}

// 5-minute non-blocking cached access recheck: verifies enrollment/status lock-free
$access = lms_check_cached_video_access($user, $video_id, $pdo, 300);
if (!$access['allowed']) {
    http_response_code(403);
    header('X-Content-Type-Options: nosniff');
    die("Access denied.");
}

// Single joined query for student session + video lookup (admins/teachers skip session join)
$video = null;
try {
    if ($user['role'] === 'student') {
        $stmt = $pdo->prepare("SELECT v.id, v.hls_dir, s.session_id 
            FROM videos v 
            LEFT JOIN lms_active_session s ON s.user_id = ? 
            WHERE v.id = ? LIMIT 1");
        $stmt->execute([$user['id'], $video_id]);
        $row = $stmt->fetch();

        if (!$row || empty($row['session_id']) || $row['session_id'] !== $current_sid) {
            http_response_code(401);
            header('X-Content-Type-Options: nosniff');
            die("Session displaced.");
        }
        $video = $row;
    } else {
        $stmt = $pdo->prepare("SELECT id, hls_dir FROM videos WHERE id = ? LIMIT 1");
        $stmt->execute([$video_id]);
        $video = $stmt->fetch();
    }
} catch (PDOException $e) {
    error_log("Segment query error: " . $e->getMessage());
    http_response_code(500);
    header('X-Content-Type-Options: nosniff');
    die("Internal server error.");
}

try {
    if (!$video) {
        http_response_code(404);
        header('X-Content-Type-Options: nosniff');
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

    // Strict HTTP Range handling conforming to RFC 9110
    if (isset($_SERVER['HTTP_RANGE'])) {
        $range_header = trim($_SERVER['HTTP_RANGE']);
        // Reject multi-range requests (multipart/byteranges)
        if (str_contains($range_header, ',')) {
            http_response_code(416);
            header("Content-Range: bytes */{$filesize}");
            exit;
        }

        if (preg_match('/^bytes=\s*(\d*)\s*-\s*(\d*)$/i', $range_header, $matches)) {
            $raw_start = $matches[1];
            $raw_end = $matches[2];

            if ($raw_start === '' && $raw_end === '') {
                http_response_code(416);
                header("Content-Range: bytes */{$filesize}");
                exit;
            } elseif ($raw_start === '') {
                // Suffix range (e.g. bytes=-500)
                $suffix_len = (int)$raw_end;
                if ($suffix_len <= 0) {
                    http_response_code(416);
                    header("Content-Range: bytes */{$filesize}");
                    exit;
                }
                $start = max(0, $filesize - $suffix_len);
                $end = $filesize - 1;
                $is_range = true;
            } elseif ($raw_end === '') {
                // Open-ended range (e.g. bytes=100-)
                $start = (int)$raw_start;
                $end = $filesize - 1;
                if ($start >= $filesize) {
                    http_response_code(416);
                    header("Content-Range: bytes */{$filesize}");
                    exit;
                }
                $is_range = true;
            } else {
                // Explicit bounded range (e.g. bytes=0-99, bytes=0-0)
                $start = (int)$raw_start;
                $end = (int)$raw_end;
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
        } else {
            http_response_code(416);
            header("Content-Range: bytes */{$filesize}");
            exit;
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
    header('X-Content-Type-Options: nosniff');

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
            if (connection_aborted()) {
                break;
            }
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
