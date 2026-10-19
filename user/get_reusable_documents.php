<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/recruitment.php';
require_once __DIR__ . '/../shared/helpers/application_documents.php';
require_once __DIR__ . '/../shared/helpers/dynamic_application_requirements.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $result = nc_find_reusable_documents($conn, $userId);
    $masterStatus = nc_get_master_status($conn, $userId);
    $profileDocuments = nc_find_profile_education_documents($conn, $userId);
    foreach ($profileDocuments as $field => $document) {
        $result['documents'][$field] = $document;
    }
    foreach ($result['documents'] as $field => &$document) {
        $sourceApplicationId = (int)($document['source_application_id'] ?? 0);
        if ($sourceApplicationId <= 0) {
            continue;
        }
        $submissionStmt = $conn->prepare('SELECT s.id FROM applicant_requirement_submissions s
            INNER JOIN teaching_load_requirements r ON r.id = s.requirement_id
            WHERE s.application_id = ? AND r.requirement_key = ? AND s.is_active = 1
            ORDER BY s.version_number DESC, s.id DESC LIMIT 1');
        $submissionStmt->bind_param('is', $sourceApplicationId, $field);
        $submissionStmt->execute();
        $submission = $submissionStmt->get_result()->fetch_assoc();
        $submissionStmt->close();
        if ($submission) {
            $document['download_url'] = 'download_requirement.php?submission_id=' . (int)$submission['id'];
        }
    }
    unset($document);

    $jobId = (int)($_GET['job_id'] ?? 0);
    $missing = [];
    $definitions = nc_application_document_definitions();
    $configuredRequirements = $jobId > 0 ? nc_get_teaching_load_requirements($conn, $jobId, true) : [];
    if ($jobId > 0) {
        foreach ($configuredRequirements as $requirement) {
            if (empty($requirement['is_required'])) {
                continue;
            }
            $field = $requirement['legacy_document_type'] ?? null;
            if (!$field || !isset($result['documents'][$field])) {
                $missing[] = [
                    'document_type' => $field ?: $requirement['requirement_key'],
                    'label' => $requirement['requirement_name'],
                ];
            }
        }
    } else {
      $requiredFields = nc_required_application_document_fields((bool)$masterStatus['requires_ongoing_documents']);
      foreach ($requiredFields as $field) {
        if (!isset($result['documents'][$field])) {
            $missing[] = [
                'document_type' => $field,
                'label' => $definitions[$field]['label'] ?? $field,
            ];
        }
      }
    }

    echo json_encode([
        'success' => true,
        'is_existing_applicant' => $result['is_existing_applicant'],
        'application_count' => $result['application_count'],
        'documents' => array_values($result['documents']),
        'missing_required_documents' => $missing,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load existing documents.']);
}

$conn->close();
