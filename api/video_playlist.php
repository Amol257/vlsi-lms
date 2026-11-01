<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/video_helper.php';

$user = auth_user();
if (!$user) {
    http_response_code(401);
    die("Unauthorized: Please sign in to stream video content.");
}

$video_id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
if ($video_id <= 0) {
    http_response_code(400);
    die("Bad request: Missing or invalid video ID.");
}

$pdo = get_db();
if (!$pdo) {
    http_response_code(500);
    die("Database connection error.");
}

try {
    $stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ? LIMIT 1");
    $stmt->execute([$video_id]);
    $video = $stmt->fetch();

    if (!$video) {
        http_response_code(404);
        die("Video not found.");
    }

    // Access authorization check
    $allowed = false;
    if ($user['role'] === 'admin' || $user['role'] === 'teacher') {
        $allowed = true;
    } elseif (!empty($video['is_trial'])) {
        $allowed = true;
    } else {
        // Check active enrollment in program
        $enroll_stmt = $pdo->prepare("SELECT id FROM enrollments 
            WHERE user_id = ? AND program_id = ? AND status = 'active' 
            AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
        $enroll_stmt->execute([$user['id'], $video['program_id']]);
        if ($enroll_stmt->fetch()) {
            $allowed = true;
        }
    }

    if (!$allowed) {
        http_response_code(403);
        die("Forbidden: An active program enrollment is required to watch this video.");
    }

    // Locate index.m3u8 on disk
    $storage_base = lms_get_video_storage_dir();
    $hls_dir = basename($video['hls_dir']);
    $m3u8_path = $storage_base . '/' . $hls_dir . '/index.m3u8';

    if (!file_exists($m3u8_path)) {
        http_response_code(404);
        die("Video playlist stream file not found on disk.");
    }

    $raw_m3u8 = file_get_contents($m3u8_path);
    if ($raw_m3u8 === false) {
        http_response_code(500);
        die("Failed to read video playlist.");
    }

    // Generate secure 10-minute HMAC token
    $token = lms_generate_key_token($user['id'], $video_id, 600);
    $key_url = "/api/video_key.php?id={$video_id}&t={$token}";

    // Rewrite encryption key URI
    $rewritten = preg_replace('/URI="[^"]*"/', 'URI="' . $key_url . '"', $raw_m3u8);

    // Rewrite relative segment paths to secure segment streaming proxy
    $lines = explode("\n", $rewritten);
    $output_lines = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (str_ends_with($trimmed, '.ts') && !str_starts_with($trimmed, '#')) {
            $seg_name = urlencode(basename($trimmed));
            $output_lines[] = "/api/video_segment.php?id={$video_id}&s={$seg_name}&t={$token}";
        } else {
            $output_lines[] = $line;
        }
    }

    $final_playlist = implode("\n", $output_lines);

    header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
    header('Cache-Control: no-cache, no-store, private, must-revalidate');
    header('Pragma: no-cache');
    echo $final_playlist;
    exit;

} catch (PDOException $e) {
    error_log("Playlist error: " . $e->getMessage());
    http_response_code(500);
    die("Internal server error generating video playlist.");
}
