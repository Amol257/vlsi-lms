<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$raw_body = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_X_TIMESTAMP'] ?? ($_SERVER['HTTP_X_SIGNATURE_TIMESTAMP'] ?? '');
$secret = lms_config('enrollment.webhook_secret', '');

if (empty($secret)) {
    http_response_code(500);
    echo json_encode(['error' => 'Webhook secret not configured on server.']);
    exit;
}

if (empty($timestamp) || !is_numeric($timestamp)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid HTTP_X_TIMESTAMP header.']);
    exit;
}

// Reject timestamps older than 5 minutes (300 seconds) or excessive future skew
$now = time();
$ts = (int)$timestamp;
if (abs($now - $ts) > 300) {
    http_response_code(400);
    echo json_encode(['error' => 'Webhook timestamp expired or clock skew exceeded 5 minutes.']);
    exit;
}

if (empty($signature) || empty($raw_body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing signature or payload body.']);
    exit;
}

// Cryptographic signature check: sign timestamp . "." . body with HMAC-SHA256
$signed_payload = $timestamp . '.' . $raw_body;
$expected = hash_hmac('sha256', $signed_payload, $secret);

if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid cryptographic webhook signature.']);
    exit;
}

$data = json_decode($raw_body, true);
$email = strtolower(trim($data['user_email'] ?? ''));
$program_slug = trim($data['program_slug'] ?? '');
$source = trim($data['source'] ?? 'import');
$duration_days = (int)($data['duration_days'] ?? 180);

if (empty($email) || empty($program_slug)) {
    http_response_code(400);
    echo json_encode(['error' => 'user_email and program_slug are required.']);
    exit;
}

$pdo = get_db();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

try {
    // 1. Locate Program
    $prog_stmt = $pdo->prepare("SELECT id, title FROM programs WHERE slug = ? LIMIT 1");
    $prog_stmt->execute([$program_slug]);
    $program = $prog_stmt->fetch();

    if (!$program) {
        http_response_code(404);
        echo json_encode(['error' => "Program '{$program_slug}' not found."]);
        exit;
    }

    // 2. Locate or create Student User
    $user_stmt = $pdo->prepare("SELECT id, name FROM lms_users WHERE email = ? LIMIT 1");
    $user_stmt->execute([$email]);
    $user = $user_stmt->fetch();

    $user_id = null;
    if ($user) {
        $user_id = (int)$user['id'];
    } else {
        $name_fallback = ucwords(explode('@', $email)[0]);
        $ins_user = $pdo->prepare("INSERT INTO lms_users (name, email, role, status, created_at) VALUES (?, ?, 'student', 'active', NOW())");
        $ins_user->execute([$name_fallback, $email]);
        $user_id = (int)$pdo->lastInsertId();
    }

    // 3. Idempotent Grant Enrollment (UNIQUE user_id + program_id upsert)
    $expires_at = date('Y-m-d H:i:s', time() + ($duration_days * 86400));
    $enroll_stmt = $pdo->prepare("INSERT INTO enrollments (user_id, program_id, status, source, starts_at, expires_at) 
        VALUES (?, ?, 'active', ?, NOW(), ?) 
        ON DUPLICATE KEY UPDATE status = 'active', source = VALUES(source), expires_at = VALUES(expires_at)");
    $enroll_stmt->execute([$user_id, $program['id'], $source, $expires_at]);

    lms_audit_log($user_id, 'external_enroll_webhook', 'enrollments', $program['id'], [
        'email' => $email,
        'program' => $program['title'],
        'source' => $source
    ]);

    echo json_encode([
        'success' => true,
        'user_id' => $user_id,
        'program_id' => $program['id'],
        'expires_at' => $expires_at
    ]);
    exit;

} catch (Exception $e) {
    error_log("Enrollment webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error processing enrollment grant.']);
    exit;
}
