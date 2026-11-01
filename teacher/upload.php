<?php
require_once __DIR__ . '/../db.php';
$teacher = require_role(['teacher', 'admin']);
$pdo = get_db();

$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    require_csrf();
    $title     = trim($_POST['title'] ?? '');
    $batch_id  = intval($_POST['batch_id'] ?? 1);
    $sub_id    = intval($_POST['subject_id'] ?? 1);
    $video_url = trim($_POST['video_url'] ?? '');
    $duration  = intval($_POST['duration'] ?? 60) * 60;
    $notes_url = !empty($_POST['notes_url']) ? trim($_POST['notes_url']) : null;
    $lab_url   = !empty($_POST['lab_url']) ? trim($_POST['lab_url']) : null;
    $desc      = trim($_POST['description'] ?? '');

    if ($title && $video_url) {
        $stmt = $pdo->prepare("INSERT INTO lms_lectures 
            (batch_id, subject_id, teacher_id, teacher_name, title, description, video_type, video_url, duration_seconds, notes_url, lab_url, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'youtube', ?, ?, ?, ?, 'submitted')");
        $stmt->execute([$batch_id, $sub_id, $teacher['id'], $teacher['name'], $title, $desc, $video_url, $duration, $notes_url, $lab_url]);
        $new_lec_id = (int)$pdo->lastInsertId();

        lms_audit_log($teacher['id'], 'upload_lecture', 'lms_lectures', $new_lec_id, [
            'title' => $title,
            'batch_id' => $batch_id
        ]);

        header("Location: /teacher/index.php?notice=uploaded");
        exit;
    } else {
        $error = 'Lecture title and video URL are required.';
    }
}

$batches = [];
$subjects = [];
if ($pdo) {
    try {
        $batches = $pdo->query("SELECT id, code, name FROM lms_batches ORDER BY id ASC")->fetchAll();
        $subjects = $pdo->query("SELECT id, name, course_id FROM lms_subjects ORDER BY id ASC")->fetchAll();
    } catch (PDOException $e) {
        error_log("Upload page fetch error: " . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Upload Lecture - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="role-teacher">
<div class="shell">
  <aside class="sidebar">
    <a class="brand" href="/teacher/index.php">
      <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/></svg></span>
      <span><b>VLSI LMS</b><small>Instructor Portal</small></span>
    </a>
    <nav>
      <a href="/teacher/index.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span>My Lectures</span></a>
      <a href="/teacher/upload.php" class="on"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg><span>Upload / Submit Lecture</span></a>
      <a href="/teacher/submissions.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg><span>Review Submissions</span></a>
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
      <h1>Upload Recorded Lecture</h1>
      <div class="top-user">
        <span class="who-name"><?= h($teacher['name']) ?></span>
      </div>
    </header>

    <main class="content">
      <?php if (!empty($error)): ?>
        <div style="background: var(--red-soft); color: var(--red); padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; font-weight: 500;">
          <?= h($error) ?>
        </div>
      <?php endif; ?>

      <section class="card">
        <form method="post" class="form">
          <?= csrf_field() ?>
          <label>Lecture Title
            <input type="text" name="title" required placeholder="e.g. Lec 04: Clock Tree Routing and Skew Minimization">
          </label>

          <div class="grid-2">
            <label>Assigned Batch
              <select name="batch_id" required>
                <?php foreach ($batches as $b): ?>
                  <option value="<?= (int)$b['id'] ?>"><?= h($b['code']) ?> (<?= h($b['name']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </label>

            <label>Subject / Curriculum Module
              <select name="subject_id" required>
                <?php foreach ($subjects as $s): ?>
                  <option value="<?= (int)$s['id'] ?>"><?= h($s['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>

          <label>Video Stream URL (Embed or Direct)
            <input type="url" name="video_url" required placeholder="https://www.youtube.com/embed/... or direct MP4/HLS stream URL">
          </label>

          <div class="grid-2">
            <label>Duration (in minutes)
              <input type="number" name="duration" value="60" min="1" required>
            </label>

            <label>Class Notes URL (PDF)
              <input type="url" name="notes_url" placeholder="https://vlsiinsights.com/docs/...pdf">
            </label>
          </div>

          <label>Lab Testbench URL (.tar.gz, .zip)
            <input type="url" name="lab_url" placeholder="https://vlsiinsights.com/labs/...tar.gz">
          </label>

          <label>Lecture Description &amp; Topics Covered
            <textarea name="description" rows="4" placeholder="Brief outline of concepts covered, SDC files analyzed, and signoff checkpoints..."></textarea>
          </label>

          <button type="submit" class="btn btn-primary" style="margin-top: 10px;">Submit Lecture for Review</button>
        </form>
      </section>
    </main>
  </div>
</div>
<script src="/assets/app.js"></script>
</body>
</html>
