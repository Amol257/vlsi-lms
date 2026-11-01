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
    // Ensure live_sessions table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `live_sessions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `type` enum('webinar','class') NOT NULL DEFAULT 'webinar',
        `title` varchar(255) NOT NULL,
        `description` text DEFAULT NULL,
        `starts_at` datetime NOT NULL,
        `meet_url` varchar(255) NOT NULL,
        `program_id` int(11) DEFAULT NULL,
        `created_by` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_starts_at` (`starts_at`),
        KEY `idx_type` (`type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare("SELECT * FROM live_sessions WHERE id = ? LIMIT 1");
    $stmt->execute([$session_id]);
    $live = $stmt->fetch();

    if (!$live) {
        json_res(['error' => 'Webinar session not found.'], 404);
    }

    $now = time();
    $starts_at = strtotime($live['starts_at']);
    $window_start = $starts_at - (10 * 60);      // 10 minutes prior
    $window_end = $starts_at + (3 * 3600);       // 3 hours after

    // Students must be within live window; Admins/Teachers may preview anytime
    if ($user['role'] === 'student') {
        if ($now < $window_start) {
            $mins_left = ceil(($starts_at - $now) / 60);
            json_res([
                'error' => "Webinar is not currently live. It starts in {$mins_left} minutes (access opens 10 minutes prior)."
            ], 403);
        }

        if ($now > $window_end) {
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
    json_res(['error' => 'Internal server error processing webinar join.'], 500);
}
