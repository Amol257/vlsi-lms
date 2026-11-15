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
  <?php 
$active_tab = 'batches';
include __DIR__ . '/../includes/sidebar_admin.php'; 
?>

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
