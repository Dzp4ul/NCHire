<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/application_documents.php';
require_once __DIR__ . '/../shared/helpers/dynamic_application_requirements.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required.']);
    exit;
}

$sessionToken = (string)($_SESSION['csrf_token'] ?? '');
$requestToken = (string)($_POST['csrf_token'] ?? '');
if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Your session token expired. Refresh the page and try again.']);
    exit;
}

$applicationId = (int)($_POST['application_id'] ?? 0);
$appStmt = $conn->prepare('SELECT id, job_id, user_id FROM job_applicants WHERE id = ? AND user_id = ? LIMIT 1');
$appStmt->bind_param('ii', $applicationId, $userId);
$appStmt->execute();
$application = $appStmt->get_result()->fetch_assoc();
$appStmt->close();
if (!$application) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Application not found or access denied.']);
    exit;
}

$jobId = (int)$application['job_id'];
$requirements = nc_get_teaching_load_requirements($conn, $jobId, true);
$legacy = nc_requirement_legacy_definitions();
$uploaded = [];
$storedPaths = [];

try {
    $conn->begin_transaction();
    foreach ($requirements as $requirement) {
        $inputName = rtrim((string)$requirement['input_name'], '[]');
        if (!isset($_FILES[$inputName])) {
            continue;
        }
        $uploadErrors = $_FILES[$inputName]['error'] ?? UPLOAD_ERR_NO_FILE;
        if ((!is_array($uploadErrors) && (int)$uploadErrors === UPLOAD_ERR_NO_FILE)
            || (is_array($uploadErrors) && !array_filter($uploadErrors, static fn($error): bool => (int)$error !== UPLOAD_ERR_NO_FILE))) {
            continue;
        }
        $upload = $_FILES[$inputName];
        if (is_array($upload['name'] ?? null)) {
            $selectedIndex = null;
            foreach ($upload['name'] as $index => $name) {
                if ($name !== '' && (int)($upload['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    if ($selectedIndex !== null) {
                        throw new RuntimeException('Upload one replacement file per requirement.');
                    }
                    $selectedIndex = $index;
                }
            }
            if ($selectedIndex === null) {
                continue;
            }
            $upload = [
                'name' => $upload['name'][$selectedIndex],
                'type' => $upload['type'][$selectedIndex] ?? '',
                'tmp_name' => $upload['tmp_name'][$selectedIndex],
                'error' => $upload['error'][$selectedIndex],
                'size' => $upload['size'][$selectedIndex],
            ];
        }

        $stored = nc_store_secure_requirement_upload($upload, $userId);
        $storedPaths[] = dirname(__DIR__) . '/user/uploads/requirements/' . $stored['file_path'];
        $requirementId = (int)$requirement['id'];
        nc_record_requirement_submission(
            $conn,
            $applicationId,
            $requirementId,
            $stored['file_path'],
            $stored['original_filename'],
            $stored['storage_area']
        );

        $legacyKey = $requirement['legacy_document_type'] ?? null;
        if ($legacyKey && isset($legacy[$legacyKey])) {
            $column = $legacy[$legacyKey]['column'];
            $legacyPath = 'requirements/' . $stored['file_path'];
            $update = $conn->prepare("UPDATE job_applicants SET `{$column}` = ? WHERE id = ? AND user_id = ?");
            $update->bind_param('sii', $legacyPath, $applicationId, $userId);
            $update->execute();
            $update->close();
            nc_record_application_document_version($conn, $userId, $applicationId, $legacyKey, $legacyPath, $stored['original_filename']);
        }
        $uploaded[] = $requirement['requirement_name'];
    }

    if (!$uploaded) {
        throw new InvalidArgumentException('Choose at least one document to upload or replace.');
    }

    $completion = nc_requirement_completion($conn, $applicationId, $jobId);
    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Application documents updated successfully.',
        'uploaded' => $uploaded,
        'required_remaining' => $completion['required_remaining'],
        'is_complete' => $completion['is_complete'],
        'workflow_unchanged' => true,
    ]);
} catch (InvalidArgumentException $e) {
    $conn->rollback();
    foreach ($storedPaths as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    $conn->rollback();
    foreach ($storedPaths as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    error_log('upload_requirement_documents.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to update the application documents.']);
}

