# VLSI LMS — Learning Management Portal

A lightweight, high-performance Learning Management System built for **VLSI Insights**, modeled identically after the architecture, user roles, and workflows of **astarindo LMS**.

---

## 🚀 Key Features

1. **Role-Based Access Control (RBAC)**:
   - **Administrators**: Manage courses, subjects, batches, student roster, approve/reject teacher lecture submissions, view student stream progress, broadcast announcements, and inspect security audit logs.
   - **Faculty / Instructors**: Upload recorded lectures, video embeds (YouTube, Vimeo, MP4), notes (PDF), and lab testbenches (.tar.gz), track approval state.
   - **Students**: Enrolled cohort classroom, continuous video streaming with automatic position resume (`player.dataset.resume`), syllabus progress checkmarks, and lab downloads.

2. **Zero Bloat & Fast**:
   - Zero framework overhead, clean vanilla CSS & JS.
   - Responsive mobile drawer navigation.

3. **Database & API Ready**:
   - PostgreSQL schema with Row Level Security (RLS) provided in `database/lms_schema.sql` for Supabase.
   - Automatic offline fallback / client store (`assets/lms-data.js`) with pre-seeded demo courses, subjects, batches, and lectures.

---

## 📂 Project Directory Structure

```text
WORK/VLSI LMS/
├── index.html                  # Role-based automatic landing router
├── login.html                  # Split-screen auth identical to astarindo LMS /login.php
├── register.html               # Student registration form
├── assets/
│   ├── style.css               # Dependency-free stylesheet
│   ├── app.js                  # Video resume, heartbeat tracker, drawer menu
│   └── lms-data.js             # Data store, session management & seed data
├── admin/
│   ├── index.html              # Admin Dashboard (4-metric tiles, pending reviews, viewing log)
│   ├── courses.html            # Courses & Subjects manager
│   ├── batches.html            # Cohort batches manager
│   ├── lectures.html           # Filterable lecture roster (submitted, published, draft)
│   ├── lecture_view.html       # Video preview review & approve/reject controls
│   ├── users.html              # Students & Teachers directory
│   ├── enrolments.html         # Batch enrolments list
│   ├── announcements.html      # Broadcast announcements
│   ├── reports.html            # Viewing completion analytics
│   └── audit.html              # Security audit log
├── teacher/
│   ├── index.html              # Faculty dashboard with lecture approval status
│   └── upload.html             # Video & lab materials submission form
├── student/
│   ├── index.html              # Enrolled batches & "Continue Watching" quick resume
│   └── player.html             # Classroom video player with syllabus playlist & downloads
└── database/
    └── lms_schema.sql          # Supabase PostgreSQL schema with RLS policies
```

---

## 🔑 Default Credentials for Testing

| Role | Email | Password |
|---|---|---|
| **Super Admin** | `admin@lms.local` | `Admin@123` |
| **Faculty / Teacher** | `teacher@vlsiinsights.com` | `Pass@123` |
| **Enrolled Student** | `student@example.com` | `Pass@123` |

*(Quick 1-click role switcher buttons are also provided on the `login.html` screen for instant testing).*
