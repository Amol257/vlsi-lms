# Hostinger Go-Live Master Checklist for VLSI LMS

**Domain**: `learn.vlsiinsights.com`  
**Repository**: `https://github.com/Amol257/vlsi-lms`  
**Pre-Built Production Zip**: `C:\Users\acer\Desktop\vlsi-lms-production.zip`  

---

## Phase 1: Hostinger Subdomain & SSL

- [ ] 1. Log in to [Hostinger hPanel](https://hpanel.hostinger.com/) and select `vlsiinsights.com`.
- [ ] 2. Navigate to **Domains** -> **Subdomains**.
- [ ] 3. Create the subdomain:
  - **Subdomain**: `learn`
  - **Custom folder for subdomain**: Check this box and set it to `public_html/learn`.
  - Click **Create**.
- [ ] 4. Navigate to **Security** -> **SSL**, locate `learn.vlsiinsights.com`, and verify SSL is installed/active.
- [ ] 5. *(DNS check)*: If `vlsiinsights.com` DNS is managed in Netlify or Cloudflare, add an **A record**:
  - **Host / Name**: `learn`
  - **Points to**: Hostinger Server IP *(found in hPanel -> Websites -> Manage -> Details)*.
  - **TTL**: Automatic / 300 seconds.

---

## Phase 2: Hostinger PHP Configuration

- [ ] 1. In hPanel, navigate to **Advanced** -> **PHP Configuration**.
- [ ] 2. Set **PHP Version** to **PHP 8.2** and click **Update**.
- [ ] 3. Open the **PHP Extensions** tab and verify the following are enabled:
  - `sodium` (required for Libsodium video encryption)
  - `pdo_mysql` (required for MySQL database)
  - `curl` (required for Supabase token verification)
  - `fileinfo` (required for file upload MIME inspection)
  - `mbstring`
  - `json`
- [ ] 4. Click **Save**.

---

## Phase 3: MySQL Database Setup

- [ ] 1. In hPanel, navigate to **Databases** -> **MySQL Databases**.
- [ ] 2. Create a new database:
  - **Database Name**: e.g., `lms` (full name will look like `u123456789_lms`)
  - **Username**: e.g., `lmsuser` (full username will look like `u123456789_lmsuser`)
  - **Password**: Generate and copy a strong 24-character password.
  - Click **Create**.
- [ ] 3. Click **Enter phpMyAdmin** next to the newly created database.
- [ ] 4. Click the **SQL** tab at the top.
- [ ] 5. Open `database/hostinger_mysql.sql` from your repository, copy all contents, paste into phpMyAdmin, and click **Go**.
- [ ] 6. Confirm that all 22 tables are created successfully.

---

## Phase 4: Deploy Files to Hostinger

- [ ] 1. In hPanel, go to **Files** -> **File Manager** (access files for `vlsiinsights.com`).
- [ ] 2. Open folder: `public_html/learn/`.
- [ ] 3. Delete any default file (such as `default.php`).
- [ ] 4. Click **Upload** (top right), select **File**, and upload:
  `C:\Users\acer\Desktop\vlsi-lms-production.zip`
- [ ] 5. Right-click `vlsi-lms-production.zip` in File Manager, select **Extract**, and extract into `public_html/learn/`.
- [ ] 6. Delete `vlsi-lms-production.zip` after extraction.

---

## Phase 5: Create Production Configuration (`config.local.php`)

- [ ] 1. In File Manager, create a new file named `config.local.php` inside `public_html/learn/` (or in `/domains/vlsiinsights.com/config.local.php`).
- [ ] 2. Paste the following configuration, filling in your Hostinger database details:

```php
<?php
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'PASTE_HOSTINGER_DB_NAME_HERE',
        'user' => 'PASTE_HOSTINGER_DB_USER_HERE',
        'pass' => 'PASTE_HOSTINGER_DB_PASSWORD_HERE',
        'charset' => 'utf8mb4'
    ],
    'supabase' => [
        'url' => 'https://riipijrgucqigndcjwre.supabase.co',
        'anon_key' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJpaXBpanJndWNxaWduZGNqd3JlIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODg5NTk3NjQsImV4cCI6MjEwNDUzNTc2NH0.LD3-e8JT0eS8-sDR1bXqJq4-06MV9G8he2PH-o_3K2M',
        'google_oauth_enabled' => true,
    ],
    'video' => [
        'segments_mode' => 'php',
    ],
    'player' => [
        'hidden_pause_seconds' => 60,
    ],
    'app' => [
        'site_url' => 'https://learn.vlsiinsights.com',
        'app_key' => '8a7d3b9e4f1c2a5e8f0b7c4d1e2a5f8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f',
        'admin_bootstrap_emails' => [
            'admin@vlsiinsights.com',
            'amol.singhal25@gmail.com'
        ],
        'session_idle_timeout' => 28800, // 8 hours
        'session_absolute_timeout' => 86400, // 24 hours
        'server_transcode' => false,
    ],
    'razorpay' => [
        'enabled' => false,
        'key_id' => 'rzp_live_placeholder',
        'key_secret' => 'placeholder_secret',
        'webhook_secret' => 'placeholder_webhook_secret'
    ],
    'enrollment' => [
        'webhook_secret' => '9d2f8e1a4c7b0e3f5a8c1d4e7b0a3f6c8d1e4a7b0c3f6a9d2e5b8c1f4a7d0e3b'
    ]
];
```
- [ ] 3. Click **Save & Close**.

---

## Phase 6: Supabase Authentication Whitelist

- [ ] 1. Open your [Supabase Dashboard](https://supabase.com/dashboard/project/riipijrgucqigndcjwre).
- [ ] 2. Go to **Authentication** (key icon) -> **URL Configuration**.
- [ ] 3. Under **Redirect URLs**, add both:
  - `https://learn.vlsiinsights.com/**`
  - `https://learn.vlsiinsights.com/auth/callback.php`
- [ ] 4. Click **Save**.

---

## Phase 7: Live Smoke Testing

- [ ] 1. Open `https://learn.vlsiinsights.com/login.php` in your browser. Confirm the SSL lock is green.
- [ ] 2. Click **Continue with Google** (using `amol.singhal25@gmail.com`).
- [ ] 3. Verify you are automatically elevated to the **Admin** role and directed to the Admin Dashboard.
- [ ] 4. Verify security rules: visiting `https://learn.vlsiinsights.com/config.local.php` must return **404 Not Found**.
- [ ] 5. Navigate to `https://learn.vlsiinsights.com/admin/diagnostics_ip.php` to inspect Hostinger proxy IP headers. Copy any proxy IP into `app.trusted_proxies` in `config.local.php`.
- [ ] 6. Delete `public_html/learn/admin/diagnostics_ip.php` from Hostinger File Manager.

