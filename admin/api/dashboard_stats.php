<?php
session_start();
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get admin info from session
$admin_role = $_SESSION['admin_role'] ?? 'Admin';
$admin_department = $_SESSION['admin_department'] ?? '';

// Initialize stats array
$stats = [];

// Determine who should see stats and if filtering is needed
$show_applicant_stats = false;
$department_filter = "";
$department_params = [];

// Admin and Secretary see all departments
if ($admin_role === 'Admin' || $admin_role === 'Secretary') {
    $show_applicant_stats = true;
    // No department filter - they see everything
}
// Department Head, HR, Recruiter see only their department
else if (($admin_role === 'Department Head' || $admin_role === 'HR Manager' || $admin_role === 'Recruiter') && !empty($admin_department)) {
    $show_applicant_stats = true;
    $department_filter = " AND assigned_to_department = ?";
    $department_params[] = $admin_department;
}

// 1. Pending Secretary Review (applications in secretary_review stage)
// Only count non-rejected applications that are actually in secretary queue
if ($show_applicant_stats) {
    if (!empty($department_params)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE workflow_stage = 'secretary_review' AND status != 'Rejected'" . $department_filter);
        $stmt->bind_param("s", ...$department_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['secretary_pending'] = $result->fetch_assoc()['count'];
    } else {
        $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE workflow_stage = 'secretary_review' AND status != 'Rejected'");
        $stats['secretary_pending'] = $result ? $result->fetch_assoc()['count'] : 0;
    }
} else {
    $stats['secretary_pending'] = 0;
}

// 2. Pending Department Review (applications in department_head_review stage)
// Only count non-rejected applications that are actually in dean/dept head queue
if ($show_applicant_stats) {
    if (!empty($department_params)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE workflow_stage = 'department_head_review' AND status != 'Rejected'" . $department_filter);
        $stmt->bind_param("s", ...$department_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['dept_pending'] = $result->fetch_assoc()['count'];
    } else {
        $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE workflow_stage = 'department_head_review' AND status != 'Rejected'");
        $stats['dept_pending'] = $result ? $result->fetch_assoc()['count'] : 0;
    }
} else {
    $stats['dept_pending'] = 0;
}

// 3. Total Applications
if ($show_applicant_stats) {
    if (!empty($department_params)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE status != 'Rejected'" . $department_filter);
        $stmt->bind_param("s", ...$department_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_applicants'] = $result->fetch_assoc()['count'];
    } else {
        $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE status != 'Rejected'");
        $stats['total_applicants'] = $result ? $result->fetch_assoc()['count'] : 0;
    }
} else {
    $stats['total_applicants'] = 0;
}

// 4. Total Jobs
$result = $conn->query("SELECT COUNT(*) as count FROM job");
$stats['total_jobs'] = $result ? $result->fetch_assoc()['count'] : 0;

// 5. Active Users (registered applicants who are verified)
$result = $conn->query("SELECT COUNT(*) as count FROM applicants WHERE is_verified = 1");
$stats['active_users'] = $result ? $result->fetch_assoc()['count'] : 0;

// 6. Calculate pending reviews based on role
if ($admin_role === 'Secretary') {
    $stats['pending_reviews'] = $stats['secretary_pending'];
    $stats['pending_reviews_label'] = 'Pending Review';
} else if ($admin_role === 'Department Head') {
    $stats['pending_reviews'] = $stats['dept_pending'];
    $stats['pending_reviews_label'] = 'Dept. Pending';
} else {
    $stats['pending_reviews'] = $stats['secretary_pending'] + $stats['dept_pending'];
    $stats['pending_reviews_label'] = 'Pending Reviews';
}

$conn->close();

echo json_encode([
    'success' => true,
    'stats' => $stats
]);
