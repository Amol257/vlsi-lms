# VLSI LMS — Hostinger PHP & MySQL Learning Portal

A high-performance Learning Management System built for **VLSI Insights**, modeled identically after the architecture, user roles, and workflows of **astarindo LMS**.

---

## 🚀 Key Features

1. **Native Hostinger Stack (PHP 8.x + MySQL)**:
   - Full server-side authentication with PHP sessions and bcrypt hashing.
   - Central database configuration in `db.php`.
   - Complete database schema and seed data in `database/hostinger_mysql.sql`.

2. **3 Core Role Portals**:
   - **Administrators (`/admin/`)**: Manage courses, curriculum modules, cohort batches, student enrolments, approve/reject teacher lecture submissions, track student streaming hours, broadcast announcements, and inspect audit logs.
   - **Faculty / Instructors (`/teacher/`)**: Upload recorded lectures (YouTube, Vimeo, MP4), notes (PDF), and lab testbenches (.tar.gz), track approval state.
   - **Students (`/student/`)**: Enrolled cohort classroom, continuous video streaming with automatic position resume (`player.dataset.resume`), syllabus progress checkmarks, and lab downloads.

---

## 🛠️ Step-by-Step Deployment on Hostinger

### Step 1: Create a Subdomain in Hostinger
1. Log in to **[Hostinger hPanel](https://hpanel.hostinger.com/)**.
2. Select your domain (`vlsiinsights.com`) and click **Manage**.
3. In the sidebar, go to **Domains** &rarr; **Subdomains**.
4. Enter `learn` (or `lms`) to create:
   `learn.vlsiinsights.com` (pointing to `public_html/learn`).

### Step 2: Create a MySQL Database in Hostinger
1. In hPanel, go to **Databases** &rarr; **MySQL Databases**.
2. Create a new database:
   - **Database Name**: e.g., `u123456_lms`
   - **Username**: e.g., `u123456_lmsuser`
   - **Password**: (set a secure password)
3. Note these credentials down.

### Step 3: Import Database Schema via phpMyAdmin
1. In hPanel Databases, click **Enter phpMyAdmin** next to your new database.
2. Click the **SQL** tab.
3. Open `database/hostinger_mysql.sql` from this repository, copy all contents, paste into phpMyAdmin, and click **Go**.

### Step 4: Configure `db.php`
Open `db.php` and enter your Hostinger database details:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'u123456_lms');      // Your Hostinger Database Name
define('DB_USER', 'u123456_lmsuser');  // Your Hostinger Database Username
define('DB_PASS', 'YOUR_PASSWORD');    // Your Hostinger Database Password
```

### Step 5: Upload Files to Hostinger
Upload all files from this repository directly to `public_html/learn/` via:
- **Hostinger File Manager**, OR
- **Git Deployment** in hPanel (`https://github.com/Amol257/vlsi-lms.git`), OR
- **FTP**.

---

## 🔑 Default Accounts (Pre-Seeded)

| Role | Email | Password | Access |
|---|---|---|---|
| **Super Admin** | `admin@lms.local` | `Admin@123` | Full admin management & approvals |
| **Faculty / Teacher** | `teacher@vlsiinsights.com` | `Pass@123` | Upload class recordings & lab files |
| **Student** | `student@example.com` | `Pass@123` | Stream lectures, resume playback |
