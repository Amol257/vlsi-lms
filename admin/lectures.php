<?php
require_once __DIR__ . '/../db.php';
$admin = require_role(['admin']);
$pdo = get_db();

$batch_id = intval($_GET['batch'] ?? 0);
$status = trim($_GET['status'] ?? '');

$lectures = [];
$batches = [];
if ($pdo) {
    $batches = $pdo->query("SELECT id, code, name FROM lms_batches ORDER BY id ASC")->fetchAll();

    $sql = "SELECT l.*, b.name as batch_name, s.name as subject_name 
        FROM lms_lectures l 
        JOIN lms_batches b ON l.batch_id = b.id 
        JOIN lms_subjects s ON l.subject_id = s.id 
        WHERE 1=1";
    $params = [];
    if ($batch_id > 0) {
        $sql .= " AND l.batch_id = ?";
        $params[] = $batch_id;
    }
    if ($status !== '') {
        $sql .= " AND l.status = ?";
        $params[] = $status;
    }
    $sql .= " ORDER BY l.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lectures = $stmt->fetchAll();
} else {
    $lectures = [
        ['id' => 1, 'title' => 'Lec 01: Advanced Floorplanning & Macro Placement Strategy', 'subject_name' => 'Floorplanning', 'batch_name' => 'PD Weekend', 'teacher_name' => 'Sanjay Deshmukh', 'duration_seconds' => 3600, 'status' => 'published'],
        ['id' => 2, 'title' => 'Lec 02: Power Mesh IR Drop & Stripe Pitch Calculation', 'subject_name' => 'Power Grid', 'batch_name' => 'PD Weekend', 'teacher_name' => 'Sanjay Deshmukh', 'duration_seconds' => 4200, 'status' => 'published'],
        ['id' => 3, 'title' => 'Lec 03: Clock Tree Synthesis & Inverter Sizing Review', 'subject_name' => 'Clock Tree', 'batch_name' => 'PD Weekend', 'teacher_name' => 'Sanjay Deshmukh', 'duration_seconds' => 3100, 'status' => 'submitted']
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lectures - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="role-admin">
<div class="shell">
  <?php 
$active_tab = 'lectures';
include __DIR__ . '/../includes/sidebar_admin.php'; 
?>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
      <h1>Recorded VLSI Lectures</h1>
    </header>

    <main class="content">
      <div class="toolbar">
        <form method="get" class="filters">
          <select name="batch" onchange="this.form.submit()">
            <option value="">All Batches</option>
            <?php foreach ($batches as $b): ?>
              <option value="<?= $b['id'] ?>" <?= $batch_id == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['code']) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="status" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>Submitted (Pending Review)</option>
            <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
          </select>
        </form>
      </div>

      <section class="card flush">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Lecture Title</th>
                <th>Subject &amp; Cohort</th>
                <th>Instructor</th>
                <th class="num">Duration</th>
                <th>Status</th>
                <th class="right">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($lectures as $l): ?>
                <?php 
                  $badgeCls = $l['status'] === 'published' ? 'badge-green' : ($l['status'] === 'submitted' ? 'badge-amber' : 'badge-grey');
                ?>
                <tr>
                  <td><b><?= htmlspecialchars($l['title']) ?></b></td>
                  <td><?= htmlspecialchars($l['subject_name']) ?><div class="small muted"><?= htmlspecialchars($l['batch_name']) ?></div></td>
                  <td><?= htmlspecialchars($l['teacher_name']) ?></td>
                  <td class="num font-mono"><?= round($l['duration_seconds']/60) ?>m</td>
                  <td><span class="badge <?= $badgeCls ?>"><?= strtoupper($l['status']) ?></span></td>
                  <td class="right nowrap">
                    <a href="lecture_view.php?id=<?= $l['id'] ?>" class="btn btn-sm <?= $l['status'] === 'submitted' ? 'btn-primary' : '' ?>">
                      <?= $l['status'] === 'submitted' ? 'Review &amp; Approve' : 'View' ?>
                    </a>
                  </td>
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
