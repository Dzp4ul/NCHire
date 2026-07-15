<?php
/**
 * Database Migration: Add 'cancelled' to workflow_stage ENUM
 * 
 * This migration adds 'cancelled' workflow stage for applicants who cancel their own applications.
 * Cancelled applications:
 * - Move to archive (same as rejected)
 * - Result in 4-month ban from applying (same as rejection)
 * - Are visible in Secretary and Department Head archive sections
 */

require_once __DIR__ . '/../../config/db.php';

echo "=== Add Cancelled Workflow Stage Migration ===\n\n";

// Check current workflow_stage ENUM values
echo "1. Checking current workflow_stage ENUM values...\n";
$check = $conn->query("SHOW COLUMNS FROM job_applicants LIKE 'workflow_stage'");
if ($check && $check->num_rows > 0) {
    $column = $check->fetch_assoc();
    $current_type = $column['Type'];
    echo "   Current type: $current_type\n";
    
    // Check if 'cancelled' already exists
    if (strpos($current_type, 'cancelled') !== false) {
        echo "   ✓ 'cancelled' workflow stage already exists\n";
    } else {
        echo "   Adding 'cancelled' to workflow_stage ENUM...\n";
        
        // Alter the ENUM to include 'cancelled'
        $sql = "ALTER TABLE job_applicants 
                MODIFY COLUMN workflow_stage ENUM(
                    'secretary_review',
                    'secretary_approved',
                    'department_head_review',
                    'interview_scheduled',
                    'interview_completed',
                    'demo_scheduled',
                    'demo_completed',
                    'psych_scheduled',
                    'psych_completed',
                    'initially_hired',
                    'permanently_hired',
                    'hired',
                    'rejected',
                    'cancelled'
                ) DEFAULT 'secretary_review'";
        
        if ($conn->query($sql)) {
            echo "   ✓ 'cancelled' workflow stage added successfully\n";
        } else {
            echo "   ⚠ Error: " . $conn->error . "\n";
        }
    }
} else {
    echo "   ⚠ Error: workflow_stage column not found\n";
}

echo "\n=== Cancelled Workflow Stage Information ===\n";
echo "When an applicant cancels their application:\n";
echo "1. Status is set to 'Cancelled'\n";
echo "2. workflow_stage is set to 'cancelled'\n";
echo "3. rejected_date is set to NOW() (for archive visibility)\n";
echo "4. rejection_reason is set to 'Application cancelled by applicant'\n";
echo "5. Applicant receives a 4-month ban from applying (same as rejection)\n";
echo "6. Application appears in Archive section for Secretary and Department Head\n\n";

echo "=== Migration Complete ===\n";
echo "✓ Cancelled workflow stage is now available\n";
echo "✓ Cancelled applications will be archived\n";
echo "✓ Applicants who cancel will be banned for 4 months\n\n";

$conn->close();
