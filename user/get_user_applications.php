<?php
session_start();
header('Content-Type: application/json');

$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['email'] ?? ($_SESSION['applicant_email'] ?? null);

if (!$user_id && !$user_email) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated', 'code' => 'NO_SESSION']);
    exit;
}

try {
    if ($user_id) {
        $stmt = $conn->prepare("SELECT id, position, applied_date, status, job_id, interview_date, interview_notes, resubmission_documents, resubmission_notes, rejection_reason, application_letter, resume, tor, diploma, professional_license, coe, seminars_trainings, masteral_cert FROM job_applicants WHERE user_id = ? ORDER BY applied_date DESC, id DESC");
        $stmt->bind_param("i", $user_id);
    } else {
        $stmt = $conn->prepare("SELECT id, position, applied_date, status, job_id, interview_date, interview_notes, resubmission_documents, resubmission_notes, rejection_reason, applicant_email, application_letter, resume, tor, diploma, professional_license, coe, seminars_trainings, masteral_cert FROM job_applicants WHERE applicant_email = ? ORDER BY applied_date DESC, id DESC");
        $stmt->bind_param("s", $user_email);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $applications = [];

    while ($row = $result->fetch_assoc()) {
        $applications[] = [
            'id' => (int)$row['id'],
            'position' => $row['position'] ?? 'Unknown Position',
            'job_id' => $row['job_id'] ?? null,
            'applied_date' => $row['applied_date'],
            'applied_date_pretty' => $row['applied_date'] ? date('M d, Y', strtotime($row['applied_date'])) : null,
            'status' => $row['status'] ?? 'Pending',
            'interview_date' => $row['interview_date'] ?? null,
            'interview_date_pretty' => !empty($row['interview_date']) ? date('M d, Y g:i A', strtotime($row['interview_date'])) : null,
            'interview_notes' => $row['interview_notes'] ?? null,
            'resubmission_documents' => $row['resubmission_documents'] ?? null,
            'resubmission_notes' => $row['resubmission_notes'] ?? null,
            'rejection_reason' => $row['rejection_reason'] ?? null,
            'application_letter' => $row['application_letter'] ?? null,
            'resume' => $row['resume'] ?? null,
            'tor' => $row['tor'] ?? null,
            'diploma' => $row['diploma'] ?? null,
            'professional_license' => $row['professional_license'] ?? null,
            'coe' => $row['coe'] ?? null,
            'seminars_trainings' => $row['seminars_trainings'] ?? null,
            'masteral_cert' => $row['masteral_cert'] ?? null,
        ];
    }

    echo json_encode(['success' => true, 'applications' => $applications]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
