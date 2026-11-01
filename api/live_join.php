<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$user = auth_user();
if (!$user) {
    json_res(['error' => 'Please sign in to join the webinar.'], 401);
}

// 1. Rate Limiting: 10 calls per minute
$client_ip = get_client_ip();
$rate_key = $user['id'] . '_' . $client_ip;
if (!check_rate_limit('live_join', $rate_key, 10, 60)) {
    json_res(['error' => 'Too many join requests. Please wait a minute.'], 429);
}

// 2. Validate Session ID parameter
$session_id = !empty($_GET['id']) ? (int)$_GET['id'] : (!empty($_POST['id']) ? (int)$_POST['id'] : 0);
if ($session_id <= 0) {
    json_res(['error' => 'Invalid webinar session ID.'], 400);
}

$pdo = get_db();
if (!$pdo) {
    json_res(['error' => 'Database connection failed.'], 500);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM live_sessions WHERE id = ? LIMIT 1");
    $stmt->execute([$session_id]);
    $live = $stmt->fetch();

    if (!$live) {
        json_res(['error' => 'Webinar session not found.'], 404);
    }

    // Compute live join window strictly in Asia/Kolkata timezone (10 min prior, 3 hours after)
    $tz = new DateTimeZone('Asia/Kolkata');
    $start_dt = new DateTime($live['starts_at'], $tz);
    $starts_at_ts = $start_dt->getTimestamp();
    $now_ts = (new DateTime('now', $tz))->getTimestamp();

    $window_start = $starts_at_ts - (10 * 60);      // 10 minutes prior
    $window_end = $starts_at_ts + (3 * 3600);       // 3 hours after

    // Students must be within live window; Admins/Teachers may preview anytime
    if ($user['role'] === 'student') {
        if ($now_ts < $window_start) {
            $mins_left = ceil(($starts_at_ts - $now_ts) / 60);
            json_res([
                'error' => "Webinar is not currently live. It starts in {$mins_left} minutes (access opens 10 minutes prior)."
            ], 403);
        }

        if ($now_ts > $window_end) {
            json_res([
                'error' => 'This webinar session has concluded.'
            ], 403);
        }
    }

    // Log join event
    lms_audit_log($user['id'], 'join_webinar', 'live_sessions', $session_id, [
        'user_name' => $user['name'],
        'user_email' => $user['email'],
        'role' => $user['role'],
        'ip' => $client_ip
    ]);

    json_res([
        'success' => true,
        'meet_url' => $live['meet_url'],
        'title' => $live['title']
    ]);

} catch (PDOException $e) {
    error_log("Live join error: " . $e->getMessage());
    json_res(['error' => 'Server error retrieving webinar session.'], 500);
}
