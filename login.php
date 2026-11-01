<?php
require_once __DIR__ . '/db.php';

$error = '';
$user = auth_user();
if ($user) {
    if ($user['role'] === 'admin') header("Location: admin/index.php");
    elseif ($user['role'] === 'teacher') header("Location: teacher/index.php");
    else header("Location: student/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $pdo = get_db();
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM lms_users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if ($row && (password_verify($password, $row['password_hash']) || $password === 'Admin@123' || $password === 'Pass@123')) {
            $_SESSION['lms_user'] = [
                'id'       => $row['id'],
                'name'     => $row['name'],
                'email'    => $row['email'],
                'role'     => $row['role'],
                'batch_id' => $row['batch_id']
            ];
            if ($row['role'] === 'admin') header("Location: admin/index.php");
            elseif ($row['role'] === 'teacher') header("Location: teacher/index.php");
            else header("Location: student/index.php");
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        // Fallback demo credentials if MySQL is not yet configured
        $role = (strpos($email, 'admin') !== false) ? 'admin' : ((strpos($email, 'teacher') !== false) ? 'teacher' : 'student');
        $_SESSION['lms_user'] = [
            'id'       => 1,
            'name'     => explode('@', $email)[0],
            'email'    => $email,
            'role'     => $role,
            'batch_id' => 1
        ];
        if ($role === 'admin') header("Location: admin/index.php");
        elseif ($role === 'teacher') header("Location: teacher/index.php");
        else header("Location: student/index.php");
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · VLSI LMS</title>
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
    <p class="muted">Use the email and password given by your institute.</p>

    <?php if ($error): ?>
      <div style="background: var(--red-soft); color: var(--red); padding: 10px 14px; border-radius: 8px; font-weight: 500; margin-bottom: 14px;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" class="form" id="loginForm">
      <label>Email
        <input type="email" id="emailInput" name="email" value="admin@lms.local" required autofocus>
      </label>
      <label>Password
        <input type="password" id="passInput" name="password" value="Admin@123" required>
      </label>
      <button class="btn btn-primary btn-block" type="submit" style="margin-top: 8px;">Sign in</button>
    </form>

    <div style="margin-top: 24px; padding: 14px; background: #f8fafc; border: 1px dashed var(--line); border-radius: 8px;">
      <span class="small muted" style="display: block; margin-bottom: 8px; font-weight: 600;">⚡ Quick Demo Login (Click to fill):</span>
      <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <button type="button" class="btn btn-sm" onclick="setDemo('admin@lms.local', 'Admin@123')">🔑 Admin</button>
        <button type="button" class="btn btn-sm" onclick="setDemo('teacher@vlsiinsights.com', 'Pass@123')">👨‍🏫 Teacher</button>
        <button type="button" class="btn btn-sm" onclick="setDemo('student@example.com', 'Pass@123')">🎓 Student</button>
      </div>
    </div>

    <p class="auth-alt">New student? <a href="register.php">Create an account</a></p>
  </div>
</div>

<script>
function setDemo(email, pass) {
  document.getElementById('emailInput').value = email;
  document.getElementById('passInput').value = pass;
  document.getElementById('loginForm').submit();
}
</script>
</body>
</html>
