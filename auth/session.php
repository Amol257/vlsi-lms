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

// 2. IP Rate Limiting on Session Exchange (300 requests per 5 minutes per IP to allow lab/hostel networks)
$client_ip = get_client_ip();
if (!check_rate_limit('session_ip', $client_ip, 300, 300)) {
    json_res(['error' => 'Too many sign in requests from this network. Please wait 5 minutes.'], 429);
}

// 3. Extract Bearer / Access Token
$access_token = trim($_POST['access_token'] ?? '');
if (empty($access_token)) {
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

// 6. Rate Limit on Email Hash (10 sign-ins per 5 minutes per email)
$email_hash = hash('sha256', $email);
if (!check_rate_limit('session_email', $email_hash, 10, 300)) {
    json_res(['error' => 'Too many sign-in attempts for this account. Please wait 5 minutes.'], 429);
}

// 7. Extract and Sanitize User Name (cap at 100 characters, strip control characters)
// Note: batch_id from user_metadata is intentionally rejected and never read to prevent client privilege escalation.
$user_meta = $user_data['user_metadata'] ?? [];
$name = trim($user_meta['full_name'] ?? $user_meta['name'] ?? '');
if (empty($name)) {
    $parts = explode('@', $email);
    $name = ucwords(str_replace(['.', '_', '-'], ' ', $parts[0]));
}
$name = preg_replace('/[\x00-\x1F\x7F]/u', '', mb_substr($name, 0, 100));

// 8. Admin Bootstrap Check (Elevate ONLY when email confirmation is verified)
$bootstrap_emails = lms_config('app.admin_bootstrap_emails', []);
if (is_string($bootstrap_emails)) {
    $bootstrap_emails = array_map('trim', explode(',', $bootstrap_emails));
}
$is_bootstrap_admin = $email_confirmed && in_array($email, array_map('strtolower', $bootstrap_emails), true);

// 9. Database Lookup and User Upsert
$pdo = get_db();
if (!$pdo) {
    json_res(['error' => 'Database connection failed. Please contact administrator.'], 500);
}

try {
    // Lookup by supabase_uid first
    $stmt = $pdo->prepare("SELECT id, supabase_uid, name, email, role, batch_id, status FROM lms_users WHERE supabase_uid = ? LIMIT 1");
    $stmt->execute([$supabase_uid]);
    $existing = $stmt->fetch();

    // If not found by supabase_uid, check by email
    if (!$existing) {
        $stmt = $pdo->prepare("SELECT id, supabase_uid, name, email, role, batch_id, status FROM lms_users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        // If found by email with a different registered supabase_uid, reject mapping conflict
        if ($existing && !empty($existing['supabase_uid']) && $existing['supabase_uid'] !== $supabase_uid) {
            lms_audit_log(0, 'account_mapping_conflict', 'lms_users', (int)$existing['id'], [
                'email' => $email,
                'stored_uid' => $existing['supabase_uid'],
                'incoming_uid' => $supabase_uid,
                'ip' => $client_ip
            ]);
            json_res(['error' => 'Your account credentials have changed. Please contact your administrator to sync your account.'], 403);
        }
    }

    $user_id = null;
    $role = 'student';
    $batch_id = null;

    if ($existing) {
        // Enforce active status strictly (any status other than active is denied)
        if ($existing['status'] !== 'active') {
            json_res(['error' => 'Your account is suspended. Please contact support.'], 403);
        }

        $user_id = (int)$existing['id'];
        $role = $existing['role'];
        $batch_id = $existing['batch_id'] ? (int)$existing['batch_id'] : null;

        // Auto-elevate to admin if in bootstrap list and email was verified
        if ($is_bootstrap_admin && $role !== 'admin') {
            $role = 'admin';
        }

        $upd = $pdo->prepare("UPDATE lms_users SET supabase_uid = ?, name = ?, email = ?, role = ?, last_login_at = NOW() WHERE id = ?");
        $upd->execute([$supabase_uid, $name, $email, $role, $user_id]);
    } else {
        $role = $is_bootstrap_admin ? 'admin' : 'student';
        $batch_id = null; // New users strictly receive NULL batch_id; batches are assigned by admins only

        $ins = $pdo->prepare("INSERT INTO lms_users (supabase_uid, name, email, password_hash, role, batch_id, status, created_at, last_login_at) VALUES (?, ?, ?, NULL, ?, NULL, 'active', NOW(), NOW())");
        $ins->execute([$supabase_uid, $name, $email, $role]);
        $user_id = (int)$pdo->lastInsertId();
    }

    // 10. Single Active Session Check for Students (Phase 2 Concurrency Gate)
    // Uses TIMESTAMPDIFF(SECOND, last_seen, NOW()) in MySQL to avoid PHP/MySQL timezone skew
    if ($role === 'student') {
        $pdo->beginTransaction();
        $sess_stmt = $pdo->prepare("SELECT session_id, ip, last_seen, TIMESTAMPDIFF(SECOND, last_seen, NOW()) AS diff_sec FROM lms_active_session WHERE user_id = ? FOR UPDATE");
        $sess_stmt->execute([$user_id]);
        $active_row = $sess_stmt->fetch();

        $session_clash = false;
        if ($active_row) {
            $diff_sec = isset($active_row['diff_sec']) ? (int)$active_row['diff_sec'] : 9999;
            if ($diff_sec < 150 && $active_row['session_id'] !== session_id()) {
                $session_clash = true;
            }
        }

        if ($session_clash) {
            $pdo->rollBack();
            lms_audit_log($user_id, 'session_clash_409', 'lms_active_session', $user_id, [
                'ip' => $client_ip,
                'active_ip' => $active_row['ip'] ?? ''
            ]);
            http_response_code(409);
            echo json_encode([
                'error' => 'This account is active on another device. Sign out there, or try again in 2 minutes.'
            ]);
            exit;
        }

        session_regenerate_id(true);
        $new_session_id = session_id();

        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $lock_stmt = $pdo->prepare("REPLACE INTO lms_active_session (user_id, session_id, ip, user_agent, last_seen) VALUES (?, ?, ?, ?, NOW())");
        $lock_stmt->execute([$user_id, $new_session_id, $client_ip, $ua]);

        $pdo->commit();
    } else {
        session_regenerate_id(true);
    }

    // 11. Establish PHP Session State
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

} catch (\Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Session setup error: " . $e->getMessage());
    json_res(['error' => 'An unexpected authentication error occurred. Please try again later.'], 500);
}
