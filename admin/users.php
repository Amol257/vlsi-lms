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

// Handle batch reassignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    require_csrf();
    $uid = intval($_POST['user_id'] ?? 0);
    $bid = intval($_POST['batch_id'] ?? 0);
    if ($uid && $bid) {
        $stmt = $pdo->prepare("UPDATE lms_users SET batch_id = ? WHERE id = ?");
        $stmt->execute([$bid, $uid]);
        lms_audit_log($admin['id'], 'update_user_batch', 'lms_users', $uid, ['batch_id' => $bid]);
        $notice = 'Cohort batch updated successfully.';
    }
}

$users = [];
$batches = [];
if ($pdo) {
    try {
        $batches = $pdo->query("SELECT id, code, name FROM lms_batches ORDER BY id ASC")->fetchAll();
        $stmt = $pdo->prepare("SELECT u.*, b.code as batch_code, b.name as batch_name, s.last_seen as active_last_seen, s.ip as active_ip 
            FROM lms_users u 
            LEFT JOIN lms_batches b ON u.batch_id = b.id 
            LEFT JOIN lms_active_session s ON u.id = s.user_id 
            WHERE u.role = ? 
            ORDER BY u.id DESC");
        $stmt->execute([$role]);
        $users = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error loading users: " . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<title><?= ucfirst($role) ?>s Roster - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="role-admin">
<div class="shell">
  <?php 
$active_tab = 'students';
include __DIR__ . '/../includes/sidebar_admin.php'; 
?>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
      <h1><?= ucfirst($role) ?>s Directory</h1>
    </header>

    <main class="content">
      <?php if (!empty($notice)): ?>
        <div style="background: #ecfdf5; color: #047857; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px;">
          <?= h($notice) ?>
        </div>
      <?php endif; ?>

      <section class="card flush">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>User Name</th>
                <th>Email Address</th>
                <th>Role</th>
                <th>Assigned Cohort Batch</th>
                <th>Active Session</th>
                <th class="right">Actions</th>
              </tr>
            </thead>
            <tbody>
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
                  <td><b><?= h($u['name']) ?></b></td>
                  <td><code><?= h($u['email']) ?></code></td>
                  <td><span class="badge <?= $u['role'] === 'teacher' ? 'badge-blue' : 'badge-green' ?>"><?= strtoupper($u['role']) ?></span></td>
                  <td>
                    <b><?= h($u['batch_code'] ?? 'Unassigned') ?></b>
                    <div class="small muted"><?= h($u['batch_name'] ?? 'None') ?></div>
                  </td>
                  <td>
                    <?php if ($u['role'] === 'student'): ?>
                      <?php if ($is_online): ?>
                        <span class="badge badge-green" title="IP: <?= h($u['active_ip'] ?? '') ?>">Online</span>
                      <?php elseif (!empty($u['active_last_seen'])): ?>
                        <span class="badge badge-gray" title="Last seen: <?= h($u['active_last_seen']) ?>">Device Lock</span>
                      <?php else: ?>
                        <span class="small muted">Idle</span>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="small muted">Exempt</span>
                    <?php endif; ?>
                  </td>
                  <td class="right" style="white-space: nowrap;">
                    <form method="post" class="inline" style="display: inline-block;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <select name="batch_id" onchange="this.form.submit()" style="padding: 4px 8px; font-size: 12px; border-radius: 6px;">
                        <option value="">Change Cohort...</option>
                        <?php foreach ($batches as $b): ?>
                          <option value="<?= (int)$b['id'] ?>"><?= h($b['code']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </form>
                    <?php if ($u['role'] === 'student' && !empty($u['active_last_seen'])): ?>
                      <button type="button" class="btn btn-sm btn-outline" style="margin-left: 6px; padding: 4px 8px; font-size: 12px;" onclick="resetActiveSession(<?= (int)$u['id'] ?>, '<?= h($u['name']) ?>')">Reset Lock</button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</div>

<script src="/assets/app.js"></script>
<script>
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
