<?php
/**
 * Database Migration: Add Secretary Workflow Columns
 * 
 * This migration adds workflow tracking for the new Secretary review process:
 * - Applications now go to Secretary first for document review
 * - Secretary can Transfer to Department Head, Request Resubmission, or Reject
 * - Department Head receives only transferred applications
 */

require_once __DIR__ . '/../../config/db.php';

echo "=== Secretary Workflow Migration ===\n\n";

// 1. Add workflow_stage column to track where application is in the process
echo "1. Adding workflow_stage column...\n";
$check = $conn->query("SHOW COLUMNS FROM job_applicants LIKE 'workflow_stage'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE job_applicants 
            ADD COLUMN workflow_stage ENUM(
                'secretary_review',           -- New applications start here
                'secretary_approved',         -- Secretary approved and transferred
                'department_head_review',     -- With department head now
                'interview_scheduled',        -- Interview scheduled by dept head
                'interview_completed',        -- Interview done
                'demo_scheduled',            -- Demo teaching scheduled
                'demo_completed',            -- Demo teaching done
                'psych_scheduled',           -- Psych exam scheduled
                'psych_completed',           -- Psych exam done
                'initially_hired',           -- Initially hired
                'permanently_hired',         -- Permanently hired
                'hired',                     -- Final hired status
                'rejected'                   -- Rejected at any stage
            ) DEFAULT 'secretary_review' AFTER status";
            
    if ($conn->query($sql)) {
        echo "   ✓ workflow_stage column added\n";
    } else {
        echo "   ⚠ Error: " . $conn->error . "\n";
    }
} else {
    echo "   ✓ workflow_stage column already exists\n";
}

// 2. Add secretary_id to track which secretary reviewed
echo "2. Adding secretary_id column...\n";
$check = $conn->query("SHOW COLUMNS FROM job_applicants LIKE 'secretary_id'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE job_applicants ADD COLUMN secretary_id INT NULL AFTER workflow_stage";
    if ($conn->query($sql)) {
        echo "   ✓ secretary_id column added\n";
        // Add index
        $conn->query("ALTER TABLE job_applicants ADD KEY idx_secretary (secretary_id)");
    } else {
        echo "   ⚠ Error: " . $conn->error . "\n";
    }
} else {
    echo "   ✓ secretary_id column already exists\n";
}

// 3. Add secretary_review_date
echo "3. Adding secretary_review_date column...\n";
$check = $conn->query("SHOW COLUMNS FROM job_applicants LIKE 'secretary_review_date'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE job_applicants ADD COLUMN secretary_review_date DATETIME NULL AFTER secretary_id";
    if ($conn->query($sql)) {
        echo "   ✓ secretary_review_date column added\n";
    } else {
        echo "   ⚠ Error: " . $conn->error . "\n";
    }
} else {
    echo "   ✓ secretary_review_date column already exists\n";
}

// 4. Add secretary_notes
echo "4. Adding secretary_notes column...\n";
$check = $conn->query("SHOW COLUMNS FROM job_applicants LIKE 'secretary_notes'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE job_applicants ADD COLUMN secretary_notes TEXT NULL AFTER secretary_review_date";
    if ($conn->query($sql)) {
        echo "   ✓ secretary_notes column added\n";
    } else {
        echo "   ⚠ Error: " . $conn->error . "\n";
    }
} else {
    echo "   ✓ secretary_notes column already exists\n";
}

// 5. Add transferred_to_dept_head_date
echo "5. Adding transferred_to_dept_head_date column...\n";
$check = $conn->query("SHOW COLUMNS FROM job_applicants LIKE 'transferred_to_dept_head_date'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE job_applicants ADD COLUMN transferred_to_dept_head_date DATETIME NULL AFTER secretary_notes";
    if ($conn->query($sql)) {
        echo "   ✓ transferred_to_dept_head_date column added\n";
    } else {
        echo "   ⚠ Error: " . $conn->error . "\n";
    }
} else {
    echo "   ✓ transferred_to_dept_head_date column already exists\n";
}

// 6. Update existing Pending applications to secretary_review stage
echo "6. Updating existing applications to new workflow...\n";
$sql = "UPDATE job_applicants 
        SET workflow_stage = 'secretary_review' 
        WHERE status = 'Pending' AND workflow_stage IS NULL";
        
if ($conn->query($sql)) {
    $affected = $conn->affected_rows;
    echo "   ✓ Updated $affected applications to secretary_review\n";
} else {
    echo "   ⚠ Error: " . $conn->error . "\n";
}

// 7. Create workflow_history table for audit trail
echo "7. Creating workflow_history table...\n";
$sql = "CREATE TABLE IF NOT EXISTS workflow_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    application_id INT NOT NULL,
    from_stage VARCHAR(50),
    to_stage VARCHAR(50) NOT NULL,
    action_by_id INT NOT NULL,
    action_by_role VARCHAR(50) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_application (application_id),
    KEY idx_action_by (action_by_id),
    FOREIGN KEY (application_id) REFERENCES job_applicants(id) ON DELETE CASCADE
)";

if ($conn->query($sql)) {
    echo "   ✓ workflow_history table created\n";
} else {
    echo "   ⚠ Error: " . $conn->error . "\n";
}

// 8. Add Secretary role to admin_users table
echo "8. Adding Secretary role to admin system...\n";
$sql = "ALTER TABLE admin_users 
        MODIFY COLUMN role ENUM('Admin', 'HR Manager', 'Department Head', 'Recruiter', 'Secretary') 
        NOT NULL DEFAULT 'Recruiter'";
        
if ($conn->query($sql)) {
    echo "   ✓ Secretary role added to admin_users\n";
} else {
    echo "   ⚠ Error: " . $conn->error . "\n";
}

// 9. Display current workflow structure
echo "\n=== Workflow Structure ===\n";
echo "Applicant submits → [Secretary Review] → Transfer/Reject/Resubmit → [Department Head] → Interview → Hire/Reject\n\n";

echo "=== Migration Complete ===\n";
echo "✓ All workflow columns added\n";
echo "✓ Secretary role created\n";
echo "✓ Existing applications migrated\n";
echo "✓ Audit trail system ready\n\n";

echo "Next Steps:\n";
echo "1. Create a Secretary user account in the admin panel\n";
echo "2. Secretary will see all new applications for document review\n";
echo "3. Department Heads will only see transferred applications\n";

$conn->close();
