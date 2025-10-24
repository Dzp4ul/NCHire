<?php
$servername = "127.0.0.1";
$username = "root";
$password = "12345678";
$dbname = "nchire";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "Database connected successfully\n";
    
    // Check notifications table
    $result = $conn->query("SELECT COUNT(*) as count FROM notifications");
    $count = $result->fetch_assoc()['count'];
    echo "Total notifications in database: $count\n";
    
    // Show recent notifications
    $result = $conn->query("SELECT id, user_id, title, created_at FROM notifications ORDER BY created_at DESC LIMIT 5");
    echo "\nRecent notifications:\n";
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']}, User: {$row['user_id']}, Title: {$row['title']}, Created: {$row['created_at']}\n";
    }
    
    // Check job_applicants table
    $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants");
    $count = $result->fetch_assoc()['count'];
    echo "\nTotal job applications: $count\n";
    
    // Show sample job applicants with user_id info
    $result = $conn->query("SELECT id, user_id, applicant_email, status FROM job_applicants LIMIT 3");
    echo "\nSample job applicants:\n";
    while ($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'] ?? 'NULL';
        echo "ID: {$row['id']}, User_ID: $user_id, Email: {$row['applicant_email']}, Status: {$row['status']}\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
