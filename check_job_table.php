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

echo "<h2>Job Table Structure Analysis</h2>";

// Check job table structure
echo "<h3>Job Table Structure:</h3>";
$result = $conn->query("DESCRIBE job");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "Field: " . $row['Field'] . ", Type: " . $row['Type'] . ", Null: " . $row['Null'] . ", Key: " . $row['Key'] . ", Default: " . $row['Default'] . "<br>";
    }
} else {
    echo "Error describing job table: " . $conn->error . "<br>";
}

// Check sample job data
echo "<h3>Sample Job Data (first 3):</h3>";
$result = $conn->query("SELECT * FROM job LIMIT 3");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<pre>";
        print_r($row);
        echo "</pre><hr>";
    }
} else {
    echo "No jobs found or error: " . $conn->error . "<br>";
}

$conn->close();
?>
