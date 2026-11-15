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
<title>Dashboard - VLSI LMS Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="role-admin">
<div class="shell">
  <?php 
$active_tab = 'dashboard';
include __DIR__ . '/../includes/sidebar_admin.php'; 
?>

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
      <?php if (!lms_is_custom_session_path_active()): ?>
        <div style="background: #fffbeb; border: 1px solid #fef3c7; color: #b45309; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px;">
          <strong>Shared Hosting Session Warning:</strong> PHP sessions are stored in the server default temporary folder. For shared hosting isolation, set <code>app.session_save_path</code> in <code>config.local.php</code> to a dedicated directory outside public_html.
        </div>
      <?php endif; ?>
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
