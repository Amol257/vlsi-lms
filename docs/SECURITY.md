# VLSI LMS Security Architecture and Hardening Specification

This document details the multi-layered security controls implemented across the VLSI Insights LMS portal (`learn.vlsiinsights.com`).

---

## 1. Authentication Security (Supabase Auth Server-Side Verification)

1. Shared Identity, Separate Sessions:
   - User identities reside in the official Supabase project (`riipijrgucqigndcjwre`).
   - No cross-site session cookies or client-side trust assumptions.
   - The browser exchanges the Supabase bearer access token with `/auth/session.php`.
2. Strict Server-Side Validation:
   - PHP queries `GET {SUPABASE_URL}/auth/v1/user` with the bearer token and anon key.
   - Tokens that are expired, fabricated, or belong to unconfirmed email addresses are immediately rejected with HTTP 401 or 403.
   - User roles are strictly determined by the LMS MySQL database and `ADMIN_BOOTSTRAP_EMAILS` configuration, never accepted from client input.
3. No Password Dependency:
   - The LMS does not manage or store cleartext passwords.
   - Bcrypt password columns are nullable and deprecated in favor of Supabase cryptographic token verification.

---

## 2. Session Hardening & Lifecycle

1. Cookie Configuration:
   - `session.use_strict_mode = 1`
   - `session.use_only_cookies = 1`
   - `HttpOnly`: true (mitigates XSS cookie exfiltration)
   - `Secure`: true when HTTPS is detected
   - `SameSite`: Lax (protects against cross-site request forgery)
2. Timeouts:
   - 8-hour idle timeout (`session_idle_timeout = 28800`)
   - 24-hour absolute session lifetime (`session_absolute_timeout = 86400`)
   - Expired sessions are destroyed server-side and redirected to `/login.php?reason=expired`.
3. Session Regeneration:
   - `session_regenerate_id(true)` is executed upon every authentication event to prevent session fixation attacks.

---

## 3. Student Single Active Device Lock (Heartbeat Concurrency Gate)

1. Concurrency Model:
   - Applied strictly to accounts with the role `student`.
   - Tracked via table `lms_active_session` (`user_id` PK, `session_id`, `ip`, `user_agent`, `last_seen`).
2. Login Concurrency Gate:
   - At `/auth/session.php`, a database transaction with `SELECT ... FOR UPDATE` inspects `last_seen`.
   - If an active session was observed within the last 150 seconds on another device, login is rejected with HTTP 409 Conflict.
3. Heartbeat Verification:
   - The student layout executes a background ping every 30 seconds to `/api/heartbeat.php`.
   - If the session ID does not match `lms_active_session`, the session is terminated and the client is redirected to `/login.php?reason=other-device`.
4. Administrator Reset:
   - Administrators can clear a locked device session via `/api/admin_reset_session.php`, logged to the immutable audit log.

---

## 4. Video Protection & Encryption Pipeline

1. Workstation Packaging:
   - Slices video into 6-second `.ts` segments using AES-128 encryption.
   - Generates random 16-byte key and writes temporary `manifest.json`.
   - Plaintext keys (`enc.key`, `keyinfo.txt`) are deleted immediately after slicing.
2. Server Key Encryption at Rest:
   - Ingest encrypts the 16-byte key using Libsodium secretbox (`sodium_crypto_secretbox`) with the server `APP_KEY`.
   - Stored as `base64(nonce + ciphertext)` in `videos.key_enc`.
   - Fails closed if `APP_KEY` is missing or default.
   - Temporary `manifest.json` is purged immediately upon import.
3. Tokenized Key Gating:
   - Playlist endpoint `/api/video_playlist.php` generates a signed HMAC token.
   - Key delivery endpoint `/api/video_key.php` validates the HMAC signature, confirms active student session lock, and decrypts the key on demand via `sodium_crypto_secretbox_open`.
   - Key delivery is the only place the raw key is decrypted and delivered; it is never logged.
   - Burst detection: logs a security alert to `audit_log` if rapid repeated key requests are detected.
4. Dynamic Watermarking:
   - A moving text watermark displays student email, client IP, and real-time clock across the video viewport to discourage screen recording.
5. Known Limitations of AES-128 Gated Streaming:
   - AES-128 HLS encryption stops casual downloading and direct link sharing. However, because AES-128 keys are delivered to standard web video players, any enrolled student with a valid session can theoretically inspect network requests, capture the decryption key and encrypted segments, and reassemble/decrypt them offline with a script.
   - It also cannot prevent external screen recording software.
   - Therefore, forensic dynamic watermarking, concurrency locks, and detailed audit logging of all playback token issuances are the real deterrents against redistribution. Full cryptographic DRM (Google Widevine, Apple FairPlay) requires specialized proprietary licensing and CDN packaging, which can be integrated into the modular VideoProvider architecture in future phases.

---

## 5. Web Server and Network Hardening (.htaccess)

1. HTTPS Enforcement:
   - Automatic 301 redirection from HTTP to HTTPS.
2. Directory Browsing Disabled:
   - `Options -Indexes` prevents directory listings.
3. Protected System Directories:
   - Access to `database/`, `tools/`, `docs/`, `storage/`, `uploads/`, `lms_storage/`, and `media/incoming/` is blocked with HTTP 403 Forbidden.
4. File Extension Masking:
   - Direct requests for `*.sql`, `*.md`, `config*.php`, `*.env`, and `*.log` files are denied.
5. Security Headers:
   - `X-Content-Type-Options: nosniff`
   - `X-Frame-Options: SAMEORIGIN`
   - `Referrer-Policy: strict-origin-when-cross-origin`
   - `Permissions-Policy: camera=(), microphone=(), geolocation=()`
   - Strict `Content-Security-Policy` restricting script and connect origins.

---

## 6. Content Security Policy (CSP) Specification & Architecture

### 6.1 Full Production CSP Header
```text
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://checkout.razorpay.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: https:; media-src 'self' blob:; worker-src 'self' blob:; connect-src 'self' https://riipijrgucqigndcjwre.supabase.co https://api.razorpay.com https://lumberjack.razorpay.com; frame-src 'self' https://meet.google.com https://api.razorpay.com; frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self' https://riipijrgucqigndcjwre.supabase.co;
```

### 6.2 Justification for Retaining `'unsafe-inline'`
In the VLSI LMS architecture, PHP renders dynamic views server-side and embeds runtime parameters directly into inline script blocks:
- `login.php`: Injects Supabase project endpoint and anonymous client credentials.
- `student/watch.php`: Injects dynamic video IDs, signed playback tokens, watermark user email, and client IP into the player configuration.
- `student/programs.php`: Injects Razorpay public key and generated order IDs for checkout modal invocation.

Because Hostinger shared Apache environments do not support dynamic per-request Apache header nonce generation without heavy performance overhead or complex PHP header middleware, `'unsafe-inline'` is retained for script execution.

Defense in Depth mitigating `'unsafe-inline'`:
1. Strict `object-src 'none'` blocks Flash, Java applets, and legacy plugin injection.
2. Strict `base-uri 'self'` prevents base tag hijacking.
3. Strict `connect-src` blocks exfiltration to arbitrary external domains (allowing only Supabase and Razorpay).
4. Strict input sanitization with `htmlspecialchars()` across all rendered dynamic strings prevents arbitrary HTML or script element injection.
5. State-changing requests enforce anti-CSRF token verification and prepared SQL statements.

### 6.3 Inline Script Inventory
- `login.php`: Supabase authentication listener, magic link handlers, OAuth redirect handling, and displacement modal logic.
- `student/watch.php`: Hls.js video player initialization, token injection, watermark canvas rendering, and playback progress beacon.
- `student/programs.php`: Razorpay checkout options builder and payment response handler.
- `admin/*.php` & `teacher/*.php`: Modal dialog triggers and client-side confirmation dialogs.
