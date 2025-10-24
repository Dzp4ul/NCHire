<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]));
}

// Set response header to JSON
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// Create tables if they don't exist
$create_education_table = "CREATE TABLE IF NOT EXISTS user_education (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    degree VARCHAR(255) NOT NULL,
    field_of_study VARCHAR(255) NOT NULL,
    institution VARCHAR(255) NOT NULL,
    start_year INT NOT NULL,
    end_year INT NOT NULL,
    gpa VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_education_table);

$create_experience_table = "CREATE TABLE IF NOT EXISTS user_experience (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_title VARCHAR(255) NOT NULL,
    company VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    start_date DATE NOT NULL,
    end_date DATE,
    description TEXT,
    is_current BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_experience_table);

$create_skills_table = "CREATE TABLE IF NOT EXISTS user_skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    skill_name VARCHAR(255) NOT NULL,
    skill_category VARCHAR(100),
    skill_level INT NOT NULL CHECK (skill_level BETWEEN 1 AND 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_skills_table);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get user ID from session
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        $response['message'] = 'User not logged in properly. Please log in again.';
        echo json_encode($response);
        exit();
    }
    
    // Debug: Log what POST data we received
    error_log("POST data in save_profile_data.php: " . print_r($_POST, true));
    
    // Handle Profile Picture Upload
    if (isset($_POST['upload_profile_picture']) && isset($_FILES['profile_picture'])) {
        $uploadDir = 'uploads/profile_pictures/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $file = $_FILES['profile_picture'];
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $response['message'] = 'File upload error.';
        } elseif ($file['size'] > $maxSize) {
            $response['message'] = 'File size must be less than 5MB.';
        } elseif (!in_array($file['type'], $allowedTypes)) {
            $response['message'] = 'Please select a valid image file (JPG, PNG, GIF).';
        } else {
            // Generate unique filename
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = $user_id . '_' . time() . '.' . $fileExtension;
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // Update database with new profile picture
                $stmt = $conn->prepare("UPDATE applicants SET profile_picture = ? WHERE id = ?");
                $stmt->bind_param("si", $fileName, $user_id);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Profile picture updated successfully!';
                    $response['profile_picture_url'] = 'uploads/profile_pictures/' . $fileName;
                } else {
                    $response['message'] = 'Error updating profile picture in database.';
                    // Clean up uploaded file on database error
                    unlink($targetPath);
                }
                $stmt->close();
            } else {
                $response['message'] = 'Error uploading file.';
            }
        }
    }
    // Handle Personal Information form submission
    elseif (isset($_POST['savePersonal'])) {
        $applicant_fname = $conn->real_escape_string($_POST['applicant_fname'] ?? '');
        $applicant_lname = $conn->real_escape_string($_POST['applicant_lname'] ?? '');
        $applicant_email = $conn->real_escape_string($_POST['applicant_email'] ?? '');
        $applicant_num = $conn->real_escape_string($_POST['applicant_num'] ?? '');
        $applicant_address = $conn->real_escape_string($_POST['applicant_address'] ?? '');
        
        // Validate Philippine phone number format (09XXXXXXXXX)
        if (!empty($applicant_num) && !preg_match('/^09[0-9]{9}$/', $applicant_num)) {
            $response['success'] = false;
            $response['message'] = 'Invalid phone number format. Please use Philippine mobile format (e.g., 09123456789)';
            echo json_encode($response);
            exit();
        }
        
        // Update applicants table
        $stmt = $conn->prepare("UPDATE applicants SET first_name = ?, last_name = ?, applicant_email = ?, contact_number = ?, address = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $applicant_fname, $applicant_lname, $applicant_email, $applicant_num, $applicant_address, $user_id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Personal information updated successfully!';
        } else {
            $response['message'] = 'Error updating personal information: ' . $stmt->error;
        }
        $stmt->close();
    }
    // Handle Education form submission
    elseif (isset($_POST['saveEducation']) || (isset($_POST['ed_degree']) && isset($_POST['ed_fs']))) {
        if (empty($_POST['ed_degree']) || empty($_POST['ed_fs']) || empty($_POST['ed_ins']) || 
            empty($_POST['ed_sy']) || empty($_POST['ed_ey'])) {
            $response['message'] = 'Please fill in all required fields.';
        } else {
            $ed_degree = $conn->real_escape_string($_POST['ed_degree']);
            $ed_fs = $conn->real_escape_string($_POST['ed_fs']);
            $ed_ins = $conn->real_escape_string($_POST['ed_ins']);
            $ed_sy = (int)$_POST['ed_sy'];
            $ed_ey = (int)$_POST['ed_ey'];
            $ed_gpa = isset($_POST['ed_gpa']) ? $conn->real_escape_string($_POST['ed_gpa']) : '';

            $stmt = $conn->prepare("INSERT INTO user_education (user_id, degree, field_of_study, institution, start_year, end_year, gpa) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssiss", $user_id, $ed_degree, $ed_fs, $ed_ins, $ed_sy, $ed_ey, $ed_gpa);

            if ($stmt->execute()) {
                error_log("Education added successfully for user_id: 1");
                $educationData = [
                    'degree' => $ed_degree,
                    'field_of_study' => $ed_fs,
                    'institution' => $ed_ins,
                    'start_year' => $ed_sy,
                    'end_year' => $ed_ey,
                    'gpa' => $ed_gpa
                ];
                $response['success'] = true;
                $response['message'] = 'Education added successfully!';
                $response['data'] = $educationData;
            } else {
                error_log("Error executing education insert: " . $stmt->error);
                $response['message'] = 'Error saving education data: ' . $stmt->error;
            }
        }
    }
    
    // Handle Experience form submission
    elseif (isset($_POST['saveExperience']) || (isset($_POST['job_title']) && isset($_POST['work_comp']))) {
        if (empty($_POST['job_title']) || empty($_POST['work_comp']) || empty($_POST['start_date'])) {
            $response['message'] = 'Please fill in all required fields.';
        } else {
            $job_title = $conn->real_escape_string($_POST['job_title']);
            $work_comp = $conn->real_escape_string($_POST['work_comp']);
            $work_loc = isset($_POST['work_loc']) ? $conn->real_escape_string($_POST['work_loc']) : '';
            $start_date = $conn->real_escape_string($_POST['start_date']);
            $end_date = isset($_POST['is_current']) ? NULL : (isset($_POST['end_date']) ? $conn->real_escape_string($_POST['end_date']) : NULL);
            $work_descript = isset($_POST['work_descript']) ? $conn->real_escape_string($_POST['work_descript']) : '';
            $is_current = isset($_POST['is_current']) ? 1 : 0;

            // Convert date format from YYYY-MM to YYYY-MM-01 for MySQL DATE type
            $start_date_formatted = $start_date . '-01';
            $end_date_formatted = $end_date ? $end_date . '-01' : NULL;

            $sql_insert = "INSERT INTO user_experience (user_id, job_title, company, location, start_date, end_date, description, is_current) 
                           VALUES ('$user_id', '$job_title', '$work_comp', '$work_loc', '$start_date_formatted', " . 
                           ($end_date_formatted ? "'$end_date_formatted'" : "NULL") . ", '$work_descript', '$is_current')";

            if ($conn->query($sql_insert) === TRUE) {
                $experienceData = [
                    'job_title' => $job_title,
                    'company' => $work_comp,
                    'location' => $work_loc,
                    'start_date' => $start_date_formatted,
                    'end_date' => $end_date_formatted,
                    'description' => $work_descript,
                    'is_current' => $is_current
                ];
                $response['success'] = true;
                $response['message'] = 'Work experience added successfully.';
                $response['data'] = $experienceData;
            } else {
                $response['message'] = 'Error adding work experience: ' . $conn->error;
            }
        }
    }
    
    // Handle Skill form submission
    elseif (isset($_POST['saveSkill']) || (isset($_POST['skill_name']) && isset($_POST['skill_category']))) {
        if (empty($_POST['skill_name']) || empty($_POST['skill_category']) || empty($_POST['skill_level']) || $_POST['skill_level'] == '0') {
            $response['message'] = 'Please fill in all required fields and select a skill level.';
        } else {
            $skill_name = $conn->real_escape_string($_POST['skill_name']);
            $skill_category = $conn->real_escape_string($_POST['skill_category']);
            $skill_level = (int)$_POST['skill_level'];

            if ($skill_level < 1 || $skill_level > 5) {
                $response['message'] = 'Please select a valid skill level (1-5).';
            } else {
                $sql_insert = "INSERT INTO user_skills (user_id, skill_name, skill_category, skill_level) 
                               VALUES ('$user_id', '$skill_name', '$skill_category', '$skill_level')";

                if ($conn->query($sql_insert) === TRUE) {
                    $skillData = [
                        'skill_name' => $skill_name,
                        'skill_category' => $skill_category,
                        'skill_level' => $skill_level
                    ];
                    $response['success'] = true;
                    $response['message'] = 'Skill added successfully.';
                    $response['data'] = $skillData;
                } else {
                    $response['message'] = 'Error adding skill: ' . $conn->error;
                }
            }
        }
    }
    else {
        $response['message'] = 'Invalid form submission.';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
?>
