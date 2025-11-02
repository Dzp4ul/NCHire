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

echo "<h2>Database Table Analysis</h2>";

// Check job_applicants table structure
echo "<h3>job_applicants table structure:</h3>";
$result = $conn->query("SHOW COLUMNS FROM job_applicants");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")<br>";
    }
} else {
    echo "Error: " . $conn->error . "<br>";
}

// Check applicants table structure  
echo "<h3>applicants table structure:</h3>";
$result = $conn->query("SHOW COLUMNS FROM applicants");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")<br>";
    }
} else {
    echo "Error: " . $conn->error . "<br>";
}

// Check users table structure
echo "<h3>users table structure:</h3>";
$result = $conn->query("SHOW COLUMNS FROM users");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")<br>";
    }
} else {
    echo "Error: " . $conn->error . "<br>";
}

// Check notifications table structure
echo "<h3>notifications table structure:</h3>";
$result = $conn->query("SHOW COLUMNS FROM notifications");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")<br>";
    }
} else {
    echo "Error: " . $conn->error . "<br>";
}

// Sample data from job_applicants
echo "<h3>Sample job_applicants data:</h3>";
$result = $conn->query("SELECT id, applicant_name, user_id FROM job_applicants LIMIT 3");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . ", Name: " . $row['applicant_name'] . ", User ID: " . ($row['user_id'] ?? 'NULL') . "<br>";
    }
} else {
    echo "Error: " . $conn->error . "<br>";
}

$conn->close();
?>
