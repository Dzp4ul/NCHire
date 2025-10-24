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

// Get user_id from session
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo "Error: User not logged in. Please log in first.<br>";
    echo "<a href='../index.php'>Go to Login</a>";
    exit;
}

// Create a test notification
$title = "Test Notification";
$message = "This is a test notification to verify the system is working.";
$type = "info";

$stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $user_id, $title, $message, $type);

if ($stmt->execute()) {
    echo "✅ Test notification created successfully for user ID: $user_id<br>";
    echo "<a href='user.php'>Go back to user dashboard</a><br>";
    echo "<br>Check the notification icon in the header to see if it appears.";
} else {
    echo "❌ Failed to create test notification: " . $stmt->error;
}

$conn->close();
?>
