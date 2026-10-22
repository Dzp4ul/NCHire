<?php
session_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'reply' => 'Method not allowed.', 'type' => 'method_not_allowed']);
    exit();
}

$rootPath = dirname(__DIR__, 2);

require_once __DIR__ . '/services/EnvLoader.php';
require_once __DIR__ . '/services/GroqService.php';
require_once __DIR__ . '/services/RecruitmentDataService.php';
require_once __DIR__ . '/services/ChatbotToolService.php';
require_once __DIR__ . '/services/ChatbotService.php';

ChatbotEnvLoader::load($rootPath);

function chatbot_json_response($statusCode, array $payload)
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    chatbot_json_response(401, [
        'success' => false,
        'reply' => 'Unauthorized.',
        'type' => 'unauthorized',
    ]);
}

$admin = [
    'id' => (int) ($_SESSION['admin_id'] ?? 0),
    'name' => $_SESSION['admin_name'] ?? 'Unknown Admin',
    'email' => $_SESSION['admin_email'] ?? '',
    'role' => $_SESSION['admin_role'] ?? '',
    'department' => $_SESSION['admin_department'] ?? '',
];

$allowedRoles = ['Admin', 'Secretary', 'Department Head'];
if (!in_array($admin['role'], $allowedRoles, true)) {
    chatbot_json_response(403, [
        'success' => false,
        'reply' => 'You are not authorized to use the AI Recruitment Assistant.',
        'type' => 'forbidden',
    ]);
}

if ($admin['id'] <= 0) {
    chatbot_json_response(401, [
        'success' => false,
        'reply' => 'Unauthorized.',
        'type' => 'unauthorized',
    ]);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    chatbot_json_response(400, [
        'success' => false,
        'reply' => 'Invalid request body.',
        'type' => 'invalid_request',
    ]);
}

$csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$sessionToken = (string)($_SESSION['csrf_token'] ?? '');
if ($csrfToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
    chatbot_json_response(403, [
        'success' => false,
        'reply' => 'Your session token expired. Refresh the page and try again.',
        'type' => 'invalid_csrf_token',
    ]);
}

mysqli_report(MYSQLI_REPORT_OFF);
try {
    $databaseName = getenv('NCHIRE_DB_NAME') ?: 'nchire';
    $conn = @new mysqli('127.0.0.1', 'root', '', $databaseName);
} catch (Throwable $connectionError) {
    $conn = null;
}
if (!$conn instanceof mysqli || $conn->connect_errno) {
    chatbot_json_response(503, [
        'success' => false,
        'reply' => 'The NCHire database is unavailable, so I cannot verify an answer. Please try again when the connection is restored.',
        'type' => 'database_unavailable',
    ]);
}
$conn->set_charset('utf8mb4');

$statusStmt = $conn->prepare("SELECT status, role, department, full_name, email FROM admin_users WHERE id = ? LIMIT 1");
if (!$statusStmt) {
    chatbot_json_response(503, [
        'success' => false,
        'reply' => 'The NCHire database could not verify your account, so no answer was generated.',
        'type' => 'database_unavailable',
    ]);
}
$statusStmt->bind_param('i', $admin['id']);
$statusStmt->execute();
$statusRow = $statusStmt->get_result()->fetch_assoc();
$statusStmt->close();

if (!$statusRow || $statusRow['status'] !== 'Active' || !in_array($statusRow['role'], $allowedRoles, true)) {
    chatbot_json_response(403, [
        'success' => false,
        'reply' => 'Your account is not authorized to use the AI Recruitment Assistant.',
        'type' => 'forbidden',
    ]);
}

// Use the current database values rather than potentially stale session role data.
$admin['name'] = $statusRow['full_name'];
$admin['email'] = $statusRow['email'];
$admin['role'] = $statusRow['role'];
$admin['department'] = $statusRow['department'];

$service = new ChatbotService($conn, $admin);
$action = (string)($input['action'] ?? 'message');
if ($action === 'clear') {
    $service->clear();
    chatbot_json_response(200, [
        'success' => true,
        'reply' => 'Conversation cleared.',
        'type' => 'conversation_cleared',
    ]);
}
if ($action === 'history') {
    chatbot_json_response(200, [
        'success' => true,
        'messages' => $service->getHistory(),
        'type' => 'conversation_history',
    ]);
}
if ($action !== 'message') {
    chatbot_json_response(400, [
        'success' => false,
        'reply' => 'Unsupported chatbot action.',
        'type' => 'invalid_request',
    ]);
}

$message = $input['message'] ?? null;
if (!is_string($message)) {
    chatbot_json_response(400, [
        'success' => false,
        'reply' => 'Message is required.',
        'type' => 'invalid_request',
    ]);
}

$message = trim($message);
if ($message === '') {
    chatbot_json_response(400, [
        'success' => false,
        'reply' => 'Please enter a message before sending.',
        'type' => 'invalid_request',
    ]);
}

if (strlen($message) > 1000) {
    chatbot_json_response(400, [
        'success' => false,
        'reply' => 'Please keep chatbot messages under 1000 characters.',
        'type' => 'invalid_request',
    ]);
}

$response = $service->handle($message);

http_response_code(($response['success'] ?? false) ? 200 : 503);
echo json_encode($response);
