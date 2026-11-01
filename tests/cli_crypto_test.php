<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    die("Not found.\n");
}
require_once __DIR__ . '/../includes/video_helper.php';

// 1. Test compromised key rejection
$compromised = '30e1799c8cb94460b6273182f6a3c351c8434102b5af47cba6aa79c069a7c357';
if (lms_get_app_key_bytes($compromised) !== null) {
    die("FAIL: Compromised key was not rejected.\n");
}

// 2. Test short or invalid keys
if (lms_get_app_key_bytes('short_key') !== null) {
    die("FAIL: Short key was not rejected.\n");
}
if (lms_get_app_key_bytes('<64_HEX_CHARS_GENERATED_BY_ADMIN>') !== null) {
    die("FAIL: Placeholder was not rejected.\n");
}

// 3. Test valid 64-hex key
$valid_hex = bin2hex(random_bytes(32));
$bin = lms_get_app_key_bytes($valid_hex);
if ($bin === null || strlen($bin) !== 32) {
    die("FAIL: Valid hex key did not produce 32 bytes.\n");
}

// 4. Test video key encrypt / decrypt with HKDF subkeys
$raw_video_key = random_bytes(16);
$enc = lms_encrypt_video_key($raw_video_key, $valid_hex);
if ($enc === null) {
    die("FAIL: Encryption returned null.\n");
}
$dec = lms_decrypt_video_key($enc, $valid_hex);
if ($dec !== $raw_video_key) {
    die("FAIL: Decrypted key does not match original bytes.\n");
}

// 5. Test wrong key fails decryption
$wrong_hex = bin2hex(random_bytes(32));
$dec_wrong = lms_decrypt_video_key($enc, $wrong_hex);
if ($dec_wrong !== null) {
    die("FAIL: Wrong key did not fail decryption.\n");
}

// 6. Test storage usage cache
$usage = lms_get_video_storage_usage();
if (!isset($usage['bytes']) || !isset($usage['cached_at'])) {
    die("FAIL: Storage usage did not return expected array.\n");
}

echo "ALL_VIDEO_HELPER_TESTS_PASS\n";

