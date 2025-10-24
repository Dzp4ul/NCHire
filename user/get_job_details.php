<?php
session_start();
header('Content-Type: application/json');

// Database connection
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

$job_id = $_GET['job_id'] ?? null;

if (!$job_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Job ID is required']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM job WHERE id = ?");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Job not found']);
        exit;
    }
    
    $job = $result->fetch_assoc();
    
    // Check if current user has already applied to this job
    $application_id = null;
    $application_status = null;
    
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $app_stmt = $conn->prepare("SELECT id, status FROM job_applicants WHERE job_id = ? AND user_id = ? LIMIT 1");
        $app_stmt->bind_param("ii", $job_id, $user_id);
        $app_stmt->execute();
        $app_result = $app_stmt->get_result();
        
        if ($app_result->num_rows > 0) {
            $application = $app_result->fetch_assoc();
            $application_id = $application['id'];
            $application_status = $application['status'];
        }
        $app_stmt->close();
    }
    
    // Format salary
    $salary_parts = explode(' - ', $job['salary_range']);
    $formatted_salary = '₱' . $salary_parts[0] . ' - ₱' . (isset($salary_parts[1]) ? $salary_parts[1] : $salary_parts[0]);
    
    $response = [
        'success' => true,
        'job' => [
            'id' => $job['id'],
            'job_title' => $job['job_title'],
            'department_role' => $job['department_role'],
            'job_type' => $job['job_type'],
            'locations' => $job['locations'],
            'salary_range' => $formatted_salary,
            'application_deadline' => $job['application_deadline'],
            'job_description' => $job['job_description'],
            'job_requirements' => $job['job_requirements'],
            'education' => $job['education'],
            'experience' => $job['experience'],
            'training' => $job['training'],
            'eligibility' => $job['eligibility'],
            'competency' => $job['competency'],
            'application_id' => $application_id,
            'application_status' => $application_status
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>
