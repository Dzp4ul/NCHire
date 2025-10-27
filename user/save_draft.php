<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit();
}

$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
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
    $check_stmt = $conn->prepare("SELECT id, application_letter, resume, tor, diploma, professional_license, coe, seminars_trainings, masteral_cert, letter_of_intent FROM user_draft_documents WHERE user_id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $existing_draft = $result->fetch_assoc();
    $check_stmt->close();
    
    // Upload new files or keep existing ones
    // Check for existing draft filenames sent from hidden inputs
    $application_letter = uploadDraftFile('applicationLetter', $userDraftDir, $user_id) ?? ($_POST['existing_applicationLetter'] ?? ($existing_draft['application_letter'] ?? null));
    $resume = uploadDraftFile('resume_file', $userDraftDir, $user_id) ?? ($_POST['existing_resume_file'] ?? ($existing_draft['resume'] ?? null));
    $tor = uploadDraftFile('transcript', $userDraftDir, $user_id) ?? ($_POST['existing_transcript'] ?? ($existing_draft['tor'] ?? null));
    $diploma = uploadDraftFile('diploma', $userDraftDir, $user_id) ?? ($_POST['existing_diploma'] ?? ($existing_draft['diploma'] ?? null));
    $professional_license = uploadDraftFile('license', $userDraftDir, $user_id) ?? ($_POST['existing_license'] ?? ($existing_draft['professional_license'] ?? null));
    $coe = uploadDraftFile('coe', $userDraftDir, $user_id) ?? ($_POST['existing_coe'] ?? ($existing_draft['coe'] ?? null));
    $seminars_trainings = uploadDraftFile('certificates', $userDraftDir, $user_id, true) ?? ($_POST['existing_certificates[]'] ?? ($existing_draft['seminars_trainings'] ?? null));
    $masteral_cert = uploadDraftFile('masteral_cert', $userDraftDir, $user_id) ?? ($_POST['existing_masteral_cert'] ?? ($existing_draft['masteral_cert'] ?? null));
    $letter_of_intent = uploadDraftFile('letter_of_intent', $userDraftDir, $user_id) ?? ($_POST['existing_letter_of_intent'] ?? ($existing_draft['letter_of_intent'] ?? null));
    
    error_log("letter_of_intent result after upload: " . ($letter_of_intent ?? 'NULL'));
    error_log("existing_letter_of_intent from POST: " . ($_POST['existing_letter_of_intent'] ?? 'NOT SET'));
    
    if ($existing_draft) {
        // Update existing draft
        $stmt = $conn->prepare("UPDATE user_draft_documents SET 
            application_letter = COALESCE(?, application_letter),
            resume = COALESCE(?, resume),
            tor = COALESCE(?, tor),
            diploma = COALESCE(?, diploma),
            professional_license = COALESCE(?, professional_license),
            coe = COALESCE(?, coe),
            seminars_trainings = COALESCE(?, seminars_trainings),
            masteral_cert = COALESCE(?, masteral_cert),
            letter_of_intent = COALESCE(?, letter_of_intent),
            updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?");
        $stmt->bind_param("sssssssssi", 
            $application_letter, $resume, $tor, $diploma, 
            $professional_license, $coe, $seminars_trainings, 
            $masteral_cert, $letter_of_intent, $user_id
        );
    } else {
        // Insert new draft
        $stmt = $conn->prepare("INSERT INTO user_draft_documents 
            (user_id, application_letter, resume, tor, diploma, professional_license, coe, seminars_trainings, masteral_cert, letter_of_intent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssssss", 
            $user_id, $application_letter, $resume, $tor, $diploma, 
            $professional_license, $coe, $seminars_trainings, $masteral_cert, $letter_of_intent
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
                'letter_of_intent' => $letter_of_intent
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save draft: ' . $conn->error]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
