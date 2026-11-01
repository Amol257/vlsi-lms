<?php
/**
 * VLSI LMS : Temporary Admin Diagnostic Page - Proxy and IP Header Inspector
 * Accessible only to authenticated Administrators. Remove or disable after deployment.
 */

require_once __DIR__ . '/../db.php';
$admin = require_role(['admin']);

$headers_to_check = [
    'REMOTE_ADDR'          => $_SERVER['REMOTE_ADDR'] ?? 'not set',
    'HTTP_X_FORWARDED_FOR' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'not set',
    'HTTP_CF_CONNECTING_IP'=> $_SERVER['HTTP_CF_CONNECTING_IP'] ?? 'not set',
    'HTTP_X_REAL_IP'       => $_SERVER['HTTP_X_REAL_IP'] ?? 'not set',
    'HTTP_X_FORWARDED_PROTO'=> $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'not set',
    'SERVER_PORT'          => $_SERVER['SERVER_PORT'] ?? 'not set',
    'HTTPS'                => $_SERVER['HTTPS'] ?? 'not set'
];

$resolved_ip = get_client_ip();
$trusted_proxies = lms_config('app.trusted_proxies', []);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>IP & Proxy Diagnostics - VLSI LMS</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="role-admin">
<div class="shell">
  <div class="main-body" style="max-width: 800px; margin: 40px auto; padding: 24px;">
    <div class="card">
      <div class="card-head">
        <h2>Hostinger Proxy & IP Header Diagnostics</h2>
        <a href="/admin/index.php" class="btn btn-sm">Back to Dashboard</a>
      </div>
      <p class="muted" style="margin-top: 8px;">
        Use this page to inspect which IP headers Hostinger edge servers pass to PHP.
        Only trust headers when <code>REMOTE_ADDR</code> matches Hostinger reverse proxies.
      </p>

      <table class="data-table" style="margin-top: 20px;">
        <thead>
          <tr>
            <th>Header / Variable</th>
            <th>Value Received by Server</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($headers_to_check as $key => $val): ?>
          <tr>
            <td><code><?= h($key) ?></code></td>
            <td><strong><?= h($val) ?></strong></td>
          </tr>
          <?php endforeach; ?>
          <tr style="background: #f1f5f9;">
            <td><strong>Resolved LMS Client IP</strong></td>
            <td><strong style="color: #2563eb;"><?= h($resolved_ip) ?></strong></td>
          </tr>
        </tbody>
      </table>

      <div style="margin-top: 24px;">
        <h3>Configured Trusted Proxies</h3>
        <pre style="background: #0f172a; color: #f8fafc; padding: 12px; border-radius: 6px; font-size: 13px;"><?= h(json_encode($trusted_proxies, JSON_PRETTY_PRINT)) ?></pre>
        <p class="small muted" style="margin-top: 8px;">
          To configure trusted proxies, add CIDRs or IPs to <code>app.trusted_proxies</code> in <code>config.local.php</code>:
          <br><code>'app' => ['trusted_proxies' => ['127.0.0.1', '10.0.0.0/8', ...]]</code>
        </p>
      </div>
    </div>
  </div>
</div>
</body>
</html>
