<?php
/**
 * VLSI LMS : Database and Authentication Facade
 * Delegates bootstrap and configuration to includes/bootstrap.php.
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Auth User Helper: Strict state validation, live status recheck, and single-session lock
function auth_user() {
    if (!isset($_SESSION['lms_user']) || empty($_SESSION['lms_user']['id'])) {
        return null;
    }

    $sess_user = $_SESSION['lms_user'];
    $user_id = (int)$sess_user['id'];
    $is_student = (($sess_user['role'] ?? '') === 'student');

    $pdo = get_db();
    if (!$pdo) {
        // Fail closed for authenticated sessions if database is unreachable
        return null;
    }

    try {
        if ($is_student) {
            $stmt = $pdo->prepare("SELECT u.id, u.status, u.role, u.name, u.email, u.batch_id, s.session_id, s.last_seen 
                FROM lms_users u 
                LEFT JOIN lms_active_session s ON u.id = s.user_id 
                WHERE u.id = ? LIMIT 1");
        } else {
            $stmt = $pdo->prepare("SELECT id, status, role, name, email, batch_id FROM lms_users WHERE id = ? LIMIT 1");
        }
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();

        if (!$row) {
            // User was removed from database
            session_unset();
            session_destroy();
            return null;
        }

        // Reject if status is anything other than 'active'
        if ($row['status'] !== 'active') {
            session_unset();
            session_destroy();
            return null;
        }

        // For students: strict active session enforcement (missing row or mismatched session id is immediately displaced)
        if ($is_student) {
            $current_sid = session_id();
            if (empty($row['session_id']) || $row['session_id'] !== $current_sid) {
                session_unset();
                session_destroy();
                return null;
            }
        }

        // Sync role changes if updated by administrator
        if ($row['role'] !== $sess_user['role']) {
            $_SESSION['lms_user']['role'] = $row['role'];
            $sess_user['role'] = $row['role'];
        }

        // Keep session metadata synced with database
        $_SESSION['lms_user']['name'] = $row['name'];
        $_SESSION['lms_user']['batch_id'] = $row['batch_id'] ? (int)$row['batch_id'] : null;

        return $_SESSION['lms_user'];

    } catch (PDOException $e) {
        error_log("auth_user check failed: " . $e->getMessage());
        return null;
    }
}

// Role Enforcement Helper with strict single-slash redirect validation
function require_role($allowed_roles = []) {
    $user = auth_user();
    if (!$user) {
        $raw_uri = $_SERVER['REQUEST_URI'] ?? '/';
        $safe_redirect = (str_starts_with($raw_uri, '/') && !str_starts_with($raw_uri, '//') && !str_contains($raw_uri, '://') && !str_contains($raw_uri, '\\')) 
            ? $raw_uri 
            : '/';
        header("Location: /login.php?redirect=" . urlencode($safe_redirect));
        exit;
    }

    if (!empty($allowed_roles) && !in_array($user['role'], $allowed_roles, true)) {
        if ($user['role'] === 'admin') {
            header("Location: /admin/index.php");
        } elseif ($user['role'] === 'teacher') {
            header("Location: /teacher/index.php");
        } else {
            header("Location: /student/index.php");
        }
        exit;
    }

    return $user;
}

// Audit Logging Helper
function lms_audit_log($actor_id, string $action, ?string $entity = null, $entity_id = null, array $meta = []): void {
    $pdo = get_db();
    if (!$pdo) {
        return;
    }

    $ip = get_client_ip();
    $meta_json = json_encode($meta);

    try {
        // Support audit_log (new phase schema) and fallback to lms_audit_logs if legacy
        $stmt = $pdo->prepare("INSERT INTO audit_log (actor_id, action, entity, entity_id, meta, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$actor_id, $action, $entity, $entity_id ? (int)$entity_id : null, $meta_json, $ip]);
    } catch (PDOException $e) {
        try {
            $legacy_actor = is_numeric($actor_id) ? "User #{$actor_id}" : (string)$actor_id;
            $legacy_details = $meta_json ?: ($entity ? "{$entity} #{$entity_id}" : '');
            $stmt = $pdo->prepare("INSERT INTO lms_audit_logs (actor, action, details) VALUES (?, ?, ?)");
            $stmt->execute([$legacy_actor, $action, $legacy_details]);
        } catch (PDOException $ex) {
            error_log("Audit log failed: " . $ex->getMessage());
        }
    }
}
