<?php
/**
 * Test Script for Secretary Transfer Notifications
 * This script helps debug why notifications aren't appearing
 */

session_start();

// Simulate being logged in as secretary
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = 1;
$_SESSION['admin_name'] = 'Test Secretary';
$_SESSION['admin_role'] = 'Secretary';

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<html><head><style>
body { font-family: Arial, sans-serif; padding: 20px; }
.success { color: green; background: #d4edda; padding: 10px; margin: 10px 0; border-radius: 5px; }
.error { color: red; background: #f8d7da; padding: 10px; margin: 10px 0; border-radius: 5px; }
.info { color: blue; background: #d1ecf1; padding: 10px; margin: 10px 0; border-radius: 5px; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
th { background: #007bff; color: white; }
</style></head><body>";

echo "<h1>🔍 Secretary Transfer Notification Test</h1>";

// Step 1: Check if tables exist
echo "<h2>Step 1: Check Database Tables</h2>";

$tables_to_check = ['admin_notifications', 'notifications', 'job_applicants', 'admin_users', 'applicants'];
foreach ($tables_to_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "<div class='success'>✅ Table '$table' exists</div>";
    } else {
        echo "<div class='error'>❌ Table '$table' does NOT exist!</div>";
    }
}

// Step 2: Check email helper file
echo "<h2>Step 2: Check Email Helper Files</h2>";

$email_helpers = [
    __DIR__ . '/../helpers/email_helper.php',
    __DIR__ . '/helpers/email_helper.php',
    __DIR__ . '/email_helper.php'
];

foreach ($email_helpers as $path) {
    if (file_exists($path)) {
        echo "<div class='success'>✅ Email helper found at: $path</div>";
        require_once $path;
        
        // Check if function exists
        if (function_exists('sendEmailNotification')) {
            echo "<div class='success'>✅ sendEmailNotification() function is available</div>";
        } else {
            echo "<div class='error'>❌ sendEmailNotification() function NOT found after including file</div>";
        }
        break;
    } else {
        echo "<div class='info'>ℹ️ Email helper not found at: $path</div>";
    }
}

// Step 3: Find test application
echo "<h2>Step 3: Find Test Application</h2>";

$app_query = "SELECT ja.*, a.applicant_email, a.first_name, a.last_name 
              FROM job_applicants ja 
              LEFT JOIN applicants a ON ja.user_id = a.id 
              WHERE ja.workflow_stage = 'secretary_review'
              ORDER BY ja.id DESC 
              LIMIT 1";
$app_result = $conn->query($app_query);

if ($app_result && $app_result->num_rows > 0) {
    $application = $app_result->fetch_assoc();
    echo "<div class='success'>✅ Found test application (ID: {$application['id']})</div>";
    
    echo "<table>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>Application ID</td><td>{$application['id']}</td></tr>";
    echo "<tr><td>Full Name</td><td>{$application['full_name']}</td></tr>";
    echo "<tr><td>Position</td><td>{$application['position']}</td></tr>";
    echo "<tr><td>Department</td><td>{$application['assigned_to_department']}</td></tr>";
    echo "<tr><td>Applicant Email</td><td>{$application['applicant_email']}</td></tr>";
    echo "<tr><td>Workflow Stage</td><td>{$application['workflow_stage']}</td></tr>";
    echo "</table>";
    
    $department = $application['assigned_to_department'];
    $applicant_email = $application['applicant_email'];
    
    // Step 4: Find Department Head
    echo "<h2>Step 4: Find Department Head for '$department'</h2>";
    
    if ($department) {
        $dept_head_query = "SELECT id, full_name, email, department, role, status 
                           FROM admin_users 
                           WHERE role = 'Department Head' AND department = ? AND status = 'Active'";
        $dept_stmt = $conn->prepare($dept_head_query);
        $dept_stmt->bind_param("s", $department);
        $dept_stmt->execute();
        $dept_result = $dept_stmt->get_result();
        
        if ($dept_result->num_rows > 0) {
            echo "<div class='success'>✅ Found " . $dept_result->num_rows . " department head(s)</div>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Department</th><th>Role</th><th>Status</th></tr>";
            while ($dept_head = $dept_result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$dept_head['id']}</td>";
                echo "<td>{$dept_head['full_name']}</td>";
                echo "<td>{$dept_head['email']}</td>";
                echo "<td>{$dept_head['department']}</td>";
                echo "<td>{$dept_head['role']}</td>";
                echo "<td>{$dept_head['status']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='error'>❌ No active Department Head found for department: '$department'</div>";
            
            // Show all department heads
            $all_dept_heads = $conn->query("SELECT id, full_name, email, department, role, status FROM admin_users WHERE role = 'Department Head'");
            if ($all_dept_heads->num_rows > 0) {
                echo "<div class='info'>ℹ️ Available Department Heads:</div>";
                echo "<table>";
                echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Department</th><th>Status</th></tr>";
                while ($dh = $all_dept_heads->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>{$dh['id']}</td>";
                    echo "<td>{$dh['full_name']}</td>";
                    echo "<td>{$dh['email']}</td>";
                    echo "<td>{$dh['department']}</td>";
                    echo "<td>{$dh['status']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        }
    } else {
        echo "<div class='error'>❌ Application has no department assigned!</div>";
    }
    
    // Step 5: Test creating notifications
    echo "<h2>Step 5: Test Creating Notifications</h2>";
    
    if ($applicant_email) {
        echo "<h3>A. Test Applicant Notification</h3>";
        try {
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_email, user_name, title, message, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $test_title = "TEST: Application Transferred";
            $test_message = "This is a test notification to verify the system works.";
            $test_type = "info";
            $applicant_name = $application['first_name'] ?? $application['full_name'];
            $notif_stmt->bind_param("sssss", $applicant_email, $applicant_name, $test_title, $test_message, $test_type);
            
            if ($notif_stmt->execute()) {
                $notif_id = $notif_stmt->insert_id;
                echo "<div class='success'>✅ Test applicant notification created successfully! (ID: $notif_id)</div>";
            } else {
                echo "<div class='error'>❌ Failed to create applicant notification: " . $notif_stmt->error . "</div>";
            }
            $notif_stmt->close();
        } catch (Exception $e) {
            echo "<div class='error'>❌ Exception: " . $e->getMessage() . "</div>";
        }
    }
    
    // Test admin notification
    echo "<h3>B. Test Department Head Notification</h3>";
    if ($department) {
        $dept_stmt = $conn->prepare("SELECT id, full_name, email FROM admin_users WHERE role = 'Department Head' AND department = ? AND status = 'Active' LIMIT 1");
        $dept_stmt->bind_param("s", $department);
        $dept_stmt->execute();
        $dept_result = $dept_stmt->get_result();
        
        if ($dept_result->num_rows > 0) {
            $dept_head = $dept_result->fetch_assoc();
            $dept_head_id = $dept_head['id'];
            
            try {
                $admin_notif_stmt = $conn->prepare("INSERT INTO admin_notifications (admin_id, title, message, type, action_type, applicant_id, applicant_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $admin_title = "TEST: New Application Transferred";
                $admin_message = "This is a test notification for department head.";
                $admin_type = "info";
                $admin_action = "application_transferred";
                $admin_notif_stmt->bind_param("isssiis", $dept_head_id, $admin_title, $admin_message, $admin_type, $admin_action, $application['id'], $application['full_name']);
                
                if ($admin_notif_stmt->execute()) {
                    $admin_notif_id = $admin_notif_stmt->insert_id;
                    echo "<div class='success'>✅ Test department head notification created successfully! (ID: $admin_notif_id)</div>";
                    echo "<div class='info'>ℹ️ Notification sent to: {$dept_head['full_name']} (ID: $dept_head_id)</div>";
                } else {
                    echo "<div class='error'>❌ Failed to create admin notification: " . $admin_notif_stmt->error . "</div>";
                }
                $admin_notif_stmt->close();
            } catch (Exception $e) {
                echo "<div class='error'>❌ Exception: " . $e->getMessage() . "</div>";
            }
        }
    }
    
    // Step 6: Show recent notifications
    echo "<h2>Step 6: Recent Notifications</h2>";
    
    echo "<h3>A. Applicant Notifications</h3>";
    $recent_notif = $conn->query("SELECT * FROM notifications WHERE user_email = '$applicant_email' ORDER BY created_at DESC LIMIT 5");
    if ($recent_notif && $recent_notif->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Title</th><th>Message</th><th>Type</th><th>Created</th></tr>";
        while ($n = $recent_notif->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$n['id']}</td>";
            echo "<td>{$n['title']}</td>";
            echo "<td>" . substr($n['message'], 0, 50) . "...</td>";
            echo "<td>{$n['type']}</td>";
            echo "<td>{$n['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>ℹ️ No notifications found for applicant</div>";
    }
    
    echo "<h3>B. Admin Notifications (All)</h3>";
    $admin_notif = $conn->query("SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT 10");
    if ($admin_notif && $admin_notif->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Admin ID</th><th>Title</th><th>Type</th><th>Action</th><th>Applicant</th><th>Read</th><th>Created</th></tr>";
        while ($an = $admin_notif->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$an['id']}</td>";
            echo "<td>" . ($an['admin_id'] ?? 'NULL (All Admins)') . "</td>";
            echo "<td>{$an['title']}</td>";
            echo "<td>{$an['type']}</td>";
            echo "<td>{$an['action_type']}</td>";
            echo "<td>{$an['applicant_name']}</td>";
            echo "<td>" . ($an['is_read'] ? 'Yes' : 'No') . "</td>";
            echo "<td>{$an['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>ℹ️ No admin notifications found</div>";
    }
    
} else {
    echo "<div class='error'>❌ No applications found in 'secretary_review' stage</div>";
    
    // Show all applications
    $all_apps = $conn->query("SELECT id, full_name, position, workflow_stage, assigned_to_department FROM job_applicants ORDER BY id DESC LIMIT 10");
    if ($all_apps && $all_apps->num_rows > 0) {
        echo "<div class='info'>ℹ️ Recent Applications:</div>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>Position</th><th>Department</th><th>Stage</th></tr>";
        while ($app = $all_apps->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$app['id']}</td>";
            echo "<td>{$app['full_name']}</td>";
            echo "<td>{$app['position']}</td>";
            echo "<td>{$app['assigned_to_department']}</td>";
            echo "<td>{$app['workflow_stage']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

echo "<hr>";
echo "<p><a href='index.php'>← Back to Admin Panel</a></p>";

$conn->close();
echo "</body></html>";
?>
