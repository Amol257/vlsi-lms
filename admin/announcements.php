<?php
require_once __DIR__ . '/../db.php';
$admin = require_role(['admin']);
$pdo = get_db();

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    require_csrf();
    $batch_id = !empty($_POST['batch_id']) ? intval($_POST['batch_id']) : null;
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title && $content) {
        $stmt = $pdo->prepare("INSERT INTO lms_announcements (batch_id, title, content, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$batch_id, $title, $content]);
        $ann_id = (int)$pdo->lastInsertId();

        // Also broadcast to notifications table
        try {
            $pdo->prepare("INSERT INTO notifications (user_id, title, body, created_at) VALUES (NULL, ?, ?, NOW())")
                ->execute([$title, $content]);
        } catch (PDOException $e) {}

        lms_audit_log($admin['id'], 'broadcast_announcement', 'lms_announcements', $ann_id, [
            'title' => $title,
            'batch_id' => $batch_id
        ]);
        $notice = 'Announcement broadcasted successfully.';
    } else {
        $error = 'Please provide both title and content.';
    }
}

$announcements = [];
$batches = [];
if ($pdo) {
    try {
        $batches = $pdo->query("SELECT id, code, name FROM lms_batches ORDER BY id ASC")->fetchAll();
        $stmt = $pdo->query("SELECT a.*, b.code as batch_code 
            FROM lms_announcements a 
            LEFT JOIN lms_batches b ON a.batch_id = b.id 
            ORDER BY a.id DESC");
        $announcements = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Announcements load error: " . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Announcements - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="role-admin">
<div class="shell">
  <?php 
$active_tab = 'announcements';
include __DIR__ . '/../includes/sidebar_admin.php'; 
?>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
      <h1>Broadcast Announcements</h1>
    </header>

    <main class="content">
      <?php if (!empty($notice)): ?>
        <div style="background: #ecfdf5; color: #047857; padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; font-weight: 500;">
          <?= h($notice) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div style="background: var(--red-soft); color: var(--red); padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; font-weight: 500;">
          <?= h($error) ?>
        </div>
      <?php endif; ?>

      <section class="card" style="margin-bottom: 24px;">
        <h2 style="margin-top: 0; font-size: 18px;">Post New Cohort Announcement</h2>
        <form method="post" class="form">
          <?= csrf_field() ?>
          <label>Announcement Headline
            <input type="text" name="title" required placeholder="e.g. Live Signoff Timing Lab Session this Saturday">
          </label>

          <label>Target Cohort Batch
            <select name="batch_id">
              <option value="">Broadcast to All Cohorts</option>
              <?php foreach ($batches as $b): ?>
                <option value="<?= (int)$b['id'] ?>"><?= h($b['code']) ?> (<?= h($b['name']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </label>

          <label>Detailed Message Content
            <textarea name="content" rows="4" required placeholder="Write instructions, meeting prerequisites, or syllabus milestones..."></textarea>
          </label>

          <button type="submit" class="btn btn-primary" style="margin-top: 10px;">Broadcast Announcement</button>
        </form>
      </section>

      <section class="card flush">
        <div class="card-head pad">
          <h2 style="margin: 0; font-size: 18px;">Recent Broadcasts (<?= count($announcements) ?>)</h2>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Target</th>
                <th>Headline</th>
                <th>Content Summary</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($announcements as $a): ?>
                <tr>
                  <td><code><?= date('M j, Y', strtotime($a['created_at'])) ?></code></td>
                  <td>
                    <?php if (!empty($a['batch_code'])): ?>
                      <span class="badge badge-blue"><?= h($a['batch_code']) ?></span>
                    <?php else: ?>
                      <span class="badge badge-green">All Cohorts</span>
                    <?php endif; ?>
                  </td>
                  <td><b><?= h($a['title']) ?></b></td>
                  <td class="small muted" style="max-width: 360px;"><?= h($a['content']) ?></td>
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
</body>
</html>
