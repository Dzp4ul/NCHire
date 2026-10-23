<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../shared/helpers/recruitment.php';
require_once __DIR__ . '/../shared/helpers/dynamic_application_requirements.php';
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = getenv('NCHIRE_DB_NAME') ?: "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if a specific job ID is requested
$jobId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($jobId) {
    // Fetch single job with all fields
    $sql = "SELECT j.*,
                   (SELECT COUNT(*) FROM job_applicants ja WHERE ja.job_id = j.id) AS applicant_count,
                   (SELECT COUNT(*) FROM teaching_assignments ta WHERE ta.job_id = j.id) AS assignment_count,
                   (SELECT COUNT(*) FROM job_applicants finalized
                    WHERE finalized.job_id = j.id
                      AND (finalized.workflow_stage IN ('initially_hired', 'permanently_hired', 'passed', 'hired')
                           OR finalized.status IN ('Initially Hired', 'Permanently Hired', 'Passed', 'Application Passed', 'Hired'))) AS finalized_application_count
            FROM job j WHERE j.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $jobId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $job = $result->fetch_assoc();
        $job['remaining_vacancies'] = nc_remaining_vacancies($conn, $job);
        $job['display_status'] = ($job['status'] === 'Active' && $job['remaining_vacancies'] > 0 && (!$job['application_deadline'] || $job['application_deadline'] >= date('Y-m-d'))) ? 'Active' : 'Closed';
        $job['teaching_load_title'] = nc_format_teaching_load_title($job);
        $job['academic_period_label'] = nc_format_academic_period($job);
        $job['salary_projection'] = nc_calculate_salary_projection_from_education([], $job, null, $conn);
        $job['salary_display'] = $job['salary_projection']['salary_display'];
        $job['application_requirements'] = nc_get_teaching_load_requirements($conn, (int)$job['id'], true);
        $job['applicant_count'] = (int)($job['applicant_count'] ?? 0);
        $job['assignment_count'] = (int)($job['assignment_count'] ?? 0);
        $job['finalized_application_count'] = (int)($job['finalized_application_count'] ?? 0);
        echo json_encode($job);
    } else {
        echo json_encode(["error" => "Job not found"]);
    }
    $stmt->close();
} else {
    // Fetch all jobs with all fields
    $sql = "SELECT j.*,
                   (SELECT COUNT(*) FROM job_applicants ja WHERE ja.job_id = j.id) AS applicant_count,
                   (SELECT COUNT(*) FROM teaching_assignments ta WHERE ta.job_id = j.id) AS assignment_count,
                   (SELECT COUNT(*) FROM job_applicants finalized
                    WHERE finalized.job_id = j.id
                      AND (finalized.workflow_stage IN ('initially_hired', 'permanently_hired', 'passed', 'hired')
                           OR finalized.status IN ('Initially Hired', 'Permanently Hired', 'Passed', 'Application Passed', 'Hired'))) AS finalized_application_count
            FROM job j ORDER BY j.id DESC";
    $result = $conn->query($sql);

    $jobs = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $row['remaining_vacancies'] = nc_remaining_vacancies($conn, $row);
            $row['display_status'] = ($row['status'] === 'Active' && $row['remaining_vacancies'] > 0 && (!$row['application_deadline'] || $row['application_deadline'] >= date('Y-m-d'))) ? 'Active' : 'Closed';
            $row['teaching_load_title'] = nc_format_teaching_load_title($row);
            $row['academic_period_label'] = nc_format_academic_period($row);
            $row['salary_projection'] = nc_calculate_salary_projection_from_education([], $row, null, $conn);
            $row['salary_display'] = $row['salary_projection']['salary_display'];
            $row['application_requirements'] = nc_get_teaching_load_requirements($conn, (int)$row['id'], true);
            $row['applicant_count'] = (int)($row['applicant_count'] ?? 0);
            $row['assignment_count'] = (int)($row['assignment_count'] ?? 0);
            $row['finalized_application_count'] = (int)($row['finalized_application_count'] ?? 0);
            $jobs[] = $row;
        }
    }
    echo json_encode($jobs);
}

$conn->close();
?>
