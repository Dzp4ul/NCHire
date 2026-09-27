<?php
// Clean output buffer and ensure JSON response
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

session_start();

// Set error handler to catch any PHP errors and return JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Save draft error: {$errstr} in {$errfile}:{$errline}");
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unable to save the draft. Please try again.']);
    exit();
});

// Set exception handler
set_exception_handler(function($exception) {
    error_log('Save draft exception: ' . $exception->getMessage());
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unable to save the draft. Please try again.']);
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
    $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    $allowedMimes = [
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip', 'image/jpeg', 'image/png', 'application/octet-stream',
    ];
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    $validateAndStore = static function(string $name, string $tmpName, int $size, int $error, string $suffix = '') use ($allowedExtensions, $allowedMimes, $finfo, $uploadDir, $user_id): string {
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('One of the selected files could not be uploaded.');
        }
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            throw new RuntimeException('Each document must be 5MB or smaller.');
        }
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = $finfo ? finfo_file($finfo, $tmpName) : false;
        if (!in_array($extension, $allowedExtensions, true) || ($mime && !in_array($mime, $allowedMimes, true))) {
            throw new RuntimeException('Only PDF, DOC, DOCX, JPG, and PNG files are accepted.');
        }
        $safeOriginal = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename($name));
        $fileName = 'draft_' . $user_id . '_' . time() . $suffix . '_' . bin2hex(random_bytes(6)) . '_' . $safeOriginal;
        if (!move_uploaded_file($tmpName, $uploadDir . $fileName)) {
            throw new RuntimeException('Unable to store an uploaded document.');
        }
        return $fileName;
    };

    if ($multiple) {
        $savedFiles = [];
        try {
            if (isset($_FILES[$fileKey]) && is_array($_FILES[$fileKey]['name'])) {
                foreach ($_FILES[$fileKey]['name'] as $index => $name) {
                    if (!empty($name)) {
                        $savedFiles[] = $validateAndStore(
                            $name,
                            $_FILES[$fileKey]['tmp_name'][$index],
                            (int)$_FILES[$fileKey]['size'][$index],
                            (int)$_FILES[$fileKey]['error'][$index],
                            '_' . $index
                        );
                    }
                }
            }
        } catch (Throwable $exception) {
            foreach ($savedFiles as $savedFile) {
                $savedPath = $uploadDir . $savedFile;
                if (is_file($savedPath)) {
                    unlink($savedPath);
                }
            }
            if ($finfo) finfo_close($finfo);
            throw $exception;
        }
        if ($finfo) finfo_close($finfo);
        return !empty($savedFiles) ? implode(",", $savedFiles) : null;
    } else {
        if (isset($_FILES[$fileKey]) && !empty($_FILES[$fileKey]['name'])) {
            $fileName = $validateAndStore(
                $_FILES[$fileKey]['name'],
                $_FILES[$fileKey]['tmp_name'],
                (int)$_FILES[$fileKey]['size'],
                (int)$_FILES[$fileKey]['error']
            );
            if ($finfo) finfo_close($finfo);
            return $fileName;
        }
        if ($finfo) finfo_close($finfo);
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
    $check_stmt = $conn->prepare("SELECT id, application_letter, resume, tor, diploma, professional_license, coe, seminars_trainings, masteral_cert, certificate_of_grades, proof_of_enrollment, faculty_evaluation, letter_of_intent FROM user_draft_documents WHERE user_id = ?");
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
    $seminars_trainings = uploadDraftFile('certificates', $userDraftDir, $user_id, true) ?? ($_POST['existing_certificates'] ?? null);
    $masteral_cert = uploadDraftFile('masteral_cert', $userDraftDir, $user_id) ?? ($_POST['existing_masteral_cert'] ?? null);
    $certificate_of_grades = uploadDraftFile('certificate_of_grades', $userDraftDir, $user_id) ?? ($_POST['existing_certificate_of_grades'] ?? null);
    $proof_of_enrollment = uploadDraftFile('proof_of_enrollment', $userDraftDir, $user_id) ?? ($_POST['existing_proof_of_enrollment'] ?? null);
    $faculty_evaluation = uploadDraftFile('faculty_evaluation', $userDraftDir, $user_id) ?? ($_POST['existing_faculty_evaluation'] ?? null);
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
            faculty_evaluation = ?,
            letter_of_intent = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?");
        $stmt->bind_param("ssssssssssssi",
            $application_letter, $resume, $tor, $diploma, 
            $professional_license, $coe, $seminars_trainings, 
            $masteral_cert, $certificate_of_grades, $proof_of_enrollment, $faculty_evaluation, $letter_of_intent, $user_id
        );
    } else {
        // Insert new draft
        $stmt = $conn->prepare("INSERT INTO user_draft_documents 
            (user_id, application_letter, resume, tor, diploma, professional_license, coe, seminars_trainings, masteral_cert, certificate_of_grades, proof_of_enrollment, faculty_evaluation, letter_of_intent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssssssss",
            $user_id, $application_letter, $resume, $tor, $diploma, 
            $professional_license, $coe, $seminars_trainings, $masteral_cert, $certificate_of_grades, $proof_of_enrollment, $faculty_evaluation, $letter_of_intent
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
                'faculty_evaluation' => $faculty_evaluation,
                'letter_of_intent' => $letter_of_intent
            ]
        ]);
    } else {
        error_log('Failed to save document draft: ' . $conn->error);
        echo json_encode(['success' => false, 'error' => 'Unable to save the draft. Please try again.']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log('Unable to save document draft: ' . $e->getMessage());
    ob_clean();
    $safeErrors = [
        'One of the selected files could not be uploaded.',
        'Each document must be 5MB or smaller.',
        'Only PDF, DOC, DOCX, JPG, and PNG files are accepted.',
        'Unable to store an uploaded document.',
    ];
    echo json_encode(['success' => false, 'error' => in_array($e->getMessage(), $safeErrors, true) ? $e->getMessage() : 'Unable to save the draft. Please try again.']);
}

$conn->close();

// Clean output and send JSON
ob_end_flush();
exit();
