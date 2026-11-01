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

$reason = $_GET['reason'] ?? '';
if ($reason === 'other-device') {
    $error = 'Your account was signed in on another device. This session has ended.';
} elseif ($reason === 'expired') {
    $error = 'Your session has expired. Please sign in again.';
} elseif ($reason === 'idle') {
    $error = 'Signed out due to inactivity. Please sign in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    $client_ip = get_client_ip();
    if (!check_rate_limit('login_attempt', $client_ip, 10, 300)) {
        $error = 'Too many sign in attempts. Please wait 5 minutes before trying again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $pdo = get_db();
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role, batch_id, status FROM lms_users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $row = $stmt->fetch();

            if ($row && ($row['status'] ?? 'active') === 'blocked') {
                $error = 'This account has been suspended. Please contact the administrator.';
            } elseif ($row && !empty($row['password_hash']) && password_verify($password, $row['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['auth_time'] = time();
                $_SESSION['last_activity'] = time();
                $_SESSION['lms_user'] = [
                    'id'       => (int)$row['id'],
                    'name'     => $row['name'],
                    'email'    => $row['email'],
                    'role'     => $row['role'],
                    'batch_id' => $row['batch_id'] ? (int)$row['batch_id'] : null
                ];

                lms_audit_log($row['id'], 'user_login', 'lms_users', $row['id'], ['method' => 'password']);

                if ($row['role'] === 'admin') {
                    header("Location: admin/index.php");
                } elseif ($row['role'] === 'teacher') {
                    header("Location: teacher/index.php");
                } else {
                    header("Location: student/index.php");
                }
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'System temporarily unavailable. Please try again shortly.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in - VLSI LMS</title>
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
    <h2>Recorded VLSI classes, organised by course, batch and subject.</h2>
    <ul>
      <li>Teachers upload lectures, notes and lab files</li>
      <li>Admins approve and publish to the right batch</li>
      <li>Students watch, resume and track their progress</li>
    </ul>
  </div>

  <div class="auth-card">
    <h1>Sign in</h1>
    <p class="muted">Enter your registered account credentials below.</p>

    <?php if (!empty($error)): ?>
      <div style="background: var(--red-soft); color: var(--red); padding: 10px 14px; border-radius: 8px; font-weight: 500; margin-bottom: 14px;">
        <?= h($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" class="form" id="loginForm">
      <?= csrf_field() ?>
      <label>Email
        <input type="email" id="emailInput" name="email" placeholder="name@domain.com" required autofocus>
      </label>
      <label>Password
        <input type="password" id="passInput" name="password" placeholder="Enter your password" required>
      </label>
      <button class="btn btn-primary btn-block" type="submit" style="margin-top: 8px;">Sign in</button>
    </form>

    <p class="auth-alt">New student? <a href="register.php">Create an account</a></p>
  </div>
</div>
</body>
</html>
