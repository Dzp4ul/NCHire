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

// Add job_requirements column to job table
$alterQuery = "ALTER TABLE job ADD COLUMN job_requirements TEXT NULL";

// Check if column already exists
$checkColumn = $conn->query("SHOW COLUMNS FROM job LIKE 'job_requirements'");

if ($checkColumn->num_rows == 0) {
    // Column doesn't exist, add it
    if ($conn->query($alterQuery) === TRUE) {
        echo "Column 'job_requirements' added successfully to job table.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "Column 'job_requirements' already exists in job table.\n";
}

$conn->close();
?>
