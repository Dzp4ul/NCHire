<?php
// Quick database column addition
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Adding missing workflow columns to job_applicants table...</h2>";

// Add columns one by one
$columns = [
    "demo_date DATETIME DEFAULT NULL COMMENT 'Demo teaching schedule'",
    "demo_notes TEXT DEFAULT NULL COMMENT 'Demo schedule notes'",
    "psych_exam_date DATETIME DEFAULT NULL COMMENT 'Psychological exam date'",
    "psych_exam_receipt VARCHAR(255) DEFAULT NULL COMMENT 'Psych exam receipt filename'",
    "psych_exam_notes TEXT DEFAULT NULL COMMENT 'Psych exam notes'",
    "initially_hired_date DATETIME DEFAULT NULL COMMENT 'Date marked as initially hired'",
    "initially_hired_notes TEXT DEFAULT NULL COMMENT 'Initial hiring notes'",
    "workflow_stage INT DEFAULT 1 COMMENT 'Current workflow stage (1-6)'",
    "documents_approved TINYINT(1) DEFAULT 0 COMMENT 'Documents approval status'"
];

foreach ($columns as $column) {
    $column_name = explode(' ', $column)[0];
    
    // Check if column exists
    $check = $conn->query("SHOW COLUMNS FROM job_applicants LIKE '$column_name'");
    
    if ($check->num_rows > 0) {
        echo "✓ Column '$column_name' already exists<br>";
    } else {
        // Add the column
        $sql = "ALTER TABLE job_applicants ADD COLUMN $column";
        if ($conn->query($sql)) {
            echo "<strong style='color: green;'>✓ Added column '$column_name'</strong><br>";
        } else {
            echo "<strong style='color: red;'>✗ Error adding '$column_name': " . $conn->error . "</strong><br>";
        }
    }
}

echo "<hr>";
echo "<h3 style='color: green;'>✓ Database update complete!</h3>";
echo "<p><a href='admin/index.php'>Go to Admin Panel</a></p>";

$conn->close();
?>
