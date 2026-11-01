<?php
require_once __DIR__ . '/db.php';

$error = '';
$user = auth_user();
if ($user) {
    if ($user['role'] === 'admin') {
        header("Location: admin/index.php");
    } elseif ($user['role'] === 'teacher') {
        header("Location: teacher/index.php");
    } else {
        header("Location: student/index.php");
    }
    exit;
}

$pdo = get_db();
$batches = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, code, name FROM lms_batches WHERE status = 'active' ORDER BY id ASC");
        $batches = $stmt->fetchAll();
    } catch (PDOException $e) {
        $batches = [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $batch_id = !empty($_POST['batch_id']) ? (int)$_POST['batch_id'] : null;

    $client_ip = get_client_ip();
    if (!check_rate_limit('register_attempt', $client_ip, 5, 600)) {
        $error = 'Too many registration attempts. Please try again later.';
    } elseif (empty($name) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!$pdo) {
        $error = 'System temporarily unavailable. Please try again shortly.';
    } else {
        $check = $pdo->prepare("SELECT id FROM lms_users WHERE email = ? LIMIT 1");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'An account with this email address already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins = $pdo->prepare("INSERT INTO lms_users (name, email, password_hash, role, batch_id, status) VALUES (?, ?, ?, 'student', ?, 'active')");
            $ins->execute([$name, $email, $hash, $batch_id]);
            $uid = (int)$pdo->lastInsertId();

            session_regenerate_id(true);
            $_SESSION['auth_time'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['lms_user'] = [
                'id'       => $uid,
                'name'     => $name,
                'email'    => $email,
                'role'     => 'student',
                'batch_id' => $batch_id
            ];

            lms_audit_log($uid, 'user_register', 'lms_users', $uid, ['email' => $email]);

            header("Location: student/index.php");
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register - VLSI LMS</title>
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
    <h2>Join your VLSI class cohort and access real recorded labs and lectures.</h2>
    <ul>
      <li>High-definition recorded lectures with automatic resume</li>
      <li>Instructor notes, Verilog testbenches, and lab scripts</li>
      <li>Track completion progress towards tapeout certification</li>
    </ul>
  </div>

  <div class="auth-card">
    <h1>Create Student Account</h1>
    <p class="muted">Enter your details to access your enrolled class recordings.</p>

    <?php if (!empty($error)): ?>
      <div style="background: var(--red-soft); color: var(--red); padding: 10px 14px; border-radius: 8px; font-weight: 500; margin-bottom: 14px;">
        <?= h($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" class="form">
      <?= csrf_field() ?>
      <label>Full Name
        <input type="text" name="name" required placeholder="e.g. Rahul Sharma" autofocus>
      </label>
      <label>Email Address
        <input type="email" name="email" required placeholder="name@domain.com">
      </label>
      <label>Choose Password
        <input type="password" name="password" required placeholder="At least 6 characters">
      </label>
      <?php if (!empty($batches)): ?>
        <label>Cohort Program Batch
          <select name="batch_id" required>
            <?php foreach ($batches as $b): ?>
              <option value="<?= (int)$b['id'] ?>"><?= h($b['code']) ?> (<?= h($b['name']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php endif; ?>
      <button class="btn btn-primary btn-block" type="submit" style="margin-top: 8px;">Register Account</button>
    </form>

    <p class="auth-alt">Already enrolled? <a href="login.php">Sign in</a></p>
  </div>
</div>
</body>
</html>
