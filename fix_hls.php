<?php
/**
 * Diagnostic & HLS Path Normalizer
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/video_helper.php';

$hls_base = __DIR__ . '/lms_storage/hls';
$target_dir = $hls_base . '/d31b7a2dd5922df913a90b0bf2874e34';

if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}

$count = 0;
$source_dir = $hls_base . '/hls_free_lecture_d31b';
if (is_dir($source_dir)) {
    $files = scandir($source_dir);
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $clean_name = basename(str_replace('\\', '/', $f));
        $src_file = $source_dir . '/' . $f;
        $dst_file = $target_dir . '/' . $clean_name;
        if (rename($src_file, $dst_file)) {
            $count++;
        }
    }
    @rmdir($source_dir);
}

if (is_dir($hls_base)) {
    $root_files = scandir($hls_base);
    foreach ($root_files as $f) {
        if (strpos($f, 'd31b7a2dd5922df913a90b0bf2874e34\\') !== false || strpos($f, 'd31b7a2dd5922df913a90b0bf2874e34/') !== false) {
            $clean_name = basename(str_replace('\\', '/', $f));
            $src_file = $hls_base . '/' . $f;
            $dst_file = $target_dir . '/' . $clean_name;
            if (rename($src_file, $dst_file)) {
                $count++;
            }
        }
    }
}

$m3u8_exists = file_exists($target_dir . '/index.m3u8');
$seg0_exists = file_exists($target_dir . '/segment_0000.ts');
$total_in_target = is_dir($target_dir) ? count(scandir($target_dir)) - 2 : 0;

$key_enc = '0yPajjPGjvhY4LH4vQDRmkhwZy0480h3xzc3M/+59qdLY1Rw+xhRGT3Q3OZclAJuGUL6HerMBag=';
$raw_key = lms_decrypt_video_key($key_enc);
$key_ok = ($raw_key && strlen($raw_key) === 16);

header('Content-Type: text/plain');
echo "HLS Diagnostic Report:\n";
echo "Target Dir: {$target_dir}\n";
echo "Files in Target: {$total_in_target}\n";
echo "index.m3u8 exists: " . ($m3u8_exists ? "YES" : "NO") . "\n";
echo "segment_0000.ts exists: " . ($seg0_exists ? "YES" : "NO") . "\n";
echo "Key Decryption: " . ($key_ok ? "SUCCESS (16 bytes)" : "FAILED (Check APP_KEY in config.local.php)") . "\n";
if (!$key_ok) {
    echo "Current APP_KEY Prefix: " . substr(lms_config('app.app_key', ''), 0, 10) . "...\n";
}
