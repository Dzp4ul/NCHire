<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
requireAdminApiAuth(['Secretary', 'Department Head', 'HR Manager', 'Recruiter']);

header('Content-Type: application/json');

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = getenv('NCHIRE_DB_NAME') ?: 'nchire';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    $conn->set_charset('utf8mb4');
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to connect to the database.']);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true);
$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!$id) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Select a valid teaching load to delete.']);
    $conn->close();
    exit;
}

$adminName = (string)($_SESSION['admin_name'] ?? 'Unknown Admin');

try {
    $conn->begin_transaction();

    $jobStmt = $conn->prepare('SELECT job_title FROM job WHERE id = ? FOR UPDATE');
    $jobStmt->bind_param('i', $id);
    $jobStmt->execute();
    $job = $jobStmt->get_result()->fetch_assoc();
    $jobStmt->close();

    if (!$job) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'The teaching load no longer exists.']);
        $conn->close();
        exit;
    }

    $assignmentStmt = $conn->prepare('SELECT COUNT(*) AS total FROM teaching_assignments WHERE job_id = ?');
    $assignmentStmt->bind_param('i', $id);
    $assignmentStmt->execute();
    $assignmentCount = (int)($assignmentStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $assignmentStmt->close();

    $finalizedStmt = $conn->prepare("SELECT COUNT(*) AS total
        FROM job_applicants
        WHERE job_id = ?
          AND (workflow_stage IN ('initially_hired', 'permanently_hired', 'passed', 'hired')
               OR status IN ('Initially Hired', 'Permanently Hired', 'Passed', 'Application Passed', 'Hired'))");
    $finalizedStmt->bind_param('i', $id);
    $finalizedStmt->execute();
    $finalizedCount = (int)($finalizedStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $finalizedStmt->close();

    if ($assignmentCount > 0 || $finalizedCount > 0) {
        $conn->rollback();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'This teaching load has a finalized hire or teaching assignment and cannot be deleted. Close it instead to preserve its history.',
        ]);
        $conn->close();
        exit;
    }

    $applicationsStmt = $conn->prepare('SELECT id, user_id, applicant_email, full_name FROM job_applicants WHERE job_id = ? FOR UPDATE');
    $applicationsStmt->bind_param('i', $id);
    $applicationsStmt->execute();
    $applicationsResult = $applicationsStmt->get_result();
    $applications = [];
    while ($application = $applicationsResult->fetch_assoc()) {
        $applications[] = $application;
    }
    $applicationsStmt->close();
    $applicationCount = count($applications);

    if ($applicationCount > 0) {
        $notificationStmt = $conn->prepare("INSERT INTO notifications
            (user_id, user_email, user_name, title, message, type, is_read, created_at)
            VALUES (?, ?, ?, 'Teaching load cancelled', ?, 'warning', 0, NOW())");
        $notificationMessage = 'The teaching load "' . (string)$job['job_title'] . '" was removed. Your application was cancelled and moved to Archive.';
        foreach ($applications as $application) {
            $userId = !empty($application['user_id']) ? (int)$application['user_id'] : null;
            $email = (string)($application['applicant_email'] ?? '');
            $name = (string)($application['full_name'] ?? 'Applicant');
            $notificationStmt->bind_param('isss', $userId, $email, $name, $notificationMessage);
            $notificationStmt->execute();
        }
        $notificationStmt->close();

        $submissionStmt = $conn->prepare('DELETE submissions
            FROM applicant_requirement_submissions submissions
            INNER JOIN teaching_load_requirements requirements ON requirements.id = submissions.requirement_id
            WHERE requirements.teaching_load_id = ?');
        $submissionStmt->bind_param('i', $id);
        $submissionStmt->execute();
        $submissionStmt->close();

        $cancelReason = 'Teaching load deleted by ' . $adminName . '.';
        $cancelStmt = $conn->prepare("UPDATE job_applicants
            SET status = 'Cancelled',
                workflow_stage = 'cancelled',
                rejection_reason = CASE
                    WHEN rejection_reason IS NULL OR TRIM(rejection_reason) = '' THEN ?
                    ELSE rejection_reason
                END,
                rejected_date = COALESCE(rejected_date, CURDATE()),
                job_id = NULL
            WHERE job_id = ?");
        $cancelStmt->bind_param('si', $cancelReason, $id);
        $cancelStmt->execute();
        $cancelStmt->close();
    }

    $requirementsStmt = $conn->prepare('DELETE FROM teaching_load_requirements WHERE teaching_load_id = ?');
    $requirementsStmt->bind_param('i', $id);
    $requirementsStmt->execute();
    $requirementsStmt->close();

    $deleteStmt = $conn->prepare('DELETE FROM job WHERE id = ?');
    $deleteStmt->bind_param('i', $id);
    $deleteStmt->execute();
    if ($deleteStmt->affected_rows !== 1) {
        throw new RuntimeException('Teaching load deletion did not affect the expected record.');
    }
    $deleteStmt->close();

    $activityStmt = $conn->prepare('INSERT INTO admin_activity
        (activity_type, description, user_name, related_table, related_id, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())');
    $activityType = 'job_deleted';
    $description = $adminName . ' deleted teaching load: ' . (string)$job['job_title'];
    if ($applicationCount > 0) {
        $description .= ' and cancelled ' . $applicationCount . ' related application' . ($applicationCount === 1 ? '' : 's');
    }
    $relatedTable = 'job';
    $activityStmt->bind_param('ssssi', $activityType, $description, $adminName, $relatedTable, $id);
    $activityStmt->execute();
    $activityStmt->close();

    $conn->commit();

    $message = 'Teaching load deleted successfully.';
    if ($applicationCount > 0) {
        $message = 'Teaching load deleted. ' . $applicationCount . ' related application' . ($applicationCount === 1 ? ' was' : 's were') . ' cancelled and moved to Archive.';
    }
    echo json_encode([
        'success' => true,
        'message' => $message,
        'cancelled_applications' => $applicationCount,
    ]);
} catch (Throwable $error) {
    if (isset($conn)) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // Preserve the original deletion failure for the server log.
        }
    }
    error_log('Teaching load deletion failed for ID ' . $id . ': ' . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'The teaching load could not be deleted. Please try again or close it instead.',
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
