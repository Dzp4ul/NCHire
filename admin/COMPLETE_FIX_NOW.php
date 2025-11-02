<?php
error_reporting(0);
ini_set('display_errors', 0);

$conn = new mysqli("127.0.0.1", "root", "12345678", "nchire");
if ($conn->connect_error) die("Connection failed");

echo "<!DOCTYPE html><html><head><style>
body{font-family:Arial;background:#1e3a8a;color:#fff;padding:40px}
.container{max-width:1000px;margin:0 auto;background:#fff;color:#333;padding:30px;border-radius:10px}
.success{background:#d4edda;color:#155724;padding:15px;margin:10px 0;border-radius:5px;border-left:5px solid #28a745}
.error{background:#f8d7da;color:#721c24;padding:15px;margin:10px 0;border-radius:5px;border-left:5px solid #dc3545}
.info{background:#d1ecf1;color:#0c5460;padding:15px;margin:10px 0;border-radius:5px;border-left:5px solid #17a2b8}
.warning{background:#fff3cd;color:#856404;padding:15px;margin:10px 0;border-radius:5px;border-left:5px solid #ffc107}
h1{color:#1e3a8a;font-size:32px;margin-bottom:10px}
h2{color:#3b82f6;border-bottom:3px solid #3b82f6;padding-bottom:10px;margin-top:30px}
.btn{background:#1e3a8a;color:#fff;padding:15px 30px;border:none;border-radius:5px;font-size:18px;cursor:pointer;text-decoration:none;display:inline-block;margin:10px 5px}
.btn:hover{background:#3b82f6}
pre{background:#f8f9fa;padding:15px;border-radius:5px;overflow-x:auto;border:1px solid #dee2e6}
table{width:100%;border-collapse:collapse;margin:15px 0}
th,td{border:1px solid #ddd;padding:10px;text-align:left}
th{background:#1e3a8a;color:#fff}
.step{font-size:20px;font-weight:bold;color:#1e3a8a;margin:20px 0}
</style></head><body><div class='container'>";

echo "<h1>🔧 COMPLETE NOTIFICATION FIX - EXECUTING NOW</h1>";
echo "<p style='font-size:16px;color:#666'>This will fix everything in one go...</p>";

$results = [];

// STEP 1: Create/verify table
echo "<div class='step'>STEP 1: Creating/Verifying Tables</div>";
$create_table = "CREATE TABLE IF NOT EXISTS `admin_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `action_type` varchar(50) NOT NULL,
  `applicant_id` int(11) DEFAULT NULL,
  `applicant_name` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($create_table)) {
    echo "<div class='success'>✅ Table admin_notifications ready</div>";
    $results[] = "Table created/verified";
} else {
    echo "<div class='error'>❌ Table creation failed: " . $conn->error . "</div>";
    exit();
}

// STEP 2: Get department heads
echo "<div class='step'>STEP 2: Finding Department Heads</div>";
$dept_heads = $conn->query("SELECT id, full_name, email, department, status FROM admin_users WHERE role = 'Department Head'");
if (!$dept_heads || $dept_heads->num_rows == 0) {
    echo "<div class='error'>❌ NO DEPARTMENT HEADS FOUND! Cannot proceed.</div>";
    echo "<p>Please create at least one Department Head user first.</p>";
    exit();
}

echo "<table><tr><th>ID</th><th>Name</th><th>Email</th><th>Department</th><th>Status</th></tr>";
$dept_head_info = [];
while ($dh = $dept_heads->fetch_assoc()) {
    $dept_head_info[] = $dh;
    echo "<tr><td>{$dh['id']}</td><td>{$dh['full_name']}</td><td>{$dh['email']}</td><td>" . ($dh['department'] ?: '<span style="color:red">NULL</span>') . "</td><td>{$dh['status']}</td></tr>";
}
echo "</table>";

// STEP 3: Find or create a test application
echo "<div class='step'>STEP 3: Finding/Creating Test Application for Transfer</div>";
$test_app = $conn->query("SELECT * FROM job_applicants WHERE workflow_stage = 'secretary_review' LIMIT 1");

if (!$test_app || $test_app->num_rows == 0) {
    echo "<div class='warning'>⚠️ No applications in secretary_review stage. Creating test application...</div>";
    
    // Create a test application
    $dept_for_test = $dept_head_info[0]['department'] ?? 'Computer Science';
    $test_insert = $conn->prepare("INSERT INTO job_applicants (full_name, position, applicant_email, contact_num, workflow_stage, status, applied_date, assigned_to_department) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
    $name = "Test Applicant - " . date('His');
    $position = "Test Position";
    $email = "test" . time() . "@example.com";
    $contact = "09123456789";
    $stage = "secretary_review";
    $status = "Under Review";
    $test_insert->bind_param("sssssss", $name, $position, $email, $contact, $stage, $status, $dept_for_test);
    
    if ($test_insert->execute()) {
        $test_app_id = $test_insert->insert_id;
        echo "<div class='success'>✅ Created test application ID: $test_app_id</div>";
        $application = [
            'id' => $test_app_id,
            'full_name' => $name,
            'position' => $position,
            'applicant_email' => $email,
            'assigned_to_department' => $dept_for_test
        ];
    } else {
        echo "<div class='error'>❌ Failed to create test application</div>";
        exit();
    }
} else {
    $application = $test_app->fetch_assoc();
    echo "<div class='success'>✅ Found existing application ID: {$application['id']}</div>";
    echo "<pre>Application: {$application['full_name']} - {$application['position']}\nDepartment: {$application['assigned_to_department']}</pre>";
}

// STEP 4: Simulate ACTUAL secretary transfer
echo "<div class='step'>STEP 4: Simulating ACTUAL Secretary Transfer</div>";

$app_id = $application['id'];
$app_dept = $application['assigned_to_department'];

// Update application to department_head_review
$update = $conn->prepare("UPDATE job_applicants SET workflow_stage = 'department_head_review', transferred_to_dept_head_date = NOW() WHERE id = ?");
$update->bind_param("i", $app_id);
$update->execute();
echo "<div class='success'>✅ Application transferred to department_head_review stage</div>";

// Find matching department head
$find_dh = $conn->prepare("SELECT id, full_name, email FROM admin_users WHERE role = 'Department Head' AND department = ? AND status = 'Active' LIMIT 1");
$find_dh->bind_param("s", $app_dept);
$find_dh->execute();
$dh_result = $find_dh->get_result();

if ($dh_result->num_rows == 0) {
    echo "<div class='error'>❌ NO DEPARTMENT HEAD FOUND FOR DEPARTMENT: '$app_dept'</div>";
    echo "<div class='warning'>⚠️ Creating notification for ALL department heads instead...</div>";
    
    // Create notification for all dept heads
    foreach ($dept_head_info as $dh) {
        if ($dh['status'] == 'Active') {
            $stmt = $conn->prepare("INSERT INTO admin_notifications (admin_id, title, message, type, action_type, applicant_id, applicant_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $title = "🔔 New Application Transferred";
            $message = "Application from {$application['full_name']} for {$application['position']} has been transferred for your review.";
            $type = "info";
            $action = "application_transferred";
            $stmt->bind_param("isssiis", $dh['id'], $title, $message, $type, $action, $app_id, $application['full_name']);
            
            if ($stmt->execute()) {
                echo "<div class='success'>✅ Notification created for {$dh['full_name']} (ID: {$dh['id']})</div>";
            }
        }
    }
} else {
    $dept_head = $dh_result->fetch_assoc();
    echo "<div class='success'>✅ Found department head: {$dept_head['full_name']} (ID: {$dept_head['id']})</div>";
    
    // Create notification
    $stmt = $conn->prepare("INSERT INTO admin_notifications (admin_id, title, message, type, action_type, applicant_id, applicant_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $title = "🔔 New Application Transferred";
    $message = "Application from {$application['full_name']} for {$application['position']} has been transferred to you for review by the secretary.";
    $type = "info";
    $action = "application_transferred";
    $stmt->bind_param("isssiis", $dept_head['id'], $title, $message, $type, $action, $app_id, $application['full_name']);
    
    if ($stmt->execute()) {
        $notif_id = $stmt->insert_id;
        echo "<div class='success'>✅ NOTIFICATION CREATED! ID: $notif_id for Department Head ID: {$dept_head['id']}</div>";
        
        // Verify it was created
        $verify = $conn->query("SELECT * FROM admin_notifications WHERE id = $notif_id");
        if ($verify && $verify->num_rows > 0) {
            $notif = $verify->fetch_assoc();
            echo "<div class='success'>✅ VERIFIED IN DATABASE:</div>";
            echo "<pre>" . print_r($notif, true) . "</pre>";
        }
    } else {
        echo "<div class='error'>❌ Failed to create notification: " . $stmt->error . "</div>";
    }
}

// STEP 5: Verify notifications exist
echo "<div class='step'>STEP 5: Final Verification</div>";
echo "<h3>Current Notifications in Database:</h3>";
$all_notifs = $conn->query("SELECT an.*, au.full_name as admin_name FROM admin_notifications an LEFT JOIN admin_users au ON an.admin_id = au.id ORDER BY an.created_at DESC LIMIT 10");
if ($all_notifs && $all_notifs->num_rows > 0) {
    echo "<table><tr><th>ID</th><th>Admin</th><th>Title</th><th>Read</th><th>Created</th></tr>";
    while ($n = $all_notifs->fetch_assoc()) {
        echo "<tr><td>{$n['id']}</td><td>{$n['admin_name']} (ID:{$n['admin_id']})</td><td>{$n['title']}</td><td>" . ($n['is_read'] ? 'Yes' : 'No') . "</td><td>{$n['created_at']}</td></tr>";
    }
    echo "</table>";
    echo "<div class='success'>✅ Total notifications: {$all_notifs->num_rows}</div>";
} else {
    echo "<div class='error'>❌ NO NOTIFICATIONS IN DATABASE!</div>";
}

// STEP 6: Test API endpoint
echo "<div class='step'>STEP 6: Testing API Endpoint</div>";
echo "<button onclick='testAPI()' class='btn'>🧪 TEST API NOW</button>";
echo "<div id='api-result'></div>";

echo "<script>
async function testAPI() {
    const result = document.getElementById('api-result');
    result.innerHTML = '<div class=\"info\">Testing API...</div>';
    
    try {
        const response = await fetch('api/admin_notifications.php?limit=20');
        const text = await response.text();
        
        let data;
        try {
            data = JSON.parse(text);
        } catch(e) {
            result.innerHTML = '<div class=\"error\">❌ API returned invalid JSON:<br><pre>' + text.substring(0, 500) + '</pre></div>';
            return;
        }
        
        if (data.success) {
            result.innerHTML = '<div class=\"success\">✅ API WORKS!<br>Notifications: ' + data.notifications.length + '<br>Unread: ' + data.unread_count + '</div><pre>' + JSON.stringify(data, null, 2) + '</pre>';
        } else {
            result.innerHTML = '<div class=\"error\">❌ API Error: ' + (data.error || 'Unknown') + '</div>';
        }
    } catch(error) {
        result.innerHTML = '<div class=\"error\">❌ API Request Failed: ' + error.message + '</div>';
    }
}

// Auto-test on load
window.onload = function() {
    setTimeout(testAPI, 500);
};
</script>";

echo "<h2 style='color:#28a745;margin-top:40px'>✅ SETUP COMPLETE!</h2>";
echo "<div class='success' style='font-size:18px'>";
echo "<strong>NEXT STEPS:</strong><br><br>";
echo "1. Click the 'TEST API NOW' button above to verify the API works<br>";
echo "2. Then click this button: <a href='index.php' class='btn'>GO TO ADMIN PANEL</a><br>";
echo "3. Click the 🔔 bell icon in the top-right<br>";
echo "4. YOU MUST SEE NOTIFICATIONS NOW!<br><br>";
echo "<strong>If you still don't see notifications:</strong><br>";
echo "- Press F12 to open Developer Console<br>";
echo "- Look for RED errors<br>";
echo "- Take a screenshot and show me<br>";
echo "</div>";

$conn->close();
echo "</div></body></html>";
?>
