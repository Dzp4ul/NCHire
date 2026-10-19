<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/dynamic_application_requirements.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$applicationId = (int)($_GET['application_id'] ?? 0);
$jobId = (int)($_GET['job_id'] ?? 0);

if ($applicationId > 0) {
    $stmt = $conn->prepare('SELECT id, job_id FROM job_applicants WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $applicationId, $userId);
    $stmt->execute();
    $application = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$application) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Application not found or access denied.']);
        exit;
    }
    $jobId = (int)$application['job_id'];
}

if ($jobId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'A teaching load is required.']);
    exit;
}

$jobStmt = $conn->prepare('SELECT id FROM job WHERE id = ? LIMIT 1');
$jobStmt->bind_param('i', $jobId);
$jobStmt->execute();
$jobExists = $jobStmt->get_result()->num_rows > 0;
$jobStmt->close();
if (!$jobExists) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Teaching load not found.']);
    exit;
}

$completion = $applicationId > 0
    ? nc_requirement_completion($conn, $applicationId, $jobId)
    : [
        'requirements' => nc_get_teaching_load_requirements($conn, $jobId, true),
        'required_total' => 0,
        'required_complete' => 0,
        'required_remaining' => 0,
        'is_complete' => false,
        'missing' => [],
    ];

if ($applicationId <= 0) {
    $required = array_values(array_filter($completion['requirements'], static fn(array $row): bool => !empty($row['is_required'])));
    $completion['required_total'] = count($required);
    $completion['required_remaining'] = count($required);
    $completion['missing'] = $required;
    $completion['is_complete'] = count($required) === 0;
}

foreach ($completion['requirements'] as &$requirement) {
    if (!empty($requirement['submission']['id'])) {
        $requirement['submission']['download_url'] = 'download_requirement.php?submission_id=' . (int)$requirement['submission']['id'];
    }
}
unset($requirement);

$updatedAt = null;
foreach ($completion['requirements'] as $requirement) {
    $candidate = $requirement['updated_at'] ?? null;
    if ($candidate && ($updatedAt === null || $candidate > $updatedAt)) {
        $updatedAt = $candidate;
    }
}

echo json_encode([
    'success' => true,
    'job_id' => $jobId,
    'application_id' => $applicationId ?: null,
    'requirements_updated_at' => $updatedAt,
    'requirements' => $completion['requirements'],
    'required_total' => $completion['required_total'],
    'required_complete' => $completion['required_complete'],
    'required_remaining' => $completion['required_remaining'],
    'is_complete' => $completion['is_complete'],
]);

