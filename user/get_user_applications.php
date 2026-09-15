<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../shared/helpers/recruitment.php';

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
        $stmt = $conn->prepare("SELECT ja.*, j.job_type AS projection_job_type, j.salary_grade AS projection_salary_grade, j.salary_range AS projection_salary_range, j.teaching_hours_per_week AS projection_teaching_hours FROM job_applicants ja LEFT JOIN job j ON j.id = ja.job_id WHERE ja.user_id = ? ORDER BY ja.applied_date DESC, ja.id DESC");
        $stmt->bind_param("i", $user_id);
    } else {
        error_log("Querying by email: " . $user_email);
        $stmt = $conn->prepare("SELECT ja.*, j.job_type AS projection_job_type, j.salary_grade AS projection_salary_grade, j.salary_range AS projection_salary_range, j.teaching_hours_per_week AS projection_teaching_hours FROM job_applicants ja LEFT JOIN job j ON j.id = ja.job_id WHERE ja.applicant_email = ? ORDER BY ja.applied_date DESC, ja.id DESC");
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
        $projectionJob = [
            'job_type' => $row['projection_job_type'] ?? '',
            'salary_grade' => $row['projection_salary_grade'] ?? '',
            'salary_range' => $row['projection_salary_range'] ?? '',
            'teaching_hours_per_week' => $row['projection_teaching_hours'] ?? null,
        ];
        $projectionUserId = (int)($row['user_id'] ?? 0);
        $projectionDetails = $projectionUserId > 0
            ? nc_calculate_salary_projection($conn, $projectionUserId, $projectionJob)
            : nc_calculate_salary_projection_from_education([], $projectionJob);
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
            'certificate_of_grades' => $row['certificate_of_grades'] ?? null,
            'proof_of_enrollment' => $row['proof_of_enrollment'] ?? null,
            'letter_of_intent' => $row['letter_of_intent'] ?? null,
            'application_type' => $row['application_type'] ?? 'new',
            'academic_year' => $row['academic_year'] ?? null,
            'semester' => $row['semester'] ?? null,
            'applicable_hourly_rate' => $row['applicable_hourly_rate'] ?? null,
            'salary_projection' => $row['salary_projection'] ?? null,
            'salary_projection_basis' => $row['salary_projection_basis'] ?? null,
            'salary_projection_details' => $projectionDetails,
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
