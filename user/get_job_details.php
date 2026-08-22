<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../shared/helpers/recruitment.php';

$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}
$conn->set_charset('utf8mb4');

$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
if ($job_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Teaching load ID is required']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM job WHERE id = ?");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Teaching load not found']);
        exit;
    }

    $job = $result->fetch_assoc();
    $stmt->close();

    $assigned = nc_get_assigned_instructor_count($conn, $job_id);
    $job['assigned_instructors'] = $assigned;
    $remaining = nc_remaining_vacancies($conn, $job);

    $application_id = null;
    $application_status = null;
    $user_id = $_SESSION['user_id'] ?? null;

    if ($user_id) {
        $app_stmt = $conn->prepare("SELECT id, status FROM job_applicants WHERE job_id = ? AND user_id = ? AND status NOT IN ('Cancelled', 'Rejected') ORDER BY id DESC LIMIT 1");
        $app_stmt->bind_param("ii", $job_id, $user_id);
        $app_stmt->execute();
        $app_result = $app_stmt->get_result();
        if ($app_result->num_rows > 0) {
            $application = $app_result->fetch_assoc();
            $application_id = (int)$application['id'];
            $application_status = $application['status'];
        }
        $app_stmt->close();
    }

    $salaryProjection = $user_id ? nc_calculate_salary_projection($conn, (int)$user_id, $job) : null;
    $isFullTime = stripos((string)$job['job_type'], 'full') !== false;
    $salaryDisplay = $isFullTime
        ? (($job['salary_grade'] ?? '') !== '' ? 'SGD ' . $job['salary_grade'] : 'SGD pending HR configuration')
        : 'Salary projection computed from qualification and load hours';

    echo json_encode([
        'success' => true,
        'job' => [
            'id' => (int)$job['id'],
            'job_title' => $job['job_title'],
            'teaching_load_title' => nc_format_teaching_load_title($job),
            'department_role' => $job['department_role'],
            'program' => $job['program'] ?: $job['department_role'],
            'job_type' => $job['job_type'],
            'locations' => $job['locations'],
            'salary_range' => $salaryDisplay,
            'salary_display' => $salaryDisplay,
            'salary_grade' => $job['salary_grade'] ?? null,
            'application_deadline' => $job['application_deadline'],
            'subject' => $job['subject'] ?? '',
            'subject_code' => $job['subject_code'] ?? '',
            'subject_name' => $job['subject_name'] ?: ($job['subject'] ?? ''),
            'academic_year' => $job['academic_year'] ?: nc_current_academic_year(),
            'semester' => $job['semester'] ?: nc_current_semester(),
            'academic_period_label' => nc_format_academic_period($job),
            'teaching_schedule' => $job['teaching_schedule'] ?? '',
            'teaching_hours_per_week' => $job['teaching_hours_per_week'] !== null ? (float)$job['teaching_hours_per_week'] : null,
            'load_units' => $job['load_units'] !== null ? (float)$job['load_units'] : null,
            'required_instructors' => (int)($job['required_instructors'] ?? 1),
            'assigned_instructors' => $assigned,
            'remaining_vacancies' => $remaining,
            'is_available' => $remaining > 0 && $job['status'] === 'Active' && strtotime($job['application_deadline']) >= strtotime(date('Y-m-d')),
            'job_description' => $job['job_description'],
            'job_requirements' => $job['job_requirements'],
            'duties' => $job['duties'] ?? null,
            'education' => $job['education'],
            'experience' => $job['experience'],
            'training' => $job['training'],
            'eligibility' => $job['eligibility'],
            'competency' => $job['competency'],
            'application_id' => $application_id,
            'application_status' => $application_status,
            'salary_projection' => $salaryProjection,
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>