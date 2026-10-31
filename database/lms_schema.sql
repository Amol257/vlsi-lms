-- ==============================================================================
-- VLSI LMS — SUPABASE POSTGRESQL DATABASE SCHEMA & POLICIES
-- ==============================================================================

CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- 1. LMS Courses Table
CREATE TABLE IF NOT EXISTS public.lms_courses (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    code VARCHAR(32) NOT NULL UNIQUE,       -- 'PD', 'DV', 'STA', etc.
    title VARCHAR(255) NOT NULL,
    duration VARCHAR(64) DEFAULT '6 months',
    description TEXT,
    thumbnail_url TEXT,
    status VARCHAR(32) DEFAULT 'active',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- 2. LMS Subjects Table
CREATE TABLE IF NOT EXISTS public.lms_subjects (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    course_id UUID NOT NULL REFERENCES public.lms_courses(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    order_idx INT DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 3. LMS Batches Table
CREATE TABLE IF NOT EXISTS public.lms_batches (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    course_id UUID NOT NULL REFERENCES public.lms_courses(id) ON DELETE RESTRICT,
    code VARCHAR(64) NOT NULL UNIQUE,       -- 'PD-NOV-2026-WKND'
    name VARCHAR(255) NOT NULL,
    schedule_type VARCHAR(64) DEFAULT 'Weekend Live',
    start_date DATE,
    status VARCHAR(32) DEFAULT 'active',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 4. LMS Batch Enrollments Table
CREATE TABLE IF NOT EXISTS public.lms_enrolments (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    batch_id UUID NOT NULL REFERENCES public.lms_batches(id) ON DELETE CASCADE,
    user_email VARCHAR(255),
    user_name VARCHAR(255),
    status VARCHAR(32) DEFAULT 'active',
    enrolled_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(user_id, batch_id)
);

-- 5. LMS Lectures Table
CREATE TABLE IF NOT EXISTS public.lms_lectures (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    batch_id UUID NOT NULL REFERENCES public.lms_batches(id) ON DELETE CASCADE,
    subject_id UUID NOT NULL REFERENCES public.lms_subjects(id) ON DELETE CASCADE,
    teacher_id UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    teacher_name VARCHAR(255) DEFAULT 'Staff Instructor',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    video_type VARCHAR(32) DEFAULT 'youtube', -- 'youtube', 'vimeo', 'file', 'url'
    video_url TEXT NOT NULL,
    duration_seconds INT DEFAULT 0,
    notes_url TEXT,
    lab_url TEXT,
    status VARCHAR(32) DEFAULT 'published',    -- 'draft', 'submitted', 'published', 'rejected'
    order_idx INT DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- 6. LMS Student Lecture Progress
CREATE TABLE IF NOT EXISTS public.lms_lecture_progress (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    lecture_id UUID NOT NULL REFERENCES public.lms_lectures(id) ON DELETE CASCADE,
    user_email VARCHAR(255),
    position_seconds INT DEFAULT 0,
    duration_seconds INT DEFAULT 0,
    percent_watched INT DEFAULT 0,
    is_completed BOOLEAN DEFAULT FALSE,
    last_viewed_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(user_id, lecture_id)
);

-- 7. LMS Announcements Table
CREATE TABLE IF NOT EXISTS public.lms_announcements (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    batch_id UUID REFERENCES public.lms_batches(id) ON DELETE CASCADE,
    author_id UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    audience VARCHAR(32) DEFAULT 'all',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 8. LMS Audit Logs Table
CREATE TABLE IF NOT EXISTS public.lms_audit_logs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    actor_id UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    actor_email VARCHAR(255),
    action VARCHAR(64) NOT NULL,
    details TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Row Level Security
ALTER TABLE public.lms_courses ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.lms_subjects ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.lms_batches ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.lms_enrolments ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.lms_lectures ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.lms_lecture_progress ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.lms_announcements ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.lms_audit_logs ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Public read active courses" ON public.lms_courses FOR SELECT USING (true);
CREATE POLICY "Public read subjects" ON public.lms_subjects FOR SELECT USING (true);
CREATE POLICY "Public read batches" ON public.lms_batches FOR SELECT USING (true);
CREATE POLICY "Public read lectures" ON public.lms_lectures FOR SELECT USING (true);
CREATE POLICY "Full access lectures" ON public.lms_lectures FOR ALL USING (true);
CREATE POLICY "Full access progress" ON public.lms_lecture_progress FOR ALL USING (true);
CREATE POLICY "Public read announcements" ON public.lms_announcements FOR SELECT USING (true);
CREATE POLICY "Full access enrolments" ON public.lms_enrolments FOR ALL USING (true);
CREATE POLICY "Full access audit logs" ON public.lms_audit_logs FOR ALL USING (true);
