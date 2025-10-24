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

// Check current session
echo "<h2>Current Session Status</h2>";
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    echo "Logged in as User ID: $user_id<br>";
    echo "Name: " . ($_SESSION['first_name'] ?? 'Unknown') . "<br>";
} else {
    echo "No active session - not logged in<br>";
    
    // Auto-login for testing
    $result = $conn->query("SELECT id, applicant_email, first_name FROM applicants WHERE id = 1");
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_email'] = $user['applicant_email'];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['first_name'] = $user['first_name'];
        $user_id = $user['id'];
        echo "<br><strong>Auto-logged in as: " . $user['first_name'] . " (ID: $user_id)</strong><br>";
    }
}

if (isset($user_id)) {
    // Check notifications
    echo "<h2>Notifications for User ID: $user_id</h2>";
    $stmt = $conn->prepare("SELECT id, title, message, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "Found " . $result->num_rows . " notifications:<br><br>";
        while ($row = $result->fetch_assoc()) {
            $badge = $row['is_read'] ? '' : ' <span style="background:red;color:white;padding:2px 6px;border-radius:10px;font-size:10px;">NEW</span>';
            echo "<div style='border:1px solid #ddd;padding:10px;margin:5px 0;border-radius:5px;'>";
            echo "<strong>" . htmlspecialchars($row['title']) . "</strong>$badge<br>";
            echo "<small style='color:#666;'>" . $row['created_at'] . "</small><br>";
            echo htmlspecialchars($row['message']);
            echo "</div>";
        }
    } else {
        echo "No notifications found.<br>";
        
        // Create test notifications
        echo "<h3>Creating test notifications...</h3>";
        $test_notifications = [
            ['title' => 'Interview Scheduled', 'message' => 'Your interview has been scheduled for tomorrow at 2 PM.', 'type' => 'info'],
            ['title' => 'Document Required', 'message' => 'Please submit your updated resume.', 'type' => 'warning'],
            ['title' => 'Application Update', 'message' => 'Your application status has been updated.', 'type' => 'info']
        ];
        
        foreach ($test_notifications as $notif) {
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $user_id, $notif['title'], $notif['message'], $notif['type']);
            if ($stmt->execute()) {
                echo "✓ Created: " . $notif['title'] . "<br>";
            }
        }
        echo "<br><a href='?' style='color:blue;'>Refresh to see notifications</a><br>";
    }
    
    // Test the API
    echo "<h2>API Test</h2>";
    echo "<button onclick='testAPI()' style='padding:10px 20px;background:#007cba;color:white;border:none;border-radius:5px;cursor:pointer;'>Test get_notifications.php</button>";
    echo "<div id='apiResult' style='margin-top:10px;padding:10px;background:#f5f5f5;border-radius:5px;'></div>";
    
    echo "<script>
    function testAPI() {
        document.getElementById('apiResult').innerHTML = 'Testing...';
        fetch('user/get_notifications.php')
            .then(response => response.json())
            .then(data => {
                document.getElementById('apiResult').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
            })
            .catch(error => {
                document.getElementById('apiResult').innerHTML = 'Error: ' + error.message;
            });
    }
    </script>";
}

$conn->close();
?>
