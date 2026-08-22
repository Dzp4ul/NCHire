<?php
session_start();
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Get admin info from session
$admin_role = $_SESSION['admin_role'] ?? 'Admin';
$admin_department = $_SESSION['admin_department'] ?? '';

// Get dashboard statistics
$stats = [];

// Admin role should NOT see application statistics
$show_applicant_stats = false;
$department_filter = "";
$department_params = [];

if (($admin_role === 'Department Head' || $admin_role === 'HR Manager' || $admin_role === 'Recruiter') && !empty($admin_department)) {
    $show_applicant_stats = true;
    $department_filter = " AND assigned_to_department = ?";
    $department_params[] = $admin_department;
}

// Total Applications (excluding rejected/archived)
if ($show_applicant_stats && !empty($department_params)) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE status != 'Rejected'" . $department_filter);
    $stmt->bind_param("s", ...$department_params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_applicants'] = $result->fetch_assoc()['count'];
} else {
    $stats['total_applicants'] = 0;
}

// Demo Scheduled
if ($show_applicant_stats && !empty($department_params)) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE status = 'Demo Scheduled'" . $department_filter);
    $stmt->bind_param("s", ...$department_params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['demo_scheduled'] = $result->fetch_assoc()['count'];
} else {
    $stats['demo_scheduled'] = 0;
}

// Interview Scheduled
if ($show_applicant_stats && !empty($department_params)) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE status = 'Interview Scheduled'" . $department_filter);
    $stmt->bind_param("s", ...$department_params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['interview_scheduled'] = $result->fetch_assoc()['count'];
} else {
    $stats['interview_scheduled'] = 0;
}

// Passed (includes current Passed statuses and legacy hired statuses)
if ($show_applicant_stats && !empty($department_params)) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE status IN ('Passed', 'Application Passed', 'Initially Hired', 'Permanently Hired', 'Hired')" . $department_filter);
    $stmt->bind_param("s", ...$department_params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['hired'] = $result->fetch_assoc()['count'];
} else {
    $stats['hired'] = 0;
}

$conn->close();

echo json_encode([
    'success' => true,
    'stats' => $stats
]);
?>
