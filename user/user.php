<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_email'])) {
    // Save the intended view parameter if coming from email
    if (isset($_GET['view'])) {
        $_SESSION['redirect_after_login'] = 'user.php?view=' . $_GET['view'];
    }
    // Redirect to homepage with login prompt
    header('Location: ../index.php');
    exit();
}

// Handle AJAX request to clear success session
if (isset($_POST['clear_success_session'])) {
    unset($_SESSION['application_success']);
    unset($_SESSION['applied_job_id']);
    unset($_SESSION['new_application_id']);
    unset($_SESSION['show_workflow_step']);
    exit(); // Don't process anything else for this request
}

// Check for success message from redirect
$success_message = '';
$error_message = '';
$applied_job_id = '';
$new_application_id = '';
$show_workflow_step = 0;
if (isset($_SESSION['application_success'])) {
    $success_message = $_SESSION['application_success'];
    $applied_job_id = $_SESSION['applied_job_id'] ?? '';
    $new_application_id = $_SESSION['new_application_id'] ?? '';
    $show_workflow_step = $_SESSION['show_workflow_step'] ?? 0;
    unset($_SESSION['application_success']); // Clear the message
    unset($_SESSION['applied_job_id']); // Clear the job ID
    unset($_SESSION['new_application_id']);
    unset($_SESSION['show_workflow_step']);
}
if (isset($_SESSION['application_error'])) {
    $error_message = $_SESSION['application_error'];
    unset($_SESSION['application_error']);
}

$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if this is an AJAX request early to suppress debug output
$is_ajax_request = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_submit']));

// Get user data for form population (at the top before any operations)
$user_profile_data = [];
$user_work_experience = [];
$user_skills = [];
$user_address = '';

if (!$is_ajax_request) {
    echo "<!-- Debug Session: " . print_r($_SESSION, true) . " -->";
}

if (isset($_SESSION['user_id'])) {
    $profile_user_id = $_SESSION['user_id'];
    if (!$is_ajax_request) echo "<!-- Debug: Looking for user ID: " . $profile_user_id . " -->";
    
    // First try users table
    $profile_stmt = $conn->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
    $profile_stmt->bind_param("i", $profile_user_id);
    $profile_stmt->execute();
    $profile_result = $profile_stmt->get_result();
    
    if (!$is_ajax_request) echo "<!-- Debug: Found " . $profile_result->num_rows . " rows in users table -->";
    
    if ($profile_result->num_rows === 1) {
        $user_profile_data = $profile_result->fetch_assoc();
        if (!$is_ajax_request) echo "<!-- Debug User Data from users table: " . print_r($user_profile_data, true) . " -->";
    } else {
        if (!$is_ajax_request) echo "<!-- Debug: No user found in users table, trying applicants table -->";
        // Try applicants table as fallback
        $profile_stmt2 = $conn->prepare("SELECT first_name, last_name, applicant_email as email, contact_number as phone, profile_picture, address FROM applicants WHERE id = ?");
        $profile_stmt2->bind_param("i", $profile_user_id);
        $profile_stmt2->execute();
        $profile_result2 = $profile_stmt2->get_result();
        
        if (!$is_ajax_request) echo "<!-- Debug: Found " . $profile_result2->num_rows . " rows in applicants table -->";
        
        if ($profile_result2->num_rows === 1) {
            $user_profile_data = $profile_result2->fetch_assoc();
            $user_address = $user_profile_data['address'] ?? '';
            if (!$is_ajax_request) echo "<!-- Debug User Data from applicants table: " . print_r($user_profile_data, true) . " -->";
        }
        $profile_stmt2->close();
    }
    $profile_stmt->close();
    
    // Fetch ALL work experiences (remove LIMIT 1)
    $exp_stmt = $conn->prepare("SELECT job_title, company, location, start_date, end_date, is_current, description FROM user_experience WHERE user_id = ? ORDER BY start_date DESC");
    $exp_stmt->bind_param("i", $profile_user_id);
    $exp_stmt->execute();
    $exp_result = $exp_stmt->get_result();
    $user_work_experience = [];
    while ($exp = $exp_result->fetch_assoc()) {
        $user_work_experience[] = $exp;
    }
    if (!$is_ajax_request && !empty($user_work_experience)) {
        echo "<!-- Debug Work Experience: " . print_r($user_work_experience, true) . " -->";
    }
    $exp_stmt->close();
    
    // Fetch ALL education entries
    $edu_stmt = $conn->prepare("SELECT institution, degree, field_of_study, start_year, end_year, gpa FROM user_education WHERE user_id = ? ORDER BY start_year DESC");
    $edu_stmt->bind_param("i", $profile_user_id);
    $edu_stmt->execute();
    $edu_result = $edu_stmt->get_result();
    $user_education = [];
    while ($edu = $edu_result->fetch_assoc()) {
        $user_education[] = $edu;
    }
    if (!$is_ajax_request && !empty($user_education)) {
        echo "<!-- Debug Education: " . print_r($user_education, true) . " -->";
    }
    $edu_stmt->close();
    
    // Fetch skills
    $skills_stmt = $conn->prepare("SELECT skill_name FROM user_skills WHERE user_id = ? ORDER BY skill_name");
    $skills_stmt->bind_param("i", $profile_user_id);
    $skills_stmt->execute();
    $skills_result = $skills_stmt->get_result();
    $skills_array = [];
    while ($skill = $skills_result->fetch_assoc()) {
        $skills_array[] = $skill['skill_name'];
    }
    if (!empty($skills_array)) {
        $user_skills = implode(", ", $skills_array);
        if (!$is_ajax_request) echo "<!-- Debug Skills: " . $user_skills . " -->";
    }
    $skills_stmt->close();
} else {
    if (!$is_ajax_request) echo "<!-- Debug: No user_id in session -->";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    
    // Start output buffering for AJAX requests to ensure clean JSON
    $is_ajax_submit = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    $is_ajax_submit = $is_ajax_submit || (isset($_POST['ajax_submit']) && $_POST['ajax_submit'] == '1');
    
    if ($is_ajax_submit) {
        // Clear any existing output and start fresh buffer
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
        
        error_log("AJAX SUBMIT DETECTED - Output buffering started");
    }

    $uploadDir = __DIR__ . "/uploads/";
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            $_SESSION['application_error'] = "Failed to create upload directory. Please contact administrator.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
    
    // Get user_id from session (from applicants table)
    $user_id = $_SESSION['user_id'] ?? null;
    
    // Validate that user is logged in
    if (!$user_id) {
        $_SESSION['application_error'] = 'User not logged in properly. Please log in again.';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    // Get user data from users table for form population
    $user_stmt = $conn->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    
    if ($user_result->num_rows === 1) {
        $user_data = $user_result->fetch_assoc();
        $full_name = trim($user_data['first_name'] . ' ' . $user_data['last_name']);
        $applicant_email = $user_data['email'];
        $contact_num = $user_data['phone'] ?? '';
        $first_name = $user_data['first_name'];
    } else {
        // Fallback to form data if user not found in users table
        $full_name = $_POST['full_name'] ?? '';
        $applicant_email = $_POST['email'] ?? '';
        $contact_num = $_POST['cellphone'] ?? '';
        $first_name = $_SESSION['first_name'] ?? "Guest";
    }
    $user_stmt->close();

    function uploadFile($fileKey, $uploadDir, $multiple = false) {
        if ($multiple) {
            $savedFiles = [];
            if (isset($_FILES[$fileKey]) && is_array($_FILES[$fileKey]['name'])) {
                foreach ($_FILES[$fileKey]['name'] as $index => $name) {
                    if (!empty($name) && $_FILES[$fileKey]['error'][$index] === UPLOAD_ERR_OK) {
                        // Validate file size (5MB max)
                        if ($_FILES[$fileKey]['size'][$index] > 5 * 1024 * 1024) {
                            continue; // Skip files larger than 5MB
                        }
                        $fileName = time() . "_" . $index . "_" . basename($name);
                        $targetFile = $uploadDir . $fileName;
                        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'][$index], $targetFile)) {
                            $savedFiles[] = $fileName; // Store only filename, not full path
                        }
                    }
                }
            }
            return !empty($savedFiles) ? implode(",", $savedFiles) : null;
        } else {
            if (isset($_FILES[$fileKey]) && !empty($_FILES[$fileKey]['name']) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                // Validate file size (5MB max)
                if ($_FILES[$fileKey]['size'] > 5 * 1024 * 1024) {
                    return null; // File too large
                }
                $fileName = time() . "_" . basename($_FILES[$fileKey]['name']);
                $targetFile = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetFile)) {
                    return $fileName; // Return only filename, not full path
                }
            }
            return null;
        }
    }

    // Upload files with error tracking
    $upload_errors = [];
    
    // Get draft documents from database
    $draft_docs = null;
    $draft_stmt = $conn->prepare("SELECT application_letter, resume, tor, diploma, professional_license, coe, seminars_trainings, masteral_cert FROM user_draft_documents WHERE user_id = ?");
    $draft_stmt->bind_param("i", $user_id);
    $draft_stmt->execute();
    $draft_result = $draft_stmt->get_result();
    if ($draft_result->num_rows > 0) {
        $draft_docs = $draft_result->fetch_assoc();
    }
    $draft_stmt->close();
    
    // Function to copy draft file to application uploads
    function copyDraftFile($draftFilename, $user_id, $uploadDir) {
        if (empty($draftFilename)) return null;
        
        $draftPath = __DIR__ . "/uploads/drafts/" . $user_id . "/" . $draftFilename;
        if (!file_exists($draftPath)) return null;
        
        // Create new filename for application
        $newFilename = time() . "_" . basename($draftFilename);
        $targetPath = $uploadDir . $newFilename;
        
        if (copy($draftPath, $targetPath)) {
            return $newFilename;
        }
        return null;
    }
    
    // Upload new files OR use draft files automatically
    error_log("=== FILE UPLOAD PROCESS START ===");
    error_log("POST is_resubmission: " . ($_POST['is_resubmission'] ?? 'NOT SET'));
    error_log("POST resubmit_application_id: " . ($_POST['resubmit_application_id'] ?? 'NOT SET'));
    
    $application_letter = uploadFile('applicationLetter', $uploadDir);
    error_log("Application Letter upload result: " . ($application_letter ?? 'NULL'));
    if (!$application_letter) {
        if ($draft_docs && $draft_docs['application_letter']) {
            $application_letter = copyDraftFile($draft_docs['application_letter'], $user_id, $uploadDir);
            error_log("Application Letter from draft: " . ($application_letter ?? 'NULL'));
        }
        if (!$application_letter && isset($_FILES['applicationLetter']) && !empty($_FILES['applicationLetter']['name'])) {
            $upload_errors[] = 'Application Letter upload failed';
            error_log("Application Letter upload FAILED");
        }
    }
    
    $resume = uploadFile('resume_file', $uploadDir);
    error_log("Resume upload result: " . ($resume ?? 'NULL'));
    if (!$resume) {
        if ($draft_docs && $draft_docs['resume']) {
            $resume = copyDraftFile($draft_docs['resume'], $user_id, $uploadDir);
            error_log("Resume from draft: " . ($resume ?? 'NULL'));
        }
        if (!$resume && isset($_FILES['resume_file']) && !empty($_FILES['resume_file']['name'])) {
            $upload_errors[] = 'Resume upload failed';
            error_log("Resume upload FAILED");
        }
    }
    
    $tor = uploadFile('transcript', $uploadDir);
    if (!$tor && $draft_docs && $draft_docs['tor']) {
        $tor = copyDraftFile($draft_docs['tor'], $user_id, $uploadDir);
    }
    
    $diploma = uploadFile('diploma', $uploadDir);
    if (!$diploma && $draft_docs && $draft_docs['diploma']) {
        $diploma = copyDraftFile($draft_docs['diploma'], $user_id, $uploadDir);
    }
    
    $professional_license = uploadFile('license', $uploadDir);
    if (!$professional_license && $draft_docs && $draft_docs['professional_license']) {
        $professional_license = copyDraftFile($draft_docs['professional_license'], $user_id, $uploadDir);
    }
    
    $coe = uploadFile('coe', $uploadDir);
    if (!$coe && $draft_docs && $draft_docs['coe']) {
        $coe = copyDraftFile($draft_docs['coe'], $user_id, $uploadDir);
    }
    
    $seminars_trainings = uploadFile('certificates', $uploadDir, true);
    if (!$seminars_trainings && $draft_docs && $draft_docs['seminars_trainings']) {
        // Handle multiple certificates from draft
        $draftCerts = explode(',', $draft_docs['seminars_trainings']);
        $copiedCerts = [];
        foreach ($draftCerts as $cert) {
            $copied = copyDraftFile(trim($cert), $user_id, $uploadDir);
            if ($copied) $copiedCerts[] = $copied;
        }
        if (!empty($copiedCerts)) {
            $seminars_trainings = implode(',', $copiedCerts);
        }
    }
    
    $masteral_cert = uploadFile('masteral_cert', $uploadDir);
    if (!$masteral_cert && $draft_docs && $draft_docs['masteral_cert']) {
        $masteral_cert = copyDraftFile($draft_docs['masteral_cert'], $user_id, $uploadDir);
    }
    
    // Check if required files were uploaded or available from draft
    if (!$application_letter) {
        $upload_errors[] = 'Application Letter is required';
    }
    if (!$resume) {
        $upload_errors[] = 'Resume is required';
    }
    if (!$tor) {
        $upload_errors[] = 'Transcript of Records is required';
    }
    if (!$diploma) {
        $upload_errors[] = 'Diploma is required';
    }
    if (!$coe) {
        $upload_errors[] = 'Certificate of Employment is required';
    }
    if (!$seminars_trainings) {
        $upload_errors[] = 'Seminar/Training Certificates are required';
    }
    
    if (!empty($upload_errors)) {
        $_SESSION['application_error'] = implode('. ', $upload_errors) . '. Please upload all required documents or save them as draft first.';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    // Get applicant data
    $applicant_name = $_SESSION['first_name'] ?? "Guest";
    $position = $_POST['job_title'] ?? "Unknown"; 
    $job_id = $_POST['job_id'] ?? null;
    $applied_date = date("Y-m-d H:i:s");
    $status = "Pending";

    // Validate job_id
    if (empty($job_id)) {
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        
        if ($is_ajax || (isset($_POST['ajax_submit']) && $_POST['ajax_submit'] == '1')) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Job ID is missing',
                'message' => 'Invalid application data. Please try again.'
            ]);
            exit();
        }
        
        $_SESSION['application_error'] = 'Job ID is missing. Please start the application again.';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // New form values with validation
    $full_name = $_POST['full_name'] ?? $applicant_name;
    $applicant_email = $_POST['email'] ?? '';
    $contact_num = $_POST['cellphone'] ?? '';
    
    // Ensure required fields are not empty
    if (empty($applicant_email)) {
        $applicant_email = 'no-email@provided.com'; // Default email if not provided
    }
    if (empty($contact_num)) {
        $contact_num = 'Not provided'; // Default contact if not provided
    }
    if (empty($full_name)) {
        $full_name = $applicant_name; // Use session name as fallback
    }

    // ✅ Check if this is a RESUBMISSION or new application
    $is_resubmission = isset($_POST['is_resubmission']) && $_POST['is_resubmission'] == '1';
    $resubmit_app_id = isset($_POST['resubmit_application_id']) ? intval($_POST['resubmit_application_id']) : 0;
    
    if ($is_resubmission && $resubmit_app_id > 0) {
        // ✅ RESUBMISSION - Update existing application
        error_log("RESUBMISSION DETECTED: App ID = $resubmit_app_id, User ID = $user_id");
        
        // First, get the existing file data and job_id
        $existing_stmt = $conn->prepare("SELECT job_id, application_letter, resume, tor, diploma, professional_license, coe, seminars_trainings, masteral_cert FROM job_applicants WHERE id = ? AND user_id = ?");
        $existing_stmt->bind_param("ii", $resubmit_app_id, $user_id);
        $existing_stmt->execute();
        $existing_result = $existing_stmt->get_result();
        
        if ($existing_result->num_rows > 0) {
            $existing_data = $existing_result->fetch_assoc();
            
            // Get job_id from existing application
            $job_id = $existing_data['job_id'];
            
            error_log("BEFORE MERGE - New uploads:");
            error_log("  AL uploaded: " . ($application_letter ? $application_letter : "NONE"));
            error_log("  Resume uploaded: " . ($resume ? $resume : "NONE"));
            error_log("  TOR uploaded: " . ($tor ? $tor : "NONE"));
            error_log("BEFORE MERGE - Existing files:");
            error_log("  AL existing: " . ($existing_data['application_letter'] ?? "NONE"));
            error_log("  Resume existing: " . ($existing_data['resume'] ?? "NONE"));
            error_log("  TOR existing: " . ($existing_data['tor'] ?? "NONE"));
            
            // Use new files if uploaded, otherwise keep existing files
            $application_letter = $application_letter ?: $existing_data['application_letter'];
            $resume = $resume ?: $existing_data['resume'];
            $tor = $tor ?: $existing_data['tor'];
            $diploma = $diploma ?: $existing_data['diploma'];
            $professional_license = $professional_license ?: $existing_data['professional_license'];
            $coe = $coe ?: $existing_data['coe'];
            $seminars_trainings = $seminars_trainings ?: $existing_data['seminars_trainings'];
            $masteral_cert = $masteral_cert ?: $existing_data['masteral_cert'];
            
            error_log("AFTER MERGE - Final values to update:");
            error_log("  AL final: " . ($application_letter ?? "NULL"));
            error_log("  Resume final: " . ($resume ?? "NULL"));
            error_log("  TOR final: " . ($tor ?? "NULL"));
            
            // Update the application with new files and change status
            $update_stmt = $conn->prepare("UPDATE job_applicants SET 
                application_letter = ?, 
                resume = ?, 
                tor = ?, 
                diploma = ?, 
                professional_license = ?, 
                coe = ?, 
                seminars_trainings = ?, 
                masteral_cert = ?,
                status = 'Resubmitted',
                resubmission_documents = NULL,
                resubmission_notes = NULL
                WHERE id = ? AND user_id = ?");
            
            $update_stmt->bind_param("ssssssssii", 
                $application_letter, $resume, $tor, $diploma, 
                $professional_license, $coe, $seminars_trainings, $masteral_cert,
                $resubmit_app_id, $user_id);
            
            if ($update_stmt->execute()) {
                $affected_rows = $update_stmt->affected_rows;
                error_log("RESUBMISSION SUCCESS: Updated application $resubmit_app_id (affected rows: $affected_rows)");
                error_log("Updated files - AL: " . ($application_letter ? basename($application_letter) : "NULL") . 
                         ", Resume: " . ($resume ? basename($resume) : "NULL") .
                         ", TOR: " . ($tor ? basename($tor) : "NULL"));
                
                $application_id = $resubmit_app_id;
                $_SESSION['application_success'] = "Documents resubmitted successfully! Your application is now under review.";
                $_SESSION['applied_job_id'] = $job_id;
                $_SESSION['new_application_id'] = $application_id;
                $_SESSION['show_workflow_step'] = 3;
                
                $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
                
                if ($is_ajax || (isset($_POST['ajax_submit']) && $_POST['ajax_submit'] == '1')) {
                    // Clear ALL output buffers to ensure clean JSON
                    while (ob_get_level()) {
                        ob_end_clean();
                    }
                    
                    error_log("Sending resubmission success JSON");
                    
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'application_id' => $application_id,
                        'message' => 'Documents resubmitted successfully!',
                        'is_resubmission' => true
                    ]);
                    exit();
                } else {
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                }
            } else {
                error_log("RESUBMISSION FAILED: Database error - " . $update_stmt->error);
                
                $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
                
                if ($is_ajax || (isset($_POST['ajax_submit']) && $_POST['ajax_submit'] == '1')) {
                    // Clear ALL output buffers to ensure clean JSON
                    while (ob_get_level()) {
                        ob_end_clean();
                    }
                    
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'error' => 'Database error: ' . $update_stmt->error,
                        'message' => 'Failed to update application. Please try again.'
                    ]);
                    exit();
                }
            }
            $update_stmt->close();
        } else {
            error_log("RESUBMISSION FAILED: Application not found - App ID: $resubmit_app_id, User ID: $user_id");
            
            // Application not found - return error
            $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                       strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
            
            if ($is_ajax || (isset($_POST['ajax_submit']) && $_POST['ajax_submit'] == '1')) {
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Application not found',
                    'message' => 'Could not find your application. Please try again.'
                ]);
                exit();
            }
        }
        $existing_stmt->close();
        
        // Exit here for resubmission - don't continue to new application insert
        if ($is_ajax_submit) {
            // If we get here and it's AJAX, something went wrong
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Resubmission processing error',
                'message' => 'An error occurred during resubmission. Please try again.'
            ]);
            exit();
        }
        exit(); // Always exit after resubmission attempt
        
    } else {
        // ✅ NEW APPLICATION - Insert into job_applicants table
        $stmt = $conn->prepare("INSERT INTO job_applicants 
            (applicant_name, position, applied_date, status, full_name, applicant_email, contact_num, user_id, job_id, 
             application_letter, resume, tor, diploma, professional_license, coe, seminars_trainings, masteral_cert) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Files are already filenames (not full paths) from uploadFile function
        // Types: s=string, i=integer
        $stmt->bind_param("sssssssisssssssss", 
            $applicant_name, $position, $applied_date, $status, $full_name, $applicant_email, $contact_num, 
            $user_id, $job_id, $application_letter, $resume, $tor, $diploma, 
            $professional_license, $coe, $seminars_trainings, $masteral_cert);

        if ($stmt->execute()) {
        // ✅ Success - Get the application ID
        $application_id = $conn->insert_id;
        $_SESSION['application_success'] = "Application submitted successfully! We will review your application and contact you soon.";
        $_SESSION['applied_job_id'] = $job_id;
        $_SESSION['new_application_id'] = $application_id; // Store for showing step 3
        $_SESSION['show_workflow_step'] = 3; // Show step 3 after submission
        
        // Check if this is an AJAX request
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        
        if ($is_ajax || (isset($_POST['ajax_submit']) && $_POST['ajax_submit'] == '1')) {
            // Clear ALL output buffers to ensure clean JSON
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Return JSON for AJAX
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'application_id' => $application_id,
                'message' => 'Application submitted successfully!'
            ]);
            exit();
        } else {
            // Regular form submission - redirect
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        } else {
            // ❌ Error - check if AJAX request
            $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                       strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
            
            if ($is_ajax || (isset($_POST['ajax_submit']) && $_POST['ajax_submit'] == '1')) {
                // Clear ALL output buffers to ensure clean JSON
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Return JSON error for AJAX
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Database error: ' . $conn->error,
                    'message' => 'Failed to save application. Please try again.'
                ]);
                exit();
            }
            
            // For non-AJAX, show JavaScript notification
            echo "
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const errorNotification = document.createElement('div');
                errorNotification.className = 'fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded shadow-md';
                errorNotification.innerHTML = `
                    <div class='flex items-center'>
                        <i class='ri-error-warning-line mr-2'></i>
                        <span>Error saving application: " . addslashes($conn->error) . "</span>
                    </div>
                `;
                document.body.appendChild(errorNotification);
                setTimeout(() => {
                    errorNotification.remove();
                }, 5000);
            });
            </script>
            ";
        }

        $stmt->close();
    }
    $conn->close();
}
?>




<!DOCTYPE html>
<html lang="en">
<head><script src="https://static.readdy.ai/static/e.js"></script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NCHire - Job Opportunities</title>
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" rel="stylesheet">
<style>
:where([class^="ri-"])::before { content: "\f3c2"; }

/* Navigation Active States */
.nav-link.active {
    color: #f59e0b; /* secondary color */
    font-weight: 600;
}

.nav-link.active .nav-indicator {
    transform: scale-x-1;
}

.nav-link:hover .nav-indicator {
    transform: scale-x-0.5;
    opacity: 0.5;
}

.nav-link.active:hover .nav-indicator {
    transform: scale-x-1;
    opacity: 1;
}

/* Success notification animation */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}
</style>
<script>
tailwind.config = {
theme: {
extend: {
colors: {
primary: '#1e40af',
secondary: '#f59e0b'
},
borderRadius: {
'none': '0px',
'sm': '4px',
DEFAULT: '8px',
'md': '12px',
'lg': '16px',
'xl': '20px',
'2xl': '24px',
'3xl': '32px',
'full': '9999px',
'button': '8px'
}
}
}
}
</script>
</head>
<body class="bg-gray-50 min-h-screen">
<?php 
// No longer show success message on reload since we're using AJAX
// This code is kept for backwards compatibility but won't be used
?>

<?php if ($error_message): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const errorNotification = document.createElement('div');
    errorNotification.className = 'fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded shadow-md z-50 max-w-md';
    errorNotification.innerHTML = `
        <div class='flex items-start'>
            <i class='ri-error-warning-line mr-2 text-xl'></i>
            <div class='flex-1'>
                <strong class='font-semibold'>Upload Error</strong>
                <p class='text-sm mt-1'><?php echo addslashes($error_message); ?></p>
            </div>
            <button onclick='this.parentElement.parentElement.remove()' class='ml-2 text-red-700 hover:text-red-900'>
                <i class='ri-close-line text-xl'></i>
            </button>
        </div>
    `;
    document.body.appendChild(errorNotification);
    setTimeout(() => {
        if (errorNotification.parentElement) {
            errorNotification.remove();
        }
    }, 10000);
});
</script>
<?php endif; ?>
<header class="bg-primary text-white">
<div class="px-6 py-4">
<div class="flex items-center justify-between">
<div class="flex items-center space-x-8">
<div class="flex items-center space-x-3">
<div class="flex items-center gap-3">
<img src="https://static.readdy.ai/image/2d44f09b25f25697de5dc274e7f0a5a3/04242d6bffded145c33d09c9dcfae98c.png" alt="Norzagaray College Logo" class="w-12 h-12 object-contain">
</div>
<span class="text-2xl font-bold">NCHire</span>
</div>
<nav class="flex space-x-6">
<a href="#" class="nav-link hover:text-secondary transition-colors relative" id="dashboardLink" data-section="dashboard">
  Dashboard
  <span class="nav-indicator absolute bottom-0 left-0 w-full h-0.5 bg-secondary transform scale-x-0 transition-transform duration-200"></span>
</a>
<a href="#" class="nav-link hover:text-secondary transition-colors relative" id="applicationsLink" data-section="applications">
  My Applications
  <span class="nav-indicator absolute bottom-0 left-0 w-full h-0.5 bg-secondary transform scale-x-0 transition-transform duration-200"></span>
</a>
<a href="user_profile.php" class="nav-link hover:text-secondary transition-colors relative" id="profileLink" data-section="profile">
  Profile
  <span class="nav-indicator absolute bottom-0 left-0 w-full h-0.5 bg-secondary transform scale-x-0 transition-transform duration-200"></span>
</a>
</nav>
</div>
<div class="flex items-center space-x-4">
<div class="relative inline-block">
<div class="w-8 h-8 flex items-center justify-center cursor-pointer hover:bg-gray-100 rounded-full transition-colors" id="notificationBtn">
<i class="ri-notification-line text-xl"></i>
<span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full text-xs text-white flex items-center justify-center" id="notificationBadge" style="display: none;"></span>
</div>
<!-- Notification Dropdown -->
<div id="notificationDropdown" class="hidden absolute right-0 top-full mt-2 w-80 bg-white rounded-xl shadow-xl border border-gray-200 text-gray-800 z-50">
<div class="p-4 border-b border-gray-100 flex items-center justify-between">
<h3 class="font-semibold text-gray-900">Notifications</h3>
<button id="markAllRead" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Mark all as read</button>
</div>
<div class="max-h-96 overflow-y-auto" id="notificationsList">
<div class="p-4 text-center text-gray-500">
<i class="ri-loader-4-line text-xl animate-spin mb-2 block"></i>
<p class="text-sm">Loading notifications...</p>
</div>
</div>
</div>
</div>
<?php


$first_name = $_SESSION['first_name'] ?? "Guest";
$initial = strtoupper(substr($first_name, 0, 1));
$profile_picture = $user_profile_data['profile_picture'] ?? '';
?>
<div class="relative inline-block text-left">
    <!-- Profile Button -->
    <div class="flex items-center space-x-3 cursor-pointer" id="profileDropdownBtn">
        <div class="w-8 h-8 bg-secondary rounded-full flex items-center justify-center overflow-hidden">
            <?php if (!empty($profile_picture) && file_exists('uploads/profile_pictures/' . $profile_picture)): ?>
                <img src="uploads/profile_pictures/<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture" class="w-full h-full object-cover">
            <?php else: ?>
                <span class="text-primary font-semibold text-sm"><?= $initial ?></span>
            <?php endif; ?>
        </div>
        <span class="text-sm"><?= htmlspecialchars($first_name) ?></span>
    </div>

    <!-- Dropdown (inside relative container ✅) -->
    <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg z-50">
        <div class="py-2">
            <a href="#" onclick="confirmLogout(event)" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                <i class="ri-logout-box-line w-5 h-5 mr-2"></i>
                Sign Out
            </a>
        </div>
    </div>
</div>

</div>
</div>
</div>
</div>
</header>
<main id="mainContent" class="max-w-7xl mx-auto px-6 py-8">
<div id="jobHeader" class="mb-8">
<h1 class="text-3xl font-bold text-gray-900 mb-2">Available Job Opportunities</h1>
<p class="text-gray-600">Find your perfect career match at NCHire</p>
</div>
<!-- Search, Filter and Sort Section -->
<div id="searchFilters" class="mb-6 space-y-4">
  <!-- Search Bar -->
  <div class="relative">
    <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
      <i class="ri-search-line text-gray-400 text-lg"></i>
    </div>
    <input type="text" id="searchInput" placeholder="Search job positions, departments, or locations..." 
           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
  </div>
  
  <!-- Filter and Sort Controls -->
  <div class="flex flex-wrap gap-3 items-center">
    <!-- Department Filter -->
    <div class="relative">
      <select id="departmentFilter" class="appearance-none bg-white border border-gray-300 rounded-lg px-4 py-2 pr-8 text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
        <option value="">All Departments</option>
        <option value="computer science">Computer Science</option>
        <option value="hospitality management">Hospitality Management</option>
        <option value="education">Education</option>
      </select>
      <div class="absolute right-2 top-1/2 transform -translate-y-1/2 pointer-events-none">
        <i class="ri-arrow-down-s-line text-gray-400"></i>
      </div>
    </div>
    
    <!-- Job Type Filter -->
    <div class="relative">
      <select id="jobTypeFilter" class="appearance-none bg-white border border-gray-300 rounded-lg px-4 py-2 pr-8 text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
        <option value="">All Types</option>
        <option value="full-time">Full-time</option>
        <option value="part-time">Part-time</option>
      </select>
      <div class="absolute right-2 top-1/2 transform -translate-y-1/2 pointer-events-none">
        <i class="ri-arrow-down-s-line text-gray-400"></i>
      </div>
    </div>
    
    <!-- Sort Dropdown -->
    <div class="relative">
      <select id="sortSelect" class="appearance-none bg-white border border-gray-300 rounded-lg px-4 py-2 pr-8 text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
        <option value="newest">Newest First</option>
        <option value="oldest">Oldest First</option>
        <option value="title_asc">Title A-Z</option>
        <option value="title_desc">Title Z-A</option>
        <option value="department_asc">Department A-Z</option>
        <option value="department_desc">Department Z-A</option>
      </select>
      <div class="absolute right-2 top-1/2 transform -translate-y-1/2 pointer-events-none">
        <i class="ri-sort-desc text-gray-400"></i>
      </div>
    </div>
    
    <!-- Active Filters Display -->
    <div id="activeFilters" class="flex flex-wrap gap-2 ml-auto">
      <!-- Active filter tags will appear here -->
    </div>
  </div>
</div>
<!-- Job Listings Container -->
<div id="jobListings" class="grid gap-6">
    <!-- Loading state -->
    <div id="jobsLoading" class="flex justify-center items-center py-12">
        <div class="text-center">
            <i class="ri-loader-4-line text-3xl text-gray-400 animate-spin mb-4"></i>
            <p class="text-gray-500">Loading job opportunities...</p>
        </div>
    </div>
    
    <!-- Jobs will be populated here by JavaScript -->
    <div id="jobsContainer"></div>
    
    <!-- No jobs message -->
    <div id="noJobsMessage" class="hidden text-center py-12">
        <div class="text-gray-500">
            <i class="ri-briefcase-line text-4xl mb-4"></i>
            <p class="text-lg">No job opportunities found</p>
            <p class="text-sm">Try adjusting your search criteria</p>
        </div>
    </div>
</div>

<!-- Pagination Controls -->
<div id="paginationContainer" class="mt-8 flex justify-center items-center space-x-4">
    <!-- Pagination info -->
    <div id="paginationInfo" class="text-sm text-gray-600 mr-4">
        <!-- Will show "Showing 1-4 of 20 jobs" -->
    </div>
    
    <!-- Pagination controls -->
    <div class="flex items-center space-x-2">
        <!-- Previous button -->
        <button id="prevPageBtn" class="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
            <i class="ri-arrow-left-line"></i>
        </button>
        
        <!-- Page numbers container -->
        <div id="pageNumbers" class="flex items-center space-x-1">
            <!-- Page numbers will be populated here -->
        </div>
        
        <!-- Next button -->
        <button id="nextPageBtn" class="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
            <i class="ri-arrow-right-line"></i>
        </button>
    </div>
</div>

<!-- Detailed Job View Section -->
<div id="jobDetailView" class="hidden">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <!-- Job Header with Background -->
        <div class="bg-gradient-to-r from-blue-800 to-blue-900 text-white p-8 relative">
            <div class="absolute top-4 left-4">
                <button id="backToJobs" class="text-white hover:text-gray-200 transition-colors flex items-center gap-2">
                    <i class="ri-arrow-left-line text-xl"></i>
                    <span>Back to Jobs</span>
                </button>
            </div>
            <div class="max-w-4xl w-full mx-auto">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h1 id="detailJobTitle" class="text-3xl font-bold mb-4"></h1>
                        <div id="detailJobMeta" class="flex flex-wrap items-center gap-6 text-blue-100 text-sm mb-4">
                            <!-- Meta info with icons will be populated here -->
                        </div>
                        <div id="detailSalary" class="text-white text-xl font-semibold">
                            <span><!-- Salary will be populated here --></span>
                        </div>
                    </div>
                    <div id="detailDeadline" class="text-right ml-8">
                        <div class="text-blue-100 text-sm font-medium">DEADLINE OF SUBMISSIONS</div>
                        <div id="detailDeadlineDate" class="text-white text-lg font-bold">Loading...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Job Content -->
        <div class="p-8">
            <div class="max-w-4xl mx-auto">
                <!-- Minimum Qualifications Section -->
                <div class="mb-8">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">MINIMUM QUALIFICATIONS</h2>
                    
                    <div class="grid md:grid-cols-2 gap-8 mb-6">
                        <div>
                            <h3 class="text-gray-500 font-semibold mb-2">EDUCATION</h3>
                            <p id="detailEducation" class="text-gray-700">Loading...</p>
                        </div>
                        <div>
                            <h3 class="text-gray-500 font-semibold mb-2">EXPERIENCE</h3>
                            <p id="detailExperience" class="text-gray-700">Loading...</p>
                        </div>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-gray-500 font-semibold mb-2">TRAINING</h3>
                        <p id="detailTraining" class="text-gray-700">Loading...</p>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-gray-500 font-semibold mb-2">ELIGIBILITY</h3>
                        <p id="detailEligibility" class="text-gray-700">Loading...</p>
                    </div>
                </div>

                <!-- Job Requirements Section -->
                <div id="requirementsSection" class="mb-8 bg-blue-50 border-l-4 border-primary rounded-lg p-6">
                    <h2 class="text-xl font-bold text-primary mb-4 flex items-center">
                        <i class="ri-file-list-line mr-2"></i>
                        JOB REQUIREMENTS
                    </h2>
                    <div id="detailJobRequirements" class="text-gray-700 space-y-2">
                        <!-- Job requirements will be populated here -->
                    </div>
                </div>

                <!-- Additional Details Section -->
                <div class="mb-8">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">ADDITIONAL DETAILS</h2>
                    
                    <div class="mb-6">
                        <h3 class="text-gray-900 font-semibold mb-3">Competency:</h3>
                        <div id="detailCompetency" class="text-gray-700 mb-2">Loading...</div>
                    </div>
                </div>

                <!-- Job Description Section -->
                <div class="mb-8">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">DESCRIPTION</h2>
                    <div id="detailJobDescription" class="text-gray-700 space-y-3">
                        <!-- Job description will be populated here -->
                    </div>
                </div>


                <!-- Apply Button -->
                <div class="flex justify-center pt-6 border-t border-gray-200">
                    <button id="detailApplyBtn" class="px-8 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors font-medium apply-btn">
                        Apply Now
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Application Wizard (full-screen) -->
<div id="applicationWizard" class="hidden fixed inset-0 bg-gray-50 z-50 overflow-y-auto" style="background-color: #f9fafb !important;">
    <div class="min-h-screen bg-white" style="background-color: white !important;">
        <!-- Wizard Header -->
        <div class="bg-gradient-to-r from-blue-800 to-blue-900 text-white sticky top-0 z-10 shadow-lg" style="background: linear-gradient(to right, #1e40af, #1e3a8a) !important; color: white !important; padding: 1rem !important; position: sticky !important; top: 0 !important; z-index: 10 !important; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;">
            <div class="flex items-center justify-between mb-3" style="display: flex !important; align-items: center !important; justify-content: space-between !important; margin-bottom: 0.75rem !important;">
                <button id="backFromWizard" class="text-white hover:text-gray-200 transition-colors flex items-center" style="color: white !important; display: flex !important; align-items: center !important; font-size: 0.875rem !important;">
                    <i class="ri-arrow-left-line text-lg mr-2"></i>Back to Jobs
                </button>
                <div class="text-sm opacity-80" id="wizardJobTitle" style="font-size: 0.8rem !important; opacity: 0.8 !important;">Applying for: <span>-</span></div>
            </div>
            <!-- Progress Steps -->
            <div class="max-w-6xl mx-auto" style="max-width: 72rem !important; margin-left: auto !important; margin-right: auto !important;">
                <div class="flex items-center justify-between" style="display: flex !important; align-items: center !important; justify-content: space-between !important;">
                    <!-- Step 1 -->
                    <div class="flex-1 flex items-center" style="flex: 1 !important; display: flex !important; align-items: center !important;">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center font-semibold step-dot" data-step="1" style="width: 1.75rem !important; height: 1.75rem !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-weight: 600 !important; background: #f59e0b !important; color: #1e40af !important; font-size: 0.875rem !important;">1</div>
                        <div class="flex-1 h-0.5 mx-1 bg-white bg-opacity-30 step-line" data-after="1" style="flex: 1 !important; height: 2px !important; margin: 0 0.25rem !important; background-color: rgba(255, 255, 255, 0.3) !important;"></div>
                    </div>
                    <!-- Step 2 -->
                    <div class="flex-1 flex items-center" style="flex: 1 !important; display: flex !important; align-items: center !important;">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center font-semibold step-dot" data-step="2" style="width: 1.75rem !important; height: 1.75rem !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-weight: 600 !important; background: rgba(255,255,255,0.3) !important; color: white !important; font-size: 0.875rem !important;">2</div>
                        <div class="flex-1 h-0.5 mx-1 bg-white bg-opacity-30 step-line" data-after="2" style="flex: 1 !important; height: 2px !important; margin: 0 0.25rem !important; background-color: rgba(255, 255, 255, 0.3) !important;"></div>
                    </div>
                    <!-- Step 3 -->
                    <div class="flex-1 flex items-center" style="flex: 1 !important; display: flex !important; align-items: center !important;">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center font-semibold step-dot" data-step="3" style="width: 1.75rem !important; height: 1.75rem !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-weight: 600 !important; background: rgba(255,255,255,0.3) !important; color: white !important; font-size: 0.875rem !important;">3</div>
                        <div class="flex-1 h-0.5 mx-1 bg-white bg-opacity-30 step-line" data-after="3" style="flex: 1 !important; height: 2px !important; margin: 0 0.25rem !important; background-color: rgba(255, 255, 255, 0.3) !important;"></div>
                    </div>
                    <!-- Step 4 -->
                    <div class="flex-1 flex items-center" style="flex: 1 !important; display: flex !important; align-items: center !important;">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center font-semibold step-dot" data-step="4" style="width: 1.75rem !important; height: 1.75rem !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-weight: 600 !important; background: rgba(255,255,255,0.3) !important; color: white !important; font-size: 0.875rem !important;">4</div>
                        <div class="flex-1 h-0.5 mx-1 bg-white bg-opacity-30 step-line" data-after="4" style="flex: 1 !important; height: 2px !important; margin: 0 0.25rem !important; background-color: rgba(255, 255, 255, 0.3) !important;"></div>
                    </div>
                    <!-- Step 5 -->
                    <div class="flex-1 flex items-center" style="flex: 1 !important; display: flex !important; align-items: center !important;">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center font-semibold step-dot" data-step="5" style="width: 1.75rem !important; height: 1.75rem !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-weight: 600 !important; background: rgba(255,255,255,0.3) !important; color: white !important; font-size: 0.875rem !important;">5</div>
                        <div class="flex-1 h-0.5 mx-1 bg-white bg-opacity-30 step-line" data-after="5" style="flex: 1 !important; height: 2px !important; margin: 0 0.25rem !important; background-color: rgba(255, 255, 255, 0.3) !important;"></div>
                    </div>
                    <!-- Step 6 -->
                    <div class="flex items-center" style="display: flex !important; align-items: center !important;">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center font-semibold step-dot" data-step="6" style="width: 1.75rem !important; height: 1.75rem !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-weight: 600 !important; background: rgba(255,255,255,0.3) !important; color: white !important; font-size: 0.875rem !important;">6</div>
                    </div>
                </div>
                <div class="mt-2 text-center text-blue-100 text-xs" id="wizardStepLabel" style="margin-top: 0.5rem !important; text-align: center !important; color: #bfdbfe !important; font-size: 0.75rem !important;">Step 1 of 6: Personal Information, Work Experience, Education & Skills</div>
            </div>
        </div>

        <!-- Wizard Body -->
        <div class="p-4 pb-16" style="min-height: 400px; background: #f8fafc !important; padding: 1.5rem !important; padding-bottom: 4rem !important; padding-top: 1rem !important;">
            <div class="max-w-4xl mx-auto" style="position: relative; z-index: 1; max-width: 56rem; margin: 0 auto;">
                <!-- Step 1: Personal Information, Work Experience, Education & Skills -->
                <section id="step1" class="wizard-step">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Personal Information, Work Experience, Education & Skills</h2>
                    <p class="text-sm text-gray-600 mb-6">Complete all sections below to help us evaluate your qualifications.</p>
                    
                    <!-- Personal Information -->
                    <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="ri-user-line mr-2 text-blue-600"></i>Personal Information
                        </h3>
                        <div class="space-y-4">
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                                    <input type="text" id="pf_first_name" class="w-full border border-gray-300 rounded-lg p-3" required>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                                    <input type="text" id="pf_last_name" class="w-full border border-gray-300 rounded-lg p-3" required>
                                </div>
                            </div>
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                                    <input type="email" id="pf_email" class="w-full border border-gray-300 rounded-lg p-3" required>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-700 mb-1">Contact Number <span class="text-red-500">*</span></label>
                                    <input type="tel" id="pf_phone" class="w-full border border-gray-300 rounded-lg p-3" 
                                           pattern="09[0-9]{9}" 
                                           maxlength="11" 
                                           placeholder="09XXXXXXXXX"
                                           title="Please enter a valid Philippine mobile number (e.g., 09123456789)"
                                           oninput="this.value = this.value.replace(/[^0-9]/g, '')" 
                                           required>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-700 mb-1">Address</label>
                                <input type="text" id="pf_address" class="w-full border border-gray-300 rounded-lg p-3" placeholder="Optional">
                            </div>
                        </div>
                    </div>

                    <!-- Work Experience -->
                    <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <i class="ri-briefcase-line mr-2 text-blue-600"></i>Work Experience
                                <span id="workExpCount" class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">0</span>
                            </span>
                            <button type="button" id="addWorkExpBtn" class="text-sm bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 flex items-center gap-1">
                                <i class="ri-add-line"></i>Add Experience
                            </button>
                        </h3>
                        <div id="workExperienceDisplay" class="space-y-3">
                            <!-- Work experience boxes will be populated here -->
                            <div class="text-sm text-gray-500 italic">Loading work experience...</div>
                        </div>
                    </div>

                    <!-- Skills -->
                    <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <i class="ri-lightbulb-line mr-2 text-blue-600"></i>Skills & Competencies
                                <span id="skillsCount" class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-semibold rounded-full">0</span>
                            </span>
                            <button type="button" id="addSkillsBtn" class="text-sm bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700 flex items-center gap-1">
                                <i class="ri-add-line"></i>Add Skills
                            </button>
                        </h3>
                        <div id="skillsDisplay" class="flex flex-wrap gap-2">
                            <!-- Skill tags will be populated here -->
                            <div class="text-sm text-gray-500 italic">Loading skills...</div>
                        </div>
                    </div>

                    <!-- Education -->
                    <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <i class="ri-graduation-cap-line mr-2 text-blue-600"></i>Education
                                <span id="educationCount" class="px-2 py-0.5 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full">0</span>
                            </span>
                            <button type="button" id="addEducationBtn" class="text-sm bg-purple-600 text-white px-3 py-1.5 rounded-lg hover:bg-purple-700 flex items-center gap-1">
                                <i class="ri-add-line"></i>Add Education
                            </button>
                        </h3>
                        <div id="educationDisplay" class="space-y-3">
                            <!-- Education entries will be populated here -->
                            <div class="text-sm text-gray-500 italic">Loading education...</div>
                        </div>
                    </div>
                    
                    <!-- Hidden fields for form submission -->
                    <input type="hidden" id="wx_job_title">
                    <input type="hidden" id="wx_company">
                    <input type="hidden" id="wx_location">
                    <input type="hidden" id="wx_start">
                    <input type="hidden" id="wx_end">
                    <input type="hidden" id="wx_current">
                    <input type="hidden" id="wx_description">
                    <input type="hidden" id="wx_skills">
                    
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" id="saveAllStep1Btn" class="px-5 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Save Progress</button>
                        <button type="button" id="toStep2" class="px-5 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">Next</button>
                    </div>
                </section>

                <!-- Step 2: Submit Requirements -->
                <section id="step2" class="wizard-step hidden">
                    <h2 class="text-lg font-bold text-gray-900 mb-2">Submit Requirements</h2>
                    <p class="text-gray-600 mb-4">Upload your required documents. Accepted formats: PDF, DOC, DOCX, JPG, PNG. Maximum size: 5MB per file.</p>
                    
                    <form id="requirementsForm" class="space-y-4" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="submit_application" value="1">
                        <input type="hidden" name="ajax_submit" value="1">
                        <input type="hidden" name="job_id" id="rf_job_id">
                        <input type="hidden" name="job_title" id="rf_job_title">
                        <input type="hidden" name="full_name" id="rf_full_name">
                        <input type="hidden" name="email" id="rf_email">
                        <input type="hidden" name="cellphone" id="rf_cellphone">

                        <!-- Document Requirements -->
                        <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                            <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-4 rounded-t-lg">
                                <div class="flex items-center">
                                    <i class="ri-file-text-line text-lg mr-2"></i>
                                    <div>
                                        <h3 class="text-lg font-semibold">Required Documents</h3>
                                        <p class="text-blue-100 text-xs mt-1">Please upload all required documents below</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-4 space-y-4">
                                <!-- Primary Documents -->
                                <div class="grid md:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700">
                                            <i class="ri-file-text-line mr-2 text-blue-600"></i>Application Letter <span class="text-red-500">*</span>
                                        </label>
                                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-blue-400 transition-colors">
                                            <input type="file" name="applicationLetter" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required
                                                   class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700">
                                            <i class="ri-file-text-line mr-2 text-blue-600"></i>Updated and Comprehensive Resume<span class="text-red-500">*</span>
                                        </label>
                                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-blue-400 transition-colors">
                                            <input type="file" name="resume_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required
                                                   class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                        </div>
                                    </div>
                                </div>

                                <!-- Educational Documents -->
                                <div class="border-t pt-3">
                                    <h4 class="text-base font-semibold text-gray-800 mb-3">Educational Documents</h4>
                                    <div class="grid md:grid-cols-2 gap-4">
                                        <div class="space-y-2">
                                            <label class="block text-sm font-semibold text-gray-700">
                                                <i class="ri-file-text-line mr-2 text-blue-600"></i>Transcript of Records (TOR) <span class="text-red-500">*</span>
                                            </label>
                                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-3 hover:border-blue-400 transition-colors">
                                                <input type="file" name="transcript" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required
                                                       class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                            </div>
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="block text-sm font-semibold text-gray-700">
                                                <i class="ri-file-text-line mr-2 text-blue-600"></i>Diploma <span class="text-red-500">*</span>
                                            </label>
                                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-3 hover:border-blue-400 transition-colors">
                                                <input type="file" name="diploma" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required
                                                       class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Professional Documents -->
                                <div class="border-t pt-3">
                                    <h4 class="text-base font-semibold text-gray-800 mb-3">Professional Documents</h4>
                                    <div class="grid md:grid-cols-2 gap-4">
                                        <div class="space-y-2">
                                            <label class="block text-sm font-semibold text-gray-700">
                                                <i class="ri-file-text-line mr-2 text-blue-600"></i>Professional License <span class="text-green-600">(Optional)</span>
                                            </label>
                                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-3 hover:border-blue-400 transition-colors">
                                                <input type="file" name="license" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                                       class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                            </div>
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="block text-sm font-semibold text-gray-700">
                                                <i class="ri-file-text-line mr-2 text-blue-600"></i>Certificate of Employment (COE) <span class="text-red-500">*</span>
                                            </label>
                                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-3 hover:border-blue-400 transition-colors">
                                                <input type="file" name="coe" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required
                                                       class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Additional Certificates -->
                                <div class="border-t pt-3">
                                    <h4 class="text-base font-semibold text-gray-800 mb-3">Additional Certificates</h4>
                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700">
                                            <i class="ri-file-text-line mr-2 text-blue-600"></i>Seminars/Trainings Certificates <span class="text-red-500">*</span>
                                        </label>
                                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-3 hover:border-blue-400 transition-colors">
                                            <input type="file" name="certificates[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required
                                                   class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">You can select multiple files at once. Hold Ctrl/Cmd to select multiple files.</p>
                                    </div>
                                    
                                    <!-- Masteral Certificate (Optional) -->
                                    <div class="space-y-2 mt-4">
                                        <label class="block text-sm font-semibold text-gray-700">
                                            <i class="ri-file-text-line mr-2 text-blue-600"></i>Masteral Certificate <span class="text-green-600">(Optional)</span>
                                        </label>
                                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-3 hover:border-blue-400 transition-colors">
                                            <input type="file" name="masteral_cert" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                                   class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">If you have a master's degree, upload your certificate here.</p>
                                    </div>
                                </div>

                                <!-- File Requirements Notice -->
                                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mt-3">
                                    <div class="flex items-start">
                                        <i class="ri-information-line text-amber-600 text-base mr-2 mt-0.5"></i>
                                        <div class="text-sm text-amber-800">
                                            <p class="font-semibold mb-1">File Requirements:</p>
                                            <ul class="list-disc list-inside space-y-0.5 text-xs">
                                                <li>Accepted formats: PDF, DOC, DOCX, JPG, PNG</li>
                                                <li>Maximum file size: 5MB per file</li>
                                                <li>Files marked with <span class="text-red-500">*</span> are required</li>
                                                <li>Ensure documents are clear and readable</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex justify-between items-center pt-6 border-t">
                            <button type="button" id="backToStep1" class="flex items-center px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                <i class="ri-arrow-left-line mr-2"></i>Back
                            </button>
                            <button type="submit" class="flex items-center px-8 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all shadow-lg font-semibold">
                                <i class="ri-send-plane-line mr-2"></i>Submit Application
                            </button>
                            <button type="button" id="step2NextBtn" onclick="setStep(3)" class="hidden flex items-center px-8 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all shadow-lg font-semibold">
                                Next <i class="ri-arrow-right-line ml-2"></i>
                            </button>
                        </div>
                    </form>
                </section>
                
                <!-- Step 3: Interview Scheduled -->
                <section id="step3" class="wizard-step hidden">
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">Interview Scheduled</h2>
                    <p class="text-gray-600 mb-6">Waiting for admin to schedule and approve your interview</p>
                    
                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                        <div id="interview_status_container" class="text-center py-8">
                            <i class="ri-calendar-line text-6xl text-blue-500 mb-4"></i>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Interview Scheduling</h3>
                            <p class="text-gray-600" id="interview_status_text">Your application has been submitted. Please wait while the admin reviews your documents and schedules an interview.</p>
                            <div id="interview_details" class="hidden mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <!-- Interview details will be populated here -->
                            </div>
                            <div id="interview_approved_status" class="hidden mt-4">
                                <!-- Approval status will be shown here -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center pt-6">
                        <button type="button" id="step3_back_btn" onclick="setStep(2)" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            <i class="ri-arrow-left-line mr-2"></i>Back
                        </button>
                        <button type="button" id="interview_next_btn" onclick="setStep(4)" disabled class="px-6 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed" title="Waiting for admin approval">
                            Next: Demo Teaching <i class="ri-arrow-right-line ml-2"></i>
                        </button>
                    </div>
                </section>
                
                <!-- Step 4: Demo Scheduled -->
                <section id="step4" class="wizard-step hidden">
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">Demo Teaching Scheduled</h2>
                    <p class="text-gray-600 mb-6">Waiting for admin to schedule and approve your demo teaching</p>
                    
                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                        <div id="demo_status_container" class="text-center py-8">
                            <i class="ri-presentation-line text-6xl text-indigo-500 mb-4"></i>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Demo Teaching Scheduling</h3>
                            <p class="text-gray-600" id="demo_status_text">Your interview has been completed. Please wait while the admin schedules your demo teaching session.</p>
                            <div id="demo_details" class="hidden mt-6 p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
                                <!-- Demo details will be populated here -->
                            </div>
                            <div id="demo_approved_status" class="hidden mt-4">
                                <!-- Approval status will be shown here -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center pt-6">
                        <button type="button" onclick="setStep(3)" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            <i class="ri-arrow-left-line mr-2"></i>Back
                        </button>
                        <button type="button" id="demo_next_btn" onclick="setStep(5)" disabled class="px-6 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed" title="Waiting for admin approval">
                            Next: Psychological Exam <i class="ri-arrow-right-line ml-2"></i>
                        </button>
                    </div>
                </section>
                
                <!-- Step 5: Psychological Exam -->
                <section id="step5" class="wizard-step hidden">
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">Psychological Examination</h2>
                    <p class="text-gray-600 mb-6">Upload your psychological exam receipt or proof of completion. After submission, please wait for the admin to review and mark you as hired.</p>
                    
                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                        <div id="psych_status_container" class="py-8">
                            <div class="text-center mb-6">
                                <i class="ri-brain-line text-6xl text-purple-500 mb-4"></i>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Psychological Examination</h3>
                                <p class="text-gray-600" id="psych_status_text">Please take your psychological exam and upload your receipt/proof of completion.</p>
                            </div>
                            
                            <div id="psych_upload_form" class="hidden max-w-md mx-auto">
                                <form id="psychReceiptForm" enctype="multipart/form-data">
                                    <input type="hidden" id="wizard_psych_app_id">
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Upload Receipt/Proof <span class="text-red-500">*</span>
                                        </label>
                                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-purple-400 transition-colors">
                                            <input type="file" id="wizard_psych_receipt" accept=".pdf,.jpg,.jpeg,.png" required
                                                   class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                                        </div>
                                        <p class="text-xs text-gray-500 mt-2">Accepted formats: PDF, JPG, PNG (Max 5MB)</p>
                                    </div>
                                    <button type="submit" class="w-full px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 flex items-center justify-center">
                                        <i class="ri-upload-line mr-2"></i>Upload Receipt
                                    </button>
                                </form>
                            </div>
                            
                            <div id="psych_upload_success" class="hidden text-center p-4 bg-green-50 border border-green-200 rounded-lg">
                                <i class="ri-check-circle-line text-4xl text-green-600 mb-2"></i>
                                <p class="text-green-800 font-semibold">Receipt uploaded successfully!</p>
                            </div>
                            
                            <div id="psych_approved_status" class="hidden mt-4">
                                <!-- Approval status will be shown here -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center pt-6">
                        <button type="button" onclick="setStep(4)" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            <i class="ri-arrow-left-line mr-2"></i>Back
                        </button>
                        <button type="button" id="psych_next_btn" onclick="setStep(6)" disabled class="px-6 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed" title="Waiting for admin to mark you as hired">
                            Next: Initially Hired <i class="ri-arrow-right-line ml-2"></i>
                        </button>
                    </div>
                </section>
                
                <!-- Step 6: Initially Hired -->
                <section id="step6" class="wizard-step hidden">
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">Initially Hired</h2>
                    <p class="text-gray-600 mb-6">Congratulations! You have been marked as initially hired</p>
                    
                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                        <div id="hired_status_container" class="text-center py-8">
                            <i class="ri-user-star-line text-6xl text-green-500 mb-4"></i>
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">Congratulations!</h3>
                            <p class="text-lg text-gray-600 mb-4">You have been marked as initially hired</p>
                            <div id="hired_details" class="hidden mt-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                                <!-- Initial hiring details will be populated here -->
                            </div>
                            <p class="text-sm text-gray-500 mt-6">Please wait for further instructions regarding your onboarding process.</p>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center pt-6">
                        <button type="button" onclick="setStep(5)" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            <i class="ri-arrow-left-line mr-2"></i>Back
                        </button>
                        <button type="button" onclick="closeWizardAndRefresh()" class="px-8 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold">
                            <i class="ri-check-line mr-2"></i>Close & View My Applications
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<div class="mt-8 flex justify-center">

</div>
</button>
</div>
</div>
</main>
<div id="jobModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
<div class="bg-white rounded-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
<div class="p-6 border-b border-gray-200">
<div class="flex justify-between items-start">
<div>
<h2 id="modalTitle" class="text-2xl font-bold text-gray-900 mb-2"></h2>
<div id="modalMeta" class="flex items-center text-sm text-gray-600 space-x-4"></div>
</div>
<button id="closeModal" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600">
<i class="ri-close-line text-xl"></i>
</button>
</div>
</div>
<div class="p-6">
<div class="mb-6">
<h3 class="text-lg font-semibold text-gray-900 mb-3">Description</h3>
<p id="modalDescription" class="text-gray-700 leading-relaxed"></p>
</div>
<div class="flex space-x-4">
<button id="modalApplyBtn" class="flex-1 bg-primary text-white py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium whitespace-nowrap !rounded-button">Apply for this Position</button>
<button id="modalSaveBtn" class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap !rounded-button">
<div class="w-5 h-5 flex items-center justify-center">
<i class="ri-bookmark-line"></i>
</div>
Save Job
</button>
</div>
</div>
</div>
</div>
<script id="headerInteractions">
document.addEventListener('DOMContentLoaded', function() {
const notificationBtn = document.getElementById('notificationBtn');
const notificationDropdown = document.getElementById('notificationDropdown');
const profileDropdownBtn = document.getElementById('profileDropdownBtn');
const profileDropdown = document.getElementById('profileDropdown');
const searchInput = document.getElementById('searchInput');
const searchResults = document.getElementById('searchResults');

notificationBtn.addEventListener('click', function(e) {
e.stopPropagation();
console.log('Notification button clicked');
profileDropdown.classList.add('hidden');
notificationDropdown.classList.toggle('hidden');
console.log('Dropdown visibility:', !notificationDropdown.classList.contains('hidden'));

// Force show dropdown and load notifications when opened
if (!notificationDropdown.classList.contains('hidden')) {
  console.log('Showing dropdown and loading notifications');
  notificationDropdown.style.display = 'block';
  notificationDropdown.style.visibility = 'visible';
  loadNotifications();
} else {
  console.log('Hiding dropdown');
  notificationDropdown.style.display = '';
  notificationDropdown.style.visibility = '';
}
});

profileDropdownBtn.addEventListener('click', function(e) {
e.stopPropagation();
notificationDropdown.classList.add('hidden');
profileDropdown.classList.toggle('hidden');
});

document.addEventListener('click', function(e) {
if (notificationDropdown && notificationBtn && !notificationDropdown.contains(e.target) && !notificationBtn.contains(e.target)) {
notificationDropdown.classList.add('hidden');
}
if (profileDropdown && profileDropdownBtn && !profileDropdown.contains(e.target) && !profileDropdownBtn.contains(e.target)) {
profileDropdown.classList.add('hidden');
}
if (searchResults && searchInput && !searchResults.contains(e.target) && !searchInput.contains(e.target)) {
searchResults.classList.add('hidden');
}
});

searchInput.addEventListener('input', function() {
const searchTerm = this.value.toLowerCase();
if (searchTerm.length > 0) {
const allJobs = Object.values(jobListings).flat();
const filteredJobs = allJobs.filter(job => 
job.title.toLowerCase().includes(searchTerm) || 
job.department.toLowerCase().includes(searchTerm) ||
job.description.toLowerCase().includes(searchTerm)
);

if (filteredJobs.length > 0) {
searchResults.innerHTML = filteredJobs.map(job => `
<div class="p-4 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0">
<div class="flex items-start">
<div class="flex-1">
<h4 class="font-semibold text-gray-900">${job.title}</h4>
<div class="flex items-center text-sm text-gray-600 mt-1">
<span class="flex items-center">
<i class="${job.icon} mr-1"></i>
${job.department}
</span>
<span class="mx-2">•</span>
<span>${job.type}</span>
</div>
</div>
<span class="${job.status === 'Open' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'} px-2 py-1 rounded text-xs">${job.status}</span>
</div>
</div>
`).join('');
} else {
searchResults.innerHTML = `
<div class="p-4 text-center text-gray-500">
No jobs found matching "${searchTerm}"
</div>`;
}
searchResults.classList.remove('hidden');
} else {
searchResults.classList.add('hidden');
}
});

searchResults.addEventListener('click', function(e) {
const jobItem = e.target.closest('.p-4');
if (jobItem) {
const jobTitle = jobItem.querySelector('h4').textContent;
searchInput.value = jobTitle;
searchResults.classList.add('hidden');
const jobElement = Array.from(document.querySelectorAll('h3')).find(el => el.textContent === jobTitle);
if (jobElement) {
jobElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
jobElement.closest('.bg-white').classList.add('ring-2', 'ring-primary');
setTimeout(() => {
jobElement.closest('.bg-white').classList.remove('ring-2', 'ring-primary');
}, 2000);
}
}
});


});
</script>

<!-- Wizard Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Fallback toast helper if not present on this page
  if (typeof window.showToast !== 'function') {
    window.showToast = function(message, type = 'info', duration = 3000) {
      const id = 'toast-container';
      let container = document.getElementById(id);
      if (!container) {
        container = document.createElement('div');
        container.id = id;
        container.className = 'fixed top-4 right-4 z-[9999] space-y-2';
        document.body.appendChild(container);
      }
      const base = 'px-4 py-3 rounded border shadow flex items-center max-w-sm bg-white';
      const variants = {
        success: 'border-green-300 text-green-800 bg-green-50',
        error: 'border-red-300 text-red-800 bg-red-50',
        info: 'border-blue-300 text-blue-800 bg-blue-50',
        warning: 'border-yellow-300 text-yellow-800 bg-yellow-50'
      };
      const icon = {
        success: '<i class="ri-check-line mr-2"></i>',
        error: '<i class="ri-error-warning-line mr-2"></i>',
        info: '<i class="ri-information-line mr-2"></i>',
        warning: '<i class="ri-alert-line mr-2"></i>'
      };
      const toast = document.createElement('div');
      toast.className = `${base} ${variants[type] || variants.info}`;
      toast.innerHTML = `${icon[type] || icon.info}<span class="text-sm"></span>`;
      toast.querySelector('span').textContent = String(message || '');
      container.appendChild(toast);
      setTimeout(() => {
        toast.remove();
        if (container.children.length === 0) container.remove();
      }, duration);
    };
  }
  const mainContent = document.getElementById('mainContent');
  const listings = document.getElementById('jobListings');
  const detailView = document.getElementById('jobDetailView');
  const wizard = document.getElementById('applicationWizard');
  const jobHeader = document.getElementById('jobHeader');
  const searchFilters = document.getElementById('searchFilters');
  const pagination = document.getElementById('paginationContainer');

  const stepDots = document.querySelectorAll('.step-dot');
  const stepLabel = document.getElementById('wizardStepLabel');
  const wizardJobTitle = document.querySelector('#wizardJobTitle span');

  const step1 = document.getElementById('step1');
  const step2 = document.getElementById('step2');
  const step3 = document.getElementById('step3');
  const step4 = document.getElementById('step4');
  const step5 = document.getElementById('step5');
  const step6 = document.getElementById('step6');

  const pf_first = document.getElementById('pf_first_name');
  const pf_last = document.getElementById('pf_last_name');
  const pf_email = document.getElementById('pf_email');
  const pf_phone = document.getElementById('pf_phone');
  const pf_address = document.getElementById('pf_address');

  const wx_job = document.getElementById('wx_job_title');
  const wx_comp = document.getElementById('wx_company');
  const wx_loc = document.getElementById('wx_location');
  const wx_start = document.getElementById('wx_start');
  const wx_end = document.getElementById('wx_end');
  const wx_cur = document.getElementById('wx_current');
  const wx_desc = document.getElementById('wx_description');

  const rf_job_id = document.getElementById('rf_job_id');
  const rf_job_title = document.getElementById('rf_job_title');
  const rf_full_name = document.getElementById('rf_full_name');
  const rf_email = document.getElementById('rf_email');
  const rf_cellphone = document.getElementById('rf_cellphone');

  let currentStep = 1;
  let context = { jobId: null, jobTitle: null };
  let isViewMode = false; // Track if wizard is in view-only mode
  let globalResubmissionDocs = []; // Store resubmission docs globally

  // Prefill from PHP-provided profile data when available
  const initialProfile = <?php echo json_encode([
    'first_name' => $user_profile_data['first_name'] ?? '',
    'last_name' => $user_profile_data['last_name'] ?? '',
    'email' => $user_profile_data['email'] ?? '',
    'phone' => $user_profile_data['phone'] ?? '',
    'address' => $user_address ?? ''
  ]); ?>;
  
  // Work experience is now an array of all experiences
  const initialWorkExperiences = <?php echo json_encode($user_work_experience ?? []); ?>;
  
  // Education is now an array of all education entries
  const initialEducation = <?php echo json_encode($user_education ?? []); ?>;
  
  const initialSkills = <?php echo json_encode($user_skills ?? ''); ?>;

  // Make setStep globally accessible
  window.setStep = function(n) {
    console.log('=== setStep called with:', n, '===');
    console.log('Step elements check:');
    console.log('- step1:', step1);
    console.log('- step2:', step2);
    console.log('- step3:', step3);
    console.log('- step4:', step4);
    console.log('- step5:', step5);
    console.log('- step6:', step6);
    
    // CRITICAL: When showing step 1, immediately initialize displays
    if (n === 1) {
      console.log('🎯 Step 1 activated - initializing displays...');
      setTimeout(() => {
        console.log('📊 Calling display functions...');
        console.log('Initial data check:');
        console.log('- Work Experiences:', initialWorkExperiences);
        console.log('- Education:', initialEducation);
        console.log('- Skills:', initialSkills);
        
        try {
          displayWorkExperience();
          console.log('✅ displayWorkExperience() called');
        } catch (e) {
          console.error('❌ Error in displayWorkExperience:', e);
        }
        
        try {
          displaySkills();
          console.log('✅ displaySkills() called');
        } catch (e) {
          console.error('❌ Error in displaySkills:', e);
        }
        
        try {
          displayEducation();
          console.log('✅ displayEducation() called');
        } catch (e) {
          console.error('❌ Error in displayEducation:', e);
        }
      }, 100);
    }
    
    currentStep = n;
    
    // FIRST: Hide ALL steps aggressively
    console.log('Phase 1: Hiding ALL steps...');
    document.querySelectorAll('.wizard-step').forEach(step => {
      step.style.setProperty('display', 'none', 'important');
      step.style.setProperty('visibility', 'hidden', 'important');
      step.style.setProperty('opacity', '0', 'important');
      step.classList.add('hidden');
    });
    
    // SECOND: Show only the target step
    console.log('Phase 2: Showing step', n, '...');
    const steps = [step1, step2, step3, step4, step5, step6];
    const targetStep = steps[n - 1];
    
    if (targetStep) {
      targetStep.style.setProperty('display', 'block', 'important');
      targetStep.style.setProperty('visibility', 'visible', 'important');
      targetStep.style.setProperty('opacity', '1', 'important');
      targetStep.classList.remove('hidden');
      console.log(`✓ Step ${n} is now VISIBLE`);
    } else {
      console.error(`✗ Step ${n} element not found!`);
    }
    
    // When navigating to Step 2, restore file indicators if application data exists OR load draft
    if (n === 2) {
      // Check if we should load draft (no current application data)
      if (!window.currentApplicationData && !window._draftLoadAttempted) {
        console.log('📥 No application data - attempting to load saved draft...');
        window._draftLoadAttempted = true;
        
        fetch('get_draft.php')
          .then(response => response.json())
          .then(data => {
            if (data.success && data.has_draft) {
              console.log('✅ Draft found!', data.draft);
              
              // Show notification that draft was loaded
              const draftNotif = document.createElement('div');
              draftNotif.className = 'fixed top-4 right-4 bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded shadow-md animate-fade-in z-[10001]';
              draftNotif.innerHTML = `
                <div class='flex items-center'>
                  <i class='ri-file-list-line mr-2'></i>
                  <span>Previously saved documents loaded! You can reuse them or upload new ones.</span>
                </div>
              `;
              document.body.appendChild(draftNotif);
              setTimeout(() => draftNotif.remove(), 5000);
              
              // Add visual indicators for saved documents with checkboxes and file names
              const docMap = {
                'application_letter': { name: 'applicationLetter', label: 'Application Letter' },
                'resume': { name: 'resume_file', label: 'Resume' },
                'tor': { name: 'transcript', label: 'Transcript of Records' },
                'diploma': { name: 'diploma', label: 'Diploma' },
                'professional_license': { name: 'license', label: 'Professional License' },
                'coe': { name: 'coe', label: 'Certificate of Employment' },
                'seminars_trainings': { name: 'certificates', label: 'Seminar Certificates' },
                'masteral_cert': { name: 'masteral_cert', label: 'Masteral Certificate' }
              };
              
              Object.keys(docMap).forEach(dbField => {
                if (data.draft[dbField]) {
                  const inputInfo = docMap[dbField];
                  const input = document.querySelector(`input[name="${inputInfo.name}"], input[name="${inputInfo.name}[]"]`);
                  if (input) {
                    const container = input.closest('.border-dashed');
                    if (container) {
                      // Get filename from draft
                      let filenames = data.draft[dbField];
                      if (dbField === 'seminars_trainings') {
                        filenames = filenames.split(',');
                      } else {
                        filenames = [filenames];
                      }
                      
                      // Hide original file input
                      input.style.display = 'none';
                      input.removeAttribute('required');
                      
                      // Create draft display with filename and remove button
                      const draftDisplay = document.createElement('div');
                      draftDisplay.className = 'draft-file-display';
                      draftDisplay.innerHTML = `
                        <div class="bg-green-50 border-2 border-green-400 rounded-lg p-3">
                          <div class="flex items-start gap-3">
                            <i class="ri-checkbox-circle-fill text-green-600 text-2xl"></i>
                            <div class="flex-1">
                              <div class="font-semibold text-green-900 text-sm">Saved Draft - Ready to Use</div>
                              ${filenames.map(filename => {
                                const displayName = filename.replace(/^draft_\d+_\d+_/, '');
                                return `<div class="text-sm text-green-700 mt-1 flex items-center gap-1">
                                  <i class="ri-file-text-fill"></i>
                                  <span>${displayName}</span>
                                </div>`;
                              }).join('')}
                              <div class="mt-2 text-xs text-green-600">
                                <i class="ri-information-line mr-1"></i>
                                This file will be used automatically, or click X to upload a new one
                              </div>
                            </div>
                            <button type="button" 
                                    class="remove-draft-btn text-red-500 hover:text-red-700 hover:bg-red-50 rounded p-1 transition-colors"
                                    data-field="${dbField}"
                                    title="Remove and upload new file">
                              <i class="ri-close-circle-fill text-2xl"></i>
                            </button>
                          </div>
                        </div>
                      `;
                      
                      // Insert draft display before the hidden input
                      container.insertBefore(draftDisplay, input);
                      
                      // Store draft info
                      input.setAttribute('data-draft-available', 'true');
                      input.setAttribute('data-draft-files', data.draft[dbField]);
                    }
                  }
                }
              });
              
              // Add event listeners for remove buttons
              document.querySelectorAll('.remove-draft-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                  const field = this.getAttribute('data-field');
                  const inputInfo = docMap[field];
                  const input = document.querySelector(`input[name="${inputInfo.name}"], input[name="${inputInfo.name}[]"]`);
                  const container = this.closest('.border-dashed');
                  
                  // Remove draft display
                  this.closest('.draft-file-display').remove();
                  
                  // Show file input again
                  if (input) {
                    input.style.display = 'block';
                    input.setAttribute('required', 'required');
                    input.removeAttribute('data-draft-available');
                    input.removeAttribute('data-draft-files');
                  }
                  
                  // Show notification
                  const notif = document.createElement('div');
                  notif.className = 'fixed top-4 right-4 bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded shadow-md animate-fade-in z-[10001]';
                  notif.innerHTML = `
                    <div class='flex items-center'>
                      <i class='ri-alert-line mr-2'></i>
                      <span>Draft removed. Please upload a new file.</span>
                    </div>
                  `;
                  document.body.appendChild(notif);
                  setTimeout(() => notif.remove(), 3000);
                });
              });
              
            } else {
              console.log('ℹ️ No saved draft found');
            }
          })
          .catch(error => {
            console.error('❌ Error loading draft:', error);
          });
      }
      
      // Restore file indicators if application data exists
      if (window.currentApplicationData) {
        console.log('Navigating to Step 2 - checking for file indicators');
        console.log('Current application data:', window.currentApplicationData);
        
        // Check if indicators already exist
        const existingIndicators = document.querySelectorAll('#step2 .approved-file');
        console.log('Existing indicators found:', existingIndicators.length);
        
        // Always re-add indicators to ensure they're visible
        setTimeout(() => {
          if (typeof window.addFileIndicatorsForApplication === 'function') {
            console.log('Re-applying file indicators...');
            window.addFileIndicatorsForApplication(window.currentApplicationData);
            
            // Ensure containers are visible even with view mode restrictions
            document.querySelectorAll('#step2 .border-dashed').forEach(container => {
              const indicator = container.querySelector('.approved-file');
              if (indicator) {
                // Override opacity for containers with approved files
                container.style.opacity = '1';
                container.parentElement.style.opacity = '1';
                console.log('Container opacity restored for approved file');
              }
            });
            
            console.log('File indicators restored for Step 2');
          } else {
            console.error('addFileIndicatorsForApplication function not found!');
          }
        }, 150);
      }
    }
    
    // When navigating to Step 3, check if interview next button should be enabled
    if (n === 3) {
      console.log('Navigating to Step 3 - checking button state');
      console.log('window.currentApplicationData:', window.currentApplicationData);
      
      if (!window.currentApplicationData) {
        console.warn('⚠️ No application data available for button state check');
      }
      
      const app = window.currentApplicationData;
      
      // Use setTimeout to ensure DOM is fully rendered
      setTimeout(() => {
        if (!app) {
          console.log('❌ No app data available, skipping button enablement');
          return;
        }
        // Display interview details if available
        if (app.interview_date) {
          const interviewDate = new Date(app.interview_date);
          const detailsHtml = `
            <p class="text-sm text-green-600 font-medium">✓ Interview scheduled</p>
            <p class="text-sm text-gray-700 mt-2">
              <i class="ri-calendar-event-line mr-1"></i>
              ${interviewDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
              at ${interviewDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
            </p>
            ${app.interview_notes ? `<p class="text-sm text-gray-600 mt-2">${app.interview_notes}</p>` : ''}
          `;
          const detailsElement = document.getElementById('interview_details');
          if (detailsElement) {
            detailsElement.innerHTML = detailsHtml;
            detailsElement.classList.remove('hidden');
          }
        }
        
        // Always ensure the back button on step 3 works
        const backBtn = document.querySelector('#step3_back_btn');
        if (backBtn) {
          backBtn.disabled = false;
          backBtn.removeAttribute('disabled');
          backBtn.style.pointerEvents = 'auto';
          backBtn.style.cursor = 'pointer';
          console.log('✅ Step 3 back button enabled');
        }
        
        // Enable next button if:
        // 1. Interview is APPROVED (status = "Interview Passed"), OR
        // 2. User has progressed past interview step (at demo or beyond)
        const hasProgressedPastInterview = window.currentWorkflowStep >= 4 || 
                                            app.demo_date || 
                                            app.psych_exam_receipt;
        const isInterviewApproved = app.status && app.status.toLowerCase().includes('interview passed');
        
        console.log('Checking interview step navigation:');
        console.log('- Interview date:', app.interview_date);
        console.log('- Status:', app.status);
        console.log('- Interview approved:', isInterviewApproved);
        console.log('- Current workflow step:', window.currentWorkflowStep);
        console.log('- Has progressed past interview:', hasProgressedPastInterview);
        
        if (isInterviewApproved || hasProgressedPastInterview) {
          console.log('✓ Interview APPROVED OR user has progressed - enabling navigation to demo step');
          const nextBtn = document.getElementById('interview_next_btn');
          if (nextBtn) {
            // Aggressively enable the button
            nextBtn.disabled = false;
            nextBtn.removeAttribute('disabled');
            nextBtn.className = 'px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors';
            nextBtn.title = 'Proceed to Demo Teaching';
            nextBtn.style.pointerEvents = 'auto';
            nextBtn.style.cursor = 'pointer';
            nextBtn.style.opacity = '1';
            
            // Force onclick attribute
            nextBtn.setAttribute('onclick', 'setStep(4)');
            
            // Add direct click event listener as backup (only if not already added)
            if (!nextBtn.hasAttribute('data-listener-added')) {
              nextBtn.addEventListener('click', function(e) {
                console.log('🖱️ Interview next button clicked!');
                e.preventDefault();
                e.stopPropagation();
                if (typeof setStep === 'function') {
                  console.log('Calling setStep(4)...');
                  setStep(4);
                } else {
                  console.error('setStep function not found!');
                }
              });
              nextBtn.setAttribute('data-listener-added', 'true');
            }
            
            console.log('✅ Interview next button enabled with click listener!');
            console.log('Button state:', {
              disabled: nextBtn.disabled,
              className: nextBtn.className,
              onclick: nextBtn.getAttribute('onclick')
            });
          } else {
            console.error('❌ interview_next_btn not found!');
          }
          
          // Show appropriate status message
          let statusHtml = '';
          if (hasProgressedPastInterview && !app.interview_date) {
            // User has progressed but interview wasn't formally scheduled (edge case)
            statusHtml = `
              <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-blue-700 font-medium flex items-center justify-center">
                  <i class="ri-check-double-line mr-2"></i>Interview completed - You can navigate forward to continue
                </p>
              </div>
            `;
          } else if (app.demo_date) {
            // Demo is scheduled - interview approved
            statusHtml = `
              <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                <p class="text-green-700 font-medium flex items-center justify-center">
                  <i class="ri-checkbox-circle-fill mr-2"></i>Interview Approved - Demo Teaching step available
                </p>
              </div>
            `;
          } else if (app.interview_date) {
            // Interview done, demo not scheduled yet
            statusHtml = `
              <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-blue-700 font-medium flex items-center justify-center">
                  <i class="ri-information-line mr-2"></i>Interview completed - Demo Teaching step accessible
                </p>
              </div>
            `;
          }
          
          const approvedElement = document.getElementById('interview_approved_status');
          if (approvedElement && statusHtml) {
            approvedElement.innerHTML = statusHtml;
            approvedElement.classList.remove('hidden');
          }
        } else if (app.interview_date && !isInterviewApproved) {
          // Interview is scheduled but not yet approved
          console.log('⏳ Interview scheduled but not approved - button remains disabled');
          const nextBtn = document.getElementById('interview_next_btn');
          if (nextBtn) {
            nextBtn.disabled = true;
            nextBtn.className = 'px-6 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed';
            nextBtn.title = 'Wait for admin to approve your interview';
          }
          
          // Show waiting message
          const statusHtml = `
            <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
              <p class="text-yellow-700 font-medium flex items-center justify-center">
                <i class="ri-time-line mr-2"></i>Interview scheduled - Waiting for admin approval
              </p>
              <p class="text-yellow-600 text-sm text-center mt-2">Please attend your interview. The Next button will be enabled after admin approval.</p>
            </div>
          `;
          const approvedElement = document.getElementById('interview_approved_status');
          if (approvedElement) {
            approvedElement.innerHTML = statusHtml;
            approvedElement.classList.remove('hidden');
          }
        } else {
          console.log('❌ No interview scheduled yet - button will remain disabled');
          console.log('Full app object:', app);
        }
      }, 100); // Small delay to ensure DOM is ready
    }
    
    // When navigating to Step 4, check if demo next button should be enabled
    if (n === 4 && window.currentApplicationData) {
      console.log('Navigating to Step 4 - checking button state');
      const app = window.currentApplicationData;
      
      // Use setTimeout to ensure DOM is fully rendered
      setTimeout(() => {
        // Display demo details if available, or show waiting message
        const demoStatusText = document.getElementById('demo_status_text');
        const detailsElement = document.getElementById('demo_details');
        
        if (app.demo_date) {
          const demoDate = new Date(app.demo_date);
          const detailsHtml = `
            <p class="text-sm text-green-600 font-medium">✓ Demo teaching scheduled</p>
            <p class="text-sm text-gray-700 mt-2">
              <i class="ri-calendar-event-line mr-1"></i>
              ${demoDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
              at ${demoDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
            </p>
            ${app.demo_notes ? `<p class="text-sm text-gray-600 mt-2">${app.demo_notes}</p>` : ''}
          `;
          if (detailsElement) {
            detailsElement.innerHTML = detailsHtml;
            detailsElement.classList.remove('hidden');
          }
          if (demoStatusText) {
            demoStatusText.textContent = 'Your demo teaching has been scheduled. Please attend at the scheduled time.';
          }
        } else {
          // Demo not scheduled yet, but interview is approved
          if (demoStatusText) {
            demoStatusText.textContent = 'Your interview has been completed. Please wait while the admin schedules your demo teaching session.';
          }
          if (detailsElement) {
            detailsElement.classList.add('hidden');
          }
        }
        
        // Always ensure the back button on step 4 works
        const backBtn = document.querySelector('#step4 button[onclick="setStep(3)"]');
        if (backBtn) {
          backBtn.disabled = false;
          backBtn.removeAttribute('disabled');
          backBtn.style.pointerEvents = 'auto';
          backBtn.style.cursor = 'pointer';
          console.log('✅ Step 4 back button enabled');
        }
        
        // Enable next button if:
        // 1. Demo has passed (status = "Demo Passed"), OR
        // 2. User has progressed past demo step (at psych exam or hired)
        const status = (app.status || '').toLowerCase();
        const hasProgressedPastDemo = window.currentWorkflowStep >= 5 || 
                                       app.psych_exam_receipt || 
                                       status.includes('initially hired') || 
                                       status.includes('hired') ||
                                       status.includes('psychological');
        
        console.log('Checking demo step navigation:');
        console.log('- Status:', app.status);
        console.log('- Current workflow step:', window.currentWorkflowStep);
        console.log('- Has psych receipt:', !!app.psych_exam_receipt);
        console.log('- Has progressed past demo:', hasProgressedPastDemo);
        
        if (status.includes('demo') && status.includes('passed') || hasProgressedPastDemo) {
          console.log('✅ Demo passed OR user has progressed - enabling next button');
          const nextBtn = document.getElementById('demo_next_btn');
          if (nextBtn) {
            // Aggressively enable the button
            nextBtn.disabled = false;
            nextBtn.removeAttribute('disabled');
            nextBtn.className = 'px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors';
            nextBtn.title = 'Proceed to Psychological Exam';
            nextBtn.style.pointerEvents = 'auto';
            nextBtn.style.cursor = 'pointer';
            nextBtn.style.opacity = '1';
            
            // Force onclick attribute
            nextBtn.setAttribute('onclick', 'setStep(5)');
            
            // Add direct click event listener as backup (only if not already added)
            if (!nextBtn.hasAttribute('data-listener-added')) {
              nextBtn.addEventListener('click', function(e) {
                console.log('🖱️ Demo next button clicked!');
                e.preventDefault();
                e.stopPropagation();
                if (typeof setStep === 'function') {
                  console.log('Calling setStep(5)...');
                  setStep(5);
                } else {
                  console.error('setStep function not found!');
                }
              });
              nextBtn.setAttribute('data-listener-added', 'true');
            }
            
            console.log('✅ Demo next button enabled with click listener!');
            console.log('Button state:', {
              disabled: nextBtn.disabled,
              className: nextBtn.className,
              onclick: nextBtn.getAttribute('onclick')
            });
          } else {
            console.error('❌ demo_next_btn not found!');
          }
          
          // Show approval status
          let approvedHtml = '';
          if (status.includes('demo') && status.includes('passed')) {
            // Demo was approved
            approvedHtml = `
              <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg">
                <p class="text-emerald-700 font-medium flex items-center justify-center">
                  <i class="ri-checkbox-circle-fill mr-2"></i>Demo Teaching Approved! You can now proceed to the Psychological Examination
                </p>
              </div>
            `;
          } else if (hasProgressedPastDemo) {
            // User has already progressed past this step
            approvedHtml = `
              <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-blue-700 font-medium flex items-center justify-center">
                  <i class="ri-check-double-line mr-2"></i>Demo Teaching completed - You can navigate forward to continue
                </p>
              </div>
            `;
          }
          
          const approvedElement = document.getElementById('demo_approved_status');
          if (approvedElement && approvedHtml) {
            approvedElement.innerHTML = approvedHtml;
            approvedElement.classList.remove('hidden');
          }
        } else if (app.demo_date) {
          // Demo scheduled but not yet approved
          console.log('⏳ Demo scheduled but not approved - button remains disabled');
          const nextBtn = document.getElementById('demo_next_btn');
          if (nextBtn) {
            nextBtn.disabled = true;
            nextBtn.className = 'px-6 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed';
            nextBtn.title = 'Wait for admin to approve your demo teaching';
          }
          
          const waitingHtml = `
            <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
              <p class="text-yellow-700 font-medium flex items-center justify-center">
                <i class="ri-time-line mr-2"></i>Demo Teaching scheduled - Waiting for admin approval
              </p>
              <p class="text-yellow-600 text-sm text-center mt-2">Please attend your demo teaching. The Next button will be enabled after admin approval.</p>
            </div>
          `;
          const approvedElement = document.getElementById('demo_approved_status');
          if (approvedElement) {
            approvedElement.innerHTML = waitingHtml;
            approvedElement.classList.remove('hidden');
          }
        } else {
          console.log('❌ No demo scheduled yet - button will remain disabled');
        }
      }, 100); // Small delay to ensure DOM is ready
    }
    
    // When navigating to Step 5, check if psych next button should be enabled
    if (n === 5 && window.currentApplicationData) {
      console.log('Navigating to Step 5 - checking button state');
      const app = window.currentApplicationData;
      
      // Use setTimeout to ensure DOM is fully rendered
      setTimeout(() => {
        // Only enable next button if admin has marked as "Initially Hired"
        // Just uploading psych exam receipt should NOT allow access to step 6
        const status = (app.status || '').toLowerCase();
        console.log('Checking psych step - Status:', app.status);
        console.log('Has psych_exam_receipt:', !!app.psych_exam_receipt);
        
        if (status.includes('initially hired') || status.includes('hired')) {
          console.log('✅✅✅ Admin has marked as Initially Hired - ENABLING BUTTON!');
          const nextBtn = document.getElementById('psych_next_btn');
          if (nextBtn) {
            // Aggressively enable the button
            nextBtn.disabled = false;
            nextBtn.removeAttribute('disabled');
            nextBtn.className = 'px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors';
            nextBtn.title = 'Click to View Initial Hiring Status';
            nextBtn.style.pointerEvents = 'auto';
            nextBtn.style.cursor = 'pointer';
            nextBtn.style.opacity = '1';
            
            // Force onclick attribute
            nextBtn.setAttribute('onclick', 'setStep(6)');
            
            // Add direct click event listener as backup (only if not already added)
            if (!nextBtn.hasAttribute('data-listener-added')) {
              nextBtn.addEventListener('click', function(e) {
                console.log('🖱️ Hired button clicked! Going to step 6...');
                e.preventDefault();
                e.stopPropagation();
                if (typeof setStep === 'function') {
                  console.log('Calling setStep(6)...');
                  setStep(6);
                } else {
                  console.error('setStep function not found!');
                }
              });
              nextBtn.setAttribute('data-listener-added', 'true');
            }
            
            console.log('🎉🎉🎉 BUTTON IS NOW CLICKABLE! You can view the hiring status!');
            console.log('Button state:', {
              disabled: nextBtn.disabled,
              className: nextBtn.className,
              onclick: nextBtn.getAttribute('onclick'),
              backgroundColor: nextBtn.style.backgroundColor
            });
          } else {
            console.error('❌ psych_next_btn not found!');
          }
        } else {
          console.log('❌ Not hired yet - button remains disabled');
          console.log('User has uploaded psych receipt but admin has not approved/hired yet');
          
          // Show appropriate status message
          if (app.psych_exam_receipt) {
            // Receipt uploaded, waiting for admin to hire
            const waitingHtml = `
              <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p class="text-yellow-700 font-medium flex items-center justify-center">
                  <i class="ri-time-line mr-2"></i>Receipt submitted - Waiting for admin to mark you as hired
                </p>
                <p class="text-yellow-600 text-sm text-center mt-2">The button below will turn green once the admin marks you as "Initially Hired".</p>
              </div>
            `;
            const approvedElement = document.getElementById('psych_approved_status');
            if (approvedElement) {
              approvedElement.innerHTML = waitingHtml;
              approvedElement.classList.remove('hidden');
            }
          } else {
            // No receipt uploaded yet
            const uploadReminderHtml = `
              <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-blue-700 font-medium flex items-center justify-center">
                  <i class="ri-information-line mr-2"></i>Please upload your psychological exam receipt above
                </p>
              </div>
            `;
            const approvedElement = document.getElementById('psych_approved_status');
            if (approvedElement) {
              approvedElement.innerHTML = uploadReminderHtml;
              approvedElement.classList.remove('hidden');
            }
          }
        }
      }, 100); // Small delay to ensure DOM is ready
    }
    
    // Show upload form in Step 5 by default
    if (n === 5) {
      const uploadForm = document.getElementById('psych_upload_form');
      const uploadSuccess = document.getElementById('psych_upload_success');
      const approvedStatus = document.getElementById('psych_approved_status');
      const appIdField = document.getElementById('wizard_psych_app_id');
      
      // Ensure application ID is set
      if (appIdField && window.currentApplicationId) {
        appIdField.value = window.currentApplicationId;
        console.log('Step 5: Application ID set to', window.currentApplicationId);
      } else {
        console.warn('Step 5: No application ID available!');
      }
      
      // Check if user already uploaded receipt
      if (uploadSuccess && !uploadSuccess.classList.contains('hidden')) {
        // Receipt already uploaded, keep success message visible
      } else {
        // Show upload form for new users
        if (uploadForm) uploadForm.classList.remove('hidden');
      }
      
      // Hide any old status messages
      if (approvedStatus) approvedStatus.classList.add('hidden');
    }
    
    // Verify what's visible
    setTimeout(() => {
      steps.forEach((el, idx) => {
        if (el) {
          const computed = window.getComputedStyle(el);
          console.log(`Step ${idx + 1} final state:`, {
            display: computed.display,
            visibility: computed.visibility,
            opacity: computed.opacity
          });
        }
      });
    }, 50);
    
    // Force a reflow to ensure styles are applied
    void document.body.offsetHeight;
    
    // Update step dots based on actual workflow progress, not viewing step
    // Use window.currentWorkflowStep if available, otherwise use viewing step
    const progressStep = window.currentWorkflowStep || n;
    console.log('Updating dots - Viewing step:', n, 'Progress step:', progressStep);
    
    stepDots.forEach(dot => {
      const s = Number(dot.getAttribute('data-step'));
      if (s < progressStep) {
        // Completed steps - green
        dot.style.background = '#10b981';
        dot.style.color = '#fff';
      } else if (s === progressStep) {
        // Current progress step - yellow (stays here even when viewing other steps)
        dot.style.background = '#f59e0b';
        dot.style.color = '#1e40af';
      } else {
        // Future steps - gray
        dot.style.background = 'rgba(255,255,255,0.3)';
        dot.style.color = '#fff';
      }
    });
    
    // Update lines between dots based on progress, not viewing step
    document.querySelectorAll('.step-line').forEach((line, idx) => {
      const lineAfterStep = idx + 1;
      if (lineAfterStep < progressStep) {
        line.style.backgroundColor = '#10b981';
      } else {
        line.style.backgroundColor = 'rgba(255, 255, 255, 0.3)';
      }
    });
    
    // Update step label to show actual progress, not viewing step
    const labels = {
      1: 'Step 1 of 6: Personal Information, Work Experience, Education & Skills',
      2: 'Step 2 of 6: Submit Requirements',
      3: 'Step 3 of 6: Interview Scheduled',
      4: 'Step 4 of 6: Demo Teaching Scheduled',
      5: 'Step 5 of 6: Psychological Examination',
      6: 'Step 6 of 6: Initially Hired'
    };
    if (stepLabel) {
      // Show progress step label, not viewing step
      const labelToShow = labels[progressStep] || labels[n];
      stepLabel.textContent = labelToShow;
      console.log('Step label updated to:', labelToShow, '(Progress:', progressStep, 'Viewing:', n, ')');
    } else {
      console.error('stepLabel element not found!');
    }
  }

  // Public API: View existing application in wizard
  window.viewExistingApplication = async function(applicationId) {
    console.log('📖 viewExistingApplication called with ID:', applicationId);
    
    // Mark that wizard was opened from My Applications (for close button behavior)
    window.wizardOpenedFromMyApplications = true;
    console.log('✅ Marked wizard as opened from My Applications');
    
    // Check if wizard element exists on this page
    const wizardElement = document.getElementById('applicationWizard');
    const mainContent = document.getElementById('mainContent');
    
    if (!wizardElement) {
      console.log('⚠️ Wizard not visible. Switching to dashboard view...');
      
      // Check if we're in My Applications view (mainContent has been replaced)
      if (mainContent && mainContent.innerHTML.includes('user_application')) {
        console.log('🔄 Switching from My Applications to Dashboard...');
        
        // Show loading overlay
        const loadingOverlay = document.createElement('div');
        loadingOverlay.id = 'switchingOverlay';
        loadingOverlay.style.cssText = `
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(255, 255, 255, 0.95);
          z-index: 9999;
          display: flex;
          align-items: center;
          justify-content: center;
          flex-direction: column;
        `;
        loadingOverlay.innerHTML = `
          <i class="ri-loader-4-line text-6xl text-blue-600 animate-spin mb-4"></i>
          <p class="text-gray-700 text-lg">Opening application wizard...</p>
        `;
        document.body.appendChild(loadingOverlay);
        
        // Store the application ID and switch to dashboard
        sessionStorage.setItem('openApplicationId', applicationId);
        sessionStorage.setItem('returnToMyApplications', 'true');
        
        // CRITICAL: Force dashboard view by clearing localStorage navigation state
        // This prevents page state persistence from restoring My Applications view
        localStorage.setItem('activeNavSection', 'dashboard');
        console.log('🔄 Forced activeNavSection to dashboard');
        
        // Navigate to dashboard by reloading the page
        setTimeout(() => {
          location.reload();
        }, 100);
        return;
      }
      
      console.log('⚠️ Wizard element not found even on dashboard. This should not happen.');
      alert('Unable to open application wizard. Please refresh the page and try again.');
      return;
    }
    
    console.log('✅ Wizard element found on page, proceeding to open...');
    
    try {
      console.log('🌐 Fetching application details from API...');
      const res = await fetch(`get_application_details.php?id=${applicationId}`);
      console.log('📡 API Response status:', res.status, res.statusText);
      
      const data = await res.json();
      console.log('📦 API Response data:', data);
      console.log('📦 Work Experience from API:', data.work_experience);
      console.log('📦 Education from API:', data.education);
      console.log('📦 Skills from API:', data.skills);
      
      if (!data.success) {
        console.error('❌ API returned error:', data.error);
        throw new Error(data.error || 'Failed to load application');
      }
      
      const app = data.application;
      console.log('✅ Application loaded successfully:', app);
      
      // Determine current workflow step
      let workflowStep = 3;
      const status = (app.status || '').toLowerCase();
      
      if (status.includes('initially hired') || status.includes('hired')) {
        workflowStep = 6;
      } else if (app.psych_exam_receipt) {
        workflowStep = 5;
      } else if (status.includes('demo') && status.includes('passed')) {
        workflowStep = 5;
      } else if (status.includes('demo') || app.demo_date) {
        workflowStep = 4;
      } else if (status.includes('interview') && status.includes('passed')) {
        workflowStep = 4;
      } else if (status.includes('interview') || app.interview_date) {
        workflowStep = 3;
      }
      
      console.log('Opening wizard at step:', workflowStep);
      
      // Set globals
      window.selectedJobId = app.job_id;
      window.selectedJobTitle = app.position;
      window.currentApplicationId = applicationId;
      window.currentApplicationData = app;
      window.currentWorkflowStep = workflowStep;
      
      // Populate wizard with data
      if (app.interview_date && workflowStep >= 3) {
        const interviewDate = new Date(app.interview_date);
        const detailsHtml = `
          <p class="text-sm text-green-600 font-medium">✓ Interview scheduled</p>
          <p class="text-sm text-gray-700 mt-2">
            <i class="ri-calendar-event-line mr-1"></i>
            ${interviewDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
            at ${interviewDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
          </p>
          ${app.interview_notes ? `<p class="text-sm text-gray-600 mt-2">${app.interview_notes}</p>` : ''}
        `;
        const detailsElement = document.getElementById('interview_details');
        if (detailsElement) {
          detailsElement.innerHTML = detailsHtml;
          detailsElement.classList.remove('hidden');
        }
      }
      
      if (app.demo_date && workflowStep >= 4) {
        const demoDate = new Date(app.demo_date);
        const detailsHtml = `
          <p class="text-sm text-green-600 font-medium">✓ Demo teaching scheduled</p>
          <p class="text-sm text-gray-700 mt-2">
            <i class="ri-calendar-event-line mr-1"></i>
            ${demoDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
            at ${demoDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
          </p>
          ${app.demo_notes ? `<p class="text-sm text-gray-600 mt-2">${app.demo_notes}</p>` : ''}
        `;
        const detailsElement = document.getElementById('demo_details');
        if (detailsElement) {
          detailsElement.innerHTML = detailsHtml;
          detailsElement.classList.remove('hidden');
        }
      }
      
      // Show psych exam receipt if uploaded
      if (app.psych_exam_receipt && workflowStep >= 5) {
        const psychUploadSuccess = document.getElementById('psych_upload_success');
        const psychUploadForm = document.getElementById('psych_upload_form');
        if (psychUploadSuccess) psychUploadSuccess.classList.remove('hidden');
        if (psychUploadForm) psychUploadForm.classList.add('hidden');
        
        // Show hired status if applicable
        if (status.includes('initially hired') || status.includes('hired')) {
          const hiredHtml = `
            <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
              <p class="text-green-700 font-medium flex items-center justify-center">
                <i class="ri-checkbox-circle-fill mr-2"></i>Congratulations! You have been hired!
              </p>
              <p class="text-green-600 text-sm text-center mt-2">Click the green "Next: Initially Hired" button below to view your hiring details.</p>
            </div>
          `;
          const approvedElement = document.getElementById('psych_approved_status');
          if (approvedElement) {
            approvedElement.innerHTML = hiredHtml;
            approvedElement.classList.remove('hidden');
          }
        }
      }
      
      // Show hired details if at step 6
      if (app.initially_hired_date && workflowStep >= 6) {
        const hiredDate = new Date(app.initially_hired_date);
        const detailsHtml = `
          <p class="text-sm text-gray-700">Date: ${hiredDate.toLocaleDateString()}</p>
          ${app.initially_hired_notes ? `<p class="text-sm text-gray-600 mt-2">${app.initially_hired_notes}</p>` : ''}
        `;
        const hiredDetailsElement = document.getElementById('hired_details');
        if (hiredDetailsElement) {
          hiredDetailsElement.innerHTML = detailsHtml;
          hiredDetailsElement.classList.remove('hidden');
        }
      }
      
      // ✅ POPULATE STEP 1 (Personal Information) with application data
      console.log('📝 Populating Step 1 with application data...');
      const pf_first = document.getElementById('pf_first_name');
      const pf_last = document.getElementById('pf_last_name');
      const pf_email = document.getElementById('pf_email');
      const pf_phone = document.getElementById('pf_phone');
      const pf_address = document.getElementById('pf_address');
      
      if (pf_first) pf_first.value = app.first_name || '';
      if (pf_last) pf_last.value = app.last_name || '';
      if (pf_email) pf_email.value = app.applicant_email || '';
      if (pf_phone) pf_phone.value = app.contact_num || '';
      if (pf_address) pf_address.value = app.address || '';
      
      console.log('✅ Step 1 personal info populated:', {
        first_name: app.first_name,
        last_name: app.last_name,
        email: app.applicant_email,
        phone: app.contact_num,
        address: app.address
      });
      
      // Show wizard in view mode FIRST
      showWizard(true);
      setStep(workflowStep);
      
      // THEN display work experience, skills, and education after wizard is visible
      // Use setTimeout to ensure DOM is ready
      setTimeout(() => {
        // Display data from API response
        console.log('📝 Displaying application data from API...');
        displayWorkExperienceFromData(data.work_experience || []);
        displaySkillsFromData(data.skills || []);
        displayEducationFromData(data.education || []);
      }, 300);
      
      // ✅ POPULATE STEP 2 form fields (for resubmission or viewing)
      console.log('📝 Populating Step 2 with job info...');
      const rf_job_id = document.getElementById('rf_job_id');
      const rf_job_title = document.getElementById('rf_job_title');
      const rf_full_name = document.getElementById('rf_full_name');
      const rf_email = document.getElementById('rf_email');
      const rf_cellphone = document.getElementById('rf_cellphone');
      
      if (rf_job_id) rf_job_id.value = app.job_id || '';
      if (rf_job_title) rf_job_title.value = app.position || '';
      if (rf_full_name) rf_full_name.value = app.full_name || '';
      if (rf_email) rf_email.value = app.applicant_email || '';
      if (rf_cellphone) rf_cellphone.value = app.contact_num || '';
      
      console.log('✅ Step 2 job info populated');
      
      // Add file indicators for uploaded documents
      setTimeout(() => {
        if (typeof window.addFileIndicatorsForApplication === 'function') {
          window.addFileIndicatorsForApplication(app);
          console.log('✅ File indicators added');
        }
      }, 400);
      
      // Clear retry counter on successful open
      sessionStorage.removeItem('wizardOpenRetry');
      
      console.log('✅ Wizard opened successfully at step', workflowStep);
    } catch (error) {
      console.error('💥 Error loading application:', error);
      console.error('Error details:', {
        name: error.name,
        message: error.message,
        stack: error.stack
      });
      
      // Show user-friendly error
      let errorMsg = 'Failed to load application details.\n\n';
      if (error.message.includes('not found') || error.message.includes('access denied')) {
        errorMsg += 'This application may have been deleted or you do not have permission to view it.';
      } else if (error.message.includes('Network')) {
        errorMsg += 'Network error. Please check your internet connection.';
      } else {
        errorMsg += error.message;
      }
      
      alert(errorMsg);
      
      // Clear retry counter on error
      sessionStorage.removeItem('wizardOpenRetry');
    }
  };
  
  // Make showWizard globally accessible
  window.showWizard = function(viewMode = false) {
    console.log('showWizard() called with viewMode:', viewMode);
    isViewMode = viewMode;
    
    console.log('Elements check:');
    console.log('- wizard:', wizard);
    console.log('- wizard initial classes:', wizard ? wizard.className : 'null');
    
    if (!wizard) {
      console.error('Wizard element is null!');
      alert('Wizard element not found! Please refresh the page.');
      return;
    }
    
    // Show wizard with multiple methods to ensure visibility
    console.log('Making wizard visible...');
    wizard.classList.remove('hidden');
    wizard.style.display = 'block !important';
    wizard.style.visibility = 'visible';
    wizard.style.opacity = '1';
    wizard.style.zIndex = '9999';
    
    console.log('Wizard classes after changes:', wizard.className);
    console.log('Wizard inline styles:', wizard.style.cssText);
    
    // Don't hide main content since wizard is positioned independently with fixed positioning
    // The wizard should overlay everything with its z-index
    
    // Hide other elements that might interfere (but keep main content visible)
    if (jobHeader) jobHeader.style.display = 'none';
    if (searchFilters) searchFilters.style.display = 'none';
    if (listings) listings.style.display = 'none';
    if (detailView) detailView.style.display = 'none';
    if (pagination) pagination.style.display = 'none';
    
    // Ensure wizard body is visible
    const wizardBody = wizard.querySelector('.p-6');
    if (wizardBody) {
      console.log('Making wizard body visible...');
      wizardBody.style.display = 'block !important';
      wizardBody.style.visibility = 'visible !important';
      wizardBody.style.opacity = '1';
    } else {
      console.error('Wizard body (.p-6) not found!');
    }
    
    // Set body overflow
    document.body.style.overflow = 'hidden';
    
    // Note: Don't automatically set to step 1 - let the caller decide which step to show
    
    // Final verification
    setTimeout(() => {
      console.log('=== FINAL WIZARD VERIFICATION ===');
      console.log('Wizard display:', window.getComputedStyle(wizard).display);
      console.log('Wizard visibility:', window.getComputedStyle(wizard).visibility);
      console.log('Wizard opacity:', window.getComputedStyle(wizard).opacity);
      console.log('Wizard z-index:', window.getComputedStyle(wizard).zIndex);
      
      const step1Element = document.getElementById('step1');
      if (step1Element) {
        console.log('Step1 display:', window.getComputedStyle(step1Element).display);
        console.log('Step1 visibility:', window.getComputedStyle(step1Element).visibility);
        
        // Force step1 visibility as final backup
        step1Element.style.display = 'block !important';
        step1Element.style.visibility = 'visible !important';
        step1Element.classList.remove('hidden');
      }
      
      // If still not visible, show alert
      const wizardRect = wizard.getBoundingClientRect();
      console.log('Wizard bounding rect:', wizardRect);
      
      if (wizardRect.width === 0 || wizardRect.height === 0) {
        console.error('⚠️ Wizard has zero dimensions!');
        console.log('Wizard parent:', wizard.parentElement);
        console.log('Wizard offsetParent:', wizard.offsetParent);
        console.log('Document body contains wizard:', document.body.contains(wizard));
        
        // Try to fix by forcing dimensions
        wizard.style.width = '100%';
        wizard.style.height = '100vh';
        wizard.style.position = 'fixed';
        wizard.style.top = '0';
        wizard.style.left = '0';
        console.log('Applied fixed positioning to wizard');
        
        // Check again
        const newRect = wizard.getBoundingClientRect();
        if (newRect.width === 0 || newRect.height === 0) {
          console.error('❌ Still has zero dimensions after fix attempt');
          alert('Wizard is not displaying properly. The wizard element may not be available on this page. Please go to the Jobs page to view your application.');
        } else {
          console.log('✅ Fixed! New dimensions:', newRect.width, 'x', newRect.height);
        }
      }
    }, 100);
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    // Apply view mode settings if needed
    if (isViewMode) {
      applyViewModeRestrictions();
    } else {
      removeViewModeRestrictions();
    }
    
    console.log('showWizard() completed');
  }

  // Make hideWizard globally accessible
  window.hideWizard = function() {
    wizard.classList.add('hidden');
    wizard.style.display = 'none';
    
    // Reset ALL indicator flags when closing wizard
    window._lastLoadedAppId = null;
    window._isProcessingIndicators = false;
    
    // CRITICAL FIX: Clean up all file upload containers in Step 2 to prevent duplication
    const step2 = document.getElementById('step2');
    if (step2) {
      console.log('🧹 Cleaning up Step 2 file containers...');
      const allInputs = step2.querySelectorAll('input[type="file"]');
      
      allInputs.forEach((input) => {
        const container = input.closest('.border-dashed');
        if (!container) return;
        
        // Save original input attributes
        const inputName = input.name;
        const inputAccept = input.accept;
        const inputRequired = input.required;
        
        // Remove all child elements (including indicators)
        container.innerHTML = '';
        
        // Recreate clean input element
        const newInput = document.createElement('input');
        newInput.type = 'file';
        newInput.name = inputName;
        newInput.accept = inputAccept;
        newInput.required = inputRequired;
        newInput.className = 'w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100';
        
        container.appendChild(newInput);
        
        // Reset container to default state
        container.className = 'border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-blue-400 transition-colors';
        container.removeAttribute('style');
        
        // Reset parent styling
        if (container.parentElement) {
          container.parentElement.removeAttribute('style');
        }
      });
      
      console.log('✅ Step 2 cleaned up successfully');
    }
    
    // Restore other elements
    if (jobHeader) jobHeader.style.display = 'block';
    if (searchFilters) searchFilters.style.display = 'block';
    if (listings) listings.style.display = 'block';
    if (pagination) pagination.style.display = 'block';
    
    // Restore main content container
    if (mainContent) mainContent.style.display = 'block';
    document.body.style.overflow = 'auto';
  }

  // Apply view mode restrictions (make everything readonly)
  function applyViewModeRestrictions() {
    console.log('Applying view mode restrictions...');
    
    // Make all form inputs readonly/disabled
    const wizard = document.getElementById('applicationWizard');
    if (!wizard) return;
    
    // Disable text inputs, textareas, and selects ONLY in steps 1 and 2
    // Allow inputs in workflow steps (3-6) where user might still need to interact
    const wizStep1 = wizard.querySelector('#step1');
    const wizStep2 = wizard.querySelector('#step2');
    
    if (wizStep1) {
      wizStep1.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="date"], textarea, select').forEach(input => {
        input.setAttribute('readonly', 'readonly');
        input.style.backgroundColor = '#f3f4f6';
        input.style.cursor = 'not-allowed';
      });
    }
    
    if (wizStep2) {
      wizStep2.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="date"], textarea, select').forEach(input => {
        input.setAttribute('readonly', 'readonly');
        input.style.backgroundColor = '#f3f4f6';
        input.style.cursor = 'not-allowed';
      });
      
      // Disable file inputs in step 2 (already submitted requirements)
      // EXCEPT for documents that need resubmission
      wizStep2.querySelectorAll('input[type="file"]').forEach(input => {
        // Check if this input is in the resubmission list
        const inputName = input.getAttribute('name');
        const docInputMap = {
          'applicationLetter': 'application_letter',
          'resume_file': 'resume',
          'transcript': 'tor',
          'diploma': 'diploma',
          'license': 'professional_license',
          'coe': 'coe',
          'certificates[]': 'seminars_trainings',
          'masteral_cert': 'masteral_cert'
        };
        
        const documentField = docInputMap[inputName];
        const needsResubmission = globalResubmissionDocs.includes(documentField);
        
        if (!needsResubmission) {
          // Only disable if NOT in resubmission list
          input.setAttribute('disabled', 'disabled');
          input.style.opacity = '0.5';
          input.style.cursor = 'not-allowed';
          const parent = input.parentElement;
          if (parent) {
            parent.style.opacity = '0.5';
            parent.style.cursor = 'not-allowed';
          }
        } else {
          console.log(`Skipping disable for ${inputName} - needs resubmission`);
        }
      });
      
      // Disable checkboxes in step 2
      wizStep2.querySelectorAll('input[type="checkbox"]').forEach(input => {
        input.setAttribute('disabled', 'disabled');
        input.style.cursor = 'not-allowed';
      });
    }
    
    // Hide submit buttons ONLY in steps 1 and 2 (personal info and requirements submission)
    // But keep navigation buttons (Previous/Next) visible
    if (wizStep1) {
      wizStep1.querySelectorAll('button[type="submit"]').forEach(btn => {
        btn.style.display = 'none';
      });
    }
    if (wizStep2) {
      wizStep2.querySelectorAll('button[type="submit"]').forEach(btn => {
        btn.style.display = 'none';
      });
      
      // Show the Next button in step 2 for view mode
      const step2NextBtn = document.getElementById('step2NextBtn');
      if (step2NextBtn) {
        step2NextBtn.classList.remove('hidden');
        step2NextBtn.style.display = 'flex';
      }
    }
    
    // Ensure navigation buttons remain visible
    document.querySelectorAll('#step1 button[onclick*="setStep"], #step2 button[onclick*="setStep"]').forEach(btn => {
      btn.style.display = ''; // Keep navigation buttons visible
    });
    
    // Step 3 back button already configured to go to step 2
    // No changes needed in view mode
    
    console.log('View mode restrictions applied');
  }
  
  // Remove view mode restrictions (restore edit mode)
  function removeViewModeRestrictions() {
    console.log('Removing view mode restrictions...');
    
    const wizard = document.getElementById('applicationWizard');
    if (!wizard) return;
    
    // Re-enable all inputs
    wizard.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="date"], textarea, select').forEach(input => {
      input.removeAttribute('readonly');
      input.style.backgroundColor = '';
      input.style.cursor = '';
    });
    
    // Re-enable file inputs and remove upload indicators
    wizard.querySelectorAll('input[type="file"]').forEach(input => {
      input.removeAttribute('disabled');
      input.style.opacity = '';
      input.style.cursor = '';
      input.style.display = ''; // Make sure input is visible
      input.value = ''; // Clear any selected files
      const parent = input.parentElement;
      if (parent) {
        parent.style.opacity = '';
        parent.style.cursor = '';
      }
      
      // Reset container styling
      const container = input.closest('.border-dashed');
      if (container) {
        container.classList.remove('border-green-400', 'border-green-500', 'bg-green-50');
        container.classList.add('border-gray-300', 'hover:border-blue-400');
        container.style.backgroundColor = '';
      }
    });
    
    // Remove all uploaded file indicators (green boxes showing "Uploaded: filename")
    wizard.querySelectorAll('.border-dashed .bg-green-50').forEach(indicator => {
      indicator.remove();
    });
    
    // Hide all file upload indicators
    wizard.querySelectorAll('.file-upload-indicator').forEach(indicator => {
      indicator.classList.add('hidden');
    });
    
    // Re-enable checkboxes
    wizard.querySelectorAll('input[type="checkbox"]').forEach(input => {
      input.removeAttribute('disabled');
      input.style.cursor = '';
    });
    
    // Restore submit buttons
    wizard.querySelectorAll('button[type="submit"]').forEach(btn => {
      btn.style.display = '';
    });
    
    // Hide the Next button in step 2 (restore to edit mode)
    const step2NextBtn = document.getElementById('step2NextBtn');
    if (step2NextBtn) {
      step2NextBtn.classList.add('hidden');
      step2NextBtn.style.display = 'none';
    }
    
    // Step 3 back button remains as "Back" for navigation
    // No need to change it in edit mode
    
    console.log('View mode restrictions removed');
  }

  function prefillPersonal() {
    // Prefill personal information
    pf_first.value = initialProfile.first_name || '';
    pf_last.value = initialProfile.last_name || '';
    pf_email.value = initialProfile.email || '';
    pf_phone.value = initialProfile.phone || '';
    pf_address.value = initialProfile.address || '';
    
    // Populate hidden work experience fields for form submission (use first/most recent experience)
    const firstExp = initialWorkExperiences && initialWorkExperiences.length > 0 ? initialWorkExperiences[0] : {};
    wx_job.value = firstExp.job_title || '';
    wx_comp.value = firstExp.company || '';
    wx_loc.value = firstExp.location || '';
    
    // Format dates for month input (YYYY-MM)
    if (firstExp.start_date) {
      const startDate = new Date(firstExp.start_date);
      wx_start.value = startDate.getFullYear() + '-' + String(startDate.getMonth() + 1).padStart(2, '0');
    }
    
    if (firstExp.end_date && !firstExp.is_current) {
      const endDate = new Date(firstExp.end_date);
      wx_end.value = endDate.getFullYear() + '-' + String(endDate.getMonth() + 1).padStart(2, '0');
    }
    
    wx_cur.value = firstExp.is_current ? '1' : '';
    wx_desc.value = firstExp.description || '';
    
    // Display work experience in a box
    try {
      displayWorkExperience();
    } catch (e) {
      console.error('Error displaying work experience:', e);
    }
    
    // Populate hidden skills field
    const skillsField = document.getElementById('wx_skills');
    if (skillsField) {
      skillsField.value = initialSkills || '';
    }
    
    // Display skills as tags
    try {
      displaySkills();
    } catch (e) {
      console.error('Error displaying skills:', e);
    }
    
    // Display education
    try {
      displayEducation();
    } catch (e) {
      console.error('Error displaying education:', e);
    }
    
    console.log('Pre-filled all wizard fields with user profile data');
  }

  // Define FromData functions BEFORE they're used (for viewing applications)
  // Make them globally accessible
  window.displayWorkExperienceFromData = function(workExperienceArray) {
    const container = document.getElementById('workExperienceDisplay');
    const countBadge = document.getElementById('workExpCount');
    
    console.log('📝 displayWorkExperienceFromData called with:', workExperienceArray);
    
    if (!workExperienceArray || workExperienceArray.length === 0) {
      container.innerHTML = `
        <div class="text-center py-6 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
          <i class="ri-briefcase-line text-3xl text-gray-400 mb-2"></i>
          <p class="text-sm text-gray-500">No work experience found</p>
        </div>
      `;
      if (countBadge) countBadge.textContent = '0';
      return;
    }
    
    const experiencesHTML = workExperienceArray.map(exp => {
      let dateRange = '';
      if (exp.start_date) {
        const startDate = new Date(exp.start_date);
        const startStr = startDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
        
        if (exp.is_current) {
          dateRange = startStr + ' - Present';
        } else if (exp.end_date) {
          const endDate = new Date(exp.end_date);
          const endStr = endDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
          dateRange = startStr + ' - ' + endStr;
        } else {
          dateRange = startStr;
        }
      }
      
      return `
        <div class="border border-blue-200 bg-blue-50 rounded-lg p-4">
          <div class="flex items-start justify-between mb-2">
            <div class="flex-1">
              <h4 class="font-semibold text-gray-900 text-base">${exp.job_title}</h4>
              <p class="text-sm text-gray-700 font-medium">${exp.company}</p>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
              <i class="ri-briefcase-line mr-1"></i>Experience
            </span>
          </div>
          ${exp.location ? `
            <div class="flex items-center text-sm text-gray-600 mb-1">
              <i class="ri-map-pin-line mr-1"></i>${exp.location}
            </div>
          ` : ''}
          <div class="flex items-center text-sm text-gray-600 mb-2">
            <i class="ri-calendar-line mr-1"></i>${dateRange}
          </div>
          ${exp.description ? `
            <div class="mt-3 pt-3 border-t border-blue-200">
              <p class="text-sm text-gray-700 whitespace-pre-wrap">${exp.description}</p>
            </div>
          ` : ''}
        </div>
      `;
    }).join('');
    
    container.innerHTML = experiencesHTML;
    if (countBadge) countBadge.textContent = workExperienceArray.length.toString();
    console.log('✅ Work experience displayed:', workExperienceArray.length, 'items');
  }

  window.displaySkillsFromData = function(skillsArray) {
    const container = document.getElementById('skillsDisplay');
    const countBadge = document.getElementById('skillsCount');
    
    console.log('📝 displaySkillsFromData called with:', skillsArray);
    
    if (!skillsArray || skillsArray.length === 0) {
      container.innerHTML = `
        <div class="w-full text-center py-6 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
          <i class="ri-lightbulb-line text-3xl text-gray-400 mb-2"></i>
          <p class="text-sm text-gray-500">No skills found</p>
        </div>
      `;
      if (countBadge) countBadge.textContent = '0';
      return;
    }
    
    const skillTags = skillsArray.map(skill => `
      <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-green-100 text-green-800 border border-green-200">
        <i class="ri-check-line mr-1 text-green-600"></i>${skill}
      </span>
    `).join('');
    
    container.innerHTML = skillTags;
    if (countBadge) countBadge.textContent = skillsArray.length.toString();
    console.log('✅ Skills displayed:', skillsArray.length, 'items');
  }

  window.displayEducationFromData = function(educationArray) {
    const container = document.getElementById('educationDisplay');
    const countBadge = document.getElementById('educationCount');
    
    console.log('📝 displayEducationFromData called with:', educationArray);
    
    if (!educationArray || educationArray.length === 0) {
      container.innerHTML = `
        <div class="text-center py-6 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
          <i class="ri-graduation-cap-line text-3xl text-gray-400 mb-2"></i>
          <p class="text-sm text-gray-500">No education found</p>
        </div>
      `;
      if (countBadge) countBadge.textContent = '0';
      return;
    }
    
    const educationHTML = educationArray.map(edu => {
      let yearRange = '';
      if (edu.start_year) {
        if (edu.is_current) {
          yearRange = edu.start_year + ' - Present';
        } else if (edu.end_year) {
          yearRange = edu.start_year + ' - ' + edu.end_year;
        } else {
          yearRange = edu.start_year;
        }
      }
      
      return `
        <div class="border border-purple-200 bg-purple-50 rounded-lg p-4">
          <div class="flex items-start justify-between mb-2">
            <div class="flex-1">
              <h4 class="font-semibold text-gray-900 text-base">${edu.degree || 'Degree'}</h4>
              <p class="text-sm text-gray-700 font-medium">${edu.institution}</p>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">
              <i class="ri-graduation-cap-line mr-1"></i>Education
            </span>
          </div>
          ${edu.field_of_study ? `
            <div class="flex items-center text-sm text-gray-600 mb-1">
              <i class="ri-book-line mr-1"></i>${edu.field_of_study}
            </div>
          ` : ''}
          <div class="flex items-center text-sm text-gray-600">
            <i class="ri-calendar-line mr-1"></i>${yearRange}
          </div>
        </div>
      `;
    }).join('');
    
    container.innerHTML = educationHTML;
    if (countBadge) countBadge.textContent = educationArray.length.toString();
    console.log('✅ Education displayed:', educationArray.length, 'items');
  }
  
  function displayWorkExperience() {
    const container = document.getElementById('workExperienceDisplay');
    const countBadge = document.getElementById('workExpCount');
    
    if (!initialWorkExperiences || initialWorkExperiences.length === 0) {
      container.innerHTML = `
        <div class="text-center py-6 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
          <i class="ri-briefcase-line text-3xl text-gray-400 mb-2"></i>
          <p class="text-sm text-gray-500">No work experience found in your profile</p>
          <p class="text-xs text-gray-400 mt-1">Please update your profile to add work experience</p>
        </div>
      `;
      if (countBadge) countBadge.textContent = '0';
      return;
    }
    
    // Generate HTML for all work experiences
    const experiencesHTML = initialWorkExperiences.map(exp => {
      // Format date display
      let dateRange = '';
      if (exp.start_date) {
        const startDate = new Date(exp.start_date);
        const startStr = startDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
        
        if (exp.is_current) {
          dateRange = startStr + ' - Present';
        } else if (exp.end_date) {
          const endDate = new Date(exp.end_date);
          const endStr = endDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
          dateRange = startStr + ' - ' + endStr;
        } else {
          dateRange = startStr;
        }
      }
      
      return `
        <div class="border border-blue-200 bg-blue-50 rounded-lg p-4">
          <div class="flex items-start justify-between mb-2">
            <div class="flex-1">
              <h4 class="font-semibold text-gray-900 text-base">${exp.job_title}</h4>
              <p class="text-sm text-gray-700 font-medium">${exp.company}</p>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
              <i class="ri-briefcase-line mr-1"></i>Experience
            </span>
          </div>
          ${exp.location ? `
            <div class="flex items-center text-sm text-gray-600 mb-1">
              <i class="ri-map-pin-line mr-1"></i>${exp.location}
            </div>
          ` : ''}
          <div class="flex items-center text-sm text-gray-600 mb-2">
            <i class="ri-calendar-line mr-1"></i>${dateRange}
          </div>
          ${exp.description ? `
            <div class="mt-3 pt-3 border-t border-blue-200">
              <p class="text-sm text-gray-700 whitespace-pre-wrap">${exp.description}</p>
            </div>
          ` : ''}
        </div>
      `;
    }).join('');
    
    container.innerHTML = `
      ${experiencesHTML}
      <p class="text-xs text-gray-500 mt-2 italic">
        <i class="ri-information-line"></i> This information is from your profile. To update, visit your Profile page.
      </p>
    `;
    
    // Update count badge
    if (countBadge) countBadge.textContent = initialWorkExperiences.length.toString();
  }
  
  function displaySkills() {
    const container = document.getElementById('skillsDisplay');
    const countBadge = document.getElementById('skillsCount');
    
    if (!initialSkills || initialSkills.trim() === '') {
      container.innerHTML = `
        <div class="w-full text-center py-6 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
          <i class="ri-lightbulb-line text-3xl text-gray-400 mb-2"></i>
          <p class="text-sm text-gray-500">No skills found in your profile</p>
          <p class="text-xs text-gray-400 mt-1">Please update your profile to add skills</p>
        </div>
      `;
      if (countBadge) countBadge.textContent = '0';
      return;
    }
    
    // Split skills by comma and create tags
    const skillsArray = initialSkills.split(',').map(s => s.trim()).filter(s => s);
    
    const skillTags = skillsArray.map(skill => `
      <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-green-100 text-green-800 border border-green-200">
        <i class="ri-check-line mr-1 text-green-600"></i>${skill}
      </span>
    `).join('');
    
    container.innerHTML = `
      ${skillTags}
      <p class="w-full text-xs text-gray-500 mt-2 italic">
        <i class="ri-information-line"></i> These skills are from your profile. To update, visit your Profile page.
      </p>
    `;
    
    // Update count badge
    if (countBadge) countBadge.textContent = skillsArray.length.toString();
  }

  function displayEducation() {
    const container = document.getElementById('educationDisplay');
    const countBadge = document.getElementById('educationCount');
    
    if (!initialEducation || initialEducation.length === 0) {
      container.innerHTML = `
        <div class="text-center py-6 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
          <i class="ri-graduation-cap-line text-3xl text-gray-400 mb-2"></i>
          <p class="text-sm text-gray-500">No education found in your profile</p>
          <p class="text-xs text-gray-400 mt-1">Please update your profile to add education</p>
        </div>
      `;
      if (countBadge) countBadge.textContent = '0';
      return;
    }
    
    // Generate HTML for all education entries
    const educationHTML = initialEducation.map(edu => {
      // Format year display
      let yearRange = '';
      if (edu.start_year) {
        if (edu.is_current) {
          yearRange = edu.start_year + ' - Present';
        } else if (edu.end_year) {
          yearRange = edu.start_year + ' - ' + edu.end_year;
        } else {
          yearRange = edu.start_year;
        }
      }
      
      return `
        <div class="border border-purple-200 bg-purple-50 rounded-lg p-4">
          <div class="flex items-start justify-between mb-2">
            <div class="flex-1">
              <h4 class="font-semibold text-gray-900 text-base">${edu.degree || 'Degree'}</h4>
              <p class="text-sm text-gray-700 font-medium">${edu.institution}</p>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">
              <i class="ri-graduation-cap-line mr-1"></i>Education
            </span>
          </div>
          ${edu.field_of_study ? `
            <div class="flex items-center text-sm text-gray-600 mb-1">
              <i class="ri-book-line mr-1"></i>${edu.field_of_study}
            </div>
          ` : ''}
          <div class="flex items-center text-sm text-gray-600">
            <i class="ri-calendar-line mr-1"></i>${yearRange}
          </div>
        </div>
      `;
    }).join('');
    
    container.innerHTML = `
      ${educationHTML}
      <p class="text-xs text-gray-500 mt-2 italic">
        <i class="ri-information-line"></i> This information is from your profile. To update, visit your Profile page.
      </p>
    `;
    
    // Update count badge
    if (countBadge) countBadge.textContent = initialEducation.length.toString();
  }

  // Helper function to hide all document upload sections in step 2 (GLOBAL)
  window.hideAllDocumentSections = function() {
    console.log('🚫 Hiding all document sections...');
    
    // Find all document upload sections in step 2
    const step2 = document.getElementById('step2');
    if (!step2) return;
    
    // Hide Primary Documents section
    const primaryDocsGrid = step2.querySelector('.grid.md\\:grid-cols-2');
    if (primaryDocsGrid && primaryDocsGrid.closest('.p-4')) {
      // Hide the entire primary documents container but keep the main card visible
      const allSpaceSections = primaryDocsGrid.querySelectorAll('.space-y-2');
      allSpaceSections.forEach(section => {
        section.style.display = 'none';
        section.classList.add('hidden');
      });
    }
    
    // Hide all category sections (Educational Documents, Professional Documents, Additional Certificates)
    const categorySections = step2.querySelectorAll('.border-t.pt-3');
    categorySections.forEach(section => {
      section.style.display = 'none';
      section.classList.add('hidden');
    });
    
    console.log('✅ All document sections hidden');
  };

  // Helper function to update step 2 UI for resubmission mode (GLOBAL)
  window.updateStep2ForResubmission = function(docCount) {
    const step2 = document.getElementById('step2');
    if (!step2) return;
    
    const heading = step2.querySelector('h2');
    const description = step2.querySelector('p');
    
    if (heading) {
      heading.textContent = `Resubmit Required Documents (${docCount})`;
      heading.className = 'text-lg font-bold text-orange-900 mb-2';
    }
    
    if (description) {
      description.textContent = `Please upload only the ${docCount} document(s) requested by the admin below. Other approved documents are not shown.`;
      description.className = 'text-orange-800 mb-4 font-medium';
    }
    
    console.log('✅ Step 2 UI updated for resubmission mode');
  };

  // Public API: call after Terms & Conditions are accepted
  window.startApplicationWizard = function(jobId, jobTitle) {
    console.log('startApplicationWizard called with:', { jobId, jobTitle });
    
    // Check if required elements exist
    console.log('Checking wizard elements...');
    console.log('wizard element:', wizard);
    console.log('wizardJobTitle element:', wizardJobTitle);
    console.log('rf_job_id element:', rf_job_id);
    console.log('rf_job_title element:', rf_job_title);
    
    if (!wizard) {
      console.error('Wizard element not found!');
      alert('Application wizard not available. Please refresh the page and try again.');
      return;
    }
    
    // IMPORTANT: Clear all previous application data for fresh start
    console.log('Clearing previous application data...');
    
    // Clear resubmission docs for new application
    globalResubmissionDocs = [];
    
    // Remove any interview/demo/psych schedule details from previous applications
    const interviewDetails = document.getElementById('interview_details');
    const demoDetails = document.getElementById('demo_details');
    const hiredDetails = document.getElementById('hired_details');
    if (interviewDetails) {
      interviewDetails.innerHTML = '';
      interviewDetails.classList.add('hidden');
    }
    if (demoDetails) {
      demoDetails.innerHTML = '';
      demoDetails.classList.add('hidden');
    }
    if (hiredDetails) {
      hiredDetails.innerHTML = '';
      hiredDetails.classList.add('hidden');
    }
    
    // Clear status messages
    const interviewApprovedStatus = document.getElementById('interview_approved_status');
    const demoApprovedStatus = document.getElementById('demo_approved_status');
    const psychApprovedStatus = document.getElementById('psych_approved_status');
    if (interviewApprovedStatus) {
      interviewApprovedStatus.innerHTML = '';
      interviewApprovedStatus.classList.add('hidden');
    }
    if (demoApprovedStatus) {
      demoApprovedStatus.innerHTML = '';
      demoApprovedStatus.classList.add('hidden');
    }
    if (psychApprovedStatus) {
      psychApprovedStatus.innerHTML = '';
      psychApprovedStatus.classList.add('hidden');
    }
    
    // Remove resubmission notice if any
    const resubmissionNotice = document.querySelector('.resubmission-notice');
    if (resubmissionNotice) {
      resubmissionNotice.remove();
    }
    
    // Clear all file upload indicators and reset containers
    document.querySelectorAll('#step2 .border-dashed').forEach(container => {
      // Remove warning messages
      container.querySelectorAll('.bg-orange-100, .bg-green-50').forEach(msg => msg.remove());
      // Reset border colors
      container.classList.remove('border-orange-400', 'bg-orange-50', 'border-green-400', 'border-green-500', 'bg-green-50');
      container.classList.add('border-gray-300');
      container.style.backgroundColor = '';
      
      // Reset file inputs
      const fileInput = container.querySelector('input[type="file"]');
      if (fileInput) {
        fileInput.value = '';
        fileInput.disabled = false;
        fileInput.style.display = '';
        fileInput.classList.remove('hidden');
      }
    });
    
    // Hide file upload indicators
    document.querySelectorAll('.file-upload-indicator').forEach(indicator => {
      indicator.classList.add('hidden');
    });
    
    // Reset psych exam form
    const psychUploadForm = document.getElementById('psych_upload_form');
    const psychUploadSuccess = document.getElementById('psych_upload_success');
    if (psychUploadForm) psychUploadForm.classList.add('hidden');
    if (psychUploadSuccess) psychUploadSuccess.classList.add('hidden');
    
    // Reset interview/demo next buttons to disabled state
    const interviewNextBtn = document.getElementById('interview_next_btn');
    const demoNextBtn = document.getElementById('demo_next_btn');
    const psychNextBtn = document.getElementById('psych_next_btn');
    if (interviewNextBtn) {
      interviewNextBtn.disabled = true;
      interviewNextBtn.className = 'px-6 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed';
    }
    if (demoNextBtn) {
      demoNextBtn.disabled = true;
      demoNextBtn.className = 'px-6 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed';
    }
    if (psychNextBtn) {
      psychNextBtn.disabled = true;
      psychNextBtn.className = 'px-6 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed';
    }
    
    // Reset default status text messages
    const interviewStatusText = document.getElementById('interview_status_text');
    const demoStatusText = document.getElementById('demo_status_text');
    const psychStatusText = document.getElementById('psych_status_text');
    if (interviewStatusText) {
      interviewStatusText.textContent = 'Your application has been submitted. Please wait while the admin reviews your documents and schedules an interview.';
    }
    if (demoStatusText) {
      demoStatusText.textContent = 'Your interview has been completed. Please wait while the admin schedules your demo teaching session.';
    }
    if (psychStatusText) {
      psychStatusText.textContent = 'Please take your psychological exam and upload your receipt/proof of completion.';
    }
    
    console.log('All wizard data cleared for new application');
    
    context.jobId = jobId || (window.selectedJobId || null);
    context.jobTitle = jobTitle || (window.selectedJobTitle || '');
    console.log('Context set to:', context);
    
    // Safely set wizard title
    if (wizardJobTitle) {
      wizardJobTitle.textContent = context.jobTitle || '-';
    } else {
      console.warn('wizardJobTitle element not found');
    }
    
    // Safely set form values
    if (rf_job_id) rf_job_id.value = context.jobId || '';
    if (rf_job_title) rf_job_title.value = context.jobTitle || '';
    
    console.log('About to show wizard...');
    showWizard(false); // Pass false for edit mode (new application)
    console.log('Wizard should now be visible');
    
    // IMPORTANT: Prefill AFTER wizard is shown to ensure DOM elements exist
    setTimeout(() => {
      try {
        console.log('📋 Calling prefillPersonal() after wizard is shown...');
        prefillPersonal();
        // also prefill hidden submit fields when known
        if (rf_full_name && pf_first && pf_last) {
          rf_full_name.value = [pf_first.value, pf_last.value].filter(Boolean).join(' ');
        }
        if (rf_email && pf_email) rf_email.value = pf_email.value;
        if (rf_cellphone && pf_phone) rf_cellphone.value = pf_phone.value;
      } catch (error) {
        console.error('Error prefilling form:', error);
      }
    }, 300);
    
    // For new applications, start at step 1
    // Set workflow progress to step 1 (new application)
    window.currentWorkflowStep = 1;
    window.currentApplicationData = null; // Clear any previous data
    console.log('Set workflow progress to step 1 (new application)');
    setStep(1);
    
    // Double-check wizard visibility
    setTimeout(() => {
      const isVisible = !wizard.classList.contains('hidden');
      console.log('Wizard visibility check:', isVisible);
      if (!isVisible) {
        console.error('Wizard is still hidden after showWizard() call');
      }
    }, 100);
  }

  // If existing Apply buttons are used, they may set globals; keep compatibility
  document.querySelectorAll('.apply-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      // Do not bypass terms modal; assume existing flow opens it and later calls startApplicationWizard()
      // However, set potential globals for compatibility
      const jid = btn.getAttribute('data-job-id');
      const jtitle = btn.getAttribute('data-job-title') || (document.getElementById('detailJobTitle')?.textContent || '');
      window.selectedJobId = jid;
      window.selectedJobTitle = jtitle;
      // Existing code should show the Terms modal. After user agrees, call window.startApplicationWizard(window.selectedJobId, window.selectedJobTitle)
    });
  });

  // Helper function to update job button state
  function updateJobButtonState(jobId) {
    console.log('Updating button state for job ID:', jobId);
    
    // Find all Apply Now buttons
    document.querySelectorAll('button').forEach(button => {
      const buttonJobId = button.getAttribute('data-job-id');
      
      if (buttonJobId == jobId && button.textContent.trim() === 'Apply Now') {
        console.log('Found Apply Now button for job', jobId, '- changing to View Application');
        
        // Change to View Application
        button.textContent = 'View Application';
        button.className = 'px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm whitespace-nowrap !rounded-button';
        button.disabled = false;
        
        if (window.currentApplicationId) {
          button.setAttribute('data-application-id', window.currentApplicationId);
        }
        button.setAttribute('data-application-status', 'in-progress');
      }
    });
  }
  
  // Navigation - Back button from wizard (top left button)
  document.getElementById('backFromWizard').addEventListener('click', () => {
    console.log('Back from wizard clicked - refreshing page...');
    // Always reload the page to show updated dashboard with applied status
    location.reload();
  });

  // Step 1 actions
  document.getElementById('saveAllStep1Btn').addEventListener('click', async () => {
    try {
      // Validate phone number before saving
      if (pf_phone.value.trim()) {
        const phonePattern = /^09[0-9]{9}$/;
        if (!phonePattern.test(pf_phone.value.trim())) {
          showToast('Invalid phone number! Must be 11 digits starting with 09 (e.g., 09123456789)', 'error', 5000);
          pf_phone.focus();
          return;
        }
      }
      
      const formData = new FormData();
      formData.append('savePersonal', '1');
      formData.append('applicant_fname', pf_first.value.trim());
      formData.append('applicant_lname', pf_last.value.trim());
      formData.append('applicant_email', pf_email.value.trim());
      formData.append('applicant_num', pf_phone.value.trim());
      formData.append('applicant_address', pf_address.value.trim());
      
      // Save work experience
      if (wx_job.value && wx_comp.value && wx_start.value) {
        formData.append('saveExperience', '1');
        formData.append('job_title', wx_job.value.trim());
        formData.append('work_comp', wx_comp.value.trim());
        formData.append('work_loc', wx_loc.value.trim());
        formData.append('start_date', wx_start.value);
        if (!wx_cur.checked && wx_end.value) formData.append('end_date', wx_end.value);
        if (wx_cur.checked) formData.append('is_current', '1');
        formData.append('work_descript', wx_desc.value.trim());
      }
      
      const res = await fetch('save_profile_data.php', { method: 'POST', body: formData });
      const data = await res.json();
      showToast(data.success ? 'Information saved successfully' : (data.message || 'Save failed'), data.success ? 'success' : 'error');
      if (data.success) {
        rf_full_name.value = [pf_first.value, pf_last.value].filter(Boolean).join(' ');
        rf_email.value = pf_email.value;
        rf_cellphone.value = pf_phone.value;
      }
    } catch (e) {
      showToast('Error saving personal info', 'error');
    }
  });
  document.getElementById('toStep2').addEventListener('click', () => {
    // If viewing an existing application (view mode), just navigate without validation
    if (window.currentApplicationData && window.currentApplicationData.id) {
      console.log('📖 View mode - navigating to step 2 without validation');
      setStep(2);
      return;
    }
    
    // For new applications, validate required personal fields
    if (!pf_first.value || !pf_last.value || !pf_email.value || !pf_phone.value) {
      showToast('Please complete required personal fields', 'warning');
      return;
    }
    
    // Validate Philippine phone number format (must be 11 digits starting with 09)
    const phonePattern = /^09[0-9]{9}$/;
    if (!phonePattern.test(pf_phone.value)) {
      showToast('Invalid phone number! Must be 11 digits starting with 09 (e.g., 09123456789)', 'error', 5000);
      pf_phone.focus();
      return;
    }
    
    // Check if work experience exists (from profile)
    if (!wx_job.value || !wx_comp.value) {
      showToast('Please add work experience in your profile before applying. Visit Profile page to add your work experience.', 'warning', 5000);
      return;
    }
    rf_full_name.value = [pf_first.value, pf_last.value].filter(Boolean).join(' ');
    rf_email.value = pf_email.value;
    rf_cellphone.value = pf_phone.value;
    
    // Update workflow step for proper step indicator color
    window.currentWorkflowStep = 2;
    console.log('Updated currentWorkflowStep to 2 for step indicator');
    
    setStep(2);
  });

  // Step 2 actions - handle form submission with AJAX (no page reload)
  document.getElementById('backToStep1').addEventListener('click', () => setStep(1));
  
  // Save Draft button handler - REMOVED (button no longer exists)
  /*
  document.getElementById('saveDraftBtn').addEventListener('click', async function(e) {
    e.preventDefault();
    console.log('💾 Saving draft...');
    
    const btn = this;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line animate-spin mr-2"></i>Saving...';
    
    try {
      const formData = new FormData(requirementsForm);
      
      // Submit to save_draft.php
      const response = await fetch('save_draft.php', {
        method: 'POST',
        body: formData
      });
      
      const data = await response.json();
      
      if (data.success) {
        // Show success notification
        const successNotif = document.createElement('div');
        successNotif.className = 'fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded shadow-md animate-fade-in z-[10001]';
        successNotif.innerHTML = `
          <div class='flex items-center'>
            <i class='ri-check-line mr-2'></i>
            <span>Draft saved! Your documents will auto-load for future applications.</span>
          </div>
        `;
        document.body.appendChild(successNotif);
        setTimeout(() => successNotif.remove(), 5000);
        
        console.log('✅ Draft saved successfully');
      } else {
        throw new Error(data.error || 'Failed to save draft');
      }
    } catch (error) {
      console.error('❌ Error saving draft:', error);
      
      // Show error notification
      const errorNotif = document.createElement('div');
      errorNotif.className = 'fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded shadow-md animate-fade-in z-[10001]';
      errorNotif.innerHTML = `
        <div class='flex items-center'>
          <i class='ri-error-warning-line mr-2'></i>
          <span>Failed to save draft. Please try again.</span>
        </div>
      `;
      document.body.appendChild(errorNotif);
      setTimeout(() => errorNotif.remove(), 5000);
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  });
  */
  
  // Intercept form submission to avoid page reload
  const requirementsForm = document.getElementById('requirementsForm');
  if (requirementsForm) {
    requirementsForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      console.log('📝 Form submitted - uploading files...');
      console.log('📋 Form element:', this);
      
      const submitBtn = this.querySelector('button[type="submit"]');
      const originalBtnHtml = submitBtn.innerHTML;
      
      // ✅ VALIDATION: Check if this is resubmission mode and validate ALL required documents
      const isResubmission = this.querySelector('input[name="is_resubmission"]');
      if (isResubmission && isResubmission.value === '1') {
        console.log('🔍 Resubmission mode detected - validating all required documents...');
        
        // Get the list of required documents from the global variable
        if (window.currentResubmissionDocs && window.currentResubmissionDocs.length > 0) {
          console.log('📋 Required documents:', window.currentResubmissionDocs);
          
          // Document field name mapping
          const docFieldMap = {
            'application_letter': 'applicationLetter',
            'resume': 'resume_file',
            'tor': 'transcript',
            'diploma': 'diploma',
            'professional_license': 'license',
            'coe': 'coe',
            'seminars_trainings': 'certificates[]',
            'masteral_cert': 'masteral_cert'
          };
          
          const missingDocs = [];
          
          // Check each required document
          for (const docKey of window.currentResubmissionDocs) {
            const fieldName = docFieldMap[docKey];
            if (!fieldName) {
              console.warn('⚠️ Unknown document key:', docKey);
              continue;
            }
            
            // Check if file is uploaded for this document
            const fileInput = this.querySelector(`input[name="${fieldName}"]`);
            if (!fileInput) {
              console.warn('⚠️ File input not found for:', fieldName);
              continue;
            }
            
            const hasFile = fileInput.files && fileInput.files.length > 0;
            console.log(`  ${docKey} (${fieldName}): ${hasFile ? '✅ Uploaded' : '❌ Missing'}`);
            
            if (!hasFile) {
              // Get document label for user-friendly message
              const docLabels = {
                'application_letter': 'Application Letter',
                'resume': 'Resume',
                'tor': 'Transcript of Records (TOR)',
                'diploma': 'Diploma',
                'professional_license': 'Professional License',
                'coe': 'Certificate of Employment',
                'seminars_trainings': 'Seminars/Training Certificates',
                'masteral_cert': 'Masteral Certificate'
              };
              missingDocs.push(docLabels[docKey] || docKey);
            }
          }
          
          // If any documents are missing, show error and prevent submission
          if (missingDocs.length > 0) {
            console.error('❌ Missing required documents:', missingDocs);
            
            const errorNotif = document.createElement('div');
            errorNotif.className = 'fixed top-4 right-4 bg-red-100 border-2 border-red-400 text-red-700 px-5 py-4 rounded-lg shadow-lg z-[10001] max-w-md';
            errorNotif.innerHTML = `
              <div class='flex flex-col'>
                <div class='flex items-center mb-2'>
                  <i class='ri-error-warning-line text-2xl mr-2'></i>
                  <span class="font-bold text-lg">Missing Required Documents</span>
                </div>
                <p class="text-sm mb-2">You must upload ALL ${window.currentResubmissionDocs.length} requested documents before submitting:</p>
                <ul class="text-sm space-y-1 ml-4 list-disc">
                  ${missingDocs.map(doc => `<li class="font-semibold">${doc}</li>`).join('')}
                </ul>
              </div>
            `;
            document.body.appendChild(errorNotif);
            setTimeout(() => errorNotif.remove(), 8000);
            
            // Don't proceed with submission
            return;
          }
          
          console.log('✅ All required documents validated successfully!');
        }
      }
      
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="ri-loader-4-line animate-spin mr-2"></i>Uploading...';
      
      try {
        const formData = new FormData(this);
        
        // Debug: Log all form data
        console.log('📦 Form Data Contents:');
        for (let [key, value] of formData.entries()) {
          if (value instanceof File) {
            console.log(`  ${key}: File(${value.name}, ${value.size} bytes)`);
          } else {
            console.log(`  ${key}: ${value}`);
          }
        }
        
        // Submit form via AJAX with proper headers
        const response = await fetch(window.location.href, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
        });
        
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        // Get response as text first to see what we got
        const responseText = await response.text();
        console.log('Raw response:', responseText);
        console.log('Response length:', responseText.length);
        
        // Try to parse as JSON
        let data;
        try {
          data = JSON.parse(responseText);
          console.log('✅ Parsed JSON successfully:', data);
        } catch(e) {
          console.error('❌ JSON parse error:', e);
          console.error('Response text (first 500 chars):', responseText.substring(0, 500));
          
          data = { 
            success: false, 
            error: 'Invalid response format',
            message: 'Server returned invalid JSON. Check console for details.',
            raw_response: responseText.substring(0, 200)
          };
        }
        
        // Check if successful
        if (data.success) {
          console.log('✅ Upload successful! Application ID:', data.application_id);
          
          // Show success notification
          const successNotif = document.createElement('div');
          successNotif.className = 'fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded shadow-md animate-fade-in z-[10001]';
          successNotif.innerHTML = `
            <div class='flex items-center'>
              <i class='ri-check-line mr-2'></i>
              <span>Application submitted successfully!</span>
            </div>
          `;
          document.body.appendChild(successNotif);
          setTimeout(() => successNotif.remove(), 5000);
          
          // Wait a moment for the data to be saved, then fetch it
          await new Promise(resolve => setTimeout(resolve, 500));
          
          // Fetch the newly created application data with timeout
          console.log('Fetching application data from get_user_applications.php...');
          const appsResponse = await fetch('get_user_applications.php');
          console.log('get_user_applications response status:', appsResponse.status);
          
          if (!appsResponse.ok) {
            console.error('Failed to fetch applications:', appsResponse.status);
            throw new Error('Failed to fetch application data');
          }
          
          const appsData = await appsResponse.json();
          console.log('Applications data:', appsData);
          
          if (appsData.success && appsData.applications && appsData.applications.length > 0) {
            const newApp = appsData.applications[0]; // Most recent application
            console.log('Loaded new application:', newApp);
            console.log('Application files:', {
              application_letter: newApp.application_letter,
              resume: newApp.resume,
              tor: newApp.tor,
              diploma: newApp.diploma,
              coe: newApp.coe
            });
            
            // Store globally
            window.currentApplicationData = newApp;
            window.currentApplicationId = newApp.application_id;
            
            // Set workflow progress to step 3 (application submitted, waiting for interview)
            window.currentWorkflowStep = 3;
            console.log('Set workflow progress to step 3 (application submitted)');
            
            // Add file indicators immediately to show uploaded files
            console.log('Calling addFileIndicatorsForApplication...');
            
            // Use setTimeout to ensure DOM is ready
            setTimeout(() => {
              if (typeof window.addFileIndicatorsForApplication === 'function') {
                window.addFileIndicatorsForApplication(newApp);
                console.log('File indicators added via function');
              } else {
                console.error('addFileIndicatorsForApplication function not found!');
              }
              
              // Also manually update the UI as a fallback
              console.log('Manually updating file indicators...');
              const fileInputs = requirementsForm.querySelectorAll('input[type="file"]');
              fileInputs.forEach(input => {
                const container = input.closest('.border-dashed');
                if (container && input.files && input.files.length > 0) {
                  const fileName = input.files[0].name;
                  console.log('Found uploaded file:', fileName);
                  
                  // Hide input
                  input.style.display = 'none';
                  input.disabled = true;
                  input.required = false;
                  
                  // Update container styling
                  container.classList.remove('border-gray-300', 'hover:border-blue-400');
                  container.classList.add('border-green-400', 'bg-green-50');
                  container.style.borderColor = '#4ade80';
                  container.style.backgroundColor = '#f0fdf4';
                  
                  // Add green indicator
                  container.innerHTML = `
                    <div class="flex items-center p-2">
                      <i class="ri-checkbox-circle-fill text-green-600 text-lg mr-2"></i>
                      <span class="text-green-700 text-sm font-medium">Uploaded: ${fileName}</span>
                    </div>
                  `;
                }
              });
              
              // Hide the submit button and show the Next button
              const submitBtn = requirementsForm.querySelector('button[type="submit"]');
              const nextBtn = document.getElementById('step2NextBtn');
              
              if (submitBtn) {
                submitBtn.style.display = 'none';
                console.log('Submit button hidden');
              }
              
              if (nextBtn) {
                nextBtn.classList.remove('hidden');
                nextBtn.style.display = 'flex';
                console.log('Next button shown');
              }
            }, 100);
            
          } else {
            console.error('No applications found in response:', appsData);
            throw new Error('Could not load application data');
          }
          
        } else {
          console.error('❌ Upload failed - Response data:', data);
          const errorMsg = data.error || data.message || 'Upload failed - no success response';
          
          // If there's a raw response, log it
          if (data.raw_response) {
            console.error('Raw server response:', data.raw_response);
          }
          
          throw new Error(errorMsg);
        }
        
      } catch (error) {
        console.error('❌ Upload error:', error);
        console.error('Error stack:', error.stack);
        
        // Show error with specific message
        const errorNotif = document.createElement('div');
        errorNotif.className = 'fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded shadow-md z-[10001] max-w-md';
        errorNotif.innerHTML = `
          <div class='flex flex-col'>
            <div class='flex items-center mb-2'>
              <i class='ri-error-warning-line mr-2'></i>
              <span class="font-semibold">${error.message || 'Upload failed. Please try again.'}</span>
            </div>
            <div class='text-xs opacity-75'>Check browser console (F12) for details</div>
          </div>
        `;
        document.body.appendChild(errorNotif);
        setTimeout(() => errorNotif.remove(), 8000);
        
        // Re-enable button
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnHtml;
      }
    });
  }
  
  // Add Work Experience button - redirect to profile
  document.getElementById('addWorkExpBtn').addEventListener('click', () => {
    if (confirm('You will be redirected to your Profile page to add work experience. Your application progress will be saved. Continue?')) {
      // Close wizard and go to profile
      hideWizard();
      showProfile(); // This function should exist in your navigation
    }
  });
  
  // Add Skills button - redirect to profile
  document.getElementById('addSkillsBtn').addEventListener('click', () => {
    if (confirm('You will be redirected to your Profile page to add skills. Your application progress will be saved. Continue?')) {
      // Close wizard and go to profile
      hideWizard();
      showProfile(); // This function should exist in your navigation
    }
  });
  
  // Add Education button - redirect to profile
  document.getElementById('addEducationBtn').addEventListener('click', () => {
    if (confirm('You will be redirected to your Profile page to add education. Your application progress will be saved. Continue?')) {
      // Close wizard and go to profile
      hideWizard();
      showProfile(); // This function should exist in your navigation
    }
  });
  
  // File upload visual indicators for Step 2
  function setupFileUploadIndicators() {
    // Get all file inputs in step 2
    const fileInputs = document.querySelectorAll('#step2 input[type="file"]');
    
    fileInputs.forEach(input => {
      // Create indicator container
      const container = input.closest('.border-dashed');
      if (!container) return;
      
      // Check if indicator already exists to prevent duplicates
      let indicator = container.querySelector('.file-upload-indicator');
      if (!indicator) {
        // Create uploaded indicator (hidden by default)
        indicator = document.createElement('div');
        indicator.className = 'file-upload-indicator hidden w-full flex items-center justify-between';
        indicator.innerHTML = `
          <div class="flex items-center text-green-700 flex-1 min-w-0">
            <i class="ri-checkbox-circle-fill mr-2 text-lg flex-shrink-0"></i>
            <span class="text-sm font-medium truncate filename"></span>
          </div>
          <button type="button" class="remove-file text-red-600 hover:text-red-800 ml-2 flex-shrink-0">
            <i class="ri-close-circle-fill text-xl"></i>
          </button>
        `;
        container.appendChild(indicator);
        
        // Handle remove button
        const removeBtn = indicator.querySelector('.remove-file');
        removeBtn.addEventListener('click', function() {
          // Clear the file input
          input.value = '';
          
          // Reset container styling
          container.classList.remove('border-green-400', 'border-green-500', 'bg-green-50');
          container.classList.add('border-gray-300', 'hover:border-blue-400');
          container.style.backgroundColor = '';
          
          // Hide indicator
          indicator.classList.add('hidden');
          
          // Show file input again
          input.style.display = '';
          input.classList.remove('hidden');
        });
      }
      
      // Handle file selection
      input.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
          // Show green indicator styling
          container.classList.remove('border-gray-300', 'hover:border-blue-400');
          container.classList.add('border-green-500', 'bg-green-50');
          
          // Update filename display
          const filenameSpan = indicator.querySelector('.filename');
          if (this.files.length > 1) {
            filenameSpan.textContent = `${this.files.length} files selected`;
          } else {
            filenameSpan.textContent = this.files[0].name;
          }
          
          // Show indicator
          indicator.classList.remove('hidden');
          
          // Hide the file input
          this.style.display = 'none';
          this.classList.add('hidden');
        }
      });
    });
  }
  
  // Initialize file upload indicators when wizard is shown
  setTimeout(() => {
    setupFileUploadIndicators();
  }, 100);
  
  // Handle psych receipt upload in wizard
  const psychForm = document.getElementById('psychReceiptForm');
  if (psychForm) {
    psychForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const appId = document.getElementById('wizard_psych_app_id').value;
      const fileInput = document.getElementById('wizard_psych_receipt');
      const file = fileInput.files[0];
      
      if (!file) {
        showToast('Please select a file', 'error');
        return;
      }
      
      if (file.size > 5 * 1024 * 1024) {
        showToast('File size must be less than 5MB', 'error');
        return;
      }
      
      const formData = new FormData();
      formData.append('application_id', appId);
      formData.append('psych_receipt', file);
      
      try {
        const res = await fetch('upload_psych_receipt.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await res.json();
        
        if (!data.success) {
          throw new Error(data.error || 'Upload failed');
        }
        
        // Hide upload form and show success
        document.getElementById('psych_upload_form').classList.add('hidden');
        document.getElementById('psych_upload_success').classList.remove('hidden');
        
        // Show waiting for approval message
        const waitingHtml = `
          <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
            <p class="text-yellow-700 font-medium flex items-center justify-center">
              <i class="ri-time-line mr-2"></i>Receipt submitted - Waiting for admin approval
            </p>
          </div>
        `;
        document.getElementById('psych_approved_status').innerHTML = waitingHtml;
        document.getElementById('psych_approved_status').classList.remove('hidden');
        
        showToast('Receipt uploaded successfully! Waiting for admin approval.', 'success');
        
      } catch (err) {
        showToast('Error uploading receipt: ' + err.message, 'error');
      }
    });
  }
  
  // Check if we should show workflow step after form submission
  // DISABLED: Now using AJAX submission, so no need to auto-open wizard on page reload
  const showWorkflowStep = 0; // <?php echo $show_workflow_step; ?>;
  const newApplicationId = ''; // '<?php echo $new_application_id; ?>';
  
  if (false && showWorkflowStep > 0 && showWorkflowStep <= 6 && newApplicationId) {
    console.log('Auto-showing workflow step:', showWorkflowStep);
    console.log('New application ID:', newApplicationId);
    
    // Load the application data to show uploaded files
    fetch(`get_user_applications.php`)
      .then(res => res.json())
      .then(data => {
        console.log('Loaded applications after submission:', data);
        
        if (data.success && data.applications) {
          // Find the newly submitted application
          const newApp = data.applications.find(app => app.application_id == newApplicationId);
          
          if (newApp) {
            console.log('Found newly submitted application:', newApp);
            
            // Store application data globally
            window.currentApplicationData = newApp;
            window.currentApplicationId = newApplicationId;
            
            // Show wizard in view mode
            showWizard(true);
            
            // First go to step 2 to show uploaded files
            setStep(2);
            
            // Add file indicators while step 2 is visible
            setTimeout(() => {
              console.log('Adding file indicators to visible step 2...');
              addFileIndicatorsForApplication(newApp);
              
              // Add a temporary "reviewing files" message at the top of step 2
              const step2 = document.getElementById('step2');
              if (step2) {
                const reviewMessage = document.createElement('div');
                reviewMessage.className = 'mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg';
                reviewMessage.innerHTML = `
                  <div class='flex items-center text-blue-700'>
                    <i class='ri-check-double-line text-xl mr-2'></i>
                    <span class='text-sm font-medium'>Files uploaded successfully! Reviewing your documents...</span>
                  </div>
                `;
                step2.insertBefore(reviewMessage, step2.firstChild);
                
                // Remove the message after 2.5 seconds
                setTimeout(() => reviewMessage.remove(), 2500);
              }
              
              // Stay in step 2 for 3 seconds to show uploaded files, then move to workflow step
              setTimeout(() => {
                console.log('Navigating to workflow step:', showWorkflowStep);
                setStep(showWorkflowStep);
              }, 3000); // Stay in step 2 for 3 seconds
            }, 300);
          } else {
            console.warn('Could not find application with ID:', newApplicationId);
            // Still show wizard even if we can't find the application
            showWizard(true);
            setStep(showWorkflowStep);
          }
        } else {
          console.error('Failed to load applications:', data);
          // Still show wizard
          showWizard(true);
          setStep(showWorkflowStep);
        }
      })
      .catch(err => {
        console.error('Error loading application data:', err);
        // Still show wizard even on error
        showWizard(true);
        setStep(showWorkflowStep);
      });
  }
  
  // Helper function to add file indicators (make it global)
  window.addFileIndicatorsForApplication = function(app) {
    console.log('=== ADDING FILE INDICATORS ===');
    console.log('Application ID:', app.application_id || app.id);
    
    // Check if already processing to prevent duplicate calls
    if (window._isProcessingIndicators) {
      console.warn('Already processing indicators, skipping duplicate call');
      return;
    }
    window._isProcessingIndicators = true;
    
    // Use setTimeout to ensure this runs after any other pending operations
    setTimeout(() => {
      try {
        const step2 = document.getElementById('step2');
        if (!step2) {
          console.error('Step 2 not found!');
          return;
        }
        
        console.log('NUCLEAR CLEANUP - DESTROYING ALL CONTAINERS...');
        
        // Get all file inputs in step 2
        const allInputs = step2.querySelectorAll('input[type="file"]');
        console.log('Found', allInputs.length, 'file inputs to reset');
        
        // NUCLEAR OPTION: Save input data, destroy container, rebuild
        allInputs.forEach((input, index) => {
          const container = input.closest('.border-dashed');
          if (!container) return;
          
          console.log(`DESTROYING container ${index + 1} for input: ${input.name}`);
          
          // Save input attributes
          const inputName = input.name;
          const inputAccept = input.accept;
          const inputRequired = input.required;
          
          // COMPLETELY DESTROY the container content
          container.innerHTML = '';
          
          // Rebuild ONLY the input element
          const newInput = document.createElement('input');
          newInput.type = 'file';
          newInput.name = inputName;
          newInput.accept = inputAccept;
          newInput.required = inputRequired;
          newInput.className = 'w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100';
          
          container.appendChild(newInput);
          
          // Reset container styling
          container.className = 'border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-blue-400 transition-colors';
          container.removeAttribute('style');
          
          // Reset parent
          if (container.parentElement) {
            container.parentElement.removeAttribute('style');
          }
          
          console.log(`✓ Container ${index + 1} REBUILT from scratch`);
        });
        
        console.log('✓ All containers cleared');
        
        // Update the last loaded app ID
        window._lastLoadedAppId = app.application_id || app.id;
    
    // Helper function to add uploaded file indicator
    const addUploadedIndicator = (inputName, fileName, documentField) => {
      const input = document.querySelector(`input[name="${inputName}"]`);
      if (!input) {
        console.warn('Input not found for:', inputName);
        return;
      }
      
      const container = input.closest('.border-dashed');
      if (!container) {
        console.warn('Container not found for input:', inputName);
        return;
      }
      
      // Check if THIS specific document needs resubmission
      const needsResubmission = window.globalResubmissionDocs && window.globalResubmissionDocs.includes(documentField);
      
      if (fileName) {
        console.log('Adding indicator for:', inputName, fileName, needsResubmission ? '(needs resubmission)' : '(approved)');
        
        if (needsResubmission) {
          // Document needs resubmission - NO GREEN, show orange styling
          console.log(`🟧 ${documentField} needs resubmission - NO GREEN indicator, showing file input`);
          
          // Orange border styling
          container.classList.remove('border-gray-300', 'border-green-400', 'bg-green-50', 'hover:border-blue-400');
          container.classList.add('border-orange-400', 'bg-orange-50');
          container.style.setProperty('border-width', '2px', 'important');
          container.style.setProperty('border-color', '#fb923c', 'important');
          container.style.setProperty('background-color', '#fff7ed', 'important');
          
          // Keep file input visible and enabled
          input.disabled = false;
          input.required = false;
          input.style.display = 'block';
          input.classList.remove('hidden');
          
          // Add orange notice showing previous file
          const resubmitNotice = document.createElement('div');
          resubmitNotice.className = 'mt-2 p-2 bg-orange-100 border border-orange-300 rounded text-xs';
          resubmitNotice.innerHTML = `
            <div class="flex items-center text-orange-800">
              <i class="ri-information-line mr-2"></i>
              <span class="font-medium">Previous file: ${fileName}</span>
            </div>
          `;
          container.appendChild(resubmitNotice);
          
        } else {
          // Document is approved - show GREEN indicator
          container.classList.remove('border-gray-300', 'border-orange-400', 'bg-orange-50', 'hover:border-blue-400');
          container.classList.add('border-green-400', 'bg-green-50');
          container.style.setProperty('border-width', '2px', 'important');
          container.style.setProperty('border-color', '#4ade80', 'important');
          container.style.setProperty('opacity', '1', 'important');
          container.style.setProperty('background-color', '#f0fdf4', 'important');
          if (container.parentElement) {
            container.parentElement.style.setProperty('opacity', '1', 'important');
          }
          
          // Create green uploaded file indicator
          const indicator = document.createElement('div');
          indicator.className = 'flex items-center p-2 approved-file';
          indicator.style.setProperty('opacity', '1', 'important');
          indicator.innerHTML = `
            <i class="ri-checkbox-circle-fill text-green-600 text-lg mr-2"></i>
            <span class="text-green-700 text-sm font-medium">Uploaded: ${fileName}</span>
          `;
          container.appendChild(indicator);
          
          // Hide the file input (approved, view-only)
          input.disabled = true;
          input.style.setProperty('display', 'none', 'important');
          input.classList.add('hidden');
          input.required = false;
        }
      }
    };
    
        // Add indicators for each uploaded file
        console.log('NOW ADDING FILE INDICATORS...');
        if (app.application_letter) addUploadedIndicator('applicationLetter', app.application_letter, 'application_letter');
        if (app.resume) addUploadedIndicator('resume_file', app.resume, 'resume');
        if (app.tor) addUploadedIndicator('transcript', app.tor, 'tor');
        if (app.diploma) addUploadedIndicator('diploma', app.diploma, 'diploma');
        if (app.professional_license) addUploadedIndicator('license', app.professional_license, 'professional_license');
        if (app.coe) addUploadedIndicator('coe', app.coe, 'coe');
        if (app.seminars_trainings) addUploadedIndicator('certificates[]', app.seminars_trainings, 'seminars_trainings');
        if (app.masteral_cert) addUploadedIndicator('masteral_cert', app.masteral_cert, 'masteral_cert');
        
        console.log('✓ File indicators added successfully');
        
      } catch (error) {
        console.error('Error adding file indicators:', error);
      } finally {
        // Always reset the processing flag
        window._isProcessingIndicators = false;
      }
    }, 100); // Delay to ensure clean state
  };
});

// Global function to close wizard and refresh
window.closeWizardAndRefresh = function() {
  console.log('Closing wizard...');
  
  // Check if wizard was opened from My Applications
  if (window.wizardOpenedFromMyApplications) {
    console.log('✅ Returning to My Applications tab');
    
    // Hide wizard
    if (typeof hideWizard === 'function') {
      hideWizard();
    } else {
      const wizard = document.getElementById('applicationWizard');
      if (wizard) {
        wizard.classList.add('hidden');
        wizard.style.display = 'none';
      }
    }
    
    // Reset flag
    window.wizardOpenedFromMyApplications = false;
    
    // Show main content containers
    document.body.style.overflow = '';
    const jobHeader = document.getElementById('jobHeader');
    const searchFilters = document.getElementById('searchFilters');
    const listings = document.getElementById('listings');
    const mainContent = document.getElementById('mainContent');
    
    if (jobHeader) jobHeader.style.display = '';
    if (searchFilters) searchFilters.style.display = '';
    if (listings) listings.style.display = '';
    if (mainContent) mainContent.style.display = '';
    
    // Navigate to My Applications tab
    const applicationsLink = document.getElementById('applicationsLink');
    if (applicationsLink) {
      console.log('Clicking My Applications link...');
      applicationsLink.click();
    } else {
      console.log('⚠️ Could not find My Applications link, reloading page...');
      location.reload();
    }
  } else {
    console.log('Closing wizard and refreshing page...');
    // Always reload the page to show updated dashboard with applied status
    location.reload();
  }
};
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
const modal = document.getElementById('jobModal');
const closeModal = document.getElementById('closeModal');
const filterBtn = document.getElementById('filterBtn');
const filterModal = document.getElementById('filterModal');
const sortBtn = document.getElementById('sortBtn');
const sortModal = document.getElementById('sortModal');
const modalSaveBtn = document.getElementById('modalSaveBtn');
filterBtn.addEventListener('click', () => {
filterModal.classList.remove('hidden');
filterModal.classList.add('flex');
});
document.querySelectorAll('.closeFilterModal').forEach(btn => {
btn.addEventListener('click', () => {
filterModal.classList.add('hidden');
filterModal.classList.remove('flex');
});
});
sortBtn.addEventListener('click', () => {
sortModal.classList.remove('hidden');
sortModal.classList.add('flex');
});
document.querySelectorAll('.closeSortModal').forEach(btn => {
btn.addEventListener('click', () => {
sortModal.classList.add('hidden');
sortModal.classList.remove('flex');
});
});
modalSaveBtn.addEventListener('click', function() {
const savedState = this.getAttribute('data-saved') === 'true';
const icon = this.querySelector('i');
if (!savedState) {
icon.classList.remove('ri-bookmark-line');
icon.classList.add('ri-bookmark-fill');
this.setAttribute('data-saved', 'true');
showNotification('Job saved successfully!', 'success');
} else {
icon.classList.remove('ri-bookmark-fill');
icon.classList.add('ri-bookmark-line');
this.setAttribute('data-saved', 'false');
showNotification('Job removed from saved items', 'info');
}
});
function showNotification(message, type) {
const notification = document.createElement('div');
notification.className = `fixed top-4 right-4 ${type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-blue-100 border-blue-400 text-blue-700'} px-4 py-3 rounded border`;
notification.innerHTML = `
<div class="flex items-center">
<i class="${type === 'success' ? 'ri-check-line' : 'ri-information-line'} mr-2"></i>
<span>${message}</span>
</div>
`;
document.body.appendChild(notification);
setTimeout(() => {
notification.remove();
}, 3000);
}
filterModal.addEventListener('click', function(e) {
if (e.target === this) {
this.classList.add('hidden');
this.classList.remove('flex');
}
});
sortModal.addEventListener('click', function(e) {
if (e.target === this) {
this.classList.add('hidden');
this.classList.remove('flex');
}
});
const jobListings = {
1: [
{
title: "Part-time Instructor",
department: "Academic Department",
type: "Part-time",
location: "North Carolina",
status: "Open",
description: "We are seeking qualified part-time instructors to teach various courses in our academic programs. The ideal candidate should have relevant educational background and teaching experience.",
salary: "$25 - $35 per hour",
icon: "ri-building-line"
},
{
title: "Utility Staff",
department: "Facilities Management",
type: "Full-time",
location: "North Carolina",
status: "Open",
description: "Join our facilities team as a utility staff member. Responsibilities include general maintenance, cleaning, and ensuring our campus facilities are well-maintained and operational.",
salary: "$18 - $22 per hour",
icon: "ri-tools-line"
},
{
title: "Office Secretary",
department: "Administration",
type: "Full-time",
location: "North Carolina",
status: "Applied",
description: "We are looking for an organized and professional office secretary to handle administrative tasks, manage correspondence, and provide excellent customer service to students and staff.",
salary: "$16 - $20 per hour",
icon: "ri-file-text-line"
},
{
title: "Campus Security Guard",
department: "Security Department",
type: "Part-time",
location: "North Carolina",
status: "Open",
description: "Ensure campus safety and security by monitoring premises, controlling access, and responding to emergencies. Previous security experience preferred but not required.",
salary: "$15 - $18 per hour",
icon: "ri-shield-line"
},
{
title: "Library Assistant",
department: "Library Services",
type: "Part-time",
location: "North Carolina",
status: "Open",
description: "Support library operations by assisting students and faculty, managing book circulation, organizing materials, and maintaining library resources and facilities.",
salary: "$14 - $17 per hour",
icon: "ri-book-line"
}
],
2: [
{
title: "IT Support Specialist",
department: "Information Technology",
type: "Full-time",
location: "North Carolina",
status: "Open",
description: "Provide technical support to faculty, staff, and students. Troubleshoot hardware and software issues, maintain network infrastructure, and assist with technology implementations.",
salary: "$22 - $28 per hour",
icon: "ri-computer-line"
},
{
title: "Student Counselor",
department: "Student Services",
type: "Full-time",
location: "North Carolina",
status: "Open",
description: "Guide and support students in their academic and personal development. Provide counseling services, career advice, and assistance with academic planning.",
salary: "$24 - $30 per hour",
icon: "ri-user-heart-line"
},
{
title: "Cafeteria Staff",
department: "Food Services",
type: "Part-time",
location: "North Carolina",
status: "Open",
description: "Join our dining services team to prepare and serve meals, maintain kitchen cleanliness, and ensure food safety standards are met.",
salary: "$15 - $18 per hour",
icon: "ri-restaurant-line"
},
{
title: "Research Assistant",
department: "Research Department",
type: "Part-time",
location: "North Carolina",
status: "Open",
description: "Support faculty research projects through data collection, analysis, and documentation. Assist with literature reviews and experiment preparation.",
salary: "$20 - $25 per hour",
icon: "ri-flask-line"
},
{
title: "Marketing Coordinator",
department: "Marketing",
type: "Full-time",
location: "North Carolina",
status: "Open",
description: "Develop and implement marketing strategies for campus events and programs. Manage social media presence and create promotional materials.",
salary: "$20 - $26 per hour",
icon: "ri-megaphone-line"
}
]
};
const jobData = {
'Part-time Instructor': {
description: 'We are seeking qualified part-time instructors to teach various courses in our academic programs. You will be responsible for delivering engaging lectures, creating course materials, evaluating student performance, and maintaining academic standards. This position offers flexible scheduling and the opportunity to contribute to student success.',
requirements: [
'Master\'s degree in relevant field required',
'Previous teaching experience preferred',
'Excellent communication and presentation skills',
'Ability to work flexible hours including evenings',
'Strong organizational and time management skills'
],
benefits: [
'Competitive hourly compensation',
'Flexible scheduling options',
'Professional development opportunities',
'Access to campus resources and facilities',
'Opportunity for course development'
]
},
'Utility Staff': {
description: 'Join our facilities team as a utility staff member responsible for maintaining our campus infrastructure. Your duties will include general maintenance tasks, cleaning and sanitizing facilities, minor repairs, and ensuring all campus areas are safe and functional for students and staff.',
requirements: [
'High school diploma or equivalent',
'Previous maintenance experience preferred',
'Physical ability to lift up to 50 pounds',
'Basic knowledge of maintenance tools and equipment',
'Reliable and punctual with strong work ethic'
],
benefits: [
'Full-time position with benefits',
'Health insurance coverage',
'Paid time off and holidays',
'Retirement plan participation',
'On-the-job training provided'
]
},
'Office Secretary': {
description: 'We are looking for an organized and professional office secretary to handle administrative tasks in our main office. You will manage correspondence, schedule appointments, maintain records, assist students and staff with inquiries, and provide general administrative support to ensure smooth office operations.',
requirements: [
'High school diploma required, associate degree preferred',
'Proficiency in Microsoft Office Suite',
'Excellent written and verbal communication skills',
'Strong organizational and multitasking abilities',
'Customer service experience preferred'
],
benefits: [
'Full-time position with comprehensive benefits',
'Health, dental, and vision insurance',
'Paid vacation and sick leave',
'Professional development opportunities',
'Friendly and supportive work environment'
]
}
};
document.querySelectorAll('.view-details-btn').forEach(button => {
button.addEventListener('click', function() {
const jobId = this.getAttribute('data-job-id');

if (!jobId) {
alert('Job ID not found');
return;
}

// Hide job listings, header, and search/filters, then show detailed view
document.getElementById('jobListings').style.display = 'none';
document.getElementById('jobHeader').style.display = 'none';
document.getElementById('searchFilters').style.display = 'none';
document.getElementById('jobDetailView').classList.remove('hidden');

// Show loading state
document.getElementById('detailJobTitle').textContent = 'Loading...';
document.getElementById('detailJobMeta').innerHTML = 'Loading...';
document.getElementById('detailSalary').querySelector('span').textContent = 'Loading...';

// Fetch job details from database
fetch(`get_job_details.php?job_id=${jobId}`)
.then(response => response.json())
.then(data => {
if (data.success) {
const job = data.job;

// Populate header information
document.getElementById('detailJobTitle').textContent = job.job_title;

// Create meta information with icons
const metaHTML = `
<div class="flex items-center">
<i class="ri-building-line mr-2"></i>
<span>${job.department_role || 'Not specified'}</span>
</div>
<div class="flex items-center">
<i class="ri-time-line mr-2"></i>
<span>${job.job_type || 'Not specified'}</span>
</div>
<div class="flex items-center">
<i class="ri-map-pin-line mr-2"></i>
<span>${job.locations || 'Not specified'}</span>
</div>
`;
document.getElementById('detailJobMeta').innerHTML = metaHTML;

// Populate salary
document.getElementById('detailSalary').querySelector('span').textContent = job.salary_range || 'Not specified';

// Populate deadline
if (job.application_deadline) {
const deadline = new Date(job.application_deadline);
const options = { year: 'numeric', month: 'long', day: '2-digit' };
document.getElementById('detailDeadlineDate').textContent = deadline.toLocaleDateString('en-US', options);
} else {
document.getElementById('detailDeadlineDate').textContent = 'Not specified';
}

// Populate qualifications
document.getElementById('detailEducation').textContent = job.education || 'Not specified';
document.getElementById('detailExperience').textContent = job.experience || 'Not specified';
document.getElementById('detailTraining').textContent = job.training || 'Not specified';
document.getElementById('detailEligibility').textContent = job.eligibility || 'Not specified';

// Populate job requirements
const requirementsContainer = document.getElementById('detailJobRequirements');
if (job.job_requirements && job.job_requirements.trim()) {
const requirementPoints = job.job_requirements.split('\n').filter(point => point.trim().length > 0);
requirementsContainer.innerHTML = requirementPoints.map(point => `<p>• ${point.trim()}</p>`).join('');
document.getElementById('requirementsSection').style.display = 'block';
} else {
document.getElementById('requirementsSection').style.display = 'none';
}

// Populate competency
document.getElementById('detailCompetency').textContent = job.competency || 'Not specified';

// Populate job description
const descriptionContainer = document.getElementById('detailJobDescription');
if (job.job_description && job.job_description.trim()) {
const descriptionPoints = job.job_description.split('\n').filter(point => point.trim().length > 0);
descriptionContainer.innerHTML = descriptionPoints.map(point => `<p>• ${point.trim()}</p>`).join('');
} else {
descriptionContainer.innerHTML = '<p>No description available</p>';
}


// Set up Apply button with job data
const detailApplyBtn = document.getElementById('detailApplyBtn');
detailApplyBtn.setAttribute('data-job-id', jobId);
detailApplyBtn.setAttribute('data-job-title', job.job_title);

} else {
alert('Error loading job details: ' + data.error);
// Go back to job listings on error
document.getElementById('jobDetailView').classList.add('hidden');
document.getElementById('jobListings').style.display = 'block';
document.getElementById('jobHeader').style.display = 'block';
document.getElementById('searchFilters').style.display = 'flex';
}
})
.catch(error => {
console.error('Error fetching job details:', error);
alert('Error loading job details. Please try again.');
// Go back to job listings on error
document.getElementById('jobDetailView').classList.add('hidden');
document.getElementById('jobListings').style.display = 'block';
document.getElementById('jobHeader').style.display = 'block';
document.getElementById('searchFilters').style.display = 'flex';
});

// Scroll to top
window.scrollTo(0, 0);
});
});

// Back to Jobs button functionality
document.getElementById('backToJobs').addEventListener('click', function() {
document.getElementById('jobDetailView').classList.add('hidden');
document.getElementById('jobListings').style.display = 'block';
document.getElementById('jobHeader').style.display = 'block';
document.getElementById('searchFilters').style.display = 'flex';
window.scrollTo(0, 0);
});

// Make the detailed view Apply button work with existing application system
document.addEventListener('click', function(e) {
if (e.target && e.target.id === 'detailApplyBtn') {
const jobId = e.target.getAttribute('data-job-id');
const jobTitle = e.target.getAttribute('data-job-title');

if (jobId && jobTitle) {
// Store job info for terms modal (same as regular Apply buttons)
window.selectedJobId = jobId;
window.selectedJobTitle = jobTitle;

// Show terms modal
const termsModalElement = document.getElementById('termsModal');
if (termsModalElement) {
termsModalElement.style.display = 'flex';
}
}
}
});
closeModal.addEventListener('click', function() {
modal.classList.add('hidden');
modal.classList.remove('flex');
});
modal.addEventListener('click', function(e) {
if (e.target === modal) {
modal.classList.add('hidden');
modal.classList.remove('flex');
}
});

// Handle modal Apply button
const modalApplyBtn = document.getElementById('modalApplyBtn');
if (modalApplyBtn) {
modalApplyBtn.addEventListener('click', function() {
// Use the stored job info from the modal
if (window.modalJobId && window.modalJobTitle) {
// Store job info for terms modal
window.selectedJobId = window.modalJobId;
window.selectedJobTitle = window.modalJobTitle;

// Close job details modal
modal.classList.add('hidden');
modal.classList.remove('flex');

// Show terms modal (same as regular Apply Now button)
const termsModalElement = document.getElementById('termsModal');
if (termsModalElement) {
termsModalElement.style.display = 'flex';
}
}
});
}
});
</script>
<script id="pagination">
document.addEventListener('DOMContentLoaded', function() {
function createJobCard(job) {
return `
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
<div class="flex justify-between items-start mb-4">
<div>
<h3 class="text-xl font-semibold text-gray-900 mb-2">${job.title}</h3>
<div class="flex items-center text-sm text-gray-600 space-x-4">
<span class="flex items-center">
<div class="w-4 h-4 flex items-center justify-center mr-1">
<i class="${job.icon}"></i>
</div>
${job.department}
</span>
<span class="flex items-center">
<div class="w-4 h-4 flex items-center justify-center mr-1">
<i class="ri-time-line"></i>
</div>
${job.type}
</span>
<span class="flex items-center">
<div class="w-4 h-4 flex items-center justify-center mr-1">
<i class="ri-map-pin-line"></i>
</div>
${job.location}
</span>
</div>
</div>
<span class="${job.status === 'Open' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'} px-3 py-1 rounded-full text-sm font-medium">${job.status}</span>
</div>
<p class="text-gray-700 mb-4">${job.description}</p>
<div class="flex items-center justify-between">
<div class="text-lg font-semibold text-gray-900">${job.salary}</div>
<div class="flex space-x-3">
<button class="px-4 py-2 text-primary border border-primary rounded-lg hover:bg-primary hover:text-white transition-colors text-sm whitespace-nowrap !rounded-button">View Details</button>
${job.status === 'Open' ?
'<button class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors text-sm whitespace-nowrap !rounded-button">Apply Now</button>' :
'<button class="px-4 py-2 bg-gray-300 text-gray-600 rounded-lg cursor-not-allowed text-sm whitespace-nowrap !rounded-button" disabled>Applied</button>'
}
</div>
</div>
</div>
`;
}
function updateJobListings(page) {
const jobGrid = document.querySelector('.grid.gap-6');
const jobs = jobListings[page];
jobGrid.innerHTML = jobs.map(job => createJobCard(job)).join('');
history.pushState({page}, '', `?page=${page}`);
document.querySelectorAll('.flex.space-x-2 button').forEach(btn => {
if (btn.textContent === page.toString()) {
btn.classList.add('bg-primary', 'text-white');
btn.classList.remove('border', 'border-gray-300', 'hover:bg-gray-50');
} else if (!btn.querySelector('i')) {
btn.classList.remove('bg-primary', 'text-white');
btn.classList.add('border', 'border-gray-300', 'hover:bg-gray-50');
}
});
}
document.querySelectorAll('.flex.space-x-2 button').forEach(button => {
if (!button.querySelector('i')) {
button.addEventListener('click', function() {
const page = parseInt(this.textContent);
if (jobListings[page]) {
updateJobListings(page);
}
});
}
});
});
</script>
<script id="applyButton">
document.addEventListener('DOMContentLoaded', function() {
// Use dynamic terms modal system only

// Legacy application form removed - now using full-page wizard
// Terms modal is created dynamically by showTermsModal() function

// Apply button handlers moved to dynamic system below

// Modal apply button uses dynamic terms modal
document.getElementById('modalApplyBtn')?.addEventListener('click', function() {
    const jobModal = document.getElementById('jobModal');
    jobModal.classList.add('hidden');
    jobModal.classList.remove('flex');
    // Use dynamic terms modal
    showTermsModal();
});

// Terms modal handlers moved to dynamic system

// Legacy application modal handlers removed

// Legacy file upload handlers removed - now handled in wizard
});
</script>

<script>
    !function (t, e) { var o, n, p, r; e.__SV || (window.posthog = e, e._i = [], e.init = function (i, s, a) { function g(t, e) { var o = e.split("."); 2 == o.length && (t = t[o[0]], e = o[1]), t[e] = function () { t.push([e].concat(Array.prototype.slice.call(arguments, 0))) } } (p = t.createElement("script")).type = "text/javascript", p.crossOrigin = "anonymous", p.async = !0, p.src = s.api_host.replace(".i.posthog.com", "-assets.i.posthog.com") + "/static/array.js", (r = t.getElementsByTagName("script")[0]).parentNode.insertBefore(p, r); var u = e; for (void 0 !== a ? u = e[a] = [] : a = "posthog", u.people = u.people || [], u.toString = function (t) { var e = "posthog"; return "posthog" !== a && (e += "." + a), t || (e += " (stub)"), e }, u.people.toString = function () { return u.toString(1) + ".people (stub)" }, o = "init capture register register_once register_for_session unregister unregister_for_session getFeatureFlag getFeatureFlagPayload isFeatureEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSessionId getSurveys getActiveMatchingSurveys renderSurvey canRenderSurvey getNextSurveyStep identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags reset get_distinct_id getGroups get_session_id get_session_replay_url alias set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_property getSessionProperty createPersonProfile opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing clear_opt_in_out_capturing debug".split(" "), n = 0; n < o.length; n++)g(u, o[n]); e._i.push([i, s, a]) }, e.__SV = 1) }(document, window.posthog || []);
    posthog.init('phc_t9tkQZJiyi2ps9zUYm8TDsL6qXo4YmZx0Ot5rBlAlEd', {
        api_host: 'https://us.i.posthog.com',
        autocapture: false,
        capture_pageview: false,
        capture_pageleave: false,
        capture_performance: {
            web_vitals: false,
        },
        rageclick: false,
    })
    

</script>


<script>
document.addEventListener('DOMContentLoaded', function () {
  const profileLink = document.getElementById('profileLink');
  const applicationsLink = document.getElementById('applicationsLink');
  const dashboardLink = document.getElementById('dashboardLink');
  const mainContent = document.getElementById('mainContent');

  // Function to update active navigation state
  function updateActiveNavigation(activeSection) {
    // Remove active class from all nav links
    document.querySelectorAll('.nav-link').forEach(link => {
      link.classList.remove('active');
    });
    
    // Add active class to the current section
    const activeLink = document.querySelector(`[data-section="${activeSection}"]`);
    if (activeLink) {
      activeLink.classList.add('active');
    }
    
    // Save active section to localStorage for persistence
    localStorage.setItem('activeNavSection', activeSection);
  }

  // Check if coming from email link
  const urlParams = new URLSearchParams(window.location.search);
  const viewParam = urlParams.get('view');
  
  // Set initial active state
  let initialSection = 'dashboard';
  if (viewParam === 'applications') {
    initialSection = 'applications';
  } else {
    initialSection = localStorage.getItem('activeNavSection') || 'dashboard';
  }
  
  updateActiveNavigation(initialSection);

  // Function to load content dynamically
  function loadContent(url, callback) {
    fetch(url)
      .then((response) => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.text();
      })
      .then((html) => {
        mainContent.innerHTML = html;

        // CRITICAL: Execute scripts in the loaded content
        const scripts = mainContent.querySelectorAll('script');
        scripts.forEach((oldScript) => {
          const newScript = document.createElement('script');
          Array.from(oldScript.attributes).forEach(attr => {
            newScript.setAttribute(attr.name, attr.value);
          });
          newScript.textContent = oldScript.textContent;
          oldScript.parentNode.replaceChild(newScript, oldScript);
          console.log('✅ Script executed from loaded content');
        });

        // Reinitialize event listeners for the loaded content
        if (typeof callback === 'function') {
          callback();
        }
      })
      .catch((error) => {
        console.error('Error loading content:', error);
        alert('Failed to load content. Please try again later.');
      });
  }

  // Load My Applications content
  applicationsLink.addEventListener('click', function (e) {
    e.preventDefault();
    updateActiveNavigation('applications');
    loadContent('user_application.php'); // Event listeners are built into user_application.php
  });

  // Load Profile content
  profileLink.addEventListener('click', function (e) {
    e.preventDefault();
    updateActiveNavigation('profile');
    loadContent('user_profile.php', initializeProfileListeners);
  });

  // Dashboard link (return to main dashboard)
  dashboardLink.addEventListener('click', function (e) {
    e.preventDefault();
    updateActiveNavigation('dashboard');
    // Reload the page to show the main dashboard content
    location.reload();
  });
  
  // Check if there's a pending wizard to open - if so, skip content loading
  const hasPendingWizard = sessionStorage.getItem('openApplicationId');
  
  if (!hasPendingWizard) {
    // Auto-load content based on saved state or URL parameter
    if (viewParam === 'applications') {
      // Coming from email link
      loadContent('user_application.php');
      // Clear the URL parameter to avoid reloading on refresh
      window.history.replaceState({}, document.title, window.location.pathname);
    } else if (initialSection === 'applications') {
      // Restore My Applications if it was the last viewed section
      loadContent('user_application.php');
    } else if (initialSection === 'profile') {
      // Restore Profile if it was the last viewed section
      loadContent('user_profile.php', initializeProfileListeners);
    }
    // If initialSection is 'dashboard', do nothing (default content is already shown)
  } else {
    console.log('⏭️ Skipping content auto-load - wizard pending');
  }

  // Notification functionality - ensure DOM elements exist
  let notificationBtn, notificationDropdown, notificationBadge, notificationsList, markAllReadBtn;
  
  function initializeNotificationElements() {
    notificationBtn = document.getElementById('notificationBtn');
    notificationDropdown = document.getElementById('notificationDropdown');
    notificationBadge = document.getElementById('notificationBadge');
    notificationsList = document.getElementById('notificationsList');
    markAllReadBtn = document.getElementById('markAllRead');
    
    console.log('Notification elements initialized:', {
      notificationBtn: !!notificationBtn,
      notificationDropdown: !!notificationDropdown,
      notificationBadge: !!notificationBadge,
      notificationsList: !!notificationsList,
      markAllReadBtn: !!markAllReadBtn
    });
  }
  
  // Initialize elements when DOM is ready
  initializeNotificationElements();

  // Toggle notification dropdown
  function setupNotificationListeners() {
    if (!notificationBtn || !notificationDropdown) {
      console.error('Notification elements not found, retrying in 500ms');
      setTimeout(setupNotificationListeners, 500);
      return;
    }
    
    // Remove duplicate event listener - this is handled in the main DOMContentLoaded section
  }
  
  setupNotificationListeners();

  // Note: Dropdown click handling is already done above, no need to duplicate

  // Mark all notifications as read
  function setupMarkAllReadListener() {
    if (markAllReadBtn) {
      markAllReadBtn.addEventListener('click', function() {
        fetch('mark_notification_read.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'mark_all=true'
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            loadNotifications();
          }
        })
        .catch(error => console.error('Error:', error));
      });
    }
  }
  
  setupMarkAllReadListener();

  // Load notifications function
  function loadNotifications() {
    console.log('Loading notifications...');
    
    // Ensure elements exist before making request
    if (!notificationsList) {
      initializeNotificationElements();
      if (!notificationsList) {
        console.error('Cannot load notifications - notificationsList element not found');
        return;
      }
    }
    
    fetch('get_notifications.php')
      .then(response => {
        console.log('Response status:', response.status);
        return response.json();
      })
      .then(data => {
        console.log('Notification response:', data);
        if (data.success) {
          console.log('Found', data.notifications.length, 'notifications for user_id:', data.debug_user_id);
          displayNotifications(data.notifications);
          updateNotificationBadge(data.unread_count);
        } else {
          console.error('Failed to load notifications:', data.error);
          notificationsList.innerHTML = '<div class="p-4 text-center text-gray-500"><p class="text-sm">Failed: ' + (data.error || 'Unknown error') + '</p></div>';
        }
      })
      .catch(error => {
        console.error('Error loading notifications:', error);
        if (notificationsList) {
          notificationsList.innerHTML = '<div class="p-4 text-center text-red-500"><p class="text-sm">Network Error</p></div>';
        }
      });
  }

  // Display notifications
  function displayNotifications(notifications) {
    console.log('displayNotifications called with:', notifications);
    
    // Re-initialize elements if needed
    if (!notificationsList) {
      initializeNotificationElements();
    }
    
    if (!notificationsList) {
      console.error('notificationsList element not found!');
      return;
    }
    
    console.log('notificationsList element found:', notificationsList);
    
    if (notifications.length === 0) {
      console.log('No notifications to display');
      notificationsList.innerHTML = `
        <div class="p-6 text-center text-gray-500">
          <i class="ri-notification-off-line text-3xl mb-2 block"></i>
          <p class="text-sm">No notifications yet</p>
        </div>`;
      return;
    }

    let html = '';
    notifications.forEach((notification, index) => {
      console.log(`Processing notification ${index}:`, notification);
      const timeAgo = getTimeAgo(notification.created_at);
      const iconClass = getNotificationIcon(notification.type);
      const isUnread = notification.is_read == '0' || notification.is_read === false;
      const bgClass = isUnread ? 'bg-blue-50 hover:bg-blue-100' : 'hover:bg-gray-50';
      
      html += `
        <div class="p-4 ${bgClass} border-b border-gray-100 cursor-pointer transition-colors" onclick="markNotificationRead(${notification.id})">
          <div class="flex items-start">
            <div class="w-10 h-10 ${iconClass.bgClass} rounded-full flex items-center justify-center mr-3 flex-shrink-0">
              <i class="${iconClass.iconClass}"></i>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-gray-900 truncate">${notification.title || 'Notification'}</p>
                ${isUnread ? '<div class="w-2 h-2 bg-blue-500 rounded-full ml-2 flex-shrink-0"></div>' : ''}
              </div>
              <p class="text-sm text-gray-700 mt-1 leading-relaxed">${notification.message || 'No message content'}</p>
              <p class="text-xs text-gray-500 mt-2">${timeAgo}</p>
            </div>
          </div>
        </div>
      `;
    });
    
    console.log('Generated HTML length:', html.length);
    console.log('Setting innerHTML...');
    
    try {
      notificationsList.innerHTML = html;
      console.log('Successfully set innerHTML');
      console.log('Current notificationsList content:', notificationsList.innerHTML.substring(0, 200));
    } catch (error) {
      console.error('Error setting innerHTML:', error);
    }
  }

  // Update notification badge
  function updateNotificationBadge(count) {
    console.log('Updating notification badge with count:', count);
    if (notificationBadge) {
      if (count > 0) {
        notificationBadge.style.display = 'block';
        console.log('Badge shown');
      } else {
        notificationBadge.style.display = 'none';
        console.log('Badge hidden');
      }
    } else {
      console.error('notificationBadge element not found');
    }
  }

  // Get notification icon based on type
  function getNotificationIcon(type) {
    const icons = {
      'info': {
        iconClass: 'ri-information-line text-blue-600',
        bgClass: 'bg-blue-100'
      },
      'success': {
        iconClass: 'ri-check-line text-green-600',
        bgClass: 'bg-green-100'
      },
      'warning': {
        iconClass: 'ri-alert-line text-yellow-600',
        bgClass: 'bg-yellow-100'
      },
      'error': {
        iconClass: 'ri-close-line text-red-600',
        bgClass: 'bg-red-100'
      }
    };
    return icons[type] || icons['info'];
  }

  // Get time ago string
  function getTimeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' minutes ago';
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hours ago';
    return Math.floor(diffInSeconds / 86400) + ' days ago';
  }

  // Mark notification as read
  window.markNotificationRead = function(notificationId) {
    fetch('mark_notification_read.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'notification_id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        loadNotifications();
      }
    })
    .catch(error => console.error('Error:', error));
  };

  // Load notifications on page load
  loadNotifications();
  
  // Auto-refresh notifications every 30 seconds
  setInterval(loadNotifications, 30000);

  // Note: Event listeners for My Applications are now handled in user_application.php
  // This old function has been removed to prevent conflicts with the new progress modal

  // Reinitialize event listeners for Profile
  function initializeProfileListeners() {
    // Tab Navigation
    setTimeout(function() {
      const tabButtons = document.querySelectorAll('.tab-btn');
      const tabContents = document.querySelectorAll('.tab-content');

      // Function to switch tabs
      function switchTab(targetTabId, clickedButton) {
        // Hide all tab contents
        tabContents.forEach(content => {
          content.classList.add('hidden');
        });

        // Remove active state from all tab buttons
        tabButtons.forEach(btn => {
          btn.classList.remove('border-primary', 'text-primary');
          btn.classList.add('border-transparent', 'text-gray-500');
        });

        // Show target content
        const targetContent = document.getElementById(targetTabId);
        if (targetContent) {
          targetContent.classList.remove('hidden');
        }

        // Add active state to clicked button
        if (clickedButton) {
          clickedButton.classList.remove('border-transparent', 'text-gray-500');
          clickedButton.classList.add('border-primary', 'text-primary');
        }
      }

      // Add click event listeners to tab buttons
      tabButtons.forEach(button => {
        button.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          const targetTab = this.getAttribute('data-tab');
          if (targetTab) {
            switchTab(targetTab, this);
          }
        });
      });

      // Initialize - make sure education tab is active by default
      if (tabButtons.length > 0) {
        const educationTab = document.querySelector('[data-tab="education"]');
        if (educationTab) {
          switchTab('education', educationTab);
        }
      }
    }, 100);

    // Modal Handlers
    const educationModal = document.getElementById('educationModal');
    const experienceModal = document.getElementById('experienceModal');
    const skillModal = document.getElementById('skillModal');

    const addEducationBtn = document.getElementById('addEducationBtn');
    const addExperienceBtn = document.getElementById('addExperienceBtn');
    const addSkillBtn = document.getElementById('addSkillBtn');

    const closeEducationModalBtn = document.getElementById('closeEducationModal');
    const cancelEducationBtn = document.getElementById('cancelEducationBtn');
    const closeExperienceModalBtn = document.getElementById('closeExperienceModal');
    const cancelExperienceBtn = document.getElementById('cancelExperienceBtn');
    const closeSkillModalBtn = document.getElementById('closeSkillModal');
    const cancelSkillBtn = document.getElementById('cancelSkillBtn');

    function closeModal(modal) {
      if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      }
    }

    if (addEducationBtn && educationModal) {
      addEducationBtn.addEventListener('click', (e) => {
        e.preventDefault();
        educationModal.classList.remove('hidden');
        educationModal.classList.add('flex');
      });
    }

    if (addExperienceBtn && experienceModal) {
      addExperienceBtn.addEventListener('click', (e) => {
        e.preventDefault();
        experienceModal.classList.remove('hidden');
        experienceModal.classList.add('flex');
      });
    }

    if (addSkillBtn && skillModal) {
      addSkillBtn.addEventListener('click', (e) => {
        e.preventDefault();
        skillModal.classList.remove('hidden');
        skillModal.classList.add('flex');
      });
    }

    if (closeEducationModalBtn) {
      closeEducationModalBtn.addEventListener('click', () => closeModal(educationModal));
    }
    if (cancelEducationBtn) {
      cancelEducationBtn.addEventListener('click', () => closeModal(educationModal));
    }
    if (closeExperienceModalBtn) {
      closeExperienceModalBtn.addEventListener('click', () => closeModal(experienceModal));
    }
    if (cancelExperienceBtn) {
      cancelExperienceBtn.addEventListener('click', () => closeModal(experienceModal));
    }
    if (closeSkillModalBtn) {
      closeSkillModalBtn.addEventListener('click', () => closeModal(skillModal));
    }
    if (cancelSkillBtn) {
      cancelSkillBtn.addEventListener('click', () => closeModal(skillModal));
    }

    // Handle "I currently work here" checkbox
    const isCurrentCheckbox = document.getElementById('is_current');
    const endDateInput = document.getElementById('end_date');
    
    if (isCurrentCheckbox && endDateInput) {
      isCurrentCheckbox.addEventListener('change', function() {
        if (this.checked) {
          endDateInput.disabled = true;
          endDateInput.value = '';
          endDateInput.style.backgroundColor = '#f3f4f6';
        } else {
          endDateInput.disabled = false;
          endDateInput.style.backgroundColor = '';
        }
      });
    }

    // Handle skill level selection
    const skillLevelButtons = document.querySelectorAll('.skill-level');
    const skillLevelInput = document.getElementById('skill_level');
    
    if (skillLevelButtons.length > 0 && skillLevelInput) {
      skillLevelButtons.forEach(button => {
        button.addEventListener('click', function(e) {
          e.preventDefault();
          const level = parseInt(this.getAttribute('data-level'));
          skillLevelInput.value = level;
          
          skillLevelButtons.forEach((btn, index) => {
            if (index < level) {
              btn.classList.remove('bg-gray-300');
              btn.classList.add('bg-primary');
            } else {
              btn.classList.remove('bg-primary');
              btn.classList.add('bg-gray-300');
            }
          });
        });
      });
    }

    // Personal Information Edit functionality is now handled in initializeProfileListeners()

    // Universal function to update all profile pictures on the page
    function updateAllProfilePictures(profilePictureUrl) {
      // Update header profile picture
      const headerProfilePicture = document.querySelector('#profileDropdownBtn .w-8.h-8');
      if (headerProfilePicture) {
        headerProfilePicture.innerHTML = `<img src="${profilePictureUrl}" alt="Profile Picture" class="w-full h-full object-cover">`;
      }
      
      // Update any other profile pictures that might exist
      const allProfileContainers = document.querySelectorAll('[id*="profilePicture"], [class*="profile-picture"]');
      allProfileContainers.forEach(container => {
        if (container.id !== 'profilePictureContainer') { // Don't update the main one again
          const img = container.querySelector('img');
          if (img) {
            img.src = profilePictureUrl;
          }
        }
      });
    }

    // Profile Picture Upload Functionality
    const uploadPhotoBtn = document.getElementById('uploadPhotoBtn');
    const photoUpload = document.getElementById('photoUpload');
    const profilePictureContainer = document.getElementById('profilePictureContainer');

    if (uploadPhotoBtn && photoUpload) {
      uploadPhotoBtn.addEventListener('click', function() {
        photoUpload.click();
      });

      photoUpload.addEventListener('change', function() {
        if (this.files && this.files[0]) {
          const file = this.files[0];
          
          // Validate file size (5MB max)
          if (file.size > 5 * 1024 * 1024) {
            if (typeof showNotification === 'function') {
              showNotification('File size must be less than 5MB', 'error');
            }
            return;
          }
          
          // Validate file type
          const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
          if (!allowedTypes.includes(file.type)) {
            if (typeof showNotification === 'function') {
              showNotification('Please select a valid image file (JPG, PNG, GIF)', 'error');
            }
            return;
          }
          
          // Create FormData for upload
          const formData = new FormData();
          formData.append('profile_picture', file);
          formData.append('upload_profile_picture', '1');
          
          // Show loading state
          const originalContent = profilePictureContainer.innerHTML;
          profilePictureContainer.innerHTML = '<div class="flex items-center justify-center"><i class="ri-loader-4-line text-white text-2xl animate-spin"></i></div>';
          
          // Upload file
          fetch('save_profile_data.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Update profile picture display
              if (data.profile_picture_url) {
                profilePictureContainer.innerHTML = `<img src="${data.profile_picture_url}" alt="Profile Picture" class="w-full h-full object-cover" id="profileImage">`;
                
                // Update all profile pictures on the page immediately
                updateAllProfilePictures(data.profile_picture_url);
              }
              if (typeof showNotification === 'function') {
                showNotification('Profile photo updated successfully!', 'success');
              }
            } else {
              // Restore original content on error
              profilePictureContainer.innerHTML = originalContent;
              if (typeof showNotification === 'function') {
                showNotification('Error: ' + data.message, 'error');
              }
            }
          })
          .catch(error => {
            console.error('Error:', error);
            // Restore original content on error
            profilePictureContainer.innerHTML = originalContent;
            if (typeof showNotification === 'function') {
              showNotification('Error uploading profile picture', 'error');
            }
          });
        }
      });
    }

    // Handle form submissions with AJAX to show notifications
    const educationForm = document.getElementById('educationForm');
    const experienceForm = document.getElementById('experienceForm');
    const skillForm = document.getElementById('skillForm');

    if (educationForm) {
      educationForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('save_profile_data.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showNotification(data.message, 'success');
            educationForm.reset();
            closeModal(educationModal);
            // Add new education entry to the UI
            addEducationToUI(data.data);
          } else {
            showNotification(data.message, 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showNotification('Error adding education. Please try again.', 'error');
        });
      });
    }

    if (experienceForm) {
      experienceForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('save_profile_data.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showNotification(data.message, 'success');
            experienceForm.reset();
            closeModal(experienceModal);
            // Reset checkbox and end date field
            const isCurrentCheckbox = document.getElementById('is_current');
            const endDateInput = document.getElementById('end_date');
            if (isCurrentCheckbox) isCurrentCheckbox.checked = false;
            if (endDateInput) {
              endDateInput.disabled = false;
              endDateInput.style.backgroundColor = '';
            }
            // Add new experience entry to the UI
            addExperienceToUI(data.data);
          } else {
            showNotification(data.message, 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showNotification('Error adding work experience. Please try again.', 'error');
        });
      });
    }

    if (skillForm) {
      skillForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('save_profile_data.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showNotification(data.message, 'success');
            skillForm.reset();
            closeModal(skillModal);
            // Reset skill level buttons
            if (skillLevelButtons.length > 0) {
              skillLevelButtons.forEach(btn => {
                btn.classList.remove('bg-primary');
                btn.classList.add('bg-gray-300');
              });
            }
            if (skillLevelInput) {
              skillLevelInput.value = '0';
            }
            // Add new skill entry to the UI
            addSkillToUI(data.data);
          } else {
            showNotification(data.message, 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showNotification('Error adding skill. Please try again.', 'error');
        });
      });
    }

    // Functions to dynamically add new entries to UI
    function addEducationToUI(education) {
      const educationList = document.getElementById('educationList');
      if (!educationList) return;
      
      // Remove "no records" message if it exists
      const noRecordsMsg = educationList.querySelector('.text-center');
      if (noRecordsMsg) {
        noRecordsMsg.remove();
      }
      
      const educationCard = document.createElement('div');
      educationCard.className = 'bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow';
      educationCard.innerHTML = `
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <h4 class="font-semibold text-gray-900 text-base">${education.degree}</h4>
            <p class="text-gray-600 mt-1 text-sm">${education.institution}</p>
            <p class="text-gray-500 text-sm mt-1">
              ${education.start_year} - ${education.end_year}
              ${education.gpa ? ` | GPA: ${education.gpa}` : ''}
            </p>
          </div>
          <div class="flex space-x-1 ml-4">
            <button class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors">
              <i class="ri-edit-line text-sm"></i>
            </button>
            <button class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors">
              <i class="ri-delete-bin-line text-sm"></i>
            </button>
          </div>
        </div>
      `;
      educationList.insertBefore(educationCard, educationList.firstChild);
    }
    
    function addExperienceToUI(experience) {
      const experienceList = document.getElementById('experienceList');
      if (!experienceList) return;
      
      // Remove "no records" message if it exists
      const noRecordsMsg = experienceList.querySelector('.text-center');
      if (noRecordsMsg) {
        noRecordsMsg.remove();
      }
      
      const startDate = new Date(experience.start_date).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
      const endDate = experience.end_date ? new Date(experience.end_date).toLocaleDateString('en-US', { month: 'short', year: 'numeric' }) : 'Present';
      
      const experienceCard = document.createElement('div');
      experienceCard.className = 'bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow';
      experienceCard.innerHTML = `
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <h4 class="font-semibold text-gray-900 text-base">${experience.job_title}</h4>
            <p class="text-gray-600 mt-1 text-sm">${experience.company}</p>
            <p class="text-gray-500 text-sm mt-1">
              ${startDate} - ${endDate}
              ${experience.location ? ` | ${experience.location}` : ''}
            </p>
            ${experience.description ? `<p class="text-gray-700 mt-2 text-sm leading-relaxed">${experience.description.replace(/\n/g, '<br>')}</p>` : ''}
          </div>
          <div class="flex space-x-1 ml-4">
            <button class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors">
              <i class="ri-edit-line text-sm"></i>
            </button>
            <button class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors">
              <i class="ri-delete-bin-line text-sm"></i>
            </button>
          </div>
        </div>
      `;
      experienceList.insertBefore(experienceCard, experienceList.firstChild);
    }
    
    function addSkillToUI(skill) {
      const skillsList = document.getElementById('skillsList');
      if (!skillsList) return;
      
      // Remove "no records" message if it exists
      const noRecordsMsg = skillsList.querySelector('.text-center');
      if (noRecordsMsg) {
        noRecordsMsg.remove();
      }
      
      const levels = ['', 'Beginner', 'Novice', 'Intermediate', 'Advanced', 'Expert'];
      let dots = '';
      for (let i = 1; i <= 5; i++) {
        dots += `<div class="w-2.5 h-2.5 rounded-full mr-1 ${i <= skill.skill_level ? 'bg-blue-500' : 'bg-gray-200'}"></div>`;
      }
      
      const skillCard = document.createElement('div');
      skillCard.className = 'bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow';
      skillCard.innerHTML = `
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <h4 class="font-semibold text-gray-900 text-base">${skill.skill_name}</h4>
            <div class="flex items-center mt-2">
              ${dots}
              <span class="text-sm text-gray-500 ml-2">${levels[skill.skill_level]}</span>
            </div>
          </div>
          <div class="flex space-x-1 ml-4">
            <button class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors">
              <i class="ri-edit-line text-sm"></i>
            </button>
            <button class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors">
              <i class="ri-delete-bin-line text-sm"></i>
            </button>
          </div>
        </div>
      `;
      skillsList.insertBefore(skillCard, skillsList.firstChild);
    }

    // Personal Information Edit Functionality
    const editPersonalBtn = document.getElementById('editPersonalBtn');
    const cancelPersonalBtn = document.getElementById('cancelPersonalBtn');
    const savePersonalBtn = document.getElementById('savePersonalBtn');
    const personalActions = document.getElementById('personalActions');
    const personalInputs = document.querySelectorAll('#personalInfo input[name]');
    const addressTextarea = document.querySelector('textarea[name="applicant_address"]');

    let originalValues = {};

    console.log('Profile loaded - checking elements:');
    console.log('editPersonalBtn:', editPersonalBtn);
    console.log('personalActions:', personalActions);
    console.log('personalInputs count:', personalInputs.length);
    console.log('addressTextarea:', addressTextarea);

    if (editPersonalBtn && personalActions) {
      editPersonalBtn.addEventListener('click', function() {
        console.log('=== EDIT BUTTON CLICKED ===');
        console.log('Found inputs:', personalInputs.length);
        console.log('Found textarea:', addressTextarea);
        
        // Try multiple ways to find the textarea
        const textarea1 = document.querySelector('textarea[name="applicant_address"]');
        const textarea2 = document.querySelector('#personalInfo textarea');
        const textarea3 = document.querySelector('textarea');
        const allTextareas = document.querySelectorAll('textarea');
        
        console.log('Method 1 - by name:', textarea1);
        console.log('Method 2 - by container:', textarea2);
        console.log('Method 3 - any textarea:', textarea3);
        console.log('All textareas:', allTextareas.length);
        
        personalInputs.forEach(input => {
          originalValues[input.name || input.type] = input.value;
          input.disabled = false;
          console.log('Enabled input:', input.name, input.value);
        });
        
        // Enable textarea using the best available method
        let workingTextarea = addressTextarea || textarea1 || textarea2 || textarea3;
        
        if (workingTextarea) {
          originalValues['applicant_address'] = workingTextarea.value;
          workingTextarea.disabled = false;
          workingTextarea.readOnly = false;
          workingTextarea.style.backgroundColor = '';
          workingTextarea.style.cursor = '';
          workingTextarea.style.color = '';
          console.log('✅ Enabled textarea:', workingTextarea.value);
          console.log('Textarea disabled status:', workingTextarea.disabled);
          console.log('Textarea readonly status:', workingTextarea.readOnly);
        } else {
          console.log('❌ NO TEXTAREA FOUND AT ALL!');
          console.log('DOM ready state:', document.readyState);
          console.log('Personal info container:', document.getElementById('personalInfo'));
        }
        
        personalActions.classList.remove('hidden');
        personalActions.classList.add('flex');
        this.style.display = 'none';
      });
    }

    if (cancelPersonalBtn && personalActions && editPersonalBtn) {
      cancelPersonalBtn.addEventListener('click', function() {
        personalInputs.forEach(input => {
          input.value = originalValues[input.name || input.type] || input.value;
          input.disabled = true;
        });
        // Also reset textarea
        if (addressTextarea) {
          addressTextarea.value = originalValues['applicant_address'] || addressTextarea.value;
          addressTextarea.disabled = true;
          addressTextarea.readOnly = true;
          addressTextarea.style.backgroundColor = '#f9fafb';
          addressTextarea.style.cursor = 'not-allowed';
        }
        personalActions.classList.add('hidden');
        personalActions.classList.remove('flex');
        editPersonalBtn.style.display = 'block';
      });
    }

    if (savePersonalBtn && personalActions && editPersonalBtn) {
      savePersonalBtn.addEventListener('click', function() {
        console.log('Save button clicked');
        
        // Collect form data
        const formData = new FormData();
        formData.append('savePersonal', '1');
        
        personalInputs.forEach(input => {
          if (input.name) {
            formData.append(input.name, input.value);
            console.log('Adding to form:', input.name, input.value);
          }
        });
        
        // Also include textarea
        if (addressTextarea) {
          formData.append('applicant_address', addressTextarea.value);
          console.log('Adding address:', addressTextarea.value);
        }
        
        // Debug form data
        for (let [key, value] of formData.entries()) {
          console.log('FormData:', key, value);
        }
        
        // Send form data to user_profile.php
        fetch('user_profile.php', {
          method: 'POST',
          body: formData
        })
        .then(response => {
          console.log('Response status:', response.status);
          return response.text();
        })
        .then(data => {
          console.log('Response data length:', data.length);
          console.log('Contains success:', data.includes('Personal information updated successfully'));
          
          // Check if the response contains success or error messages
          if (data.includes('Personal information updated successfully')) {
            personalInputs.forEach(input => {
              input.disabled = true;
            });
            // Also handle textarea
            if (addressTextarea) {
              addressTextarea.disabled = true;
              addressTextarea.readOnly = true;
              addressTextarea.style.backgroundColor = '#f9fafb';
              addressTextarea.style.cursor = 'not-allowed';
            }
            personalActions.classList.add('hidden');
            personalActions.classList.remove('flex');
            editPersonalBtn.style.display = 'block';
            showToast('Personal information updated successfully!', 'success');
            // Reload the profile content to show updated data
            setTimeout(() => {
              loadContent('user_profile.php', initializeProfileListeners);
            }, 1000);
          } else if (data.includes('Error')) {
            // Extract error message if possible
            const errorMatch = data.match(/Error[^<]*/);
            const errorMsg = errorMatch ? errorMatch[0] : 'Error saving personal information. Please try again.';
            showToast(errorMsg, 'error');
          } else {
            showToast('Unexpected response. Please try again.', 'error');
            console.log('Full response:', data.substring(0, 500));
          }
        })
        .catch(error => {
          console.error('Fetch error:', error);
          showToast('Network error saving personal information', 'error');
        });
      });
    }

    // Show notification function
    if (typeof showNotification === 'undefined') {
      window.showNotification = function(message, type) {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 ${type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'} px-4 py-3 rounded border z-50`;
        notification.innerHTML = `
          <div class="flex items-center">
            <i class="${type === 'success' ? 'ri-check-line' : 'ri-error-warning-line'} mr-2"></i>
            <span>${message}</span>
          </div>
        `;
        document.body.appendChild(notification);
        setTimeout(() => {
          notification.remove();
        }, 3000);
      };
    }
  }

  // Handle button state changes after successful application
  <?php if (!empty($applied_job_id)): ?>
  const appliedJobId = "<?php echo addslashes($applied_job_id); ?>";
  console.log('Looking for job to mark as applied with ID:', appliedJobId);
  
  // Wait a bit for DOM to be fully ready
  setTimeout(() => {
    let buttonFound = false;
    
    // Find and update the specific job button
    document.querySelectorAll('button').forEach(button => {
      if (button.textContent.trim() === 'Apply Now' && !button.disabled) {
        const buttonJobId = button.getAttribute('data-job-id');
        console.log('Checking job card with ID:', buttonJobId);
        
        if (buttonJobId === appliedJobId) {
          console.log('✅ Found matching job! Updating button for job ID:', buttonJobId);
            
          // Change button to "View Application" state - keep it clickable
          button.textContent = 'View Application';
          button.className = 'px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm whitespace-nowrap !rounded-button';
          button.disabled = false;
          button.setAttribute('data-application-status', 'in-progress');
          
          // Update any "Open" status text to "In Progress"
          const jobCard = button.closest('.bg-white, .job-card, [class*="job"]');
          const statusElements = jobCard.querySelectorAll('*');
          statusElements.forEach(el => {
            if (el.textContent.trim() === 'Open') {
              el.textContent = 'In Progress';
              el.className = el.className.replace(/green/g, 'blue');
            }
          });
          
          buttonFound = true;
        }
      }
    });
    
    if (!buttonFound) {
      console.log('❌ No matching job button found for ID:', appliedJobId);
    }
  }, 100);
  <?php endif; ?>

  // Check for existing applications and update button states on page load
  <?php if (isset($_SESSION['user_id'])): ?>
  fetch('get_user_applications.php')
    .then(response => response.json())
    .then(data => {
      if (data.success && data.applications) {
        console.log('Loaded applications:', data.applications);
        
        // Update buttons for jobs user has already applied to
        document.querySelectorAll('button').forEach(button => {
          if (button.textContent.trim() === 'Apply Now' && !button.disabled) {
            const buttonJobId = button.getAttribute('data-job-id');
            
            // Find matching application
            const application = data.applications.find(app => app.job_id == buttonJobId);
            
            if (application) {
              console.log('✅ Found existing application for job ID:', buttonJobId, 'Status:', application.status);
              
              const status = (application.status || '').toLowerCase();
              const isCompleted = status.includes('hired') || status.includes('reject');
              
              if (isCompleted) {
                // Application is completed (hired or rejected) - disable button
                button.textContent = status.includes('hired') ? 'Hired' : 'Rejected';
                button.className = 'px-4 py-2 bg-gray-300 text-gray-600 rounded-lg cursor-not-allowed text-sm whitespace-nowrap !rounded-button';
                button.disabled = true;
              } else {
                // Application is in progress - keep clickable to view status
                button.textContent = 'View Application';
                button.className = 'px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm whitespace-nowrap !rounded-button';
                button.disabled = false;
                button.setAttribute('data-application-id', application.id);
                button.setAttribute('data-application-status', 'in-progress');
              }
              
              // Update any "Open" status text
              const jobCard = button.closest('.bg-white, .job-card, [class*="job"]');
              if (jobCard) {
                const statusElements = jobCard.querySelectorAll('*');
                statusElements.forEach(el => {
                  if (el.textContent.trim() === 'Open') {
                    el.textContent = isCompleted ? (status.includes('hired') ? 'Hired' : 'Rejected') : 'In Progress';
                    el.className = el.className.replace(/green/g, isCompleted ? 'gray' : 'blue');
                  }
                });
              }
            }
          }
        });
      }
    })
    .catch(error => {
      console.log('Could not load existing applications:', error);
    });
  <?php endif; ?>

  // Optional: handle back/forward navigation by reloading page
  window.addEventListener('popstate', function () {
    location.reload();
  });
});

// Pagination and filtering functionality
let currentPage = 1;
const jobsPerPage = 4;
let totalPages = 1;
let currentFilters = {
  search: '',
  department: '',
  job_type: '',
  sort: 'newest'
};

// Load jobs with pagination and filters
function loadJobs(page = 1, filters = {}) {
  currentPage = page;
  currentFilters = { ...currentFilters, ...filters };
  
  // Show loading state
  document.getElementById('jobsLoading').style.display = 'flex';
  document.getElementById('jobsContainer').innerHTML = '';
  document.getElementById('noJobsMessage').classList.add('hidden');
  document.getElementById('paginationContainer').style.display = 'none';
  
  // Build API URL
  const url = new URL('get_jobs_paginated.php', window.location.origin + window.location.pathname.replace('user.php', ''));
  url.searchParams.append('page', page);
  url.searchParams.append('limit', jobsPerPage);
  
  // Add filter parameters
  if (currentFilters.search) {
    url.searchParams.append('search', currentFilters.search);
  }
  if (currentFilters.department) {
    url.searchParams.append('department', currentFilters.department);
  }
  if (currentFilters.job_type) {
    url.searchParams.append('job_type', currentFilters.job_type);
  }
  if (currentFilters.sort) {
    url.searchParams.append('sort', currentFilters.sort);
  }
  
  fetch(url)
    .then(response => response.json())
    .then(data => {
      // Hide loading state
      document.getElementById('jobsLoading').style.display = 'none';
      
      if (data.success && data.jobs.length > 0) {
        displayJobs(data.jobs);
        updatePagination(data.pagination);
        
        // Update existing application states
        setTimeout(() => {
          updateExistingApplicationStates();
        }, 100);
      } else {
        document.getElementById('noJobsMessage').classList.remove('hidden');
        document.getElementById('paginationContainer').style.display = 'none';
      }
    })
    .catch(error => {
      console.error('Error loading jobs:', error);
      document.getElementById('jobsLoading').style.display = 'none';
      document.getElementById('noJobsMessage').classList.remove('hidden');
    });
}

// Display jobs in the container
function displayJobs(jobs) {
  const container = document.getElementById('jobsContainer');
  container.innerHTML = '';
  
  jobs.forEach(job => {
    const jobCard = document.createElement('div');
    jobCard.className = 'bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow mb-6';
    jobCard.innerHTML = `
      <div class="flex justify-between items-start mb-4">
        <div>
          <h3 class="text-xl font-semibold text-gray-900 mb-2">${escapeHtml(job.job_title)}</h3>
          <div class="flex items-center text-sm text-gray-600 space-x-4">
            <span class="flex items-center">
              <div class="w-4 h-4 flex items-center justify-center mr-1">
                <i class="ri-building-line"></i>
              </div>${escapeHtml(job.department_role)}
            </span>
            <span class="flex items-center">
              <div class="w-4 h-4 flex items-center justify-center mr-1">
                <i class="ri-time-line"></i>
              </div>${escapeHtml(job.job_type)}
            </span>
            <span class="flex items-center">
              <div class="w-4 h-4 flex items-center justify-center mr-1">
                <i class="ri-map-pin-line"></i>
              </div>${escapeHtml(job.locations)}
            </span>
          </div>
        </div>
        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">Open</span>
      </div>
      <p class="text-gray-700 mb-4">${escapeHtml(job.job_description).replace(/\n/g, '<br>')}</p>
      <div class="flex items-center justify-between">
        <div class="text-lg font-semibold text-gray-900">${escapeHtml(job.salary_range)}</div>
        <div class="flex space-x-3">
          <button class="px-4 py-2 text-primary border border-primary rounded-lg hover:bg-primary hover:text-white transition-colors text-sm whitespace-nowrap !rounded-button view-details-btn" 
                  data-job-id="${job.id}">View Details</button>
          <button class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors apply-btn" data-job-id="${job.id}">Apply Now</button>
        </div>
      </div>
    `;
    container.appendChild(jobCard);
  });
  
  // Re-attach event listeners for new buttons
  attachJobEventListeners();
}

// Update pagination controls
function updatePagination(pagination) {
  totalPages = pagination.total_pages;
  
  // Update pagination info
  const paginationInfo = document.getElementById('paginationInfo');
  paginationInfo.textContent = `Showing ${pagination.showing_from}-${pagination.showing_to} of ${pagination.total_jobs} jobs`;
  
  // Remove the search summary update since we're removing it
  
  // Update previous button
  const prevBtn = document.getElementById('prevPageBtn');
  prevBtn.disabled = !pagination.has_prev;
  
  // Update next button
  const nextBtn = document.getElementById('nextPageBtn');
  nextBtn.disabled = !pagination.has_next;
  
  // Update page numbers
  updatePageNumbers(pagination.current_page, pagination.total_pages);
  
  // Update active filters display
  updateActiveFilters();
  
  // Show pagination container
  document.getElementById('paginationContainer').style.display = 'flex';
}

// Update page numbers display
function updatePageNumbers(currentPage, totalPages) {
  const pageNumbers = document.getElementById('pageNumbers');
  pageNumbers.innerHTML = '';
  
  // Calculate which pages to show
  let startPage = Math.max(1, currentPage - 2);
  let endPage = Math.min(totalPages, currentPage + 2);
  
  // Adjust if we're near the beginning or end
  if (currentPage <= 3) {
    endPage = Math.min(5, totalPages);
  }
  if (currentPage >= totalPages - 2) {
    startPage = Math.max(1, totalPages - 4);
  }
  
  // Add first page if not visible
  if (startPage > 1) {
    addPageButton(1);
    if (startPage > 2) {
      addEllipsis();
    }
  }
  
  // Add visible page numbers
  for (let i = startPage; i <= endPage; i++) {
    addPageButton(i, i === currentPage);
  }
  
  // Add last page if not visible
  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      addEllipsis();
    }
    addPageButton(totalPages);
  }
}

// Add page button
function addPageButton(pageNum, isActive = false) {
  const pageNumbers = document.getElementById('pageNumbers');
  const button = document.createElement('button');
  button.className = `px-3 py-2 text-sm rounded-lg transition-colors ${
    isActive 
      ? 'bg-primary text-white' 
      : 'border border-gray-300 hover:bg-gray-50'
  }`;
  button.textContent = pageNum;
  button.onclick = () => loadJobs(pageNum, currentSearchTerm);
  pageNumbers.appendChild(button);
}

// Add ellipsis
function addEllipsis() {
  const pageNumbers = document.getElementById('pageNumbers');
  const ellipsis = document.createElement('span');
  ellipsis.className = 'px-2 text-gray-500';
  ellipsis.textContent = '...';
  pageNumbers.appendChild(ellipsis);
}

// Attach event listeners to job buttons
function attachJobEventListeners() {
  // View Details buttons
  const viewDetailsBtns = document.querySelectorAll('.view-details-btn');
  console.log('🔗 Attaching event listeners to', viewDetailsBtns.length, 'View Details buttons');
  
  viewDetailsBtns.forEach((btn, index) => {
    btn.addEventListener('click', function() {
      const jobId = this.getAttribute('data-job-id');
      console.log('👆 View Details clicked for job ID:', jobId);
      showJobDetails(jobId);
    });
  });
  
  // Apply buttons
  document.querySelectorAll('.apply-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      if (this.disabled) return;
      
      const jobId = this.getAttribute('data-job-id');
      const applicationId = this.getAttribute('data-application-id');
      const applicationStatus = this.getAttribute('data-application-status');
      const buttonText = this.textContent.trim();
      
      // If this is a "View Application" button, load and show the wizard at current step
      if (buttonText === 'View Application' && applicationId) {
        console.log('Opening existing application:', applicationId);
        
        // CRITICAL: Clear ALL previous application data before loading new one
        console.log('Clearing previous application data before loading...');
        
        // Reset BOTH flags to allow new application to load
        window._lastLoadedAppId = null;
        window._isProcessingIndicators = false;
        
        // Clear resubmission docs from previous view
        globalResubmissionDocs = [];
        
        // Clear interview/demo/psych schedule details
        const interviewDetails = document.getElementById('interview_details');
        const demoDetails = document.getElementById('demo_details');
        const hiredDetails = document.getElementById('hired_details');
        if (interviewDetails) {
          interviewDetails.innerHTML = '';
          interviewDetails.classList.add('hidden');
        }
        if (demoDetails) {
          demoDetails.innerHTML = '';
          demoDetails.classList.add('hidden');
        }
        if (hiredDetails) {
          hiredDetails.innerHTML = '';
          hiredDetails.classList.add('hidden');
        }
        
        // Clear status messages
        const interviewApprovedStatus = document.getElementById('interview_approved_status');
        const demoApprovedStatus = document.getElementById('demo_approved_status');
        const psychApprovedStatus = document.getElementById('psych_approved_status');
        if (interviewApprovedStatus) {
          interviewApprovedStatus.innerHTML = '';
          interviewApprovedStatus.classList.add('hidden');
        }
        if (demoApprovedStatus) {
          demoApprovedStatus.innerHTML = '';
          demoApprovedStatus.classList.add('hidden');
        }
        if (psychApprovedStatus) {
          psychApprovedStatus.innerHTML = '';
          psychApprovedStatus.classList.add('hidden');
        }
        
        // Hide psych upload forms
        const psychUploadForm = document.getElementById('psych_upload_form');
        const psychUploadSuccess = document.getElementById('psych_upload_success');
        if (psychUploadForm) psychUploadForm.classList.add('hidden');
        if (psychUploadSuccess) psychUploadSuccess.classList.add('hidden');
        
        // Remove resubmission notices
        document.querySelectorAll('.resubmission-notice').forEach(notice => notice.remove());
        
        try {
          const res = await fetch(`get_application_details.php?id=${applicationId}`);
          const data = await res.json();
          
          console.log('📦 API Response data (location 2):', data);
          console.log('📦 Work Experience from API:', data.work_experience);
          console.log('📦 Education from API:', data.education);
          console.log('📦 Skills from API:', data.skills);
          
          if (!data.success) {
            throw new Error(data.error || 'Failed to load application');
          }
          
          const app = data.application;
          
          // Determine current workflow step
          let workflowStep = 3; // Default to step 3 (Interview)
          const status = (app.status || '').toLowerCase();
          
          if (status.includes('initially hired') || status.includes('hired')) {
            workflowStep = 6; // Admin has marked as hired - show step 6
          } else if (app.psych_exam_receipt) {
            workflowStep = 5; // Psych receipt uploaded, waiting for admin to hire
          } else if (status.includes('demo') && status.includes('passed')) {
            workflowStep = 5; // Demo passed, ready for psychological exam
          } else if (status.includes('demo') || app.demo_date) {
            workflowStep = 4; // Demo scheduled but not yet passed
          } else if (status.includes('interview') && status.includes('passed')) {
            workflowStep = 4; // Interview passed, ready for demo
          } else if (status.includes('interview') || app.interview_date) {
            workflowStep = 3;
          }
          
          console.log('Opening wizard at step:', workflowStep);
          
          // Set job context and application ID globally
          window.selectedJobId = app.job_id;
          window.selectedJobTitle = app.position;
          window.currentApplicationId = applicationId;
          
          // CRITICAL FIX: Store application data globally for setStep() to use
          window.currentApplicationData = app;
          console.log('Stored application data globally:', app);
          
          // Store the actual workflow progress step (for progress indicator)
          window.currentWorkflowStep = workflowStep;
          console.log('Set current workflow progress to step:', workflowStep);
          
          // Set application ID in Step 5 form
          document.getElementById('wizard_psych_app_id').value = applicationId;
          
          // Populate details for each step
          if (workflowStep >= 3) {
            if (app.interview_date) {
              const interviewDate = new Date(app.interview_date);
              const detailsHtml = `
                <p class="text-sm text-green-600 font-medium">✓ Interview scheduled</p>
                <p class="text-sm text-gray-700 mt-2">
                  <i class="ri-calendar-event-line mr-1"></i>
                  ${interviewDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                  at ${interviewDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
                </p>
                ${app.interview_notes ? `<p class="text-sm text-gray-600 mt-2">${app.interview_notes}</p>` : ''}
              `;
              document.getElementById('interview_details').innerHTML = detailsHtml;
              document.getElementById('interview_details').classList.remove('hidden');
              
              // Check if interview is approved (has demo_date set)
              if (app.demo_date) {
                const approvedHtml = `
                  <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-green-700 font-medium flex items-center justify-center">
                      <i class="ri-checkbox-circle-fill mr-2"></i>Interview Approved - You can proceed to Demo Teaching
                    </p>
                  </div>
                `;
                document.getElementById('interview_approved_status').innerHTML = approvedHtml;
                document.getElementById('interview_approved_status').classList.remove('hidden');
                
                // Enable next button
                const nextBtn = document.getElementById('interview_next_btn');
                nextBtn.disabled = false;
                nextBtn.className = 'px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors';
                nextBtn.title = 'Proceed to Demo Teaching';
              }
            } else {
              // No interview scheduled yet
              document.getElementById('interview_status_text').textContent = 'Your application has been submitted. Please wait while the admin reviews your documents and schedules an interview.';
            }
          }
          
          if (workflowStep >= 4) {
            if (app.demo_date) {
              const demoDate = new Date(app.demo_date);
              const detailsHtml = `
                <p class="text-sm text-green-600 font-medium">✓ Demo teaching scheduled</p>
                <p class="text-sm text-gray-700 mt-2">
                  <i class="ri-calendar-event-line mr-1"></i>
                  ${demoDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                  at ${demoDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
                </p>
                ${app.demo_notes ? `<p class="text-sm text-gray-600 mt-2">${app.demo_notes}</p>` : ''}
              `;
              document.getElementById('demo_details').innerHTML = detailsHtml;
              document.getElementById('demo_details').classList.remove('hidden');
              
              // Check if demo has been approved (status = "Demo Passed")
              const nextBtn = document.getElementById('demo_next_btn');
              if (app.status === 'Demo Passed') {
                // Demo approved - allow user to proceed to psychological exam
                const approvedHtml = `
                  <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg">
                    <p class="text-emerald-700 font-medium flex items-center justify-center">
                      <i class="ri-checkbox-circle-fill mr-2"></i>Demo Teaching Approved! You can now proceed to the Psychological Examination
                    </p>
                  </div>
                `;
                document.getElementById('demo_approved_status').innerHTML = approvedHtml;
                document.getElementById('demo_approved_status').classList.remove('hidden');
                
                // Enable next button
                nextBtn.disabled = false;
                nextBtn.className = 'px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors';
                nextBtn.title = 'Proceed to Psychological Exam';
              } else {
                // Demo scheduled but not yet approved - keep button disabled
                const waitingHtml = `
                  <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-yellow-700 font-medium flex items-center justify-center">
                      <i class="ri-time-line mr-2"></i>Waiting for admin to approve your demo teaching performance
                    </p>
                  </div>
                `;
                document.getElementById('demo_approved_status').innerHTML = waitingHtml;
                document.getElementById('demo_approved_status').classList.remove('hidden');
                
                // Keep next button disabled
                nextBtn.disabled = true;
                nextBtn.className = 'px-6 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed';
                nextBtn.title = 'Waiting for demo approval';
              }
            }
          }
          
          if (workflowStep >= 5) {
            document.getElementById('wizard_psych_app_id').value = applicationId;
            
            if (app.psych_exam_receipt) {
              // Receipt already uploaded
              document.getElementById('psych_upload_success').classList.remove('hidden');
              document.getElementById('psych_upload_form').classList.add('hidden');
              
              // Check if psych exam is approved (has initially_hired_date set)
              if (app.initially_hired_date) {
                const approvedHtml = `
                  <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-green-700 font-medium flex items-center justify-center">
                      <i class="ri-checkbox-circle-fill mr-2"></i>Congratulations! You have been hired!
                    </p>
                    <p class="text-green-600 text-sm text-center mt-2">Click the green "Next: Initially Hired" button below to view your hiring details.</p>
                  </div>
                `;
                document.getElementById('psych_approved_status').innerHTML = approvedHtml;
                document.getElementById('psych_approved_status').classList.remove('hidden');
                
                // Only enable next button if status is "Initially Hired"
                console.log('📋 Checking hiring status:', app.status);
                if (app.status && (app.status.toLowerCase().includes('initially hired') || app.status.toLowerCase().includes('hired'))) {
                  console.log('✅ STATUS IS "INITIALLY HIRED" - Enabling button!');
                  const nextBtn = document.getElementById('psych_next_btn');
                  if (nextBtn) {
                    nextBtn.disabled = false;
                    nextBtn.removeAttribute('disabled');
                    nextBtn.className = 'px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors';
                    nextBtn.title = 'Click to View Initial Hiring Status';
                    nextBtn.style.pointerEvents = 'auto';
                    nextBtn.style.cursor = 'pointer';
                    nextBtn.style.opacity = '1';
                    
                    console.log('🎉 Button is now CLICKABLE! Status:', app.status);
                  }
                } else {
                  // Receipt approved but not hired yet - keep button disabled
                  console.log('⏳ Receipt uploaded but status is not "Initially Hired" yet');
                  console.log('Current status:', app.status);
                  console.log('Button will be enabled once admin marks you as "Initially Hired"');
                }
              } else {
                // Receipt uploaded, waiting for admin approval
                const waitingHtml = `
                  <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-yellow-700 font-medium flex items-center justify-center">
                      <i class="ri-time-line mr-2"></i>Receipt submitted - Waiting for admin approval
                    </p>
                  </div>
                `;
                document.getElementById('psych_approved_status').innerHTML = waitingHtml;
                document.getElementById('psych_approved_status').classList.remove('hidden');
              }
            } else {
              // Show upload form
              document.getElementById('psych_upload_form').classList.remove('hidden');
              document.getElementById('psych_status_text').textContent = 'Please upload your psychological exam receipt or proof of completion.';
            }
          }
          
          if (workflowStep >= 6 && app.initially_hired_date) {
            const hiredDate = new Date(app.initially_hired_date);
            const detailsHtml = `
              <p class="text-sm text-gray-700">Date: ${hiredDate.toLocaleDateString()}</p>
              ${app.initially_hired_notes ? `<p class="text-sm text-gray-600 mt-2">${app.initially_hired_notes}</p>` : ''}
            `;
            document.getElementById('hired_details').innerHTML = detailsHtml;
            document.getElementById('hired_details').classList.remove('hidden');
          }
          
          // Parse resubmission documents list (if status is "Resubmission Required")
          let resubmissionDocs = [];
          if (app.status === 'Resubmission Required' && app.resubmission_documents) {
            console.log('📋 Raw resubmission_documents from database:', app.resubmission_documents);
            console.log('📋 Type:', typeof app.resubmission_documents);
            
            try {
              // If already an array (parsed by API), use it directly
              if (Array.isArray(app.resubmission_documents)) {
                resubmissionDocs = app.resubmission_documents;
                console.log('✅ Resubmission docs (already array):', resubmissionDocs);
              } else {
                // Try parsing as JSON first (new format from admin)
                resubmissionDocs = JSON.parse(app.resubmission_documents);
                console.log('✅ Resubmission docs parsed (JSON):', resubmissionDocs);
              }
            } catch (e) {
              console.log('⚠️ JSON parse failed, trying CSV format...');
              // Fallback to comma-separated format
              try {
                resubmissionDocs = app.resubmission_documents.split(',').map(doc => doc.trim());
                console.log('✅ Resubmission docs parsed (CSV):', resubmissionDocs);
              } catch (e2) {
                console.error('❌ Error parsing resubmission documents:', e2);
                console.error('Raw value:', app.resubmission_documents);
              }
            }
          } else {
            console.log('ℹ️ No resubmission required or status is not "Resubmission Required"');
            console.log('Status:', app.status);
            console.log('Resubmission documents:', app.resubmission_documents);
          }
          
          const isResubmissionMode = (app.status === 'Resubmission Required' && resubmissionDocs.length > 0);
          
          // CRITICAL: Set global resubmission docs BEFORE showing wizard
          // This allows applyViewModeRestrictions to skip disabling these inputs
          globalResubmissionDocs = resubmissionDocs;
          window.currentResubmissionDocs = resubmissionDocs; // Store for form validation
          console.log('Set globalResubmissionDocs:', globalResubmissionDocs);
          console.log('Set window.currentResubmissionDocs:', window.currentResubmissionDocs);
          
          // CRITICAL: If resubmission mode, prepare CSS to hide only resubmission document indicators
          if (app.status === 'Resubmission Required') {
            console.log('⚠️ RESUBMISSION MODE DETECTED - Will selectively hide indicators');
            
            // DON'T add blanket CSS - we want to show approved files normally
            // Only specific resubmission documents will get orange overlays
            console.log('✅ Approved documents will show green indicators normally');
          }
          
          // IMPORTANT: Open wizard FIRST in view mode
          console.log('Opening wizard in view mode...');
          showWizard(true); // true = view mode (read-only)
          setStep(workflowStep);
          
          // NOW populate Step 1 and Step 2 AFTER wizard is visible
          setTimeout(() => {
            // Populate Step 1 (Personal Information) with submitted data
            document.getElementById('pf_first_name').value = app.first_name || '';
            document.getElementById('pf_last_name').value = app.last_name || '';
            document.getElementById('pf_email').value = app.applicant_email || '';
            document.getElementById('pf_phone').value = app.contact_num || '';
            document.getElementById('pf_address').value = app.address || '';
            
            // Populate Step 2 form fields (for display purposes)
            document.getElementById('rf_job_id').value = app.job_id || '';
            document.getElementById('rf_job_title').value = app.position || '';
            document.getElementById('rf_full_name').value = app.full_name || '';
            document.getElementById('rf_email').value = app.applicant_email || '';
            document.getElementById('rf_cellphone').value = app.contact_num || '';
            
            // Display work experience, skills, and education from API data
            displayWorkExperienceFromData(data.work_experience || []);
            displaySkillsFromData(data.skills || []);
            displayEducationFromData(data.education || []);
          }, 300);
          
          // NOW add the file indicators after wizard is shown
          // Show which files were uploaded in Step 2 - inside each upload box
          
          // Helper function to add uploaded file indicator
          const addUploadedIndicator = (inputName, fileName, documentField) => {
            const input = document.querySelector(`input[name="${inputName}"]`);
            if (!input) return;
            
            const container = input.closest('.border-dashed');
            if (!container) return;
            
            // Check if THIS specific document needs resubmission
            const needsResubmission = resubmissionDocs.includes(documentField);
            
            if (fileName) {
              if (needsResubmission) {
                // Document needs resubmission - NO GREEN, show orange styling
                console.log(`🟧 ${documentField} needs resubmission - NO GREEN indicator, showing file input`);
                
                // Orange border styling
                container.classList.remove('border-gray-300', 'border-green-400', 'bg-green-50');
                container.classList.add('border-orange-400', 'bg-orange-50');
                
                // Keep original input visible and enabled
                input.disabled = false;
                input.required = false;
                input.style.display = 'block';
                input.classList.remove('hidden');
                
                // Add orange notice showing previous file
                const resubmitNotice = document.createElement('div');
                resubmitNotice.className = 'mt-2 p-2 bg-orange-100 border border-orange-300 rounded text-xs';
                resubmitNotice.innerHTML = `
                  <div class="flex items-center text-orange-800">
                    <i class="ri-information-line mr-2"></i>
                    <span class="font-medium">Previous file: ${fileName}</span>
                  </div>
                `;
                container.appendChild(resubmitNotice);
                
              } else {
                // Document is approved - show GREEN indicator
                container.classList.remove('border-gray-300', 'border-orange-400', 'bg-orange-50');
                container.classList.add('border-green-400', 'bg-green-50');
                
                // Create green uploaded file indicator
                const indicator = document.createElement('div');
                indicator.className = 'mt-2 p-2 bg-green-50 border border-green-200 rounded text-xs approved-file';
                indicator.innerHTML = `
                  <div class="flex items-center text-green-700">
                    <i class="ri-checkbox-circle-fill mr-2"></i>
                    <span class="font-medium">Uploaded: ${fileName}</span>
                  </div>
                `;
                container.appendChild(indicator);
                
                // Hide the file input (approved, view-only)
                input.disabled = true;
                input.style.display = 'none';
                input.classList.add('hidden');
              }
            }
          };
          
          console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
          console.log('🔍 INDICATOR DECISION POINT');
          console.log('  App Status:', app.status);
          console.log('  Is Resubmission Mode:', isResubmissionMode);
          console.log('  Resubmission Docs:', resubmissionDocs);
          console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
          
          // ALWAYS add indicators to show what was uploaded
          // The addUploadedIndicator function will handle resubmission mode internally
          console.log('✅ ADDING indicators for all uploaded files...');
          if (app.application_letter) addUploadedIndicator('applicationLetter', app.application_letter, 'application_letter');
          if (app.resume) addUploadedIndicator('resume_file', app.resume, 'resume');
          if (app.tor) addUploadedIndicator('transcript', app.tor, 'tor');
          if (app.diploma) addUploadedIndicator('diploma', app.diploma, 'diploma');
          if (app.professional_license) addUploadedIndicator('license', app.professional_license, 'professional_license');
          if (app.coe) addUploadedIndicator('coe', app.coe, 'coe');
          if (app.seminars_trainings) addUploadedIndicator('certificates[]', app.seminars_trainings, 'seminars_trainings');
          if (app.masteral_cert) addUploadedIndicator('masteral_cert', app.masteral_cert, 'masteral_cert');
          
          // If resubmission mode, the indicator function already handles showing file inputs
          if (isResubmissionMode) {
            console.log('🔄 RESUBMISSION MODE');
            console.log('   ✅ Approved documents: Green border + "Uploaded: filename" (no file input)');
            console.log('   🟧 Resubmission documents: Orange border + file input + "Previous file: filename"');
            console.log('   Requested documents:', resubmissionDocs);
            
            // Add hidden fields to indicate resubmission mode
            const form = document.getElementById('requirementsForm');
            if (form) {
              // Add resubmission indicator
              let resubmitInput = form.querySelector('input[name="is_resubmission"]');
              if (!resubmitInput) {
                resubmitInput = document.createElement('input');
                resubmitInput.type = 'hidden';
                resubmitInput.name = 'is_resubmission';
                resubmitInput.value = '1';
                form.appendChild(resubmitInput);
              }
              
              // Add application ID for resubmission
              let appIdInput = form.querySelector('input[name="resubmit_application_id"]');
              if (!appIdInput) {
                appIdInput = document.createElement('input');
                appIdInput.type = 'hidden';
                appIdInput.name = 'resubmit_application_id';
                appIdInput.value = app.application_id || app.id;
                form.appendChild(appIdInput);
              }
              
              console.log('✅ Added resubmission hidden fields to form');
            }
            
            // Update step 2 heading and description for resubmission mode
            window.updateStep2ForResubmission(resubmissionDocs.length);
            
            // Enable and update submit button in step 2
            const submitBtn = document.querySelector('#step2 button[type="submit"]');
            if (submitBtn) {
              submitBtn.style.display = '';
              submitBtn.disabled = false;
              
              // Change button text to "Resubmit Files"
              const btnContent = submitBtn.querySelector('i');
              if (btnContent && btnContent.nextSibling) {
                btnContent.nextSibling.textContent = 'Resubmit Files';
              } else {
                // If no icon, just change the whole text
                const iconHtml = submitBtn.innerHTML.match(/<i[^>]*>.*?<\/i>/);
                if (iconHtml) {
                  submitBtn.innerHTML = iconHtml[0] + 'Resubmit Files';
                } else {
                  submitBtn.textContent = 'Resubmit Files';
                }
              }
              
              console.log('✅ Submit button updated to "Resubmit Files"');
            }
            
            // Show enhanced resubmission notice at top of step 2
            const step2 = document.getElementById('step2');
            if (step2) {
              const existingNotice = step2.querySelector('.resubmission-notice');
              if (!existingNotice) {
                const docLabels = {
                  'application_letter': 'Application Letter',
                  'resume': 'Resume',
                  'tor': 'Transcript of Records (TOR)',
                  'diploma': 'Diploma',
                  'professional_license': 'Professional License',
                  'coe': 'Certificate of Employment',
                  'seminars_trainings': 'Seminars/Training Certificates',
                  'masteral_cert': 'Masteral Certificate'
                };
                
                const requestedDocsList = resubmissionDocs.map(doc => 
                  `<li class="flex items-center"><i class="ri-file-text-line mr-2 text-orange-600"></i>${docLabels[doc] || doc}</li>`
                ).join('');
                
                const notice = document.createElement('div');
                notice.className = 'resubmission-notice mb-6 p-5 bg-gradient-to-r from-orange-50 to-amber-50 border-2 border-orange-400 rounded-xl shadow-md';
                notice.innerHTML = `
                  <div class="flex items-start">
                    <i class="ri-alert-line text-orange-600 text-2xl mr-4 mt-0.5"></i>
                    <div class="flex-1">
                      <h3 class="font-bold text-orange-900 mb-2 text-lg">📋 Document Resubmission Required</h3>
                      <p class="text-sm text-orange-800 mb-3">The admin has requested you to resubmit the following ${resubmissionDocs.length} document(s). Only the requested documents are shown below:</p>
                      <ul class="space-y-1 text-sm text-orange-900 font-medium mb-3">
                        ${requestedDocsList}
                      </ul>
                      ${app.resubmission_notes ? `
                        <div class="mt-3 p-3 bg-white bg-opacity-60 rounded-lg border border-orange-300">
                          <p class="text-xs text-orange-700 font-semibold mb-1">📝 Reason from Admin:</p>
                          <p class="text-sm text-orange-800 italic">${app.resubmission_notes}</p>
                        </div>
                      ` : ''}
                    </div>
                  </div>
                `;
                const firstChild = step2.querySelector('h2');
                if (firstChild) {
                  firstChild.parentNode.insertBefore(notice, firstChild.nextSibling);
                }
              }
            }
            
            console.log('✅ Resubmission mode setup complete!');
          }
          
        } catch (err) {
          console.error('Error loading application:', err);
          showToast('Error loading application details: ' + err.message, 'error');
        }
        
        return;
      }
      
      // Otherwise, it's a new application - show terms modal first
      const jobTitle = this.closest('.bg-white').querySelector('h3').textContent;
      
      // Store job info for application form
      window.selectedJobId = jobId;
      window.selectedJobTitle = jobTitle;
      
      // Show terms modal first
      showTermsModal();
    });
  });
}

// Helper function to populate wizard with application data
function populateWizardWithApplicationData(app, data) {
  // Set job info
  window.selectedJobId = app.job_id;
  window.selectedJobTitle = app.job_title || 'Job Application';
  
  // Populate wizard job title
  const wizardJobTitle = document.getElementById('wizardJobTitle');
  if (wizardJobTitle) {
    wizardJobTitle.innerHTML = `Applying for: <span>${app.job_title || '-'}</span>`;
  }
  
  // Populate step 1 fields
  const pf_first = document.getElementById('pf_first_name');
  const pf_last = document.getElementById('pf_last_name');
  const pf_email = document.getElementById('pf_email');
  const pf_phone = document.getElementById('pf_phone');
  const pf_address = document.getElementById('pf_address');
  
  if (pf_first) pf_first.value = app.first_name || '';
  if (pf_last) pf_last.value = app.last_name || '';
  if (pf_email) pf_email.value = app.email || '';
  if (pf_phone) pf_phone.value = app.contact_number || '';
  if (pf_address) pf_address.value = app.address || '';
  
  // Display work experience, education, and skills
  if (data.work_experience && data.work_experience.length > 0) {
    displayWorkExperienceFromData(data.work_experience);
  }
  if (data.education && data.education.length > 0) {
    displayEducationFromData(data.education);
  }
  if (data.skills && data.skills.length > 0) {
    displaySkillsFromData(data.skills);
  }
}

// Show job details function
function showJobDetails(jobId) {
  console.log('🔍 Showing job details for ID:', jobId);
  
  // Check if elements exist
  const jobListings = document.getElementById('jobListings');
  const jobDetailView = document.getElementById('jobDetailView');
  
  console.log('Elements found:', {
    jobListings: !!jobListings,
    jobDetailView: !!jobDetailView
  });
  
  if (!jobListings || !jobDetailView) {
    console.error('❌ Required elements not found!');
    return;
  }
  
  // Hide job listings and show job detail view
  jobListings.style.display = 'none';
  document.getElementById('paginationContainer').style.display = 'none';
  document.getElementById('jobHeader').style.display = 'none';
  document.getElementById('searchFilters').style.display = 'none';
  jobDetailView.classList.remove('hidden');
  
  console.log('✅ Switched to detail view, fetching data...');
  
  // Load job details via API
  fetch(`get_job_details.php?job_id=${jobId}`)
    .then(response => {
      console.log('📡 Response received:', response.status);
      return response.json();
    })
    .then(data => {
      console.log('📦 Data received:', data);
      if (data.success && data.job) {
        console.log('✅ Populating job details...');
        populateJobDetails(data.job);
      } else {
        console.error('❌ Failed to load job details:', data.message || data.error);
        showToast('Failed to load job details', 'error');
        showJobListings();
      }
    })
    .catch(error => {
      console.error('❌ Error loading job details:', error);
      showToast('Error loading job details', 'error');
      showJobListings();
    });
}

// Populate job details in the detail view
function populateJobDetails(job) {
  // Update job title
  document.getElementById('detailJobTitle').textContent = job.job_title || 'Job Title';
  
  // Update job meta information
  const metaContainer = document.getElementById('detailJobMeta');
  metaContainer.innerHTML = `
    <span class="flex items-center">
      <i class="ri-building-line mr-2"></i>
      ${job.department_role || 'Department'}
    </span>
    <span class="flex items-center">
      <i class="ri-time-line mr-2"></i>
      ${job.job_type || 'Job Type'}
    </span>
    <span class="flex items-center">
      <i class="ri-map-pin-line mr-2"></i>
      ${job.locations || 'Location'}
    </span>
  `;
  
  // Update salary
  document.getElementById('detailSalary').innerHTML = `<span>${job.salary_range || 'Salary not specified'}</span>`;
  
  // Update deadline
  const deadlineElement = document.getElementById('detailDeadlineDate');
  if (job.application_deadline) {
    const deadline = new Date(job.application_deadline);
    deadlineElement.textContent = deadline.toLocaleDateString('en-US', { 
      year: 'numeric', 
      month: 'long', 
      day: 'numeric' 
    });
  } else {
    deadlineElement.textContent = 'No deadline specified';
  }
  
  // Update qualifications
  document.getElementById('detailEducation').textContent = job.education || 'Not specified';
  document.getElementById('detailExperience').textContent = job.experience || 'Not specified';
  document.getElementById('detailTraining').textContent = job.training || 'Not specified';
  document.getElementById('detailEligibility').textContent = job.eligibility || 'Not specified';
  
  // Update job requirements - Show document requirements
  const requirementsContainer = document.getElementById('detailJobRequirements');
  const documentRequirements = [
    'Application Letter',
    'Updated and Comprehensive Resume',
    'Transcript of Record (TOR)',
    'Diploma',
    'Professional License',
    'Certificate of Employment (COE)',
    'Seminar/Training Certificates',
    'Masteral Certificate'
  ];
  
  requirementsContainer.innerHTML = documentRequirements.map(req => `
    <div class="flex items-start">
      <i class="ri-file-text-line text-primary mr-2 mt-0.5"></i>
      <p class="flex-1">${req}</p>
    </div>
  `).join('');
  
  // Update competency
  document.getElementById('detailCompetency').textContent = job.competency || 'Not specified';
  
  // Update job description
  const descriptionContainer = document.getElementById('detailJobDescription');
  if (job.job_description) {
    const descriptions = job.job_description.split('\n').filter(desc => desc.trim());
    descriptionContainer.innerHTML = descriptions.map(desc => `<p>• ${desc.trim()}</p>`).join('');
  } else {
    descriptionContainer.innerHTML = '<p>No description available</p>';
  }
  
  // Update apply button based on application status
  const applyBtn = document.getElementById('detailApplyBtn');
  applyBtn.setAttribute('data-job-id', job.id);
  
  // Check if user has already applied
  if (job.application_id) {
    // User has applied - show "View Application" button
    applyBtn.textContent = 'View Application';
    applyBtn.className = 'px-8 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium';
    applyBtn.setAttribute('data-application-id', job.application_id);
    applyBtn.setAttribute('data-application-status', job.application_status || '');
    applyBtn.onclick = async function() {
      const applicationId = this.getAttribute('data-application-id');
      console.log('🔍 View Application clicked, ID:', applicationId);
      
      if (applicationId) {
        console.log('📂 Opening existing application:', applicationId);
        
        // Clear previous data
        window._lastLoadedAppId = null;
        window._isProcessingIndicators = false;
        globalResubmissionDocs = [];
        
        try {
          console.log('📡 Fetching application details...');
          const res = await fetch(`get_application_details.php?id=${applicationId}`);
          console.log('📡 Response status:', res.status);
          
          const data = await res.json();
          console.log('📦 Application data received:', data);
          
          if (!data.success) {
            console.error('❌ API returned error:', data.error);
            throw new Error(data.error || 'Failed to load application');
          }
          
          const app = data.application;
          console.log('✅ Application loaded:', app);
          
          // Hide job detail view, show wizard
          console.log('🔄 Hiding job detail view, showing wizard...');
          document.getElementById('jobDetailView').classList.add('hidden');
          
          // Show wizard in view mode
          populateWizardWithApplicationData(app, data);
          showWizard(true); // true = view mode
          
          // Determine current workflow step (SAME LOGIC AS DASHBOARD)
          let workflowStep = 3; // Default to step 3 (Interview)
          const status = (app.status || '').toLowerCase();
          
          console.log('🔍 Application status:', status);
          console.log('📋 Has interview_date:', !!app.interview_date);
          console.log('📋 Has demo_date:', !!app.demo_date);
          console.log('📋 Has psych_exam_receipt:', !!app.psych_exam_receipt);
          
          if (status.includes('initially hired') || status.includes('hired')) {
            workflowStep = 6; // Admin has marked as hired - show step 6
          } else if (app.psych_exam_receipt) {
            workflowStep = 5; // Psych receipt uploaded, waiting for admin to hire
          } else if (status.includes('demo') && status.includes('passed')) {
            workflowStep = 5; // Demo passed, ready for psychological exam
          } else if (status.includes('demo') || app.demo_date) {
            workflowStep = 4; // Demo scheduled but not yet passed
          } else if (status.includes('interview') && status.includes('passed')) {
            workflowStep = 4; // Interview passed, ready for demo
          } else if (status.includes('interview') || app.interview_date) {
            workflowStep = 3;
          }
          
          console.log('📍 Opening wizard at step:', workflowStep, 'based on status:', status);
          window.currentWorkflowStep = workflowStep;
          window.currentApplicationData = app;
          
          // Navigate to current workflow step
          setStep(workflowStep);
          console.log('✅ Application view opened at step', workflowStep);
          
        } catch (error) {
          console.error('❌ Error loading application:', error);
          showToast('Error loading application details: ' + error.message, 'error');
        }
      } else {
        console.error('❌ No application ID found!');
        showToast('Application ID not found', 'error');
      }
    };
  } else {
    // User hasn't applied - show "Apply Now" button
    applyBtn.textContent = 'Apply Now';
    applyBtn.className = 'px-8 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors font-medium';
    applyBtn.onclick = function() {
      window.selectedJobId = job.id;
      window.selectedJobTitle = job.job_title;
      showTermsModal();
    };
  }
}

// Show job listings (back from detail view)
function showJobListings() {
  document.getElementById('jobDetailView').classList.add('hidden');
  document.getElementById('jobListings').style.display = 'block';
  document.getElementById('paginationContainer').style.display = 'flex';
  document.getElementById('jobHeader').style.display = 'block';
  document.getElementById('searchFilters').style.display = 'block';
}

// Show terms modal function
function showTermsModal() {
  console.log('Showing terms modal for job:', window.selectedJobTitle);
  
  // Create and show terms modal
  const existingModal = document.getElementById('termsModal');
  if (existingModal) {
    existingModal.remove();
  }
  
  const termsModalHTML = `
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" id="termsModal">
      <!-- Outer Box -->
      <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full border-2 border-gray-200">
        <!-- Header -->
        <div class="p-6 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-900">Application Terms & Conditions</h2>
            <button id="closeTermsModal" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600">
              <i class="ri-close-line text-xl"></i>
            </button>
          </div>
        </div>

        <!-- Inner Scrollable Box -->
        <div class="p-6">
          <div class="border-2 border-gray-300 rounded-lg p-6 bg-gray-50 overflow-y-auto" style="max-height: 400px;">
            <!-- Important Information Alert -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
              <div class="flex items-start">
                <i class="ri-information-line text-blue-600 text-xl mr-3 mt-0.5"></i>
                <div>
                  <h3 class="font-semibold text-blue-900 mb-2">Important Information</h3>
                  <p class="text-blue-800 text-sm">Please read and understand the following terms and conditions before submitting your application.</p>
                </div>
              </div>
            </div>

            <!-- Terms and Conditions Content -->
            <div class="text-sm text-gray-700 leading-relaxed">
              <p class="mb-4">Before submitting your application, please read and understand the following terms and conditions. All required documents must be submitted in PDF format (maximum 5MB each), ensuring they are clear and complete to avoid rejection. Your personal information will remain confidential and will be used solely for recruitment purposes in compliance with data protection regulations. By applying, you confirm that all provided information is true and accurate, and you agree to participate in the recruitment process if selected. Submission of an application does not guarantee employment.</p>
            </div>
          </div>
        </div>

        <!-- Checkbox (Outside Scrollable Area) -->
        <div class="px-6 pb-4">
          <div class="flex items-center space-x-3">
            <input type="checkbox" id="agreeTerms" class="w-5 h-5 text-primary border-gray-300 rounded focus:ring-primary">
            <label for="agreeTerms" class="text-sm font-medium text-gray-700">
              I have read and agree to the terms and conditions, and I understand the application requirements.
            </label>
          </div>
        </div>

        <!-- Footer Buttons -->
        <div class="p-6 border-t border-gray-200 flex justify-end space-x-3">
          <button id="cancelApplication" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
            Cancel
          </button>
          <button id="proceedToApplication" class="px-6 py-2 bg-blue-200 text-blue-400 rounded-lg cursor-not-allowed transition-colors" disabled>
            Proceed to Application
          </button>
        </div>
      </div>
    </div>
  `;
  
  document.body.insertAdjacentHTML('beforeend', termsModalHTML);
  
  // Add event listeners for the modal
  const modal = document.getElementById('termsModal');
  const closeBtn = document.getElementById('closeTermsModal');
  const cancelBtn = document.getElementById('cancelApplication');
  const agreeCheckbox = document.getElementById('agreeTerms');
  const proceedBtn = document.getElementById('proceedToApplication');
  
  // Close modal events
  closeBtn.addEventListener('click', () => modal.remove());
  cancelBtn.addEventListener('click', () => modal.remove());
  
  // Checkbox validation with visual feedback
  agreeCheckbox.addEventListener('change', function() {
    if (this.checked) {
      // Enable button
      proceedBtn.disabled = false;
      proceedBtn.className = 'px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors cursor-pointer';
    } else {
      // Disable button
      proceedBtn.disabled = true;
      proceedBtn.className = 'px-6 py-2 bg-blue-200 text-blue-400 rounded-lg cursor-not-allowed transition-colors';
    }
  });
  
  // Proceed to application
  proceedBtn.addEventListener('click', function() {
    console.log('Proceed button clicked');
    console.log('Checkbox checked:', agreeCheckbox.checked);
    
    if (agreeCheckbox.checked) {
      console.log('Terms agreed, proceeding to wizard...');
      console.log('Selected Job ID:', window.selectedJobId);
      console.log('Selected Job Title:', window.selectedJobTitle);
      console.log('startApplicationWizard function available:', typeof window.startApplicationWizard);
      
      console.log('Removing terms modal...');
      modal.remove();
      console.log('Terms modal removed');
      
      if (typeof window.startApplicationWizard === 'function') {
        console.log('Calling startApplicationWizard...');
        try {
          window.startApplicationWizard(window.selectedJobId, window.selectedJobTitle);
          console.log('startApplicationWizard call completed');
        } catch (error) {
          console.error('Error calling startApplicationWizard:', error);
          alert('Error starting application wizard: ' + error.message);
        }
      } else {
        console.error('Application wizard not available - function not found');
        alert('Application wizard is not available. Please refresh the page and try again.');
      }
    } else {
      console.log('Terms not agreed - checkbox not checked');
    }
  });
}

// Update existing application states
function updateExistingApplicationStates() {
  <?php if (isset($_SESSION['user_id'])): ?>
  fetch('get_user_applications.php')
    .then(response => response.json())
    .then(data => {
      if (data.success && data.applications) {
        // Update buttons for jobs user has already applied to
        document.querySelectorAll('.apply-btn').forEach(button => {
          const buttonJobId = button.getAttribute('data-job-id');
          
          // Find matching application
          const application = data.applications.find(app => app.job_id == buttonJobId);
          
          if (application) {
            const status = (application.status || '').toLowerCase();
            const isCompleted = status.includes('hired') || status.includes('reject');
            
            if (isCompleted) {
              // Application is completed - disable button
              button.textContent = status.includes('hired') ? 'Hired' : 'Rejected';
              button.className = 'px-4 py-2 bg-gray-300 text-gray-600 rounded-lg cursor-not-allowed text-sm whitespace-nowrap !rounded-button';
              button.disabled = true;
            } else {
              // Application is in progress - keep clickable
              button.textContent = 'View Application';
              button.className = 'px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm whitespace-nowrap !rounded-button';
              button.disabled = false;
              button.setAttribute('data-application-id', application.id);
              button.setAttribute('data-application-status', 'in-progress');
            }
            
            // Update status badge
            const jobCard = button.closest('.bg-white');
            if (jobCard) {
              const statusBadge = jobCard.querySelector('.bg-green-100');
              if (statusBadge) {
                statusBadge.textContent = isCompleted ? (status.includes('hired') ? 'Hired' : 'Rejected') : 'In Progress';
                statusBadge.className = isCompleted ? 'bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium' : 'bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium';
              }
            }
          }
        });
      }
    })
    .catch(error => {
      console.log('Could not load existing applications:', error);
    });
  <?php endif; ?>
}

// Filter and search functionality
function updateActiveFilters() {
  const activeFiltersContainer = document.getElementById('activeFilters');
  activeFiltersContainer.innerHTML = '';
  
  const filters = [
    { key: 'search', label: 'Search', value: currentFilters.search },
    { key: 'department', label: 'Department', value: currentFilters.department },
    { key: 'job_type', label: 'Job Type', value: currentFilters.job_type }
  ];
  
  // Show active filters with X buttons
  filters.forEach(filter => {
    if (filter.value) {
      const tag = document.createElement('span');
      tag.className = 'inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-primary text-white';
      tag.innerHTML = `
        ${filter.label}: ${filter.value}
        <button class="ml-2 hover:text-gray-200" onclick="removeFilter('${filter.key}')">
          <i class="ri-close-line"></i>
        </button>
      `;
      activeFiltersContainer.appendChild(tag);
    }
  });
  
  // Show "All Departments" label when no department filter is active
  if (!currentFilters.department && !currentFilters.search && !currentFilters.job_type) {
    const allDeptLabel = document.createElement('span');
    allDeptLabel.className = 'inline-flex items-center px-3 py-1 text-sm font-medium text-gray-600';
    allDeptLabel.innerHTML = `
      <i class="ri-building-line mr-2"></i>All Departments
    `;
    activeFiltersContainer.appendChild(allDeptLabel);
  }
}

function removeFilter(filterKey) {
  currentFilters[filterKey] = '';
  document.getElementById(filterKey === 'search' ? 'searchInput' : filterKey + 'Filter').value = '';
  loadJobs(1, currentFilters);
  updateActiveFilters();
}

// Pagination event listeners
document.addEventListener('DOMContentLoaded', function() {
  // Previous page button
  document.getElementById('prevPageBtn').addEventListener('click', function() {
    if (currentPage > 1) {
      loadJobs(currentPage - 1, currentFilters);
    }
  });
  
  // Next page button
  document.getElementById('nextPageBtn').addEventListener('click', function() {
    if (currentPage < totalPages) {
      loadJobs(currentPage + 1, currentFilters);
    }
  });
  
  // Back to Jobs button
  const backToJobsBtn = document.getElementById('backToJobs');
  if (backToJobsBtn) {
    backToJobsBtn.addEventListener('click', function() {
      showJobListings();
    });
  }
  
  // Search functionality
  const searchInput = document.getElementById('searchInput');
  let searchTimeout;
  
  searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const searchTerm = this.value.trim();
    
    // Debounce search to avoid too many requests
    searchTimeout = setTimeout(() => {
      currentFilters.search = searchTerm;
      loadJobs(1, currentFilters); // Reset to page 1 when searching
      updateActiveFilters();
    }, 500);
  });
  
  // Department filter
  document.getElementById('departmentFilter').addEventListener('change', function() {
    currentFilters.department = this.value;
    loadJobs(1, currentFilters);
    updateActiveFilters();
  });
  
  // Job type filter
  document.getElementById('jobTypeFilter').addEventListener('change', function() {
    currentFilters.job_type = this.value;
    loadJobs(1, currentFilters);
    updateActiveFilters();
  });
  
  // Sort functionality
  document.getElementById('sortSelect').addEventListener('change', function() {
    currentFilters.sort = this.value;
    loadJobs(currentPage, currentFilters); // Keep current page when sorting
  });
  
  // Check if there's a pending application to open (from My Applications page)
  // Do this BEFORE loading jobs to prevent dashboard flash
  const openApplicationId = sessionStorage.getItem('openApplicationId');
  if (openApplicationId) {
    console.log('🔄 Found pending application to open:', openApplicationId);
    console.log('📊 Current state:', {
      wizardExists: !!document.getElementById('applicationWizard'),
      functionExists: typeof window.viewExistingApplication,
      retryCount: sessionStorage.getItem('wizardOpenRetry')
    });
    
    // Check retry count to prevent infinite loops
    const retryCount = parseInt(sessionStorage.getItem('wizardOpenRetry') || '0');
    if (retryCount >= 3) {
      console.error('❌ Too many retry attempts. Clearing state.');
      console.error('Debug info:');
      console.error('- Application ID:', openApplicationId);
      console.error('- Wizard element exists:', !!document.getElementById('applicationWizard'));
      console.error('- viewExistingApplication function exists:', typeof window.viewExistingApplication);
      sessionStorage.removeItem('openApplicationId');
      sessionStorage.removeItem('returnToMyApplications');
      sessionStorage.removeItem('wizardOpenRetry');
      alert('Unable to open application wizard after 3 attempts.\n\nPlease:\n1. Refresh the page (F5)\n2. Go to Dashboard\n3. Try clicking the eye icon again\n\nIf the issue persists, check the browser console (F12) for errors.');
    } else {
      sessionStorage.setItem('wizardOpenRetry', (retryCount + 1).toString());
      sessionStorage.removeItem('openApplicationId');
      
      // Check if we should return to My Applications when closing
      const returnToMyApplications = sessionStorage.getItem('returnToMyApplications');
      if (returnToMyApplications === 'true') {
        console.log('✅ Will return to My Applications when wizard closes');
        window.wizardOpenedFromMyApplications = true;
        sessionStorage.removeItem('returnToMyApplications');
      }
    
    // IMMEDIATELY hide dashboard content to prevent flash
    console.log('🔒 Hiding dashboard content...');
    const jobHeader = document.getElementById('jobHeader');
    const searchFilters = document.getElementById('searchFilters');
    const listings = document.getElementById('listings');
    const pagination = document.getElementById('pagination');
    
    if (jobHeader) jobHeader.style.display = 'none';
    if (searchFilters) searchFilters.style.display = 'none';
    if (listings) listings.style.display = 'none';
    if (pagination) pagination.style.display = 'none';
    
    // Show loading overlay
    const loadingOverlay = document.createElement('div');
    loadingOverlay.id = 'wizardLoadingOverlay';
    loadingOverlay.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: white;
      z-index: 9998;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
    `;
    loadingOverlay.innerHTML = `
      <i class="ri-loader-4-line text-6xl text-blue-600 animate-spin mb-4"></i>
      <p class="text-gray-700 text-lg">Opening application wizard...</p>
    `;
    document.body.appendChild(loadingOverlay);
    
    // Open wizard immediately (no delay)
    setTimeout(() => {
      if (typeof window.viewExistingApplication === 'function') {
        console.log('📖 Opening application wizard...');
        window.viewExistingApplication(openApplicationId);
        
        // Remove loading overlay after wizard opens
        setTimeout(() => {
          const overlay = document.getElementById('wizardLoadingOverlay');
          if (overlay) overlay.remove();
          // Clear retry counter on success
          sessionStorage.removeItem('wizardOpenRetry');
        }, 500);
      } else {
        console.error('❌ viewExistingApplication function not available yet');
        const overlay = document.getElementById('wizardLoadingOverlay');
        if (overlay) overlay.remove();
      }
    }, 100); // Minimal delay just for DOM readiness
    }
  } else {
    // Normal dashboard load - only if no pending application
    loadJobs(1);
  }
});

// Utility function to escape HTML
function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Enhanced file upload functionality
document.addEventListener('DOMContentLoaded', function() {
  // Add file upload enhancements
  const fileInputs = document.querySelectorAll('input[type="file"]');
  
  fileInputs.forEach(input => {
    const container = input.closest('.file-upload-container');
    const placeholder = container?.querySelector('.file-placeholder');
    
    input.addEventListener('change', function() {
      if (this.files && this.files.length > 0) {
        if (placeholder) {
          if (this.files.length === 1) {
            placeholder.textContent = `Selected: ${this.files[0].name}`;
          } else {
            placeholder.textContent = `Selected: ${this.files.length} files`;
          }
          placeholder.style.color = '#059669'; // green color for selected files
        }
        
        // Add selected state styling
        this.style.borderColor = '#059669';
        this.style.backgroundColor = '#f0fdf4';
      } else {
        if (placeholder) {
          // Reset placeholder text based on input type
          if (this.hasAttribute('multiple')) {
            placeholder.textContent = 'Click to upload multiple files or drag & drop';
          } else {
            placeholder.textContent = 'Click to upload or drag & drop';
          }
          placeholder.style.color = '#6b7280'; // reset to gray
        }
        
        // Reset styling
        this.style.borderColor = '#d1d5db';
        this.style.backgroundColor = 'white';
      }
    });
    
    // Add drag and drop functionality
    const dropZone = input.parentElement;
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
      dropZone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
      e.preventDefault();
      e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
      dropZone.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
      dropZone.addEventListener(eventName, unhighlight, false);
    });
    
    dropZone.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
      const dt = e.dataTransfer;
      const files = dt.files;
      input.files = files;
      
      // Trigger change event
      const event = new Event('change', { bubbles: true });
      input.dispatchEvent(event);
    }
  });
});
</script>

<style>
/* Enhanced file upload styling */
.file-upload-container input[type="file"] {
  position: relative;
}

.file-upload-container input[type="file"]:focus {
  outline: none;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.file-upload-container .file-placeholder {
  pointer-events: none;
  transition: color 0.2s ease;
}

/* Hide placeholder when file is selected */
.file-upload-container input[type="file"]:not(:placeholder-shown) + .file-placeholder {
  display: none;
}

/* Drag and drop visual feedback */
.file-upload-container .relative {
  transition: all 0.2s ease;
}

/* File input styling improvements */
.file-upload-container input[type="file"]::file-selector-button {
  margin-right: 1rem;
  padding: 0.5rem 1rem;
  border-radius: 9999px;
  border: 0;
  font-size: 0.875rem;
  font-weight: 600;
  transition: all 0.2s ease;
}

/* Section-specific file button colors */
.bg-blue-50 input[type="file"]::file-selector-button {
  background-color: #dbeafe;
  color: #1d4ed8;
}

.bg-blue-50 input[type="file"]:hover::file-selector-button {
  background-color: #bfdbfe;
}

.bg-green-50 input[type="file"]::file-selector-button {
  background-color: #dcfce7;
  color: #166534;
}

.bg-green-50 input[type="file"]:hover::file-selector-button {
  background-color: #bbf7d0;
}

.bg-purple-50 input[type="file"]::file-selector-button {
  background-color: #f3e8ff;
  color: #7c3aed;
}

.bg-purple-50 input[type="file"]:hover::file-selector-button {
  background-color: #e9d5ff;
}

/* Wizard specific styles - Override all other styles */
#applicationWizard {
  display: none !important;
  position: fixed !important;
  top: 0 !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  z-index: 9999 !important;
  background-color: #f9fafb !important;
  overflow-y: auto !important;
}

/* Fix sticky header scroll issue */
#applicationWizard .wizard-step {
  scroll-margin-top: 120px !important;
}

/* Compact wizard design */
#applicationWizard h2 {
  font-size: 1.125rem !important;
  margin-bottom: 0.5rem !important;
}

#applicationWizard p {
  margin-bottom: 1rem !important;
}

#applicationWizard:not(.hidden) {
  display: block !important;
}

/* Force wizard visibility when shown */
#applicationWizard[style*="display: block"] {
  display: block !important;
  visibility: visible !important;
  opacity: 1 !important;
}

.wizard-step {
  display: block !important;
  opacity: 1 !important;
  visibility: visible !important;
}

.wizard-step.hidden {
  display: none !important;
}

/* Ensure wizard body is always visible */
#applicationWizard .p-6 {
  display: block !important;
  visibility: visible !important;
  opacity: 1 !important;
  min-height: 400px !important;
  background: white !important;
}

/* Ensure wizard content container is visible */
#applicationWizard .max-w-4xl {
  display: block !important;
  visibility: visible !important;
  position: relative !important;
  z-index: 1 !important;
}

/* Override any Tailwind hidden class */
#applicationWizard.hidden {
  display: none !important;
}

#applicationWizard:not(.hidden) {
  display: block !important;
}

/* Responsive improvements */
@media (max-width: 768px) {
  .file-upload-container {
    margin-bottom: 1rem;
  }
  
  .file-upload-container input[type="file"] {
    padding: 0.75rem;
    font-size: 0.875rem;
  }
}
</style>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[9999] flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                <i class="ri-logout-box-line text-red-600 text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">Sign Out</h3>
            <p class="text-sm text-gray-600 text-center mb-6">Are you sure you want to sign out? You will need to log in again to access your account.</p>
            <div class="flex gap-3">
                <button onclick="closeLogoutModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                    Cancel
                </button>
                <button onclick="proceedLogout()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium">
                    Sign Out
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Note: Application progress modal functionality is now handled in user_application.php
// to avoid conflicts with the main application wizard

// Logout confirmation modal
function confirmLogout(event) {
    event.preventDefault();
    document.getElementById('logoutModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeLogoutModal() {
    document.getElementById('logoutModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function proceedLogout() {
    window.location.href = '../index.php?logout=1';
}
</script>

</body>
</html>