<?php
session_start();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function ranking_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    ranking_response(405, ['success' => false, 'error' => 'Method not allowed.', 'code' => 'method_not_allowed']);
}

if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || empty($_SESSION['admin_id'])) {
    ranking_response(401, ['success' => false, 'error' => 'Unauthorized.', 'code' => 'unauthorized']);
}

$csrf = $_SERVER['HTTP_X_NCHIRE_CSRF'] ?? '';
if (empty($_SESSION['ranking_csrf_token']) || !is_string($csrf) || !hash_equals($_SESSION['ranking_csrf_token'], $csrf)) {
    ranking_response(403, ['success' => false, 'error' => 'Invalid request token.', 'code' => 'invalid_csrf']);
}

$root = dirname(__DIR__, 2);
require_once $root . '/config/db.php';
require_once $root . '/api/chatbot/services/EnvLoader.php';
require_once $root . '/api/chatbot/services/GroqService.php';
require_once $root . '/api/ranking/services/CandidateScoringService.php';
require_once $root . '/api/ranking/services/CandidateRankingAiService.php';
require_once $root . '/api/ranking/services/CandidateRankingService.php';

$adminId = (int)($_SESSION['admin_id'] ?? 0);
$statusStmt = $conn->prepare('SELECT role, department, status FROM admin_users WHERE id = ? LIMIT 1');
if (!$statusStmt) ranking_response(500, ['success' => false, 'error' => 'Unable to verify authorization.', 'code' => 'database_error']);
$statusStmt->bind_param('i', $adminId);
$statusStmt->execute();
$account = $statusStmt->get_result()->fetch_assoc();
$statusStmt->close();
if (!$account || ($account['status'] ?? '') !== 'Active') {
    ranking_response(403, ['success' => false, 'error' => 'Your account is not authorized to access candidate rankings.', 'code' => 'forbidden']);
}
$admin = [
    'id' => $adminId,
    'role' => (string)($account['role'] ?? ''),
    'department' => (string)($account['department'] ?? ''),
];
$allowedRoles = ['Secretary', 'Department Head', 'HR Manager', 'Recruiter'];
if (!in_array($admin['role'], $allowedRoles, true)) {
    ranking_response(403, ['success' => false, 'error' => 'Your role is not authorized to access candidate rankings.', 'code' => 'forbidden']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) ranking_response(400, ['success' => false, 'error' => 'Invalid JSON request.', 'code' => 'invalid_request']);

ChatbotEnvLoader::load($root);
$scorer = new CandidateScoringService();
$ai = new CandidateRankingAiService(new GroqService());
$service = new CandidateRankingService($conn, $scorer, $ai);
$action = (string)($input['action'] ?? 'list');

try {
    if (in_array($action, ['list', 'recalculate'], true)) {
        $jobId = filter_var($input['job_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$jobId) throw new RankingException('invalid_request', 'A valid job posting is required.', 422);
        $data = $service->listForJob((int)$jobId, $admin, $action === 'recalculate');
        ranking_response(200, ['success' => true, 'data' => $data]);
    }

    if (in_array($action, ['details', 'analyze'], true)) {
        $applicationId = filter_var($input['application_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$applicationId) throw new RankingException('invalid_request', 'A valid application is required.', 422);
        $data = $service->details((int)$applicationId, $admin, $action === 'analyze');
        ranking_response(200, ['success' => true, 'data' => $data]);
    }

    throw new RankingException('invalid_action', 'Unsupported ranking action.', 400);
} catch (RankingException $e) {
    ranking_response($e->getHttpStatus(), ['success' => false, 'error' => $e->getMessage(), 'code' => $e->getCategory()]);
} catch (Throwable $e) {
    error_log('Candidate ranking endpoint error: ' . get_class($e) . ': ' . $e->getMessage());
    ranking_response(500, ['success' => false, 'error' => 'Candidate ranking is temporarily unavailable.', 'code' => 'server_error']);
}
