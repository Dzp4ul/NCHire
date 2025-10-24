<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

try {
    // Get dashboard statistics
    $stats = [];

    // Total Applications
    $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants");
    $stats['total_applicants'] = $result ? $result->fetch_assoc()['count'] : 0;

    // Total Jobs from job table
    $result = $conn->query("SELECT COUNT(*) as count FROM job");
    $stats['total_jobs'] = $result ? $result->fetch_assoc()['count'] : 0;

    // Active Users (unique applicants)
    $result = $conn->query("SELECT COUNT(DISTINCT full_name) as count FROM job_applicants");
    $stats['active_users'] = $result ? $result->fetch_assoc()['count'] : 0;

    // Pending Reviews
    $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE status = 'Pending'");
    $stats['pending_reviews'] = $result ? $result->fetch_assoc()['count'] : 0;

    // Get recent applications (last 5)
    $recent_applicants_query = "SELECT * FROM job_applicants ORDER BY applied_date DESC LIMIT 5";
    $recent_applicants_result = $conn->query($recent_applicants_query);
    $recent_applicants = [];
    if ($recent_applicants_result) {
        while ($row = $recent_applicants_result->fetch_assoc()) {
            $recent_applicants[] = $row;
        }
    }

    // Get recent jobs from job table with application counts
    $recent_jobs_query = "SELECT j.*, COUNT(ja.id) as application_count 
                          FROM job j 
                          LEFT JOIN job_applicants ja ON j.job_title = ja.position 
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

    // Get comprehensive recent activity - only show applications from last 2 hours
    $recent_activity_query = "
        (SELECT 'application' as activity_type, 
                CONCAT(full_name, ' applied for ', position) as description,
                full_name as user_name,
                applied_date as created_at
         FROM job_applicants 
         WHERE applied_date >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
         ORDER BY applied_date DESC LIMIT 5)
        UNION ALL
        (SELECT activity_type, description, user_name, created_at 
         FROM admin_activity 
         ORDER BY created_at DESC LIMIT 10)
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
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'recent_applicants' => $recent_applicants,
        'recent_jobs' => $recent_jobs,
        'recent_activity' => $recent_activity,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
