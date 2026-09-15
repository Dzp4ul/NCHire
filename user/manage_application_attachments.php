<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/recruitment.php';
require_once __DIR__ . '/../shared/helpers/application_documents.php';

$sessionUserId = (int)($_SESSION['user_id'] ?? 0);
$sessionEmail = trim((string)($_SESSION['email'] ?? ($_SESSION['user_email'] ?? ($_SESSION['applicant_email'] ?? ''))));
if ($sessionUserId <= 0 && $sessionEmail === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$applicationId = (int)($_REQUEST['application_id'] ?? 0);
if ($applicationId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid application is required.']);
    exit;
}

$definitions = nc_application_document_definitions();
$documentColumns = implode(', ', array_map(static fn(string $field): string => "ja.`{$field}`", array_keys($definitions)));
$ownerStmt = $conn->prepare("SELECT ja.id, ja.user_id, ja.applicant_email, ja.applied_date, ja.status, ja.resubmission_documents, {$documentColumns}
                             FROM job_applicants ja
                             WHERE ja.id = ? AND (ja.user_id = ? OR (? <> '' AND ja.applicant_email = ?))
                             LIMIT 1");
$ownerStmt->bind_param('iiss', $applicationId, $sessionUserId, $sessionEmail, $sessionEmail);
$ownerStmt->execute();
$application = $ownerStmt->get_result()->fetch_assoc();
$ownerStmt->close();

if (!$application) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Application not found or access denied.']);
    exit;
}

$applicantId = (int)($application['user_id'] ?? 0);
if ($applicantId <= 0) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'This legacy application is not linked to an applicant record.']);
    exit;
}

function nc_attachment_response(mysqli $conn, array $application, array $definitions): array
{
    $applicationId = (int)$application['id'];
    $versionData = [];
    if (nc_table_exists($conn, 'application_document_versions')) {
        $stmt = $conn->prepare('SELECT document_type, file_name, original_name, version_number, is_active, inherited_from_application_id, uploaded_at FROM application_document_versions WHERE application_id = ? ORDER BY document_type, version_number DESC');
        if ($stmt) {
            $stmt->bind_param('i', $applicationId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $versionData[$row['document_type']][] = $row;
            }
            $stmt->close();
        }
    }

    $masterStatus = nc_get_master_status($conn, (int)$application['user_id']);
    $required = array_flip(nc_required_application_document_fields((bool)$masterStatus['requires_ongoing_documents']));
    $documents = [];
    foreach ($definitions as $field => $definition) {
        $currentValue = trim((string)($application[$field] ?? ''));
        $versions = $versionData[$field] ?? [];
        $activeVersion = null;
        foreach ($versions as $version) {
            if ((int)$version['is_active'] === 1 && $version['file_name'] === $currentValue) {
                $activeVersion = $version;
                break;
            }
        }
        $files = [];
        foreach (nc_document_file_names($currentValue, (bool)$definition['multiple']) as $fileName) {
            $files[] = [
                'name' => basename($fileName),
                'url' => 'uploads/' . rawurlencode(basename($fileName)),
            ];
        }
        $documents[] = [
            'document_type' => $field,
            'label' => $definition['label'],
            'required' => isset($required[$field]),
            'multiple' => (bool)$definition['multiple'],
            'has_file' => $currentValue !== '',
            'files' => $files,
            'uploaded_at' => $activeVersion['uploaded_at'] ?? $application['applied_date'],
            'status' => $currentValue !== '' ? 'Current / Active' : 'Missing',
            'version_number' => (int)($activeVersion['version_number'] ?? ($currentValue !== '' ? 1 : 0)),
            'history_count' => count($versions),
        ];
    }
    return $documents;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'success' => true,
        'application_id' => $applicationId,
        'documents' => nc_attachment_response($conn, $application, $definitions),
    ]);
    $conn->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

function nc_store_attachment_upload(string $field, bool $multiple, string $uploadDir): ?array
{
    if (!isset($_FILES[$field])) {
        return null;
    }
    $file = $_FILES[$field];
    $names = $multiple && is_array($file['name']) ? $file['name'] : [$file['name']];
    $tmpNames = $multiple && is_array($file['tmp_name']) ? $file['tmp_name'] : [$file['tmp_name']];
    $sizes = $multiple && is_array($file['size']) ? $file['size'] : [$file['size']];
    $errors = $multiple && is_array($file['error']) ? $file['error'] : [$file['error']];
    $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    $allowedMimes = [
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip', 'image/jpeg', 'image/png', 'application/octet-stream',
    ];
    $stored = [];
    $originals = [];
    $paths = [];
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    try {
        foreach ($names as $index => $original) {
            if ((int)$errors[$index] === UPLOAD_ERR_NO_FILE || trim((string)$original) === '') {
                continue;
            }
            if ((int)$errors[$index] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('One of the selected files could not be uploaded.');
            }
            if ((int)$sizes[$index] <= 0 || (int)$sizes[$index] > 5 * 1024 * 1024) {
                throw new RuntimeException('Each attachment must be 5MB or smaller.');
            }
            $extension = strtolower(pathinfo((string)$original, PATHINFO_EXTENSION));
            $mime = $finfo->file($tmpNames[$index]);
            if (!in_array($extension, $allowedExtensions, true) || !in_array($mime, $allowedMimes, true)) {
                throw new RuntimeException('Only PDF, DOC, DOCX, JPG, and PNG files are accepted.');
            }
            $safeOriginal = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename((string)$original));
            $storedName = time() . '_' . bin2hex(random_bytes(6)) . '_' . $safeOriginal;
            $target = $uploadDir . $storedName;
            if (!move_uploaded_file($tmpNames[$index], $target)) {
                throw new RuntimeException('Unable to store an uploaded attachment.');
            }
            $stored[] = $storedName;
            $originals[] = $safeOriginal;
            $paths[] = $target;
        }
    } catch (Throwable $exception) {
        foreach ($paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        throw $exception;
    }

    if (!$stored) {
        return null;
    }
    return [
        'file_name' => implode(',', $stored),
        'original_name' => implode(',', $originals),
        'paths' => $paths,
    ];
}

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to initialize attachment storage.']);
    exit;
}

$uploadedPaths = [];
$updates = [];
$transactionStarted = false;
try {
    foreach ($definitions as $field => $definition) {
        $upload = nc_store_attachment_upload($field, (bool)$definition['multiple'], $uploadDir);
        if (!$upload) {
            continue;
        }
        $updates[$field] = $upload;
        $uploadedPaths = array_merge($uploadedPaths, $upload['paths']);
    }
    if (!$updates) {
        throw new RuntimeException('Select at least one attachment to add or update.');
    }

    $conn->begin_transaction();
    $transactionStarted = true;
    foreach ($updates as $field => $upload) {
        nc_seed_legacy_document_version(
            $conn,
            $applicantId,
            $applicationId,
            $field,
            $application[$field] ?? null,
            $application['applied_date'] ?? null
        );
        $update = $conn->prepare("UPDATE job_applicants SET `{$field}` = ? WHERE id = ? AND user_id = ?");
        if (!$update) {
            throw new RuntimeException('Unable to prepare the attachment update.');
        }
        $update->bind_param('sii', $upload['file_name'], $applicationId, $applicantId);
        if (!$update->execute()) {
            throw new RuntimeException('Unable to update the selected attachment.');
        }
        $update->close();
        if (!nc_record_application_document_version($conn, $applicantId, $applicationId, $field, $upload['file_name'], $upload['original_name'])) {
            throw new RuntimeException('Unable to record the attachment version. Run the document-version migration first.');
        }
        $application[$field] = $upload['file_name'];
    }

    // Updating through this screen also satisfies matching admin-requested
    // resubmissions. Unrelated requested documents remain outstanding.
    $resubmissionCompleted = false;
    if (($application['status'] ?? '') === 'Resubmission Required') {
        $requested = nc_parse_document_list($application['resubmission_documents'] ?? null);
        $remaining = array_values(array_diff($requested, array_keys($updates)));
        if (!$remaining) {
            $statusUpdate = $conn->prepare("UPDATE job_applicants SET status = 'Resubmitted', resubmission_documents = NULL, resubmission_notes = NULL WHERE id = ? AND user_id = ?");
            $statusUpdate->bind_param('ii', $applicationId, $applicantId);
            if (!$statusUpdate->execute()) {
                throw new RuntimeException('Unable to complete the requested document resubmission.');
            }
            $statusUpdate->close();
            $resubmissionCompleted = true;
        } else {
            $remainingJson = json_encode($remaining);
            $statusUpdate = $conn->prepare('UPDATE job_applicants SET resubmission_documents = ? WHERE id = ? AND user_id = ?');
            $statusUpdate->bind_param('sii', $remainingJson, $applicationId, $applicantId);
            if (!$statusUpdate->execute()) {
                throw new RuntimeException('Unable to update the remaining document requests.');
            }
            $statusUpdate->close();
        }
    }
    $conn->commit();
    $transactionStarted = false;

    echo json_encode([
        'success' => true,
        'message' => $resubmissionCompleted
            ? 'Requested attachments updated and resubmitted successfully.'
            : (count($updates) === 1 ? 'Attachment updated successfully.' : 'Attachments updated successfully.'),
        'documents' => nc_attachment_response($conn, $application, $definitions),
    ]);
} catch (Throwable $exception) {
    if ($transactionStarted) {
        try { $conn->rollback(); } catch (Throwable $_ignored) {}
    }
    foreach ($uploadedPaths as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
}

$conn->close();
