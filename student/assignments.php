<?php
require_once __DIR__ . '/../db.php';
$student = require_role(['student', 'admin', 'teacher']);
$pdo = get_db();

$notice = '';
$error = '';

// Handle student assignment submission upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    require_csrf();
    $resource_id = (int)($_POST['resource_id'] ?? 0);

    if ($resource_id > 0 && isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['submission_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Allowed academic upload extensions
        $allowed_exts = ['pdf', 'tar', 'gz', 'zip', 'v', 'sv', 'txt', 'py'];

        // Strict MIME inspection using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected_mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed_mimes = [
            'application/pdf',
            'application/x-tar', 'application/tar', 'application/gzip', 'application/x-gzip',
            'application/zip', 'application/x-zip-compressed',
            'text/plain', 'text/x-verilog', 'text/x-python', 'application/octet-stream'
        ];

        if (!in_array($ext, $allowed_exts, true) || !in_array($detected_mime, $allowed_mimes, true)) {
            $error = 'Invalid file type or MIME mismatch. Allowed formats: .pdf, .tar, .gz, .zip, .v, .sv, .txt, .py';
        } elseif ($file['size'] > 50 * 1024 * 1024) { // 50MB max
            $error = 'File size exceeds maximum 50MB limit.';
        } else {
            // Storage priority: user home -> domain root -> project root fallback
            $candidate_dirs = [
                dirname(__DIR__, 3) . '/lms_storage/submissions',
                dirname(__DIR__, 2) . '/lms_storage/submissions',
                dirname(__DIR__) . '/lms_storage/submissions'
            ];

            $storage_dir = null;
            foreach ($candidate_dirs as $cand) {
                if (!is_dir($cand)) {
                    @mkdir($cand, 0755, true);
                }
                if (is_dir($cand) && is_writable($cand)) {
                    $storage_dir = $cand;
                    break;
                }
            }

            if (!$storage_dir) {
                $storage_dir = dirname(__DIR__) . '/lms_storage/submissions';
                if (!is_dir($storage_dir)) {
                    @mkdir($storage_dir, 0755, true);
                }
            }

            $safe_filename = 'subm_' . $student['id'] . '_' . $resource_id . '_' . bin2hex(random_bytes(16)) . '.' . $ext;
            $dest = $storage_dir . '/' . $safe_filename;
            $real_storage_dir = realpath($storage_dir);

            // Path traversal guard
            if ($real_storage_dir && strpos($safe_filename, '..') === false && move_uploaded_file($file['tmp_name'], $dest)) {
                $stmt = $pdo->prepare("INSERT INTO submissions (resource_id, user_id, file_path, submitted_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$resource_id, $student['id'], $safe_filename]);
                $sub_id = (int)$pdo->lastInsertId();

                lms_audit_log($student['id'], 'submit_assignment', 'submissions', $sub_id, [
                    'resource_id' => $resource_id,
                    'file' => $safe_filename,
                    'mime' => $detected_mime
                ]);
                $notice = 'Assignment submitted successfully!';
            } else {
                $error = 'Failed to safely store submission file on server.';
            }
        }
    } else {
        $error = 'Please select a file to submit.';
    }
}

// Fetch resources and student submissions
$assignments = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT r.*, p.title as program_title, s.id as submission_id, s.file_path as submitted_file, s.submitted_at, s.grade, s.feedback 
            FROM resources r 
            JOIN programs p ON r.program_id = p.id 
            LEFT JOIN submissions s ON r.id = s.resource_id AND s.user_id = ? 
            WHERE r.type IN ('assignment', 'lab') 
            ORDER BY r.due_at ASC, r.id DESC");
        $stmt->execute([$student['id']]);
        $assignments = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Assignments query error: " . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<title>Assignments and Labs - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="role-student">
<div class="shell">
  <?php 
  $active_tab = 'assignments';
  include __DIR__ . '/../includes/sidebar_student.php'; 
  ?>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
      <h1>Lab Exercises and Assignments</h1>
      <div class="top-user">
        <a href="/student/index.php" class="btn btn-sm">Back to Dashboard</a>
      </div>
    </header>

    <main class="content">
      <?php if (!empty($notice)): ?>
        <div style="background: #ecfdf5; color: #047857; padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; font-weight: 500;">
          <?= h($notice) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div style="background: var(--red-soft); color: var(--red); padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; font-weight: 500;">
          <?= h($error) ?>
        </div>
      <?php endif; ?>

      <section class="card flush">
        <div class="card-head pad">
          <h2 style="margin: 0; font-size: 18px;">Active Program Assignments (<?= count($assignments) ?>)</h2>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Title</th>
                <th>Program</th>
                <th>Type</th>
                <th>Due Date</th>
                <th>Submission Status</th>
                <th>Grade &amp; Feedback</th>
                <th class="right">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($assignments)): ?>
                <tr><td colspan="7" style="text-align: center; color: var(--muted); padding: 24px;">No assignments or lab submissions assigned at this time.</td></tr>
              <?php else: ?>
                <?php foreach ($assignments as $a): ?>
                  <tr>
                    <td><b><?= h($a['title']) ?></b></td>
                    <td><?= h($a['program_title']) ?></td>
                    <td><span class="badge badge-gray"><?= strtoupper($a['type']) ?></span></td>
                    <td><?= $a['due_at'] ? date('M j, Y', strtotime($a['due_at'])) : 'No Deadline' ?></td>
                    <td>
                      <?php if (!empty($a['submission_id'])): ?>
                        <span class="badge badge-green">Submitted</span>
                        <div class="small muted"><?= date('M j, g:i A', strtotime($a['submitted_at'])) ?></div>
                      <?php else: ?>
                        <span class="badge badge-blue">Pending</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if (!empty($a['grade'])): ?>
                        <b style="color: var(--primary);"><?= h($a['grade']) ?></b>
                        <?php if (!empty($a['feedback'])): ?>
                          <div class="small muted"><?= h($a['feedback']) ?></div>
                        <?php endif; ?>
                      <?php elseif (!empty($a['submission_id'])): ?>
                        <span class="small muted">Awaiting Review</span>
                      <?php else: ?>
                        <span class="small muted">Not submitted</span>
                      <?php endif; ?>
                    </td>
                    <td class="right">
                      <form method="post" enctype="multipart/form-data" style="display: flex; gap: 6px; align-items: center; justify-content: flex-end;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="resource_id" value="<?= (int)$a['id'] ?>">
                        <input type="file" name="submission_file" required style="font-size: 11px; max-width: 170px;">
                        <button type="submit" class="btn btn-sm btn-primary">Upload</button>
                      </form>
                    </td>
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
<script src="/assets/app.js"></script>
<script src="/assets/heartbeat.js"></script>
</body>
</html>
