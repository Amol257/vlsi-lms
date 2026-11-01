<?php
require_once __DIR__ . '/db.php';
$user = auth_user();
if (!$user) {
    header("Location: login.php");
    exit;
}
if ($user['role'] === 'admin') {
    header("Location: admin/index.php");
} elseif ($user['role'] === 'teacher') {
    header("Location: teacher/index.php");
} else {
    header("Location: student/index.php");
}
exit;
