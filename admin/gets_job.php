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
    $sql = "SELECT * 
            FROM job WHERE id = ?";
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
        echo json_encode($job);
    } else {
        echo json_encode(["error" => "Job not found"]);
    }
    $stmt->close();
} else {
    // Fetch all jobs with all fields
    $sql = "SELECT * FROM job ORDER BY id DESC";
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
            $jobs[] = $row;
        }
    }
    echo json_encode($jobs);
}

$conn->close();
?>
