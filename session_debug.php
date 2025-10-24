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

echo "<h2>Session and Notification Debug</h2>";

// Check current session
echo "<h3>Current Session:</h3>";
if (isset($_SESSION['user_id'])) {
    echo "Session user_id: " . $_SESSION['user_id'] . "<br>";
    echo "Session first_name: " . ($_SESSION['first_name'] ?? 'Not set') . "<br>";
    echo "Session email: " . ($_SESSION['email'] ?? 'Not set') . "<br>";
    
    $session_user_id = $_SESSION['user_id'];
    
    // Check notifications for this user
    echo "<h3>Notifications for Session User (ID: $session_user_id):</h3>";
    $stmt = $conn->prepare("SELECT id, title, message, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $session_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "ID: " . $row['id'] . " | Title: " . $row['title'] . " | Read: " . ($row['is_read'] ? 'Yes' : 'No') . " | Created: " . $row['created_at'] . "<br>";
        }
    } else {
        echo "No notifications found for this user.<br>";
    }
    
} else {
    echo "No user session found. User not logged in.<br>";
    
    // Show all users in applicants table
    echo "<h3>Available Users in Applicants Table:</h3>";
    $result = $conn->query("SELECT id, applicant_email, first_name, last_name FROM applicants LIMIT 5");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "ID: " . $row['id'] . " | Email: " . $row['applicant_email'] . " | Name: " . $row['first_name'] . " " . $row['last_name'] . "<br>";
        }
    }
}

// Show all notifications with user mapping
echo "<h3>All Notifications with User Info:</h3>";
$result = $conn->query("
    SELECT n.id, n.user_id, n.title, n.created_at, a.applicant_email, a.first_name 
    FROM notifications n 
    LEFT JOIN applicants a ON n.user_id = a.id 
    ORDER BY n.created_at DESC 
    LIMIT 10
");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Notification ID: " . $row['id'] . " | User ID: " . $row['user_id'] . " | Title: " . $row['title'] . " | User: " . ($row['first_name'] ?? 'Unknown') . " (" . ($row['applicant_email'] ?? 'No email') . ") | Created: " . $row['created_at'] . "<br>";
    }
} else {
    echo "No notifications found.<br>";
}

// Test the get_notifications.php API
echo "<h3>Testing get_notifications.php API:</h3>";
if (isset($_SESSION['user_id'])) {
    $api_url = 'http://localhost/FinalResearch%20-%20Copy/user/get_notifications.php';
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "API Response (HTTP $http_code):<br>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
} else {
    echo "Cannot test API - no user session.<br>";
}

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>
