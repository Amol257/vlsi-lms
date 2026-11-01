<?php
require_once __DIR__ . '/../db.php';

$user = auth_user();
if (!$user) {
    json_res(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$lecture_id = intval($_POST['lecture_id'] ?? 0);
$position   = intval($_POST['position'] ?? 0);
$duration   = intval($_POST['duration'] ?? 0);
$event      = trim($_POST['event'] ?? 'tick');

if ($lecture_id <= 0) {
    json_res(['ok' => false, 'error' => 'Invalid lecture ID'], 400);
}

$percent = ($duration > 0) ? min(100, round(($position / $duration) * 100)) : 0;
$completed = ($event === 'complete' || $percent >= 90) ? 1 : 0;

$pdo = get_db();
if ($pdo) {
    $stmt = $pdo->prepare("INSERT INTO lms_progress (user_id, lecture_id, position_seconds, duration_seconds, percent_watched, is_completed, last_viewed_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE 
            position_seconds = GREATEST(position_seconds, VALUES(position_seconds)),
            duration_seconds = VALUES(duration_seconds),
            percent_watched = GREATEST(percent_watched, VALUES(percent_watched)),
            is_completed = IF(is_completed = 1, 1, VALUES(is_completed)),
            last_viewed_at = NOW()");
    $stmt->execute([$user['id'], $lecture_id, $position, $duration, $percent, $completed]);
}

json_res([
    'ok'        => true,
    'percent'   => $percent,
    'completed' => (bool)$completed
]);
