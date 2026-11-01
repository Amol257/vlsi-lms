<?php
/**
 * VLSI LMS : Temporary Admin Diagnostic Page - Proxy, IP and Path Inspector
 * Accessible only to authenticated Administrators. Remove or disable after deployment.
 */

require_once __DIR__ . '/../db.php';
$admin = require_role(['admin']);

// 1. Automatic Expiry Guard: return 404 once app.trusted_proxies is saved or expires_at passes
$trusted_proxies = lms_config('app.trusted_proxies', []);
$diag_expires = lms_config('diagnostics.expires_at');

$is_expired = false;
if (!empty($trusted_proxies)) {
    $is_expired = true;
}
if (!empty($diag_expires) && strtotime($diag_expires) !== false && time() > strtotime($diag_expires)) {
    $is_expired = true;
}

if ($is_expired) {
    http_response_code(404);
    die("Not found.");
}

// 2. Safe Header Inspection (strictly NO cookies, NO authorization headers, NO secrets)
$headers_to_check = [
    'REMOTE_ADDR'           => $_SERVER['REMOTE_ADDR'] ?? 'not set',
    'HTTP_X_FORWARDED_FOR'  => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'not set',
    'HTTP_CF_CONNECTING_IP' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? 'not set',
    'HTTP_X_REAL_IP'        => $_SERVER['HTTP_X_REAL_IP'] ?? 'not set',
    'HTTP_X_FORWARDED_PROTO'=> $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'not set',
    'SERVER_PORT'           => $_SERVER['SERVER_PORT'] ?? 'not set',
    'HTTPS'                 => $_SERVER['HTTPS'] ?? 'not set'
];

$resolved_ip = get_client_ip();

// 3. Path & Storage Diagnostics
$loaded_config = lms_get_loaded_config_path();
$candidate_configs = lms_get_candidate_config_paths();
$docroot = realpath($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__));
$real_config = $loaded_config ? realpath($loaded_config) : null;
$config_in_docroot = ($real_config && $docroot && str_starts_with($real_config, $docroot));

$storage_dir = lms_get_video_storage_dir();
$real_storage = realpath($storage_dir);
$storage_in_docroot = ($real_storage && $docroot && str_starts_with($real_storage, $docroot));
$storage_writable = is_writable($storage_dir);

// 4. Session Storage Diagnostics
$current_sess_path = session_save_path() ?: sys_get_temp_dir();
$is_private_sess = lms_is_custom_session_path_active();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Diagnostics - VLSI LMS</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="role-admin">
<div class="shell">
  <div class="main-body" style="max-width: 860px; margin: 40px auto; padding: 24px;">
    <div class="card">
      <div class="card-head">
        <h2>Hostinger Deployment &amp; IP Diagnostics</h2>
        <a href="/admin/index.php" class="btn btn-sm">Back to Dashboard</a>
      </div>
      <p class="muted" style="margin-top: 8px;">
        This temporary diagnostic utility inspects edge headers, paths, and directory permissions.
        Delete this file (<code>admin/diagnostics_ip.php</code>) immediately after completing proxy verification.
      </p>

      <!-- IP Header Diagnostics -->
      <h3 style="margin-top: 24px;">1. Reverse Proxy &amp; IP Headers</h3>
      <table class="data-table" style="margin-top: 10px;">
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

      <!-- Config & Path Diagnostics -->
      <h3 style="margin-top: 32px;">2. Configuration &amp; Storage Paths</h3>
      <table class="data-table" style="margin-top: 10px;">
        <tbody>
          <tr>
            <td><strong>Document Root (Web Served)</strong></td>
            <td><code><?= h($docroot ?: 'Unknown') ?></code></td>
          </tr>
          <tr>
            <td><strong>Resolved Configuration File</strong></td>
            <td>
              <code><?= h($loaded_config ?: 'None loaded (using example defaults)') ?></code>
              <?php if ($config_in_docroot): ?>
                <div style="color: #b91c1c; font-weight: 600; font-size: 13px; margin-top: 4px;">
                  Caution: Config file is located inside public web docroot. Move to parent directory outside public_html for optimal security.
                </div>
              <?php else: ?>
                <div style="color: #15803d; font-weight: 500; font-size: 13px; margin-top: 4px;">
                  Secure: Configuration file is located outside public web docroot.
                </div>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <td><strong>Resolved Video Storage Directory</strong></td>
            <td>
              <code><?= h($storage_dir) ?></code>
              <?php if ($storage_in_docroot): ?>
                <div style="color: #b91c1c; font-weight: 600; font-size: 13px; margin-top: 4px;">
                  Caution: Storage directory is inside public docroot. Protected only by .htaccess.
                </div>
              <?php else: ?>
                <div style="color: #15803d; font-weight: 500; font-size: 13px; margin-top: 4px;">
                  Secure: Storage directory is outside public web docroot.
                </div>
              <?php endif; ?>
              <div style="margin-top: 4px; font-size: 13px;">
                Writable by PHP: <?= $storage_writable ? '<strong style="color: #15803d;">YES (Writable)</strong>' : '<strong style="color: #b91c1c;">NO (Not writable - check permissions)</strong>' ?>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Candidate Config Search Paths -->
      <div style="margin-top: 20px;">
        <h4 style="font-size: 14px; margin-bottom: 6px;">Candidate Configuration Search Paths:</h4>
        <ul style="font-size: 13px; line-height: 1.6; color: #475569; padding-left: 20px;">
          <?php foreach ($candidate_configs as $name => $path): ?>
            <li>
              <strong><?= h($name) ?>:</strong> <code><?= h($path ?: 'Not set') ?></code>
              <?= ($path && file_exists($path)) ? '<span style="color: #15803d; font-weight: 600;">[Found]</span>' : '<span style="color: #94a3b8;">[Not present]</span>' ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Private PHP Session Storage Diagnostics -->
      <h3 style="margin-top: 32px;">3. PHP Session Storage</h3>
      <table class="data-table" style="margin-top: 10px;">
        <tbody>
          <tr>
            <td><strong>Current Session Save Path</strong></td>
            <td><code><?= h($current_sess_path) ?></code></td>
          </tr>
          <tr>
            <td><strong>Private Isolation Status</strong></td>
            <td>
              <?php if ($is_private_sess): ?>
                <span style="color: #15803d; font-weight: 600;">ACTIVE: Using dedicated private directory outside system temp.</span>
              <?php else: ?>
                <span style="color: #d97706; font-weight: 600;">DEFAULT: Sessions saved in shared system temp directory. To isolate sessions on shared hosting, create a private directory (e.g. <code>../lms_sessions</code> mode 0700) and specify <code>app.session_save_path</code>.</span>
              <?php endif; ?>
            </td>
          </tr>
        </tbody>
      </table>

      <div style="margin-top: 32px; padding: 16px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;">
        <h4 style="margin: 0 0 8px 0; color: #991b1b;">Auto-Expiry Policy</h4>
        <p class="small" style="margin: 0; color: #7f1d1d; line-height: 1.5;">
          Once you configure <code>app.trusted_proxies</code> in <code>config.local.php</code>, this page will automatically return <code>HTTP 404 Not Found</code> to protect server metadata.
        </p>
      </div>

    </div>
  </div>
</div>
</body>
</html>
