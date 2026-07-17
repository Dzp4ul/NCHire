<?php
// Suppress all errors and warnings to ensure clean JSON
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any stray output
ob_start();

session_start();

// Clear output buffer and set headers
ob_clean();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

try {
    // Get admin role and department from session
    $admin_role = $_SESSION['admin_role'] ?? 'SuperAdmin';
    $admin_department = $_SESSION['admin_department'] ?? '';
    
    // Determine filtering
    $department_filter = "";
    $department_params = [];
    
    // SuperAdmin and Secretary see all departments (no filter)
    // Deans see only their department
    if ($admin_role === 'Dean' && !empty($admin_department)) {
        $department_filter = " AND assigned_to_department = ?";
        $department_params[] = $admin_department;
    }
    
    // Get dashboard statistics
    $stats = [];

    // NEW ACCURATE STATS

    // 1. Pending Secretary Review
    // Use try-catch in case workflow_stage column doesn't exist yet
    try {
        if (!empty($department_params)) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE workflow_stage = 'secretary_review' AND status != 'Rejected'" . $department_filter);
            $stmt->bind_param("s", ...$department_params);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['secretary_pending'] = $result->fetch_assoc()['count'];
            $stmt->close();
        } else {
            $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE workflow_stage = 'secretary_review' AND status != 'Rejected'");
            $stats['secretary_pending'] = $result ? $result->fetch_assoc()['count'] : 0;
        }
    } catch (Exception $e) {
        // If column doesn't exist, default to 0
        $stats['secretary_pending'] = 0;
    }

    // 2. Pending Department Review
    try {
        if (!empty($department_params)) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE workflow_stage = 'department_head_review' AND status != 'Rejected'" . $department_filter);
            $stmt->bind_param("s", ...$department_params);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['dept_pending'] = $result->fetch_assoc()['count'];
            $stmt->close();
        } else {
            $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE workflow_stage = 'department_head_review' AND status != 'Rejected'");
            $stats['dept_pending'] = $result ? $result->fetch_assoc()['count'] : 0;
        }
    } catch (Exception $e) {
        // If column doesn't exist, default to 0
        $stats['dept_pending'] = 0;
    }

    // 3. Interviews This Week
    $today = date('Y-m-d');
    $next_week = date('Y-m-d', strtotime('+7 days'));
    if (!empty($department_params)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE (status = 'Interview Scheduled' OR workflow_stage = 'interview_scheduled') AND interview_date >= ? AND interview_date <= ?" . $department_filter);
        $stmt->bind_param("sss", $today, $next_week, ...$department_params);
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
    if (!empty($department_params)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE status IN ('Initially Hired', 'Permanently Hired', 'Hired')" . $department_filter);
        $stmt->bind_param("s", ...$department_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['overall_hired'] = $result->fetch_assoc()['count'];
        $stmt->close();
    } else {
        $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE status IN ('Initially Hired', 'Permanently Hired', 'Hired')");
        $stats['overall_hired'] = $result ? $result->fetch_assoc()['count'] : 0;
    }

    // Keep old stats for backward compatibility
    // Total Applications
    if (!empty($department_params)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE status != 'Rejected'" . $department_filter);
        $stmt->bind_param("s", ...$department_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_applicants'] = $result->fetch_assoc()['count'];
        $stmt->close();
    } else {
        $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE status != 'Rejected'");
        $stats['total_applicants'] = $result ? $result->fetch_assoc()['count'] : 0;
    }

    // Interview Scheduled
    if (!empty($department_params)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE status = 'Interview Scheduled'" . $department_filter);
        $stmt->bind_param("s", ...$department_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['interview_scheduled'] = $result->fetch_assoc()['count'];
        $stmt->close();
    } else {
        $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE status = 'Interview Scheduled'");
        $stats['interview_scheduled'] = $result ? $result->fetch_assoc()['count'] : 0;
    }

    // Demo Scheduled
    if (!empty($department_params)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE status = 'Demo Scheduled'" . $department_filter);
        $stmt->bind_param("s", ...$department_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['demo_scheduled'] = $result->fetch_assoc()['count'];
        $stmt->close();
    } else {
        $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE status = 'Demo Scheduled'");
        $stats['demo_scheduled'] = $result ? $result->fetch_assoc()['count'] : 0;
    }

    // Hired (includes Initially Hired, Permanently Hired, and Hired)
    if (!empty($department_params)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applicants WHERE status IN ('Initially Hired', 'Permanently Hired', 'Hired')" . $department_filter);
        $stmt->bind_param("s", ...$department_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['hired'] = $result->fetch_assoc()['count'];
        $stmt->close();
    } else {
        $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE status IN ('Initially Hired', 'Permanently Hired', 'Hired')");
        $stats['hired'] = $result ? $result->fetch_assoc()['count'] : 0;
    }

    // Get recent applications (last 5) with role-based filtering
    $recent_stmt = null;
    if (!empty($department_params)) {
        $recent_stmt = $conn->prepare("SELECT * FROM job_applicants WHERE 1=1" . $department_filter . " ORDER BY applied_date DESC LIMIT 5");
        $recent_stmt->bind_param("s", ...$department_params);
        $recent_stmt->execute();
        $recent_applicants_result = $recent_stmt->get_result();
    } else {
        $recent_applicants_result = $conn->query("SELECT * FROM job_applicants ORDER BY applied_date DESC LIMIT 5");
    }
    
    $recent_applicants = [];
    if ($recent_applicants_result) {
        while ($row = $recent_applicants_result->fetch_assoc()) {
            $recent_applicants[] = $row;
        }
    }
    if ($recent_stmt) {
        $recent_stmt->close();
    }

    // Get recent jobs from job table with application counts
    $recent_jobs_query = "SELECT j.*, COUNT(ja.id) as application_count 
                          FROM job j 
                          LEFT JOIN job_applicants ja ON j.id = ja.job_id 
                          GROUP BY j.id 
                          ORDER BY j.id DESC 
                          LIMIT 5";
    $recent_jobs_result = $conn->query($recent_jobs_query);
    $recent_jobs = [];
    if ($recent_jobs_result) {
        while ($row = $recent_jobs_result->fetch_assoc()) {
            $recent_jobs[] = $row;
        }
    }

    // Get recent admin activity only - no job applications or login activities
    // Filter out NULL, empty, 'application', and 'admin_login' types at database level
    $recent_activity_query = "
        SELECT activity_type, description, user_name, created_at 
        FROM admin_activity 
        WHERE activity_type IS NOT NULL 
        AND activity_type != '' 
        AND activity_type != 'application'
        AND activity_type != 'admin_login'
        ORDER BY created_at DESC 
        LIMIT 10";
    
    $recent_activity_result = $conn->query($recent_activity_query);
    $recent_activity = [];
    if ($recent_activity_result) {
        while ($row = $recent_activity_result->fetch_assoc()) {
            $recent_activity[] = $row;
        }
    }

    // Return JSON response
    $response = json_encode([
        'success' => true,
        'stats' => $stats,
        'recent_applicants' => $recent_applicants,
        'recent_jobs' => $recent_jobs,
        'recent_activity' => $recent_activity,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    // Clear any output buffer and send clean JSON
    ob_clean();
    echo $response;
    ob_end_flush();

} catch (Exception $e) {
    // Clear buffer and send error response
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    ob_end_flush();
}

$conn->close();
exit();
?>
