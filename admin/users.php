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
  <aside class="sidebar">
    <a class="brand" href="index.php">
      <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/></svg></span>
      <span><b>VLSI LMS</b><small>Hostinger MySQL</small></span>
    </a>
    <nav>
      <a href="index.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span>Dashboard</span></a>
      <a href="users.php?role=student" class="<?= $role === 'student' ? 'on' : '' ?>"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2 .6 3.5 2.6 3.5 6"/></svg><span>Students</span></a>
      <a href="users.php?role=teacher" class="<?= $role === 'teacher' ? 'on' : '' ?>"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="7" r="4"/><path d="M5.5 21c0-4 3.5-7 6.5-7s6.5 3 6.5 7"/></svg><span>Teachers</span></a>
      <a href="batches.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg><span>Batches</span></a>
      <a href="courses.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg><span>Courses &amp; Subjects</span></a>
      <a href="lectures.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="5 3 19 12 5 21 5 3"/></svg><span>Lectures</span></a>
      <a href="enrolments.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg><span>Enrolments</span></a>
      <a href="announcements.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><span>Announcements</span></a>
      <a href="reports.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><span>Reports</span></a>
      <a href="audit.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg><span>Audit Log</span></a>
    </nav>
    <div class="sidebar-footer">
      <a href="/logout.php" style="display: flex; align-items: center; gap: 8px; color: #f87171; font-weight: 500;">
        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span>Sign Out</span>
      </a>
    </div>
  </aside>

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
