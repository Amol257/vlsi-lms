<?php
require_once __DIR__ . '/../db.php';

$user = auth_user();
if ($user) {
    $uid = $user['id'];
    $pdo = get_db();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("DELETE FROM lms_active_session WHERE user_id = ?");
            $stmt->execute([$uid]);
        } catch (PDOException $e) {
            // Table may not exist yet
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
