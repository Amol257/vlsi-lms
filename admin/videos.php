<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/video_helper.php';

$admin = require_role(['admin']);
$pdo = get_db();

$notice = '';
$error = '';

// Storage usage calculation against 100 GB quota
$used_bytes = lms_get_video_storage_usage();
$quota_bytes = 100 * 1024 * 1024 * 1024; // 100 GB
$used_gb = round($used_bytes / (1024 * 1024 * 1024), 2);
$quota_percent = round(($used_bytes / $quota_bytes) * 100, 1);

// Handle Scan & Import from media/incoming
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'scan_incoming') {
        $incoming_dir = dirname(__DIR__) . '/media/incoming';
        $storage_base = lms_get_video_storage_dir();
        $imported_count = 0;
        $import_errors = [];

        if (is_dir($incoming_dir)) {
            $items = scandir($incoming_dir);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $pkg_dir = $incoming_dir . '/' . $item;
                $manifest_file = $pkg_dir . '/manifest.json';
                $legacy_info = $pkg_dir . '/packaging_info.json';
                $m3u8_file = $pkg_dir . '/index.m3u8';

                if (is_dir($pkg_dir) && file_exists($m3u8_file)) {
                    $raw_key = null;
                    $title = 'Uploaded Lecture';
                    $duration = 3600;
                    $size = 0;

                    if (file_exists($manifest_file)) {
                        $manifest = json_decode(file_get_contents($manifest_file), true);
                        if (!empty($manifest['key_hex']) && strlen($manifest['key_hex']) === 32 && ctype_xdigit($manifest['key_hex'])) {
                            $raw_key = hex2bin($manifest['key_hex']);
                            $title = $manifest['title'] ?? $title;
                            $duration = (int)($manifest['duration_sec'] ?? 3600);
                            $size = (int)($manifest['size_bytes'] ?? 0);
                        }
                    } elseif (file_exists($legacy_info)) {
                        $info = json_decode(file_get_contents($legacy_info), true);
                        if (!empty($info['key_hex']) && strlen($info['key_hex']) === 32 && ctype_xdigit($info['key_hex'])) {
                            $raw_key = hex2bin($info['key_hex']);
                            $title = $info['title'] ?? $title;
                            $duration = (int)($info['duration_sec'] ?? 3600);
                            $size = (int)($info['size_bytes'] ?? 0);
                        }
                    }

                    if ($raw_key === null || strlen($raw_key) !== 16) {
                        $import_errors[] = "Package {$item}: Missing valid key_hex in manifest.json.";
                        continue;
                    }

                    $key_enc = lms_encrypt_video_key($raw_key);
                    if ($key_enc === null) {
                        $import_errors[] = "Package {$item}: Encryption failed. Verify APP_KEY is set and valid.";
                        continue;
                    }

                    $hls_dir_name = bin2hex(random_bytes(16));
                    $target_path = $storage_base . '/' . $hls_dir_name;

                    // Move package directory into protected storage
                    if (rename($pkg_dir, $target_path)) {
                        // Immediately purge manifest and plaintext keys from storage
                        @unlink($target_path . '/manifest.json');
                        @unlink($target_path . '/packaging_info.json');
                        @unlink($target_path . '/enc.key');
                        @unlink($target_path . '/keyinfo.txt');

                        // Default program_id = 1
                        $prog_id = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : 1;

                        $ins = $pdo->prepare("INSERT INTO videos (program_id, title, status, uploaded_by, hls_dir, key_enc, duration_sec, size_bytes, created_at) VALUES (?, ?, 'approved', ?, ?, ?, ?, ?, NOW())");
                        $ins->execute([$prog_id, $title, $admin['id'], $hls_dir_name, $key_enc, $duration, $size]);
                        $new_vid = (int)$pdo->lastInsertId();

                        lms_audit_log($admin['id'], 'import_video', 'videos', $new_vid, [
                            'title' => $title,
                            'hls_dir' => $hls_dir_name,
                            'size' => $size
                        ]);

                        $imported_count++;
                    }
                }
            }
        }

        if ($imported_count > 0) {
            $notice = "Successfully scanned and imported {$imported_count} HLS video package(s).";
        } elseif (!empty($import_errors)) {
            $error = implode(' ', $import_errors);
        } else {
            $notice = "No new packaged videos found in media/incoming/.";
        }
    } elseif ($action === 'update_status') {
        $vid = (int)($_POST['video_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        if ($vid > 0 && in_array($status, ['draft', 'pending', 'approved', 'rejected'], true)) {
            $upd = $pdo->prepare("UPDATE videos SET status = ? WHERE id = ?");
            $upd->execute([$status, $vid]);
            lms_audit_log($admin['id'], 'update_video_status', 'videos', $vid, ['status' => $status]);
            $notice = "Video status updated to {$status}.";
        }
    }
}

// Fetch all programs for assignment
$programs = [];
if ($pdo) {
    try {
        $programs = $pdo->query("SELECT id, title FROM programs ORDER BY id ASC")->fetchAll();
    } catch (PDOException $e) {}
}

// Fetch videos
$videos = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT v.*, p.title as program_title, u.name as uploader_name 
            FROM videos v 
            LEFT JOIN programs p ON v.program_id = p.id 
            LEFT JOIN lms_users u ON v.uploaded_by = u.id 
            ORDER BY v.id DESC");
        $videos = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Videos load error: " . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Video Library &amp; Disk Storage - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="role-admin">
<div class="shell">
  <aside class="sidebar">
    <a class="brand" href="/admin/index.php">
      <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/></svg></span>
      <span><b>VLSI LMS</b><small>Hostinger MySQL</small></span>
    </a>
    <nav>
      <a href="/admin/index.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span>Dashboard</span></a>
      <a href="/admin/videos.php" class="on"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="5 3 19 12 5 21 5 3"/></svg><span>Video Library</span></a>
      <a href="/admin/users.php?role=student"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2 .6 3.5 2.6 3.5 6"/></svg><span>Students</span></a>
      <a href="/admin/courses.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg><span>Courses &amp; Subjects</span></a>
      <a href="/admin/webinars.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 10l5-5v14l-5-5M4 6h11a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/></svg><span>Webinars</span></a>
      <a href="/admin/enrolments.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg><span>Enrolments</span></a>
      <a href="/admin/audit.php"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg><span>Audit Log</span></a>
    </nav>
    <div class="sidebar-footer">
      <a href="/logout.php" style="display: flex; align-items: center; gap: 8px; color: #f87171; font-weight: 500;">
        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span>Sign Out</span>
      </a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
      <h1>Video Library &amp; Streaming Management</h1>
    </header>

    <main class="content">
      <?php if (!empty($notice)): ?>
        <div style="background: #ecfdf5; color: #047857; padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; font-weight: 500;">
          <?= h($notice) ?>
        </div>
      <?php endif; ?>

      <!-- STORAGE QUOTA WIDGET -->
      <section class="card" style="margin-bottom: 24px; padding: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
          <div>
            <h3 style="margin: 0; font-size: 16px;">Hostinger Video Disk Storage Quota</h3>
            <span class="small muted">Storage limit allocated on Hostinger web hosting</span>
          </div>
          <div style="text-align: right;">
            <b><?= $used_gb ?> GB</b> / 100.00 GB (<?= $quota_percent ?>%)
          </div>
        </div>
        <div style="height: 10px; background: #e2e8f0; border-radius: 5px; overflow: hidden;">
          <div style="height: 100%; width: <?= min(100, $quota_percent) ?>%; background: <?= $quota_percent > 85 ? '#ef4444' : '#0284c7' ?>;"></div>
        </div>
      </section>

      <!-- SCAN INCOMING CARD -->
      <section class="card" style="margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
          <div>
            <h3 style="margin: 0 0 4px 0; font-size: 16px;">Scan &amp; Ingest Video Packages</h3>
            <p class="small muted" style="margin: 0;">Scan <code>media/incoming/</code> on server for HLS packages generated with <code>tools/package_video.sh</code> or <code>.ps1</code>.</p>
          </div>
          <form method="post" style="display: flex; gap: 10px; align-items: center;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="scan_incoming">
            <select name="program_id" style="padding: 8px 12px; font-size: 13px; border-radius: 6px; border: 1px solid var(--line);">
              <?php foreach ($programs as $p): ?>
                <option value="<?= (int)$p['id'] ?>">Assign to: <?= h($p['title']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Scan &amp; Ingest</button>
          </form>
        </div>
      </section>

      <!-- VIDEOS TABLE -->
      <section class="card flush">
        <div class="card-head pad">
          <h2 style="margin: 0; font-size: 18px;">Video Catalog (<?= count($videos) ?>)</h2>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Video Title</th>
                <th>Program</th>
                <th>Duration</th>
                <th>File Size</th>
                <th>Status</th>
                <th class="right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($videos)): ?>
                <tr><td colspan="6" style="text-align: center; color: var(--muted); padding: 24px;">No videos uploaded yet.</td></tr>
              <?php else: ?>
                <?php foreach ($videos as $v): ?>
                  <tr>
                    <td>
                      <b><?= h($v['title']) ?></b>
                      <div class="small muted">Directory: <code><?= h($v['hls_dir']) ?></code></div>
                    </td>
                    <td><?= h($v['program_title'] ?? 'General') ?></td>
                    <td><?= round(((int)$v['duration_sec']) / 60) ?>m</td>
                    <td><?= round(((int)$v['size_bytes']) / (1024 * 1024), 1) ?> MB</td>
                    <td>
                      <span class="badge <?= $v['status'] === 'approved' ? 'badge-green' : ($v['status'] === 'rejected' ? 'badge-red' : 'badge-blue') ?>">
                        <?= strtoupper($v['status']) ?>
                      </span>
                    </td>
                    <td class="right" style="white-space: nowrap;">
                      <a href="/student/watch.php?id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-outline" target="_blank" style="margin-right: 6px;">Preview</a>
                      <form method="post" style="display: inline-block;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="video_id" value="<?= (int)$v['id'] ?>">
                        <select name="status" onchange="this.form.submit()" style="padding: 4px 8px; font-size: 12px; border-radius: 6px;">
                          <option value="draft" <?= $v['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                          <option value="pending" <?= $v['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                          <option value="approved" <?= $v['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                          <option value="rejected" <?= $v['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</div>
<script src="/assets/app.js"></script>
</body>
</html>
