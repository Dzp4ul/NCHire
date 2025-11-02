<?php
/**
 * Complete Notification System Setup
 * This creates all necessary tables and ensures everything is ready
 */

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<html><head><style>
body { font-family: Arial; padding: 20px; background: #f5f5f5; }
.success { color: green; background: #d4edda; padding: 10px; margin: 10px 0; border-radius: 5px; }
.error { color: red; background: #f8d7da; padding: 10px; margin: 10px 0; border-radius: 5px; }
.info { color: blue; background: #d1ecf1; padding: 10px; margin: 10px 0; border-radius: 5px; }
</style></head><body>";

echo "<h1>🔧 Complete Notification System Setup</h1>";

// Step 1: Create/verify admin_notifications table
echo "<h2>Step 1: Admin Notifications Table</h2>";
$create_table = "CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT NULL COMMENT 'NULL means notification for all admins, specific ID means for that admin only',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    action_type VARCHAR(50) NOT NULL COMMENT 'e.g., application_transferred, interview_scheduled, etc.',
    applicant_id INT DEFAULT NULL,
    applicant_name VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    INDEX idx_admin_id (admin_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at),
    INDEX idx_admin_unread (admin_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($create_table)) {
    echo "<div class='success'>✅ Table 'admin_notifications' created/verified successfully</div>";
} else {
    echo "<div class='error'>❌ Error creating table: " . $conn->error . "</div>";
}

// Step 2: Create/verify notifications table (for applicants)
echo "<h2>Step 2: Applicant Notifications Table</h2>";
$create_notif_table = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_email VARCHAR(255) NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    INDEX idx_user_email (user_email),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($create_notif_table)) {
    echo "<div class='success'>✅ Table 'notifications' created/verified successfully</div>";
} else {
    echo "<div class='error'>❌ Error creating table: " . $conn->error . "</div>";
}

// Step 3: Insert test notification
echo "<h2>Step 3: Create Test Notification</h2>";

// Get first active admin for testing
$test_admin = $conn->query("SELECT id, full_name FROM admin_users WHERE status = 'Active' ORDER BY id ASC LIMIT 1");
if ($test_admin && $test_admin->num_rows > 0) {
    $admin = $test_admin->fetch_assoc();
    $admin_id = $admin['id'];
    $admin_name = $admin['full_name'];
    
    // Insert test notification
    $insert_test = $conn->prepare("INSERT INTO admin_notifications (admin_id, title, message, type, action_type, applicant_name, created_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $test_title = "Test Notification - System is Working!";
    $test_message = "This is a test notification to verify the system is functioning correctly. If you can see this, the notification system is working!";
    $test_type = "success";
    $test_action = "system_test";
    $test_applicant = "Test Applicant";
    
    $insert_test->bind_param("isssss", $admin_id, $test_title, $test_message, $test_type, $test_action, $test_applicant);
    
    if ($insert_test->execute()) {
        $test_id = $insert_test->insert_id;
        echo "<div class='success'>✅ Test notification created (ID: $test_id) for admin: $admin_name (ID: $admin_id)</div>";
        echo "<div class='info'>ℹ️ Refresh your admin panel and click the notification bell to see this test notification!</div>";
    } else {
        echo "<div class='error'>❌ Failed to create test notification: " . $insert_test->error . "</div>";
    }
} else {
    echo "<div class='error'>❌ No active admin users found to create test notification</div>";
}

// Step 4: Show statistics
echo "<h2>Step 4: Current Statistics</h2>";
$admin_count = $conn->query("SELECT COUNT(*) as count FROM admin_notifications")->fetch_assoc()['count'];
$applicant_count = $conn->query("SELECT COUNT(*) as count FROM notifications")->fetch_assoc()['count'];

echo "<div class='info'>";
echo "<strong>Admin Notifications:</strong> $admin_count<br>";
echo "<strong>Applicant Notifications:</strong> $applicant_count";
echo "</div>";

echo "<h2>✅ Setup Complete!</h2>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Go back to your admin panel: <a href='index.php'>Admin Panel</a></li>";
echo "<li>Click the bell icon in the top-right corner</li>";
echo "<li>You should see the test notification!</li>";
echo "</ol>";

echo "<hr>";
echo "<p><a href='index.php'>← Back to Admin Panel</a> | <a href='debug_notifications.php'>Debug Panel</a></p>";

$conn->close();
echo "</body></html>";
?>
