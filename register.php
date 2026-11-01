<?php
require_once __DIR__ . '/db.php';

$error = '';
$pdo = get_db();
$batches = [];
if ($pdo) {
    $stmt = $pdo->query("SELECT id, code, name FROM lms_batches WHERE status = 'active' ORDER BY id ASC");
    $batches = $stmt->fetchAll();
} else {
    $batches = [
        ['id' => 1, 'code' => 'PD-NOV-2026-WKND', 'name' => 'PD Weekend Batch (Nov 2026)'],
        ['id' => 2, 'code' => 'DV-FALL-2026', 'name' => 'DV Cohort 04 (Evening)']
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $batch_id = intval($_POST['batch_id'] ?? 1);

    if ($pdo) {
        $check = $pdo->prepare("SELECT id FROM lms_users WHERE email = ? LIMIT 1");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'An account with this email address already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins = $pdo->prepare("INSERT INTO lms_users (name, email, password_hash, role, batch_id) VALUES (?, ?, ?, 'student', ?)");
            $ins->execute([$name, $email, $hash, $batch_id]);
            $uid = $pdo->lastInsertId();

            $_SESSION['lms_user'] = [
                'id'       => $uid,
                'name'     => $name,
                'email'    => $email,
                'role'     => 'student',
                'batch_id' => $batch_id
            ];
            header("Location: student/index.php");
            exit;
        }
    } else {
        $_SESSION['lms_user'] = [
            'id'       => 10,
            'name'     => $name,
            'email'    => $email,
            'role'     => 'student',
            'batch_id' => $batch_id
        ];
        header("Location: student/index.php");
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register · VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-body">
<div class="auth-wrap">
  <div class="auth-side">
    <div class="brand brand-lg">
      <span class="brand-mark">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <rect x="6" y="6" width="12" height="12" rx="2"/>
          <path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/>
        </svg>
      </span>
      <span><b>VLSI Learning Portal</b></span>
    </div>
    <h2>Join your VLSI class cohort and access real recorded labs &amp; lectures.</h2>
    <ul>
      <li>High-definition recorded lectures with automatic resume</li>
      <li>Instructor notes, Verilog testbenches, and lab scripts</li>
      <li>Track completion progress towards tapeout certification</li>
    </ul>
  </div>

  <div class="auth-card">
    <h1>Create Student Account</h1>
    <p class="muted">Enter your details to access your enrolled class recordings.</p>

    <?php if ($error): ?>
      <div style="background: var(--red-soft); color: var(--red); padding: 10px 14px; border-radius: 8px; font-weight: 500; margin-bottom: 14px;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" class="form">
      <label>Full Name
        <input type="text" name="name" required placeholder="e.g. Rahul Sharma" autofocus>
      </label>
      <label>Email Address
        <input type="email" name="email" required placeholder="name@domain.com">
      </label>
      <label>Choose Password
        <input type="password" name="password" required placeholder="At least 6 characters">
      </label>
      <label>Cohort Program Batch
        <select name="batch_id" required>
          <?php foreach ($batches as $b): ?>
            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['code']) ?> (<?= htmlspecialchars($b['name']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn btn-primary btn-block" type="submit" style="margin-top: 8px;">Register Account</button>
    </form>

    <p class="auth-alt">Already enrolled? <a href="login.php">Sign in</a></p>
  </div>
</div>
</body>
</html>
