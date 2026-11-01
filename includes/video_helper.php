<?php
/**
 * VLSI LMS : Encrypted HLS Video Streaming Helpers
 * Uses Libsodium secretbox with APP_KEY for video encryption keys at rest.
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * Retrieve validated 32-byte binary key derived from APP_KEY.
 * Fails closed if missing, placeholder, or default.
 */
function lms_get_app_key_bytes(?string $override = null): ?string {
    $raw = $override ?? lms_config('app.app_key');
    if (!$raw || !is_string($raw)) {
        return null;
    }
    $raw = trim($raw);

    // Fail closed on default, placeholder, or compromised keys
    if (
        str_contains($raw, '<') ||
        str_contains($raw, 'default') ||
        str_contains($raw, 'placeholder') ||
        $raw === '30e1799c8cb94460b6273182f6a3c351c8434102b5af47cba6aa79c069a7c357'
    ) {
        return null;
    }

    if (strlen($raw) === 64 && ctype_xdigit($raw)) {
        $bin = hex2bin($raw);
        return (strlen($bin) === 32) ? $bin : null;
    }

    if (strlen($raw) === 32) {
        return $raw;
    }

    return null;
}

/**
 * Encrypt 16-byte raw AES video key using sodium_crypto_secretbox.
 * Returns base64(nonce + ciphertext), or null on failure.
 */
function lms_encrypt_video_key(string $raw_key_bytes, ?string $app_key = null): ?string {
    $key_bin = lms_get_app_key_bytes($app_key);
    if ($key_bin === null || strlen($raw_key_bytes) !== 16) {
        return null;
    }

    try {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($raw_key_bytes, $nonce, $key_bin);
        return base64_encode($nonce . $ciphertext);
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Decrypt base64(nonce + ciphertext) using sodium_crypto_secretbox_open.
 * Returns 16-byte raw AES key, or null on failure.
 */
function lms_decrypt_video_key(string $key_enc_b64, ?string $app_key = null): ?string {
    $key_bin = lms_get_app_key_bytes($app_key);
    if ($key_bin === null) {
        return null;
    }

    $decoded = base64_decode($key_enc_b64, true);
    if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        return null;
    }

    $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

    try {
        $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $key_bin);
        return ($decrypted !== false && strlen($decrypted) === 16) ? $decrypted : null;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Generate timed HMAC token for playlist and segment gating.
 * Refuses to issue tokens if APP_KEY is missing or invalid.
 */
function lms_generate_key_token(int $user_id, int $video_id, int $ttl_seconds = 7200): ?string {
    $key_bin = lms_get_app_key_bytes();
    if ($key_bin === null) {
        return null;
    }

    $expires = time() + $ttl_seconds;
    $payload = "{$user_id}:{$video_id}:{$expires}";
    $signature = hash_hmac('sha256', $payload, $key_bin);
    return rtrim(strtr(base64_encode("{$payload}:{$signature}"), '+/', '-_'), '=');
}

/**
 * Verify timed HMAC token.
 * Returns false if token is invalid, expired, or APP_KEY is missing.
 */
function lms_verify_key_token(string $token_urlsafe, int $expected_user_id, int $expected_video_id): bool {
    $key_bin = lms_get_app_key_bytes();
    if ($key_bin === null) {
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
    $expected_sig = hash_hmac('sha256', $payload, $key_bin);
    return hash_equals($expected_sig, $sig);
}

/**
 * Get HLS storage base directory (protected).
 */
function lms_get_video_storage_dir(): string {
    $parent_storage = dirname(__DIR__, 2) . '/lms_storage/hls';
    $local_storage = dirname(__DIR__) . '/lms_storage/hls';

    if (is_dir($parent_storage) || is_writable(dirname(__DIR__, 2))) {
        if (!is_dir($parent_storage)) {
            @mkdir($parent_storage, 0755, true);
        }
        return $parent_storage;
    }

    if (!is_dir($local_storage)) {
        @mkdir($local_storage, 0755, true);
    }
    return $local_storage;
}

/**
 * Calculate total video storage usage in bytes.
 */
function lms_get_video_storage_usage(): int {
    $dir = lms_get_video_storage_dir();
    $total_bytes = 0;
    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $total_bytes += $file->getSize();
        }
    }
    return $total_bytes;
}
