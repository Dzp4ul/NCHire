<?php
session_start();
header('Content-Type: application/json');

// Enable error logging
error_log("=== GET_USER_APPLICATIONS.PHP CALLED ===");
error_log("Session ID: " . session_id());
error_log("Session data: " . print_r($_SESSION, true));

$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Check multiple session variables for user identification
$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['email'] ?? ($_SESSION['user_email'] ?? ($_SESSION['applicant_email'] ?? null));

error_log("User ID from session: " . ($user_id ?? "NULL"));
error_log("User Email from session: " . ($user_email ?? "NULL"));

if (!$user_id && !$user_email) {
    error_log("Authentication failed - no user_id or email in session");
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated', 'code' => 'NO_SESSION', 'debug' => [
        'session_id' => session_id(),
        'has_user_id' => isset($_SESSION['user_id']),
        'has_email' => isset($_SESSION['email']),
        'has_user_email' => isset($_SESSION['user_email']),
        'has_applicant_email' => isset($_SESSION['applicant_email'])
    ]]);
    exit;
}

try {
    if ($user_id) {
        error_log("Querying by user_id: " . $user_id);
        $stmt = $conn->prepare("SELECT id, position, applied_date, status, job_id, interview_date, interview_location, interview_room, interview_notes, demo_date, demo_location, demo_room, resubmission_documents, resubmission_notes, rejection_reason, application_letter, resume, tor, diploma, professional_license, coe, seminars_trainings, masteral_cert, certificate_of_grades, proof_of_enrollment, application_type, academic_year, semester, applicable_hourly_rate, salary_projection, salary_projection_basis, letter_of_intent FROM job_applicants WHERE user_id = ? ORDER BY applied_date DESC, id DESC");
        $stmt->bind_param("i", $user_id);
    } else {
        error_log("Querying by email: " . $user_email);
        $stmt = $conn->prepare("SELECT id, position, applied_date, status, job_id, interview_date, interview_location, interview_room, interview_notes, demo_date, demo_location, demo_room, resubmission_documents, resubmission_notes, rejection_reason, applicant_email, application_letter, resume, tor, diploma, professional_license, coe, seminars_trainings, masteral_cert, certificate_of_grades, proof_of_enrollment, application_type, academic_year, semester, applicable_hourly_rate, salary_projection, salary_projection_basis, letter_of_intent FROM job_applicants WHERE applicant_email = ? ORDER BY applied_date DESC, id DESC");
        $stmt->bind_param("s", $user_email);
    }

    if (!$stmt->execute()) {
        error_log("Query execution failed: " . $stmt->error);
        throw new Exception("Query execution failed");
    }
    
    $result = $stmt->get_result();
    $applications = [];
    error_log("Query returned " . $result->num_rows . " applications");

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
            'interview_location' => $row['interview_location'] ?? null,
            'interview_room' => $row['interview_room'] ?? null,
            'interview_notes' => $row['interview_notes'] ?? null,
            'demo_date' => $row['demo_date'] ?? null,
            'demo_date_pretty' => !empty($row['demo_date']) ? date('M d, Y g:i A', strtotime($row['demo_date'])) : null,
            'demo_location' => $row['demo_location'] ?? null,
            'demo_room' => $row['demo_room'] ?? null,
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
            'letter_of_intent' => $row['letter_of_intent'] ?? null,
        ];
    }

    error_log("Returning " . count($applications) . " applications successfully");
    echo json_encode(['success' => true, 'applications' => $applications]);
} catch (Exception $e) {
    error_log("Exception in get_user_applications: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
