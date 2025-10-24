<?php
// Debug database connection and data
$servername = "127.0.0.1";
$username = "root";
$password = "12345678";
$dbname = "nchire";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "Database connection successful!\n\n";
    
    // Check if job_applicants table exists
    $result = $conn->query("SHOW TABLES LIKE 'job_applicants'");
    if ($result->num_rows > 0) {
        echo "job_applicants table exists\n\n";
        
        // Check table structure
        echo "Table structure:\n";
        $result = $conn->query("DESCRIBE job_applicants");
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
        echo "\n";
        
        // Count total applicants
        $result = $conn->query("SELECT COUNT(*) as total FROM job_applicants");
        $row = $result->fetch_assoc();
        echo "Total applicants: " . $row['total'] . "\n\n";
        
        // Show all applicants
        $result = $conn->query("SELECT id, applicant_name, applicant_email, position, applied_date, status FROM job_applicants LIMIT 10");
        if ($result->num_rows > 0) {
            echo "Applicants:\n";
            while ($row = $result->fetch_assoc()) {
                echo "ID: " . $row['id'] . ", Name: " . $row['applicant_name'] . ", Email: " . $row['applicant_email'] . ", Position: " . $row['position'] . ", Status: " . $row['status'] . "\n";
            }
        } else {
            echo "No applicants found in database\n";
        }
    } else {
        echo "job_applicants table does not exist\n";
    }
    
    // Check users table
    echo "\n";
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows > 0) {
        echo "users table exists\n";
        $result = $conn->query("SELECT COUNT(*) as total FROM users");
        $row = $result->fetch_assoc();
        echo "Total users: " . $row['total'] . "\n";
    } else {
        echo "users table does not exist\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
