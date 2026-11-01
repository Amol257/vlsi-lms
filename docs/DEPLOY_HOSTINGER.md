# Hostinger Production Deployment Guide for VLSI LMS

This guide provides end-to-end instructions for deploying the VLSI LMS on Hostinger web hosting for `learn.vlsiinsights.com`.

---

## 1. Subdomain Setup in Hostinger hPanel

1. Log in to **Hostinger hPanel** (https://hpanel.hostinger.com/).
2. Select your domain (`vlsiinsights.com`) and click **Manage**.
3. In the sidebar, navigate to **Domains** -> **Subdomains**.
4. Create subdomain:
   - Subdomain: `learn`
   - Custom folder for subdomain: check the box and set to `public_html/learn`.
   - Click **Create**.
5. Once created, enforce SSL under **Security** -> **SSL** to ensure HTTPS is active on `learn.vlsiinsights.com`.

---

## 2. MySQL Database Creation

1. In hPanel, navigate to **Databases** -> **MySQL Databases**.
2. Create a new database:
   - Database Name: e.g. `u123456789_lms`
   - Username: e.g. `u123456789_lmsuser`
   - Password: generate a strong 24-character random password.
3. Record these credentials for configuration.
4. Click **Enter phpMyAdmin** next to the new database.
5. In phpMyAdmin, click the **SQL** tab.
6. Open `database/hostinger_mysql.sql` from this repository, paste its entire contents into the SQL text area, and click **Go**.
7. Confirm that all tables (such as `lms_users`, `programs`, `enrollments`, `videos`, `live_sessions`, `lms_active_session`, `rate_limits`, etc.) have been created.

---

## 3. Configuration Setup (`config.local.php`)

For maximum security on Hostinger, place `config.local.php` one directory above `public_html/learn` (i.e. inside `/home/u123456789/public_html/` or `/home/u123456789/config.local.php`). If restricted by hosting permissions, place it directly in `public_html/learn/config.local.php`, where it is blocked from public web access by `.htaccess`.

1. Copy `config.example.php` to `config.local.php`.
2. Fill in your configuration values:
   ```php
   return [
       'db' => [
           'host' => 'localhost',
           'name' => 'u123456789_lms',
           'user' => 'u123456789_lmsuser',
           'pass' => 'YOUR_ACTUAL_MYSQL_PASSWORD',
           'charset' => 'utf8mb4'
       ],
       'supabase' => [
           'url' => 'https://riipijrgucqigndcjwre.supabase.co',
           'anon_key' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
           'google_oauth_enabled' => true,
       ],
       'app' => [
           'site_url' => 'https://learn.vlsiinsights.com',
           'app_key' => '<64_HEX_CHARS_GENERATED_BY_ADMIN>',
           'admin_bootstrap_emails' => [
               'admin@vlsiinsights.com',
               'amol.singhal25@gmail.com'
           ],
           'session_idle_timeout' => 28800,
           'session_absolute_timeout' => 86400,
           'server_transcode' => false,
       ],
       'razorpay' => [
           'enabled' => false,
           'key_id' => 'rzp_live_placeholder',
           'key_secret' => 'placeholder_secret',
           'webhook_secret' => 'placeholder_webhook_secret'
       ],
       'enrollment' => [
           'webhook_secret' => 'YOUR_GENERATED_SECRET_FOR_MAIN_SITE'
       ]
   ];
   ```
3. Never commit `config.local.php` to Git.

---

## 4. Codebase Deployment

You can deploy using either Hostinger Git Deployment or File Manager / FTP:

### Option A: Hostinger Git Deployment (Recommended)
1. In hPanel, go to **Advanced** -> **Git**.
2. Enter Repository URL: `https://github.com/Amol257/vlsi-lms.git`.
3. Branch: `main`.
4. Install Directory: `/public_html/learn`.
5. Click **Create** and then **Deploy**.

### Option B: File Manager / FTP
1. Extract or clone this repository.
2. Upload all files directly into `/public_html/learn/`.
3. Ensure `.htaccess` is uploaded (verify hidden files are visible in your FTP client).

---

## 5. Storage Directory Permissions

Create the protected storage directory on the server:
- Path: `public_html/learn/lms_storage/` (or `/home/u123456789/lms_storage/`).
- Permissions: `0755`.
- Subdirectories:
  - `lms_storage/hls/` (holds encrypted video directories)
  - `lms_storage/submissions/` (holds student assignment submissions)
  - `media/incoming/` (staging directory for SFTP video uploads)

The root `.htaccess` automatically denies all web requests to `lms_storage/`, `database/`, `tools/`, and `docs/`.

---

## 6. Post-Deployment Verification

1. Open `https://learn.vlsiinsights.com/login.php` in your browser.
2. Verify SSL lock is green.
3. Test Supabase sign in with an administrator account listed in `admin_bootstrap_emails`.
4. Verify redirection to `/admin/index.php`.
5. Verify security headers in browser DevTools Network tab:
   - `X-Content-Type-Options: nosniff`
   - `X-Frame-Options: SAMEORIGIN`
   - `Content-Security-Policy`
6. Test direct file access protection by navigating to `https://learn.vlsiinsights.com/config.local.php` and `https://learn.vlsiinsights.com/database/hostinger_mysql.sql`. Both must return `403 Forbidden`.
