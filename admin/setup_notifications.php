<?php
// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Admin Notification System Setup</h1>";
echo "<hr>";

// Create admin_notifications table
$sql = "CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "✅ <b>admin_notifications</b> table created successfully<br><br>";
} else {
    echo "❌ Error creating table: " . $conn->error . "<br><br>";
}

// Check if admin_users table exists
$check_admins = $conn->query("SHOW TABLES LIKE 'admin_users'");
if ($check_admins->num_rows > 0) {
    echo "✅ <b>admin_users</b> table exists<br><br>";
    
    // Count active admins
    $result = $conn->query("SELECT COUNT(*) as count FROM admin_users WHERE status = 'Active'");
    $count = $result->fetch_assoc()['count'];
    echo "📊 Active admin users: <b>$count</b><br><br>";
} else {
    echo "⚠️ <b>admin_users</b> table not found. Please create it first.<br><br>";
}

// Insert test notification
$test_title = "System Test";
$test_message = "Admin notification system is now active!";
$test_type = "success";
$test_action = "system_test";

$stmt = $conn->prepare("INSERT INTO admin_notifications (admin_id, title, message, type, action_type, created_at) VALUES (NULL, ?, ?, ?, ?, NOW())");
$stmt->bind_param("ssss", $test_title, $test_message, $test_type, $test_action);

if ($stmt->execute()) {
    echo "✅ Test notification created successfully<br><br>";
} else {
    echo "❌ Error creating test notification: " . $stmt->error . "<br><br>";
}

$stmt->close();

// Display recent notifications
echo "<h2>Recent Notifications</h2>";
$result = $conn->query("SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT 5");

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Title</th><th>Message</th><th>Type</th><th>Action Type</th><th>Is Read</th><th>Created At</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        $read_status = $row['is_read'] ? '✅ Read' : '🔵 Unread';
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['title']}</td>";
        echo "<td>{$row['message']}</td>";
        echo "<td>{$row['type']}</td>";
        echo "<td>{$row['action_type']}</td>";
        echo "<td>{$read_status}</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No notifications found.</p>";
}

$conn->close();

echo "<br><hr>";
echo "<h2>Setup Complete! ✅</h2>";
echo "<p>Your admin notification system is ready to use.</p>";
echo "<p><a href='index.php'>← Go to Admin Panel</a></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1000px;
    margin: 50px auto;
    padding: 20px;
    background: #f5f5f5;
}
h1, h2 {
    color: #1e3a8a;
}
table {
    background: white;
    width: 100%;
    margin-top: 10px;
}
th {
    background: #1e3a8a;
    color: white;
    text-align: left;
}
a {
    color: #1e3a8a;
    text-decoration: none;
    font-weight: bold;
}
a:hover {
    text-decoration: underline;
}
</style>
