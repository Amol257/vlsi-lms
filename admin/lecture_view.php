<?php
require_once __DIR__ . '/../db.php';
$admin = require_role(['admin']);
$pdo = get_db();

$id = intval($_GET['id'] ?? 1);

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    require_csrf();
    $new_status = $_POST['status'] ?? '';
    if (in_array($new_status, ['published', 'draft', 'rejected'])) {
        $stmt = $pdo->prepare("UPDATE lms_lectures SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $id]);

        lms_audit_log($admin['id'], 'update_lecture_status', 'lms_lectures', $id, [
            'status' => $new_status,
            'title' => $lec['title'] ?? 'Lecture'
        ]);
    }
    header("Location: lecture_view.php?id=$id");
    exit;
}

$lec = null;
$views = [];
if ($pdo) {
    $stmt = $pdo->prepare("SELECT l.*, b.name as batch_name, s.name as subject_name 
        FROM lms_lectures l 
        JOIN lms_batches b ON l.batch_id = b.id 
        JOIN lms_subjects s ON l.subject_id = s.id 
        WHERE l.id = ?");
    $stmt->execute([$id]);
    $lec = $stmt->fetch();

    if ($lec) {
        $stmt = $pdo->prepare("SELECT p.*, u.name as student_name, u.email as student_email 
            FROM lms_progress p 
            JOIN lms_users u ON p.user_id = u.id 
            WHERE p.lecture_id = ? 
            ORDER BY p.last_viewed_at DESC");
        $stmt->execute([$id]);
        $views = $stmt->fetchAll();
    }
}

if (!$lec) {
    $lec = [
        'id' => $id,
        'title' => 'Lec 01: Advanced Floorplanning & Macro Placement Strategy',
        'description' => 'Comprehensive walkthrough of core utilization, keep-out margins, channel estimation, and optimal flyline routing for multi-macro ASICs.',
        'teacher_name' => 'Sanjay Deshmukh',
        'subject_name' => 'Floorplanning & Power Grid Synthesis',
        'batch_name' => 'PD Weekend Batch (Nov 2026)',
        'video_url' => 'https://www.youtube.com/embed/gOo57e69_B8',
        'notes_url' => 'https://vlsiinsights.com/docs/PD_Floorplan_Notes.pdf',
        'lab_url' => 'https://vlsiinsights.com/labs/macro_placement_lab.tar.gz',
        'status' => 'published',
        'video_type' => 'youtube'
    ];
}

$hls_video_id = 0;
if ($lec) {
    if (($lec['video_type'] ?? '') === 'hls' || !preg_match('#^https?://#i', $lec['video_url'])) {
        if ($pdo) {
            try {
                $vstmt = $pdo->prepare("SELECT id FROM videos WHERE hls_dir = ? OR id = ? LIMIT 1");
                $vstmt->execute([$lec['video_url'], intval($lec['video_url'])]);
                $hls_video_id = (int)$vstmt->fetchColumn();
            } catch (PDOException $e) {}
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Review: <?= htmlspecialchars($lec['title']) ?> - VLSI LMS</title>
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
      <h1>Review Lecture</h1>
      <div class="top-user">
        <a href="lectures.php" class="btn btn-sm">← Back to Lectures</a>
      </div>
    </header>

    <main class="content">
      <div class="grid-side align-start">
        <div>
          <div class="player-wrap">
            <?php if ($hls_video_id > 0): ?>
              <iframe src="/student/watch.php?id=<?= $hls_video_id ?>&embed=1" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
            <?php elseif (($lec['video_type'] ?? '') === 'hls'): ?>
              <div style="height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94a3b8; padding: 24px; text-align: center; background: #0b0f19;">
                <p style="margin-bottom: 8px; font-weight: 600; color: #fff;">HLS Stream Not Linked</p>
                <small>The specified directory or ID (<code><?= htmlspecialchars($lec['video_url']) ?></code>) is not registered in the Video Library.</small>
              </div>
            <?php else: ?>
              <iframe src="<?= htmlspecialchars($lec['video_url']) ?>" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
            <?php endif; ?>
          </div>
          <?php if ($hls_video_id > 0): ?>
            <div style="margin: -6px 0 16px 0; display: flex; justify-content: flex-end;">
              <a href="/student/watch.php?id=<?= $hls_video_id ?>" target="_blank" class="btn btn-sm btn-outline">
                Open in Full Player ↗
              </a>
            </div>
          <?php endif; ?>

          <div class="card">
            <div class="card-head">
              <div>
                <?php 
                  $bCls = ($lec['status'] === 'published') ? 'badge-green' : (($lec['status'] === 'submitted') ? 'badge-amber' : 'badge-grey');
                ?>
                <span class="badge <?= $bCls ?>"><?= strtoupper($lec['status']) ?></span>
                <h2 style="margin-top: 6px; font-size: 18px;"><?= htmlspecialchars($lec['title']) ?></h2>
              </div>
              <div style="display: flex; gap: 8px;">
                <?php if ($lec['status'] === 'submitted' || $lec['status'] === 'draft'): ?>
                  <form method="post" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="published">
                    <button class="btn btn-primary" type="submit">✓ Approve &amp; Publish</button>
                  </form>
                  <form method="post" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="rejected">
                    <button class="btn btn-danger btn-sm" type="submit">Reject</button>
                  </form>
                <?php else: ?>
                  <form method="post" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="draft">
                    <button class="btn btn-sm" type="submit">Unpublish to Draft</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>

            <p style="color: var(--ink-2); margin-bottom: 16px;"><?= nl2br(htmlspecialchars($lec['description'])) ?></p>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; padding: 14px 0; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line);">
              <div>
                <small class="muted" style="display: block;">Instructor</small>
                <b><?= htmlspecialchars($lec['teacher_name']) ?></b>
              </div>
              <div>
                <small class="muted" style="display: block;">Subject / Module</small>
                <b><?= htmlspecialchars($lec['subject_name']) ?></b>
              </div>
              <div>
                <small class="muted" style="display: block;">Cohort Batch</small>
                <b><?= htmlspecialchars($lec['batch_name']) ?></b>
              </div>
            </div>

            <div style="margin-top: 16px;">
              <h4>Attached Class Materials</h4>
              <ul class="files">
                <?php if ($lec['notes_url']): ?>
                  <li>
                    <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <a href="<?= htmlspecialchars($lec['notes_url']) ?>" target="_blank">Class Notes (PDF)</a>
                  </li>
                <?php endif; ?>
                <?php if ($lec['lab_url']): ?>
                  <li>
                    <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <a href="<?= htmlspecialchars($lec['lab_url']) ?>" target="_blank">Lab Exercises &amp; Verilog Testbenches (.tar.gz)</a>
                  </li>
                <?php endif; ?>
                <?php if (!$lec['notes_url'] && !$lec['lab_url']): ?>
                  <li class="small muted">No files attached to this lecture.</li>
                <?php endif; ?>
              </ul>
            </div>
          </div>
        </div>

        <!-- RIGHT: STUDENT VIEWS -->
        <div>
          <div class="card">
            <div class="card-head">
              <h3>Student Viewer Analytics</h3>
            </div>
            <div style="display: flex; flex-direction: column; gap: 12px;">
              <?php if (empty($views)): ?>
                <p class="small muted">No student viewing logs recorded yet.</p>
              <?php else: ?>
                <?php foreach ($views as $v): ?>
                  <div style="padding: 10px; border-radius: 8px; background: #f8fafc; border: 1px solid var(--line);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                      <b><?= htmlspecialchars($v['student_name']) ?></b>
                      <span class="badge <?= $v['is_completed'] ? 'badge-green' : 'badge-blue' ?>"><?= $v['percent_watched'] ?>%</span>
                    </div>
                    <div class="progress-bar-wrap" style="margin-bottom: 6px;">
                      <div class="fill" style="width: <?= $v['percent_watched'] ?>%;"></div>
                    </div>
                    <small class="muted">Last viewed: <?= substr($v['last_viewed_at'], 0, 16) ?></small>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
<script src="../assets/app.js"></script>
</body>
</html>
