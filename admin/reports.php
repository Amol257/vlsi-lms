<?php
require_once __DIR__ . '/../db.php';
$admin = require_role(['admin']);
$pdo = get_db();

$reports = [];
$security_violations = [];
if ($pdo) {
    $stmt = $pdo->query("SELECT p.*, u.name as student_name, u.email as student_email, l.title as lecture_title 
        FROM lms_progress p 
        JOIN lms_users u ON p.user_id = u.id 
        JOIN lms_lectures l ON p.lecture_id = l.id 
        ORDER BY p.last_viewed_at DESC");
    $reports = $stmt->fetchAll();

    try {
        $sec_stmt = $pdo->query("SELECT sv.*, u.name as student_name, u.email as student_email, u.status as user_status, v.title as video_title 
            FROM security_violations sv 
            JOIN lms_users u ON sv.user_id = u.id 
            LEFT JOIN videos v ON sv.video_id = v.id 
            ORDER BY sv.created_at DESC LIMIT 50");
        $security_violations = $sec_stmt->fetchAll();
    } catch (PDOException $e) {
        $security_violations = [];
    }
} else {
    $reports = [
        ['student_name' => 'Rahul Sharma', 'student_email' => 'student@example.com', 'lecture_title' => 'Lec 01: Advanced Floorplanning', 'percent_watched' => 79, 'position_seconds' => 2850, 'duration_seconds' => 3600, 'is_completed' => 0, 'last_viewed_at' => date('Y-m-d H:i')],
        ['student_name' => 'Priya Kulkarni', 'student_email' => 'priya.k@example.com', 'lecture_title' => 'Lec 01: Advanced Floorplanning', 'percent_watched' => 100, 'position_seconds' => 3600, 'duration_seconds' => 3600, 'is_completed' => 1, 'last_viewed_at' => date('Y-m-d H:i', strtotime('-1 day'))]
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reports - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="role-admin">
<div class="shell">
  <?php 
$active_tab = 'reports';
include __DIR__ . '/../includes/sidebar_admin.php'; 
?>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
      <h1>Student Viewing Analytics</h1>
    </header>

    <main class="content">
      <section class="card flush">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th>Lecture Title</th>
                <th>Completion (%)</th>
                <th>Streamed Time</th>
                <th>Status</th>
                <th class="right">Last Active</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reports as $r): ?>
                <tr>
                  <td><b><?= htmlspecialchars($r['student_name']) ?></b><div class="small muted"><?= htmlspecialchars($r['student_email']) ?></div></td>
                  <td><?= htmlspecialchars($r['lecture_title']) ?></td>
                  <td>
                    <div style="display:flex; align-items:center; gap:8px;">
                      <span><b><?= $r['percent_watched'] ?>%</b></span>
                      <div class="progress-bar-wrap" style="width: 100px; margin: 0;">
                        <div class="fill" style="width: <?= $r['percent_watched'] ?>%;"></div>
                      </div>
                    </div>
                  </td>
                  <td class="font-mono small"><?= round($r['position_seconds']/60) ?>m / <?= round($r['duration_seconds']/60) ?>m</td>
                  <td>
                    <?= $r['is_completed'] ? '<span class="badge badge-green">Completed</span>' : '<span class="badge badge-blue">In Progress</span>' ?>
                  </td>
                  <td class="right small muted"><?= htmlspecialchars(substr($r['last_viewed_at'], 0, 16)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
      <div style="margin-top: 32px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
        <div>
          <h2 style="margin: 0 0 4px 0; font-size: 18px; color: #f87171; display: flex; align-items: center; gap: 8px;">
            <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="color: #ef4444;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Anti-Piracy &amp; Screen Capture Alerts
          </h2>
          <span class="small muted">Real-time incident log: window blur, screenshot keys, DevTools, and capture attempts</span>
        </div>
        <span class="badge badge-red font-mono"><?= count($security_violations) ?> Incidents Logged</span>
      </div>

      <section class="card flush">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th>Lecture / Video</th>
                <th>Violation Type</th>
                <th>Details</th>
                <th>IP Address</th>
                <th>Account Status</th>
                <th class="right">Detected At</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($security_violations)): ?>
                <tr>
                  <td colspan="7" style="text-align: center; padding: 24px;" class="muted">No anti-piracy violations detected. All sessions are compliant.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($security_violations as $sv): ?>
                  <tr>
                    <td>
                      <b><?= htmlspecialchars($sv['student_name']) ?></b>
                      <div class="small muted"><?= htmlspecialchars($sv['student_email']) ?></div>
                    </td>
                    <td><?= htmlspecialchars($sv['video_title'] ?? ('Video #' . $sv['video_id'])) ?></td>
                    <td>
                      <?php
                        $badge_class = 'badge-blue';
                        if (str_contains($sv['violation_type'], 'devtools') || str_contains($sv['violation_type'], 'share') || str_contains($sv['violation_type'], 'print')) {
                          $badge_class = 'badge-red';
                        } elseif (str_contains($sv['violation_type'], 'blur')) {
                          $badge_class = 'badge-amber';
                        }
                      ?>
                      <span class="badge <?= $badge_class ?> font-mono" style="font-size: 11px;">
                        <?= htmlspecialchars($sv['violation_type']) ?>
                      </span>
                    </td>
                    <td class="small" style="max-width: 260px; color: var(--ink-2);"><?= htmlspecialchars($sv['details'] ?? '') ?></td>
                    <td class="font-mono small"><?= htmlspecialchars($sv['ip']) ?></td>
                    <td>
                      <?php if ($sv['user_status'] === 'blocked'): ?>
                        <span class="badge badge-red">BLOCKED</span>
                      <?php else: ?>
                        <span class="badge badge-green">Active</span>
                      <?php endif; ?>
                    </td>
                    <td class="right small muted font-mono"><?= htmlspecialchars(substr($sv['created_at'], 0, 16)) ?></td>
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
<script src="../assets/app.js"></script>
</body>
</html>
