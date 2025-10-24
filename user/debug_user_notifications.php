<?php
session_start();
header('Content-Type: application/json');

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Debug User Notifications</h2>";

// Check session
echo "<h3>Session Info:</h3>";
echo "User ID from session: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
echo "First Name from session: " . ($_SESSION['first_name'] ?? 'NOT SET') . "<br>";

$user_id = $_SESSION['user_id'] ?? null;

if ($user_id) {
    // Check notifications for this user
    echo "<h3>Notifications for User ID: $user_id</h3>";
    $result = $conn->query("SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC");
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "ID: " . $row['id'] . ", Title: " . $row['title'] . ", Message: " . $row['message'] . ", Created: " . $row['created_at'] . ", Read: " . ($row['is_read'] ? 'Yes' : 'No') . "<br>";
        }
    } else {
        echo "No notifications found for this user.<br>";
    }
    
    // Check all notifications to see if any exist
    echo "<h3>All Notifications in Database:</h3>";
    $all_result = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10");
    
    if ($all_result->num_rows > 0) {
        while ($row = $all_result->fetch_assoc()) {
            echo "ID: " . $row['id'] . ", User ID: " . $row['user_id'] . ", Title: " . $row['title'] . ", Created: " . $row['created_at'] . "<br>";
        }
    } else {
        echo "No notifications found in database at all.<br>";
    }
    
} else {
    echo "User not logged in - cannot check notifications.<br>";
}

$conn->close();
?>
