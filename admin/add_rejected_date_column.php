<?php
// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if rejected_date column exists
$check_column = "SHOW COLUMNS FROM job_applicants LIKE 'rejected_date'";
$result = $conn->query($check_column);

if ($result->num_rows == 0) {
    // Column doesn't exist, add it
    $add_column = "ALTER TABLE job_applicants ADD COLUMN rejected_date DATETIME NULL AFTER rejection_reason";
    
    if ($conn->query($add_column)) {
        echo "✓ Successfully added 'rejected_date' column to job_applicants table<br>";
    } else {
        echo "✗ Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "✓ Column 'rejected_date' already exists in job_applicants table<br>";
}

$conn->close();

echo "<br><a href='index.php'>← Back to Admin Panel</a>";
?>
