<?php
$conn = new mysqli("127.0.0.1", "root", "", "nchire");

echo "<pre style='font-family: monospace; background: #000; color: #0f0; padding: 20px;'>";
echo "=== DATABASE DIRECT CHECK ===\n\n";

// 1. Check if table exists
$tables = $conn->query("SHOW TABLES LIKE 'admin_notifications'");
echo "1. Table 'admin_notifications' exists: " . ($tables->num_rows > 0 ? "YES" : "NO") . "\n\n";

if ($tables->num_rows > 0) {
    // 2. Count all notifications
    $count = $conn->query("SELECT COUNT(*) as c FROM admin_notifications")->fetch_assoc()['c'];
    echo "2. Total notifications in database: $count\n\n";
    
    // 3. Show all notifications
    echo "3. ALL NOTIFICATIONS:\n";
    echo str_repeat("-", 100) . "\n";
    $result = $conn->query("SELECT id, admin_id, title, type, action_type, is_read, created_at FROM admin_notifications ORDER BY created_at DESC LIMIT 10");
    if ($result->num_rows > 0) {
        printf("%-5s %-10s %-40s %-10s %-20s %-6s %-20s\n", "ID", "Admin ID", "Title", "Type", "Action", "Read", "Created");
        echo str_repeat("-", 100) . "\n";
        while ($row = $result->fetch_assoc()) {
            printf("%-5d %-10s %-40s %-10s %-20s %-6s %-20s\n", 
                $row['id'], 
                $row['admin_id'] ?? 'NULL', 
                substr($row['title'], 0, 40), 
                $row['type'], 
                $row['action_type'],
                $row['is_read'] ? 'Yes' : 'No',
                $row['created_at']
            );
        }
    } else {
        echo "NO NOTIFICATIONS FOUND!\n";
    }
    echo "\n";
    
    // 4. Show department heads
    echo "4. DEPARTMENT HEADS:\n";
    echo str_repeat("-", 100) . "\n";
    $dh = $conn->query("SELECT id, full_name, department, status FROM admin_users WHERE role = 'Department Head'");
    if ($dh->num_rows > 0) {
        printf("%-5s %-30s %-30s %-10s %-15s\n", "ID", "Name", "Department", "Status", "Notifications");
        echo str_repeat("-", 100) . "\n";
        while ($row = $dh->fetch_assoc()) {
            $notif_count = $conn->query("SELECT COUNT(*) as c FROM admin_notifications WHERE admin_id = {$row['id']}")->fetch_assoc()['c'];
            printf("%-5d %-30s %-30s %-10s %-15d\n", 
                $row['id'], 
                $row['full_name'], 
                $row['department'] ?? 'NULL', 
                $row['status'],
                $notif_count
            );
        }
    } else {
        echo "NO DEPARTMENT HEADS FOUND!\n";
    }
    echo "\n";
    
    // 5. Recent transfers
    echo "5. RECENT SECRETARY TRANSFERS:\n";
    echo str_repeat("-", 100) . "\n";
    $transfers = $conn->query("SELECT id, full_name, position, assigned_to_department, transferred_to_dept_head_date FROM job_applicants WHERE workflow_stage = 'department_head_review' ORDER BY id DESC LIMIT 5");
    if ($transfers->num_rows > 0) {
        printf("%-5s %-25s %-25s %-25s %-20s\n", "ID", "Name", "Position", "Department", "Transferred");
        echo str_repeat("-", 100) . "\n";
        while ($row = $transfers->fetch_assoc()) {
            printf("%-5d %-25s %-25s %-25s %-20s\n", 
                $row['id'], 
                $row['full_name'], 
                $row['position'], 
                $row['assigned_to_department'] ?? 'NULL',
                $row['transferred_to_dept_head_date'] ?? 'NULL'
            );
        }
    } else {
        echo "NO TRANSFERS FOUND!\n";
    }
    echo "\n";
    
    // 6. Create test notification for each department head
    echo "6. CREATING TEST NOTIFICATIONS:\n";
    echo str_repeat("-", 100) . "\n";
    $dh_query = $conn->query("SELECT id, full_name FROM admin_users WHERE role = 'Department Head' AND status = 'Active'");
    if ($dh_query->num_rows > 0) {
        while ($dh_row = $dh_query->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO admin_notifications (admin_id, title, message, type, action_type, applicant_name, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $title = "TEST: Notification System Check - " . date('H:i:s');
            $message = "This is a test notification created at " . date('Y-m-d H:i:s') . " for " . $dh_row['full_name'];
            $type = "info";
            $action = "system_test";
            $applicant = "Test System";
            $stmt->bind_param("isssss", $dh_row['id'], $title, $message, $type, $action, $applicant);
            
            if ($stmt->execute()) {
                echo "✓ Created notification ID: " . $stmt->insert_id . " for admin_id: {$dh_row['id']} ({$dh_row['full_name']})\n";
            } else {
                echo "✗ Failed for admin_id: {$dh_row['id']} - " . $stmt->error . "\n";
            }
        }
    } else {
        echo "No active department heads to create notifications for!\n";
    }
    echo "\n";
    
    // 7. Verify test notifications were created
    echo "7. VERIFICATION - NOTIFICATIONS AFTER CREATION:\n";
    echo str_repeat("-", 100) . "\n";
    $verify = $conn->query("SELECT admin_id, COUNT(*) as count FROM admin_notifications GROUP BY admin_id");
    if ($verify->num_rows > 0) {
        printf("%-10s %-15s\n", "Admin ID", "Notifications");
        echo str_repeat("-", 30) . "\n";
        while ($row = $verify->fetch_assoc()) {
            printf("%-10s %-15d\n", $row['admin_id'] ?? 'NULL', $row['count']);
        }
    }
}

echo "\n=== END CHECK ===\n";
echo "</pre>";

echo "<p style='font-size:18px; padding:20px;'>";
echo "<strong>Next Steps:</strong><br>";
echo "1. Go to admin panel: <a href='index.php' style='background:#1e3a8a; color:white; padding:10px 20px; border-radius:5px; text-decoration:none;'>Admin Panel</a><br><br>";
echo "2. Click the bell icon (🔔) in top-right corner<br><br>";
echo "3. You should see the test notification(s) created above<br><br>";
echo "4. If still not showing, press F12 and check Console for errors";
echo "</p>";

$conn->close();
?>
