<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$admin = require_role(['admin']);
require_csrf();

$target_user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
if ($target_user_id <= 0) {
    json_res(['error' => 'Invalid student user ID provided.'], 400);
}

$pdo = get_db();
if (!$pdo) {
    json_res(['error' => 'Database connection failed.'], 500);
}

try {
    $stmt = $pdo->prepare("DELETE FROM lms_active_session WHERE user_id = ?");
    $stmt->execute([$target_user_id]);
    $deleted = $stmt->rowCount();

    lms_audit_log($admin['id'], 'reset_active_session', 'lms_users', $target_user_id, [
        'reset_by' => $admin['email'],
        'cleared_rows' => $deleted
    ]);

    json_res([
        'success' => true,
        'message' => 'Active session reset successfully. The student can now log in on a new device.'
    ]);
} catch (PDOException $e) {
    error_log("Reset active session error: " . $e->getMessage());
    json_res(['error' => 'Failed to reset active session: ' . $e->getMessage()], 500);
}
