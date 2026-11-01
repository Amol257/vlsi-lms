# VLSI LMS : Hostinger PHP & MySQL Learning Portal

A high-performance Learning Management System built for **VLSI Insights** (`learn.vlsiinsights.com`), providing secure, role-based portals for students, faculty instructors, and administrators on Hostinger web hosting (PHP 8 + MySQL).

> [!WARNING]
> **Security Notice**: Previously committed default credentials (such as `admin@lms.local`, `teacher@vlsiinsights.com`, `student@example.com`, `Admin@123`, `Pass@123`) must be treated as permanently compromised. All production installations must generate unique credentials and rely on Supabase Auth.

---

## Key Features

1. **Native Hostinger Stack (PHP 8.x + MySQL)**:
   - Server-side Supabase token validation and hardened PHP sessions.
   - Central database configuration loaded from `../config.local.php` (above document root) or `config.local.php` (protected by `.htaccess`).
   - Complete relational database schema and idempotent migrations in `database/`.

2. **Enterprise Session Hardening & Concurrency Gate**:
   - Strict session cookies (`HttpOnly`, `Secure`, `SameSite=Lax`).
   - 8-hour idle timeout, 24-hour absolute session expiration.
   - Student single active session lock with 150-second heartbeat check via `lms_active_session`.
   - Administrator capability to reset stuck device sessions.

3. **Client-Packaged Encrypted HLS Video Streaming**:
   - Workstation packaging utilities (`tools/package_video.sh` and `tools/package_video.ps1`).
   - AES-128 segment encryption with dynamic key delivery.
   - Timed HMAC token verification on playlist and key endpoints.
   - Dynamic user watermark (email, IP, clock) moving across the video viewport.
   - Auto-pause on window blur or tab hide, with 10-second progress tracking.
   - Disk quota monitoring against the ~100 GB Hostinger limit.

4. **Gated Live Webinars (Google Meet)**:
   - Server-side regex validation for Google Meet meeting links.
   - Time-window gating: access opens 10 minutes prior to session and closes 3 hours after start.
   - Single active session concurrency verification at join time.
   - Automatic blank tab popup prevention.

5. **Integrated Academic Portals**:
   - **Administrators (`/admin/`)**: Manage curriculum modules, batches, student roster, program enrollments, webinars, video library, and security audit logs.
   - **Faculty / Instructors (`/teacher/`)**: Upload recorded lectures, review student lab exercises, and assign grades with feedback.
   - **Students (`/student/`)**: Program catalog, cohort classroom, lecture player with instant resume, lab submissions, and webinar access.
   - **Payments & Webhooks**: Razorpay order generation, webhook signature verification, and external enrollment webhooks.

---

## Directory Structure

```text
/
|-- admin/                     # Administrator portal (dashboard, users, webinars, videos, audit, enrollments)
|-- api/                       # REST endpoints (heartbeat, live_join, checkout, webhooks, video key/playlist)
|-- assets/                    # Shared styles, scripts, and vendor libraries
|   |-- app.js                 # Shared UI scripts
|   |-- heartbeat.js           # Student session concurrency heartbeat client
|   |-- style.css              # Portal stylesheet
|   +-- vendor/                # Pinned self-hosted libraries (supabase.min.js, hls.min.js)
|-- auth/                      # Supabase token exchange, callback, and logout handlers
|-- database/                  # Schema, migrations, and seed scripts
|   |-- hostinger_mysql.sql    # Complete master database schema
|   |-- seed_sample.sql        # Sample programs and cohort seed data
|   +-- migrations/            # Idempotent database migrations
|-- docs/                      # Architectural documentation and runbooks
|   |-- ASSUMPTIONS.md         # Environment assumptions and trade-offs
|   |-- DEPLOY_HOSTINGER.md    # Hostinger step-by-step deployment guide
|   |-- SECURITY.md            # Comprehensive security architecture
|   |-- VIDEO_WORKFLOW.md      # Video encoding and key gating documentation
|   +-- WEBINAR_RUNBOOK.md     # Google Meet instructor operations manual
|-- includes/                  # Core bootstrap and helper libraries
|   |-- bootstrap.php          # Session hardening, CSRF, escaping, rate limiting
|   +-- video_helper.php       # AES key encryption and HMAC token utilities
|-- student/                   # Student classroom, lecture player, assignments, webinars
|-- teacher/                   # Instructor lecture submissions and student grading
|-- tools/                     # Workstation video packaging scripts (Bash and PowerShell)
|-- .htaccess                  # Hostinger Apache security rules, HTTPS redirect, CSP
|-- .gitignore                 # Secrets and storage directories exclusion
|-- config.example.php         # Configuration template
|-- db.php                     # Central database and auth facade
|-- login.php                  # Supabase-authenticated sign in page
|-- register.php               # Student account registration
+-- logout.php                 # Safe session destruction
```

---

## Quick Start on Hostinger

1. Create subdomain `learn.vlsiinsights.com` pointing to `public_html/learn`.
2. Create MySQL database and import `database/hostinger_mysql.sql`.
3. Copy `config.example.php` to `config.local.php` and set your credentials.
4. Deploy the repository files via Git or FTP.
5. Review `docs/DEPLOY_HOSTINGER.md` for complete configuration details.
