<?php
require_once __DIR__ . '/../db.php';
$admin = require_role(['admin']);
$pdo = get_db();

// Default stats
$total_students = 3;
$total_batches  = 2;
$total_lectures = 2;
$watch_hours    = '3.8h';
$pending_lecs   = [];
$recent_views   = [];

if ($pdo) {
    $total_students = $pdo->query("SELECT COUNT(*) FROM lms_users WHERE role = 'student'")->fetchColumn();
    $total_batches  = $pdo->query("SELECT COUNT(*) FROM lms_batches WHERE status = 'active'")->fetchColumn();
    $total_lectures = $pdo->query("SELECT COUNT(*) FROM lms_lectures WHERE status = 'published'")->fetchColumn();
    
    $total_sec = $pdo->query("SELECT COALESCE(SUM(position_seconds), 0) FROM lms_progress")->fetchColumn();
    $watch_hours = round($total_sec / 3600, 1) . 'h';

    // Pending lectures
    $stmt = $pdo->query("SELECT l.*, b.name as batch_name, s.name as subject_name 
        FROM lms_lectures l 
        JOIN lms_batches b ON l.batch_id = b.id 
        JOIN lms_subjects s ON l.subject_id = s.id 
        WHERE l.status = 'submitted' 
        ORDER BY l.created_at DESC");
    $pending_lecs = $stmt->fetchAll();

    // Recent viewing activity
    $stmt = $pdo->query("SELECT p.*, u.name as student_name, u.email as student_email, l.title as lecture_title 
        FROM lms_progress p 
        JOIN lms_users u ON p.user_id = u.id 
        JOIN lms_lectures l ON p.lecture_id = l.id 
        ORDER BY p.last_viewed_at DESC LIMIT 10");
    $recent_views = $stmt->fetchAll();
} else {
    // Demo fallback preview
    $pending_lecs = [
        [
            'id' => 3,
            'title' => 'Lec 03: Clock Tree Synthesis & Inverter Sizing Review',
            'subject_name' => 'Clock Tree Synthesis (CTS) & Skew Optimization',
            'batch_name' => 'PD Weekend Batch (Nov 2026)',
            'teacher_name' => 'Sanjay Deshmukh',
            'created_at' => date('Y-m-d H:i')
        ]
    ];
    $recent_views = [
        [
            'student_name' => 'Rahul Sharma',
            'student_email' => 'student@example.com',
            'lecture_title' => 'Lec 01: Advanced Floorplanning & Macro Placement Strategy',
            'percent_watched' => 79,
            'position_seconds' => 2850,
            'duration_seconds' => 3600,
            'is_completed' => 0,
            'last_viewed_at' => date('Y-m-d H:i')
        ],
        [
            'student_name' => 'Priya Kulkarni',
            'student_email' => 'priya.k@example.com',
            'lecture_title' => 'Lec 01: Advanced Floorplanning & Macro Placement Strategy',
            'percent_watched' => 100,
            'position_seconds' => 3600,
            'duration_seconds' => 3600,
            'is_completed' => 1,
            'last_viewed_at' => date('Y-m-d H:i', strtotime('-1 day'))
        ]
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard · VLSI LMS Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="role-admin">
<div class="shell">
  <aside class="sidebar">
    <a class="brand" href="index.php">
      <span class="brand-mark">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/></svg>
      </span>
      <span><b>VLSI LMS</b><small>Hostinger MySQL</small></span>
    </a>
    <nav>
      <a href="index.php" class="on"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span>Dashboard</span></a>
      <a href="users.php?role=student"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2 .6 3.5 2.6 3.5 6"/></svg><span>Students</span></a>
      <a href="users.php?role=teacher"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="7" r="4"/><path d="M5.5 21c0-4 3.5-7 6.5-7s6.5 3 6.5 7"/></svg><span>Teachers</span></a>
      <a href="batches.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg><span>Batches</span></a>
      <a href="courses.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg><span>Courses &amp; Subjects</span></a>
      <a href="lectures.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="5 3 19 12 5 21 5 3"/></svg><span>Lectures</span><?php if (count($pending_lecs) > 0): ?><span class="count"><?= count($pending_lecs) ?></span><?php endif; ?></a>
      <a href="webinars.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 10l5-5v14l-5-5M4 6h11a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/></svg><span>Webinars</span></a>
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
      <button class="icon-btn menu-btn" aria-label="Toggle navigation"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
      <h1>Admin Dashboard</h1>
      <div class="top-user">
        <div class="who">
          <span class="avatar">A</span>
          <span class="who-name"><?= htmlspecialchars($admin['email']) ?></span>
        </div>
      </div>
    </header>

    <main class="content">
      <section class="stats">
        <div class="stat">
          <div class="stat-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
          <div>
            <b><?= $total_students ?></b>
            <small>Active Students</small>
          </div>
        </div>
        <div class="stat">
          <div class="stat-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></div>
          <div>
            <b><?= $total_batches ?></b>
            <small>Active Cohorts</small>
          </div>
        </div>
        <div class="stat">
          <div class="stat-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg></div>
          <div>
            <b><?= $total_lectures ?></b>
            <small>Published Lectures</small>
          </div>
        </div>
        <div class="stat">
          <div class="stat-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
          <div>
            <b><?= $watch_hours ?></b>
            <small>Watch Time Streamed</small>
          </div>
        </div>
      </section>

      <!-- PENDING REVIEWS -->
      <section class="card">
        <div class="card-head">
          <div>
            <h2>Pending Lecture Reviews</h2>
            <p class="muted small" style="margin: 0;">Teacher lecture submissions awaiting admin signoff.</p>
          </div>
          <span class="badge badge-amber"><?= count($pending_lecs) ?> Pending</span>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Lecture Title</th>
                <th>Subject &amp; Cohort</th>
                <th>Instructor</th>
                <th>Uploaded</th>
                <th>Status</th>
                <th class="right">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($pending_lecs)): ?>
                <tr><td colspan="6" class="muted" style="text-align: center; padding: 24px;">No lectures currently waiting for review.</td></tr>
              <?php else: ?>
                <?php foreach ($pending_lecs as $l): ?>
                  <tr>
                    <td><b><?= htmlspecialchars($l['title']) ?></b></td>
                    <td><?= htmlspecialchars($l['subject_name']) ?><div class="small muted"><?= htmlspecialchars($l['batch_name']) ?></div></td>
                    <td><?= htmlspecialchars($l['teacher_name']) ?></td>
                    <td class="small muted"><?= htmlspecialchars(substr($l['created_at'], 0, 16)) ?></td>
                    <td><span class="badge badge-amber">Submitted</span></td>
                    <td class="right">
                      <a href="lecture_view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-primary">Review &amp; Approve</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- VIEWING ACTIVITY -->
      <section class="card">
        <div class="card-head">
          <div>
            <h2>Student Viewing Activity</h2>
            <p class="muted small" style="margin: 0;">Recent lecture playback tracked in Hostinger MySQL.</p>
          </div>
          <a href="reports.php" class="btn btn-sm">Full Report →</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th>Lecture</th>
                <th>Watched %</th>
                <th>Position</th>
                <th>Status</th>
                <th class="right">Last Active</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_views as $v): ?>
                <tr>
                  <td><b><?= htmlspecialchars($v['student_name']) ?></b><div class="small muted"><?= htmlspecialchars($v['student_email']) ?></div></td>
                  <td><?= htmlspecialchars($v['lecture_title']) ?></td>
                  <td>
                    <div style="display:flex; align-items:center; gap:8px;">
                      <span><?= $v['percent_watched'] ?>%</span>
                      <div class="progress-bar-wrap" style="width: 80px; margin: 0;">
                        <div class="fill" style="width: <?= $v['percent_watched'] ?>%;"></div>
                      </div>
                    </div>
                  </td>
                  <td class="small muted"><?= round($v['position_seconds']/60) ?>m / <?= round($v['duration_seconds']/60) ?>m</td>
                  <td>
                    <?= $v['is_completed'] ? '<span class="badge badge-green">Completed</span>' : '<span class="badge badge-blue">In Progress</span>' ?>
                  </td>
                  <td class="right small muted"><?= htmlspecialchars(substr($v['last_viewed_at'], 0, 16)) ?></td>
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
