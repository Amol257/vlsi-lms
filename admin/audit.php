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
  <?php 
$active_tab = 'audit';
include __DIR__ . '/../includes/sidebar_admin.php'; 
?>

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
