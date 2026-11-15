<?php
require_once __DIR__ . '/../db.php';
$admin = require_role(['admin']);
$pdo = get_db();

$error = '';
$notice = '';

$meet_regex = '/^https:\/\/meet\.google\.com\/[a-z]{3}-[a-z]{4}-[a-z]{3}(\?[\w=&%-]*)?$/';

// Handle Form Submissions: Create, Edit, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $starts_at = trim($_POST['starts_at'] ?? '');
        $meet_url = trim($_POST['meet_url'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($title) || empty($starts_at) || empty($meet_url)) {
            $error = 'Title, scheduled start time, and Google Meet link are required.';
        } elseif (!preg_match($meet_regex, $meet_url)) {
            $error = 'Invalid Google Meet URL format. Must match https://meet.google.com/xxx-yyyy-zzz';
        } else {
            $stmt = $pdo->prepare("INSERT INTO live_sessions (type, title, description, starts_at, meet_url, created_by, created_at) VALUES ('webinar', ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$title, $description, $starts_at, $meet_url, $admin['id']]);
            $new_id = (int)$pdo->lastInsertId();

            lms_audit_log($admin['id'], 'create_webinar', 'live_sessions', $new_id, [
                'title' => $title,
                'starts_at' => $starts_at,
                'meet_url' => $meet_url
            ]);
            $notice = 'Webinar created successfully.';
        }
    } elseif ($action === 'edit') {
        $webinar_id = (int)($_POST['webinar_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $starts_at = trim($_POST['starts_at'] ?? '');
        $meet_url = trim($_POST['meet_url'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($webinar_id <= 0 || empty($title) || empty($starts_at) || empty($meet_url)) {
            $error = 'All fields are required to update the webinar.';
        } elseif (!preg_match($meet_regex, $meet_url)) {
            $error = 'Invalid Google Meet URL format. Must match https://meet.google.com/xxx-yyyy-zzz';
        } else {
            $stmt = $pdo->prepare("UPDATE live_sessions SET title = ?, description = ?, starts_at = ?, meet_url = ? WHERE id = ? AND type = 'webinar'");
            $stmt->execute([$title, $description, $starts_at, $meet_url, $webinar_id]);

            lms_audit_log($admin['id'], 'edit_webinar', 'live_sessions', $webinar_id, [
                'title' => $title,
                'starts_at' => $starts_at,
                'meet_url' => $meet_url
            ]);
            $notice = 'Webinar updated successfully.';
        }
    } elseif ($action === 'delete') {
        $webinar_id = (int)($_POST['webinar_id'] ?? 0);
        if ($webinar_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM live_sessions WHERE id = ? AND type = 'webinar'");
            $stmt->execute([$webinar_id]);

            lms_audit_log($admin['id'], 'delete_webinar', 'live_sessions', $webinar_id, []);
            $notice = 'Webinar deleted successfully.';
        }
    }
}

// Fetch all scheduled webinars
$webinars = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM live_sessions WHERE type = 'webinar' ORDER BY starts_at DESC");
        $webinars = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to load webinars: " . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<title>Manage Webinars - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="role-admin">
<div class="shell">
  <?php 
$active_tab = 'webinars';
include __DIR__ . '/../includes/sidebar_admin.php'; 
?>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
      <h1>Manage Live Webinars</h1>
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

      <!-- CREATE NEW WEBINAR CARD -->
      <section class="card" style="margin-bottom: 24px;">
        <h2 style="margin-top: 0; font-size: 18px;">Schedule New Google Meet Webinar</h2>
        <form method="post" class="form" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="create">

          <label style="grid-column: span 2;">Webinar Title
            <input type="text" name="title" required placeholder="e.g. Masterclass: Advanced Signoff Timing and ECO Fixing">
          </label>

          <label>Scheduled Start Time (Asia/Kolkata IST, UTC+05:30)
            <input type="datetime-local" name="starts_at" required>
            <small class="muted" style="display:block; margin-top:4px;">Timezone: Indian Standard Time (Asia/Kolkata)</small>
          </label>

          <label>Google Meet URL (Strictly Validated)
            <input type="url" name="meet_url" required placeholder="https://meet.google.com/abc-defg-hij">
          </label>

          <label style="grid-column: span 2;">Description and Agenda (Optional)
            <textarea name="description" rows="3" placeholder="Overview of session objectives, prerequisites, and speaker background..."></textarea>
          </label>

          <div style="grid-column: span 2;">
            <button type="submit" class="btn btn-primary">Schedule Webinar</button>
          </div>
        </form>
      </section>

      <!-- EXISTING WEBINARS TABLE -->
      <section class="card flush">
        <div class="card-head pad">
          <h2 style="margin: 0; font-size: 18px;">Scheduled Webinars (<?= count($webinars) ?>)</h2>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Title &amp; Agenda</th>
                <th>Scheduled Start (IST)</th>
                <th>Google Meet Link</th>
                <th>Status</th>
                <th class="right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($webinars)): ?>
                <tr><td colspan="5" style="text-align: center; color: var(--muted); padding: 24px;">No webinars scheduled yet.</td></tr>
              <?php else: ?>
                <?php foreach ($webinars as $w): ?>
                  <?php
                    $diff = time() - strtotime($w['starts_at']);
                    $is_live = ($diff >= -600 && $diff <= 10800);
                  ?>
                  <tr>
                    <td>
                      <b><?= h($w['title']) ?></b>
                      <?php if (!empty($w['description'])): ?>
                        <div class="small muted" style="max-width: 380px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= h($w['description']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <code><?= date('M j, Y g:i A', strtotime($w['starts_at'])) ?> IST</code>
                    </td>
                    <td>
                      <a href="<?= h($w['meet_url']) ?>" target="_blank" style="color: var(--primary); font-family: monospace; font-size: 13px;">
                        <?= h($w['meet_url']) ?>
                      </a>
                    </td>
                    <td>
                      <?php if ($is_live): ?>
                        <span class="badge badge-green">LIVE NOW</span>
                      <?php elseif ($diff < -600): ?>
                        <span class="badge badge-blue">Scheduled</span>
                      <?php else: ?>
                        <span class="badge badge-gray">Concluded</span>
                      <?php endif; ?>
                    </td>
                    <td class="right" style="white-space: nowrap;">
                      <form method="post" style="display: inline-block;" onsubmit="return confirm('Delete this webinar session?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="webinar_id" value="<?= (int)$w['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline" style="color: var(--red); border-color: #fca5a5;">Delete</button>
                      </form>
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
