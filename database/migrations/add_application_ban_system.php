<?php
/**
 * Database Migration: Add Application Ban System
 * 
 * When Secretary or Department Head rejects an applicant, they are banned from
 * applying for 4 months (1 semester).
 */

require_once __DIR__ . '/../../config/db.php';

echo "=== Application Ban System Migration ===\n\n";

// 1. Add rejection_ban_until column to applicants table
echo "1. Adding rejection_ban_until to applicants table...\n";
$check = $conn->query("SHOW COLUMNS FROM applicants LIKE 'rejection_ban_until'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE applicants 
            ADD COLUMN rejection_ban_until DATETIME NULL COMMENT 'Ban expires after this date' AFTER profile_picture";
    
    if ($conn->query($sql)) {
        echo "   ✓ rejection_ban_until column added to applicants table\n";
    } else {
        echo "   ⚠ Error: " . $conn->error . "\n";
    }
} else {
    echo "   ✓ rejection_ban_until column already exists\n";
}

// 2. Add ban_reason column to applicants table
echo "2. Adding ban_reason to applicants table...\n";
$check = $conn->query("SHOW COLUMNS FROM applicants LIKE 'ban_reason'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE applicants 
            ADD COLUMN ban_reason TEXT NULL COMMENT 'Reason for application ban' AFTER rejection_ban_until";
    
    if ($conn->query($sql)) {
        echo "   ✓ ban_reason column added to applicants table\n";
    } else {
        echo "   ⚠ Error: " . $conn->error . "\n";
    }
} else {
    echo "   ✓ ban_reason column already exists\n";
}

// 3. Add banned_by column to track who issued the ban
echo "3. Adding banned_by to applicants table...\n";
$check = $conn->query("SHOW COLUMNS FROM applicants LIKE 'banned_by'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE applicants 
            ADD COLUMN banned_by VARCHAR(255) NULL COMMENT 'Admin who issued the ban (Secretary/Department Head)' AFTER ban_reason";
    
    if ($conn->query($sql)) {
        echo "   ✓ banned_by column added to applicants table\n";
    } else {
        echo "   ⚠ Error: " . $conn->error . "\n";
    }
} else {
    echo "   ✓ banned_by column already exists\n";
}

// 4. Add rejection_count column to track number of rejections
echo "4. Adding rejection_count to applicants table...\n";
$check = $conn->query("SHOW COLUMNS FROM applicants LIKE 'rejection_count'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE applicants 
            ADD COLUMN rejection_count INT DEFAULT 0 COMMENT 'Number of times applicant has been rejected' AFTER banned_by";
    
    if ($conn->query($sql)) {
        echo "   ✓ rejection_count column added to applicants table\n";
    } else {
        echo "   ⚠ Error: " . $conn->error . "\n";
    }
} else {
    echo "   ✓ rejection_count column already exists\n";
}

// 5. Create application_bans table for historical tracking
echo "5. Creating application_bans table for audit trail...\n";
$sql = "CREATE TABLE IF NOT EXISTS application_bans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    applicant_id INT NOT NULL,
    applicant_email VARCHAR(255) NOT NULL,
    application_id INT NULL,
    banned_date DATETIME NOT NULL,
    ban_expires DATETIME NOT NULL,
    ban_reason TEXT NOT NULL,
    banned_by_id INT NULL,
    banned_by_name VARCHAR(255) NOT NULL,
    banned_by_role VARCHAR(50) NOT NULL COMMENT 'Secretary or Department Head',
    rejection_reason TEXT NOT NULL,
    position_applied VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_applicant (applicant_id),
    KEY idx_applicant_email (applicant_email),
    KEY idx_ban_expires (ban_expires)
)";

if ($conn->query($sql)) {
    echo "   ✓ application_bans table created\n";
} else {
    echo "   ⚠ Error: " . $conn->error . "\n";
}

// 6. Add index for faster ban checking
echo "6. Adding index for ban checking...\n";
$check = $conn->query("SHOW INDEX FROM applicants WHERE Key_name = 'idx_rejection_ban'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE applicants ADD INDEX idx_rejection_ban (rejection_ban_until)";
    if ($conn->query($sql)) {
        echo "   ✓ Index idx_rejection_ban added\n";
    } else {
        echo "   ⚠ Error: " . $conn->error . "\n";
    }
} else {
    echo "   ✓ Index idx_rejection_ban already exists\n";
}

echo "\n=== Migration Complete ===\n";
echo "✓ All ban system columns added to applicants table\n";
echo "✓ Historical tracking table created\n";
echo "✓ Indexes added for performance\n\n";

echo "Ban System Details:\n";
echo "- Ban Duration: 4 months (1 semester)\n";
echo "- Triggers: Secretary rejection OR Department Head rejection\n";
echo "- Stored in: applicants.rejection_ban_until\n";
echo "- History: application_bans table (audit trail)\n\n";

echo "Next Steps:\n";
echo "1. Update rejection handlers to set ban dates\n";
echo "2. Update application submission to check for active bans\n";
echo "3. Update UI to show ban status to users\n";

$conn->close();
