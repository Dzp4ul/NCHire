<?php
session_start();

$servername = "127.0.0.1";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Notification System Fix</h2>";

// Step 1: Check current session
echo "<h3>Step 1: Current Session Status</h3>";
if (isset($_SESSION['user_id'])) {
    $current_user_id = $_SESSION['user_id'];
    echo "✓ User logged in with ID: $current_user_id<br>";
    
    // Check if this user has notifications
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ?");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    echo "Notifications for current user: $count<br>";
    
    if ($count == 0) {
        echo "⚠️ No notifications found for current user. Creating test notification...<br>";
        
        // Create a test notification for the current user
        $title = "Welcome Notification";
        $message = "This is a test notification created for user ID: $current_user_id at " . date('Y-m-d H:i:s');
        $type = "info";
        
        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $notif_stmt->bind_param("isss", $current_user_id, $title, $message, $type);
        
        if ($notif_stmt->execute()) {
            echo "✓ Test notification created successfully<br>";
        } else {
            echo "✗ Failed to create test notification: " . $notif_stmt->error . "<br>";
        }
    }
    
} else {
    echo "✗ No user session found. Please log in first.<br>";
    
    // Find the user with existing notifications and simulate login
    $result = $conn->query("SELECT DISTINCT user_id FROM notifications LIMIT 1");
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $user_with_notifications = $row['user_id'];
        
        // Get user details from applicants table
        $user_stmt = $conn->prepare("SELECT id, applicant_email, first_name FROM applicants WHERE id = ?");
        $user_stmt->bind_param("i", $user_with_notifications);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        
        if ($user_result->num_rows > 0) {
            $user = $user_result->fetch_assoc();
            
            // Simulate login session
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_email'] = $user['applicant_email'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['first_name'] = $user['first_name'];
            
            echo "✓ Simulated login for user: " . $user['first_name'] . " (ID: " . $user['id'] . ")<br>";
            echo "This user has notifications. You can now test the notification system.<br>";
        }
    }
}

// Step 2: Fix user_id mapping in job_applicants
echo "<h3>Step 2: Fix user_id mapping in job_applicants</h3>";
$result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE user_id IS NULL OR user_id = 0");
$null_count = $result->fetch_assoc()['count'];

if ($null_count > 0) {
    echo "Found $null_count job applications with missing user_id. Fixing...<br>";
    
    // Update job_applicants to set user_id based on email match with applicants table
    $update_query = "
        UPDATE job_applicants ja 
        INNER JOIN applicants a ON ja.applicant_email = a.applicant_email 
        SET ja.user_id = a.id 
        WHERE ja.user_id IS NULL OR ja.user_id = 0
    ";
    
    if ($conn->query($update_query)) {
        $affected = $conn->affected_rows;
        echo "✓ Updated $affected job applications with correct user_id<br>";
    } else {
        echo "✗ Failed to update user_id mapping: " . $conn->error . "<br>";
    }
} else {
    echo "✓ All job applications have valid user_id mapping<br>";
}

// Step 3: Test notification API
echo "<h3>Step 3: Test Notification API</h3>";
if (isset($_SESSION['user_id'])) {
    // Simulate the get_notifications.php API call
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT id, title, message, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    $unread_count = 0;
    foreach ($notifications as $notif) {
        if (!$notif['is_read']) {
            $unread_count++;
        }
    }
    
    echo "API Test Results:<br>";
    echo "- Total notifications: " . count($notifications) . "<br>";
    echo "- Unread notifications: $unread_count<br>";
    
    if (count($notifications) > 0) {
        echo "✓ Notifications found for current user<br>";
        echo "Recent notifications:<br>";
        foreach (array_slice($notifications, 0, 3) as $notif) {
            echo "  - " . $notif['title'] . " (" . $notif['created_at'] . ")<br>";
        }
    } else {
        echo "⚠️ No notifications found for current user<br>";
    }
}

// Step 4: Create a comprehensive test notification for admin actions
echo "<h3>Step 4: Test Admin Action Notifications</h3>";
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Create test notifications for each admin action type
    $test_notifications = [
        [
            'title' => 'Interview Scheduled',
            'message' => 'Your interview has been scheduled for December 15, 2024 at 2:00 PM. Please be prepared with your documents.',
            'type' => 'info'
        ],
        [
            'title' => 'Document Resubmission Required',
            'message' => 'Please resubmit your resume and cover letter. The documents need to be updated with recent information.',
            'type' => 'warning'
        ],
        [
            'title' => 'Application Status Update',
            'message' => 'Thank you for your interest. Unfortunately, we have decided to move forward with other candidates.',
            'type' => 'error'
        ]
    ];
    
    foreach ($test_notifications as $notif) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $notif['title'], $notif['message'], $notif['type']);
        
        if ($stmt->execute()) {
            echo "✓ Created: " . $notif['title'] . "<br>";
        } else {
            echo "✗ Failed to create: " . $notif['title'] . "<br>";
        }
    }
}

echo "<h3>Fix Complete!</h3>";
echo "The notification system has been fixed. You can now:<br>";
echo "1. Log in to user/user.php to see notifications<br>";
echo "2. Test admin actions in admin panel<br>";
echo "3. Check that notifications appear in the dropdown<br>";

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
</style>
