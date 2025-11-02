<?php
session_start();

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
.section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.success { color: green; background: #d4edda; padding: 10px; margin: 10px 0; border-radius: 5px; }
.error { color: red; background: #f8d7da; padding: 10px; margin: 10px 0; border-radius: 5px; }
.info { color: blue; background: #d1ecf1; padding: 10px; margin: 10px 0; border-radius: 5px; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background: #007bff; color: white; }
pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style></head><body>";

echo "<h1>🔍 Admin Notification Debug Panel</h1>";

// Current Session Info
echo "<div class='section'>";
echo "<h2>1. Current Session Information</h2>";
echo "<pre>";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "INACTIVE") . "\n";
echo "Admin Logged In: " . (isset($_SESSION['admin_logged_in']) ? 'YES' : 'NO') . "\n";
if (isset($_SESSION['admin_logged_in'])) {
    echo "Admin ID: " . ($_SESSION['admin_id'] ?? 'NOT SET') . "\n";
    echo "Admin Name: " . ($_SESSION['admin_name'] ?? 'NOT SET') . "\n";
    echo "Admin Role: " . ($_SESSION['admin_role'] ?? 'NOT SET') . "\n";
    echo "Admin Email: " . ($_SESSION['admin_email'] ?? 'NOT SET') . "\n";
} else {
    echo "<span style='color: red;'>NOT LOGGED IN AS ADMIN</span>\n";
}
echo "</pre>";
echo "</div>";

// Check admin_notifications table
echo "<div class='section'>";
echo "<h2>2. Admin Notifications Table Check</h2>";

$table_check = $conn->query("SHOW TABLES LIKE 'admin_notifications'");
if ($table_check && $table_check->num_rows > 0) {
    echo "<div class='success'>✅ Table 'admin_notifications' exists</div>";
    
    // Get total count
    $total = $conn->query("SELECT COUNT(*) as count FROM admin_notifications")->fetch_assoc()['count'];
    echo "<p>Total notifications in database: <strong>$total</strong></p>";
    
    // Get count by admin_id
    if (isset($_SESSION['admin_id'])) {
        $admin_id = $_SESSION['admin_id'];
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin_notifications WHERE (admin_id = ? OR admin_id IS NULL)");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'];
        echo "<p>Notifications for Admin ID <strong>$admin_id</strong>: <strong>$count</strong></p>";
        
        $unread_stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin_notifications WHERE (admin_id = ? OR admin_id IS NULL) AND is_read = 0");
        $unread_stmt->bind_param("i", $admin_id);
        $unread_stmt->execute();
        $unread = $unread_stmt->get_result()->fetch_assoc()['count'];
        echo "<p>Unread notifications for Admin ID <strong>$admin_id</strong>: <strong>$unread</strong></p>";
    }
    
    // Show recent notifications
    echo "<h3>Recent Notifications (Last 10):</h3>";
    $recent = $conn->query("SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT 10");
    if ($recent && $recent->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Admin ID</th><th>Title</th><th>Message</th><th>Type</th><th>Action</th><th>Applicant</th><th>Read</th><th>Created</th></tr>";
        while ($row = $recent->fetch_assoc()) {
            $highlight = '';
            if (isset($_SESSION['admin_id']) && ($row['admin_id'] == $_SESSION['admin_id'] || $row['admin_id'] === null)) {
                $highlight = 'style="background-color: #fff3cd;"';
            }
            echo "<tr $highlight>";
            echo "<td>{$row['id']}</td>";
            echo "<td>" . ($row['admin_id'] ?? '<em>NULL (All)</em>') . "</td>";
            echo "<td>{$row['title']}</td>";
            echo "<td>" . substr($row['message'], 0, 50) . "...</td>";
            echo "<td>{$row['type']}</td>";
            echo "<td>{$row['action_type']}</td>";
            echo "<td>{$row['applicant_name']}</td>";
            echo "<td>" . ($row['is_read'] ? '✓ Yes' : '✗ No') . "</td>";
            echo "<td>{$row['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p><small>Yellow rows = notifications for current admin</small></p>";
    } else {
        echo "<div class='info'>ℹ️ No notifications found in database</div>";
    }
} else {
    echo "<div class='error'>❌ Table 'admin_notifications' does NOT exist!</div>";
}
echo "</div>";

// Check notifications table (for applicants)
echo "<div class='section'>";
echo "<h2>3. Applicant Notifications Table Check</h2>";

$notif_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($notif_check && $notif_check->num_rows > 0) {
    echo "<div class='success'>✅ Table 'notifications' exists</div>";
    
    $total_notif = $conn->query("SELECT COUNT(*) as count FROM notifications")->fetch_assoc()['count'];
    echo "<p>Total applicant notifications: <strong>$total_notif</strong></p>";
    
    $recent_notif = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
    if ($recent_notif && $recent_notif->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Email</th><th>Name</th><th>Title</th><th>Type</th><th>Created</th></tr>";
        while ($row = $recent_notif->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['user_email']}</td>";
            echo "<td>{$row['user_name']}</td>";
            echo "<td>{$row['title']}</td>";
            echo "<td>{$row['type']}</td>";
            echo "<td>{$row['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<div class='error'>❌ Table 'notifications' does NOT exist!</div>";
}
echo "</div>";

// Test API Call
echo "<div class='section'>";
echo "<h2>4. Test Admin Notification API</h2>";

if (isset($_SESSION['admin_id'])) {
    echo "<p>Testing API call to: <code>api/admin_notifications.php</code></p>";
    
    // Simulate API call
    $admin_id = $_SESSION['admin_id'];
    $query = "SELECT * FROM admin_notifications WHERE (admin_id = ? OR admin_id IS NULL) ORDER BY created_at DESC LIMIT 20";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    echo "<p>API would return: <strong>" . count($notifications) . " notifications</strong></p>";
    
    if (count($notifications) > 0) {
        echo "<div class='success'>✅ API should work - notifications found</div>";
        echo "<pre>" . json_encode($notifications, JSON_PRETTY_PRINT) . "</pre>";
    } else {
        echo "<div class='error'>❌ API returns empty - no notifications match your admin_id ($admin_id)</div>";
    }
} else {
    echo "<div class='error'>❌ Cannot test API - not logged in</div>";
}
echo "</div>";

// Check Department Heads
echo "<div class='section'>";
echo "<h2>5. Department Heads Configuration</h2>";

$dept_heads = $conn->query("SELECT id, full_name, email, department, role, status FROM admin_users WHERE role = 'Department Head'");
if ($dept_heads && $dept_heads->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Department</th><th>Status</th></tr>";
    while ($dh = $dept_heads->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$dh['id']}</td>";
        echo "<td>{$dh['full_name']}</td>";
        echo "<td>{$dh['email']}</td>";
        echo "<td>" . ($dh['department'] ?: '<span style="color:red;">NULL</span>') . "</td>";
        echo "<td>{$dh['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='error'>❌ No Department Heads found!</div>";
}
echo "</div>";

// Check Recent Applications
echo "<div class='section'>";
echo "<h2>6. Recent Applications (for testing transfers)</h2>";

$apps = $conn->query("SELECT id, full_name, position, assigned_to_department, workflow_stage, applicant_email FROM job_applicants ORDER BY id DESC LIMIT 5");
if ($apps && $apps->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Position</th><th>Department</th><th>Stage</th><th>Email</th></tr>";
    while ($app = $apps->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$app['id']}</td>";
        echo "<td>{$app['full_name']}</td>";
        echo "<td>{$app['position']}</td>";
        echo "<td>" . ($app['assigned_to_department'] ?: '<span style="color:red;">NULL</span>') . "</td>";
        echo "<td>{$app['workflow_stage']}</td>";
        echo "<td>" . ($app['applicant_email'] ?: '<span style="color:red;">NULL</span>') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='info'>ℹ️ No applications found</div>";
}
echo "</div>";

// JavaScript to test actual API
echo "<div class='section'>";
echo "<h2>7. Live API Test</h2>";
echo "<button onclick='testAPI()' style='padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;'>Test Notification API</button>";
echo "<div id='api-result' style='margin-top: 20px;'></div>";
echo "</div>";

echo "<script>
function testAPI() {
    document.getElementById('api-result').innerHTML = '<p>Testing API...</p>';
    
    fetch('api/admin_notifications.php')
        .then(response => response.json())
        .then(data => {
            console.log('API Response:', data);
            document.getElementById('api-result').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            console.error('API Error:', error);
            document.getElementById('api-result').innerHTML = '<div class=\"error\">❌ API Error: ' + error.message + '</div>';
        });
}
</script>";

echo "<hr>";
echo "<p><a href='index.php'>← Back to Admin Panel</a> | <a href='test_secretary_transfer_notification.php'>Run Transfer Test</a></p>";

$conn->close();
echo "</body></html>";
?>
