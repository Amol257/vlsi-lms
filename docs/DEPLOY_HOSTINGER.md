# Hostinger Production Deployment Guide for VLSI LMS

This guide provides end-to-end instructions for deploying and maintaining the VLSI LMS on Hostinger web hosting for `learn.vlsiinsights.com`.

---

## 1. Prerequisites & PHP Runtime Configuration

1. Log in to **Hostinger hPanel** (https://hpanel.hostinger.com/).
2. Navigate to **Advanced** -> **PHP Configuration**.
3. Select **PHP 8.2** (or minimum PHP 8.0).
4. In the **PHP Extensions** tab, ensure the following extensions are checked/enabled:
   - `sodium` (strictly required for Libsodium Secretbox encryption)
   - `pdo_mysql`
   - `curl`
   - `fileinfo`
   - `mbstring`
   - `json`
5. Save changes.

---

## 2. Subdomain Setup in Hostinger hPanel

1. In hPanel, navigate to **Domains** -> **Subdomains**.
2. Create the subdomain:
   - Subdomain: `learn`
   - Custom folder for subdomain: check the box and set to `public_html/learn`.
   - Click **Create**.
3. Enable HTTPS under **Security** -> **SSL** to ensure SSL is active on `learn.vlsiinsights.com`.

---

## 3. Hostinger Directory Structure Alignment

Hostinger shared hosting uses the standard directory hierarchy:
```text
/home/<user>/
|-- domains/
|   `-- vlsiinsights.com/
|       |-- config.local.php            <-- RECOMMENDED: Outside web docroot
|       |-- lms_sessions/               <-- Dedicated session dir (mode 0700)
|       |-- lms_storage/                <-- Dedicated storage dir (mode 0755)
|       |   |-- hls/
|       |   `-- submissions/
|       `-- public_html/
|           `-- learn/                  <-- Document root for learn.vlsiinsights.com
|               |-- index.php
|               |-- login.php
|               |-- .htaccess
|               `-- ... (LMS PHP application files)
`-- repos/
    `-- vlsi-lms/                       <-- Cloned Git repository
```

---

## 4. MySQL Database Creation & Schema Setup

1. In hPanel, navigate to **Databases** -> **MySQL Databases**.
2. Create a new database:
   - Database Name: e.g. `u123456789_lms`
   - Username: e.g. `u123456789_lmsuser`
   - Password: generate a strong 24-character random password.
3. Record these credentials for your configuration.
4. Open **phpMyAdmin** for this database.
5. In the **SQL** tab, paste the entire contents of `database/hostinger_mysql.sql` and execute.
6. Verify that all tables (`lms_users`, `programs`, `enrollments`, `videos`, `live_sessions`, `lms_active_session`, `rate_limits`, `audit_log`, etc.) are created.

---

## 5. Configuration Setup (`config.local.php`)

Place `config.local.php` outside the web document root at `/home/<user>/domains/vlsiinsights.com/config.local.php` (or in user home `/home/<user>/config.local.php`).

If hosting file permissions restrict placement outside `public_html`, place it directly in `public_html/learn/config.local.php`, where Apache `.htaccess` rules deny web access with HTTP 404.

```php
<?php
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'u123456789_lms',
        'user' => 'u123456789_lmsuser',
        'pass' => 'YOUR_STRONG_MYSQL_PASSWORD',
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
            'amol@vlsiinsights.com'
        ],
        'session_idle_timeout' => 28800,
        'session_absolute_timeout' => 86400,
        'session_save_path' => '/home/<user>/domains/vlsiinsights.com/lms_sessions',
        'trusted_proxies' => [], // Populated after checking diagnostics
        'server_transcode' => false,
    ],
    'razorpay' => [
        'enabled' => false,
        'key_id' => 'rzp_live_placeholder',
        'key_secret' => 'placeholder_secret',
        'webhook_secret' => 'placeholder_webhook_secret'
    ],
    'enrollment' => [
        'webhook_secret' => '<STRONG_RANDOM_SECRET_SHARED_WITH_MAIN_SITE>'
    ]
];
```

---

## 6. Deployment Procedure (Git Archive / Rsync)

Do NOT run `git pull` directly inside `public_html/learn`. Running Git in the public docroot risks exposing `.git` metadata if server configuration changes.

Instead, clone the repository outside the document root into `~/repos/vlsi-lms`, and sync the deployable files into `public_html/learn`:

### Initial Deployment via SSH
```bash
# 1. Connect to Hostinger via SSH
ssh -p 65002 u123456789@learn.vlsiinsights.com

# 2. Create the repos and storage directories outside docroot
mkdir -p ~/repos
mkdir -p ~/domains/vlsiinsights.com/lms_storage/hls
mkdir -p ~/domains/vlsiinsights.com/lms_storage/submissions
mkdir -p ~/domains/vlsiinsights.com/lms_sessions
chmod 700 ~/domains/vlsiinsights.com/lms_sessions
chmod 755 ~/domains/vlsiinsights.com/lms_storage

# 3. Clone repository outside docroot
cd ~/repos
git clone https://github.com/Amol257/vlsi-lms.git

# 4. Deploy into public_html/learn excluding development files
rsync -av --delete \
  --exclude='.git' \
  --exclude='tests/' \
  --exclude='docs/' \
  --exclude='tools/' \
  --exclude='database/' \
  --exclude='scratch/' \
  --exclude='config.local.php' \
  ~/repos/vlsi-lms/ ~/domains/vlsiinsights.com/public_html/learn/

# Alternatively, if rsync is not available on your plan, use git archive:
cd ~/repos/vlsi-lms
git archive main | tar -x -C ~/domains/vlsiinsights.com/public_html/learn/
# Then remove non-production directories from public_html/learn if present:
rm -rf ~/domains/vlsiinsights.com/public_html/learn/tests \
       ~/domains/vlsiinsights.com/public_html/learn/docs \
       ~/domains/vlsiinsights.com/public_html/learn/tools \
       ~/domains/vlsiinsights.com/public_html/learn/database \
       ~/domains/vlsiinsights.com/public_html/learn/scratch
```

### Subsequent Updates via SSH
```bash
cd ~/repos/vlsi-lms
git pull origin main
rsync -av --delete \
  --exclude='.git' \
  --exclude='tests/' \
  --exclude='docs/' \
  --exclude='tools/' \
  --exclude='database/' \
  --exclude='scratch/' \
  --exclude='config.local.php' \
  ~/repos/vlsi-lms/ ~/domains/vlsiinsights.com/public_html/learn/
```

---

## 7. IP Diagnostics & Reverse Proxy Verification

Hostinger terminates TLS at edge reverse proxies. To inspect edge headers and configure trusted proxies:

1. Log in to the LMS as an administrator.
2. Navigate to `https://learn.vlsiinsights.com/admin/diagnostics_ip.php`.
3. Check `REMOTE_ADDR` and proxy headers (`HTTP_X_FORWARDED_FOR`, `HTTP_CF_CONNECTING_IP`, `HTTP_X_REAL_IP`).
4. Copy the proxy IP or CIDR range into `'trusted_proxies'` under `app` in `config.local.php`.
5. **Delete After Use**:
   - Once `app.trusted_proxies` is saved with a non-empty array, `admin/diagnostics_ip.php` will automatically return `HTTP 404 Not Found`.
   - To eliminate all risk, delete the file directly:
     ```bash
     rm ~/domains/vlsiinsights.com/public_html/learn/admin/diagnostics_ip.php
     ```

---

## 8. Account Conflict & UID Synchronization Procedure

If an existing user email has been recreated in Supabase or their auth provider identity changed, `auth/session.php` returns HTTP 403:
`"Your account credentials have changed. Please contact your administrator to sync your account."`

To safely sync the user account, an administrator runs the following query in phpMyAdmin:

```sql
-- 1. Identify the user record in lms_users
SELECT id, email, supabase_uid, role, status FROM lms_users WHERE email = 'student@example.com';

-- 2. Obtain the new Supabase UID from the Supabase Dashboard (Authentication -> Users)

-- 3. Update the supabase_uid in lms_users to restore access
UPDATE lms_users 
SET supabase_uid = 'NEW_SUPABASE_UID_FROM_SUPABASE_DASHBOARD',
    last_login_at = NOW() 
WHERE email = 'student@example.com';
```

---

## 9. Verification & Go-Live

Complete every item in `docs/GO_LIVE_CHECKLIST.md` prior to opening the LMS to students and instructors.
