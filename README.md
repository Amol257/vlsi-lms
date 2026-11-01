# VLSI LMS : Hostinger PHP & MySQL Learning Portal

A high-performance Learning Management System built for **VLSI Insights**, providing role-based learning portals for students, faculty instructors, and administrators.

> [!WARNING]
> **Security Notice**: Previously committed default credentials (such as `admin@lms.local`, `teacher@vlsiinsights.com`, `student@example.com`, `Admin@123`, `Pass@123`) must be treated as permanently compromised. All production installations must generate unique credentials and rely on Supabase Auth.

---

## Key Features

1. **Native Hostinger Stack (PHP 8.x + MySQL)**:
   - Server-side token validation and secure PHP sessions.
   - Central database configuration loaded from `../config.local.php` or `config.local.php`.
   - Complete database schema and migrations in `database/`.

2. **3 Core Role Portals**:
   - **Administrators (`/admin/`)**: Manage courses, modules, batches, student enrollments, webinar scheduling, video approval, and audit logs.
   - **Faculty / Instructors (`/teacher/`)**: Upload recorded lectures, notes, lab testbenches, and track lecture reviews.
   - **Students (`/student/`)**: Enrolled cohort classroom, continuous video streaming with progress tracking, syllabus progress, lab downloads, and webinar access.

3. **Enterprise Security & Session Hardening**:
   - Strict session cookies (`HttpOnly`, `Secure`, `SameSite=Lax`).
   - 8-hour idle timeout, 24-hour absolute session expiration.
   - Student single-active-session concurrency locking via heartbeat.
   - MySQL-backed rate limiting on sensitive endpoints.
   - Comprehensive CSRF protection across all state mutations.

---

## Step-by-Step Deployment on Hostinger

### Step 1: Create Subdomain in Hostinger
1. Log in to **Hostinger hPanel**.
2. Select your domain (`vlsiinsights.com`) and click **Manage**.
3. In the sidebar, navigate to **Domains** -> **Subdomains**.
4. Enter `learn` to create `learn.vlsiinsights.com` (document root `public_html/learn`).

### Step 2: Create MySQL Database in Hostinger
1. In hPanel, go to **Databases** -> **MySQL Databases**.
2. Create a new database:
   - Database Name (e.g., `u123456_lms`)
   - Username (e.g., `u123456_lmsuser`)
   - Password (generate a strong password)
3. Record these credentials.

### Step 3: Import Database Schema via phpMyAdmin
1. In hPanel Databases, click **Enter phpMyAdmin** next to your database.
2. Click the **SQL** tab.
3. Open `database/hostinger_mysql.sql`, paste the contents, and execute.

### Step 4: Configure `config.local.php`
1. Copy `config.example.php` to `config.local.php`.
2. For optimal security, place `config.local.php` in the directory above `public_html/learn` (or inside `public_html/learn`, where it is blocked by `.htaccess`).
3. Fill in your MySQL credentials, Supabase URL, Anon Key, and generate a 32-byte hex `app_key`.
4. Never commit `config.local.php`.

### Step 5: Upload Files to Hostinger
Upload repository files to `public_html/learn/` via Hostinger Git Deployment, File Manager, or FTP.
