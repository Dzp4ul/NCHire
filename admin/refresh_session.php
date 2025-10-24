<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get current admin ID from session
$admin_id = $_SESSION['admin_id'];

// Fetch fresh data from database
$stmt = $conn->prepare("SELECT * FROM admin_users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $admin = $result->fetch_assoc();
    
    // Update session with fresh data
    $_SESSION['admin_name'] = $admin['full_name'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['admin_department'] = $admin['department'];
    $_SESSION['admin_profile_picture'] = $admin['profile_picture'];
    
    echo json_encode([
        'success' => true,
        'message' => 'Session refreshed',
        'data' => [
            'name' => $admin['full_name'],
            'email' => $admin['email'],
            'role' => $admin['role'],
            'department' => $admin['department'],
            'profile_picture' => $admin['profile_picture']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Admin not found']);
}

$stmt->close();
$conn->close();
?>
