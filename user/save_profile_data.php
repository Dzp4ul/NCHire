<?php
// Clean any output buffers first
while (ob_get_level()) {
    ob_end_clean();
}

session_start();
header('Content-Type: application/json');

// Log all incoming POST data
error_log("save_profile_data.php called");
error_log("POST data: " . print_r($_POST, true));
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("Session user_email: " . ($_SESSION['user_email'] ?? 'NOT SET'));

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_email'])) {
    error_log("ERROR: User not logged in");
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get user ID from session with fallback
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id && isset($_SESSION['user_email'])) {
    $email_stmt = $conn->prepare("SELECT id FROM applicants WHERE applicant_email = ?");
    $email_stmt->bind_param("s", $_SESSION['user_email']);
    $email_stmt->execute();
    $email_result = $email_stmt->get_result();
    if ($email_result->num_rows > 0) {
        $user_row = $email_result->fetch_assoc();
        $user_id = $user_row['id'];
        $_SESSION['user_id'] = $user_id;
    }
    $email_stmt->close();
}

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID not found']);
    exit();
}

// Handle Education Save
if (isset($_POST['saveEducation'])) {
    error_log("=== EDUCATION SAVE START ===");
    $edit_id = isset($_POST['edit_id']) && !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $ed_degree = $conn->real_escape_string($_POST['ed_degree'] ?? '');
    $ed_fs = $conn->real_escape_string($_POST['ed_fs'] ?? '');
    $ed_ins = $conn->real_escape_string($_POST['ed_ins'] ?? '');
    $ed_sy = (int)($_POST['ed_sy'] ?? 0);
    $ed_ey = (int)($_POST['ed_ey'] ?? 0);
    $ed_gpa = $conn->real_escape_string($_POST['ed_gpa'] ?? '');
    
    error_log("User ID: $user_id, Degree: $ed_degree");
    
    if (empty($ed_degree) || empty($ed_fs) || empty($ed_ins) || empty($ed_sy) || empty($ed_ey)) {
        error_log("ERROR: Missing required fields");
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit();
    }
    
    if ($edit_id > 0) {
        // UPDATE existing record
        $sql = "UPDATE user_education SET degree = ?, field_of_study = ?, institution = ?, start_year = ?, end_year = ?, gpa = ? 
                WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssiiii", $ed_degree, $ed_fs, $ed_ins, $ed_sy, $ed_ey, $ed_gpa, $edit_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Education updated successfully']);
        } else {
            error_log("Education update error: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error updating education: ' . $stmt->error]);
        }
    } else {
        // INSERT new record
        $sql = "INSERT INTO user_education (user_id, degree, field_of_study, institution, start_year, end_year, gpa) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssiis", $user_id, $ed_degree, $ed_fs, $ed_ins, $ed_sy, $ed_ey, $ed_gpa);
        
        if ($stmt->execute()) {
            error_log("SUCCESS: Education inserted with ID: " . $stmt->insert_id);
            echo json_encode(['success' => true, 'message' => 'Education added successfully', 'id' => $stmt->insert_id]);
        } else {
            error_log("ERROR: Education insert failed: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error adding education: ' . $stmt->error]);
        }
    }
    $stmt->close();
    error_log("=== EDUCATION SAVE END ===");
    exit();
}

// Handle Work Experience Save
if (isset($_POST['saveExperience'])) {
    $edit_id = isset($_POST['edit_id']) && !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $job_title = $conn->real_escape_string($_POST['job_title'] ?? '');
    $work_comp = $conn->real_escape_string($_POST['work_comp'] ?? '');
    $work_loc = $conn->real_escape_string($_POST['work_loc'] ?? '');
    $start_date = $conn->real_escape_string($_POST['start_date'] ?? '');
    $end_date = isset($_POST['is_current']) ? NULL : ($_POST['end_date'] ?? '');
    $work_descript = $conn->real_escape_string($_POST['work_descript'] ?? '');
    $is_current = isset($_POST['is_current']) ? 1 : 0;
    
    if (empty($job_title) || empty($work_comp) || empty($start_date)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit();
    }
    
    // Format dates
    $start_date_formatted = $start_date . '-01';
    $end_date_formatted = $end_date ? $end_date . '-01' : NULL;
    
    if ($edit_id > 0) {
        // UPDATE existing record
        $sql = "UPDATE user_experience SET job_title = ?, company = ?, location = ?, start_date = ?, end_date = ?, description = ?, is_current = ? 
                WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssii", $job_title, $work_comp, $work_loc, $start_date_formatted, $end_date_formatted, $work_descript, $is_current, $edit_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Work experience updated successfully']);
        } else {
            error_log("Experience update error: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error updating experience: ' . $stmt->error]);
        }
    } else {
        // INSERT new record
        $sql = "INSERT INTO user_experience (user_id, job_title, company, location, start_date, end_date, description, is_current) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssssi", $user_id, $job_title, $work_comp, $work_loc, $start_date_formatted, $end_date_formatted, $work_descript, $is_current);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Work experience added successfully']);
        } else {
            error_log("Experience insert error: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error adding experience: ' . $stmt->error]);
        }
    }
    $stmt->close();
    exit();
}

// Handle Skill Save
if (isset($_POST['saveSkill'])) {
    $edit_id = isset($_POST['edit_id']) && !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $skill_name = $conn->real_escape_string($_POST['skill_name'] ?? '');
    $skill_category = 'general'; // Default category since field was removed
    $skill_level = (int)($_POST['skill_level'] ?? 0);
    
    if (empty($skill_name) || $skill_level == 0) {
        echo json_encode(['success' => false, 'message' => 'Please fill in skill name and select a skill level']);
        exit();
    }
    
    if ($skill_level < 1 || $skill_level > 5) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid skill level (1-5)']);
        exit();
    }
    
    if ($edit_id > 0) {
        // UPDATE existing record
        $sql = "UPDATE user_skills SET skill_name = ?, skill_category = ?, skill_level = ? 
                WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiii", $skill_name, $skill_category, $skill_level, $edit_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Skill updated successfully']);
        } else {
            error_log("Skill update error: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error updating skill: ' . $stmt->error]);
        }
    } else {
        // INSERT new record
        $sql = "INSERT INTO user_skills (user_id, skill_name, skill_category, skill_level) 
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issi", $user_id, $skill_name, $skill_category, $skill_level);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Skill added successfully']);
        } else {
            error_log("Skill insert error: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error adding skill: ' . $stmt->error]);
        }
    }
    $stmt->close();
    exit();
}

// Handle DELETE requests (parse body manually since PHP doesn't populate $_POST for DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str(file_get_contents('php://input'), $delete_data);
    
    // Handle Delete Education
    if (isset($delete_data['delete_education'])) {
        $id = (int)($delete_data['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit();
        }
        
        $sql = "DELETE FROM user_education WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $user_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Education deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Education record not found']);
            }
        } else {
            error_log("Delete education error: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error deleting education']);
        }
        $stmt->close();
        exit();
    }
    
    // Handle Delete Experience
    if (isset($delete_data['delete_experience'])) {
        $id = (int)($delete_data['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit();
        }
        
        $sql = "DELETE FROM user_experience WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $user_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Work experience deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Work experience not found']);
            }
        } else {
            error_log("Delete experience error: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error deleting work experience']);
        }
        $stmt->close();
        exit();
    }
    
    // Handle Delete Skill
    if (isset($delete_data['delete_skill'])) {
        $id = (int)($delete_data['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit();
        }
        
        $sql = "DELETE FROM user_skills WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $user_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Skill deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Skill not found']);
            }
        } else {
            error_log("Delete skill error: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error deleting skill']);
        }
        $stmt->close();
        exit();
    }
}

// Handle Profile Picture Upload
if (isset($_FILES['profile_picture'])) {
    $target_dir = "uploads/profile_pictures/";
    
    // Create directory if it doesn't exist
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION));
    $allowed_extensions = array("jpg", "jpeg", "png", "gif");
    
    if (!in_array($file_extension, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, PNG & GIF files are allowed']);
        exit();
    }
    
    if ($_FILES["profile_picture"]["size"] > 5000000) {
        echo json_encode(['success' => false, 'message' => 'File is too large. Max size is 5MB']);
        exit();
    }
    
    $new_filename = "profile_" . $user_id . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
        // Update database
        $sql = "UPDATE applicants SET profile_picture = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_filename, $user_id);
        
        if ($stmt->execute()) {
            // Return both filename and full URL path
            $profile_picture_url = 'uploads/profile_pictures/' . $new_filename;
            echo json_encode([
                'success' => true, 
                'message' => 'Profile picture updated', 
                'filename' => $new_filename,
                'profile_picture_url' => $profile_picture_url
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating database']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Error uploading file']);
    }
    exit();
}

// Handle Password Update
if (isset($_POST['updatePassword'])) {
    error_log("=== PASSWORD UPDATE REQUEST START ===");
    error_log("User ID: " . $user_id);
    
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    error_log("Current password length: " . strlen($current_password));
    error_log("New password length: " . strlen($new_password));
    
    if (empty($current_password) || empty($new_password)) {
        error_log("ERROR: Empty password fields");
        echo json_encode(['success' => false, 'message' => 'Please provide both current and new passwords']);
        exit();
    }
    
    // Validate new password length
    if (strlen($new_password) < 8) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters long']);
        exit();
    }
    
    // Fetch current password from database
    error_log("Fetching current password from database...");
    $sql = "SELECT applicant_password FROM applicants WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("ERROR: User not found in database");
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    $row = $result->fetch_assoc();
    $stored_password = $row['applicant_password'];
    $stmt->close();
    error_log("Stored password retrieved (length: " . strlen($stored_password) . ")");
    
    // Verify current password
    if ($current_password !== $stored_password) {
        error_log("ERROR: Current password does not match stored password");
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit();
    }
    error_log("Current password verified successfully");
    
    // Check if new password is different from current
    if ($current_password === $new_password) {
        error_log("ERROR: New password is same as current password");
        echo json_encode(['success' => false, 'message' => 'New password must be different from current password']);
        exit();
    }
    error_log("New password is different from current");
    
    // Update password in database
    error_log("Updating password in database...");
    $update_sql = "UPDATE applicants SET applicant_password = ?, password_change_required = 0 WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_password, $user_id);
    
    if ($update_stmt->execute()) {
        error_log("SUCCESS: Password updated successfully");
        $update_stmt->close();
        echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
    } else {
        error_log("ERROR: Password update failed: " . $update_stmt->error);
        $update_stmt->close();
        echo json_encode(['success' => false, 'message' => 'Error updating password: ' . $update_stmt->error]);
    }
    
    error_log("=== PASSWORD UPDATE REQUEST END ===");
    exit();
}

echo json_encode(['success' => false, 'message' => 'No valid action specified']);
$conn->close();
?>
