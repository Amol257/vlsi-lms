<?php
require_once __DIR__ . '/../db.php';
$admin = require_role(['admin']);
$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_course') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $title = trim($_POST['title'] ?? '');
        $dur = trim($_POST['duration'] ?? '6 months');
        if ($code && $title) {
            $stmt = $pdo->prepare("INSERT INTO lms_courses (code, title, duration) VALUES (?, ?, ?)");
            $stmt->execute([$code, $title, $dur]);
        }
    } elseif ($action === 'delete_course') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM lms_courses WHERE id = ?");
            $stmt->execute([$id]);
        }
    } elseif ($action === 'add_subject') {
        $cid = intval($_POST['course_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($cid && $name) {
            $order = $pdo->query("SELECT COALESCE(MAX(order_idx),0) + 1 FROM lms_subjects WHERE course_id = $cid")->fetchColumn();
            $stmt = $pdo->prepare("INSERT INTO lms_subjects (course_id, name, order_idx) VALUES (?, ?, ?)");
            $stmt->execute([$cid, $name, $order]);
        }
    }
    header("Location: courses.php");
    exit;
}

$courses = [];
if ($pdo) {
    $courses = $pdo->query("SELECT c.*, 
        (SELECT COUNT(*) FROM lms_subjects WHERE course_id = c.id) as sub_count,
        (SELECT COUNT(*) FROM lms_batches WHERE course_id = c.id) as batch_count
        FROM lms_courses c ORDER BY c.id ASC")->fetchAll();
    
    // Get all subjects
    $subjects = $pdo->query("SELECT * FROM lms_subjects ORDER BY course_id ASC, order_idx ASC")->fetchAll();
} else {
    $courses = [
        ['id' => 1, 'code' => 'PD', 'title' => 'Physical Design & Tapeout', 'duration' => '6 months', 'sub_count' => 3, 'batch_count' => 1],
        ['id' => 2, 'code' => 'DV', 'title' => 'Design Verification (UVM/SystemVerilog)', 'duration' => '4 months', 'sub_count' => 2, 'batch_count' => 1],
        ['id' => 3, 'code' => 'STA', 'title' => 'Static Timing Analysis & Signoff', 'duration' => '3 months', 'sub_count' => 1, 'batch_count' => 0]
    ];
    $subjects = [
        ['course_id' => 1, 'name' => 'Floorplanning & Power Grid Synthesis', 'order_idx' => 1],
        ['course_id' => 1, 'name' => 'Clock Tree Synthesis (CTS) & Skew Optimization', 'order_idx' => 2],
        ['course_id' => 1, 'name' => 'Routing & Signal Integrity (SI)', 'order_idx' => 3],
        ['course_id' => 2, 'name' => 'SystemVerilog OOP & Randomization', 'order_idx' => 1],
        ['course_id' => 2, 'name' => 'UVM Testbench Architecture & Factories', 'order_idx' => 2],
        ['course_id' => 3, 'name' => 'Timing Paths & Clock Jitter Constraints', 'order_idx' => 1]
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Courses &amp; Subjects - VLSI LMS</title>
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
      <a href="courses.php" class="on"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg><span>Courses &amp; Subjects</span></a>
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
      <h1>Courses &amp; Subjects</h1>
      <div class="top-user">
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('newCourseCard').scrollIntoView({behavior:'smooth'})">+ New Course</button>
      </div>
    </header>

    <main class="content">
      <section class="card flush">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th style="width: 80px;">Code</th>
                <th>Course Name</th>
                <th class="num">Subjects</th>
                <th class="num">Batches</th>
                <th>Status</th>
                <th class="right">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($courses as $c): ?>
                <tr>
                  <td><b><?= htmlspecialchars($c['code']) ?></b></td>
                  <td>
                    <b><?= htmlspecialchars($c['title']) ?></b>
                    <div class="muted small"><?= htmlspecialchars($c['duration']) ?></div>
                  </td>
                  <td class="num"><?= $c['sub_count'] ?></td>
                  <td class="num"><?= $c['batch_count'] ?></td>
                  <td><span class="badge badge-green">Active</span></td>
                  <td class="right nowrap">
                    <form method="post" class="inline" onsubmit="return confirm('Delete this course?')">
                      <input type="hidden" name="action" value="delete_course">
                      <input type="hidden" name="id" value="<?= $c['id'] ?>">
                      <button class="btn btn-sm" style="color: var(--red);">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- SUBJECTS BREAKDOWN & NEW COURSE FORMS -->
      <div class="grid-side align-start">
        <div>
          <h2>Curriculum Subjects by Course</h2>
          <div class="grid-2 align-start" style="gap: 16px;">
            <?php foreach ($courses as $c): ?>
              <?php 
                $sub_items = array_filter($subjects, function($s) use ($c) { return $s['course_id'] == $c['id']; }); 
              ?>
              <div class="card" style="margin-bottom: 0;">
                <div class="card-head">
                  <h3><?= htmlspecialchars($c['title']) ?> (<?= htmlspecialchars($c['code']) ?>)</h3>
                  <span class="badge badge-blue"><?= count($sub_items) ?> Modules</span>
                </div>
                <ul style="list-style: none; padding: 0; margin: 0;">
                  <?php if (empty($sub_items)): ?>
                    <li class="muted small">No subjects added yet.</li>
                  <?php else: ?>
                    <?php $idx = 1; foreach ($sub_items as $s): ?>
                      <li style="padding: 8px 0; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between;">
                        <span><b><?= $idx++ ?>.</b> <?= htmlspecialchars($s['name']) ?></span>
                        <span class="small muted">Module <?= $s['order_idx'] ?></span>
                      </li>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </ul>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div>
          <!-- ADD NEW COURSE -->
          <div class="card" id="newCourseCard">
            <h3>+ Create Course Track</h3>
            <form method="post" class="form" style="margin-top: 12px;">
              <input type="hidden" name="action" value="add_course">
              <label>Course Code
                <input type="text" name="code" placeholder="e.g. ANALOG" required>
              </label>
              <label>Course Title
                <input type="text" name="title" placeholder="e.g. CMOS Analog IC Design" required>
              </label>
              <label>Duration
                <input type="text" name="duration" value="4 months" required>
              </label>
              <button class="btn btn-primary btn-block" type="submit">Save Course</button>
            </form>
          </div>

          <!-- ADD NEW SUBJECT -->
          <div class="card">
            <h3>+ Add Subject to Course</h3>
            <form method="post" class="form" style="margin-top: 12px;">
              <input type="hidden" name="action" value="add_subject">
              <label>Select Course
                <select name="course_id" required>
                  <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['code']) ?> - <?= htmlspecialchars($c['title']) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Subject / Module Name
                <input type="text" name="name" placeholder="e.g. Differential Amplifiers &amp; Op-Amps" required>
              </label>
              <button class="btn btn-primary btn-block" type="submit">Save Subject</button>
            </form>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
<script src="../assets/app.js"></script>
</body>
</html>
