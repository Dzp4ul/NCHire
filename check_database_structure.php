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

echo "<h2>Database Structure Analysis</h2>";

// Check notifications table structure
echo "<h3>Notifications Table Structure:</h3>";
$result = $conn->query("DESCRIBE notifications");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "Field: " . $row['Field'] . ", Type: " . $row['Type'] . ", Null: " . $row['Null'] . ", Key: " . $row['Key'] . ", Default: " . $row['Default'] . "<br>";
    }
} else {
    echo "Error describing notifications table: " . $conn->error . "<br>";
}

// Check job_applicants table structure
echo "<h3>Job_Applicants Table Structure:</h3>";
$result = $conn->query("DESCRIBE job_applicants");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "Field: " . $row['Field'] . ", Type: " . $row['Type'] . ", Null: " . $row['Null'] . ", Key: " . $row['Key'] . ", Default: " . $row['Default'] . "<br>";
    }
} else {
    echo "Error describing job_applicants table: " . $conn->error . "<br>";
}

// Check applicants table structure
echo "<h3>Applicants Table Structure:</h3>";
$result = $conn->query("DESCRIBE applicants");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "Field: " . $row['Field'] . ", Type: " . $row['Type'] . ", Null: " . $row['Null'] . ", Key: " . $row['Key'] . ", Default: " . $row['Default'] . "<br>";
    }
} else {
    echo "Error describing applicants table: " . $conn->error . "<br>";
}

// Check users table structure if it exists
echo "<h3>Users Table Structure:</h3>";
$result = $conn->query("DESCRIBE users");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "Field: " . $row['Field'] . ", Type: " . $row['Type'] . ", Null: " . $row['Null'] . ", Key: " . $row['Key'] . ", Default: " . $row['Default'] . "<br>";
    }
} else {
    echo "Error describing users table: " . $conn->error . "<br>";
}

// Sample data from each table
echo "<h3>Sample Data Analysis:</h3>";

echo "<h4>Sample Applicants (first 5):</h4>";
$result = $conn->query("SELECT id, applicant_email, first_name, last_name FROM applicants LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . ", Email: " . $row['applicant_email'] . ", Name: " . $row['first_name'] . " " . $row['last_name'] . "<br>";
    }
} else {
    echo "No applicants found or error: " . $conn->error . "<br>";
}

echo "<h4>Sample Job_Applicants (first 5):</h4>";
$result = $conn->query("SELECT id, applicant_id, user_id, applicant_email, job_id FROM job_applicants LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . ", Applicant_ID: " . ($row['applicant_id'] ?? 'NULL') . ", User_ID: " . ($row['user_id'] ?? 'NULL') . ", Email: " . ($row['applicant_email'] ?? 'NULL') . ", Job_ID: " . $row['job_id'] . "<br>";
    }
} else {
    echo "No job_applicants found or error: " . $conn->error . "<br>";
}

echo "<h4>Sample Users (first 5):</h4>";
$result = $conn->query("SELECT id, email, first_name, last_name FROM users LIMIT 5");
if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "ID: " . $row['id'] . ", Email: " . $row['email'] . ", Name: " . $row['first_name'] . " " . $row['last_name'] . "<br>";
        }
    } else {
        echo "Users table exists but no records found.<br>";
    }
} else {
    echo "Users table may not exist or error: " . $conn->error . "<br>";
}

echo "<h4>All Notifications:</h4>";
$result = $conn->query("SELECT id, user_id, title, message, type, is_read, created_at FROM notifications ORDER BY created_at DESC");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . ", User_ID: " . $row['user_id'] . ", Title: " . $row['title'] . ", Type: " . $row['type'] . ", Read: " . ($row['is_read'] ? 'Yes' : 'No') . ", Created: " . $row['created_at'] . "<br>";
    }
} else {
    echo "No notifications found or error: " . $conn->error . "<br>";
}

$conn->close();
?>
