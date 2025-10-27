<?php
// Add letter_of_intent column to database tables
require_once '../db.php';

echo "<h2>Adding letter_of_intent column to database tables...</h2>";

// 1. Add to user_draft_documents table
$sql1 = "ALTER TABLE user_draft_documents ADD COLUMN letter_of_intent VARCHAR(255) NULL AFTER masteral_cert";
if ($conn->query($sql1)) {
    echo "<p style='color: green;'>✓ Added letter_of_intent column to user_draft_documents table</p>";
} else {
    if (strpos($conn->error, "Duplicate column") !== false) {
        echo "<p style='color: blue;'>ℹ letter_of_intent column already exists in user_draft_documents table</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding to user_draft_documents: " . $conn->error . "</p>";
    }
}

// 2. Check if letter_of_intent exists in job_applicants table
$sql2 = "ALTER TABLE job_applicants ADD COLUMN letter_of_intent VARCHAR(255) NULL AFTER masteral_cert";
if ($conn->query($sql2)) {
    echo "<p style='color: green;'>✓ Added letter_of_intent column to job_applicants table</p>";
} else {
    if (strpos($conn->error, "Duplicate column") !== false) {
        echo "<p style='color: blue;'>ℹ letter_of_intent column already exists in job_applicants table</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding to job_applicants: " . $conn->error . "</p>";
    }
}

echo "<hr>";
echo "<h3>Database Update Complete!</h3>";
echo "<p><a href='../user/user.php'>← Go back to user dashboard</a></p>";

$conn->close();
?>
