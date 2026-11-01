<?php
/**
 * VLSI LMS : Encrypted HLS Video Streaming Helpers
 * Uses Libsodium secretbox with HKDF derived keys for video encryption keys at rest.
 * Uses HKDF derived token signing keys for gated HLS segments and playlists.
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * Retrieve validated 32-byte binary key derived from APP_KEY.
 * Accepts strictly 64 hex characters. Fails closed on missing, placeholder, or leaked keys.
 */
function lms_get_app_key_bytes(?string $override = null): ?string {
    $raw = $override ?? lms_config('app.app_key');
    if (!$raw || !is_string($raw)) {
        return null;
    }
    $raw = trim($raw);

    // Fail closed on default, placeholder, or angle-bracketed templates
    if (
        str_contains($raw, '<') ||
        str_contains($raw, 'default') ||
        str_contains($raw, 'placeholder')
    ) {
        return null;
    }

    // Fail closed on known compromised leaked key via SHA-256 hash comparison (no literal leaked key in repo)
    if (hash('sha256', $raw) === '18ba1ce0e10c3b7887b9b2e2fd3958dc514a5289a9140857756114fa9114057a') {
        return null;
    }

    // Require strictly 64 hexadecimal characters (256-bit entropy)
    if (strlen($raw) === 64 && ctype_xdigit($raw)) {
        $bin = hex2bin($raw);
        return (strlen($bin) === 32) ? $bin : null;
    }

    return null;
}

/**
 * Derive specialized 32-byte subkey using HKDF-SHA256.
 */
function lms_derive_subkey(string $context, ?string $app_key = null): ?string {
    $root_key = lms_get_app_key_bytes($app_key);
    if ($root_key === null) {
        return null;
    }
    return hash_hkdf('sha256', $root_key, 32, $context);
}

/**
 * Encrypt 16-byte raw AES video key using sodium_crypto_secretbox with HKDF encryption subkey.
 * Returns base64(nonce + ciphertext), or null on failure.
 */
function lms_encrypt_video_key(string $raw_key_bytes, ?string $app_key = null): ?string {
    $enc_key = lms_derive_subkey('lms-video-key-enc', $app_key);
    if ($enc_key === null || strlen($raw_key_bytes) !== 16) {
        return null;
    }

    try {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($raw_key_bytes, $nonce, $enc_key);
        return base64_encode($nonce . $ciphertext);
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Decrypt base64(nonce + ciphertext) using sodium_crypto_secretbox_open with HKDF encryption subkey.
 * Returns 16-byte raw AES key, or null on failure.
 */
function lms_decrypt_video_key(string $key_enc_b64, ?string $app_key = null): ?string {
    $enc_key = lms_derive_subkey('lms-video-key-enc', $app_key);
    if ($enc_key === null) {
        return null;
    }

    $decoded = base64_decode($key_enc_b64, true);
    if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        return null;
    }

    $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

    try {
        $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $enc_key);
        return ($decrypted !== false && strlen($decrypted) === 16) ? $decrypted : null;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Generate timed HMAC token for playlist and segment gating using HKDF token subkey.
 * Refuses to issue tokens if APP_KEY is missing or invalid.
 */
function lms_generate_key_token(int $user_id, int $video_id, int $ttl_seconds = 7200): ?string {
    $hmac_key = lms_derive_subkey('lms-token-hmac');
    if ($hmac_key === null) {
        return null;
    }

    $expires = time() + $ttl_seconds;
    $payload = "{$user_id}:{$video_id}:{$expires}";
    $signature = hash_hmac('sha256', $payload, $hmac_key);
    return rtrim(strtr(base64_encode("{$payload}:{$signature}"), '+/', '-_'), '=');
}

/**
 * Verify timed HMAC token with HKDF token subkey.
 * Returns false if token is invalid, expired, or APP_KEY is missing.
 */
function lms_verify_key_token(string $token_urlsafe, int $expected_user_id, int $expected_video_id): bool {
    $hmac_key = lms_derive_subkey('lms-token-hmac');
    if ($hmac_key === null) {
        return false;
    }

    $token_b64 = strtr($token_urlsafe, '-_', '+/');
    $token_str = base64_decode($token_b64, true);
    if (!$token_str) {
        return false;
    }

    $parts = explode(':', $token_str);
    if (count($parts) !== 4) {
        return false;
    }

    [$uid, $vid, $exp, $sig] = $parts;
    if ((int)$uid !== $expected_user_id || (int)$vid !== $expected_video_id) {
        return false;
    }

    if (time() > (int)$exp) {
        return false; // Token expired
    }

    $payload = "{$uid}:{$vid}:{$exp}";
    $expected_sig = hash_hmac('sha256', $payload, $hmac_key);
    return hash_equals($expected_sig, $sig);
}

/**
 * Shared Video Authorization Policy Helper.
 * Used by watch.php, video_playlist.php, video_key.php, and video_segment.php.
 * 
 * Rules:
 * - Admin and Teacher: always allowed.
 * - Student: video status must be 'approved' AND (is_trial = 1 OR user has active program enrollment).
 * 
 * @return array{allowed: bool, video: ?array, reason: string}
 */
function lms_check_video_access(array $user, int $video_id, ?PDO $pdo = null): array {
    $pdo = $pdo ?? get_db();
    if (!$pdo || $video_id <= 0) {
        return ['allowed' => false, 'video' => null, 'reason' => 'invalid_request'];
    }

    try {
        $stmt = $pdo->prepare("SELECT v.*, p.title as program_title, p.slug as program_slug 
            FROM videos v 
            JOIN programs p ON v.program_id = p.id 
            WHERE v.id = ? LIMIT 1");
        $stmt->execute([$video_id]);
        $video = $stmt->fetch();

        if (!$video) {
            return ['allowed' => false, 'video' => null, 'reason' => 'video_not_found'];
        }

        $role = $user['role'] ?? 'student';
        if ($role === 'admin' || $role === 'teacher') {
            return ['allowed' => true, 'video' => $video, 'reason' => 'authorized_role'];
        }

        // Students strictly require 'approved' status
        if ($video['status'] !== 'approved') {
            return ['allowed' => false, 'video' => null, 'reason' => 'unapproved_video'];
        }

        // Free preview / trial video
        if (!empty($video['is_trial'])) {
            return ['allowed' => true, 'video' => $video, 'reason' => 'trial_access'];
        }

        // Check active, unexpired enrollment in program
        $enroll_stmt = $pdo->prepare("SELECT id FROM enrollments 
            WHERE user_id = ? AND program_id = ? AND status = 'active' 
            AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
        $enroll_stmt->execute([$user['id'], $video['program_id']]);
        if ($enroll_stmt->fetch()) {
            return ['allowed' => true, 'video' => $video, 'reason' => 'enrolled_access'];
        }

        return ['allowed' => false, 'video' => null, 'reason' => 'not_enrolled'];

    } catch (PDOException $e) {
        error_log("Video access policy check error: " . $e->getMessage());
        return ['allowed' => false, 'video' => null, 'reason' => 'db_error'];
    }
}

/**
 * Get HLS storage base directory.
 * Search order: configured 'video.storage_dir', then outside docroot, then protected local directory.
 * Does not auto-create directories on request.
 */
function lms_get_video_storage_dir(): string {
    $configured = lms_config('video.storage_dir');
    if (!empty($configured) && is_string($configured)) {
        return rtrim($configured, '/\\');
    }

    $home_storage = dirname(__DIR__, 3) . '/lms_storage/hls';
    if (is_dir($home_storage)) {
        return $home_storage;
    }

    return dirname(__DIR__) . '/lms_storage/hls';
}

/**
 * Calculate total video storage usage in bytes, cached for 10 minutes.
 * 
 * @return array{bytes: int, cached_at: int, cache_age: int}
 */
function lms_get_video_storage_usage(): array {
    $cache_file = sys_get_temp_dir() . '/lms_video_usage_cache.json';
    $now = time();

    if (file_exists($cache_file)) {
        $raw = @file_get_contents($cache_file);
        if ($raw) {
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data['bytes'], $data['cached_at'])) {
                $age = $now - (int)$data['cached_at'];
                if ($age < 600) {
                    $data['cache_age'] = $age;
                    return $data;
                }
            }
        }
    }

    $dir = lms_get_video_storage_dir();
    $total_bytes = 0;
    if (is_dir($dir)) {
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $total_bytes += $file->getSize();
                }
            }
        } catch (\Throwable $e) {
            error_log("Storage calculation error: " . $e->getMessage());
        }
    }

    $result = [
        'bytes' => $total_bytes,
        'cached_at' => $now,
        'cache_age' => 0
    ];
    @file_put_contents($cache_file, json_encode($result), LOCK_EX);
    return $result;
}
