<?php
session_start();

$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed");
}

echo "<html><head><style>
body { font-family: Arial; padding: 20px; background: #f5f5f5; }
.box { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.success { background: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
.error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
.info { background: #d1ecf1; color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
th { background: #1e3a8a; color: white; }
h1 { color: #1e3a8a; }
h2 { color: #3b82f6; border-bottom: 2px solid #3b82f6; padding-bottom: 8px; }
.highlight { background: #fff3cd; }
</style></head><body>";

echo "<h1>🔍 Notification Status Check</h1>";

// Current session
echo "<div class='box'><h2>1. Your Current Session</h2>";
if (isset($_SESSION['admin_logged_in'])) {
    echo "<table>";
    echo "<tr><th>Key</th><th>Value</th></tr>";
    echo "<tr><td><strong>admin_id</strong></td><td><strong>" . ($_SESSION['admin_id'] ?? 'NOT SET!') . "</strong></td></tr>";
    echo "<tr><td>admin_name</td><td>" . ($_SESSION['admin_name'] ?? '-') . "</td></tr>";
    echo "<tr><td>admin_role</td><td>" . ($_SESSION['admin_role'] ?? '-') . "</td></tr>";
    echo "<tr><td>admin_department</td><td>" . ($_SESSION['admin_department'] ?? '-') . "</td></tr>";
    echo "</table>";
    $current_admin_id = $_SESSION['admin_id'] ?? null;
} else {
    echo "<div class='error'>❌ Not logged in!</div>";
    echo "</div></body></html>";
    exit();
}
echo "</div>";

// All notifications in database
echo "<div class='box'><h2>2. All Admin Notifications in Database</h2>";
$all_notifs = $conn->query("SELECT * FROM admin_notifications ORDER BY created_at DESC");
if ($all_notifs && $all_notifs->num_rows > 0) {
    echo "<p><strong>Total notifications:</strong> " . $all_notifs->num_rows . "</p>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Admin ID</th><th>Title</th><th>Message</th><th>Type</th><th>Action</th><th>Applicant</th><th>Read</th><th>Created</th></tr>";
    while ($n = $all_notifs->fetch_assoc()) {
        $highlight = ($n['admin_id'] == $current_admin_id) ? 'class="highlight"' : '';
        echo "<tr $highlight>";
        echo "<td>{$n['id']}</td>";
        echo "<td>" . ($n['admin_id'] ?? '<em>NULL</em>') . "</td>";
        echo "<td>{$n['title']}</td>";
        echo "<td>" . substr($n['message'], 0, 50) . "...</td>";
        echo "<td>{$n['type']}</td>";
        echo "<td>{$n['action_type']}</td>";
        echo "<td>" . ($n['applicant_name'] ?? '-') . "</td>";
        echo "<td>" . ($n['is_read'] ? '✓' : '✗') . "</td>";
        echo "<td>{$n['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p><small>Yellow rows = notifications for your current admin_id ($current_admin_id)</small></p>";
} else {
    echo "<div class='error'>❌ NO notifications in database!</div>";
}
echo "</div>";

// Notifications for current admin
echo "<div class='box'><h2>3. Notifications for Current Admin ID: $current_admin_id</h2>";
$my_notifs = $conn->prepare("SELECT * FROM admin_notifications WHERE (admin_id = ? OR admin_id IS NULL) ORDER BY created_at DESC");
$my_notifs->bind_param("i", $current_admin_id);
$my_notifs->execute();
$result = $my_notifs->get_result();

if ($result->num_rows > 0) {
    echo "<div class='success'>✅ Found " . $result->num_rows . " notification(s) for you</div>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Title</th><th>Message</th><th>Read</th><th>Created</th></tr>";
    while ($n = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$n['id']}</td>";
        echo "<td>{$n['title']}</td>";
        echo "<td>{$n['message']}</td>";
        echo "<td>" . ($n['is_read'] ? 'Yes' : 'No') . "</td>";
        echo "<td>{$n['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='error'>❌ NO notifications for admin_id: $current_admin_id</div>";
    echo "<div class='info'>The API query: WHERE (admin_id = $current_admin_id OR admin_id IS NULL)</div>";
}
echo "</div>";

// Recent secretary transfers
echo "<div class='box'><h2>4. Recent Secretary Transfers</h2>";
$transfers = $conn->query("SELECT * FROM job_applicants WHERE workflow_stage = 'department_head_review' ORDER BY transferred_to_dept_head_date DESC LIMIT 5");
if ($transfers && $transfers->num_rows > 0) {
    echo "<p><strong>Applications transferred to department head:</strong> " . $transfers->num_rows . "</p>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Position</th><th>Department</th><th>Transferred Date</th></tr>";
    while ($t = $transfers->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$t['id']}</td>";
        echo "<td>{$t['full_name']}</td>";
        echo "<td>{$t['position']}</td>";
        echo "<td>" . ($t['assigned_to_department'] ?? '<span style="color:red;">NULL</span>') . "</td>";
        echo "<td>" . ($t['transferred_to_dept_head_date'] ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='info'>ℹ️ No recent transfers found</div>";
}
echo "</div>";

// Department heads
echo "<div class='box'><h2>5. Department Heads in System</h2>";
$dept_heads = $conn->query("SELECT id, full_name, email, department, status FROM admin_users WHERE role = 'Department Head'");
if ($dept_heads && $dept_heads->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Department</th><th>Status</th><th>Notifications</th></tr>";
    while ($dh = $dept_heads->fetch_assoc()) {
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin_notifications WHERE admin_id = ?");
        $count_stmt->bind_param("i", $dh['id']);
        $count_stmt->execute();
        $count = $count_stmt->get_result()->fetch_assoc()['count'];
        
        $highlight = ($dh['id'] == $current_admin_id) ? 'class="highlight"' : '';
        echo "<tr $highlight>";
        echo "<td>{$dh['id']}</td>";
        echo "<td>{$dh['full_name']}</td>";
        echo "<td>{$dh['email']}</td>";
        echo "<td>" . ($dh['department'] ?? '<span style="color:red;">NULL</span>') . "</td>";
        echo "<td>{$dh['status']}</td>";
        echo "<td><strong>$count</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='error'>❌ No department heads found!</div>";
}
echo "</div>";

// PHP Error Log check
echo "<div class='box'><h2>6. Check PHP Error Log</h2>";
$error_log = 'C:/xampp/apache/logs/error.log';
if (file_exists($error_log)) {
    $lines = file($error_log);
    $recent = array_slice($lines, -20);
    
    echo "<p><strong>Last 20 lines from error log:</strong></p>";
    echo "<pre style='background:#f8f9fa; padding:10px; border-radius:5px; max-height:300px; overflow-y:auto;'>";
    foreach ($recent as $line) {
        if (stripos($line, 'notification') !== false || stripos($line, 'secretary') !== false) {
            echo "<span style='background:yellow;'>" . htmlspecialchars($line) . "</span>";
        } else {
            echo htmlspecialchars($line);
        }
    }
    echo "</pre>";
} else {
    echo "<div class='info'>Error log not found at: $error_log</div>";
}
echo "</div>";

// Test creating a notification
echo "<div class='box'><h2>7. 🧪 Create Test Notification Now</h2>";
if ($current_admin_id) {
    $test_stmt = $conn->prepare("INSERT INTO admin_notifications (admin_id, title, message, type, action_type, applicant_name, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $test_title = "🧪 Manual Test - " . date('H:i:s');
    $test_message = "This is a manually created test notification to verify the system works for admin_id $current_admin_id";
    $test_type = "info";
    $test_action = "manual_test";
    $test_applicant = "Manual Test";
    
    $test_stmt->bind_param("isssss", $current_admin_id, $test_title, $test_message, $test_type, $test_action, $test_applicant);
    
    if ($test_stmt->execute()) {
        $test_id = $test_stmt->insert_id;
        echo "<div class='success'>✅ Test notification created! ID: $test_id for admin_id: $current_admin_id</div>";
        echo "<p><strong>Now refresh your admin panel and click the bell icon!</strong></p>";
    } else {
        echo "<div class='error'>❌ Failed: " . $test_stmt->error . "</div>";
    }
}
echo "</div>";

echo "<hr>";
echo "<p><a href='index.php' style='background:#1e3a8a; color:white; padding:10px 20px; border-radius:5px; text-decoration:none;'>← Back to Admin Panel</a></p>";

$conn->close();
echo "</body></html>";
?>
