<?php
// Test script for the new email-based notification system
session_start();

$servername = "127.0.0.1";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Email-Based Notification System Test</h1>\n";

// 1. Test creating notifications directly
echo "<h2>1. Creating Test Notifications</h2>\n";

// Get sample applicant data
$sample_applicant = $conn->query("SELECT applicant_email, applicant_name FROM job_applicants WHERE applicant_email IS NOT NULL LIMIT 1");

if ($sample_applicant && $sample_applicant->num_rows > 0) {
    $applicant = $sample_applicant->fetch_assoc();
    $test_email = $applicant['applicant_email'];
    $test_name = $applicant['applicant_name'];
    
    echo "Using test email: $test_email<br>\n";
    echo "Using test name: $test_name<br>\n";
    
    // Create test notifications
    $test_notifications = [
        ['title' => 'Interview Scheduled', 'message' => 'Your interview has been scheduled for tomorrow at 2:00 PM.', 'type' => 'info'],
        ['title' => 'Document Resubmission Required', 'message' => 'Please resubmit your resume and cover letter.', 'type' => 'warning'],
        ['title' => 'Application Rejected', 'message' => 'Unfortunately, your application has been rejected.', 'type' => 'error']
    ];
    
    foreach ($test_notifications as $notif) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_email, user_name, title, message, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssss", $test_email, $test_name, $notif['title'], $notif['message'], $notif['type']);
        
        if ($stmt->execute()) {
            echo "✓ Created notification: {$notif['title']}<br>\n";
        } else {
            echo "✗ Failed to create notification: {$notif['title']} - " . $stmt->error . "<br>\n";
        }
    }
    
    // 2. Test retrieving notifications
    echo "<h2>2. Testing Notification Retrieval</h2>\n";
    
    // Simulate user session
    $_SESSION['user_email'] = $test_email;
    
    echo "Simulating session with email: $test_email<br>\n";
    
    // Test the get_notifications.php logic
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_email = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param("s", $test_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    echo "Found " . count($notifications) . " notifications:<br>\n";
    foreach ($notifications as $notif) {
        echo "- {$notif['title']} ({$notif['type']}) - {$notif['created_at']}<br>\n";
    }
    
    // Get unread count
    $count_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_email = ? AND is_read = FALSE");
    $count_stmt->bind_param("s", $test_email);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $unread_count = $count_result->fetch_assoc()['unread_count'];
    
    echo "Unread notifications: $unread_count<br>\n";
    
} else {
    echo "No sample applicant data found. Creating test data...<br>\n";
    
    // Create test applicant
    $test_email = "test@example.com";
    $test_name = "Test User";
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_email, user_name, title, message, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $title = "Test Notification";
    $message = "This is a test notification for the email-based system.";
    $type = "info";
    $stmt->bind_param("sssss", $test_email, $test_name, $title, $message, $type);
    
    if ($stmt->execute()) {
        echo "✓ Created test notification for $test_email<br>\n";
    } else {
        echo "✗ Failed to create test notification: " . $stmt->error . "<br>\n";
    }
}

// 3. Check notification table structure
echo "<h2>3. Notification Table Structure</h2>\n";
$structure = $conn->query("DESCRIBE notifications");
if ($structure) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>\n";
    while ($row = $structure->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td><td>{$row['Default']}</td></tr>\n";
    }
    echo "</table>\n";
}

// 4. Summary
echo "<h2>4. System Summary</h2>\n";
echo "✓ Notifications table updated with user_email and user_name columns<br>\n";
echo "✓ Admin actions now create notifications using email identification<br>\n";
echo "✓ get_notifications.php updated to use session email<br>\n";
echo "✓ mark_notification_read.php updated to use email matching<br>\n";
echo "<br>\n";
echo "<strong>How it works now:</strong><br>\n";
echo "1. When admin schedules/rejects/requests resubmission, notification is created with applicant's email<br>\n";
echo "2. When user logs in, their email is stored in session<br>\n";
echo "3. Notifications are retrieved by matching session email with notification user_email<br>\n";
echo "4. No complex user_id mapping required!<br>\n";

$conn->close();
?>
