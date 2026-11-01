<?php
require_once __DIR__ . '/../db.php';
$student = require_role(['student', 'admin', 'teacher']);
$pdo = get_db();

$batch = null;
$course = null;
$continue_lec = null;
$announcements = [];
$lectures_count = 0;

if ($pdo) {
    // Student batch
    $bid = $student['batch_id'] ?? 1;
    $stmt = $pdo->prepare("SELECT b.*, c.title as course_title, c.code as course_code 
        FROM lms_batches b 
        JOIN lms_courses c ON b.course_id = c.id 
        WHERE b.id = ?");
    $stmt->execute([$bid]);
    $batch = $stmt->fetch();

    if ($batch) {
        $lectures_count = $pdo->query("SELECT COUNT(*) FROM lms_lectures WHERE batch_id = {$batch['id']} AND status = 'published'")->fetchColumn();
    }

    // Continue Watching lecture
    $stmt = $pdo->prepare("SELECT p.*, l.title as lecture_title, l.teacher_name 
        FROM lms_progress p 
        JOIN lms_lectures l ON p.lecture_id = l.id 
        WHERE p.user_id = ? AND p.is_completed = 0 
        ORDER BY p.last_viewed_at DESC LIMIT 1");
    $stmt->execute([$student['id']]);
    $continue_lec = $stmt->fetch();

    // Announcements
    $stmt = $pdo->prepare("SELECT * FROM lms_announcements WHERE batch_id = ? OR batch_id IS NULL ORDER BY id DESC LIMIT 5");
    $stmt->execute([$bid]);
    $announcements = $stmt->fetchAll();
} else {
    $batch = [
        'id' => 1,
        'code' => 'PD-NOV-2026-WKND',
        'name' => 'PD Weekend Batch (Nov 2026)',
        'course_title' => 'Physical Design & Tapeout',
        'course_code' => 'PD',
        'schedule' => 'Sat/Sun 10 AM - 1 PM IST'
    ];
    $lectures_count = 2;
    $continue_lec = [
        'lecture_id' => 1,
        'lecture_title' => 'Lec 01: Advanced Floorplanning & Macro Placement Strategy',
        'teacher_name' => 'Sanjay Deshmukh',
        'percent_watched' => 79,
        'position_seconds' => 2850
    ];
    $announcements = [
        ['title' => 'Live Lab Session this Saturday 11 AM IST', 'content' => 'We will be debugging signoff timing violations on the 28nm RISC-V SoC core. Please have QuestaSim or EDA Playground open.', 'created_at' => date('Y-m-d')]
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Student Learning Portal · VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="role-student">
<div class="shell">
  <aside class="sidebar">
    <a class="brand" href="index.php">
      <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/></svg></span>
      <span><b>VLSI LMS</b><small>Student Classroom</small></span>
    </a>
    <nav>
      <a href="index.php" class="on">
        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        <span>My Batches</span>
      </a>
      <a href="player.php">
        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        <span>Lecture Player</span>
      </a>
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
      <h1>Student Dashboard</h1>
      <div class="top-user">
        <div class="who">
          <span class="avatar">S</span>
          <span class="who-name"><?= htmlspecialchars($student['name']) ?></span>
        </div>
      </div>
    </header>

    <main class="content">
      <!-- CONTINUE WATCHING BANNER -->
      <?php if ($continue_lec): ?>
        <section class="card attention" style="margin-bottom: 24px;">
          <div class="course-hero">
            <div>
              <span class="badge badge-amber" style="margin-bottom: 8px;">Resume Lecture</span>
              <h2 style="margin-bottom: 6px;"><?= htmlspecialchars($continue_lec['lecture_title']) ?></h2>
              <p class="small muted" style="margin: 0;"><?= htmlspecialchars($continue_lec['teacher_name']) ?> · <?= round($continue_lec['position_seconds']/60) ?>m streamed</p>
            </div>
            <div class="hero-progress">
              <b><?= $continue_lec['percent_watched'] ?>%</b>
              <span>Watched</span>
              <div class="progress-bar-wrap" style="margin-top: 8px; height: 8px;">
                <div class="fill" style="width: <?= $continue_lec['percent_watched'] ?>%;"></div>
              </div>
              <a href="player.php?lecture=<?= $continue_lec['lecture_id'] ?>" class="btn btn-primary btn-sm" style="margin-top: 10px;">▶ Resume Watching</a>
            </div>
          </div>
        </section>
      <?php endif; ?>

      <!-- ENROLLED COHORTS -->
      <h2>My Enrolled Program Cohort</h2>
      <div class="grid-2" style="margin-bottom: 24px;">
        <div class="card class-card">
          <div class="card-head">
            <span class="badge badge-green">Enrolled &amp; Active</span>
            <span class="badge badge-grey"><?= htmlspecialchars($batch['course_code']) ?></span>
          </div>
          <h3><?= htmlspecialchars($batch['course_title']) ?></h3>
          <p class="muted small"><?= htmlspecialchars($batch['name']) ?></p>
          <div class="mini-stats">
            <span>📅 <?= htmlspecialchars($batch['schedule']) ?></span>
            <span>🎥 <?= $lectures_count ?> Recorded Lectures</span>
          </div>
          <div style="margin-top: 10px;">
            <a href="player.php" class="btn btn-primary btn-block">Enter Classroom &amp; Lectures →</a>
          </div>
        </div>
      </div>

      <!-- ANNOUNCEMENTS -->
      <h2>Cohort Announcements &amp; Live Lab Updates</h2>
      <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php foreach ($announcements as $a): ?>
          <div class="card" style="margin-bottom: 0;">
            <div class="card-head" style="margin-bottom: 6px;">
              <b>📢 <?= htmlspecialchars($a['title']) ?></b>
              <span class="small muted"><?= substr($a['created_at'], 0, 10) ?></span>
            </div>
            <p class="small" style="color: var(--ink-2); margin: 0; white-space: pre-line;"><?= htmlspecialchars($a['content']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </main>
  </div>
</div>
<script src="../assets/app.js"></script>
</body>
</html>
