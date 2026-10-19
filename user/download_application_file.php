<?php
session_start();

require_once __DIR__ . '/../config/database.php';

$applicationId = (int)($_GET['application_id'] ?? 0);
$type = (string)($_GET['type'] ?? '');
$allowedColumns = ['psych' => 'psych_exam_receipt'];
if ($applicationId <= 0 || !isset($allowedColumns[$type])) {
    http_response_code(404);
    exit('Document not found.');
}

$column = $allowedColumns[$type];
$stmt = $conn->prepare("SELECT ja.user_id, ja.assigned_to_department, ja.`{$column}` AS file_path, j.department_role
    FROM job_applicants ja LEFT JOIN job j ON j.id = ja.job_id WHERE ja.id = ? LIMIT 1");
$stmt->bind_param('i', $applicationId);
$stmt->execute();
$document = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$document || trim((string)$document['file_path']) === '') {
    http_response_code(404);
    exit('Document not found.');
}

$authorized = (int)($_SESSION['user_id'] ?? 0) > 0 && (int)$_SESSION['user_id'] === (int)$document['user_id'];
if (!$authorized && !empty($_SESSION['admin_logged_in'])) {
    $role = (string)($_SESSION['admin_role'] ?? '');
    if ($role === 'Secretary') {
        $authorized = true;
    } elseif (in_array($role, ['Department Head', 'HR Manager', 'Recruiter'], true)) {
        $normalize = static fn(string $value): string => trim($value) === 'Computer Science' ? 'Computing Studies' : trim($value);
        $authorized = $normalize((string)($_SESSION['admin_department'] ?? '')) === $normalize((string)($document['department_role'] ?? $document['assigned_to_department']));
    }
}
if (!$authorized) {
    http_response_code(403);
    exit('Access denied.');
}

$relative = str_replace('\\', '/', (string)$document['file_path']);
$base = realpath(__DIR__ . '/uploads');
$candidate = str_starts_with($relative, 'uploads/')
    ? realpath(__DIR__ . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative))
    : realpath(__DIR__ . '/uploads/' . str_replace('/', DIRECTORY_SEPARATOR, $relative));
if (!$base || !$candidate || !str_starts_with($candidate, $base . DIRECTORY_SEPARATOR) || !is_file($candidate)) {
    http_response_code(404);
    exit('The stored document is unavailable.');
}

$name = basename($candidate);
$mime = function_exists('mime_content_type') ? mime_content_type($candidate) : 'application/octet-stream';
header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($candidate));
header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', $name) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($candidate);

