<?php
session_start();

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Notification System Test</h2>";

// Step 1: Check database tables and structure
echo "<h3>Step 1: Database Structure Check</h3>";

$tables_to_check = ['notifications', 'job_applicants', 'applicants'];
foreach ($tables_to_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
        $count = $count_result->fetch_assoc()['count'];
        echo "✓ Table '$table' exists with $count records<br>";
    } else {
        echo "✗ Table '$table' NOT FOUND<br>";
    }
}

// Step 2: Check for test data
echo "<h3>Step 2: Test Data Analysis</h3>";

echo "<h4>Sample Job Applicants:</h4>";
$result = $conn->query("SELECT id, applicant_id, user_id, applicant_email, job_id, status FROM job_applicants LIMIT 3");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | User_ID: " . ($row['user_id'] ?? 'NULL') . " | Email: " . ($row['applicant_email'] ?? 'NULL') . " | Status: " . $row['status'] . "<br>";
    }
} else {
    echo "No job applicants found<br>";
}

echo "<h4>Sample Applicants:</h4>";
$result = $conn->query("SELECT id, applicant_email, first_name, last_name FROM applicants LIMIT 3");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Email: " . $row['applicant_email'] . " | Name: " . $row['first_name'] . " " . $row['last_name'] . "<br>";
    }
} else {
    echo "No applicants found<br>";
}

// Step 3: Test notification creation
echo "<h3>Step 3: Test Notification Creation</h3>";

// Find a test applicant
$test_applicant = $conn->query("SELECT ja.id as job_app_id, ja.user_id, ja.applicant_email, a.id as applicant_id 
                                FROM job_applicants ja 
                                LEFT JOIN applicants a ON ja.applicant_email = a.applicant_email 
                                LIMIT 1");

if ($test_applicant && $test_applicant->num_rows > 0) {
    $applicant = $test_applicant->fetch_assoc();
    echo "Test applicant found: Job App ID: " . $applicant['job_app_id'] . ", User ID: " . ($applicant['user_id'] ?? 'NULL') . ", Email: " . $applicant['applicant_email'] . "<br>";
    
    // Determine user_id using the same logic as the admin script
    $user_id = $applicant['user_id'];
    if (!$user_id && $applicant['applicant_email']) {
        $user_id = $applicant['applicant_id']; // Use applicant_id as user_id
        echo "Using applicant_id as user_id: $user_id<br>";
    }
    
    if ($user_id) {
        // Create a test notification
        $title = "Test Notification";
        $message = "This is a test notification created at " . date('Y-m-d H:i:s');
        $type = "info";
        
        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $notif_stmt->bind_param("isss", $user_id, $title, $message, $type);
        
        if ($notif_stmt->execute()) {
            echo "✓ Test notification created successfully for user_id: $user_id<br>";
            $notification_id = $conn->insert_id;
            echo "Notification ID: $notification_id<br>";
        } else {
            echo "✗ Failed to create test notification: " . $notif_stmt->error . "<br>";
        }
    } else {
        echo "✗ No valid user_id found for testing<br>";
    }
} else {
    echo "✗ No test applicant found<br>";
}

// Step 4: Check all notifications
echo "<h3>Step 4: All Notifications in Database</h3>";
$all_notifications = $conn->query("SELECT id, user_id, title, message, type, is_read, created_at FROM notifications ORDER BY created_at DESC");
if ($all_notifications && $all_notifications->num_rows > 0) {
    while ($notif = $all_notifications->fetch_assoc()) {
        echo "ID: " . $notif['id'] . " | User: " . $notif['user_id'] . " | Title: " . $notif['title'] . " | Read: " . ($notif['is_read'] ? 'Yes' : 'No') . " | Created: " . $notif['created_at'] . "<br>";
    }
} else {
    echo "No notifications found in database<br>";
}

// Step 5: Test session and user login simulation
echo "<h3>Step 5: Session Test</h3>";
if (isset($_SESSION['user_id'])) {
    echo "Current session user_id: " . $_SESSION['user_id'] . "<br>";
    echo "Current session first_name: " . ($_SESSION['first_name'] ?? 'Not set') . "<br>";
    
    // Get notifications for current user
    $user_notifications = $conn->query("SELECT * FROM notifications WHERE user_id = " . $_SESSION['user_id'] . " ORDER BY created_at DESC");
    if ($user_notifications && $user_notifications->num_rows > 0) {
        echo "Notifications for current user:<br>";
        while ($notif = $user_notifications->fetch_assoc()) {
            echo "- " . $notif['title'] . " (" . $notif['created_at'] . ")<br>";
        }
    } else {
        echo "No notifications found for current user<br>";
    }
} else {
    echo "No user logged in. To test with a session, you need to log in first.<br>";
    
    // Try to simulate a session with the first available user
    $first_user = $conn->query("SELECT id, applicant_email, first_name FROM applicants LIMIT 1");
    if ($first_user && $first_user->num_rows > 0) {
        $user = $first_user->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['first_name'] = $user['first_name'];
        echo "Simulated session created for user_id: " . $user['id'] . " (" . $user['first_name'] . ")<br>";
        
        // Now check notifications for this simulated user
        $user_notifications = $conn->query("SELECT * FROM notifications WHERE user_id = " . $user['id'] . " ORDER BY created_at DESC");
        if ($user_notifications && $user_notifications->num_rows > 0) {
            echo "Notifications for simulated user:<br>";
            while ($notif = $user_notifications->fetch_assoc()) {
                echo "- " . $notif['title'] . " (" . $notif['created_at'] . ")<br>";
            }
        } else {
            echo "No notifications found for simulated user<br>";
        }
    }
}

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3, h4 { color: #333; }
</style>
