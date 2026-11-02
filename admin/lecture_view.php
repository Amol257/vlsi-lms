<?php
require_once __DIR__ . '/../db.php';
$admin = require_role(['admin']);
$pdo = get_db();

$id = intval($_GET['id'] ?? 1);

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    require_csrf();
    $new_status = $_POST['status'] ?? '';
    if (in_array($new_status, ['published', 'draft', 'rejected'])) {
        $stmt = $pdo->prepare("UPDATE lms_lectures SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $id]);

        lms_audit_log($admin['id'], 'update_lecture_status', 'lms_lectures', $id, [
            'status' => $new_status,
            'title' => $lec['title'] ?? 'Lecture'
        ]);
    }
    header("Location: lecture_view.php?id=$id");
    exit;
}

$lec = null;
$views = [];
if ($pdo) {
    $stmt = $pdo->prepare("SELECT l.*, b.name as batch_name, s.name as subject_name 
        FROM lms_lectures l 
        JOIN lms_batches b ON l.batch_id = b.id 
        JOIN lms_subjects s ON l.subject_id = s.id 
        WHERE l.id = ?");
    $stmt->execute([$id]);
    $lec = $stmt->fetch();

    if ($lec) {
        $stmt = $pdo->prepare("SELECT p.*, u.name as student_name, u.email as student_email 
            FROM lms_progress p 
            JOIN lms_users u ON p.user_id = u.id 
            WHERE p.lecture_id = ? 
            ORDER BY p.last_viewed_at DESC");
        $stmt->execute([$id]);
        $views = $stmt->fetchAll();
    }
}

if (!$lec) {
    $lec = [
        'id' => $id,
        'title' => 'Lec 01: Advanced Floorplanning & Macro Placement Strategy',
        'description' => 'Comprehensive walkthrough of core utilization, keep-out margins, channel estimation, and optimal flyline routing for multi-macro ASICs.',
        'teacher_name' => 'Sanjay Deshmukh',
        'subject_name' => 'Floorplanning & Power Grid Synthesis',
        'batch_name' => 'PD Weekend Batch (Nov 2026)',
        'video_url' => 'https://www.youtube.com/embed/gOo57e69_B8',
        'notes_url' => 'https://vlsiinsights.com/docs/PD_Floorplan_Notes.pdf',
        'lab_url' => 'https://vlsiinsights.com/labs/macro_placement_lab.tar.gz',
        'status' => 'published'
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Review: <?= htmlspecialchars($lec['title']) ?> - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="../assets/style.css">
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
      <a href="users.php?role=student"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2 .6 3.5 2.6 3.5 6"/></svg><span>Students</span></a>
      <a href="users.php?role=teacher"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="7" r="4"/><path d="M5.5 21c0-4 3.5-7 6.5-7s6.5 3 6.5 7"/></svg><span>Teachers</span></a>
      <a href="batches.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg><span>Batches</span></a>
      <a href="courses.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg><span>Courses &amp; Subjects</span></a>
      <a href="lectures.php" class="on"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="5 3 19 12 5 21 5 3"/></svg><span>Lectures</span></a>
      <a href="enrolments.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg><span>Enrolments</span></a>
      <a href="announcements.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><span>Announcements</span></a>
      <a href="reports.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><span>Reports</span></a>
      <a href="audit.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg><span>Audit Log</span></a>
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
      <h1>Review Lecture</h1>
      <div class="top-user">
        <a href="lectures.php" class="btn btn-sm">← Back to Lectures</a>
      </div>
    </header>

    <main class="content">
      <div class="grid-side align-start">
        <div>
          <div class="player-wrap">
            <iframe src="<?= htmlspecialchars($lec['video_url']) ?>" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
          </div>

          <div class="card">
            <div class="card-head">
              <div>
                <?php 
                  $bCls = ($lec['status'] === 'published') ? 'badge-green' : (($lec['status'] === 'submitted') ? 'badge-amber' : 'badge-grey');
                ?>
                <span class="badge <?= $bCls ?>"><?= strtoupper($lec['status']) ?></span>
                <h2 style="margin-top: 6px; font-size: 18px;"><?= htmlspecialchars($lec['title']) ?></h2>
              </div>
              <div style="display: flex; gap: 8px;">
                <?php if ($lec['status'] === 'submitted' || $lec['status'] === 'draft'): ?>
                  <form method="post" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="published">
                    <button class="btn btn-primary" type="submit">✓ Approve &amp; Publish</button>
                  </form>
                  <form method="post" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="rejected">
                    <button class="btn btn-danger btn-sm" type="submit">Reject</button>
                  </form>
                <?php else: ?>
                  <form method="post" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="draft">
                    <button class="btn btn-sm" type="submit">Unpublish to Draft</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>

            <p style="color: var(--ink-2); margin-bottom: 16px;"><?= nl2br(htmlspecialchars($lec['description'])) ?></p>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; padding: 14px 0; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line);">
              <div>
                <small class="muted" style="display: block;">Instructor</small>
                <b><?= htmlspecialchars($lec['teacher_name']) ?></b>
              </div>
              <div>
                <small class="muted" style="display: block;">Subject / Module</small>
                <b><?= htmlspecialchars($lec['subject_name']) ?></b>
              </div>
              <div>
                <small class="muted" style="display: block;">Cohort Batch</small>
                <b><?= htmlspecialchars($lec['batch_name']) ?></b>
              </div>
            </div>

            <div style="margin-top: 16px;">
              <h4>Attached Class Materials</h4>
              <ul class="files">
                <?php if ($lec['notes_url']): ?>
                  <li>
                    <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <a href="<?= htmlspecialchars($lec['notes_url']) ?>" target="_blank">Class Notes (PDF)</a>
                  </li>
                <?php endif; ?>
                <?php if ($lec['lab_url']): ?>
                  <li>
                    <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <a href="<?= htmlspecialchars($lec['lab_url']) ?>" target="_blank">Lab Exercises &amp; Verilog Testbenches (.tar.gz)</a>
                  </li>
                <?php endif; ?>
                <?php if (!$lec['notes_url'] && !$lec['lab_url']): ?>
                  <li class="small muted">No files attached to this lecture.</li>
                <?php endif; ?>
              </ul>
            </div>
          </div>
        </div>

        <!-- RIGHT: STUDENT VIEWS -->
        <div>
          <div class="card">
            <div class="card-head">
              <h3>Student Viewer Analytics</h3>
            </div>
            <div style="display: flex; flex-direction: column; gap: 12px;">
              <?php if (empty($views)): ?>
                <p class="small muted">No student viewing logs recorded yet.</p>
              <?php else: ?>
                <?php foreach ($views as $v): ?>
                  <div style="padding: 10px; border-radius: 8px; background: #f8fafc; border: 1px solid var(--line);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                      <b><?= htmlspecialchars($v['student_name']) ?></b>
                      <span class="badge <?= $v['is_completed'] ? 'badge-green' : 'badge-blue' ?>"><?= $v['percent_watched'] ?>%</span>
                    </div>
                    <div class="progress-bar-wrap" style="margin-bottom: 6px;">
                      <div class="fill" style="width: <?= $v['percent_watched'] ?>%;"></div>
                    </div>
                    <small class="muted">Last viewed: <?= substr($v['last_viewed_at'], 0, 16) ?></small>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
<script src="../assets/app.js"></script>
</body>
</html>
