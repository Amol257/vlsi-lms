# VLSI LMS Go-Live Verification Checklist

This operational checklist must be executed by the systems administrator or lead engineer prior to announcing `learn.vlsiinsights.com` into production.

Each verification step must be performed against the live Hostinger environment and recorded with a timestamp and operator initials.

---

## 1. Hosting Environment & PHP Runtime

| # | Verification Step | Expected Outcome | Result |
|---|-------------------|------------------|:------:|
| 1.1 | Verify PHP Version in Hostinger hPanel | PHP 8.2 or 8.1 selected (minimum PHP 8.0) | `Result: [ ]` |
| 1.2 | Verify Libsodium Extension | `sodium` extension enabled in hPanel PHP extensions | `Result: [ ]` |
| 1.3 | Verify Standard Extensions | `pdo_mysql`, `curl`, `json`, `mbstring`, `fileinfo` active | `Result: [ ]` |
| 1.4 | Verify Error Display Disabled | `display_errors = 0`, `log_errors = 1` in production | `Result: [ ]` |
| 1.5 | Verify Timezone Alignment | Server runs in `Asia/Kolkata` (IST) | `Result: [ ]` |

---

## 2. Directory Structure & File Permissions

| # | Verification Step | Expected Outcome | Result |
|---|-------------------|------------------|:------:|
| 2.1 | Deployment from Outside Docroot | Deployed from `~/repos/vlsi-lms` via `rsync` / `git archive` into `public_html/learn` | `Result: [ ]` |
| 2.2 | Excluded Directories Absent | `.git`, `tests/`, `docs/`, `tools/`, `scratch/` absent from docroot | `Result: [ ]` |
| 2.3 | Configuration File Location | `config.local.php` placed outside docroot (e.g. `domains/vlsiinsights.com/config.local.php`) | `Result: [ ]` |
| 2.4 | Video Storage Directory | `lms_storage/` placed outside docroot (or protected by `.htaccess` with 404) | `Result: [ ]` |
| 2.5 | Private PHP Session Storage | Dedicated session directory (mode 0700) configured via `app.session_save_path` | `Result: [ ]` |
| 2.6 | Write Permissions Verified | Web server user (`www-data` / account UID) can write to `lms_storage/` and session dir | `Result: [ ]` |

---

## 3. Web Server & Security Headers (.htaccess)

| # | Verification Step | Expected Outcome | Result |
|---|-------------------|------------------|:------:|
| 3.1 | Force HTTPS Redirection | `http://learn.vlsiinsights.com` redirects (301) to `https://learn.vlsiinsights.com` | `Result: [ ]` |
| 3.2 | Directory Browsing Disabled | Requesting directory paths returns 403 or 404, never a file listing | `Result: [ ]` |
| 3.3 | Protected File Access Test | Direct URL to `config.local.php`, `.env`, `*.sql`, `*.md` returns HTTP 404 | `Result: [ ]` |
| 3.4 | Protected Directory Access Test | Direct URL to `/includes/`, `/database/`, `/tools/`, `/tests/` returns HTTP 404 | `Result: [ ]` |
| 3.5 | Security Headers Check | `X-Content-Type-Options: nosniff` present on all responses | `Result: [ ]` |
| 3.6 | Frame Embedding Protection | `X-Frame-Options: SAMEORIGIN` present | `Result: [ ]` |
| 3.7 | Content Security Policy | Valid CSP header with strictly whitelisted script, connect, and media sources | `Result: [ ]` |

---

## 4. Database Schema & Integrity

| # | Verification Step | Expected Outcome | Result |
|---|-------------------|------------------|:------:|
| 4.1 | Schema Import | `database/hostinger_mysql.sql` imported without errors in phpMyAdmin | `Result: [ ]` |
| 4.2 | Core Tables Verified | `lms_users`, `programs`, `enrollments`, `videos`, `live_sessions`, `rate_limits` exist | `Result: [ ]` |
| 4.3 | Session Concurrency Table | `lms_active_session` table exists with primary key `user_id` | `Result: [ ]` |
| 4.4 | Audit Log Table | `audit_log` table exists with index on `created_at` and `action` | `Result: [ ]` |
| 4.5 | Default Admin Accounts | Confirmed no hardcoded credentials or demo passwords remain | `Result: [ ]` |

---

## 5. Authentication & Supabase Integration

| # | Verification Step | Expected Outcome | Result |
|---|-------------------|------------------|:------:|
| 5.1 | Supabase Project Link | `SUPABASE_URL` points to production Supabase project (`riipijrgucqigndcjwre`) | `Result: [ ]` |
| 5.2 | Anon Key Configured | `SUPABASE_ANON_KEY` configured; service_role key is NEVER used | `Result: [ ]` |
| 5.3 | Email Confirmation Requirement | Unconfirmed Supabase user sign-in rejected with HTTP 403 | `Result: [ ]` |
| 5.4 | Admin Bootstrap Elevation | Configured admin emails in `app.admin_bootstrap_emails` elevated to `admin` role | `Result: [ ]` |
| 5.5 | UID Conflict Handling | Existing email with changed UID returns HTTP 403 with sync advisory | `Result: [ ]` |
| 5.6 | Login Rate Limiting | More than 10 rapid logins for same email returns HTTP 429 | `Result: [ ]` |

---

## 6. Student Session Lock & Concurrency

| # | Verification Step | Expected Outcome | Result |
|---|-------------------|------------------|:------:|
| 6.1 | Single Device Lock | Sign in as student on Browser A, then attempt sign in on Browser B | `Result: [ ]` |
| 6.2 | Concurrency Rejection | Browser B receives HTTP 409 and modal offering session displacement | `Result: [ ]` |
| 6.3 | Heartbeat Displacement | Displacing session in Browser B causes Browser A heartbeat to redirect to login | `Result: [ ]` |
| 6.4 | Admin Reset Capability | Admin can clear student active session via Admin Portal | `Result: [ ]` |
| 6.5 | Session Expiry | Idle session expires after 8 hours; absolute timeout at 24 hours | `Result: [ ]` |

---

## 7. Video Protection, Streaming & Playback

| # | Verification Step | Expected Outcome | Result |
|---|-------------------|------------------|:------:|
| 7.1 | Workstation Packaging | Video packaged with `package_video.ps1` or `package_video.sh` without server secrets | `Result: [ ]` |
| 7.2 | Plaintext Key Removal | `enc.key` and `keyinfo.txt` confirmed deleted after workstation slicing | `Result: [ ]` |
| 7.3 | Secretbox Ingest | Admin ingest in `admin/videos.php` encrypts key into `videos.key_enc` via Libsodium | `Result: [ ]` |
| 7.4 | Gated Key Delivery | Direct access to `/api/video_key.php` without valid HMAC token returns HTTP 403 | `Result: [ ]` |
| 7.5 | Non-Blocking Segment Access | Segment delivery calls `session_write_close()` immediately; no parallel stalls | `Result: [ ]` |
| 7.6 | HTTP Range Requests | Segment streaming returns `206 Partial Content` with `Accept-Ranges: bytes` | `Result: [ ]` |
| 7.7 | Dynamic Watermark | Video viewport renders moving student email, IP address, and live timestamp | `Result: [ ]` |
| 7.8 | Hidden Tab Pause | Video pauses automatically when tab is hidden longer than configured threshold | `Result: [ ]` |

---

## 8. Live Webinar Access Gating

| # | Verification Step | Expected Outcome | Result |
|---|-------------------|------------------|:------:|
| 8.1 | Pre-Start Access Gate | Student attempting to join webinar before scheduled start time is blocked | `Result: [ ]` |
| 8.2 | Enrollment Verification | Non-enrolled student cannot retrieve live meeting link (HTTP 403) | `Result: [ ]` |
| 8.3 | Teacher & Admin Override | Instructors and admins can join before scheduled start for technical checks | `Result: [ ]` |
| 8.4 | Google Meet Redirection | Valid join redirects to external Google Meet room; meeting URL never leaked | `Result: [ ]` |

---

## 9. Webhooks & Payment Processing

| # | Verification Step | Expected Outcome | Result |
|---|-------------------|------------------|:------:|
| 9.1 | Razorpay Webhook Signature | `/api/razorpay_webhook.php` validates HMAC-SHA256 signature with secret | `Result: [ ]` |
| 9.2 | Replay Protection | Duplicate webhook events processed idempotently without multiple enrollments | `Result: [ ]` |
| 9.3 | Enrollment Webhook | `/api/enroll_webhook.php` validates timing-safe shared secret from main website | `Result: [ ]` |
| 9.4 | Webhook Audit Trail | Accepted and rejected webhook events logged in `audit_log` without secrets | `Result: [ ]` |

---

## 10. Operational Cleanup

| # | Verification Step | Expected Outcome | Result |
|---|-------------------|------------------|:------:|
| 10.1 | IP Header Diagnostics Disabled | `app.trusted_proxies` configured in `config.local.php` (auto-expires diagnostics) | `Result: [ ]` |
| 10.2 | Delete Diagnostics Script | Confirm `admin/diagnostics_ip.php` deleted or returns HTTP 404 | `Result: [ ]` |
| 10.3 | Review Audit Logs | Check `admin/audit.php` to ensure clean baseline with no unhandled errors | `Result: [ ]` |
| 10.4 | Verify Backup Schedule | Automated Hostinger daily database and file backups verified active | `Result: [ ]` |

---

### Signoff

- **Lead Engineer:** __________________________  **Date:** _______________
- **System Administrator:** ___________________  **Date:** _______________
- **Production Status:** `[ ] APPROVED FOR LAUNCH`  `[ ] CHANGES REQUIRED`
