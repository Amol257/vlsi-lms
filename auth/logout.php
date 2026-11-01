<?php
require_once __DIR__ . '/../db.php';

$user = auth_user();

// Check if request is a confirmed POST or carries a verified CSRF token
$is_post = ($_SERVER['REQUEST_METHOD'] === 'POST');
$token = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? '');
$is_confirmed = $is_post && verify_csrf_token($token);

// If GET request without valid CSRF confirmation, render confirmation form to prevent logout CSRF
if (!$is_confirmed) {
    if (!$user) {
        // Already logged out or session expired
        header("Location: /login.php");
        exit;
    }
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Sign Out Confirmation - VLSI LMS</title>
      <link rel="stylesheet" href="/assets/style.css">
    </head>
    <body style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f8fafc; font-family: 'Inter', sans-serif;">
      <div class="card" style="max-width: 420px; width: 100%; text-align: center; padding: 32px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border-radius: 12px; background: #ffffff;">
        <h2 style="font-size: 20px; margin-bottom: 8px;">Confirm Sign Out</h2>
        <p style="color: #64748b; font-size: 14px; margin-bottom: 24px;">Are you sure you want to end your learning session on this device?</p>
        <form method="POST" action="/auth/logout.php" style="display: flex; gap: 12px; justify-content: center;">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-primary" style="background: #ef4444; border-color: #ef4444; color: #fff; padding: 8px 18px; border-radius: 6px; font-weight: 500; cursor: pointer;">Yes, Sign Out</button>
          <a href="javascript:history.back()" class="btn" style="padding: 8px 18px; border-radius: 6px; text-decoration: none; border: 1px solid #cbd5e1; color: #334155; font-weight: 500;">Cancel</a>
        </form>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// Perform authenticated logout cleanup
if ($user) {
    $uid = $user['id'];
    $pdo = get_db();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("DELETE FROM lms_active_session WHERE user_id = ?");
            $stmt->execute([$uid]);
        } catch (PDOException $e) {
            error_log("Failed to clear active session on logout: " . $e->getMessage());
        }
    }
    lms_audit_log($uid, 'user_logout', 'lms_users', $uid, []);
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}
session_destroy();

$supabase_url = lms_config('supabase.url');
$supabase_anon_key = lms_config('supabase.anon_key');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Signing out - VLSI LMS</title>
<script src="/assets/vendor/supabase.min.js" integrity="sha384-o2Uz8M8jf3RRbNF9dFLYtjA2ohqUgp6vUxr43Uzvt8MJkkLNvpXvLCUVbWh+cadK" crossorigin="anonymous"></script>
</head>
<body style="font-family: sans-serif; text-align: center; padding-top: 50px;">
<p>Signing out safely...</p>
<script>
const SUPABASE_URL = <?= json_encode($supabase_url) ?>;
const SUPABASE_ANON_KEY = <?= json_encode($supabase_anon_key) ?>;
if (SUPABASE_URL && SUPABASE_ANON_KEY) {
  try {
    const client = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);
    client.auth.signOut().finally(() => {
      window.location.href = '/login.php';
    });
  } catch(e) {
    window.location.href = '/login.php';
  }
} else {
  window.location.href = '/login.php';
}
</script>
</body>
</html>
