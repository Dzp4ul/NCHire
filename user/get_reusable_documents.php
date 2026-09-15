<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/recruitment.php';
require_once __DIR__ . '/../shared/helpers/application_documents.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $result = nc_find_reusable_documents($conn, $userId);
    $masterStatus = nc_get_master_status($conn, $userId);
    $requiredFields = nc_required_application_document_fields((bool)$masterStatus['requires_ongoing_documents']);
    $missing = [];
    $definitions = nc_application_document_definitions();
    foreach ($requiredFields as $field) {
        if (!isset($result['documents'][$field])) {
            $missing[] = [
                'document_type' => $field,
                'label' => $definitions[$field]['label'] ?? $field,
            ];
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
