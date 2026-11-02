<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    die("Not found.\n");
}

require_once __DIR__ . '/../db.php';
$pdo = get_db();
if (!$pdo) die("No DB\n");

echo "=== TESTING ALL PORTAL PAGES WITH SIMULATED SESSIONS ===\n\n";

// Function to simulate GET request to a PHP page with output buffering
function test_render_page(string $file, array $mock_user, array $get_params = []): array {
    $_GET = $get_params;
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_HOST'] = 'localhost:8000';
    $_SERVER['REQUEST_URI'] = '/' . $file . '?' . http_build_query($get_params);

    // Set session mock
    $_SESSION['lms_user'] = $mock_user;
    $_SESSION['last_activity'] = time();

    ob_start();
    $error = null;
    try {
        include __DIR__ . '/../' . $file;
    } catch (\Throwable $t) {
        $error = $t->getMessage() . " at " . $t->getFile() . ":" . $t->getLine();
    }
    $output = ob_get_clean();

    return [
        'error' => $error,
        'length' => strlen($output),
        'has_html' => strpos($output, '<html') !== false || strpos($output, '<!doctype') !== false
    ];
}

$admin_user = [
    'id' => 1,
    'supabase_uid' => 'f3e280ca-c0b3-41e5-b927-459c28de276f',
    'name' => 'Amol Singhal',
    'email' => 'amol.singhal25@gmail.com',
    'role' => 'admin',
    'status' => 'active'
];

$teacher_user = [
    'id' => 1,
    'supabase_uid' => 'f3e280ca-c0b3-41e5-b927-459c28de276f',
    'name' => 'Instructor User',
    'email' => 'teacher@vlsiinsights.com',
    'role' => 'teacher',
    'status' => 'active'
];

$student_user = [
    'id' => 1,
    'supabase_uid' => 'f3e280ca-c0b3-41e5-b927-459c28de276f',
    'name' => 'Student User',
    'email' => 'student@vlsiinsights.com',
    'role' => 'student',
    'status' => 'active'
];

// Ensure student has active session row in lms_active_session for student test
$sid = session_id();
$pdo->prepare("INSERT INTO lms_active_session (user_id, session_id, ip, user_agent, last_seen) VALUES (?, ?, '127.0.0.1', 'CLI', NOW()) ON DUPLICATE KEY UPDATE session_id = ?, last_seen = NOW()")->execute([1, $sid, $sid]);

$admin_pages = [
    'admin/index.php',
    'admin/users.php',
    'admin/batches.php',
    'admin/courses.php',
    'admin/lectures.php',
    'admin/lecture_view.php' => ['id' => 1],
    'admin/videos.php',
    'admin/webinars.php',
    'admin/enrolments.php',
    'admin/announcements.php',
    'admin/reports.php',
    'admin/audit.php',
    'admin/diagnostics_ip.php'
];

echo "1. TESTING ADMIN PAGES:\n";
foreach ($admin_pages as $page => $params) {
    $f = is_numeric($page) ? $params : $page;
    $p = is_array($params) ? $params : [];
    $res = test_render_page($f, $admin_user, $p);
    if ($res['error']) {
        echo "   [FAIL] $f: {$res['error']}\n";
    } else {
        echo "   [PASS] $f (Bytes: {$res['length']}, HTML: " . ($res['has_html'] ? 'YES' : 'NO') . ")\n";
    }
}

$teacher_pages = [
    'teacher/index.php',
    'teacher/upload.php',
    'teacher/submissions.php'
];

echo "\n2. TESTING TEACHER PAGES:\n";
foreach ($teacher_pages as $f) {
    $res = test_render_page($f, $teacher_user);
    if ($res['error']) {
        echo "   [FAIL] $f: {$res['error']}\n";
    } else {
        echo "   [PASS] $f (Bytes: {$res['length']}, HTML: " . ($res['has_html'] ? 'YES' : 'NO') . ")\n";
    }
}

$student_pages = [
    'student/index.php',
    'student/programs.php',
    'student/watch.php' => ['id' => 1],
    'student/assignments.php',
    'student/webinars.php'
];

echo "\n3. TESTING STUDENT PAGES:\n";
foreach ($student_pages as $page => $params) {
    $f = is_numeric($page) ? $params : $page;
    $p = is_array($params) ? $params : [];
    $res = test_render_page($f, $student_user, $p);
    if ($res['error']) {
        echo "   [FAIL] $f: {$res['error']}\n";
    } else {
        echo "   [PASS] $f (Bytes: {$res['length']}, HTML: " . ($res['has_html'] ? 'YES' : 'NO') . ")\n";
    }
}

echo "\nALL PAGES RENDER TEST COMPLETE.\n";
