<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/video_helper.php';

header('Content-Type: application/json; charset=utf-8');

$user = auth_user();
if (!$user) {
    json_res(['error' => 'Unauthorized'], 401);
}

// 1. Require CSRF token (verified from POST body or X-CSRF-Token header)
require_csrf();

$video_id = !empty($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
$position = !empty($_POST['position_sec']) ? (int)$_POST['position_sec'] : 0;

if ($video_id <= 0) {
    json_res(['error' => 'Invalid video ID.'], 400);
}

$pdo = get_db();
if (!$pdo) {
    json_res(['error' => 'Database error.'], 500);
}

try {
    // 2. Validate video access with shared policy function
    $access = lms_check_video_access($user, $video_id, $pdo);
    if (!$access['allowed'] || !$access['video']) {
        json_res(['error' => 'Access denied to this video lecture.'], 403);
    }
    $video = $access['video'];

    // 3. Read duration_sec from videos table (never trust client duration)
    $duration_sec = (int)($video['duration_sec'] ?? 0);
    if ($duration_sec > 0) {
        // Clamp position between 0 and duration_sec
        $position = max(0, min($position, $duration_sec));
    } else {
        $position = max(0, $position);
    }

    // 4. Mark completed only when position is at least 90% of stored duration
    $client_wants_completed = !empty($_POST['completed']) && (int)$_POST['completed'] === 1;
    $is_finished = ($duration_sec > 0) && ($position >= ($duration_sec * 0.90));
    if ($client_wants_completed && $duration_sec > 0 && $position >= ($duration_sec * 0.85)) {
        $is_finished = true;
    }

    // 5. Update progress: store latest position for true resume, preserve completed status
    $stmt = $pdo->prepare("INSERT INTO video_progress (user_id, video_id, position_sec, completed, updated_at) 
        VALUES (?, ?, ?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE 
            position_sec = VALUES(position_sec),
            completed = IF(completed = 1, 1, VALUES(completed)),
            updated_at = NOW()");
    $stmt->execute([$user['id'], $video_id, $position, $is_finished ? 1 : 0]);

    json_res([
        'success'   => true,
        'position'  => $position,
        'completed' => (bool)$is_finished
    ]);

} catch (PDOException $e) {
    error_log("Video progress error: " . $e->getMessage());
    json_res(['error' => 'Failed to record playback progress.'], 500);
}
