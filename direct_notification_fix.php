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

// Step 1: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    // Auto-login with user ID 1 (the one with notifications)
    $result = $conn->query("SELECT id, applicant_email, first_name FROM applicants WHERE id = 1");
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_email'] = $user['applicant_email'];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['first_name'] = $user['first_name'];
        echo "Auto-logged in as: " . $user['first_name'] . " (ID: 1)<br>";
    }
}

$user_id = $_SESSION['user_id'];
echo "Current user ID: $user_id<br>";

// Step 2: Check if notifications exist for this user
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$count = $result->fetch_assoc()['count'];

echo "Notifications for user $user_id: $count<br>";

if ($count == 0) {
    // Create test notifications
    echo "Creating test notifications...<br>";
    $notifications = [
        ['title' => 'Interview Scheduled', 'message' => 'Your interview has been scheduled for December 20, 2024 at 2:00 PM.', 'type' => 'info'],
        ['title' => 'Document Resubmission Required', 'message' => 'Please resubmit your resume with updated information.', 'type' => 'warning'],
        ['title' => 'Application Status Update', 'message' => 'Your application has been reviewed and moved to the next stage.', 'type' => 'info']
    ];
    
    foreach ($notifications as $notif) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $notif['title'], $notif['message'], $notif['type']);
        $stmt->execute();
        echo "Created: " . $notif['title'] . "<br>";
    }
}

// Step 3: Test the API response
echo "<br><h3>Testing API Response:</h3>";
ob_start();
include 'user/get_notifications.php';
$api_response = ob_get_clean();

echo "Raw API Response:<br>";
echo "<pre>" . htmlspecialchars($api_response) . "</pre>";

$json_data = json_decode($api_response, true);
if ($json_data && $json_data['success']) {
    echo "<br>✓ API working correctly<br>";
    echo "Notifications returned: " . count($json_data['notifications']) . "<br>";
    echo "Unread count: " . $json_data['unread_count'] . "<br>";
} else {
    echo "<br>✗ API error<br>";
}

echo "<br><h3>Direct Database Query:</h3>";
$result = $conn->query("SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC");
while ($row = $result->fetch_assoc()) {
    $status = $row['is_read'] ? 'READ' : 'UNREAD';
    echo "[$status] {$row['title']} - {$row['created_at']}<br>";
}

$conn->close();
?>

<script>
// Test the JavaScript API call
console.log('Testing JavaScript API call...');
fetch('user/get_notifications.php')
    .then(response => response.json())
    .then(data => {
        console.log('JavaScript API Response:', data);
        if (data.success) {
            console.log('✓ JavaScript API working');
            console.log('Notifications:', data.notifications.length);
            console.log('Unread:', data.unread_count);
        } else {
            console.log('✗ JavaScript API error:', data.error);
        }
    })
    .catch(error => {
        console.log('✗ JavaScript API fetch error:', error);
    });
</script>
