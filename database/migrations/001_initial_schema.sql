-- ==============================================================================
-- VLSI LMS : Database Migration 001 Initial Relational Schema
-- Fully idempotent schema definition for Hostinger MySQL 8
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Programs Catalog (synced with vlsiinsights.com courses)
CREATE TABLE IF NOT EXISTS `programs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price_inr` int(11) NOT NULL DEFAULT 0,
  `vlsiinsights_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_program_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Users Table
CREATE TABLE IF NOT EXISTS `lms_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supabase_uid` varchar(64) DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role` enum('student','teacher','admin') NOT NULL DEFAULT 'student',
  `status` enum('active','blocked') NOT NULL DEFAULT 'active',
  `batch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_email` (`email`),
  UNIQUE KEY `idx_user_supabase_uid` (`supabase_uid`),
  KEY `idx_user_role` (`role`),
  KEY `idx_user_batch` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Program Enrollments
CREATE TABLE IF NOT EXISTS `enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `status` enum('active','expired','revoked') NOT NULL DEFAULT 'active',
  `source` enum('razorpay','admin','import') NOT NULL DEFAULT 'admin',
  `payment_id` varchar(128) DEFAULT NULL,
  `starts_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_program_enroll` (`user_id`, `program_id`),
  KEY `idx_enroll_status` (`status`),
  CONSTRAINT `fk_enroll_user` FOREIGN KEY (`user_id`) REFERENCES `lms_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enroll_prog` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. HLS Encrypted Video Catalog
CREATE TABLE IF NOT EXISTS `videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `program_id` int(11) NOT NULL,
  `module_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_trial` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('draft','pending','approved','rejected') NOT NULL DEFAULT 'draft',
  `uploaded_by` int(11) NOT NULL,
  `hls_dir` varchar(64) NOT NULL,
  `key_enc` varchar(255) NOT NULL,
  `duration_sec` int(11) NOT NULL DEFAULT 0,
  `size_bytes` bigint(20) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vid_prog` (`program_id`),
  KEY `idx_vid_module` (`module_id`),
  KEY `idx_vid_status` (`status`),
  CONSTRAINT `fk_vid_prog` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vid_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `lms_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Video Progress Tracking
CREATE TABLE IF NOT EXISTS `video_progress` (
  `user_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `position_sec` int(11) NOT NULL DEFAULT 0,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `video_id`),
  KEY `idx_vprog_completed` (`completed`),
  CONSTRAINT `fk_vprog_user` FOREIGN KEY (`user_id`) REFERENCES `lms_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vprog_vid` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Academic Resources (Notes, Assignments, Lab Files)
CREATE TABLE IF NOT EXISTS `resources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `program_id` int(11) NOT NULL,
  `module_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `type` enum('note','assignment','lab') NOT NULL DEFAULT 'note',
  `due_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_res_prog` (`program_id`),
  KEY `idx_res_module` (`module_id`),
  CONSTRAINT `fk_res_prog` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Student Submissions
CREATE TABLE IF NOT EXISTS `submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `grade` varchar(16) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_subm_res` (`resource_id`),
  KEY `idx_subm_user` (`user_id`),
  CONSTRAINT `fk_subm_res` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subm_user` FOREIGN KEY (`user_id`) REFERENCES `lms_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Live Interactive Sessions (Webinars and Classes)
CREATE TABLE IF NOT EXISTS `live_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('webinar','class') NOT NULL DEFAULT 'webinar',
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `meet_url` varchar(255) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_live_starts` (`starts_at`),
  KEY `idx_live_type` (`type`),
  KEY `idx_live_prog` (`program_id`),
  CONSTRAINT `fk_live_prog` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_live_creator` FOREIGN KEY (`created_by`) REFERENCES `lms_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. System Notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `lms_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notification_reads` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`, `user_id`),
  KEY `idx_nread_user` (`user_id`),
  CONSTRAINT `fk_nread_notif` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nread_user` FOREIGN KEY (`user_id`) REFERENCES `lms_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Direct Messaging Threads
CREATE TABLE IF NOT EXISTS `message_threads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_a` int(11) NOT NULL,
  `user_b` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_thread_ua` (`user_a`),
  KEY `idx_thread_ub` (`user_b`),
  CONSTRAINT `fk_thread_ua` FOREIGN KEY (`user_a`) REFERENCES `lms_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_thread_ub` FOREIGN KEY (`user_b`) REFERENCES `lms_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `thread_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_msg_thread` (`thread_id`),
  KEY `idx_msg_sender` (`sender_id`),
  CONSTRAINT `fk_msg_thread` FOREIGN KEY (`thread_id`) REFERENCES `message_threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `lms_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Payments Records
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `razorpay_order_id` varchar(128) DEFAULT NULL,
  `razorpay_payment_id` varchar(128) DEFAULT NULL,
  `amount_inr` int(11) NOT NULL,
  `status` enum('created','paid','failed','refunded') NOT NULL DEFAULT 'created',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pay_user` (`user_id`),
  KEY `idx_pay_prog` (`program_id`),
  KEY `idx_pay_order` (`razorpay_order_id`),
  CONSTRAINT `fk_pay_user` FOREIGN KEY (`user_id`) REFERENCES `lms_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_prog` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Security Audit Log
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `actor_id` int(11) NOT NULL DEFAULT 0,
  `action` varchar(128) NOT NULL,
  `entity` varchar(64) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `ip` varchar(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_actor` (`actor_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Student Single Active Session Lock
CREATE TABLE IF NOT EXISTS `lms_active_session` (
  `user_id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `ip` varchar(64) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `last_seen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `idx_active_last_seen` (`last_seen`),
  CONSTRAINT `fk_active_user` FOREIGN KEY (`user_id`) REFERENCES `lms_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. MySQL Rate Limits Table
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(64) NOT NULL,
  `identifier` varchar(191) NOT NULL,
  `hits` int(11) NOT NULL DEFAULT 1,
  `first_hit_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reset_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_action_id` (`action`, `identifier`),
  KEY `idx_reset_at` (`reset_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pre-seed default VLSI programs
INSERT INTO `programs` (`id`, `slug`, `title`, `description`, `price_inr`, `vlsiinsights_url`, `is_active`) VALUES
(1, 'physical-design', 'Physical Design & Tapeout Masterclass', 'Netlist to GDSII flow, floorplanning, CTS, routing, DRC/LVS, and signoff closure.', 29999, 'https://www.vlsiinsights.com/physical-design', 1),
(2, 'design-verification', 'Design Verification (UVM & SystemVerilog)', 'Functional verification, assertions, constrained-random tests, and UVM environment creation.', 24999, 'https://www.vlsiinsights.com/design-verification', 1),
(3, 'sta-signoff', 'Static Timing Analysis & Signoff Closure', 'Setup and Hold margins, clock skew, SDC constraints, and ECO timing fixing.', 19999, 'https://www.vlsiinsights.com/sta', 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `price_inr` = VALUES(`price_inr`);

SET FOREIGN_KEY_CHECKS = 1;
