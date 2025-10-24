-- Database updates for NCHire application workflow
-- Run this script to add new fields for the hiring process stages

-- Add new fields to job_applicants table
ALTER TABLE job_applicants 
ADD COLUMN IF NOT EXISTS masteral_cert VARCHAR(255) DEFAULT NULL COMMENT 'Optional masteral certificate filename',
ADD COLUMN IF NOT EXISTS interview_date DATETIME DEFAULT NULL COMMENT 'Interview schedule',
ADD COLUMN IF NOT EXISTS interview_notes TEXT DEFAULT NULL COMMENT 'Interview schedule notes',
ADD COLUMN IF NOT EXISTS demo_date DATETIME DEFAULT NULL COMMENT 'Demo teaching schedule',
ADD COLUMN IF NOT EXISTS demo_notes TEXT DEFAULT NULL COMMENT 'Demo schedule notes',
ADD COLUMN IF NOT EXISTS psych_exam_date DATETIME DEFAULT NULL COMMENT 'Psychological exam date',
ADD COLUMN IF NOT EXISTS psych_exam_receipt VARCHAR(255) DEFAULT NULL COMMENT 'Psych exam receipt filename',
ADD COLUMN IF NOT EXISTS psych_exam_notes TEXT DEFAULT NULL COMMENT 'Psych exam notes',
ADD COLUMN IF NOT EXISTS initially_hired_date DATETIME DEFAULT NULL COMMENT 'Date marked as initially hired',
ADD COLUMN IF NOT EXISTS initially_hired_notes TEXT DEFAULT NULL COMMENT 'Initial hiring notes',
ADD COLUMN IF NOT EXISTS workflow_stage INT DEFAULT 1 COMMENT 'Current workflow stage (1-6)',
ADD COLUMN IF NOT EXISTS documents_approved TINYINT(1) DEFAULT 0 COMMENT 'Documents approval status';

-- Add indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_applicant_status ON job_applicants(status);
CREATE INDEX IF NOT EXISTS idx_user_id ON job_applicants(user_id);
CREATE INDEX IF NOT EXISTS idx_job_id ON job_applicants(job_id);
CREATE INDEX IF NOT EXISTS idx_workflow_stage ON job_applicants(workflow_stage);

-- Update existing statuses to include new workflow stages
-- Note: These are the possible status values:
-- 'Pending' - Initial application submitted
-- 'Interview Scheduled' - Interview date set
-- 'Demo Scheduled' - Demo teaching scheduled
-- 'Psychological Exam' - Awaiting psych exam completion
-- 'Initially Hired' - Marked as initially hired, pending final approval
-- 'Hired' - Fully hired
-- 'Rejected' - Application rejected
-- 'Resubmission Required' - Documents need resubmission
