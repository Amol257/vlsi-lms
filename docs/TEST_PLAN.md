# Comprehensive Manual Test Plan for VLSI LMS

This document outlines the step-by-step test cases required to verify the VLSI Insights LMS portal (`learn.vlsiinsights.com`).

Because automated execution in this environment lacks a running PHP/MySQL server, browser runtime, and live Supabase credentials, manual steps are marked with execution status **[NOT RUN: Requires live server and browser environment]**.

---

## Environment Prerequisites

1. Target Domain: `https://learn.vlsiinsights.com`
2. Server Runtime: PHP 8.2+, MySQL 8, Apache with `.htaccess` support
3. External Services:
   - Supabase project (`riipijrgucqigndcjwre.supabase.co`)
   - Google Meet for webinars
   - Razorpay Checkout (when enabled)
4. Configured Accounts:
   - One bootstrap administrator email in `ADMIN_BOOTSTRAP_EMAILS`
   - One teacher account
   - Two student accounts for concurrency testing (`studentA@example.com`, `studentB@example.com`)

---

## Test Suites

### 1. Supabase Authentication and Session Provisioning
- **Objective**: Verify that login succeeds only with valid Supabase tokens and sets strict session cookies.
- **Status**: [NOT RUN: Requires live server and browser]
- **Steps**:
  1. Open browser and navigate to `https://learn.vlsiinsights.com/login.php`.
  2. Enter valid Supabase credentials and submit.
  3. Verify browser network tab: Supabase SDK authenticates, then client POSTs access token to `/auth/session.php`.
  4. Verify server response is HTTP 200 with `{ "success": true, "redirect": "/student/index.php" }` (or `/admin/index.php` for bootstrap email).
  5. Check cookie jar: verify session cookie has `HttpOnly`, `SameSite=Lax`, and `Secure` flags.
- **Pass Criteria**: Valid tokens create an authorized PHP session; users with bootstrap emails receive role `admin`; other users default to role `student`.

---

### 2. Forged or Tampered Token Rejection
- **Objective**: Ensure that server-side validation rejects invalid, expired, or forged Supabase tokens.
- **Status**: [NOT RUN: Requires live server]
- **Steps**:
  1. Send a POST request to `/auth/session.php` with an arbitrary fabricated token:
     ```bash
     curl -X POST https://learn.vlsiinsights.com/auth/session.php \
       -H "Content-Type: application/x-www-form-urlencoded" \
       -d "access_token=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.fake_signature&csrf_token=VALID_CSRF"
     ```
  2. Send a POST request with an unconfirmed email user token.
- **Pass Criteria**: Server contacts `GET {SUPABASE_URL}/auth/v1/user`, receives non-200, rejects request with HTTP 401 Unauthorized, and creates no PHP session.

---

### 3. Student Single Active Session Concurrency Lock (HTTP 409)
- **Objective**: Verify that a student cannot log in concurrently on two different devices.
- **Status**: [NOT RUN: Requires live server and two separate browsers/devices]
- **Steps**:
  1. Log in with `studentA@example.com` in Browser 1 (e.g. Chrome). Verify active session created in `lms_active_session`.
  2. Immediately open Browser 2 (e.g. Firefox or incognito window) and attempt to log in with `studentA@example.com`.
  3. Inspect response from `/auth/session.php`.
- **Pass Criteria**: Browser 2 receives HTTP 409 Conflict with message: `"This account is active on another device. Sign out there, or try again in 2 minutes."` No session is created in Browser 2.

---

### 4. Displaced Session Detection via Background Heartbeat (HTTP 401)
- **Objective**: Confirm that when an active session is cleared or displaced, existing tabs are terminated.
- **Status**: [NOT RUN: Requires live server and browser]
- **Steps**:
  1. Log in with `studentA@example.com` in Browser 1.
  2. Wait 150 seconds or click "Reset active session" in the Admin UI for that student.
  3. Log in with `studentA@example.com` in Browser 2.
  4. Switch back to Browser 1 and wait up to 30 seconds for `/api/heartbeat.php` to ping, or focus the tab.
- **Pass Criteria**: Browser 1 heartbeat receives HTTP 401, clears the session, and redirects to `/login.php?reason=other-device`.

---

### 5. Webinar Join Prior to Access Window
- **Objective**: Verify students cannot access Google Meet links earlier than 10 minutes prior to scheduled start time.
- **Status**: [NOT RUN: Requires live server and database]
- **Steps**:
  1. In Admin portal (`/admin/webinars.php`), schedule a webinar starting 1 hour from now.
  2. Log in as a student and navigate to `https://learn.vlsiinsights.com/student/webinars.php`.
  3. Select the scheduled webinar and click "Join the webinar".
- **Pass Criteria**: Blank tab closes; student page displays alert: `"Webinar is not currently live. It starts in X minutes (access opens 10 minutes prior)."` HTTP status 403 returned by `/api/live_join.php`.

---

### 6. Webinar Join Inside Access Window
- **Objective**: Verify students and administrators can retrieve the validated Google Meet link inside the live window.
- **Status**: [NOT RUN: Requires live server and database]
- **Steps**:
  1. Schedule a webinar in `/admin/webinars.php` with `starts_at` equal to current time minus 5 minutes.
  2. In student portal (`/student/webinars.php`), select the webinar (tagged `[LIVE NOW]`) and click "Join the webinar".
- **Pass Criteria**: Endpoint `/api/live_join.php` returns HTTP 200 with `{ "success": true, "meet_url": "https://meet.google.com/..." }`. The new tab navigates to the Google Meet URL with `rel="noopener"`. Join event is recorded in `audit_log`.

---

### 7. Webinar Join After Access Window Concludes
- **Objective**: Verify that webinars older than 3 hours are closed to students.
- **Status**: [NOT RUN: Requires live server and database]
- **Steps**:
  1. In the database, set a webinar's `starts_at` to 4 hours in the past.
  2. As a student, attempt to join via `/api/live_join.php?id=N`.
- **Pass Criteria**: Endpoint returns HTTP 403 with message: `"This webinar session has concluded."`

---

### 8. Webinar Join Attempt While Logged Out
- **Objective**: Verify unauthenticated users cannot join webinars or discover Meet links.
- **Status**: [NOT RUN: Requires live server]
- **Steps**:
  1. Without logging in, send a GET request to `/api/live_join.php?id=1`.
- **Pass Criteria**: Endpoint returns HTTP 401 with `{ "error": "Please sign in to join the webinar." }`. Meet link is never returned.

---

### 9. Free Preview (Trial) vs Paid Video Access Gate
- **Objective**: Verify trial videos are viewable without enrollment while paid videos strictly require active enrollment.
- **Status**: [NOT RUN: Requires live server and database]
- **Steps**:
  1. Create Video 1 with `is_trial = 1` and Video 2 with `is_trial = 0` in Program 1.
  2. Log in with a student who has NO enrollment in Program 1.
  3. Navigate to `/student/watch.php?id=1` (Trial video).
  4. Navigate to `/student/watch.php?id=2` (Paid video).
- **Pass Criteria**:
  - Video 1: Playlist `/api/video_playlist.php?id=1` returns HTTP 200 with playlist and token.
  - Video 2: Playlist `/api/video_playlist.php?id=2` returns HTTP 403 with `"Forbidden: An active program enrollment is required to watch this video."`

---

### 10. Key Endpoint 403 Security Cases
- **Objective**: Verify that `/api/video_key.php` rejects forged, expired, or cross-entity tokens.
- **Status**: [NOT RUN: Requires live server]
- **Steps**:
  1. Expired Token: Generate a token with timestamp 1 hour in the past; call `/api/video_key.php?id=1&t=EXPIRED_TOKEN`.
  2. Wrong User: Take a valid token generated for User 1; call `/api/video_key.php?id=1&t=TOKEN` while authenticated as User 2.
  3. Wrong Video: Take a valid token generated for Video 1; call `/api/video_key.php?id=2&t=TOKEN`.
- **Pass Criteria**: All three cases return HTTP 403 Forbidden with `"Forbidden: Invalid or expired key authorization token."` No raw key is delivered.

---

### 11. Playback 15-Minute Pause and Seek Recovery
- **Objective**: Verify that pausing playback for 15 minutes allows seamless seeking without session expiration or playback stall.
- **Status**: [NOT RUN: Requires live browser]
- **Steps**:
  1. Open `/student/watch.php?id=1` and start video playback.
  2. Pause the video and leave the player idle for 15 minutes.
  3. Seek forward to 25 minutes and resume playback.
- **Pass Criteria**: Dynamic token TTL (`duration_sec + 30 min`) remains valid; if segment returns 403, `VideoProvider.handle403Recovery()` fetches a fresh playlist, reloads source, and resumes playback at the seek position.

---

### 12. Long Lecture Playback (> 10 Minutes with Dynamic Token TTL)
- **Objective**: Ensure long lectures (> 10 minutes) do not fail when fetching segments after the initial 10-minute window.
- **Status**: [NOT RUN: Requires live browser and video package]
- **Steps**:
  1. Package a 45-minute video lecture (`duration_sec = 2700`).
  2. Ingest the video via `/admin/videos.php`.
  3. Inspect token TTL generated by `/api/video_playlist.php`: verify TTL is `duration_sec + 1800` (4500 seconds / 75 minutes).
  4. Stream video continuously past the 15-minute and 30-minute marks.
- **Pass Criteria**: Segment requests at minute 11, minute 25, and minute 40 succeed without requiring manual page reload or token re-authentication.

---

### 13. Rate Limiting and Burst Detection Protection
- **Objective**: Verify rate limits protect login, live join, and video key endpoints against abuse.
- **Status**: [NOT RUN: Requires live server]
- **Steps**:
  1. Live Join Rate Limit: Issue 12 rapid requests within 60 seconds to `/api/live_join.php?id=1`.
  2. Key Burst Detection: Issue 8 rapid requests within 30 seconds to `/api/video_key.php`.
- **Pass Criteria**:
  - Live join returns HTTP 429 Too Many Requests after 10 hits within 60 seconds.
  - Video key burst logs a security warning to `audit_log` at most once per minute; if requests exceed 20 within 30s, HTTP 429 is returned.

---

### 14. Sensitive File Path Protection via .htaccess
- **Objective**: Verify that direct web access to sensitive configuration files, SQL dumps, version control internals, and storage directories is denied with HTTP 403 or 404.
- **Status**: [NOT RUN: Requires live web server]
- **Verification Commands**:
  ```bash
  # Git internals protection
  curl -I https://learn.vlsiinsights.com/.git/HEAD
  # Configuration file protection
  curl -I https://learn.vlsiinsights.com/config.local.php
  curl -I https://learn.vlsiinsights.com/config.example.php
  # Internal includes and source protection
  curl -I https://learn.vlsiinsights.com/includes/bootstrap.php
  curl -I https://learn.vlsiinsights.com/tools/package_video.sh
  curl -I https://learn.vlsiinsights.com/scratch/
  # Database schema files
  curl -I https://learn.vlsiinsights.com/database/hostinger_mysql.sql
  # Storage directories
  curl -I https://learn.vlsiinsights.com/lms_storage/
  curl -I https://learn.vlsiinsights.com/media/incoming/manifest.json
  # Documentation files
  curl -I https://learn.vlsiinsights.com/docs/SECURITY.md
  # Check same paths on the MAIN domain (vlsiinsights.com) to confirm no docroot overlap
  curl -I https://www.vlsiinsights.com/config.local.php
  curl -I https://www.vlsiinsights.com/lms_storage/
  ```
- **Pass Criteria**: Every request returns HTTP 403 Forbidden or HTTP 404 Not Found. Directory indexing is disabled (`Options -Indexes`).

---

### 15. HTTP Range Specification Tests (RFC 9110)
- **Objective**: Verify that `/api/video_segment.php` handles all valid single range patterns, suffix ranges, and correctly returns HTTP 416 for malformed or multi-range headers.
- **Status**: **VERIFIED WITH EVIDENCE via CLI Unit Test** (`tests/cli_range_test.php`)
- **CLI / curl Test Vectors**:
  ```bash
  # 1. First byte only (bytes=0-0) -> HTTP 206, Content-Range: bytes 0-0/SIZE, Length: 1
  curl -H "Range: bytes=0-0" "https://learn.vlsiinsights.com/api/video_segment.php?id=1&s=segment_0001.ts&t=TOKEN"

  # 2. Bounded range (bytes=0-99) -> HTTP 206, Content-Range: bytes 0-99/SIZE, Length: 100
  curl -H "Range: bytes=0-99" "https://learn.vlsiinsights.com/api/video_segment.php?id=1&s=segment_0001.ts&t=TOKEN"

  # 3. Open-ended range (bytes=100-) -> HTTP 206, Content-Range: bytes 100-(SIZE-1)/SIZE
  curl -H "Range: bytes=100-" "https://learn.vlsiinsights.com/api/video_segment.php?id=1&s=segment_0001.ts&t=TOKEN"

  # 4. Suffix range (bytes=-500) -> HTTP 206, Content-Range: bytes (SIZE-500)-(SIZE-1)/SIZE, Length: 500
  curl -H "Range: bytes=-500" "https://learn.vlsiinsights.com/api/video_segment.php?id=1&s=segment_0001.ts&t=TOKEN"

  # 5. Out of bounds range (bytes=999999999-) -> HTTP 416 Range Not Satisfiable, Content-Range: bytes */SIZE
  curl -H "Range: bytes=999999999-" "https://learn.vlsiinsights.com/api/video_segment.php?id=1&s=segment_0001.ts&t=TOKEN"

  # 6. Multi-range header (bytes=0-50, 100-200) -> HTTP 416 Range Not Satisfiable (multipart rejected)
  curl -H "Range: bytes=0-50, 100-200" "https://learn.vlsiinsights.com/api/video_segment.php?id=1&s=segment_0001.ts&t=TOKEN"
  ```
- **Pass Criteria**: Standard ranges return HTTP 206 Partial Content with correct byte bounds; invalid or multipart ranges return HTTP 416 Range Not Satisfiable.

