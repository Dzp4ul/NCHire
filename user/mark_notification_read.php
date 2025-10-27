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
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$user_email = $_SESSION['user_email'] ?? null;

if (!$user_email) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

$action = $_POST['action'] ?? 'mark_one';
$notification_id = $_POST['notification_id'] ?? null;

try {
    if ($action === 'mark_all_read') {
        // Mark all notifications as read for this user
        $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_email = ? AND is_read = FALSE");
        $stmt->bind_param("s", $user_email);
        
        if ($stmt->execute()) {
            $affected = $stmt->affected_rows;
            echo json_encode(['success' => true, 'message' => "Marked $affected notifications as read"]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to mark notifications as read']);
        }
    } else {
        // Mark single notification as read
        if (!$notification_id) {
            echo json_encode(['success' => false, 'error' => 'Missing notification_id']);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_email = ?");
        $stmt->bind_param("is", $notification_id, $user_email);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to mark notification as read']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>
