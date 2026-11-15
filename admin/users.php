<?php
require_once __DIR__ . '/../db.php';
$admin = require_role(['admin']);
$pdo = get_db();

$role = $_GET['role'] ?? 'student';
if (!in_array($role, ['student', 'teacher'], true)) {
    $role = 'student';
}

$notice = '';
$error = '';

// Handle POST actions: add_user, edit_user, delete_user, update_batch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    require_csrf();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'add_user') {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $target_role = in_array($_POST['role'] ?? '', ['student', 'teacher', 'admin'], true) ? $_POST['role'] : $role;
        $batch_id = intval($_POST['batch_id'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['active', 'blocked'], true) ? $_POST['status'] : 'active';
        $password = trim($_POST['password'] ?? '');

        if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Valid student name and email address are required.';
        } else {
            try {
                $check = $pdo->prepare("SELECT id FROM lms_users WHERE email = ? LIMIT 1");
                $check->execute([$email]);
                if ($check->fetch()) {
                    $error = "A user with email {$email} already exists.";
                } else {
                    $pwd_hash = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;
                    $ins = $pdo->prepare("INSERT INTO lms_users (name, email, password_hash, role, batch_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $ins->execute([
                        $name,
                        $email,
                        $pwd_hash,
                        $target_role,
                        $batch_id > 0 ? $batch_id : null,
                        $status
                    ]);
                    $new_id = (int)$pdo->lastInsertId();

                    lms_audit_log($admin['id'], 'create_user', 'lms_users', $new_id, [
                        'name' => $name,
                        'email' => $email,
                        'role' => $target_role,
                        'batch_id' => $batch_id > 0 ? $batch_id : null,
                        'status' => $status
                    ]);

                    $notice = ucfirst($target_role) . " {$name} created successfully.";
                }
            } catch (PDOException $e) {
                $error = 'Failed to create user: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'edit_user') {
        $uid = intval($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $target_role = in_array($_POST['role'] ?? '', ['student', 'teacher', 'admin'], true) ? $_POST['role'] : 'student';
        $batch_id = intval($_POST['batch_id'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['active', 'blocked'], true) ? $_POST['status'] : 'active';
        $new_password = trim($_POST['new_password'] ?? '');

        if ($uid <= 0 || empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Valid user ID, name, and email address are required.';
        } else {
            try {
                $check = $pdo->prepare("SELECT id FROM lms_users WHERE email = ? AND id != ? LIMIT 1");
                $check->execute([$email, $uid]);
                if ($check->fetch()) {
                    $error = "Another user is already registered with email {$email}.";
                } else {
                    if (!empty($new_password)) {
                        $pwd_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $upd = $pdo->prepare("UPDATE lms_users SET name = ?, email = ?, role = ?, batch_id = ?, status = ?, password_hash = ? WHERE id = ?");
                        $upd->execute([
                            $name,
                            $email,
                            $target_role,
                            $batch_id > 0 ? $batch_id : null,
                            $status,
                            $pwd_hash,
                            $uid
                        ]);
                    } else {
                        $upd = $pdo->prepare("UPDATE lms_users SET name = ?, email = ?, role = ?, batch_id = ?, status = ? WHERE id = ?");
                        $upd->execute([
                            $name,
                            $email,
                            $target_role,
                            $batch_id > 0 ? $batch_id : null,
                            $status,
                            $uid
                        ]);
                    }

                    // If account was suspended, terminate active session immediately
                    if ($status === 'blocked') {
                        $pdo->prepare("DELETE FROM lms_active_session WHERE user_id = ?")->execute([$uid]);
                    }

                    lms_audit_log($admin['id'], 'edit_user', 'lms_users', $uid, [
                        'name' => $name,
                        'email' => $email,
                        'role' => $target_role,
                        'batch_id' => $batch_id > 0 ? $batch_id : null,
                        'status' => $status
                    ]);

                    $notice = "User {$name} updated successfully.";
                }
            } catch (PDOException $e) {
                $error = 'Failed to update user: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_user') {
        $uid = intval($_POST['user_id'] ?? 0);
        if ($uid <= 0) {
            $error = 'Invalid user ID.';
        } elseif ($uid === (int)$admin['id']) {
            $error = 'You cannot delete your own administrative account.';
        } else {
            try {
                $find = $pdo->prepare("SELECT id, name, email, role FROM lms_users WHERE id = ? LIMIT 1");
                $find->execute([$uid]);
                $target = $find->fetch();

                if ($target) {
                    $pdo->prepare("DELETE FROM lms_active_session WHERE user_id = ?")->execute([$uid]);
                    $pdo->prepare("DELETE FROM lms_users WHERE id = ?")->execute([$uid]);

                    lms_audit_log($admin['id'], 'delete_user', 'lms_users', $uid, [
                        'name' => $target['name'],
                        'email' => $target['email'],
                        'role' => $target['role']
                    ]);

                    $notice = ucfirst($target['role']) . " {$target['name']} deleted successfully.";
                } else {
                    $error = 'User not found.';
                }
            } catch (PDOException $e) {
                $error = 'Failed to delete user: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update_batch' || (!empty($_POST['user_id']) && isset($_POST['batch_id']))) {
        $uid = intval($_POST['user_id'] ?? 0);
        $raw_batch = $_POST['batch_id'] ?? '';
        $bid = ($raw_batch === '' || $raw_batch === '0' || $raw_batch === 'none') ? null : intval($raw_batch);

        if ($uid > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE lms_users SET batch_id = ? WHERE id = ?");
                $stmt->execute([$bid, $uid]);

                lms_audit_log($admin['id'], 'update_user_batch', 'lms_users', $uid, ['batch_id' => $bid]);
                $notice = 'Cohort batch updated successfully.';
            } catch (PDOException $e) {
                $error = 'Failed to update batch: ' . $e->getMessage();
            }
        }
    }
}

// Search and filter parameters
$search = trim($_GET['search'] ?? '');
$filter_batch = trim($_GET['batch'] ?? '');
$filter_status = trim($_GET['status'] ?? '');

$users = [];
$batches = [];

if ($pdo) {
    try {
        $batches = $pdo->query("SELECT id, code, name FROM lms_batches ORDER BY id ASC")->fetchAll();

        $query = "SELECT u.*, b.code as batch_code, b.name as batch_name, 
                         s.last_seen as active_last_seen, s.ip as active_ip 
                  FROM lms_users u 
                  LEFT JOIN lms_batches b ON u.batch_id = b.id 
                  LEFT JOIN lms_active_session s ON u.id = s.user_id 
                  WHERE u.role = ?";
        $params = [$role];

        if ($search !== '') {
            $query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filter_batch !== '') {
            if ($filter_batch === 'unassigned') {
                $query .= " AND u.batch_id IS NULL";
            } else {
                $query .= " AND u.batch_id = ?";
                $params[] = intval($filter_batch);
            }
        }

        if ($filter_status !== '') {
            $query .= " AND u.status = ?";
            $params[] = $filter_status;
        }

        $query .= " ORDER BY u.id DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error loading users: " . $e->getMessage());
    }
}

$active_tab = ($role === 'teacher') ? 'teachers' : 'students';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<title><?= ucfirst($role) ?>s Management - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/style.css">
<style>
/* Modal overlay and card styling */
.modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.65);
  backdrop-filter: blur(4px);
  z-index: 1000;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.modal-overlay.open {
  display: flex;
}
.modal-dialog {
  background: #ffffff;
  border-radius: 12px;
  width: 100%;
  max-width: 520px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--line, #e2e8f0);
  overflow: hidden;
  animation: modalFadeIn 0.18s ease-out;
}
@keyframes modalFadeIn {
  from { opacity: 0; transform: scale(0.97) translateY(6px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--line, #e2e8f0);
  background: #f8fafc;
}
.modal-head h3 {
  margin: 0;
  font-size: 17px;
  font-weight: 700;
  color: #0f172a;
}
.modal-close {
  background: transparent;
  border: none;
  font-size: 22px;
  line-height: 1;
  color: #64748b;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 6px;
}
.modal-close:hover {
  background: #e2e8f0;
  color: #0f172a;
}
.modal-body {
  padding: 20px;
}
.modal-foot {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid var(--line, #e2e8f0);
  background: #f8fafc;
}
.form-row {
  margin-bottom: 14px;
}
.form-row label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 6px;
  color: #334155;
}
.form-row input, .form-row select {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 14px;
  background: #ffffff;
}
.form-row input:focus, .form-row select:focus {
  border-color: var(--brand, #0284c7);
  outline: none;
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
}
.form-hint {
  font-size: 11.5px;
  color: #64748b;
  margin-top: 4px;
  display: block;
}
.filter-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  margin-bottom: 16px;
}
.filter-toolbar .search-box {
  flex: 1;
  min-width: 220px;
}
.filter-toolbar input, .filter-toolbar select {
  padding: 8px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 13px;
  background: #ffffff;
}
.action-btns {
  display: flex;
  align-items: center;
  gap: 6px;
}
</style>
</head>
<body class="role-admin">
<div class="shell">
  <?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
      <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <h1><?= ucfirst($role) ?>s Management</h1>
        <button type="button" class="btn btn-primary btn-sm" onclick="openAddModal()">
          + Add New <?= ucfirst($role) ?>
        </button>
      </div>
    </header>

    <main class="content">
      <?php if (!empty($notice)): ?>
        <div style="background: #ecfdf5; color: #047857; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; border: 1px solid #a7f3d0; font-weight: 500;">
          <?= h($notice) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div style="background: #fef2f2; color: #b91c1c; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; border: 1px solid #fecaca; font-weight: 500;">
          <?= h($error) ?>
        </div>
      <?php endif; ?>

      <!-- FILTER & SEARCH TOOLBAR -->
      <form method="get" class="filter-toolbar">
        <input type="hidden" name="role" value="<?= h($role) ?>">
        <div class="search-box">
          <input type="text" name="search" value="<?= h($search) ?>" placeholder="Search by name or email..." style="width: 100%;">
        </div>
        <div>
          <select name="batch" onchange="this.form.submit()">
            <option value="">All Batches</option>
            <option value="unassigned" <?= $filter_batch === 'unassigned' ? 'selected' : '' ?>>Unassigned (No Batch)</option>
            <?php foreach ($batches as $b): ?>
              <option value="<?= (int)$b['id'] ?>" <?= $filter_batch === (string)$b['id'] ? 'selected' : '' ?>>
                <?= h($b['code']) ?> (<?= h($b['name']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <select name="status" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active Only</option>
            <option value="blocked" <?= $filter_status === 'blocked' ? 'selected' : '' ?>>Suspended / Blocked</option>
          </select>
        </div>
        <button type="submit" class="btn btn-sm">Filter</button>
        <?php if ($search !== '' || $filter_batch !== '' || $filter_status !== ''): ?>
          <a href="/admin/users.php?role=<?= h($role) ?>" class="btn btn-sm btn-outline">Reset</a>
        <?php endif; ?>
      </form>

      <!-- USERS TABLE -->
      <section class="card flush">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>User Details</th>
                <th>Role &amp; Status</th>
                <th>Assigned Batch</th>
                <th>Active Session</th>
                <th class="right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($users)): ?>
                <tr>
                  <td colspan="5" style="text-align: center; padding: 32px; color: #64748b;">
                    No <?= h($role) ?>s found matching the specified criteria.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($users as $u): ?>
                  <?php
                  $is_online = false;
                  if (!empty($u['active_last_seen'])) {
                      $diff = time() - strtotime($u['active_last_seen']);
                      if ($diff < 150) {
                          $is_online = true;
                      }
                  }
                  ?>
                  <tr>
                    <td>
                      <b><?= h($u['name']) ?></b>
                      <div class="small muted"><code><?= h($u['email']) ?></code></div>
                    </td>
                    <td>
                      <span class="badge <?= $u['role'] === 'teacher' ? 'badge-blue' : ($u['role'] === 'admin' ? 'badge-amber' : 'badge-green') ?>">
                        <?= strtoupper($u['role']) ?>
                      </span>
                      <?php if (($u['status'] ?? 'active') === 'blocked'): ?>
                        <span class="badge badge-red" style="margin-left: 4px;">SUSPENDED</span>
                      <?php else: ?>
                        <span class="badge badge-green" style="margin-left: 4px;">ACTIVE</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <form method="post" style="display: inline-block;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_batch">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <select name="batch_id" onchange="this.form.submit()" style="padding: 4px 8px; font-size: 12px; border-radius: 6px; border: 1px solid #cbd5e1; max-width: 190px;">
                          <option value="0" <?= empty($u['batch_id']) ? 'selected' : '' ?>>Unassigned (None)</option>
                          <?php foreach ($batches as $b): ?>
                            <option value="<?= (int)$b['id'] ?>" <?= ((int)($u['batch_id'] ?? 0) === (int)$b['id']) ? 'selected' : '' ?>>
                              <?= h($b['code']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </form>
                      <?php if (!empty($u['batch_name'])): ?>
                        <div class="small muted" style="font-size: 11px; margin-top: 2px;"><?= h($u['batch_name']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($u['role'] === 'student'): ?>
                        <?php if ($is_online): ?>
                          <span class="badge badge-green" title="IP: <?= h($u['active_ip'] ?? '') ?>">Online</span>
                        <?php elseif (!empty($u['active_last_seen'])): ?>
                          <span class="badge badge-grey" title="Last seen: <?= h($u['active_last_seen']) ?>">Device Lock</span>
                        <?php else: ?>
                          <span class="small muted">Idle</span>
                        <?php endif; ?>

                        <?php if (!empty($u['active_last_seen'])): ?>
                          <button type="button" class="btn btn-sm btn-outline" style="margin-left: 4px; padding: 2px 6px; font-size: 11px;" onclick="resetActiveSession(<?= (int)$u['id'] ?>, '<?= h($u['name']) ?>')">Reset Lock</button>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="small muted">Exempt</span>
                      <?php endif; ?>
                    </td>
                    <td class="right" style="white-space: nowrap;">
                      <div class="action-btns" style="justify-content: flex-end;">
                        <button type="button" 
                                class="btn btn-sm btn-outline" 
                                onclick='openEditModal(<?= json_encode($u) ?>)'>
                          Edit
                        </button>
                        <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
                          <button type="button" 
                                  class="btn btn-sm btn-danger" 
                                  onclick="openDeleteModal(<?= (int)$u['id'] ?>, '<?= h(addslashes($u['name'])) ?>', '<?= h(addslashes($u['email'])) ?>')">
                            Delete
                          </button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</div>

<!-- ADD USER MODAL -->
<div id="addUserModal" class="modal-overlay" onclick="handleBackdropClick(event, 'addUserModal')">
  <div class="modal-dialog">
    <div class="modal-head">
      <h3>Add New <?= ucfirst($role) ?></h3>
      <button type="button" class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
    </div>
    <form method="post" action="users.php?role=<?= h($role) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_user">
      <input type="hidden" name="role" value="<?= h($role) ?>">
      <div class="modal-body">
        <div class="form-row">
          <label>Full Name *</label>
          <input type="text" name="name" placeholder="e.g. Rahul Sharma" required>
        </div>
        <div class="form-row">
          <label>Email Address *</label>
          <input type="email" name="email" placeholder="e.g. rahul.sharma@example.com" required>
        </div>
        <div class="form-row">
          <label>Assign Cohort Batch</label>
          <select name="batch_id">
            <option value="0">Unassigned (None)</option>
            <?php foreach ($batches as $b): ?>
              <option value="<?= (int)$b['id'] ?>"><?= h($b['code']) ?> - <?= h($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="form-hint">Assigning a batch gives the student immediate access to batch materials and lectures.</span>
        </div>
        <div class="form-row">
          <label>Account Status</label>
          <select name="status">
            <option value="active">Active (Can Login)</option>
            <option value="blocked">Suspended (Blocked)</option>
          </select>
        </div>
        <div class="form-row">
          <label>Initial Password (Optional)</label>
          <input type="password" name="password" placeholder="Leave blank if authenticating via Google or Magic Link">
          <span class="form-hint">Set a temporary password if the student will sign in using email and password.</span>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-sm" onclick="closeModal('addUserModal')">Cancel</button>
        <button type="submit" class="btn btn-sm btn-primary">Create <?= ucfirst($role) ?></button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT USER MODAL -->
<div id="editUserModal" class="modal-overlay" onclick="handleBackdropClick(event, 'editUserModal')">
  <div class="modal-dialog">
    <div class="modal-head">
      <h3>Edit User Details</h3>
      <button type="button" class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
    </div>
    <form method="post" action="users.php?role=<?= h($role) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="user_id" id="editUserId" value="">
      <div class="modal-body">
        <div class="form-row">
          <label>Full Name *</label>
          <input type="text" name="name" id="editName" required>
        </div>
        <div class="form-row">
          <label>Email Address *</label>
          <input type="email" name="email" id="editEmail" required>
        </div>
        <div class="form-row">
          <label>Assigned Role</label>
          <select name="role" id="editRole">
            <option value="student">Student</option>
            <option value="teacher">Teacher</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="form-row">
          <label>Assign Cohort Batch</label>
          <select name="batch_id" id="editBatchId">
            <option value="0">Unassigned (None)</option>
            <?php foreach ($batches as $b): ?>
              <option value="<?= (int)$b['id'] ?>"><?= h($b['code']) ?> - <?= h($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label>Account Status</label>
          <select name="status" id="editStatus">
            <option value="active">Active (Access Granted)</option>
            <option value="blocked">Suspended (Access Revoked)</option>
          </select>
        </div>
        <div class="form-row">
          <label>Reset Password (Optional)</label>
          <input type="password" name="new_password" placeholder="Leave empty to keep existing password">
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-sm" onclick="closeModal('editUserModal')">Cancel</button>
        <button type="submit" class="btn btn-sm btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- DELETE USER CONFIRMATION MODAL -->
<div id="deleteUserModal" class="modal-overlay" onclick="handleBackdropClick(event, 'deleteUserModal')">
  <div class="modal-dialog" style="max-width: 440px;">
    <div class="modal-head">
      <h3 style="color: #b91c1c;">Confirm Deletion</h3>
      <button type="button" class="modal-close" onclick="closeModal('deleteUserModal')">&times;</button>
    </div>
    <form method="post" action="users.php?role=<?= h($role) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_user">
      <input type="hidden" name="user_id" id="deleteUserId" value="">
      <div class="modal-body">
        <p style="margin-bottom: 12px; color: #1e293b; line-height: 1.5;">
          Are you sure you want to delete <b id="deleteUserName"></b> (<code id="deleteUserEmail"></code>)?
        </p>
        <p style="font-size: 12.5px; color: #64748b; line-height: 1.5; margin: 0;">
          This action is permanent and will remove the student's enrollments, viewing progress, and active device sessions.
        </p>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-sm" onclick="closeModal('deleteUserModal')">Cancel</button>
        <button type="submit" class="btn btn-sm btn-danger">Yes, Delete Student</button>
      </div>
    </form>
  </div>
</div>

<script src="/assets/app.js"></script>
<script>
function openAddModal() {
  document.getElementById('addUserModal').classList.add('open');
}

function openEditModal(user) {
  document.getElementById('editUserId').value = user.id;
  document.getElementById('editName').value = user.name || '';
  document.getElementById('editEmail').value = user.email || '';
  document.getElementById('editRole').value = user.role || 'student';
  document.getElementById('editBatchId').value = user.batch_id || 0;
  document.getElementById('editStatus').value = user.status || 'active';
  document.getElementById('editUserModal').classList.add('open');
}

function openDeleteModal(id, name, email) {
  document.getElementById('deleteUserId').value = id;
  document.getElementById('deleteUserName').textContent = name;
  document.getElementById('deleteUserEmail').textContent = email;
  document.getElementById('deleteUserModal').classList.add('open');
}

function closeModal(modalId) {
  const el = document.getElementById(modalId);
  if (el) el.classList.remove('open');
}

function handleBackdropClick(event, modalId) {
  if (event.target === document.getElementById(modalId)) {
    closeModal(modalId);
  }
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeModal('addUserModal');
    closeModal('editUserModal');
    closeModal('deleteUserModal');
  }
});

async function resetActiveSession(userId, userName) {
  if (!confirm('Reset the active session lock for ' + userName + '? This will allow them to sign in on a new device.')) {
    return;
  }
  const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  const formData = new FormData();
  formData.append('user_id', userId);
  formData.append('csrf_token', csrfToken);

  try {
    const res = await fetch('/api/admin_reset_session.php', {
      method: 'POST',
      body: formData,
      headers: { 'Accept': 'application/json' }
    });
    const data = await res.json();
    if (res.ok) {
      alert(data.message || 'Session lock reset successfully.');
      window.location.reload();
    } else {
      alert(data.error || 'Failed to reset session lock.');
    }
  } catch (err) {
    alert('Network error while resetting session.');
  }
}
</script>
</body>
</html>
