-- Fix job_applicants table to allow NULL for workflow fields
-- INSTRUCTIONS:
-- 1. Open phpMyAdmin: http://localhost/phpmyadmin
-- 2. Click on "nchire" database on the left side
-- 3. Click "SQL" tab at the top
-- 4. Copy ONLY the ALTER TABLE command below and paste it
-- 5. Click "Go"

ALTER TABLE job_applicants 
MODIFY COLUMN psych_exam_receipt VARCHAR(255) NULL DEFAULT NULL,
MODIFY COLUMN interview_date DATETIME NULL DEFAULT NULL,
MODIFY COLUMN interview_notes TEXT NULL DEFAULT NULL,
MODIFY COLUMN demo_date DATETIME NULL DEFAULT NULL,
MODIFY COLUMN initially_hired_date DATETIME NULL DEFAULT NULL,
MODIFY COLUMN initially_hired_notes TEXT NULL DEFAULT NULL,
MODIFY COLUMN rejection_reason TEXT NULL DEFAULT NULL,
MODIFY COLUMN resubmission_documents TEXT NULL DEFAULT NULL,
MODIFY COLUMN resubmission_notes TEXT NULL DEFAULT NULL;
