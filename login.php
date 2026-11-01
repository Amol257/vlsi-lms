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

$reason = $_GET['reason'] ?? '';
if ($reason === 'other-device') {
    $error = 'Your account was signed in on another device. This session has ended.';
} elseif ($reason === 'expired') {
    $error = 'Your session has expired. Please sign in again.';
} elseif ($reason === 'idle') {
    $error = 'Signed out due to inactivity. Please sign in again.';
}

$redirect_param = $_GET['redirect'] ?? '';
$safe_redirect = (str_starts_with($redirect_param, '/') && !str_starts_with($redirect_param, '//') && !str_contains($redirect_param, '://') && !str_contains($redirect_param, '\\'))
    ? $redirect_param
    : '';

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
<title>Sign in - VLSI LMS</title>
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

    <div id="errorAlert" style="<?= empty($error) ? 'display:none;' : '' ?> background: var(--red-soft); color: var(--red); padding: 10px 14px; border-radius: 8px; font-weight: 500; margin-bottom: 14px;">
      <?= h($error) ?>
    </div>

    <div id="infoAlert" style="display:none; background: #e0f2fe; color: #0369a1; padding: 10px 14px; border-radius: 8px; font-weight: 500; margin-bottom: 14px;"></div>

    <?php if ($google_oauth_enabled && !empty($supabase_url)): ?>
      <button type="button" id="googleBtn" class="btn btn-block" style="display: flex; align-items: center; justify-content: center; gap: 8px; background: #ffffff; border: 1px solid var(--line); color: var(--ink); margin-bottom: 16px;">
        <svg width="18" height="18" viewBox="0 0 24 24">
          <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.8-2.4 3.66v3.04h3.88c2.27-2.09 3.66-5.17 3.66-9.14z"/>
          <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.04c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.26v3.13C3.26 21.36 7.35 24 12 24z"/>
          <path fill="#FBBC05" d="M5.28 14.28c-.24-.72-.38-1.49-.38-2.28s.14-1.56.38-2.28V6.59H1.26C.46 8.19 0 9.99 0 12s.46 3.81 1.26 5.41l4.02-3.13z"/>
          <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.35 0 3.26 2.64 1.26 6.59l4.02 3.13c.95-2.83 3.6-4.97 6.72-4.97z"/>
        </svg>
        Continue with Google
      </button>
      <div style="text-align: center; position: relative; margin: 16px 0;">
        <hr style="border: 0; border-top: 1px solid var(--line);">
        <span style="position: absolute; top: -10px; left: 50%; transform: translateX(-50%); background: #ffffff; padding: 0 10px; font-size: 12px; color: var(--muted);">or with email</span>
      </div>
    <?php endif; ?>

    <form class="form" id="loginForm">
      <input type="hidden" id="csrfToken" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <label>Email
        <input type="email" id="emailInput" name="email" placeholder="name@domain.com" required autofocus>
      </label>
      <label>
        <div style="display: flex; justify-content: space-between;">
          <span>Password</span>
          <a href="#" id="toggleForgot" style="font-size: 12px; color: var(--primary);">Forgot password?</a>
        </div>
        <input type="password" id="passInput" name="password" placeholder="Enter your password" required>
      </label>
      <button class="btn btn-primary btn-block" type="submit" id="submitBtn" style="margin-top: 8px;">Sign in</button>
    </form>

    <div id="forgotBox" style="display: none; margin-top: 16px; padding: 14px; background: #f8fafc; border: 1px solid var(--line); border-radius: 8px;">
      <h3 style="margin-top: 0; font-size: 14px;">Reset Password</h3>
      <p class="small muted">Enter your email address and we will send you a password recovery link.</p>
      <div style="display: flex; gap: 8px;">
        <input type="email" id="resetEmail" placeholder="your@email.com" style="flex: 1; padding: 8px 12px; border: 1px solid var(--line); border-radius: 6px;">
        <button type="button" id="sendResetBtn" class="btn btn-sm btn-primary">Send Link</button>
      </div>
    </div>

    <p class="auth-alt">New student? <a href="/register.php">Create an account</a></p>
  </div>
</div>

<script>
const SUPABASE_URL = <?= json_encode($supabase_url) ?>;
const SUPABASE_ANON_KEY = <?= json_encode($supabase_anon_key) ?>;
const SITE_URL = <?= json_encode($site_url) ?>;
const CSRF_TOKEN = document.getElementById('csrfToken').value;
const REDIRECT_TARGET = <?= json_encode($safe_redirect) ?>;

let sb = null;
if (SUPABASE_URL && SUPABASE_ANON_KEY && typeof supabase !== 'undefined') {
  sb = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);
}

function showError(msg) {
  const el = document.getElementById('errorAlert');
  el.textContent = msg;
  el.style.display = 'block';
  document.getElementById('infoAlert').style.display = 'none';
}

function showInfo(msg) {
  const el = document.getElementById('infoAlert');
  el.textContent = msg;
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
      showError(err.message || 'Google sign in failed.');
    }
  });
}

// Forgot Password Toggle
const toggleForgot = document.getElementById('toggleForgot');
const forgotBox = document.getElementById('forgotBox');
toggleForgot.addEventListener('click', (e) => {
  e.preventDefault();
  forgotBox.style.display = forgotBox.style.display === 'none' ? 'block' : 'none';
  if (forgotBox.style.display === 'block') {
    document.getElementById('resetEmail').value = document.getElementById('emailInput').value;
  }
});

// Password Reset Request
const sendResetBtn = document.getElementById('sendResetBtn');
sendResetBtn.addEventListener('click', async () => {
  const email = document.getElementById('resetEmail').value.trim();
  if (!email) {
    showError('Please enter your email to receive a password reset link.');
    return;
  }
  if (!sb) {
    showError('Supabase authentication client is not available.');
    return;
  }

  sendResetBtn.disabled = true;
  sendResetBtn.textContent = 'Sending...';

  try {
    const redirectUrl = window.location.origin + '/auth/callback.php?type=recovery';
    const { error } = await sb.auth.resetPasswordForEmail(email, {
      redirectTo: redirectUrl
    });
    if (error) throw error;
    showInfo('Password recovery link has been sent to your email.');
    forgotBox.style.display = 'none';
  } catch (err) {
    showError(err.message || 'Failed to send password recovery email.');
  } finally {
    sendResetBtn.disabled = false;
    sendResetBtn.textContent = 'Send Link';
  }
});

// Login Form Submit
document.getElementById('loginForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const email = document.getElementById('emailInput').value.trim();
  const password = document.getElementById('passInput').value;
  const submitBtn = document.getElementById('submitBtn');

  if (!email || !password) {
    showError('Please provide both email and password.');
    return;
  }

  if (!sb) {
    showError('Authentication service is currently unavailable.');
    return;
  }

  submitBtn.disabled = true;
  submitBtn.textContent = 'Signing in...';

  try {
    const { data, error } = await sb.auth.signInWithPassword({
      email: email,
      password: password
    });

    if (error) {
      throw error;
    }

    if (!data.session || !data.session.access_token) {
      throw new Error('No session returned from authentication provider.');
    }

    // Exchange Supabase token for secure PHP session
    const formData = new FormData();
    formData.append('access_token', data.session.access_token);
    formData.append('csrf_token', CSRF_TOKEN);

    const res = await fetch('/auth/session.php', {
      method: 'POST',
      body: formData,
      headers: {
        'Accept': 'application/json'
      }
    });

    const sessionRes = await res.json();
    if (!res.ok) {
      if (res.status === 409) {
        // Disconnect Supabase client session if LMS session rejected concurrency
        await sb.auth.signOut();
      }
      throw new Error(sessionRes.error || 'Server rejected session exchange.');
    }

    function isSafePath(url) {
      return typeof url === 'string' && url.startsWith('/') && !url.startsWith('//') && !url.includes('://') && !url.includes('\\');
    }
    const targetUrl = isSafePath(REDIRECT_TARGET) ? REDIRECT_TARGET : (sessionRes.redirect || '/student/index.php');
    submitBtn.textContent = 'Success! Redirecting...';
    window.location.href = isSafePath(targetUrl) ? targetUrl : '/student/index.php';

  } catch (err) {
    showError(err.message || 'Authentication failed. Please check your credentials.');
    submitBtn.disabled = false;
    submitBtn.textContent = 'Sign in';
  }
});
</script>
</body>
</html>
