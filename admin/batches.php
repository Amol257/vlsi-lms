<?php
require_once __DIR__ . '/../db.php';
$admin = require_role(['admin']);
$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    require_csrf();
    $cid = intval($_POST['course_id'] ?? 0);
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $sched = trim($_POST['schedule'] ?? 'Sat/Sun 10 AM - 1 PM IST');

    if ($cid && $code && $name) {
        $stmt = $pdo->prepare("INSERT INTO lms_batches (course_id, code, name, schedule, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->execute([$cid, $code, $name, $sched]);
    }
    header("Location: batches.php");
    exit;
}

$batches = [];
$courses = [];
if ($pdo) {
    $batches = $pdo->query("SELECT b.*, c.title as course_title, c.code as course_code,
        (SELECT COUNT(*) FROM lms_users WHERE batch_id = b.id AND role = 'student') as student_count
        FROM lms_batches b 
        JOIN lms_courses c ON b.course_id = c.id 
        ORDER BY b.id ASC")->fetchAll();
    $courses = $pdo->query("SELECT id, code, title FROM lms_courses ORDER BY id ASC")->fetchAll();
} else {
    $batches = [
        ['id' => 1, 'code' => 'PD-NOV-2026-WKND', 'name' => 'PD Weekend Batch (Nov 2026)', 'course_title' => 'Physical Design & Tapeout', 'course_code' => 'PD', 'schedule' => 'Sat/Sun 10 AM - 1 PM IST', 'student_count' => 2],
        ['id' => 2, 'code' => 'DV-FALL-2026', 'name' => 'DV Cohort 04 (Evening)', 'course_title' => 'Design Verification (UVM/SystemVerilog)', 'course_code' => 'DV', 'schedule' => 'Mon/Wed/Fri 7 PM - 9 PM IST', 'student_count' => 1]
    ];
    $courses = [
        ['id' => 1, 'code' => 'PD', 'title' => 'Physical Design & Tapeout'],
        ['id' => 2, 'code' => 'DV', 'title' => 'Design Verification (UVM/SystemVerilog)']
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Batches - VLSI LMS</title>
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
      <a href="batches.php" class="on"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg><span>Batches</span></a>
      <a href="courses.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg><span>Courses &amp; Subjects</span></a>
      <a href="lectures.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="5 3 19 12 5 21 5 3"/></svg><span>Lectures</span></a>
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
      <h1>Student Cohort Batches</h1>
    </header>

    <main class="content">
      <div class="grid-side align-start">
        <section class="card flush">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Batch Code</th>
                  <th>Cohort Name</th>
                  <th>Course Track</th>
                  <th>Schedule</th>
                  <th class="num">Enrolled</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($batches as $b): ?>
                  <tr>
                    <td><code><?= htmlspecialchars($b['code']) ?></code></td>
                    <td><b><?= htmlspecialchars($b['name']) ?></b></td>
                    <td><?= htmlspecialchars($b['course_title']) ?> <span class="badge badge-grey"><?= htmlspecialchars($b['course_code']) ?></span></td>
                    <td class="small muted"><?= htmlspecialchars($b['schedule']) ?></td>
                    <td class="num"><b><?= $b['student_count'] ?></b></td>
                    <td><span class="badge badge-green">Active</span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

        <!-- CREATE BATCH FORM -->
        <div class="card">
          <h2>+ Create New Batch</h2>
          <form method="post" class="form">
            <?= csrf_field() ?>
            <label>Associated Course Track
              <select name="course_id" required>
                <?php foreach ($courses as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['code']) ?> - <?= htmlspecialchars($c['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Batch Code
              <input type="text" name="code" placeholder="e.g. PD-DEC-2026-WKND" required>
            </label>
            <label>Batch Name
              <input type="text" name="name" placeholder="e.g. Physical Design Dec Weekend Batch" required>
            </label>
            <label>Class Timings
              <input type="text" name="schedule" value="Sat/Sun 10 AM - 1 PM IST" required>
            </label>
            <button class="btn btn-primary btn-block" type="submit">Create Batch</button>
          </form>
        </div>
      </div>
    </main>
  </div>
</div>
<script src="../assets/app.js"></script>
</body>
</html>
