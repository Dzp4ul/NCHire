<?php
$servername = "127.0.0.1";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Database Connection: OK\n\n";

// Check if tables exist
$tables = ['notifications', 'job_applicants', 'applicants', 'users'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "Table '$table': EXISTS\n";
        
        // Get row count
        $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
        if ($count_result) {
            $count_row = $count_result->fetch_assoc();
            echo "  - Row count: " . $count_row['count'] . "\n";
        }
    } else {
        echo "Table '$table': NOT FOUND\n";
    }
}

echo "\n--- NOTIFICATIONS TABLE ---\n";
$result = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | User: " . $row['user_id'] . " | Title: " . $row['title'] . " | Created: " . $row['created_at'] . "\n";
    }
} else {
    echo "Error querying notifications: " . $conn->error . "\n";
}

echo "\n--- JOB_APPLICANTS TABLE ---\n";
$result = $conn->query("SELECT id, applicant_id, user_id, applicant_email FROM job_applicants LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Applicant_ID: " . ($row['applicant_id'] ?? 'NULL') . " | User_ID: " . ($row['user_id'] ?? 'NULL') . " | Email: " . ($row['applicant_email'] ?? 'NULL') . "\n";
    }
} else {
    echo "Error querying job_applicants: " . $conn->error . "\n";
}

echo "\n--- APPLICANTS TABLE ---\n";
$result = $conn->query("SELECT id, applicant_email, first_name FROM applicants LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Email: " . $row['applicant_email'] . " | Name: " . $row['first_name'] . "\n";
    }
} else {
    echo "Error querying applicants: " . $conn->error . "\n";
}

$conn->close();
?>
