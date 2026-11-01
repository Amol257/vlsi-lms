<?php
require_once __DIR__ . '/../db.php';
$teacher = require_role(['teacher', 'admin']);
$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
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

        // Audit log
        $pdo->prepare("INSERT INTO lms_audit_logs (actor, action, details) VALUES (?, 'Uploaded Lecture', ?)")
            ->execute([$teacher['email'], "$title submitted for admin review"]);

        header("Location: index.php");
        exit;
    }
}

$batches = [];
$subjects = [];
if ($pdo) {
    $batches = $pdo->query("SELECT id, code, name FROM lms_batches ORDER BY id ASC")->fetchAll();
    $subjects = $pdo->query("SELECT id, name, course_id FROM lms_subjects ORDER BY id ASC")->fetchAll();
} else {
    $batches = [['id' => 1, 'code' => 'PD-NOV-2026-WKND', 'name' => 'PD Weekend']];
    $subjects = [['id' => 1, 'name' => 'Floorplanning & Power Grid Synthesis']];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Upload Lecture · VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="role-teacher">
<div class="shell">
  <aside class="sidebar">
    <a class="brand" href="index.php">
      <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/></svg></span>
      <span><b>VLSI LMS</b><small>Instructor Portal</small></span>
    </a>
    <nav>
      <a href="index.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span>My Lectures</span></a>
      <a href="upload.php" class="on"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg><span>Upload / Submit Lecture</span></a>
    </nav>
    <div class="sidebar-footer">
      <a href="../logout.php" style="display: flex; align-items: center; gap: 8px; color: #f87171; font-weight: 500;">
        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span>Sign Out</span>
      </a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
      <h1>Upload &amp; Submit Lecture</h1>
      <div class="top-user">
        <a href="index.php" class="btn btn-sm">Cancel</a>
      </div>
    </header>

    <main class="content">
      <div class="card narrow">
        <form method="post" class="form">
          <label>Lecture Title
            <input type="text" name="title" required placeholder="e.g. Lec 04: Multi-Corner Multi-Mode (MCMM) Setup">
          </label>

          <div class="grid-2">
            <label>Target Cohort Batch
              <select name="batch_id" required>
                <?php foreach ($batches as $b): ?>
                  <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['code']) ?> (<?= htmlspecialchars($b['name']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Curriculum Subject / Module
              <select name="subject_id" required>
                <?php foreach ($subjects as $s): ?>
                  <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>

          <label>Video URL / Embed Link (YouTube, Vimeo, Cloud Storage or MP4)
            <input type="text" name="video_url" required placeholder="https://www.youtube.com/embed/gOo57e69_B8" value="https://www.youtube.com/embed/gOo57e69_B8">
          </label>

          <div class="grid-2">
            <label>Estimated Duration (Minutes)
              <input type="number" name="duration" value="60" min="1" required>
            </label>
            <label>Class Notes URL (PDF or Markdown)
              <input type="text" name="notes_url" placeholder="https://domain.com/notes.pdf">
            </label>
          </div>

          <label>Lab Exercises &amp; Verilog Testbenches Archive (Optional)
            <input type="text" name="lab_url" placeholder="https://domain.com/lab.tar.gz">
          </label>

          <label>Detailed Lecture Description
            <textarea name="description" rows="4" placeholder="Brief outline of concepts covered, prerequisite slides, and lab assignments..."></textarea>
          </label>

          <div style="margin-top: 20px; display: flex; gap: 12px; align-items: center;">
            <button class="btn btn-primary" type="submit">Submit Lecture for Admin Review</button>
            <span class="small muted">Administrators will verify the recording before publishing to students.</span>
          </div>
        </form>
      </div>
    </main>
  </div>
</div>
<script src="../assets/app.js"></script>
</body>
</html>
