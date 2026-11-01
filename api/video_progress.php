<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$user = auth_user();
if (!$user) {
    json_res(['error' => 'Unauthorized'], 401);
}

$video_id = !empty($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
$position = !empty($_POST['position_sec']) ? (int)$_POST['position_sec'] : 0;
$duration = !empty($_POST['duration_sec']) ? (int)$_POST['duration_sec'] : 0;
$is_finished = (!empty($_POST['completed']) && $_POST['completed'] == 1) || ($duration > 0 && $position >= ($duration * 0.9));

if ($video_id <= 0) {
    json_res(['error' => 'Invalid video ID'], 400);
}

$pdo = get_db();
if (!$pdo) {
    json_res(['error' => 'Database error'], 500);
}

try {
    $stmt = $pdo->prepare("INSERT INTO video_progress (user_id, video_id, position_sec, completed, updated_at) 
        VALUES (?, ?, ?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE 
            position_sec = GREATEST(position_sec, VALUES(position_sec)),
            completed = IF(completed = 1, 1, VALUES(completed)),
            updated_at = NOW()");
    $stmt->execute([$user['id'], $video_id, $position, $is_finished ? 1 : 0]);

    json_res([
        'success' => true,
        'position' => $position,
        'completed' => (bool)$is_finished
    ]);
} catch (PDOException $e) {
    error_log("Video progress error: " . $e->getMessage());
    json_res(['error' => 'Failed to record progress: ' . $e->getMessage()], 500);
}
