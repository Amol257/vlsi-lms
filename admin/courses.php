<?php
require_once __DIR__ . '/../db.php';
$admin = require_role(['admin']);
$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    require_csrf();
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
            $stmt_order = $pdo->prepare("SELECT COALESCE(MAX(order_idx),0) + 1 FROM lms_subjects WHERE course_id = ?");
            $stmt_order->execute([$cid]);
            $order = (int)$stmt_order->fetchColumn();

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
  <?php 
$active_tab = 'courses';
include __DIR__ . '/../includes/sidebar_admin.php'; 
?>

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
                      <?= csrf_field() ?>
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
              <?= csrf_field() ?>
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
              <?= csrf_field() ?>
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
