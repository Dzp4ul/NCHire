<?php
session_start();
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$admin_id = $_SESSION['admin_id'];

switch ($method) {
    case 'GET':
        // Fetch notifications for current admin
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
        
        $query = "SELECT * FROM admin_notifications WHERE (admin_id = ? OR admin_id IS NULL)";
        if ($unread_only) {
            $query .= " AND is_read = 0";
        }
        $query .= " ORDER BY created_at DESC LIMIT ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $admin_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        // Get unread count
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin_notifications WHERE (admin_id = ? OR admin_id IS NULL) AND is_read = 0");
        $count_stmt->bind_param("i", $admin_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $unread_count = $count_result->fetch_assoc()['count'];
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ]);
        break;
        
    case 'POST':
        // Mark notification as read
        $data = json_decode(file_get_contents('php://input'), true);
        $notification_id = $data['notification_id'] ?? null;
        
        if ($notification_id) {
            $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1, read_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $notification_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update notification']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Notification ID required']);
        }
        break;
        
    case 'PUT':
        // Mark all notifications as read
        $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1, read_at = NOW() WHERE (admin_id = ? OR admin_id IS NULL) AND is_read = 0");
        $stmt->bind_param("i", $admin_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update notifications']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        break;
}

$conn->close();
?>
