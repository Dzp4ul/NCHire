<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
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

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Fetch all admin users
        $query = "SELECT id, full_name as name, email, role, department, profile_picture, phone, status, 
                  DATE_FORMAT(last_login, '%Y-%m-%d %h:%i %p') as lastLogin, 
                  DATE_FORMAT(created_at, '%Y-%m-%d') as createdDate 
                  FROM admin_users ORDER BY created_at DESC";
        $result = $conn->query($query);
        
        $users = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                // Format lastLogin display
                $row['lastLogin'] = $row['lastLogin'] ? $row['lastLogin'] : 'Never';
                $users[] = $row;
            }
        }
        
        echo json_encode($users);
        break;
        
    case 'POST':
        // Create new admin user
        // Check if it's a file upload (FormData) or JSON
        $isFileUpload = isset($_FILES['profile_picture']);
        
        if ($isFileUpload) {
            // Handle FormData
            $input = $_POST;
        } else {
            // Handle JSON (for backward compatibility)
            $input = json_decode(file_get_contents('php://input'), true);
        }
        
        // Validate required fields
        if (empty($input['name']) || empty($input['email']) || empty($input['password']) || 
            empty($input['role']) || empty($input['department'])) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
            break;
        }
        
        // Check if email already exists
        $check_email = $conn->prepare("SELECT id FROM admin_users WHERE email = ?");
        $check_email->bind_param("s", $input['email']);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            break;
        }
        
        // Handle profile picture upload
        $profile_picture = null;
        if ($isFileUpload && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            // Validate file type
            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed']);
                break;
            }
            
            // Validate file size
            if ($file['size'] > $max_size) {
                echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
                break;
            }
            
            // Create upload directory if it doesn't exist
            $upload_dir = '../../uploads/profile_pictures/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $profile_picture = uniqid('admin_') . '_' . time() . '.' . $extension;
            $upload_path = $upload_dir . $profile_picture;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                echo json_encode(['success' => false, 'message' => 'Failed to upload profile picture']);
                break;
            }
        }
        
        // Hash password
        $hashed_password = password_hash($input['password'], PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO admin_users (full_name, email, password, role, department, phone, profile_picture, status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')");
        $phone = isset($input['phone']) ? $input['phone'] : null;
        $stmt->bind_param("sssssss", $input['name'], $input['email'], $hashed_password, 
                         $input['role'], $input['department'], $phone, $profile_picture);
        
        if ($stmt->execute()) {
            $newUserId = $conn->insert_id;
            
            // Fetch the newly created user
            $fetch = $conn->query("SELECT id, full_name as name, email, role, department, profile_picture, status, 
                                  'Never' as lastLogin, DATE_FORMAT(created_at, '%Y-%m-%d') as createdDate 
                                  FROM admin_users WHERE id = $newUserId");
            $newUser = $fetch->fetch_assoc();
            
            echo json_encode(['success' => true, 'user' => $newUser, 'message' => 'User created successfully']);
        } else {
            // If database insert fails, delete uploaded file
            if ($profile_picture && file_exists($upload_dir . $profile_picture)) {
                unlink($upload_dir . $profile_picture);
            }
            echo json_encode(['success' => false, 'message' => 'Failed to create user: ' . $conn->error]);
        }
        break;
        
    case 'PUT':
        // Update admin user - expects JSON
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['id'])) {
            echo json_encode(['success' => false, 'message' => 'User ID is required']);
            break;
        }
        
        $user_id = $input['id'];
        
        // Build update query dynamically
        $updates = [];
        $params = [];
        $types = "";
        
        if (isset($input['name']) && !empty($input['name'])) {
            $updates[] = "full_name = ?";
            $params[] = $input['name'];
            $types .= "s";
        }
        if (isset($input['email']) && !empty($input['email'])) {
            $updates[] = "email = ?";
            $params[] = $input['email'];
            $types .= "s";
        }
        if (isset($input['role']) && !empty($input['role'])) {
            $updates[] = "role = ?";
            $params[] = $input['role'];
            $types .= "s";
        }
        if (isset($input['department']) && !empty($input['department'])) {
            $updates[] = "department = ?";
            $params[] = $input['department'];
            $types .= "s";
        }
        if (isset($input['phone'])) {
            $updates[] = "phone = ?";
            $params[] = $input['phone'];
            $types .= "s";
        }
        if (isset($input['status']) && !empty($input['status'])) {
            $updates[] = "status = ?";
            $params[] = $input['status'];
            $types .= "s";
        }
        if (isset($input['password']) && !empty($input['password'])) {
            $updates[] = "password = ?";
            $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
            $types .= "s";
        }
        
        if (empty($updates)) {
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            break;
        }
        
        $params[] = $user_id;
        $types .= "i";
        
        $sql = "UPDATE admin_users SET " . implode(", ", $updates) . " WHERE id = ?";
        
        // Debug logging
        error_log("UPDATE SQL: " . $sql);
        error_log("UPDATE Params: " . json_encode($params));
        error_log("UPDATE Types: " . $types);
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
            break;
        }
        
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $affected = $stmt->affected_rows;
            error_log("Affected rows: " . $affected);
            
            if ($affected > 0) {
                echo json_encode(['success' => true, 'message' => 'User updated successfully', 'affected_rows' => $affected]);
            } else {
                echo json_encode(['success' => true, 'message' => 'No changes made (data was the same)', 'affected_rows' => 0]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user: ' . $stmt->error]);
        }
        break;
        
    case 'DELETE':
        // Delete admin user
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            break;
        }
        
        // Prevent admin from deleting their own account
        if (isset($_SESSION['admin_id']) && $_SESSION['admin_id'] == $id) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
            break;
        }
        
        // Prevent deleting the last admin
        $count_admins = $conn->query("SELECT COUNT(*) as count FROM admin_users WHERE role = 'Admin'");
        $admin_count = $count_admins->fetch_assoc()['count'];
        
        if ($admin_count <= 1) {
            $check_role = $conn->query("SELECT role FROM admin_users WHERE id = $id");
            if ($check_role && $check_role->num_rows > 0) {
                $user_role = $check_role->fetch_assoc()['role'];
                if ($user_role === 'Admin') {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete the last admin user']);
                    break;
                }
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM admin_users WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
        }
        break;
}

$conn->close();
?>