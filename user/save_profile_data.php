<?php
// Clean any output buffers first
while (ob_get_level()) {
    ob_end_clean();
}

session_start();
header('Content-Type: application/json');

// Log only the action name. Profile fields and credentials must not be written to logs.
error_log("save_profile_data.php called");
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_email'])) {
    error_log("ERROR: User not logged in");
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

require_once __DIR__ . '/../shared/helpers/recruitment.php';

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

function invalidateCandidateRankings(mysqli $conn, int $userId): void
{
    $check = $conn->query("SHOW TABLES LIKE 'candidate_rankings'");
    if (!$check || $check->num_rows === 0) return;
    $stmt = $conn->prepare("UPDATE candidate_rankings SET input_hash = REPEAT('0', 64), ai_status = 'stale', updated_at = NOW() WHERE applicant_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
}

function ensureEducationProfileColumns(mysqli $conn): void
{
    $columns = [
        'education_level' => "ALTER TABLE user_education ADD COLUMN education_level ENUM('high_school','associate','bachelor','master','doctorate','other') NOT NULL DEFAULT 'other' AFTER user_id",
        'education_status' => "ALTER TABLE user_education ADD COLUMN education_status ENUM('completed','ongoing') NOT NULL DEFAULT 'completed' AFTER institution",
        'completed_units' => "ALTER TABLE user_education ADD COLUMN completed_units INT NULL AFTER education_status",
        'year_completed' => "ALTER TABLE user_education ADD COLUMN year_completed INT NULL AFTER completed_units",
        'certificate_of_grades' => "ALTER TABLE user_education ADD COLUMN certificate_of_grades VARCHAR(255) NULL AFTER year_completed",
        'proof_of_enrollment' => "ALTER TABLE user_education ADD COLUMN proof_of_enrollment VARCHAR(255) NULL AFTER certificate_of_grades"
    ];

    foreach ($columns as $column => $sql) {
        if (!nc_column_exists($conn, 'user_education', $column)) {
            $conn->query($sql);
        }
    }
}

function saveEducationDocument(string $fieldName, int $userId): ?string
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Unable to upload ' . str_replace('_', ' ', $fieldName) . '.');
    }

    if ($_FILES[$fieldName]['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Education document uploads must be 5MB or smaller.');
    }

    $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    $originalName = $_FILES[$fieldName]['name'] ?? '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Education documents must be PDF, DOC, DOCX, JPG, or PNG files.');
    }

    $uploadDir = __DIR__ . '/uploads/education_documents/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException('Unable to prepare education document upload folder.');
    }

    $fileName = $fieldName . '_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $uploadDir . $fileName;
    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $targetPath)) {
        throw new RuntimeException('Unable to save uploaded education document.');
    }

    return 'uploads/education_documents/' . $fileName;
}

function saveQualificationDocument(string $fieldName, int $userId): ?string
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Unable to upload qualification proof.');
    if ($_FILES[$fieldName]['size'] > 5 * 1024 * 1024) throw new RuntimeException('Qualification proof must be 5MB or smaller.');

    $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    $extension = strtolower(pathinfo((string)($_FILES[$fieldName]['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) throw new RuntimeException('Qualification proof must be PDF, DOC, DOCX, JPG, or PNG.');

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $_FILES[$fieldName]['tmp_name']) : false;
        if ($finfo) finfo_close($finfo);
        $allowedMimes = [
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip', 'image/jpeg', 'image/png',
        ];
        if ($mime && !in_array($mime, $allowedMimes, true)) throw new RuntimeException('Qualification proof file type is not allowed.');
    }

    $uploadDir = __DIR__ . '/uploads/qualification_documents/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) throw new RuntimeException('Unable to prepare qualification upload folder.');
    $fileName = 'qualification_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $uploadDir . $fileName)) throw new RuntimeException('Unable to save qualification proof.');
    return 'uploads/qualification_documents/' . $fileName;
}

function isValidProfileDate(?string $value): bool
{
    if ($value === null) return true;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

// Handle Education Save
if (isset($_POST['saveEducation'])) {
    error_log("=== EDUCATION SAVE START ===");
    ensureEducationProfileColumns($conn);

    $edit_id = isset($_POST['edit_id']) && !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $ed_degree = trim($_POST['ed_degree'] ?? '');
    $ed_fs = trim($_POST['ed_fs'] ?? '');
    $ed_ins = trim($_POST['ed_ins'] ?? '');
    $ed_sy = (int)($_POST['ed_sy'] ?? 0);
    $ed_ey = (int)($_POST['ed_ey'] ?? 0);
    $ed_gpa = trim($_POST['ed_gpa'] ?? '');
    $education_level = strtolower(trim($_POST['education_level'] ?? $_POST['ed_level'] ?? 'other'));
    $education_status = strtolower(trim($_POST['education_status'] ?? $_POST['ed_status'] ?? 'completed'));
    $completed_units = isset($_POST['completed_units']) && $_POST['completed_units'] !== ''
        ? (int)$_POST['completed_units']
        : (isset($_POST['ed_completed_units']) && $_POST['ed_completed_units'] !== '' ? (int)$_POST['ed_completed_units'] : null);
    $year_completed = isset($_POST['year_completed']) && $_POST['year_completed'] !== ''
        ? (int)$_POST['year_completed']
        : (isset($_POST['ed_year_completed']) && $_POST['ed_year_completed'] !== '' ? (int)$_POST['ed_year_completed'] : null);

    if (!in_array($education_level, ['high_school', 'associate', 'bachelor', 'master', 'doctorate', 'other'], true)) {
        $education_level = 'other';
    }
    if (!in_array($education_status, ['completed', 'ongoing'], true)) {
        $education_status = 'completed';
    }
    if ($completed_units !== null && ($completed_units < 0 || $completed_units > 200)) {
        echo json_encode(['success' => false, 'message' => 'Completed graduate units must be between 0 and 200.']);
        exit();
    }
    if ($education_level === 'other') {
        $degree_l = strtolower($ed_degree);
        if (strpos($degree_l, 'doctor') !== false || strpos($degree_l, 'ph.d') !== false || strpos($degree_l, 'phd') !== false) {
            $education_level = 'doctorate';
        } elseif (strpos($degree_l, 'master') !== false || strpos($degree_l, 'masteral') !== false) {
            $education_level = 'master';
        } elseif (strpos($degree_l, 'bachelor') !== false || strpos($degree_l, 'baccalaureate') !== false) {
            $education_level = 'bachelor';
        } elseif (strpos($degree_l, 'associate') !== false) {
            $education_level = 'associate';
        } elseif (strpos($degree_l, 'high school') !== false || strpos($degree_l, 'secondary') !== false) {
            $education_level = 'high_school';
        }
    }

    if ($education_status === 'ongoing') {
        $year_completed = null;
        $ed_ey = 0;
    } else {
        $completed_units = null;
        $year_completed = $year_completed ?: $ed_ey;
    }

    $existing_documents = ['certificate_of_grades' => null, 'proof_of_enrollment' => null];
    if ($edit_id > 0) {
        $existing_stmt = $conn->prepare("SELECT certificate_of_grades, proof_of_enrollment FROM user_education WHERE id = ? AND user_id = ?");
        if ($existing_stmt) {
            $existing_stmt->bind_param("ii", $edit_id, $user_id);
            $existing_stmt->execute();
            $existing_documents = $existing_stmt->get_result()->fetch_assoc() ?: $existing_documents;
            $existing_stmt->close();
        }
    }

    try {
        $certificate_of_grades = saveEducationDocument('certificate_of_grades', (int)$user_id) ?: ($existing_documents['certificate_of_grades'] ?? null);
        $proof_of_enrollment = saveEducationDocument('proof_of_enrollment', (int)$user_id) ?: ($existing_documents['proof_of_enrollment'] ?? null);
    } catch (RuntimeException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }

    $is_graduate_ongoing = $education_status === 'ongoing' && in_array($education_level, ['master', 'doctorate'], true);
    if ($is_graduate_ongoing && $completed_units === null) {
        echo json_encode(['success' => false, 'message' => 'Completed units are required for ongoing graduate education.']);
        exit();
    }
    if ($is_graduate_ongoing && (!$certificate_of_grades || !$proof_of_enrollment)) {
        echo json_encode(['success' => false, 'message' => 'Certificate of Grades and Proof of Enrollment are required for ongoing graduate education.']);
        exit();
    }

    if ($ed_degree === '' || $ed_fs === '' || $ed_ins === '' || empty($ed_sy) || ($education_status === 'completed' && empty($ed_ey))) {
        error_log("ERROR: Missing required fields");
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit();
    }

    $response_data = [
        'degree' => $ed_degree,
        'field_of_study' => $ed_fs,
        'institution' => $ed_ins,
        'education_level' => $education_level,
        'education_status' => $education_status,
        'completed_units' => $completed_units,
        'year_completed' => $year_completed,
        'certificate_of_grades' => $certificate_of_grades,
        'proof_of_enrollment' => $proof_of_enrollment,
        'start_year' => $ed_sy,
        'end_year' => $ed_ey ?: null,
        'gpa' => $ed_gpa
    ];

    if ($edit_id > 0) {
        $sql = "UPDATE user_education SET degree = ?, field_of_study = ?, institution = ?, education_level = ?, education_status = ?, completed_units = ?, year_completed = ?, certificate_of_grades = ?, proof_of_enrollment = ?, start_year = ?, end_year = ?, gpa = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssiissiisii", $ed_degree, $ed_fs, $ed_ins, $education_level, $education_status, $completed_units, $year_completed, $certificate_of_grades, $proof_of_enrollment, $ed_sy, $ed_ey, $ed_gpa, $edit_id, $user_id);

        if ($stmt->execute()) {
            invalidateCandidateRankings($conn, (int)$user_id);
            $response_data['id'] = $edit_id;
            echo json_encode(['success' => true, 'message' => 'Education updated successfully', 'id' => $edit_id, 'data' => $response_data]);
        } else {
            error_log("Education update error: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error updating education: ' . $stmt->error]);
        }
    } else {
        $sql = "INSERT INTO user_education (user_id, degree, field_of_study, institution, education_level, education_status, completed_units, year_completed, certificate_of_grades, proof_of_enrollment, start_year, end_year, gpa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssiissiis", $user_id, $ed_degree, $ed_fs, $ed_ins, $education_level, $education_status, $completed_units, $year_completed, $certificate_of_grades, $proof_of_enrollment, $ed_sy, $ed_ey, $ed_gpa);

        if ($stmt->execute()) {
            invalidateCandidateRankings($conn, (int)$user_id);
            $new_id = $stmt->insert_id;
            $response_data['id'] = $new_id;
            error_log("SUCCESS: Education inserted with ID: " . $new_id);
            echo json_encode(['success' => true, 'message' => 'Education added successfully', 'id' => $new_id, 'data' => $response_data]);
        } else {
            error_log("ERROR: Education insert failed: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error adding education: ' . $stmt->error]);
        }
    }
    $stmt->close();
    error_log("=== EDUCATION SAVE END ===");
    exit();
}
// Handle Personal Information Save
if (isset($_POST['savePersonal'])) {
    error_log("=== PERSONAL INFO SAVE START ===");
    
    $first_name = $conn->real_escape_string($_POST['applicant_fname'] ?? '');
    $last_name = $conn->real_escape_string($_POST['applicant_lname'] ?? '');
    $email = $conn->real_escape_string($_POST['applicant_email'] ?? '');
    $phone = $conn->real_escape_string($_POST['applicant_num'] ?? '');
    $address = $conn->real_escape_string($_POST['applicant_address'] ?? '');
    
    error_log("Personal information update requested for user_id: $user_id");
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email)) {
        error_log("ERROR: Missing required fields (first name, last name, or email)");
        echo json_encode(['success' => false, 'message' => 'First name, last name, and email are required']);
        exit();
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("ERROR: Invalid email format");
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
        exit();
    }
    
    // Validate phone number format if provided
    if (!empty($phone)) {
        if (!preg_match('/^09[0-9]{9}$/', $phone)) {
            error_log("ERROR: Invalid phone number format");
            echo json_encode(['success' => false, 'message' => 'Invalid phone number format. Please use Philippine mobile format (e.g., 09123456789)']);
            exit();
        }
    }
    
    // Update applicants table with correct column names
    $sql = "UPDATE applicants SET 
            first_name = ?, 
            last_name = ?, 
            applicant_email = ?, 
            contact_number = ?, 
            address = ? 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $first_name, $last_name, $email, $phone, $address, $user_id);
    
    if ($stmt->execute()) {
        error_log("SUCCESS: Personal information updated for user_id: $user_id");
        
        // Update session data
        $_SESSION['first_name'] = $first_name;
        if (isset($_SESSION['user_email']) && $_SESSION['user_email'] !== $email) {
            $_SESSION['user_email'] = $email;
            error_log("Session email updated for user_id: $user_id");
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Personal information saved successfully'
        ]);
    } else {
        error_log("ERROR: Failed to update personal information: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Error saving personal information: ' . $stmt->error]);
    }
    $stmt->close();
    error_log("=== PERSONAL INFO SAVE END ===");
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
    $experience_type = strtolower(trim($_POST['experience_type'] ?? 'other'));
    if (!in_array($experience_type, ['teaching', 'industry', 'other'], true)) {
        $experience_type = 'other';
    }
    
    if (empty($job_title) || empty($work_comp) || empty($start_date)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit();
    }
    
    // Format dates
    $start_date_formatted = $start_date . '-01';
    $end_date_formatted = $end_date ? $end_date . '-01' : NULL;
    
    if ($edit_id > 0) {
        // UPDATE existing record
        $sql = "UPDATE user_experience SET job_title = ?, company = ?, location = ?, start_date = ?, end_date = ?, description = ?, is_current = ?, experience_type = ?
                WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssisii", $job_title, $work_comp, $work_loc, $start_date_formatted, $end_date_formatted, $work_descript, $is_current, $experience_type, $edit_id, $user_id);
        
        if ($stmt->execute()) {
            invalidateCandidateRankings($conn, (int)$user_id);
            echo json_encode(['success' => true, 'message' => 'Work experience updated successfully']);
        } else {
            error_log("Experience update error: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error updating experience: ' . $stmt->error]);
        }
    } else {
        // INSERT new record
        $sql = "INSERT INTO user_experience (user_id, experience_type, job_title, company, location, start_date, end_date, description, is_current)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssssi", $user_id, $experience_type, $job_title, $work_comp, $work_loc, $start_date_formatted, $end_date_formatted, $work_descript, $is_current);
        
        if ($stmt->execute()) {
            invalidateCandidateRankings($conn, (int)$user_id);
            echo json_encode(['success' => true, 'message' => 'Work experience added successfully', 'id' => $stmt->insert_id]);
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
            invalidateCandidateRankings($conn, (int)$user_id);
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
            invalidateCandidateRankings($conn, (int)$user_id);
            echo json_encode(['success' => true, 'message' => 'Skill added successfully', 'id' => $stmt->insert_id]);
        } else {
            error_log("Skill insert error: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error adding skill: ' . $stmt->error]);
        }
    }
    $stmt->close();
    exit();
}

// Handle structured certifications, licenses, and training.
if (isset($_POST['saveQualification'])) {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'user_qualifications'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Qualification profile migration is required.']);
        exit();
    }

    $editId = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $type = strtolower(trim((string)($_POST['qualification_type'] ?? '')));
    $title = trim((string)($_POST['qualification_title'] ?? ''));
    $issuer = trim((string)($_POST['issuing_organization'] ?? ''));
    $issuedDate = trim((string)($_POST['issued_date'] ?? '')) ?: null;
    $expiryDate = trim((string)($_POST['expiry_date'] ?? '')) ?: null;

    if (!in_array($type, ['certification', 'license', 'training'], true) || $title === '') {
        echo json_encode(['success' => false, 'message' => 'Qualification type and title are required.']);
        exit();
    }
    if (strlen($title) > 255 || strlen($issuer) > 255) {
        echo json_encode(['success' => false, 'message' => 'Qualification title and issuing organization must be 255 characters or fewer.']);
        exit();
    }
    foreach ([$issuedDate, $expiryDate] as $date) {
        if (!isValidProfileDate($date)) {
            echo json_encode(['success' => false, 'message' => 'Qualification dates are invalid.']);
            exit();
        }
    }
    if ($issuedDate && $expiryDate && $expiryDate < $issuedDate) {
        echo json_encode(['success' => false, 'message' => 'Expiry date cannot be before the issued date.']);
        exit();
    }

    $existingProof = null;
    if ($editId > 0) {
        $existingStmt = $conn->prepare('SELECT proof_document FROM user_qualifications WHERE id = ? AND user_id = ?');
        $existingStmt->bind_param('ii', $editId, $user_id);
        $existingStmt->execute();
        $existingProof = $existingStmt->get_result()->fetch_assoc()['proof_document'] ?? null;
        $existingStmt->close();
    }
    try {
        $proof = saveQualificationDocument('qualification_proof', (int)$user_id) ?: $existingProof;
    } catch (RuntimeException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }

    if ($editId > 0) {
        $stmt = $conn->prepare("UPDATE user_qualifications SET qualification_type=?, title=?, issuing_organization=?, issued_date=?, expiry_date=?, proof_document=?, verification_status='unverified', verified_by=NULL, verified_at=NULL WHERE id=? AND user_id=?");
        $stmt->bind_param('ssssssii', $type, $title, $issuer, $issuedDate, $expiryDate, $proof, $editId, $user_id);
    } else {
        $stmt = $conn->prepare('INSERT INTO user_qualifications (user_id, qualification_type, title, issuing_organization, issued_date, expiry_date, proof_document) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('issssss', $user_id, $type, $title, $issuer, $issuedDate, $expiryDate, $proof);
    }
    if ($stmt->execute()) {
        $id = $editId ?: $stmt->insert_id;
        invalidateCandidateRankings($conn, (int)$user_id);
        echo json_encode(['success' => true, 'message' => $editId ? 'Qualification updated successfully' : 'Qualification added successfully', 'id' => $id]);
    } else {
        error_log('Qualification save error: ' . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Unable to save qualification.']);
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
                invalidateCandidateRankings($conn, (int)$user_id);
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
                invalidateCandidateRankings($conn, (int)$user_id);
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
                invalidateCandidateRankings($conn, (int)$user_id);
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

    if (isset($delete_data['delete_qualification'])) {
        $id = (int)($delete_data['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit();
        }
        $stmt = $conn->prepare('DELETE FROM user_qualifications WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $id, $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            invalidateCandidateRankings($conn, (int)$user_id);
            echo json_encode(['success' => true, 'message' => 'Qualification deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Qualification record not found']);
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
    
    // Validate password contains at least one number
    if (!preg_match('/[0-9]/', $new_password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one number']);
        exit();
    }
    
    // Validate password contains at least one symbol
    if (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one symbol (e.g., !@#$%^&*)']);
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
