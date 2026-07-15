<?php
error_reporting(0);
ini_set('display_errors', 0);

$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// Step 1: Create table
$create_table = "CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT NULL COMMENT 'NULL for all admins, specific ID for individual admin',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    action_type VARCHAR(50) NOT NULL,
    applicant_id INT DEFAULT NULL,
    applicant_name VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    INDEX idx_admin_id (admin_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$result = ['steps' => []];

if ($conn->query($create_table)) {
    $result['steps'][] = 'Table admin_notifications created/verified';
} else {
    $result['steps'][] = 'Error creating table: ' . $conn->error;
}

// Step 2: Get first admin
$admin_query = $conn->query("SELECT id, full_name FROM admin_users WHERE status = 'Active' ORDER BY id ASC LIMIT 1");
if ($admin_query && $admin_query->num_rows > 0) {
    $admin = $admin_query->fetch_assoc();
    $admin_id = $admin['id'];
    
    // Step 3: Clear old test notifications
    $conn->query("DELETE FROM admin_notifications WHERE title LIKE '%Test%' OR title LIKE '%Welcome%' OR title LIKE '%System%'");
    $result['steps'][] = 'Cleared old test notifications';
    
    // Step 4: Insert new test notification
    $stmt = $conn->prepare("INSERT INTO admin_notifications (admin_id, title, message, type, action_type, applicant_name, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    
    $title = "✅ Notification System Working!";
    $message = "This test notification confirms your notification system is now working correctly. You should be able to see this in the notification dropdown.";
    $type = "success";
    $action = "system_test";
    $applicant = "Test System";
    
    $stmt->bind_param("isssss", $admin_id, $title, $message, $type, $action, $applicant);
    
    if ($stmt->execute()) {
        $notif_id = $stmt->insert_id;
        $result['steps'][] = "Test notification created (ID: $notif_id) for admin_id: $admin_id";
        $result['success'] = true;
    } else {
        $result['steps'][] = 'Error creating notification: ' . $stmt->error;
        $result['success'] = false;
    }
    
    // Step 5: Verify it exists
    $verify = $conn->query("SELECT COUNT(*) as count FROM admin_notifications WHERE admin_id = $admin_id AND is_read = 0");
    if ($verify) {
        $count = $verify->fetch_assoc()['count'];
        $result['unread_count'] = $count;
        $result['steps'][] = "Verified: $count unread notification(s) for admin_id $admin_id";
    }
    
} else {
    $result['steps'][] = 'No admin users found';
    $result['success'] = false;
}

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);

$conn->close();
?>
