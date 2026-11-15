<?php
require_once __DIR__ . '/../db.php';
$student = require_role(['student', 'admin', 'teacher']);

// Redirect legacy player routes to the secure HLS video player
header("Location: /student/watch.php", true, 302);
exit;
