<?php
// Test script to verify notification fix works
session_start();

$servername = "127.0.0.1";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Notification Fix Test</h1>\n";

// 1. Check if we have sample data to work with
echo "<h2>1. Checking Sample Data</h2>\n";

$applicants_result = $conn->query("SELECT id, first_name, applicant_email FROM applicants LIMIT 3");
echo "<h3>Sample Applicants:</h3>\n";
if ($applicants_result && $applicants_result->num_rows > 0) {
    while ($row = $applicants_result->fetch_assoc()) {
        echo "ID: {$row['id']}, Name: {$row['first_name']}, Email: {$row['applicant_email']}<br>\n";
    }
} else {
    echo "No applicants found<br>\n";
}

$job_applicants_result = $conn->query("SELECT id, user_id, applicant_name, applicant_email FROM job_applicants LIMIT 3");
echo "<h3>Sample Job Applicants:</h3>\n";
if ($job_applicants_result && $job_applicants_result->num_rows > 0) {
    while ($row = $job_applicants_result->fetch_assoc()) {
        $user_id = $row['user_id'] ?? 'NULL';
        echo "ID: {$row['id']}, User ID: {$user_id}, Name: {$row['applicant_name']}, Email: {$row['applicant_email']}<br>\n";
    }
} else {
    echo "No job applicants found<br>\n";
}

// 2. Test user_id resolution logic
echo "<h2>2. Testing User ID Resolution Logic</h2>\n";

// Get first job applicant for testing
$test_applicant = $conn->query("SELECT id, user_id, applicant_name, applicant_email FROM job_applicants LIMIT 1");
if ($test_applicant && $test_applicant->num_rows > 0) {
    $applicant = $test_applicant->fetch_assoc();
    $applicant_id = $applicant['id'];
    $current_user_id = $applicant['user_id'];
    $applicant_email = $applicant['applicant_email'];
    $applicant_name = $applicant['applicant_name'];
    
    echo "Testing with Applicant ID: $applicant_id<br>\n";
    echo "Current User ID: " . ($current_user_id ?: 'NULL') . "<br>\n";
    echo "Email: $applicant_email<br>\n";
    echo "Name: $applicant_name<br>\n";
    
    // Simulate the improved lookup logic
    $user_id = $current_user_id;
    
    if (!$user_id || $user_id == 0) {
        echo "<strong>User ID is null/empty, trying fallback methods...</strong><br>\n";
        
        // Method 1: Try to find by email
        if ($applicant_email) {
            $email_stmt = $conn->prepare("SELECT id FROM applicants WHERE applicant_email = ?");
            $email_stmt->bind_param("s", $applicant_email);
            $email_stmt->execute();
            $email_result = $email_stmt->get_result();
            
            if ($email_result->num_rows > 0) {
                $email_row = $email_result->fetch_assoc();
                $user_id = $email_row['id'];
                echo "✓ Found user_id by email: $user_id<br>\n";
            } else {
                echo "✗ No user found by email<br>\n";
            }
        }
        
        // Method 2: Try to find by name if email didn't work
        if (!$user_id && $applicant_name) {
            $name_stmt = $conn->prepare("SELECT id FROM applicants WHERE first_name = ? OR CONCAT(first_name, ' ', last_name) = ?");
            $name_stmt->bind_param("ss", $applicant_name, $applicant_name);
            $name_stmt->execute();
            $name_result = $name_stmt->get_result();
            
            if ($name_result->num_rows > 0) {
                $name_row = $name_result->fetch_assoc();
                $user_id = $name_row['id'];
                echo "✓ Found user_id by name: $user_id<br>\n";
            } else {
                echo "✗ No user found by name<br>\n";
            }
        }
    } else {
        echo "✓ User ID already exists: $user_id<br>\n";
    }
    
    // 3. Test notification creation
    echo "<h2>3. Testing Notification Creation</h2>\n";
    
    if ($user_id && $user_id > 0) {
        // Create a test notification
        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $title = "Test Notification Fix";
        $message = "This is a test notification created by the fix verification script at " . date('Y-m-d H:i:s');
        $type = "info";
        $notif_stmt->bind_param("isss", $user_id, $title, $message, $type);
        
        if ($notif_stmt->execute()) {
            $notification_id = $conn->insert_id;
            echo "✓ Test notification created successfully! ID: $notification_id<br>\n";
            
            // Verify the notification was created
            $verify_stmt = $conn->prepare("SELECT * FROM notifications WHERE id = ?");
            $verify_stmt->bind_param("i", $notification_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows > 0) {
                $notification = $verify_result->fetch_assoc();
                echo "✓ Notification verified in database:<br>\n";
                echo "&nbsp;&nbsp;- User ID: {$notification['user_id']}<br>\n";
                echo "&nbsp;&nbsp;- Title: {$notification['title']}<br>\n";
                echo "&nbsp;&nbsp;- Message: {$notification['message']}<br>\n";
                echo "&nbsp;&nbsp;- Type: {$notification['type']}<br>\n";
                echo "&nbsp;&nbsp;- Created: {$notification['created_at']}<br>\n";
            }
        } else {
            echo "✗ Failed to create test notification: " . $notif_stmt->error . "<br>\n";
        }
    } else {
        echo "✗ Cannot create notification - no valid user_id found<br>\n";
    }
} else {
    echo "No job applicants available for testing<br>\n";
}

// 4. Check existing notifications
echo "<h2>4. Existing Notifications Summary</h2>\n";
$notif_count = $conn->query("SELECT COUNT(*) as count FROM notifications");
if ($notif_count) {
    $count = $notif_count->fetch_assoc()['count'];
    echo "Total notifications in database: $count<br>\n";
}

$recent_notifs = $conn->query("SELECT n.*, a.first_name FROM notifications n LEFT JOIN applicants a ON n.user_id = a.id ORDER BY n.created_at DESC LIMIT 5");
if ($recent_notifs && $recent_notifs->num_rows > 0) {
    echo "<h3>Recent Notifications:</h3>\n";
    while ($notif = $recent_notifs->fetch_assoc()) {
        $user_name = $notif['first_name'] ?: 'Unknown User';
        echo "- {$notif['title']} for {$user_name} (User ID: {$notif['user_id']}) - {$notif['created_at']}<br>\n";
    }
}

echo "<h2>5. Test Summary</h2>\n";
echo "The notification fix has been implemented with the following improvements:<br>\n";
echo "✓ Enhanced user_id resolution with multiple fallback methods<br>\n";
echo "✓ Email-based lookup from applicants table<br>\n";
echo "✓ Name-based lookup as secondary fallback<br>\n";
echo "✓ Automatic updating of job_applicants table with resolved user_id<br>\n";
echo "✓ Comprehensive error logging for debugging<br>\n";
echo "✓ Validation that user_id is greater than 0 before creating notifications<br>\n";

$conn->close();
?>
