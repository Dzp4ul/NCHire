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

// Get user email from session
$user_email = $_SESSION['user_email'] ?? null;

if (!$user_email) {
    echo json_encode(['success' => false, 'error' => 'User not logged in or email not found in session']);
    exit;
}

try {
    // Debug: Log the user_email being used
    error_log("Getting notifications for user_email: " . $user_email);
    
    // Get notifications for the user using email
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_email = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    // Debug: Log how many notifications were found
    error_log("Found " . count($notifications) . " notifications for user_email: " . $user_email);
    
    // Get unread count
    $count_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_email = ? AND is_read = FALSE");
    $count_stmt->bind_param("s", $user_email);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $unread_count = $count_result->fetch_assoc()['unread_count'];
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count,
        'debug_user_email' => $user_email // Add debug info
    ]);
    
} catch (Exception $e) {
    error_log("Notification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>
