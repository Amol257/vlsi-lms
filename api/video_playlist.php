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
    $access = lms_check_video_access($user, $video_id, $pdo);
    if (!$access['allowed'] || !$access['video']) {
        http_response_code(403);
        header('X-Content-Type-Options: nosniff');
        die("Forbidden: Access denied or program enrollment required.");
    }
    $video = $access['video'];

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

    // Token lifetime: duration_sec + 30 minutes, capped at 4 hours (default 2 hours if missing)
    $dur = (int)($video['duration_sec'] ?? 0);
    if ($dur > 0) {
        $ttl = min(14400, $dur + 1800);
    } else {
        $ttl = 7200;
    }

    $token = lms_generate_key_token($user['id'], $video_id, $ttl);
    if ($token === null) {
        http_response_code(500);
        die("Server security error: Key signing unavailable.");
    }

    $key_url = "/api/video_key.php?id={$video_id}&t={$token}";

    // Rewrite encryption key URI
    $rewritten = preg_replace('/URI="[^"]*"/', 'URI="' . $key_url . '"', $raw_m3u8);

    // Rewrite relative segment paths based on segments_mode (php or static)
    $segments_mode = lms_config('video.segments_mode', 'php');
    $lines = explode("\n", $rewritten);
    $output_lines = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (str_ends_with($trimmed, '.ts') && !str_starts_with($trimmed, '#')) {
            $seg_name = basename($trimmed);
            if ($segments_mode === 'static') {
                $output_lines[] = "/media/hls/{$hls_dir}/" . rawurlencode($seg_name);
            } else {
                $output_lines[] = "/api/video_segment.php?id={$video_id}&s=" . urlencode($seg_name) . "&t={$token}";
            }
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
