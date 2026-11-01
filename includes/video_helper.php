<?php
/**
 * VLSI LMS : Encrypted HLS Video Streaming Helpers
 */

require_once __DIR__ . '/bootstrap.php';

// Encrypt 16-byte raw AES key using APP_KEY (AES-256-CBC)
function lms_encrypt_video_key(string $raw_key_bytes, ?string $app_key_hex = null): string {
    if ($app_key_hex === null) {
        $app_key_hex = lms_config('app.app_key');
    }
    $key_bin = hex2bin(str_pad(substr($app_key_hex, 0, 64), 64, '0'));
    $iv = random_bytes(16);
    $ciphertext = openssl_encrypt($raw_key_bytes, 'aes-256-cbc', $key_bin, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $ciphertext);
}

// Decrypt key_enc back to 16-byte raw AES key
function lms_decrypt_video_key(string $key_enc_b64, ?string $app_key_hex = null): ?string {
    if ($app_key_hex === null) {
        $app_key_hex = lms_config('app.app_key');
    }
    $key_bin = hex2bin(str_pad(substr($app_key_hex, 0, 64), 64, '0'));
    $data = base64_decode($key_enc_b64);
    if (strlen($data) < 17) {
        return null;
    }
    $iv = substr($data, 0, 16);
    $ciphertext = substr($data, 16);
    $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key_bin, OPENSSL_RAW_DATA, $iv);
    return $decrypted !== false ? $decrypted : null;
}

// Generate timed HMAC token for playlist key gating
function lms_generate_key_token(int $user_id, int $video_id, int $ttl_seconds = 600): string {
    $app_key = lms_config('app.app_key', 'default_secret_key');
    $expires = time() + $ttl_seconds;
    $payload = "{$user_id}:{$video_id}:{$expires}";
    $signature = hash_hmac('sha256', $payload, $app_key);
    return rtrim(strtr(base64_encode("{$payload}:{$signature}"), '+/', '-_'), '=');
}

// Verify timed HMAC token
function lms_verify_key_token(string $token_urlsafe, int $expected_user_id, int $expected_video_id): bool {
    $app_key = lms_config('app.app_key', 'default_secret_key');
    $token_b64 = strtr($token_urlsafe, '-_', '+/');
    $token_str = base64_decode($token_b64);
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
    $expected_sig = hash_hmac('sha256', $payload, $app_key);
    return hash_equals($expected_sig, $sig);
}

// Get HLS storage base directory (protected)
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

// Calculate total video storage usage in bytes
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
