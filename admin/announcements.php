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
      <a href="/admin/announcements.php" class="on"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><span>Announcements</span></a>
      <a href="/admin/audit.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg><span>Audit Log</span></a>
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
