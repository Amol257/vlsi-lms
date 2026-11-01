<?php
require_once __DIR__ . '/db.php';

$error = '';
$user = auth_user();
if ($user) {
    if ($user['role'] === 'admin') {
        header("Location: /admin/index.php");
    } elseif ($user['role'] === 'teacher') {
        header("Location: /teacher/index.php");
    } else {
        header("Location: /student/index.php");
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

$supabase_url = lms_config('supabase.url');
$supabase_anon_key = lms_config('supabase.anon_key');
$google_oauth_enabled = (bool)lms_config('supabase.google_oauth_enabled', true);
$site_url = lms_config('app.site_url', 'https://learn.vlsiinsights.com');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/style.css">
<script src="/assets/vendor/supabase.min.js" integrity="sha384-o2Uz8M8jf3RRbNF9dFLYtjA2ohqUgp6vUxr43Uzvt8MJkkLNvpXvLCUVbWh+cadK" crossorigin="anonymous"></script>
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

    <div id="errorAlert" style="display:none; background: var(--red-soft); color: var(--red); padding: 10px 14px; border-radius: 8px; font-weight: 500; margin-bottom: 14px;"></div>
    <div id="successAlert" style="display:none; background: #ecfdf5; color: #047857; padding: 12px 14px; border-radius: 8px; font-weight: 500; margin-bottom: 14px;"></div>

    <?php if ($google_oauth_enabled && !empty($supabase_url)): ?>
      <button type="button" id="googleBtn" class="btn btn-block" style="display: flex; align-items: center; justify-content: center; gap: 8px; background: #ffffff; border: 1px solid var(--line); color: var(--ink); margin-bottom: 16px;">
        <svg width="18" height="18" viewBox="0 0 24 24">
          <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.8-2.4 3.66v3.04h3.88c2.27-2.09 3.66-5.17 3.66-9.14z"/>
          <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.04c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.26v3.13C3.26 21.36 7.35 24 12 24z"/>
          <path fill="#FBBC05" d="M5.28 14.28c-.24-.72-.38-1.49-.38-2.28s.14-1.56.38-2.28V6.59H1.26C.46 8.19 0 9.99 0 12s.46 3.81 1.26 5.41l4.02-3.13z"/>
          <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.35 0 3.26 2.64 1.26 6.59l4.02 3.13c.95-2.83 3.6-4.97 6.72-4.97z"/>
        </svg>
        Sign up with Google
      </button>
      <div style="text-align: center; position: relative; margin: 16px 0;">
        <hr style="border: 0; border-top: 1px solid var(--line);">
        <span style="position: absolute; top: -10px; left: 50%; transform: translateX(-50%); background: #ffffff; padding: 0 10px; font-size: 12px; color: var(--muted);">or with email</span>
      </div>
    <?php endif; ?>

    <form class="form" id="registerForm">
      <input type="hidden" id="csrfToken" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <label>Full Name
        <input type="text" id="nameInput" name="name" required placeholder="e.g. Rahul Sharma" autofocus>
      </label>
      <label>Email Address
        <input type="email" id="emailInput" name="email" required placeholder="name@domain.com">
      </label>
      <label>Choose Password
        <input type="password" id="passInput" name="password" required placeholder="At least 6 characters">
      </label>
      <?php if (!empty($batches)): ?>
        <label>Cohort Program Batch
          <select id="batchInput" name="batch_id" required>
            <?php foreach ($batches as $b): ?>
              <option value="<?= (int)$b['id'] ?>"><?= h($b['code']) ?> (<?= h($b['name']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php endif; ?>
      <button class="btn btn-primary btn-block" type="submit" id="submitBtn" style="margin-top: 8px;">Register Account</button>
    </form>

    <p class="auth-alt">Already enrolled? <a href="/login.php">Sign in</a></p>
  </div>
</div>

<script>
const SUPABASE_URL = <?= json_encode($supabase_url) ?>;
const SUPABASE_ANON_KEY = <?= json_encode($supabase_anon_key) ?>;
const SITE_URL = <?= json_encode($site_url) ?>;
const CSRF_TOKEN = document.getElementById('csrfToken').value;

let sb = null;
if (SUPABASE_URL && SUPABASE_ANON_KEY && typeof supabase !== 'undefined') {
  sb = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);
}

function showError(msg) {
  const el = document.getElementById('errorAlert');
  el.textContent = msg;
  el.style.display = 'block';
  document.getElementById('successAlert').style.display = 'none';
}

function showSuccess(msg) {
  const el = document.getElementById('successAlert');
  el.innerHTML = msg;
  el.style.display = 'block';
  document.getElementById('errorAlert').style.display = 'none';
}

// Google OAuth
const googleBtn = document.getElementById('googleBtn');
if (googleBtn && sb) {
  googleBtn.addEventListener('click', async () => {
    try {
      const redirectUrl = window.location.origin + '/auth/callback.php';
      const { error } = await sb.auth.signInWithOAuth({
        provider: 'google',
        options: {
          redirectTo: redirectUrl
        }
      });
      if (error) throw error;
    } catch (err) {
      showError(err.message || 'Google sign up failed.');
    }
  });
}

// Register Form Submit
document.getElementById('registerForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const name = document.getElementById('nameInput').value.trim();
  const email = document.getElementById('emailInput').value.trim();
  const password = document.getElementById('passInput').value;
  const batchEl = document.getElementById('batchInput');
  const batchId = batchEl ? batchEl.value : null;
  const submitBtn = document.getElementById('submitBtn');

  if (!name || !email || !password) {
    showError('All fields are required.');
    return;
  }

  if (password.length < 6) {
    showError('Password must be at least 6 characters.');
    return;
  }

  if (!sb) {
    showError('Authentication service is currently unavailable.');
    return;
  }

  submitBtn.disabled = true;
  submitBtn.textContent = 'Creating account...';

  try {
    const { data, error } = await sb.auth.signUp({
      email: email,
      password: password,
      options: {
        data: {
          full_name: name,
          batch_id: batchId
        },
        emailRedirectTo: window.location.origin + '/auth/callback.php'
      }
    });

    if (error) {
      throw error;
    }

    if (data.session && data.session.access_token) {
      // Immediate session granted
      const formData = new FormData();
      formData.append('access_token', data.session.access_token);
      formData.append('csrf_token', CSRF_TOKEN);

      const res = await fetch('/auth/session.php', {
        method: 'POST',
        body: formData,
        headers: { 'Accept': 'application/json' }
      });

      const sessionRes = await res.json();
      if (!res.ok) {
        throw new Error(sessionRes.error || 'Server error setting up session.');
      }

      window.location.href = sessionRes.redirect || '/student/index.php';
    } else {
      // Email confirmation required
      document.getElementById('registerForm').style.display = 'none';
      if (googleBtn) googleBtn.style.display = 'none';
      showSuccess('Registration successful! A confirmation email has been sent to <b>' + email + '</b>. Please verify your email before signing in.');
    }

  } catch (err) {
    showError(err.message || 'Registration failed. Please try again.');
    submitBtn.disabled = false;
    submitBtn.textContent = 'Register Account';
  }
});
</script>
</body>
</html>
