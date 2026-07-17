<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get admin info from session
$admin_role = $_SESSION['admin_role'] ?? 'Admin';
$admin_department = $_SESSION['admin_department'] ?? '';

// Get filters from URL parameters
$school_year = isset($_GET['school_year']) ? $_GET['school_year'] : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : '';

// Department filter
$department_filter = "";
$department_params = [];

if (($admin_role === 'Department Head' || $admin_role === 'HR Manager' || $admin_role === 'Recruiter') && !empty($admin_department)) {
    $department_filter = " AND assigned_to_department = ?";
    $department_params[] = $admin_department;
}

// Build date range filter for school year and semester
$date_filter = "";
$date_params = [];
if (!empty($school_year) && !empty($semester)) {
    // Parse school year (e.g., "2024-2025")
    list($start_year, $end_year) = explode('-', $school_year);
    
    if ($semester == 'first') {
        // First Semester: July 14 to December 31
        $date_start = "$start_year-07-14";
        $date_end = "$start_year-12-31 23:59:59";
    } else {
        // Second Semester: January 1 to May 31
        $date_start = "$end_year-01-01";
        $date_end = "$end_year-05-31 23:59:59";
    }
    
    $date_filter = " AND applied_date >= ? AND applied_date <= ?";
    $date_params = [$date_start, $date_end];
}

// Get dashboard statistics with filters
$stats = [];

// Build WHERE clause
$base_where = "1=1";
$all_params = [];
$param_types = "";

if (!empty($date_filter)) {
    $base_where .= $date_filter;
    $all_params = array_merge($all_params, $date_params);
    $param_types .= "ss";
}

if (!empty($department_filter)) {
    $base_where .= $department_filter;
    $all_params = array_merge($all_params, $department_params);
    $param_types .= "s";
}

// 1. Pending Secretary Review
if (!empty($all_params)) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE workflow_stage = 'secretary_review' AND $base_where");
    $stmt->bind_param($param_types, ...$all_params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['secretary_pending'] = $result->fetch_assoc()['count'];
    $stmt->close();
} else {
    $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE workflow_stage = 'secretary_review'");
    $stats['secretary_pending'] = $result ? $result->fetch_assoc()['count'] : 0;
}

// 2. Pending Department Review
if (!empty($all_params)) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE workflow_stage = 'department_head_review' AND $base_where");
    $stmt->bind_param($param_types, ...$all_params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['dept_pending'] = $result->fetch_assoc()['count'];
    $stmt->close();
} else {
    $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE workflow_stage = 'department_head_review'");
    $stats['dept_pending'] = $result ? $result->fetch_assoc()['count'] : 0;
}

// 3. Interviews This Week (with date filter if applicable)
$today = date('Y-m-d');
$next_week = date('Y-m-d', strtotime('+7 days'));

if (!empty($all_params)) {
    // Add interview date filter
    $interview_where = "(status = 'Interview Scheduled' OR workflow_stage = 'interview_scheduled') AND interview_date >= ? AND interview_date <= ? AND $base_where";
    $interview_params = array_merge([$today, $next_week], $all_params);
    $interview_types = "ss" . $param_types;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE $interview_where");
    $stmt->bind_param($interview_types, ...$interview_params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['interviews_this_week'] = $result->fetch_assoc()['count'];
    $stmt->close();
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE (status = 'Interview Scheduled' OR workflow_stage = 'interview_scheduled') AND interview_date >= ? AND interview_date <= ?");
    $stmt->bind_param("ss", $today, $next_week);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['interviews_this_week'] = $result ? $result->fetch_assoc()['count'] : 0;
    $stmt->close();
}

// 4. Overall Hired
if (!empty($all_params)) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE status IN ('Initially Hired', 'Permanently Hired', 'Hired') AND $base_where");
    $stmt->bind_param($param_types, ...$all_params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['overall_hired'] = $result->fetch_assoc()['count'];
    $stmt->close();
} else {
    $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE status IN ('Initially Hired', 'Permanently Hired', 'Hired')");
    $stats['overall_hired'] = $result ? $result->fetch_assoc()['count'] : 0;
}

// Additional stats for other sections

// Total Applications (non-rejected)
if (!empty($all_params)) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE status != 'Rejected' AND $base_where");
    $stmt->bind_param($param_types, ...$all_params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_applicants'] = $result->fetch_assoc()['count'];
    $stmt->close();
} else {
    $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE status != 'Rejected'");
    $stats['total_applicants'] = $result ? $result->fetch_assoc()['count'] : 0;
}

// Interview Scheduled
if (!empty($all_params)) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE status = 'Interview Scheduled' AND $base_where");
    $stmt->bind_param($param_types, ...$all_params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['interview_scheduled'] = $result->fetch_assoc()['count'];
    $stmt->close();
} else {
    $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE status = 'Interview Scheduled'");
    $stats['interview_scheduled'] = $result ? $result->fetch_assoc()['count'] : 0;
}

// Demo Scheduled
if (!empty($all_params)) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE status = 'Demo Scheduled' AND $base_where");
    $stmt->bind_param($param_types, ...$all_params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['demo_scheduled'] = $result->fetch_assoc()['count'];
    $stmt->close();
} else {
    $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE status = 'Demo Scheduled'");
    $stats['demo_scheduled'] = $result ? $result->fetch_assoc()['count'] : 0;
}

// Hired (same as overall_hired, kept for backward compatibility)
$stats['hired'] = $stats['overall_hired'];

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'stats' => $stats,
    'filters' => [
        'school_year' => $school_year,
        'semester' => $semester,
        'department' => $admin_department
    ]
]);

$conn->close();
