<?php
require_once __DIR__ . '/../db.php';
$admin = require_role(['admin']);
$pdo = get_db();

$notice = '';
$error = '';

// Handle manual enrollment actions: Grant or Revoke
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'grant') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $program_id = (int)($_POST['program_id'] ?? 0);
        $days = (int)($_POST['duration_days'] ?? 180);

        if ($user_id > 0 && $program_id > 0) {
            $expires_at = date('Y-m-d H:i:s', time() + ($days * 86400));
            $stmt = $pdo->prepare("INSERT INTO enrollments (user_id, program_id, status, source, starts_at, expires_at) 
                VALUES (?, ?, 'active', 'admin', NOW(), ?) 
                ON DUPLICATE KEY UPDATE status = 'active', expires_at = VALUES(expires_at)");
            $stmt->execute([$user_id, $program_id, $expires_at]);

            lms_audit_log($admin['id'], 'grant_enrollment', 'enrollments', null, [
                'user_id' => $user_id,
                'program_id' => $program_id,
                'duration_days' => $days
            ]);
            $notice = 'Program enrollment granted successfully.';
        } else {
            $error = 'Please select both a student and a program.';
        }
    } elseif ($action === 'revoke') {
        $enroll_id = (int)($_POST['enrollment_id'] ?? 0);
        if ($enroll_id > 0) {
            $stmt = $pdo->prepare("UPDATE enrollments SET status = 'revoked' WHERE id = ?");
            $stmt->execute([$enroll_id]);

            lms_audit_log($admin['id'], 'revoke_enrollment', 'enrollments', $enroll_id, []);
            $notice = 'Enrollment status revoked.';
        }
    }
}

// Fetch all students and programs for enrollment form
$all_students = [];
$all_programs = [];
$enrollments_list = [];

if ($pdo) {
    try {
        $all_students = $pdo->query("SELECT id, name, email FROM lms_users WHERE role = 'student' ORDER BY name ASC")->fetchAll();
        $all_programs = $pdo->query("SELECT id, title, price_inr FROM programs ORDER BY id ASC")->fetchAll();

        $stmt = $pdo->query("SELECT e.*, u.name as user_name, u.email as user_email, p.title as program_title 
            FROM enrollments e 
            JOIN lms_users u ON e.user_id = u.id 
            JOIN programs p ON e.program_id = p.id 
            ORDER BY e.id DESC");
        $enrollments_list = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Enrollments fetch error: " . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Program Enrolments - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="role-admin">
<div class="shell">
  <aside class="sidebar">
    <a class="brand" href="/admin/index.php">
      <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/></svg></span>
      <span><b>VLSI LMS</b><small>Hostinger MySQL</small></span>
    </a>
    <nav>
      <a href="/admin/index.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span>Dashboard</span></a>
      <a href="/admin/videos.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="5 3 19 12 5 21 5 3"/></svg><span>Video Library</span></a>
      <a href="/admin/users.php?role=student"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2 .6 3.5 2.6 3.5 6"/></svg><span>Students</span></a>
      <a href="/admin/courses.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg><span>Courses &amp; Subjects</span></a>
      <a href="/admin/webinars.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 10l5-5v14l-5-5M4 6h11a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/></svg><span>Webinars</span></a>
      <a href="/admin/enrolments.php" class="on"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg><span>Enrolments</span></a>
      <a href="/admin/audit.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg><span>Audit Log</span></a>
    </nav>
    <div class="sidebar-footer">
      <a href="/logout.php" style="display: flex; align-items: center; gap: 8px; color: #f87171; font-weight: 500;">
        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span>Sign Out</span>
      </a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
      <h1>Student Program Enrolments</h1>
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

      <!-- GRANT NEW ENROLLMENT -->
      <section class="card" style="margin-bottom: 24px;">
        <h2 style="margin-top: 0; font-size: 18px;">Grant Student Program Access</h2>
        <form method="post" class="form" style="display: grid; grid-template-columns: 2fr 2fr 1fr auto; gap: 14px; align-items: flex-end;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="grant">

          <label>Select Student
            <select name="user_id" required>
              <option value="">Choose student...</option>
              <?php foreach ($all_students as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= h($s['name']) ?> (<?= h($s['email']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </label>

          <label>Select Program
            <select name="program_id" required>
              <option value="">Choose program...</option>
              <?php foreach ($all_programs as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= h($p['title']) ?> (INR <?= number_format((int)$p['price_inr']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </label>

          <label>Duration
            <select name="duration_days">
              <option value="90">90 Days</option>
              <option value="180" selected>180 Days (6 Months)</option>
              <option value="365">365 Days (1 Year)</option>
            </select>
          </label>

          <div>
            <button type="submit" class="btn btn-primary" style="padding: 10px 18px;">Grant Access</button>
          </div>
        </form>
      </section>

      <!-- ACTIVE ENROLLMENTS TABLE -->
      <section class="card flush">
        <div class="card-head pad">
          <h2 style="margin: 0; font-size: 18px;">Active &amp; Historical Enrolments (<?= count($enrollments_list) ?>)</h2>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th>Program Enrolled</th>
                <th>Source</th>
                <th>Enrolled Date</th>
                <th>Expiry</th>
                <th>Status</th>
                <th class="right">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($enrollments_list)): ?>
                <tr><td colspan="7" style="text-align: center; color: var(--muted); padding: 24px;">No active enrolments found.</td></tr>
              <?php else: ?>
                <?php foreach ($enrollments_list as $e): ?>
                  <tr>
                    <td>
                      <b><?= h($e['user_name']) ?></b>
                      <div class="small muted"><?= h($e['user_email']) ?></div>
                    </td>
                    <td><b><?= h($e['program_title']) ?></b></td>
                    <td><span class="badge badge-gray"><?= strtoupper($e['source']) ?></span></td>
                    <td><?= date('M j, Y', strtotime($e['starts_at'])) ?></td>
                    <td>
                      <?php if ($e['expires_at']): ?>
                        <?= date('M j, Y', strtotime($e['expires_at'])) ?>
                      <?php else: ?>
                        <span class="small muted">Lifetime</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge <?= $e['status'] === 'active' ? 'badge-green' : 'badge-red' ?>">
                        <?= strtoupper($e['status']) ?>
                      </span>
                    </td>
                    <td class="right">
                      <?php if ($e['status'] === 'active'): ?>
                        <form method="post" onsubmit="return confirm('Revoke program access for this student?');" style="display: inline;">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="revoke">
                          <input type="hidden" name="enrollment_id" value="<?= (int)$e['id'] ?>">
                          <button type="submit" class="btn btn-sm btn-outline" style="color: var(--red); border-color: #fca5a5;">Revoke</button>
                        </form>
                      <?php endif; ?>
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
