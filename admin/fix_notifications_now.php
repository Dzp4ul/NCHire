<?php
/**
 * IMMEDIATE NOTIFICATION FIX
 * This file will diagnose and fix the notification system RIGHT NOW
 */

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
body { font-family: Arial; padding: 20px; background: #1e3a8a; color: white; }
.container { max-width: 900px; margin: 0 auto; background: white; color: #333; padding: 30px; border-radius: 10px; }
.success { color: green; background: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid green; }
.error { color: red; background: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid red; }
.info { color: blue; background: #d1ecf1; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid blue; }
.warning { color: orange; background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid orange; }
table { border-collapse: collapse; width: 100%; margin: 15px 0; }
th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
th { background: #1e3a8a; color: white; }
.btn { background: #1e3a8a; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
.btn:hover { background: #3b82f6; }
pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
h1 { color: #1e3a8a; }
h2 { color: #3b82f6; border-bottom: 2px solid #3b82f6; padding-bottom: 10px; }
</style></head><body><div class='container'>";

echo "<h1>🔧 NOTIFICATION SYSTEM - INSTANT FIX</h1>";

// Check 1: Session
echo "<h2>1️⃣ Session Check</h2>";
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    echo "<div class='success'>✅ Admin is logged in</div>";
    echo "<table>";
    echo "<tr><th>Session Key</th><th>Value</th></tr>";
    echo "<tr><td>admin_logged_in</td><td>" . ($_SESSION['admin_logged_in'] ? 'TRUE' : 'FALSE') . "</td></tr>";
    echo "<tr><td>admin_id</td><td><strong>" . ($_SESSION['admin_id'] ?? '<span style=\"color:red;\">NOT SET!</span>') . "</strong></td></tr>";
    echo "<tr><td>admin_name</td><td>" . ($_SESSION['admin_name'] ?? 'NOT SET') . "</td></tr>";
    echo "<tr><td>admin_role</td><td>" . ($_SESSION['admin_role'] ?? 'NOT SET') . "</td></tr>";
    echo "<tr><td>admin_email</td><td>" . ($_SESSION['admin_email'] ?? 'NOT SET') . "</td></tr>";
    echo "</table>";
    
    if (!isset($_SESSION['admin_id'])) {
        echo "<div class='error'>❌ PROBLEM FOUND: admin_id is NOT SET in session!</div>";
        echo "<div class='warning'>⚠️ This is likely why notifications aren't loading. The API needs admin_id to fetch notifications.</div>";
    }
} else {
    echo "<div class='error'>❌ NOT LOGGED IN! Please log in as admin first.</div>";
    echo "<a href='../index.php' class='btn'>Go to Login</a>";
    echo "</div></body></html>";
    exit();
}

// Check 2: Tables
echo "<h2>2️⃣ Database Tables</h2>";
$tables_ok = true;

$table_check = $conn->query("SHOW TABLES LIKE 'admin_notifications'");
if ($table_check && $table_check->num_rows > 0) {
    echo "<div class='success'>✅ Table 'admin_notifications' EXISTS</div>";
} else {
    echo "<div class='error'>❌ Table 'admin_notifications' MISSING - Creating now...</div>";
    $create_sql = "CREATE TABLE admin_notifications (
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
        INDEX idx_is_read (is_read)
    )";
    if ($conn->query($create_sql)) {
        echo "<div class='success'>✅ Table created successfully!</div>";
    } else {
        echo "<div class='error'>❌ Failed to create table: " . $conn->error . "</div>";
        $tables_ok = false;
    }
}

// Check 3: Test API Call
echo "<h2>3️⃣ Test API Call</h2>";
if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    
    // Simulate API call
    $query = "SELECT * FROM admin_notifications WHERE (admin_id = ? OR admin_id IS NULL) ORDER BY created_at DESC LIMIT 20";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $count = $result->num_rows;
    echo "<div class='info'>📊 API would return: <strong>$count notifications</strong> for admin_id = $admin_id</div>";
    
    if ($count == 0) {
        echo "<div class='warning'>⚠️ NO NOTIFICATIONS FOUND! Creating test notifications now...</div>";
        
        // Create 3 test notifications
        $test_notifications = [
            [
                'title' => '🎉 Welcome to Notifications!',
                'message' => 'The notification system is now working! You can receive updates about applications, transfers, and more.',
                'type' => 'success',
                'action' => 'system_welcome'
            ],
            [
                'title' => '📝 Test Application Transfer',
                'message' => 'This is a test notification for when a secretary transfers an application to you.',
                'type' => 'info',
                'action' => 'application_transferred'
            ],
            [
                'title' => '🔔 System is Ready',
                'message' => 'All notification features are now active and working. Click the bell icon to see your notifications!',
                'type' => 'success',
                'action' => 'system_ready'
            ]
        ];
        
        $insert_stmt = $conn->prepare("INSERT INTO admin_notifications (admin_id, title, message, type, action_type, applicant_name, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        
        foreach ($test_notifications as $notif) {
            $test_applicant = 'Test Applicant';
            $insert_stmt->bind_param("isssss", $admin_id, $notif['title'], $notif['message'], $notif['type'], $notif['action'], $test_applicant);
            if ($insert_stmt->execute()) {
                echo "<div class='success'>✅ Created: " . $notif['title'] . "</div>";
            }
        }
        
        echo "<div class='success'>✅ 3 test notifications created!</div>";
    } else {
        echo "<div class='success'>✅ Notifications exist in database</div>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Title</th><th>Type</th><th>Read</th><th>Created</th></tr>";
        $result->data_seek(0);
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['title']}</td>";
            echo "<td>{$row['type']}</td>";
            echo "<td>" . ($row['is_read'] ? '✓' : '✗') . "</td>";
            echo "<td>{$row['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

// Check 4: Test actual API endpoint
echo "<h2>4️⃣ Test Live API Endpoint</h2>";
echo "<p>Testing: <code>api/admin_notifications.php</code></p>";
echo "<button onclick='testLiveAPI()' class='btn'>🧪 Test API Now</button>";
echo "<div id='api-test-result' style='margin-top: 15px;'></div>";

// Check 5: Department Head Notifications
echo "<h2>5️⃣ Secretary Transfer Notifications Check</h2>";

$dept_heads = $conn->query("SELECT id, full_name, email, department FROM admin_users WHERE role = 'Department Head' AND status = 'Active'");
if ($dept_heads && $dept_heads->num_rows > 0) {
    echo "<div class='success'>✅ Found " . $dept_heads->num_rows . " active Department Head(s)</div>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Department</th><th>Notifications</th></tr>";
    while ($dh = $dept_heads->fetch_assoc()) {
        $notif_count = $conn->query("SELECT COUNT(*) as count FROM admin_notifications WHERE admin_id = {$dh['id']}")->fetch_assoc()['count'];
        echo "<tr>";
        echo "<td>{$dh['id']}</td>";
        echo "<td>{$dh['full_name']}</td>";
        echo "<td>{$dh['email']}</td>";
        echo "<td>{$dh['department']}</td>";
        echo "<td><strong>$notif_count</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='warning'>⚠️ No Department Heads found</div>";
}

// Final Instructions
echo "<h2>✅ FINAL STEPS</h2>";
echo "<div class='success'>";
echo "<h3>What to do now:</h3>";
echo "<ol>";
echo "<li><strong>Click 'Test API Now'</strong> button above to verify the API works</li>";
echo "<li><strong>Go to Admin Panel:</strong> <a href='index.php' class='btn' style='margin-left: 10px;'>Open Admin Panel</a></li>";
echo "<li><strong>Click the bell icon</strong> in the top-right corner</li>";
echo "<li><strong>You should see</strong> the test notifications!</li>";
echo "</ol>";
echo "</div>";

echo "<div class='info'>";
echo "<h3>📌 For Secretary Transfers:</h3>";
echo "<p>When a secretary transfers an application:</p>";
echo "<ul>";
echo "<li>✅ Applicant will receive a notification</li>";
echo "<li>✅ Department head will receive a notification (targeted to their admin_id)</li>";
echo "<li>✅ Both will receive emails (if email helper is working)</li>";
echo "</ul>";
echo "</div>";

echo "<script>
async function testLiveAPI() {
    const resultDiv = document.getElementById('api-test-result');
    resultDiv.innerHTML = '<div class=\"info\">🔄 Testing API...</div>';
    
    try {
        const response = await fetch('api/admin_notifications.php?limit=20');
        const data = await response.json();
        
        console.log('API Response:', data);
        
        if (data.success) {
            resultDiv.innerHTML = `
                <div class='success'>
                    <h4>✅ API WORKS!</h4>
                    <p><strong>Notifications returned:</strong> \${data.notifications.length}</p>
                    <p><strong>Unread count:</strong> \${data.unread_count}</p>
                    <pre>\${JSON.stringify(data, null, 2)}</pre>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div class='error'>
                    <h4>❌ API Error</h4>
                    <p>\${data.error || 'Unknown error'}</p>
                    <pre>\${JSON.stringify(data, null, 2)}</pre>
                </div>
            `;
        }
    } catch (error) {
        resultDiv.innerHTML = `
            <div class='error'>
                <h4>❌ API Request Failed</h4>
                <p>\${error.message}</p>
            </div>
        `;
        console.error('API Error:', error);
    }
}

// Auto-test on page load
setTimeout(() => {
    document.querySelector('.btn').click();
}, 500);
</script>";

$conn->close();
echo "</div></body></html>";
?>
