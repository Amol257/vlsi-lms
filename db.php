<?php
/**
 * VLSI LMS : Database and Authentication Facade
 * Delegates bootstrap and configuration to includes/bootstrap.php.
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Auth User Helper
function auth_user() {
    if (!isset($_SESSION['lms_user'])) {
        return null;
    }

    $user = $_SESSION['lms_user'];

    // For students, enforce single active session heartbeat check
    if (($user['role'] ?? '') === 'student') {
        $pdo = get_db();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT session_id FROM lms_active_session WHERE user_id = ? LIMIT 1");
                $stmt->execute([$user['id']]);
                $active = $stmt->fetch();
                $current_sid = session_id();

                if ($active && $active['session_id'] !== $current_sid) {
                    // Session displaced by another login
                    session_unset();
                    session_destroy();
                    return null;
                }
            } catch (PDOException $e) {
                // Table might not exist yet during initial installation
            }
        }
    }

    return $user;
}

// Role Enforcement Helper
function require_role($allowed_roles = []) {
    $user = auth_user();
    if (!$user) {
        $current_url = $_SERVER['REQUEST_URI'] ?? '/';
        header("Location: /login.php?redirect=" . urlencode($current_url));
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
