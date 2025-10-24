<?php
// Test notification system
$servername = "127.0.0.1";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Testing Notification System</h2>";

// Check if we have any job applicants
echo "<h3>Job Applicants Data:</h3>";
$result = $conn->query("SELECT id, applicant_name, applicant_email, user_id FROM job_applicants LIMIT 5");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . ", Name: " . $row['applicant_name'] . ", Email: " . $row['applicant_email'] . ", User ID: " . ($row['user_id'] ?? 'NULL') . "<br>";
    }
} else {
    echo "No job applicants found.<br>";
}

// Check applicants table
echo "<h3>Applicants Table Data:</h3>";
$result = $conn->query("SELECT id, applicant_fname, applicant_lname, applicant_email FROM applicants LIMIT 5");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . ", Name: " . $row['applicant_fname'] . " " . $row['applicant_lname'] . ", Email: " . $row['applicant_email'] . "<br>";
    }
} else {
    echo "No applicants found.<br>";
}

// Check notifications table
echo "<h3>Recent Notifications:</h3>";
$result = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . ", User ID: " . $row['user_id'] . ", Title: " . $row['title'] . ", Created: " . $row['created_at'] . "<br>";
    }
} else {
    echo "No notifications found.<br>";
}

$conn->close();
?>
