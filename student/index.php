<?php
require_once __DIR__ . '/../db.php';
$student = require_role(['student', 'admin', 'teacher']);
$pdo = get_db();

$batch = null;
$course = null;
$continue_lec = null;
$announcements = [];
$free_lectures = [];
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
        $lec_stmt = $pdo->prepare("SELECT COUNT(*) FROM lms_lectures WHERE batch_id = ? AND status = 'published'");
        $lec_stmt->execute([$batch['id']]);
        $lectures_count = (int)$lec_stmt->fetchColumn();
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

    // Approved Free Preview Lectures (AES-128 HLS + DRM-alternative protected)
    try {
        $fl_stmt = $pdo->query("SELECT v.id, v.title, v.description, v.duration_sec, p.title AS program_title
            FROM videos v
            JOIN programs p ON v.program_id = p.id
            WHERE v.is_trial = 1 AND v.status = 'approved'
            ORDER BY v.id DESC");
        $free_lectures = $fl_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Free lectures fetch error: " . $e->getMessage());
    }
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
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<title>Student Learning Portal - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="role-student">
<div class="shell">
  <?php 
  $active_tab = 'batches';
  include __DIR__ . '/../includes/sidebar_student.php'; 
  ?>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
      <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div>
          <h1 style="margin: 0; font-size: 20px;">Welcome back, <?= h($student['name']) ?></h1>
          <span class="small muted">Student Portal</span>
        </div>
        <div>
          <span class="badge badge-green">Enrolled Cohort</span>
        </div>
      </div>
    </header>

    <main class="content">
      <!-- RESUME WATCHING HERO -->
      <?php if ($continue_lec): ?>
        <section class="card resume-hero" style="margin-bottom: 24px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; padding: 24px; border-radius: 12px;">
          <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div style="max-width: 600px;">
              <span class="badge" style="background: rgba(255,255,255,0.15); color: #fff; margin-bottom: 8px;">Continue Watching</span>
              <h2 style="color: #fff; margin: 4px 0 8px 0; font-size: 20px;"><?= h($continue_lec['lecture_title']) ?></h2>
              <p style="color: #94a3b8; margin: 0; font-size: 14px;">Instructor: <?= h($continue_lec['teacher_name']) ?> | Resume at <?= gmdate("i:s", $continue_lec['position_seconds']) ?></p>
            </div>
            <div style="min-width: 180px;">
              <span>Watched <?= (int)$continue_lec['percent_watched'] ?>%</span>
              <div class="progress-bar-wrap" style="margin-top: 8px; height: 8px; background: rgba(255,255,255,0.2); border-radius: 4px; overflow: hidden;">
                <div class="fill" style="width: <?= (int)$continue_lec['percent_watched'] ?>%; background: #38bdf8; height: 100%;"></div>
              </div>
              <a href="/student/player.php?lecture=<?= (int)$continue_lec['lecture_id'] ?>" class="btn btn-primary btn-sm" style="margin-top: 10px; display: inline-block;">Resume Watching</a>
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
            <span class="badge badge-grey"><?= h($batch['course_code'] ?? 'PD') ?></span>
          </div>
          <h3><?= h($batch['course_title'] ?? 'Physical Design') ?></h3>
          <p class="muted small"><?= h($batch['name'] ?? 'Cohort') ?></p>
          <div class="mini-stats">
            <span>📅 <?= h($batch['schedule'] ?? 'Sat/Sun 10 AM - 1 PM IST') ?></span>
            <span>🎥 <?= $lectures_count ?> Recorded Lectures</span>
          </div>
          <div style="margin-top: 10px;">
            <a href="/student/player.php" class="btn btn-primary btn-block">Enter Classroom &amp; Lectures</a>
          </div>
        </div>
      </div>

      <!-- FREE LECTURES (PROTECTED STREAM) -->
      <?php if (!empty($free_lectures)): ?>
      <h2>Free Lectures (Protected Preview)</h2>
      <div class="grid-2" style="margin-bottom: 24px;">
        <?php foreach ($free_lectures as $fl): ?>
          <div class="card class-card">
            <div class="card-head">
              <span class="badge badge-blue">Free Lecture</span>
              <span class="badge badge-grey"><?= round(((int)$fl['duration_sec']) / 60) ?>m</span>
            </div>
            <h3><?= h($fl['title']) ?></h3>
            <p class="muted small"><?= h($fl['program_title']) ?></p>
            <?php if (!empty($fl['description'])): ?>
              <p class="small" style="color: var(--ink-2); margin: 8px 0 12px 0; line-height: 1.5;"><?= h($fl['description']) ?></p>
            <?php endif; ?>
            <div style="margin-top: 10px;">
              <a href="/student/watch.php?id=<?= (int)$fl['id'] ?>" class="btn btn-primary btn-block">Watch Free Lecture</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- ANNOUNCEMENTS -->
      <h2>Cohort Announcements &amp; Live Lab Updates</h2>
      <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php foreach ($announcements as $a): ?>
          <div class="card" style="margin-bottom: 0;">
            <div class="card-head" style="margin-bottom: 6px;">
              <b>📢 <?= h($a['title']) ?></b>
              <span class="small muted"><?= substr($a['created_at'], 0, 10) ?></span>
            </div>
            <p class="small" style="color: var(--ink-2); margin: 0; white-space: pre-line;"><?= h($a['content']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </main>
  </div>
</div>
<script src="/assets/app.js"></script>
<script src="/assets/heartbeat.js"></script>
</body>
</html>
