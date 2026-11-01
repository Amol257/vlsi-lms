<?php
require_once __DIR__ . '/../db.php';
$student = require_role(['student', 'admin', 'teacher']);
$pdo = get_db();

$bid = $student['batch_id'] ?? 1;
$lid = intval($_GET['lecture'] ?? 0);

$lectures = [];
$current_lec = null;
$my_prog = null;

if ($pdo) {
    // Get all published lectures for student's cohort
    $stmt = $pdo->prepare("SELECT l.*, s.name as subject_name 
        FROM lms_lectures l 
        JOIN lms_subjects s ON l.subject_id = s.id 
        WHERE l.batch_id = ? AND l.status = 'published' 
        ORDER BY l.id ASC");
    $stmt->execute([$bid]);
    $lectures = $stmt->fetchAll();

    // Select current lecture
    if ($lid > 0) {
        foreach ($lectures as $l) {
            if ($l['id'] == $lid) { $current_lec = $l; break; }
        }
    }
    if (!$current_lec && !empty($lectures)) {
        $current_lec = $lectures[0];
    }

    if ($current_lec) {
        $stmt = $pdo->prepare("SELECT * FROM lms_progress WHERE user_id = ? AND lecture_id = ?");
        $stmt->execute([$student['id'], $current_lec['id']]);
        $my_prog = $stmt->fetch();
    }
}

if (!$current_lec) {
    $current_lec = [
        'id' => 1,
        'title' => 'Lec 01: Advanced Floorplanning & Macro Placement Strategy',
        'description' => 'Comprehensive walkthrough of core utilization, keep-out margins, channel estimation, and optimal flyline routing for multi-macro ASICs.',
        'teacher_name' => 'Sanjay Deshmukh',
        'subject_name' => 'Floorplanning & Power Grid Synthesis',
        'video_url' => 'https://www.youtube.com/embed/gOo57e69_B8',
        'duration_seconds' => 3600,
        'notes_url' => 'https://vlsiinsights.com/docs/PD_Floorplan_Notes.pdf',
        'lab_url' => 'https://vlsiinsights.com/labs/macro_placement_lab.tar.gz'
    ];
    $lectures = [$current_lec];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($current_lec['title']) ?> · VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="role-student">
<div class="shell">
  <aside class="sidebar">
    <a class="brand" href="index.php">
      <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/></svg></span>
      <span><b>VLSI LMS</b><small>Student Classroom</small></span>
    </a>
    <nav>
      <a href="index.php">
        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        <span>My Batches</span>
      </a>
      <a href="player.php" class="on">
        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        <span>Lecture Player</span>
      </a>
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
      <h1><?= htmlspecialchars($current_lec['title']) ?></h1>
      <div class="top-user">
        <a href="index.php" class="btn btn-sm">← Back to Dashboard</a>
      </div>
    </header>

    <main class="content">
      <div class="grid-side align-start">
        <!-- LEFT: VIDEO PLAYER & LESSON DETAILS -->
        <div>
          <div class="player-wrap">
            <iframe id="playerIframe" src="<?= htmlspecialchars($current_lec['video_url']) ?>" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
          </div>

          <div class="card">
            <div class="card-head">
              <div>
                <h2 style="margin-bottom: 4px; font-size: 18px;"><?= htmlspecialchars($current_lec['title']) ?></h2>
                <span class="small muted"><?= htmlspecialchars($current_lec['teacher_name']) ?> · <?= htmlspecialchars($current_lec['subject_name']) ?></span>
              </div>
              <div class="mark-done <?= ($my_prog && $my_prog['is_completed']) ? 'done' : '' ?>" id="markDoneContainer" data-lecture="<?= $current_lec['id'] ?>" data-duration="<?= $current_lec['duration_seconds'] ?>">
                <button class="btn btn-sm" id="markBtn" onclick="markCompleted(<?= $current_lec['id'] ?>, <?= $current_lec['duration_seconds'] ?>)">
                  <?= ($my_prog && $my_prog['is_completed']) ? '✓ Watched' : 'Mark as Watched' ?>
                </button>
                <span class="small muted" id="progressNote">
                  <?= ($my_prog && $my_prog['is_completed']) ? ' · Completed ✓' : ($my_prog ? ' · ' . $my_prog['percent_watched'] . '% watched' : '') ?>
                </span>
              </div>
            </div>

            <div class="prose" style="margin-bottom: 16px;">
              <?= nl2br(htmlspecialchars($current_lec['description'])) ?>
            </div>

            <div style="border-top: 1px solid var(--line); padding-top: 14px;">
              <h4 style="margin: 0 0 10px;">Downloads &amp; Lab Materials</h4>
              <ul class="files">
                <?php if ($current_lec['notes_url']): ?>
                  <li>
                    <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <a href="<?= htmlspecialchars($current_lec['notes_url']) ?>" target="_blank">Class Notes &amp; Slides (PDF)</a>
                  </li>
                <?php endif; ?>
                <?php if ($current_lec['lab_url']): ?>
                  <li>
                    <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <a href="<?= htmlspecialchars($current_lec['lab_url']) ?>" target="_blank">Lab Exercises &amp; Verilog Testbenches (.tar.gz)</a>
                  </li>
                <?php endif; ?>
                <?php if (!$current_lec['notes_url'] && !$current_lec['lab_url']): ?>
                  <li class="small muted">No supplemental files attached for this lecture.</li>
                <?php endif; ?>
              </ul>
            </div>
          </div>
        </div>

        <!-- RIGHT: PLAYLIST -->
        <div>
          <div class="card flush">
            <div class="card-head pad">
              <h3 style="margin: 0;">Course Syllabus</h3>
            </div>
            <ul class="lecture-list compact">
              <?php $idx = 1; foreach ($lectures as $l): ?>
                <?php 
                  $isCur = ($l['id'] == $current_lec['id']);
                  $isDone = false;
                  if ($pdo) {
                      $isDone = (bool)$pdo->query("SELECT is_completed FROM lms_progress WHERE user_id = {$student['id']} AND lecture_id = {$l['id']}")->fetchColumn();
                  }
                ?>
                <li class="<?= $isCur ? 'current' : '' ?>">
                  <a href="player.php?lecture=<?= $l['id'] ?>">
                    <span class="ln <?= $isDone ? 'done' : '' ?>"><?= $isDone ? '✓' : $idx++ ?></span>
                    <span class="lt">
                      <b><?= htmlspecialchars($l['title']) ?></b>
                      <span class="small muted"><?= htmlspecialchars($l['teacher_name']) ?></span>
                    </span>
                    <span class="lm"><?= round($l['duration_seconds']/60) ?>m</span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
function markCompleted(lecId, duration) {
  var fd = new FormData();
  fd.append('lecture_id', lecId);
  fd.append('position', duration);
  fd.append('duration', duration);
  fd.append('event', 'complete');

  fetch('../api/progress.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.ok) {
        document.getElementById('markBtn').textContent = '✓ Watched';
        document.getElementById('markDoneContainer').classList.add('done');
        document.getElementById('progressNote').textContent = ' · Completed ✓';
        var curLn = document.querySelector('.lecture-list li.current .ln');
        if (curLn) {
          curLn.classList.add('done');
          curLn.textContent = '✓';
        }
      }
    });
}
</script>
<script src="../assets/app.js"></script>
</body>
</html>
