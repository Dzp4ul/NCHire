<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get user ID
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['profile_picture'];
$allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
$max_size = 5 * 1024 * 1024; // 5MB

// Validate file type
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed']);
    exit;
}

// Validate file size
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = '../../uploads/profile_pictures/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Get old profile picture to delete later
$old_pic_query = $conn->query("SELECT profile_picture FROM admin_users WHERE id = $user_id");
if ($old_pic_query && $old_pic_query->num_rows > 0) {
    $old_pic = $old_pic_query->fetch_assoc()['profile_picture'];
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$new_filename = uniqid('admin_') . '_' . time() . '.' . $extension;
$upload_path = $upload_dir . $new_filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
    exit;
}

// Update database
$stmt = $conn->prepare("UPDATE admin_users SET profile_picture = ? WHERE id = ?");
$stmt->bind_param("si", $new_filename, $user_id);

if ($stmt->execute()) {
    // Delete old profile picture if exists
    if ($old_pic && file_exists($upload_dir . $old_pic)) {
        unlink($upload_dir . $old_pic);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Profile picture updated successfully',
        'filename' => $new_filename
    ]);
} else {
    // If database update fails, delete the uploaded file
    if (file_exists($upload_path)) {
        unlink($upload_path);
    }
    echo json_encode(['success' => false, 'message' => 'Failed to update database: ' . $conn->error]);
}

$conn->close();
?>
