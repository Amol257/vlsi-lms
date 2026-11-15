<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/video_helper.php';

$admin = require_role(['admin']);
$pdo = get_db();

$notice = '';
$error = '';

// Storage usage calculation against 100 GB quota
$usage = lms_get_video_storage_usage();
$used_bytes = (int)($usage['bytes'] ?? 0);
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
                        $is_trial = !empty($_POST['is_trial']) ? 1 : 0;

                        $ins = $pdo->prepare("INSERT INTO videos (program_id, title, is_trial, status, uploaded_by, hls_dir, key_enc, duration_sec, size_bytes, created_at) VALUES (?, ?, ?, 'approved', ?, ?, ?, ?, ?, NOW())");
                        $ins->execute([$prog_id, $title, $is_trial, $admin['id'], $hls_dir_name, $key_enc, $duration, $size]);
                        $new_vid = (int)$pdo->lastInsertId();

                        lms_audit_log($admin['id'], 'import_video', 'videos', $new_vid, [
                            'title' => $title,
                            'hls_dir' => $hls_dir_name,
                            'size' => $size,
                            'is_trial' => $is_trial
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
    } elseif ($action === 'toggle_trial') {
        $vid = (int)($_POST['video_id'] ?? 0);
        $is_trial = !empty($_POST['is_trial']) ? 1 : 0;
        if ($vid > 0) {
            $upd = $pdo->prepare("UPDATE videos SET is_trial = ? WHERE id = ?");
            $upd->execute([$is_trial, $vid]);
            lms_audit_log($admin['id'], 'toggle_video_trial', 'videos', $vid, ['is_trial' => $is_trial]);
            $notice = $is_trial ? "Marked video as Free Lecture." : "Removed Free Lecture status.";
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
  <?php 
$active_tab = 'videos';
include __DIR__ . '/../includes/sidebar_admin.php'; 
?>

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
            <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
              <input type="checkbox" name="is_trial" value="1">
              <span>Free Lecture</span>
            </label>
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
                      <?php if (!empty($v['is_trial'])): ?>
                        <span class="badge badge-blue" style="margin-left: 6px; font-size: 11px;">Free Lecture</span>
                      <?php endif; ?>
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
                      <form method="post" style="display: inline-block; margin-right: 6px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle_trial">
                        <input type="hidden" name="video_id" value="<?= (int)$v['id'] ?>">
                        <input type="hidden" name="is_trial" value="<?= !empty($v['is_trial']) ? '0' : '1' ?>">
                        <button type="submit" class="btn btn-sm btn-outline"><?= !empty($v['is_trial']) ? 'Unmark Free' : 'Mark Free' ?></button>
                      </form>
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
