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
   - Encrypts the raw 16-byte key with the LMS `APP_KEY` using AES-256-CBC.
2. Tokenized Key Gating:
   - Playlist endpoint `/api/video_playlist.php` generates a signed HMAC token with a 10-minute time-to-live.
   - Key delivery endpoint `/api/video_key.php` validates the HMAC signature, confirms active student session lock, and decrypts the key on demand.
   - Burst detection: logs a security alert to `audit_log` if more than 5 key requests are made within 30 seconds.
3. Dynamic Watermarking:
   - A moving text watermark displays student email, client IP, and real-time clock across the video viewport to discourage screen recording.
   - Video playback automatically pauses when the browser window loses focus or the tab is hidden.

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
