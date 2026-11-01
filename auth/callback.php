<?php
require_once __DIR__ . '/../db.php';

$supabase_url = lms_config('supabase.url');
$supabase_anon_key = lms_config('supabase.anon_key');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Authenticating - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/style.css">
<script src="/assets/vendor/supabase.min.js" integrity="sha384-o2Uz8M8jf3RRbNF9dFLYtjA2ohqUgp6vUxr43Uzvt8MJkkLNvpXvLCUVbWh+cadK" crossorigin="anonymous"></script>
</head>
<body class="auth-body">
<div class="auth-wrap">
  <div class="auth-card" style="text-align: center; max-width: 440px; margin: 40px auto;">
    <h2 id="statusTitle">Authenticating...</h2>
    <p id="statusMsg" class="muted">Verifying your credentials with Supabase.</p>
    <div id="spinner" style="margin: 20px 0;">
      <svg width="36" height="36" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" style="animation: spin 1s linear infinite;">
        <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
        <path d="M12 2a10 10 0 0 1 10 10" />
      </svg>
    </div>
    <div id="errorBox" style="display:none; background: var(--red-soft); color: var(--red); padding: 12px; border-radius: 8px; font-weight: 500; margin-top: 14px;"></div>
    <p style="margin-top: 20px;"><a href="/login.php" class="btn btn-sm">Return to Login</a></p>
  </div>
</div>

<style>
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>

<script>
const SUPABASE_URL = <?= json_encode($supabase_url) ?>;
const SUPABASE_ANON_KEY = <?= json_encode($supabase_anon_key) ?>;
const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;

const client = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

async function handleAuthCallback() {
  const statusTitle = document.getElementById('statusTitle');
  const statusMsg = document.getElementById('statusMsg');
  const spinner = document.getElementById('spinner');
  const errorBox = document.getElementById('errorBox');

  try {
    const { data: { session }, error } = await client.auth.getSession();
    if (error || !session) {
      throw new Error(error ? error.message : 'No authentication session found.');
    }

    statusMsg.textContent = 'Exchanging session with LMS server...';

    const formData = new FormData();
    formData.append('access_token', session.access_token);
    formData.append('csrf_token', CSRF_TOKEN);

    const res = await fetch('/auth/session.php', {
      method: 'POST',
      body: formData,
      headers: {
        'Accept': 'application/json'
      }
    });

    const json = await res.json();
    if (!res.ok) {
      throw new Error(json.error || 'Server rejected session exchange.');
    }

    statusTitle.textContent = 'Welcome back!';
    statusMsg.textContent = 'Redirecting to your dashboard...';
    window.location.href = json.redirect || '/student/index.php';
  } catch (err) {
    spinner.style.display = 'none';
    statusTitle.textContent = 'Sign in Failed';
    statusMsg.textContent = 'Could not complete authentication.';
    errorBox.textContent = err.message;
    errorBox.style.display = 'block';
  }
}

document.addEventListener('DOMContentLoaded', handleAuthCallback);
</script>
</body>
</html>
