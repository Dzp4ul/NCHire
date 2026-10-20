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

$rawPath = trim((string)$document['file_path']);
$pathCandidates = [$rawPath];
$firstPath = trim(explode(',', $rawPath)[0]);
if ($firstPath !== $rawPath) {
    $pathCandidates[] = $firstPath;
}

$base = $document['storage_area'] === 'requirements'
    ? realpath(__DIR__ . '/uploads/requirements')
    : realpath(__DIR__ . '/uploads');
$candidate = null;
if ($base) {
    foreach ($pathCandidates as $filePath) {
        if ($filePath === '') {
            continue;
        }
        if ($document['storage_area'] === 'requirements') {
            $candidate = realpath(__DIR__ . '/uploads/requirements/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath));
        } else {
            $legacyPath = str_replace('\\', '/', $filePath);
            $candidate = str_starts_with($legacyPath, 'uploads/')
                ? realpath(__DIR__ . '/' . str_replace('/', DIRECTORY_SEPARATOR, $legacyPath))
                : realpath(__DIR__ . '/uploads/' . basename($legacyPath));
        }
        if ($candidate && str_starts_with($candidate, $base . DIRECTORY_SEPARATOR) && is_file($candidate)) {
            break;
        }
        $candidate = null;
    }
}
if (!$candidate) {
    http_response_code(404);
    exit('The stored document is unavailable.');
}

$downloadName = basename(trim((string)$document['original_filename'])) ?: basename($candidate);
$mime = function_exists('mime_content_type') ? mime_content_type($candidate) : 'application/octet-stream';
header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($candidate));
header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', $downloadName) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($candidate);

