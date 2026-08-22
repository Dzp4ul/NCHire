<?php
session_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

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

require_once $rootPath . '/config/db.php';
require_once __DIR__ . '/services/EnvLoader.php';
require_once __DIR__ . '/services/GroqService.php';
require_once __DIR__ . '/services/ChatbotIntentService.php';
require_once __DIR__ . '/services/RecruitmentDataService.php';
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

$allowedRoles = ['Secretary', 'Department Head'];
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

$statusStmt = $conn->prepare("SELECT status FROM admin_users WHERE id = ? LIMIT 1");
if (!$statusStmt) {
    chatbot_json_response(500, [
        'success' => false,
        'reply' => 'The AI Recruitment Assistant is temporarily unavailable. Please try again.',
        'type' => 'server_error',
    ]);
}
$statusStmt->bind_param('i', $admin['id']);
$statusStmt->execute();
$statusResult = $statusStmt->get_result();
$statusRow = $statusResult->fetch_assoc();
$statusStmt->close();

if (!$statusRow || $statusRow['status'] !== 'Active') {
    chatbot_json_response(403, [
        'success' => false,
        'reply' => 'Your account is not authorized to use the AI Recruitment Assistant.',
        'type' => 'forbidden',
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

if (($input['action'] ?? '') === 'clear') {
    unset($_SESSION['nchire_chatbot_history'], $_SESSION['nchire_chatbot_context']);
    chatbot_json_response(200, [
        'success' => true,
        'reply' => 'Conversation cleared.',
        'type' => 'conversation_cleared',
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

$service = new ChatbotService($conn, $admin);
$response = $service->handle($message);

http_response_code(($response['success'] ?? false) ? 200 : 503);
echo json_encode($response);
