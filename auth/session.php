<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_res(['error' => 'Method not allowed.'], 405);
}

// 1. Verify CSRF Token
$csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    json_res(['error' => 'Invalid or expired CSRF token. Please refresh the page.'], 403);
}

// 2. Rate Limiting on Session Exchange
$client_ip = get_client_ip();
if (!check_rate_limit('session_exchange', $client_ip, 30, 300)) {
    json_res(['error' => 'Too many sign in requests. Please wait 5 minutes.'], 429);
}

// 3. Extract Bearer / Access Token
$access_token = trim($_POST['access_token'] ?? '');
if (empty($access_token)) {
    // Check Authorization header fallback
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
        $access_token = trim($matches[1]);
    }
}

if (empty($access_token)) {
    json_res(['error' => 'Missing authentication token.'], 400);
}

// 4. Verify Token with Supabase Auth Server-Side
$supabase_url = lms_config('supabase.url');
$supabase_anon_key = lms_config('supabase.anon_key');

if (empty($supabase_url) || empty($supabase_anon_key)) {
    json_res(['error' => 'Supabase authentication is not configured on the server.'], 500);
}

$verify_url = rtrim($supabase_url, '/') . '/auth/v1/user';
$ch = curl_init($verify_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . $supabase_anon_key,
        'Authorization: Bearer ' . $access_token,
    ],
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response_body = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err = curl_error($ch);
curl_close($ch);

if ($http_code !== 200 || !$response_body) {
    lms_audit_log(0, 'auth_token_failed', 'supabase', null, [
        'http_code' => $http_code,
        'error' => $curl_err,
        'ip' => $client_ip
    ]);
    json_res(['error' => 'Invalid or expired Supabase authentication session.'], 401);
}

$user_data = json_decode($response_body, true);
if (!is_array($user_data) || empty($user_data['id']) || empty($user_data['email'])) {
    json_res(['error' => 'Malformed user profile received from Supabase.'], 401);
}

$supabase_uid = $user_data['id'];
$email = strtolower(trim($user_data['email']));

// 5. Require Confirmed Email
$email_confirmed = !empty($user_data['email_confirmed_at']) || !empty($user_data['confirmed_at']);
if (!$email_confirmed) {
    json_res(['error' => 'Please confirm your email address before signing in.'], 403);
}

// 6. Extract User Name from user_metadata with email prefix fallback
$user_meta = $user_data['user_metadata'] ?? [];
$name = trim($user_meta['full_name'] ?? $user_meta['name'] ?? '');
if (empty($name)) {
    $parts = explode('@', $email);
    $name = ucwords(str_replace(['.', '_', '-'], ' ', $parts[0]));
}

$selected_batch_id = !empty($user_meta['batch_id']) ? (int)$user_meta['batch_id'] : null;

// 7. Check Admin Bootstrap Configuration
$bootstrap_emails = lms_config('app.admin_bootstrap_emails', []);
if (is_string($bootstrap_emails)) {
    $bootstrap_emails = array_map('trim', explode(',', $bootstrap_emails));
}
$is_bootstrap_admin = in_array($email, array_map('strtolower', $bootstrap_emails), true);

// 8. Database Lookup and User Upsert
$pdo = get_db();
if (!$pdo) {
    json_res(['error' => 'Database connection failed. Please contact administrator.'], 500);
}

try {
    // Check if lms_users has supabase_uid column; if not, add it
    $col_check = $pdo->query("SHOW COLUMNS FROM `lms_users` LIKE 'supabase_uid'");
    if ($col_check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `lms_users` ADD COLUMN `supabase_uid` varchar(64) DEFAULT NULL AFTER `id`");
        $pdo->exec("ALTER TABLE `lms_users` ADD UNIQUE KEY `idx_user_supabase_uid` (`supabase_uid`)");
    }

    // Check last_login_at column
    $col_check2 = $pdo->query("SHOW COLUMNS FROM `lms_users` LIKE 'last_login_at'");
    if ($col_check2->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `lms_users` ADD COLUMN `last_login_at` timestamp NULL DEFAULT NULL");
    }

    // Check password_hash nullable
    $pdo->exec("ALTER TABLE `lms_users` MODIFY COLUMN `password_hash` varchar(255) DEFAULT NULL");

    // Check status enum includes blocked
    $pdo->exec("ALTER TABLE `lms_users` MODIFY COLUMN `status` enum('active','blocked','suspended') NOT NULL DEFAULT 'active'");

    // Find existing user by supabase_uid or email
    $stmt = $pdo->prepare("SELECT id, supabase_uid, name, email, role, batch_id, status FROM lms_users WHERE supabase_uid = ? OR email = ? LIMIT 1");
    $stmt->execute([$supabase_uid, $email]);
    $existing = $stmt->fetch();

    $user_id = null;
    $role = 'student';
    $batch_id = $selected_batch_id;

    if ($existing) {
        if ($existing['status'] === 'blocked' || $existing['status'] === 'suspended') {
            json_res(['error' => 'Your account is suspended. Please contact support.'], 403);
        }

        $user_id = (int)$existing['id'];
        $role = $existing['role'];
        $batch_id = $existing['batch_id'] ? (int)$existing['batch_id'] : $selected_batch_id;

        // Auto-elevate to admin if in bootstrap list
        if ($is_bootstrap_admin && $role !== 'admin') {
            $role = 'admin';
        }

        $upd = $pdo->prepare("UPDATE lms_users SET supabase_uid = ?, name = ?, email = ?, role = ?, last_login_at = NOW() WHERE id = ?");
        $upd->execute([$supabase_uid, $name, $email, $role, $user_id]);
    } else {
        $role = $is_bootstrap_admin ? 'admin' : 'student';
        $ins = $pdo->prepare("INSERT INTO lms_users (supabase_uid, name, email, password_hash, role, batch_id, status, created_at, last_login_at) VALUES (?, ?, ?, NULL, ?, ?, 'active', NOW(), NOW())");
        $ins->execute([$supabase_uid, $name, $email, $role, $batch_id]);
        $user_id = (int)$pdo->lastInsertId();
    }

    // 9. Single Active Session Check for Students (Phase 2 Concurrency Gate)
    if ($role === 'student') {
        // Ensure table lms_active_session exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `lms_active_session` (
            `user_id` int(11) NOT NULL,
            `session_id` varchar(128) NOT NULL,
            `ip` varchar(64) NOT NULL,
            `user_agent` text DEFAULT NULL,
            `last_seen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`user_id`),
            KEY `idx_last_seen` (`last_seen`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->beginTransaction();
        $sess_stmt = $pdo->prepare("SELECT session_id, ip, last_seen FROM lms_active_session WHERE user_id = ? FOR UPDATE");
        $sess_stmt->execute([$user_id]);
        $active_row = $sess_stmt->fetch();

        $current_time = time();
        $session_clash = false;

        if ($active_row) {
            $last_seen_time = strtotime($active_row['last_seen']);
            $diff = $current_time - $last_seen_time;
            // Active on another device within the last 150 seconds
            if ($diff < 150 && $active_row['session_id'] !== session_id()) {
                $session_clash = true;
            }
        }

        if ($session_clash) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode([
                'error' => 'This account is active on another device. Sign out there, or try again in 2 minutes.'
            ]);
            exit;
        }

        // Regenerate PHP session id
        session_regenerate_id(true);
        $new_session_id = session_id();

        // Upsert active session lock
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $lock_stmt = $pdo->prepare("REPLACE INTO lms_active_session (user_id, session_id, ip, user_agent, last_seen) VALUES (?, ?, ?, ?, NOW())");
        $lock_stmt->execute([$user_id, $new_session_id, $client_ip, $ua]);

        $pdo->commit();
    } else {
        session_regenerate_id(true);
    }

    // 10. Establish PHP Session State
    $_SESSION['auth_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['lms_user'] = [
        'id'           => $user_id,
        'supabase_uid' => $supabase_uid,
        'name'         => $name,
        'email'        => $email,
        'role'         => $role,
        'batch_id'     => $batch_id
    ];

    lms_audit_log($user_id, 'user_login', 'lms_users', $user_id, [
        'method' => 'supabase',
        'role' => $role
    ]);

    // Determine redirect destination
    $redirect = '/student/index.php';
    if ($role === 'admin') {
        $redirect = '/admin/index.php';
    } elseif ($role === 'teacher') {
        $redirect = '/teacher/index.php';
    }

    json_res([
        'success'  => true,
        'redirect' => $redirect,
        'role'     => $role,
        'user'     => [
            'id'    => $user_id,
            'name'  => $name,
            'email' => $email
        ]
    ]);

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Session setup error: " . $e->getMessage());
    json_res(['error' => 'Failed to initialize authenticated session: ' . $e->getMessage()], 500);
}
