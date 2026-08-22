<?php
// Clean output buffer and ensure JSON response
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

session_start();

// Set error handler to catch any PHP errors and return JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => "PHP Error: $errstr in $errfile on line $errline"]);
    exit();
});

// Set exception handler
set_exception_handler(function($exception) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $exception->getMessage()]);
    exit();
});

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit();
}

$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

$user_id = $_SESSION['user_id'];
$uploadDir = __DIR__ . "/uploads/drafts/";
$userDraftDir = $uploadDir . $user_id . "/";

// Create user-specific drafts directory if it doesn't exist
if (!is_dir($userDraftDir)) {
    if (!mkdir($userDraftDir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create drafts directory']);
        exit();
    }
}

// Function to handle file uploads for drafts
function uploadDraftFile($fileKey, $uploadDir, $user_id, $multiple = false) {
    if ($multiple) {
        $savedFiles = [];
        if (isset($_FILES[$fileKey]) && is_array($_FILES[$fileKey]['name'])) {
            foreach ($_FILES[$fileKey]['name'] as $index => $name) {
                if (!empty($name) && $_FILES[$fileKey]['error'][$index] === UPLOAD_ERR_OK) {
                    if ($_FILES[$fileKey]['size'][$index] > 5 * 1024 * 1024) {
                        continue; // Skip files larger than 5MB
                    }
                    $fileName = "draft_" . $user_id . "_" . time() . "_" . $index . "_" . basename($name);
                    $targetFile = $uploadDir . $fileName;
                    if (move_uploaded_file($_FILES[$fileKey]['tmp_name'][$index], $targetFile)) {
                        $savedFiles[] = $fileName;
                    }
                }
            }
        }
        return !empty($savedFiles) ? implode(",", $savedFiles) : null;
    } else {
        if (isset($_FILES[$fileKey]) && !empty($_FILES[$fileKey]['name']) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            if ($_FILES[$fileKey]['size'] > 5 * 1024 * 1024) {
                return null; // File too large
            }
            $fileName = "draft_" . $user_id . "_" . time() . "_" . basename($_FILES[$fileKey]['name']);
            $targetFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetFile)) {
                return $fileName;
            }
        }
        return null;
    }
}

try {
    // Debug logging
    error_log("=== SAVE DRAFT DEBUG ===");
    error_log("User ID: " . $user_id);
    error_log("FILES received: " . print_r(array_keys($_FILES), true));
    
    // Check if letter_of_intent was uploaded
    if (isset($_FILES['letter_of_intent'])) {
        error_log("letter_of_intent file info:");
        error_log("  name: " . $_FILES['letter_of_intent']['name']);
        error_log("  error: " . $_FILES['letter_of_intent']['error']);
        error_log("  size: " . $_FILES['letter_of_intent']['size']);
    } else {
        error_log("letter_of_intent NOT in $_FILES");
    }
    
    // Check if draft already exists for this user
    $check_stmt = $conn->prepare("SELECT id, application_letter, resume, tor, diploma, professional_license, coe, seminars_trainings, masteral_cert, certificate_of_grades, proof_of_enrollment, letter_of_intent FROM user_draft_documents WHERE user_id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $existing_draft = $result->fetch_assoc();
    $check_stmt->close();
    
    // Upload new files - ONLY save what's currently uploaded
    // Check for existing draft filenames sent from hidden inputs (already loaded drafts)
    $application_letter = uploadDraftFile('applicationLetter', $userDraftDir, $user_id) ?? ($_POST['existing_applicationLetter'] ?? null);
    $resume = uploadDraftFile('resume_file', $userDraftDir, $user_id) ?? ($_POST['existing_resume_file'] ?? null);
    $tor = uploadDraftFile('transcript', $userDraftDir, $user_id) ?? ($_POST['existing_transcript'] ?? null);
    $diploma = uploadDraftFile('diploma', $userDraftDir, $user_id) ?? ($_POST['existing_diploma'] ?? null);
    $professional_license = uploadDraftFile('license', $userDraftDir, $user_id) ?? ($_POST['existing_license'] ?? null);
    $coe = uploadDraftFile('coe', $userDraftDir, $user_id) ?? ($_POST['existing_coe'] ?? null);
    $seminars_trainings = uploadDraftFile('certificates', $userDraftDir, $user_id, true) ?? ($_POST['existing_certificates[]'] ?? null);
    $masteral_cert = uploadDraftFile('masteral_cert', $userDraftDir, $user_id) ?? ($_POST['existing_masteral_cert'] ?? null);
    $certificate_of_grades = uploadDraftFile('certificate_of_grades', $userDraftDir, $user_id) ?? ($_POST['existing_certificate_of_grades'] ?? null);
    $proof_of_enrollment = uploadDraftFile('proof_of_enrollment', $userDraftDir, $user_id) ?? ($_POST['existing_proof_of_enrollment'] ?? null);
    $letter_of_intent = uploadDraftFile('letter_of_intent', $userDraftDir, $user_id) ?? ($_POST['existing_letter_of_intent'] ?? null);
    
    error_log("letter_of_intent result after upload: " . ($letter_of_intent ?? 'NULL'));
    error_log("existing_letter_of_intent from POST: " . ($_POST['existing_letter_of_intent'] ?? 'NOT SET'));
    
    if ($existing_draft) {
        // Update existing draft - REPLACE all fields (no COALESCE to preserve old values)
        $stmt = $conn->prepare("UPDATE user_draft_documents SET 
            application_letter = ?,
            resume = ?,
            tor = ?,
            diploma = ?,
            professional_license = ?,
            coe = ?,
            seminars_trainings = ?,
            masteral_cert = ?,
            certificate_of_grades = ?,
            proof_of_enrollment = ?,
            letter_of_intent = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?");
        $stmt->bind_param("sssssssssssi", 
            $application_letter, $resume, $tor, $diploma, 
            $professional_license, $coe, $seminars_trainings, 
            $masteral_cert, $certificate_of_grades, $proof_of_enrollment, $letter_of_intent, $user_id
        );
    } else {
        // Insert new draft
        $stmt = $conn->prepare("INSERT INTO user_draft_documents 
            (user_id, application_letter, resume, tor, diploma, professional_license, coe, seminars_trainings, masteral_cert, certificate_of_grades, proof_of_enrollment, letter_of_intent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssssssss", 
            $user_id, $application_letter, $resume, $tor, $diploma, 
            $professional_license, $coe, $seminars_trainings, $masteral_cert, $certificate_of_grades, $proof_of_enrollment, $letter_of_intent
        );
    }
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Draft saved successfully! Your documents will be auto-loaded for future applications.',
            'draft' => [
                'application_letter' => $application_letter,
                'resume' => $resume,
                'tor' => $tor,
                'diploma' => $diploma,
                'professional_license' => $professional_license,
                'coe' => $coe,
                'seminars_trainings' => $seminars_trainings,
                'masteral_cert' => $masteral_cert,
                'certificate_of_grades' => $certificate_of_grades,
                'proof_of_enrollment' => $proof_of_enrollment,
                'letter_of_intent' => $letter_of_intent
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save draft: ' . $conn->error]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();

// Clean output and send JSON
ob_end_flush();
exit();
