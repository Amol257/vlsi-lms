<?php
/**
 * Diagnostic & Automatic Key Re-Encryption for Hostinger
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/video_helper.php';

$hls_base = __DIR__ . '/lms_storage/hls';
$target_dir = $hls_base . '/d31b7a2dd5922df913a90b0bf2874e34';

header('Content-Type: text/plain');
echo "=== Hostinger HLS & Key Sync ===\n";

$raw_aes_key = hex2bin('02d420f9e7162cdcea3f5194b511e361');
$new_key_enc = lms_encrypt_video_key($raw_aes_key);

if ($new_key_enc) {
    $pdo = get_db();
    if ($pdo) {
        $stmt = $pdo->prepare("UPDATE videos SET key_enc = ? WHERE hls_dir = 'd31b7a2dd5922df913a90b0bf2874e34'");
        $stmt->execute([$new_key_enc]);
        echo "1. Re-encrypted video key with Hostinger APP_KEY: SUCCESS\n";
    } else {
        echo "1. Database connection failed.\n";
    }
} else {
    echo "1. lms_encrypt_video_key returned null.\n";
}

$verify = lms_decrypt_video_key($new_key_enc);
echo "2. Key Decryption Test: " . (($verify && strlen($verify) === 16) ? "VERIFIED (16 bytes)" : "FAILED") . "\n";

$m3u8_exists = file_exists($target_dir . '/index.m3u8');
$seg0_exists = file_exists($target_dir . '/segment_0000.ts');
$total_in_target = is_dir($target_dir) ? count(scandir($target_dir)) - 2 : 0;

echo "3. Video Chunks in Target: {$total_in_target} segments\n";
echo "4. index.m3u8: " . ($m3u8_exists ? "READY" : "MISSING") . "\n";
echo "5. segment_0000.ts: " . ($seg0_exists ? "READY" : "MISSING") . "\n\n";

if ($verify && $m3u8_exists && $seg0_exists) {
    echo "SUCCESS: Everything is 100% ready!\n";
    echo "Now open https://learn.vlsiinsights.com/student/watch.php?id=1";
}
