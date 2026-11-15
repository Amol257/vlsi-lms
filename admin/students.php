<?php
require_once __DIR__ . '/../db.php';
$admin = require_role(['admin']);
header("Location: /admin/users.php?role=student", true, 302);
exit;
