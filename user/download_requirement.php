<?php
session_start();

require_once __DIR__ . '/../config/database.php';

$submissionId = (int)($_GET['submission_id'] ?? 0);
if ($submissionId <= 0) {
    http_response_code(404);
    exit('Document not found.');
}

$stmt = $conn->prepare('SELECT s.file_path, s.original_filename, s.storage_area,
        ja.user_id, ja.assigned_to_department, j.department_role
    FROM applicant_requirement_submissions s
    INNER JOIN job_applicants ja ON ja.id = s.application_id
    INNER JOIN teaching_load_requirements r ON r.id = s.requirement_id
    INNER JOIN job j ON j.id = r.teaching_load_id AND j.id = ja.job_id
    WHERE s.id = ? LIMIT 1');
$stmt->bind_param('i', $submissionId);
$stmt->execute();
$document = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$document) {
    http_response_code(404);
    exit('Document not found.');
}

$authorized = (int)($_SESSION['user_id'] ?? 0) > 0 && (int)$_SESSION['user_id'] === (int)$document['user_id'];
if (!$authorized && !empty($_SESSION['admin_logged_in'])) {
    $role = (string)($_SESSION['admin_role'] ?? '');
    if ($role === 'Secretary') {
        $authorized = true;
    } elseif ($role === 'Department Head') {
        $normalize = static fn(string $value): string => $value === 'Computer Science' ? 'Computing Studies' : trim($value);
        $authorized = $normalize((string)($_SESSION['admin_department'] ?? '')) === $normalize((string)$document['department_role']);
    }
}
if (!$authorized) {
    http_response_code(403);
    exit('Access denied.');
}

$filePath = trim(explode(',', (string)$document['file_path'])[0]);
if ($document['storage_area'] === 'requirements') {
    $base = realpath(__DIR__ . '/uploads/requirements');
    $candidate = realpath(__DIR__ . '/uploads/requirements/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath));
} else {
    $base = realpath(__DIR__ . '/uploads');
    $legacyPath = str_replace('\\', '/', $filePath);
    if (str_starts_with($legacyPath, 'uploads/')) {
        $candidate = realpath(__DIR__ . '/' . str_replace('/', DIRECTORY_SEPARATOR, $legacyPath));
    } else {
        $candidate = realpath(__DIR__ . '/uploads/' . basename($legacyPath));
    }
}
if (!$base || !$candidate || !str_starts_with($candidate, $base . DIRECTORY_SEPARATOR) || !is_file($candidate)) {
    http_response_code(404);
    exit('The stored document is unavailable.');
}

$downloadName = basename(trim(explode(',', (string)$document['original_filename'])[0]));
$mime = function_exists('mime_content_type') ? mime_content_type($candidate) : 'application/octet-stream';
header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($candidate));
header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', $downloadName) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($candidate);

