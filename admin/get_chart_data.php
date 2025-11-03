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
$pass = "12345678";
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
        // First Semester: June to October
        $date_start = "$start_year-06-01";
        $date_end = "$start_year-10-31 23:59:59";
    } else {
        // Second Semester: November to March
        $date_start = "$start_year-11-01";
        $date_end = "$end_year-03-31 23:59:59";
    }
    
    $date_filter = " AND applied_date >= ? AND applied_date <= ?";
    $date_params = [$date_start, $date_end];
}

// Get chart data (always weekly - last 7 days)
$chart_data = [];
$chart_labels = [];

// Last 7 days
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    if (!empty($department_params)) {
        $query = "SELECT COUNT(*) as count FROM job_applicants WHERE DATE(applied_date) = ?" . $department_filter . $date_filter;
        $params = array_merge([$date], $department_params, $date_params);
        $types = str_repeat('s', count($params));
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $chart_data[] = $result->fetch_assoc()['count'];
    } else {
        $extra_filter = "";
        if (!empty($date_filter)) {
            $extra_filter = " AND applied_date >= '{$date_params[0]}' AND applied_date <= '{$date_params[1]}'";
        }
        $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE DATE(applied_date) = '$date'" . $extra_filter);
        $chart_data[] = $result ? $result->fetch_assoc()['count'] : 0;
    }
    $chart_labels[] = date('M j', strtotime($date));
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'chart_data' => $chart_data,
    'chart_labels' => $chart_labels
]);

$conn->close();
