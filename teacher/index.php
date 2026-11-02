<?php
require_once __DIR__ . '/../db.php';
$teacher = require_role(['teacher', 'admin']);
$pdo = get_db();

$lectures = [];
$total_views = 0;
if ($pdo) {
    $stmt = $pdo->prepare("SELECT l.*, b.name as batch_name, s.name as subject_name 
        FROM lms_lectures l 
        JOIN lms_batches b ON l.batch_id = b.id 
        JOIN lms_subjects s ON l.subject_id = s.id 
        WHERE l.teacher_id = ? OR ? = 'admin' 
        ORDER BY l.id DESC");
    $stmt->execute([$teacher['id'], $teacher['role']]);
    $lectures = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lms_progress p 
        JOIN lms_lectures l ON p.lecture_id = l.id 
        WHERE l.teacher_id = ? OR ? = 'admin'");
    $stmt->execute([$teacher['id'], $teacher['role']]);
    $total_views = $stmt->fetchColumn();
} else {
    $lectures = [
        ['title' => 'Lec 01: Advanced Floorplanning & Macro Placement Strategy', 'subject_name' => 'Floorplanning', 'batch_name' => 'PD Weekend', 'duration_seconds' => 3600, 'status' => 'published', 'created_at' => date('Y-m-d')],
        ['title' => 'Lec 02: Power Mesh IR Drop & Stripe Pitch Calculation', 'subject_name' => 'Power Grid', 'batch_name' => 'PD Weekend', 'duration_seconds' => 4200, 'status' => 'published', 'created_at' => date('Y-m-d', strtotime('-2 days'))],
        ['title' => 'Lec 03: Clock Tree Synthesis & Inverter Sizing Review', 'subject_name' => 'Clock Tree', 'batch_name' => 'PD Weekend', 'duration_seconds' => 3100, 'status' => 'submitted', 'created_at' => date('Y-m-d', strtotime('-3 hours'))]
    ];
    $total_views = 2;
}

$published_count = count(array_filter($lectures, function($l) { return $l['status'] === 'published'; }));
$pending_count   = count(array_filter($lectures, function($l) { return $l['status'] === 'submitted'; }));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instructor Dashboard - VLSI LMS</title>
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
      <a href="index.php" class="on">
        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        <span>My Lectures</span>
      </a>
      <a href="upload.php">
        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <span>Upload / Submit Lecture</span>
      </a>
      <a href="submissions.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg><span>Review Submissions</span></a>
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
      <h1>Instructor Dashboard</h1>
      <div class="top-user">
        <a href="upload.php" class="btn btn-primary btn-sm">+ Upload New Lecture</a>
        <div class="who">
          <span class="avatar">T</span>
          <span class="who-name"><?= htmlspecialchars($teacher['name']) ?></span>
        </div>
      </div>
    </header>

    <main class="content">
      <section class="stats">
        <div class="stat">
          <div class="stat-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg></div>
          <div>
            <b><?= count($lectures) ?></b>
            <small>Total Uploads</small>
          </div>
        </div>
        <div class="stat">
          <div class="stat-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
          <div>
            <b><?= $published_count ?></b>
            <small>Live to Students</small>
          </div>
        </div>
        <div class="stat">
          <div class="stat-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
          <div>
            <b><?= $pending_count ?></b>
            <small>Pending Admin Review</small>
          </div>
        </div>
        <div class="stat">
          <div class="stat-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
          <div>
            <b><?= $total_views ?></b>
            <small>Student Completions</small>
          </div>
        </div>
      </section>

      <section class="card flush">
        <div class="card-head pad">
          <h2>My Submitted Lectures</h2>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Lecture Title</th>
                <th>Subject &amp; Cohort</th>
                <th class="num">Duration</th>
                <th>Status</th>
                <th class="right">Uploaded Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($lectures as $l): ?>
                <?php 
                  $bCls = ($l['status'] === 'published') ? 'badge-green' : (($l['status'] === 'submitted') ? 'badge-amber' : 'badge-grey');
                ?>
                <tr>
                  <td><b><?= htmlspecialchars($l['title']) ?></b></td>
                  <td><?= htmlspecialchars($l['subject_name']) ?><div class="small muted"><?= htmlspecialchars($l['batch_name']) ?></div></td>
                  <td class="num"><?= round($l['duration_seconds']/60) ?>m</td>
                  <td><span class="badge <?= $bCls ?>"><?= strtoupper($l['status']) ?></span></td>
                  <td class="right small muted"><?= htmlspecialchars(substr($l['created_at'], 0, 10)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</div>
<script src="../assets/app.js"></script>
</body>
</html>
