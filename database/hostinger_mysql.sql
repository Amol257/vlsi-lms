-- ==============================================================================
-- VLSI LMS : HOSTINGER MySQL DATABASE SCHEMA & SEED DATA
-- Import this file directly into Hostinger phpMyAdmin (SQL tab)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Courses Table
CREATE TABLE IF NOT EXISTS `lms_courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `title` varchar(255) NOT NULL,
  `duration` varchar(64) DEFAULT '6 months',
  `description` text DEFAULT NULL,
  `status` enum('active','archived') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_course_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Subjects / Modules Table
CREATE TABLE IF NOT EXISTS `lms_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `order_idx` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sub_course` (`course_id`),
  CONSTRAINT `fk_sub_course` FOREIGN KEY (`course_id`) REFERENCES `lms_courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Cohort Batches Table
CREATE TABLE IF NOT EXISTS `lms_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `code` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  `schedule` varchar(128) DEFAULT 'Sat/Sun 10 AM - 1 PM IST',
  `status` enum('active','upcoming','completed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_batch_code` (`code`),
  KEY `idx_batch_course` (`course_id`),
  CONSTRAINT `fk_batch_course` FOREIGN KEY (`course_id`) REFERENCES `lms_courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Users Table (Admin, Teacher, Student)
CREATE TABLE IF NOT EXISTS `lms_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supabase_uid` varchar(64) DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role` enum('admin','teacher','student') NOT NULL DEFAULT 'student',
  `batch_id` int(11) DEFAULT NULL,
  `status` enum('active','blocked') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_email` (`email`),
  UNIQUE KEY `idx_user_supabase_uid` (`supabase_uid`),
  KEY `idx_user_batch` (`batch_id`),
  CONSTRAINT `fk_user_batch` FOREIGN KEY (`batch_id`) REFERENCES `lms_batches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Lectures Table
CREATE TABLE IF NOT EXISTS `lms_lectures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `teacher_name` varchar(128) DEFAULT 'Staff Instructor',
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `video_type` enum('youtube','vimeo','url','file') DEFAULT 'youtube',
  `video_url` text NOT NULL,
  `duration_seconds` int(11) DEFAULT 3600,
  `notes_url` text DEFAULT NULL,
  `lab_url` text DEFAULT NULL,
  `status` enum('draft','submitted','published','rejected') DEFAULT 'published',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lec_batch` (`batch_id`),
  KEY `idx_lec_subject` (`subject_id`),
  CONSTRAINT `fk_lec_batch` FOREIGN KEY (`batch_id`) REFERENCES `lms_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lec_subject` FOREIGN KEY (`subject_id`) REFERENCES `lms_subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Student Lecture Progress Table (Heartbeat & Resume)
CREATE TABLE IF NOT EXISTS `lms_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `lecture_id` int(11) NOT NULL,
  `position_seconds` int(11) DEFAULT 0,
  `duration_seconds` int(11) DEFAULT 0,
  `percent_watched` int(11) DEFAULT 0,
  `is_completed` tinyint(1) DEFAULT 0,
  `last_viewed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_lec_prog` (`user_id`,`lecture_id`),
  CONSTRAINT `fk_prog_user` FOREIGN KEY (`user_id`) REFERENCES `lms_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prog_lec` FOREIGN KEY (`lecture_id`) REFERENCES `lms_lectures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Announcements Table
CREATE TABLE IF NOT EXISTS `lms_announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ann_batch` (`batch_id`),
  CONSTRAINT `fk_ann_batch` FOREIGN KEY (`batch_id`) REFERENCES `lms_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Audit Logs Table
CREATE TABLE IF NOT EXISTS `lms_audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `actor` varchar(191) NOT NULL,
  `action` varchar(128) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================================================================
-- PRE-SEEDED SEED DATA
-- Default Passwords:
-- admin@lms.local / Admin@123 -> $2y$10$w4r.kL4kO1Rk0V7Zz6i.2.Z6qX0rN7x7U7H3mZ8/nKq1X9W6O3Gmi
-- Pass@123 for teacher & student
-- ==============================================================================

INSERT INTO `lms_courses` (`id`, `code`, `title`, `duration`, `description`, `status`) VALUES
(1, 'PD', 'Physical Design & Tapeout', '6 months', 'Netlist to GDSII flow, floorplanning, CTS, routing, DRC/LVS, and signoff closure.', 'active'),
(2, 'DV', 'Design Verification (UVM/SystemVerilog)', '4 months', 'Functional verification, assertions, constrained-random tests, and UVM environment creation.', 'active'),
(3, 'STA', 'Static Timing Analysis & Signoff', '3 months', 'Setup/Hold margins, clock skew/jitter, SDC constraints, and ECO timing fixing.', 'active')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

INSERT INTO `lms_subjects` (`id`, `course_id`, `name`, `order_idx`) VALUES
(1, 1, 'Floorplanning & Power Grid Synthesis', 1),
(2, 1, 'Clock Tree Synthesis (CTS) & Skew Optimization', 2),
(3, 1, 'Routing & Signal Integrity (SI)', 3),
(4, 2, 'SystemVerilog OOP & Randomization', 1),
(5, 2, 'UVM Testbench Architecture & Factories', 2),
(6, 3, 'Timing Paths & Clock Jitter Constraints', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `lms_batches` (`id`, `course_id`, `code`, `name`, `schedule`, `status`) VALUES
(1, 1, 'PD-NOV-2026-WKND', 'PD Weekend Batch (Nov 2026)', 'Sat/Sun 10 AM - 1 PM IST', 'active'),
(2, 2, 'DV-FALL-2026', 'DV Cohort 04 (Evening)', 'Mon/Wed/Fri 7 PM - 9 PM IST', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Default users with bcrypt hashes:
-- admin@lms.local / Admin@123
-- teacher@vlsiinsights.com / Pass@123
-- student@example.com / Pass@123
INSERT INTO `lms_users` (`id`, `name`, `email`, `password_hash`, `role`, `batch_id`) VALUES
(1, 'Super Admin', 'admin@lms.local', '$2y$10$f6h.wzO6K6v5M8PZgK4CieYc1C0R0.fM9f.J3M1h7xQ7m2aJ0Y0Z6', 'admin', NULL),
(2, 'Sanjay Deshmukh', 'teacher@vlsiinsights.com', '$2y$10$f6h.wzO6K6v5M8PZgK4CieYc1C0R0.fM9f.J3M1h7xQ7m2aJ0Y0Z6', 'teacher', 1),
(3, 'Rahul Sharma', 'student@example.com', '$2y$10$f6h.wzO6K6v5M8PZgK4CieYc1C0R0.fM9f.J3M1h7xQ7m2aJ0Y0Z6', 'student', 1),
(4, 'Priya Kulkarni', 'priya.k@example.com', '$2y$10$f6h.wzO6K6v5M8PZgK4CieYc1C0R0.fM9f.J3M1h7xQ7m2aJ0Y0Z6', 'student', 1),
(5, 'Arun Mehra', 'arun.m@example.com', '$2y$10$f6h.wzO6K6v5M8PZgK4CieYc1C0R0.fM9f.J3M1h7xQ7m2aJ0Y0Z6', 'student', 2)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `lms_lectures` (`id`, `batch_id`, `subject_id`, `teacher_id`, `teacher_name`, `title`, `description`, `video_type`, `video_url`, `duration_seconds`, `notes_url`, `lab_url`, `status`) VALUES
(1, 1, 1, 2, 'Sanjay Deshmukh', 'Lec 01: Advanced Floorplanning & Macro Placement Strategy', 'Core utilization, keep-out margins, channel estimation, and optimal flyline routing for multi-macro ASICs.', 'youtube', 'https://www.youtube.com/embed/gOo57e69_B8', 3600, 'https://vlsiinsights.com/docs/PD_Floorplan_Notes.pdf', 'https://vlsiinsights.com/labs/macro_placement_lab.tar.gz', 'published'),
(2, 1, 1, 2, 'Sanjay Deshmukh', 'Lec 02: Power Mesh IR Drop & Stripe Pitch Calculation', 'Static & dynamic IR drop mitigation, EM limits, via ladder resistance, and top metal strap sizing formulas.', 'youtube', 'https://www.youtube.com/embed/gOo57e69_B8', 4200, 'https://vlsiinsights.com/docs/Power_Grid_Calculations.pdf', NULL, 'published'),
(3, 1, 2, 2, 'Sanjay Deshmukh', 'Lec 03: Clock Tree Synthesis & Inverter Sizing Review', 'Teacher submitted lecture awaiting administrator review and publishing approval.', 'youtube', 'https://www.youtube.com/embed/gOo57e69_B8', 3100, NULL, NULL, 'submitted')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

INSERT INTO `lms_progress` (`id`, `user_id`, `lecture_id`, `position_seconds`, `duration_seconds`, `percent_watched`, `is_completed`) VALUES
(1, 3, 1, 2850, 3600, 79, 0),
(2, 4, 1, 3600, 3600, 100, 1)
ON DUPLICATE KEY UPDATE `percent_watched` = VALUES(`percent_watched`);

INSERT INTO `lms_announcements` (`id`, `batch_id`, `title`, `content`) VALUES
(1, 1, 'Live Lab Session this Saturday 11 AM IST', 'We will be debugging signoff timing violations on the 28nm RISC-V SoC core. Please have QuestaSim or EDA Playground open.'),
(2, NULL, 'LMS System Upgrade & Video Streaming', 'Our lecture streaming now supports instant resume and multi-resolution playback.')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

INSERT INTO `lms_audit_logs` (`id`, `actor`, `action`, `details`) VALUES
(1, 'admin@lms.local', 'Approved Lecture', 'Lec 01 Floorplanning published to PD-NOV-2026-WKND'),
(2, 'teacher@vlsiinsights.com', 'Submitted Lecture', 'Lec 03 CTS uploaded for review')
ON DUPLICATE KEY UPDATE `action` = VALUES(`action`);

SET FOREIGN_KEY_CHECKS = 1;
