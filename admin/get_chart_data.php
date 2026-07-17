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
$admin_role = $_SESSION['admin_role'] ?? 'SuperAdmin';
$admin_department = $_SESSION['admin_department'] ?? '';

// Get filters from URL parameters
$school_year = isset($_GET['school_year']) ? $_GET['school_year'] : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : '';

// Department filter
$department_filter = "";
$department_params = [];

if ($admin_role === 'Dean' && !empty($admin_department)) {
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

// Get chart data
$chart_data = [];
$chart_labels = [];

// Determine date range based on filters
if (!empty($school_year) && !empty($semester)) {
    // Show weekly data for the entire semester
    $current_date = new DateTime($date_start);
    $end_date = new DateTime($date_end);
    
    // Calculate weeks in the semester
    while ($current_date <= $end_date) {
        $week_start = $current_date->format('Y-m-d');
        $week_end = (clone $current_date)->modify('+6 days')->format('Y-m-d');
        
        // Don't exceed semester end date
        if ($week_end > $date_end) {
            $week_end = $date_end;
        }
        
        // Count applications for this week
        if (!empty($department_params)) {
            $query = "SELECT COUNT(*) as count FROM job_applicants WHERE applied_date >= ? AND applied_date <= ? AND applied_date <= ?" . $department_filter;
            $params = array_merge([$week_start, $week_end . ' 23:59:59', $date_end], $department_params);
            $types = str_repeat('s', count($params));
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->fetch_assoc()['count'];
            $chart_data[] = $count;
            $stmt->close();
        } else {
            $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE applied_date >= '$week_start' AND applied_date <= '$week_end 23:59:59' AND applied_date <= '$date_end'");
            $chart_data[] = $result ? $result->fetch_assoc()['count'] : 0;
        }
        
        // Label format: "Jun 1-7" or "Jun 1"
        $label_start = $current_date->format('M j');
        $label_end_date = (clone $current_date)->modify('+6 days');
        if ($label_end_date->format('Y-m-d') > $date_end) {
            $label_end_date = new DateTime($date_end);
        }
        
        // Only show end date if it's in a different day
        if ($current_date->format('j') != $label_end_date->format('j')) {
            $chart_labels[] = $label_start . '-' . $label_end_date->format('j');
        } else {
            $chart_labels[] = $label_start;
        }
        
        $current_date->modify('+7 days');
        
        // Safety limit: max 30 data points
        if (count($chart_data) >= 30) break;
    }
} else {
    // Default: Last 7 days
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        if (!empty($department_params)) {
            $query = "SELECT COUNT(*) as count FROM job_applicants WHERE DATE(applied_date) = ?" . $department_filter;
            $params = array_merge([$date], $department_params);
            $types = str_repeat('s', count($params));
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $chart_data[] = $result->fetch_assoc()['count'];
            $stmt->close();
        } else {
            $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE DATE(applied_date) = '$date'");
            $chart_data[] = $result ? $result->fetch_assoc()['count'] : 0;
        }
        $chart_labels[] = date('M j', strtotime($date));
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'chart_data' => $chart_data,
    'chart_labels' => $chart_labels
]);

$conn->close();
