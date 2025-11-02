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

echo "<h2>Adding Job Status Management</h2>";

// Check if status column exists
$check_column = "SHOW COLUMNS FROM job LIKE 'status'";
$result = $conn->query($check_column);

if ($result->num_rows == 0) {
    // Column doesn't exist, add it
    $add_column = "ALTER TABLE job ADD COLUMN status ENUM('Active', 'Closed') DEFAULT 'Active' AFTER application_deadline";
    
    if ($conn->query($add_column)) {
        echo "✓ Successfully added 'status' column to job table<br>";
        
        // Set all existing jobs with expired deadlines to 'Closed'
        $update_expired = "UPDATE job SET status = 'Closed' WHERE application_deadline < CURDATE()";
        if ($conn->query($update_expired)) {
            echo "✓ Updated expired jobs to 'Closed' status<br>";
        } else {
            echo "✗ Error updating expired jobs: " . $conn->error . "<br>";
        }
        
        // Set all jobs with future deadlines to 'Active'
        $update_active = "UPDATE job SET status = 'Active' WHERE application_deadline >= CURDATE()";
        if ($conn->query($update_active)) {
            echo "✓ Updated active jobs to 'Active' status<br>";
        } else {
            echo "✗ Error updating active jobs: " . $conn->error . "<br>";
        }
        
    } else {
        echo "✗ Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "✓ Column 'status' already exists in job table<br>";
}

$conn->close();

echo "<br><strong>Job Status Setup Complete!</strong><br>";
echo "<p>Jobs are now filtered by:<br>";
echo "1. Status must be 'Active'<br>";
echo "2. Deadline must be today or in the future</p>";
echo "<br><a href='index.php'>← Back to Admin Panel</a>";
?>
