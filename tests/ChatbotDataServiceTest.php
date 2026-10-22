<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/chatbot/services/RecruitmentDataService.php';
require_once __DIR__ . '/../api/chatbot/services/ChatbotToolService.php';

$database = getenv('NCHIRE_DB_NAME') ?: 'nchire_e2e';
if (!str_ends_with($database, '_e2e')) {
    fwrite(STDERR, "Refusing to run chatbot data tests outside an _e2e database.\n");
    exit(1);
}

$conn = new mysqli('127.0.0.1', 'root', '', $database);
$conn->set_charset('utf8mb4');

function chatbotAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function staffAccount(mysqli $conn, string $email): array
{
    $stmt = $conn->prepare('SELECT id, full_name AS name, email, role, department FROM admin_users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) throw new RuntimeException('Missing E2E staff account: ' . $email);
    return $row;
}

$secretaryData = new RecruitmentDataService($conn, staffAccount($conn, 'secretary.e2e@example.test'));
$secretaryTools = new ChatbotToolService($secretaryData, staffAccount($conn, 'secretary.e2e@example.test'));
$deanCsData = new RecruitmentDataService($conn, staffAccount($conn, 'dean.cs.e2e@example.test'));
$deanEducationData = new RecruitmentDataService($conn, staffAccount($conn, 'dean.edu.e2e@example.test'));
$adminData = new RecruitmentDataService($conn, staffAccount($conn, 'admin.e2e@example.test'));

$all = $secretaryData->searchApplications(['status' => 'all', 'limit' => 25]);
chatbotAssert($all['total_applications'] === 3, 'Secretary should see three non-archived synthetic applications.');
chatbotAssert($all['unique_applicants'] === 2, 'Application and unique-applicant counts must be distinct.');

$lateApplicationId = (int)$all['records'][0]['application_id'];
$lateDate = (new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))->format('Y-m-d') . ' 20:15:00';
$lateStmt = $conn->prepare('UPDATE job_applicants SET applied_date = ? WHERE id = ?');
$lateStmt->bind_param('si', $lateDate, $lateApplicationId);
$lateStmt->execute();
$lateStmt->close();
$filterDay = substr($lateDate, 0, 10);
$sameDay = $secretaryData->searchApplications(['applied_from' => $filterDay, 'applied_to' => $filterDay]);
chatbotAssert($sameDay['total_applications'] === 3, 'An inclusive application end date must include records later that day.');

$csApplications = $secretaryData->searchApplications(['job_query' => 'E2E CS 101', 'limit' => 25]);
$scheduledApplicationId = 0;
foreach ($csApplications['records'] as $record) {
    if (($record['workflow_stage'] ?? '') === 'waiting_interview_schedule') {
        $scheduledApplicationId = (int)$record['application_id'];
        break;
    }
}
chatbotAssert($scheduledApplicationId > 0, 'The schedule fixture application must exist.');
$tomorrow = (new DateTimeImmutable('tomorrow 09:00:00', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
$scheduleStmt = $conn->prepare("UPDATE job_applicants SET workflow_stage = 'interview_scheduled', status = 'Interview Scheduled', interview_date = ? WHERE id = ?");
$scheduleStmt->bind_param('si', $tomorrow, $scheduledApplicationId);
$scheduleStmt->execute();
$scheduleStmt->close();
$tomorrowSchedules = $secretaryData->getSchedules(['schedule_type' => 'interview', 'period' => 'tomorrow']);
chatbotAssert($tomorrowSchedules['total_schedules'] === 1, 'Tomorrow schedule retrieval must use the Asia/Manila date boundary.');
chatbotAssert((int)$tomorrowSchedules['schedules'][0]['application_id'] === $scheduledApplicationId, 'Schedule retrieval must return the verified application ID.');
$deanCsSchedules = $deanCsData->getSchedules(['schedule_type' => 'interview', 'period' => 'tomorrow']);
$deanEducationSchedules = $deanEducationData->getSchedules(['schedule_type' => 'interview', 'period' => 'tomorrow']);
chatbotAssert($deanCsSchedules['total_schedules'] === 1, 'Computing Dean must see the Computing interview schedule.');
chatbotAssert($deanEducationSchedules['total_schedules'] === 0, 'A Dean schedule query must not expose another department.');

$pending = $secretaryData->searchApplications(['status' => 'pending']);
chatbotAssert($pending['total_applications'] === 1, 'Pending must use the implemented review stages.');
chatbotAssert(str_contains($pending['definition'], 'Secretary review'), 'Pending result must explain its verified definition.');

$deanCs = $deanCsData->searchApplications(['status' => 'all']);
chatbotAssert($deanCs['total_applications'] === 1, 'Computing Dean must see only the transferred active Computing application.');
chatbotAssert(($deanCs['records'][0]['department'] ?? '') === 'BS Computer Science', 'Computing Dean result must stay in the Computing program.');

$deanEducation = $deanEducationData->searchApplications(['status' => 'all']);
chatbotAssert($deanEducation['total_applications'] === 1, 'Education Dean must see only the transferred Education application.');

$educationApplicationId = (int)$deanEducation['records'][0]['application_id'];
$crossDepartment = $deanCsData->getApplicationDetails(['application_id' => $educationApplicationId]);
chatbotAssert($crossDepartment['status'] === 'not_found', 'Direct IDs must not bypass Dean department scope.');

$complete = $secretaryData->searchApplications(['requirement_state' => 'complete']);
chatbotAssert($complete['total_applications'] === 1, 'Dynamic requirement completion must use active required submissions.');
$missing = $secretaryData->searchApplications(['requirement_state' => 'missing']);
chatbotAssert($missing['total_applications'] === 2, 'Missing requirements count should include the two incomplete active applications.');

$caseyDetails = $secretaryData->getApplicationDetails(['application_id' => (int)$all['records'][0]['application_id']]);
chatbotAssert($caseyDetails['status'] === 'ok', 'Secretary should retrieve authorized applicant detail.');
chatbotAssert(isset($caseyDetails['applications'][0]['education']), 'Recruitment detail must include education records.');
chatbotAssert(!isset($caseyDetails['applications'][0]['application']['applicant_email']), 'Chatbot detail must minimize applicant PII.');

$adminDetails = $adminData->getApplicationDetails(['application_id' => (int)$all['records'][0]['application_id']]);
chatbotAssert(($adminDetails['applications'][0]['access_level'] ?? '') === 'basic', 'Admin detail must honor the implemented basic-record boundary.');
chatbotAssert(!isset($adminDetails['applications'][0]['education']), 'Admin chatbot must not expose detailed qualifications.');

$openLoads = $secretaryData->searchTeachingLoads(['status' => 'open', 'limit' => 25]);
chatbotAssert($openLoads['total_teaching_loads'] === 2, 'Open vacancy query must exclude closed and expired teaching loads.');
$deanLoads = $deanCsData->searchTeachingLoads(['status' => 'open', 'limit' => 25]);
chatbotAssert($deanLoads['total_teaching_loads'] === 1, 'Dean teaching-load queries must be department scoped.');

$overview = $secretaryData->getRecruitmentOverview([]);
chatbotAssert(($overview['counts']['total_applications'] ?? -1) === 3, 'Overview must match the direct active application count.');
chatbotAssert(($overview['counts']['unique_applicants'] ?? -1) === 2, 'Overview unique count must match direct data.');

$nullableOverview = $secretaryTools->dispatch('get_recruitment_overview', [
    'applied_from' => null,
    'applied_to' => null,
    'department' => null,
    'transition_from' => null,
    'transition_to' => null,
]);
chatbotAssert(
    ($nullableOverview['counts']['total_applications'] ?? -1) === 3,
    'Null optional fields emitted by tool-capable models must be treated as omitted filters.'
);

$before = (int)$conn->query("SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'nchire_e2e' AND TABLE_NAME = 'job_applicants'")->fetch_assoc()['count'];
$secretaryData->searchApplications(['query' => "x%' OR 1=1; DROP TABLE job_applicants; --"]);
$after = (int)$conn->query("SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'nchire_e2e' AND TABLE_NAME = 'job_applicants'")->fetch_assoc()['count'];
chatbotAssert($before === 1 && $after === 1, 'Search text must remain a bound value and never execute SQL.');

$invalidRejected = false;
try {
    $secretaryTools->dispatch('search_applications', ['sql' => 'SELECT * FROM applicants']);
} catch (InvalidArgumentException $e) {
    $invalidRejected = true;
}
chatbotAssert($invalidRejected, 'Tool dispatcher must reject unknown/unrestricted arguments.');

$help = $secretaryTools->dispatch('get_system_help', ['topic' => 'recruitment_workflow']);
chatbotAssert(str_contains(implode(' ', $help['instructions']), 'secretary_review'), 'System help must describe the implemented workflow.');

echo "Chatbot data-service tests passed.\n";

