<?php
// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Debug: Notifications System</h2>";

// Check if notifications table exists
$result = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($result->num_rows > 0) {
    echo "✅ Notifications table exists<br>";
} else {
    echo "❌ Notifications table does not exist<br>";
}

// Check job_applicants table structure
echo "<h3>Job Applicants Table Structure:</h3>";
$result = $conn->query("DESCRIBE job_applicants");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "<br>";
    }
} else {
    echo "Error describing job_applicants table: " . $conn->error . "<br>";
}

// Check sample job_applicants data
echo "<h3>Sample Job Applicants Data:</h3>";
$result = $conn->query("SELECT id, applicant_name, user_id FROM job_applicants LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . ", Name: " . $row['applicant_name'] . ", User ID: " . ($row['user_id'] ?? 'NULL') . "<br>";
    }
} else {
    echo "Error querying job_applicants: " . $conn->error . "<br>";
}

// Check notifications data
echo "<h3>Recent Notifications:</h3>";
$result = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "ID: " . $row['id'] . ", User ID: " . $row['user_id'] . ", Title: " . $row['title'] . ", Created: " . $row['created_at'] . "<br>";
        }
    } else {
        echo "No notifications found<br>";
    }
} else {
    echo "Error querying notifications: " . $conn->error . "<br>";
}

// Check applicants table for user_id mapping
echo "<h3>Applicants Table User IDs:</h3>";
$result = $conn->query("SELECT id, applicant_fname, applicant_lname FROM applicants LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "Applicant ID: " . $row['id'] . ", Name: " . $row['applicant_fname'] . " " . $row['applicant_lname'] . "<br>";
    }
} else {
    echo "Error querying applicants: " . $conn->error . "<br>";
}

$conn->close();
?>
