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

echo "=== NOTIFICATION DEBUG ===\n\n";

// Step 1: Check session
echo "1. SESSION CHECK:\n";
if (isset($_SESSION['user_id'])) {
    echo "   User ID: " . $_SESSION['user_id'] . "\n";
    echo "   First Name: " . ($_SESSION['first_name'] ?? 'Not set') . "\n";
    $user_id = $_SESSION['user_id'];
} else {
    echo "   NO SESSION - User not logged in\n";
    
    // Auto-login with first available user
    $first_user = $conn->query("SELECT id, applicant_email, first_name FROM applicants LIMIT 1");
    if ($first_user->num_rows > 0) {
        $user = $first_user->fetch_assoc();
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_email'] = $user['applicant_email'];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['first_name'] = $user['first_name'];
        $user_id = $user['id'];
        echo "   AUTO-LOGIN: " . $user['first_name'] . " (ID: " . $user['id'] . ")\n";
    } else {
        echo "   ERROR: No users found in database\n";
        exit;
    }
}

// Step 2: Check notifications for this user
echo "\n2. NOTIFICATION CHECK:\n";
$stmt = $conn->prepare("SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "   Found " . $result->num_rows . " notifications:\n";
    while ($row = $result->fetch_assoc()) {
        $status = $row['is_read'] ? 'READ' : 'UNREAD';
        echo "   - [{$status}] {$row['title']} ({$row['created_at']})\n";
    }
} else {
    echo "   NO NOTIFICATIONS for user_id: $user_id\n";
    
    // Create a test notification
    echo "   Creating test notification...\n";
    $title = "Test Notification";
    $message = "This is a test notification created at " . date('Y-m-d H:i:s');
    $type = "info";
    
    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $notif_stmt->bind_param("isss", $user_id, $title, $message, $type);
    
    if ($notif_stmt->execute()) {
        echo "   ✓ Test notification created (ID: " . $conn->insert_id . ")\n";
    } else {
        echo "   ✗ Failed to create notification: " . $notif_stmt->error . "\n";
    }
}

// Step 3: Test the API directly
echo "\n3. API TEST:\n";
ob_start();
include 'user/get_notifications.php';
$api_output = ob_get_clean();

echo "   API Response: " . $api_output . "\n";

$json_data = json_decode($api_output, true);
if ($json_data) {
    echo "   Success: " . ($json_data['success'] ? 'YES' : 'NO') . "\n";
    if (isset($json_data['notifications'])) {
        echo "   Notification Count: " . count($json_data['notifications']) . "\n";
        echo "   Unread Count: " . ($json_data['unread_count'] ?? 0) . "\n";
    }
    if (isset($json_data['error'])) {
        echo "   Error: " . $json_data['error'] . "\n";
    }
} else {
    echo "   INVALID JSON RESPONSE\n";
}

echo "\n=== END DEBUG ===\n";

$conn->close();
?>
