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
        
        // Generate random temporary password (8 characters)
        $temporaryPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%'), 0, 10);
        
        // Hash the temporary password
        $hashed_password = password_hash($temporaryPassword, PASSWORD_DEFAULT);
        
        // Insert new user with password_change_required = 1
        $stmt = $conn->prepare("INSERT INTO admin_users (full_name, email, password, password_change_required, role, department, phone, profile_picture, status) 
                                VALUES (?, ?, ?, 1, ?, ?, ?, ?, 'Active')");
        $phone = isset($input['phone']) ? $input['phone'] : null;
        $stmt->bind_param("sssssss", $input['name'], $input['email'], $hashed_password, 
                         $input['role'], $input['department'], $phone, $profile_picture);
        
        if ($stmt->execute()) {
            $newUserId = $conn->insert_id;
            
            // Send temporary password email
            require_once '../send_temp_password_email.php';
            $emailResult = sendTemporaryPasswordEmail($input['email'], $input['name'], $temporaryPassword, $input['role']);
            
            // Fetch the newly created user
            $fetch = $conn->query("SELECT id, full_name as name, email, role, department, profile_picture, status, 
                                  'Never' as lastLogin, DATE_FORMAT(created_at, '%Y-%m-%d') as createdDate 
                                  FROM admin_users WHERE id = $newUserId");
            $newUser = $fetch->fetch_assoc();
            
            // Log admin activity for creating new user
            $creator_name = $_SESSION['admin_name'] ?? 'Unknown Admin';
            $activity_stmt = $conn->prepare("INSERT INTO admin_activity (activity_type, description, user_name, related_table, related_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $activity_type = "user_created";
            $activity_desc = "$creator_name created new admin user: {$input['name']} ({$input['role']})";
            $related_table = "admin_users";
            $activity_stmt->bind_param("ssssi", $activity_type, $activity_desc, $creator_name, $related_table, $newUserId);
            $activity_stmt->execute();
            
            $message = 'User created successfully. ';
            $message .= $emailResult['success'] ? 'Temporary password sent to email.' : 'However, email notification failed.';
            
            echo json_encode(['success' => true, 'user' => $newUser, 'message' => $message, 'emailSent' => $emailResult['success']]);
        } else {
            // If database insert fails, delete uploaded file
            if ($profile_picture && file_exists($upload_dir . $profile_picture)) {
                unlink($upload_dir . $profile_picture);
            }
            echo json_encode(['success' => false, 'message' => 'Failed to create user: ' . $conn->error]);
        }
        break;
        
    case 'PUT':
        // Update admin user - supports both JSON and FormData with file uploads
        
        $input = [];
        $user_id = 0;
        $uploaded_files = [];
        
        // Check Content-Type to determine how to parse the request
        $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        
        if (strpos($content_type, 'multipart/form-data') !== false) {
            // Parse multipart/form-data manually for PUT requests
            $raw_data = file_get_contents('php://input');
            
            // Extract boundary from Content-Type
            if (preg_match('/boundary=(.*)$/', $content_type, $matches)) {
                $boundary = $matches[1];
                
                // Split the data by boundary
                $parts = array_slice(explode("--$boundary", $raw_data), 1);
                
                foreach ($parts as $part) {
                    if (empty(trim($part)) || strpos($part, '--') === 0) continue;
                    
                    // Split headers from content - handle both \r\n\r\n and \n\n
                    $separator_pos = strpos($part, "\r\n\r\n");
                    if ($separator_pos === false) {
                        $separator_pos = strpos($part, "\n\n");
                        if ($separator_pos === false) continue;
                        $separator = "\n\n";
                        $line_break = "\n";
                    } else {
                        $separator = "\r\n\r\n";
                        $line_break = "\r\n";
                    }
                    
                    $raw_headers = substr($part, 0, $separator_pos);
                    $content = substr($part, $separator_pos + strlen($separator));
                    
                    // Parse headers
                    $header_lines = explode($line_break, $raw_headers);
                    $headers = [];
                    foreach ($header_lines as $header) {
                        if (strpos($header, ':') !== false) {
                            list($name, $value) = explode(':', $header, 2);
                            $headers[strtolower(trim($name))] = trim($value);
                        }
                    }
                    
                    // Get field name
                    if (isset($headers['content-disposition'])) {
                        if (preg_match('/name="([^"]*)"/', $headers['content-disposition'], $name_matches)) {
                            $field_name = $name_matches[1];
                            
                            // Check if it's a file
                            if (preg_match('/filename="([^"]*)"/', $headers['content-disposition'], $file_matches)) {
                                $filename = $file_matches[1];
                                // Remove trailing line breaks
                                $content = rtrim($content, "\r\n");
                                
                                // Store file info
                                $uploaded_files[$field_name] = [
                                    'name' => $filename,
                                    'content' => $content,
                                    'type' => isset($headers['content-type']) ? $headers['content-type'] : 'application/octet-stream'
                                ];
                            } else {
                                // Regular field - remove trailing line breaks
                                $input[$field_name] = rtrim($content, "\r\n");
                            }
                        }
                    }
                }
            }
            
            $user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;
            
        } else {
            // Try JSON format
            $input = json_decode(file_get_contents('php://input'), true);
            $user_id = isset($input['id']) ? intval($input['id']) : 0;
        }
        
        // Debug logging
        error_log("PUT Request - User ID: " . $user_id);
        error_log("PUT Request - Input: " . json_encode($input));
        error_log("PUT Request - Files: " . json_encode(array_keys($uploaded_files)));
        
        if (empty($user_id)) {
            echo json_encode(['success' => false, 'message' => 'User ID is required', 'debug' => ['input' => $input, 'content_type' => $content_type]]);
            break;
        }
        
        // Handle profile picture upload if present
        $profile_picture_updated = false;
        $new_profile_picture = null;
        
        if (isset($uploaded_files['profile_picture'])) {
            $file = $uploaded_files['profile_picture'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            // Validate file type
            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed']);
                break;
            }
            
            // Validate file size
            $file_size = strlen($file['content']);
            if ($file_size > $max_size) {
                echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
                break;
            }
            
            // Create upload directory if it doesn't exist
            $upload_dir = '../../uploads/profile_pictures/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Get old profile picture
            $old_pic_query = $conn->query("SELECT profile_picture FROM admin_users WHERE id = $user_id");
            $old_pic = null;
            if ($old_pic_query && $old_pic_query->num_rows > 0) {
                $old_pic = $old_pic_query->fetch_assoc()['profile_picture'];
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_profile_picture = uniqid('admin_') . '_' . time() . '.' . $extension;
            $upload_path = $upload_dir . $new_profile_picture;
            
            // Write file content to disk (since we already have it in memory from parsing)
            if (file_put_contents($upload_path, $file['content']) !== false) {
                $profile_picture_updated = true;
                
                // Delete old profile picture if exists
                if ($old_pic && file_exists($upload_dir . $old_pic)) {
                    unlink($upload_dir . $old_pic);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload profile picture']);
                break;
            }
        }
        
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
        
        // Add profile picture to updates if uploaded
        if ($profile_picture_updated && $new_profile_picture) {
            $updates[] = "profile_picture = ?";
            $params[] = $new_profile_picture;
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
            
            $message = 'User updated successfully';
            if ($profile_picture_updated) {
                $message .= ' (profile picture updated)';
            }
            
            if ($affected > 0 || $profile_picture_updated) {
                echo json_encode(['success' => true, 'message' => $message, 'affected_rows' => $affected]);
            } else {
                echo json_encode(['success' => true, 'message' => 'No changes made (data was the same)', 'affected_rows' => 0]);
            }
        } else {
            // If database update fails and we uploaded a file, delete it
            if ($profile_picture_updated && $new_profile_picture && file_exists($upload_dir . $new_profile_picture)) {
                unlink($upload_dir . $new_profile_picture);
            }
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