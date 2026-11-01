<?php
require_once __DIR__ . '/../db.php';
$admin = require_role(['admin']);
$pdo = get_db();

$logs = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT a.*, u.name as actor_name, u.email as actor_email 
            FROM audit_log a 
            LEFT JOIN lms_users u ON a.actor_id = u.id 
            ORDER BY a.id DESC LIMIT 100");
        $logs = $stmt->fetchAll();
    } catch (PDOException $e) {
        try {
            $stmt = $pdo->query("SELECT id, actor as actor_email, action, details, created_at FROM lms_audit_logs ORDER BY id DESC LIMIT 100");
            $logs = $stmt->fetchAll();
        } catch (PDOException $ex) {
            error_log("Audit fetch error: " . $ex->getMessage());
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Audit Log - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="role-admin">
<div class="shell">
  <aside class="sidebar">
    <a class="brand" href="/admin/index.php">
      <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/></svg></span>
      <span><b>VLSI LMS</b><small>Hostinger MySQL</small></span>
    </a>
    <nav>
      <a href="/admin/index.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span>Dashboard</span></a>
      <a href="/admin/videos.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="5 3 19 12 5 21 5 3"/></svg><span>Video Library</span></a>
      <a href="/admin/users.php?role=student"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2 .6 3.5 2.6 3.5 6"/></svg><span>Students</span></a>
      <a href="/admin/courses.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg><span>Courses &amp; Subjects</span></a>
      <a href="/admin/webinars.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 10l5-5v14l-5-5M4 6h11a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/></svg><span>Webinars</span></a>
      <a href="/admin/enrolments.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg><span>Enrolments</span></a>
      <a href="/admin/audit.php" class="on"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg><span>Audit Log</span></a>
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
      <h1>Security &amp; Action Audit Log</h1>
    </header>

    <main class="content">
      <section class="card flush">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Timestamp</th>
                <th>Actor</th>
                <th>Action</th>
                <th>Entity</th>
                <th>IP Address</th>
                <th>Metadata</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($logs)): ?>
                <tr><td colspan="6" style="text-align: center; color: var(--muted); padding: 24px;">No audit events recorded yet.</td></tr>
              <?php else: ?>
                <?php foreach ($logs as $l): ?>
                  <tr>
                    <td class="small font-mono muted"><?= h(substr($l['created_at'], 0, 19)) ?></td>
                    <td>
                      <?php if (!empty($l['actor_name'])): ?>
                        <b><?= h($l['actor_name']) ?></b>
                        <div class="small muted"><?= h($l['actor_email']) ?></div>
                      <?php else: ?>
                        <code><?= h($l['actor_email'] ?? $l['actor'] ?? 'System') ?></code>
                      <?php endif; ?>
                    </td>
                    <td><span class="badge badge-gray"><?= h($l['action']) ?></span></td>
                    <td>
                      <?= h($l['entity'] ?? 'General') ?>
                      <?php if (!empty($l['entity_id'])): ?>
                        <code>#<?= (int)$l['entity_id'] ?></code>
                      <?php endif; ?>
                    </td>
                    <td><code><?= h($l['ip'] ?? '-') ?></code></td>
                    <td class="small muted" style="max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                      <?= h($l['meta'] ?? $l['details'] ?? '') ?>
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
<script src="/assets/app.js"></script>
</body>
</html>
