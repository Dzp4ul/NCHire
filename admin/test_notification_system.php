<?php
session_start();

// Simulate admin login for testing
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = 1;

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Notification System Diagnostic</h1><hr>";

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

echo "✅ Database connected<br><br>";

// 1. Check if admin_notifications table exists
echo "<h2>1. Check Tables</h2>";
$result = $conn->query("SHOW TABLES LIKE 'admin_notifications'");
if ($result->num_rows > 0) {
    echo "✅ admin_notifications table exists<br>";
} else {
    echo "❌ admin_notifications table NOT found<br>";
    echo "<p><a href='setup_notifications.php'>Click here to create table</a></p>";
}

$result = $conn->query("SHOW TABLES LIKE 'admin_users'");
if ($result->num_rows > 0) {
    echo "✅ admin_users table exists<br>";
    
    // Count active admins
    $admin_result = $conn->query("SELECT COUNT(*) as count FROM admin_users WHERE status = 'Active'");
    $admin_count = $admin_result->fetch_assoc()['count'];
    echo "📊 Active admins: <b>$admin_count</b><br>";
} else {
    echo "❌ admin_users table NOT found<br>";
}
echo "<br>";

// 2. Check if helper files exist
echo "<h2>2. Check Helper Files</h2>";
$files = [
    'admin_notification_helper.php',
    'email_helper.php',
    'api/admin_notifications.php'
];

foreach ($files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file NOT found<br>";
    }
}
echo "<br>";

// 3. Test notification creation
echo "<h2>3. Test Notification Creation</h2>";

require_once __DIR__ . '/admin_notification_helper.php';

try {
    $test_title = "Test Notification " . date('H:i:s');
    $test_message = "This is a test notification created at " . date('Y-m-d H:i:s');
    $test_type = "info";
    $test_action = "test_action";
    
    $success = createAdminNotification(
        $conn, 
        $test_title, 
        $test_message, 
        $test_type, 
        $test_action, 
        null, 
        "Test Applicant",
        false  // Don't send email for test
    );
    
    if ($success) {
        echo "✅ Test notification created successfully<br>";
    } else {
        echo "❌ Failed to create test notification<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
echo "<br>";

// 4. Check recent notifications
echo "<h2>4. Recent Notifications</h2>";
$notif_result = $conn->query("SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT 5");

if ($notif_result && $notif_result->num_rows > 0) {
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #1e3a8a; color: white;'>
            <th>ID</th><th>Title</th><th>Type</th><th>Action Type</th><th>Applicant</th><th>Read</th><th>Created</th>
          </tr>";
    
    while ($row = $notif_result->fetch_assoc()) {
        $read_badge = $row['is_read'] ? '✅' : '🔵';
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['title']}</td>";
        echo "<td><span style='background: #dbeafe; padding: 2px 8px; border-radius: 4px;'>{$row['type']}</span></td>";
        echo "<td>{$row['action_type']}</td>";
        echo "<td>{$row['applicant_name']}</td>";
        echo "<td style='text-align: center;'>{$read_badge}</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>⚠️ No notifications found in database</p>";
}
echo "<br>";

// 5. Test email configuration
echo "<h2>5. Email Configuration Test</h2>";
if (file_exists(__DIR__ . '/email_helper.php')) {
    require_once __DIR__ . '/email_helper.php';
    echo "✅ Email helper loaded<br>";
    echo "📧 SMTP configured for: manansalajohnpaul120@gmail.com<br>";
    echo "⚠️ To test email, manually trigger an action (schedule interview)<br>";
} else {
    echo "❌ Email helper not found<br>";
}
echo "<br>";

// 6. Check API endpoint
echo "<h2>6. API Endpoint Test</h2>";
if (file_exists(__DIR__ . '/api/admin_notifications.php')) {
    echo "✅ API endpoint exists<br>";
    echo "🔗 <a href='api/admin_notifications.php' target='_blank'>Test API endpoint</a> (should return JSON)<br>";
} else {
    echo "❌ API endpoint not found<br>";
}
echo "<br>";

$conn->close();

echo "<hr>";
echo "<h2>Troubleshooting Steps:</h2>";
echo "<ol>";
echo "<li>If table doesn't exist: <a href='setup_notifications.php'>Run setup_notifications.php</a></li>";
echo "<li>If notifications aren't created: Check error logs in browser console</li>";
echo "<li>If emails not sending: Check email_helper.php SMTP settings</li>";
echo "<li>Try scheduling an interview and check this page again</li>";
echo "</ol>";
echo "<br>";
echo "<p><a href='index.php'>← Back to Admin Panel</a></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1200px;
    margin: 30px auto;
    padding: 20px;
    background: #f5f5f5;
}
h1, h2 {
    color: #1e3a8a;
}
table {
    background: white;
    margin-top: 10px;
}
a {
    color: #1e3a8a;
    font-weight: bold;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
</style>
