<?php
require_once __DIR__ . '/../db.php';
$teacher = require_role(['teacher', 'admin']);
$pdo = get_db();

$notice = '';
$error = '';

// Handle grading submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    require_csrf();
    $submission_id = (int)($_POST['submission_id'] ?? 0);
    $grade = trim($_POST['grade'] ?? '');
    $feedback = trim($_POST['feedback'] ?? '');

    if ($submission_id > 0 && !empty($grade)) {
        $stmt = $pdo->prepare("UPDATE submissions SET grade = ?, feedback = ? WHERE id = ?");
        $stmt->execute([$grade, $feedback, $submission_id]);

        lms_audit_log($teacher['id'], 'grade_submission', 'submissions', $submission_id, [
            'grade' => $grade,
            'feedback' => $feedback
        ]);
        $notice = 'Grade and feedback submitted successfully.';
    } else {
        $error = 'Please provide a grade.';
    }
}

// Fetch submissions
$submissions = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT s.*, u.name as student_name, u.email as student_email, r.title as assignment_title, p.title as program_title 
            FROM submissions s 
            JOIN lms_users u ON s.user_id = u.id 
            JOIN resources r ON s.resource_id = r.id 
            JOIN programs p ON r.program_id = p.id 
            ORDER BY s.id DESC");
        $submissions = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Teacher submissions fetch error: " . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Review Student Submissions - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="role-teacher">
<div class="shell">
  <?php 
$active_tab = 'submissions';
include __DIR__ . '/../includes/sidebar_teacher.php'; 
?>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
      <h1>Student Lab and Assignment Submissions</h1>
      <div class="top-user">
        <span class="who-name"><?= h($teacher['name']) ?></span>
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
          <h2 style="margin: 0; font-size: 18px;">Submissions Requiring Review (<?= count($submissions) ?>)</h2>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th>Assignment</th>
                <th>Submitted File</th>
                <th>Submitted Time</th>
                <th>Grade</th>
                <th class="right">Assign Grade &amp; Feedback</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($submissions)): ?>
                <tr><td colspan="6" style="text-align: center; color: var(--muted); padding: 24px;">No student submissions found.</td></tr>
              <?php else: ?>
                <?php foreach ($submissions as $s): ?>
                  <tr>
                    <td>
                      <b><?= h($s['student_name']) ?></b>
                      <div class="small muted"><?= h($s['student_email']) ?></div>
                    </td>
                    <td>
                      <b><?= h($s['assignment_title']) ?></b>
                      <div class="small muted"><?= h($s['program_title']) ?></div>
                    </td>
                    <td>
                      <code><?= h($s['file_path']) ?></code>
                    </td>
                    <td><?= date('M j, Y g:i A', strtotime($s['submitted_at'])) ?></td>
                    <td>
                      <?php if (!empty($s['grade'])): ?>
                        <span class="badge badge-green"><?= h($s['grade']) ?></span>
                      <?php else: ?>
                        <span class="badge badge-blue">Pending</span>
                      <?php endif; ?>
                    </td>
                    <td class="right">
                      <form method="post" style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="submission_id" value="<?= (int)$s['id'] ?>">
                        <input type="text" name="grade" value="<?= h($s['grade'] ?? '') ?>" placeholder="Grade (A, 95%)" style="width: 100px; padding: 4px 8px; font-size: 12px;">
                        <input type="text" name="feedback" value="<?= h($s['feedback'] ?? '') ?>" placeholder="Feedback notes" style="width: 140px; padding: 4px 8px; font-size: 12px;">
                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
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
</body>
</html>
