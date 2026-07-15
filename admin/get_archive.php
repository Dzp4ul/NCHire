<?php
session_start();
header('Content-Type: application/json');

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get admin role and department from session
$admin_role = $_SESSION['admin_role'] ?? 'Admin';
$admin_department = $_SESSION['admin_department'] ?? '';

// Admin role should NOT see archived applications - return empty array
if ($admin_role === 'Admin') {
    echo json_encode([]);
    $conn->close();
    exit();
}

// Secretary, Department Heads, HR Managers, and Recruiters can see archived applications
// Secretary sees all rejected and cancelled, others filtered by department
if ($admin_role === 'Secretary') {
    $query = "SELECT 
                ja.id,
                ja.full_name,
                ja.applicant_email,
                ja.position,
                ja.applied_date,
                ja.rejected_date,
                ja.rejection_reason,
                ja.status,
                ja.workflow_stage,
                ja.assigned_to_department,
                a.profile_picture
              FROM job_applicants ja
              LEFT JOIN applicants a ON ja.user_id = a.id
              WHERE ja.workflow_stage IN ('rejected', 'cancelled')
              ORDER BY ja.rejected_date DESC";
    
    $result = $conn->query($query);
    
    $archived = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $archived[] = $row;
        }
    }
    
    echo json_encode($archived);
} elseif (($admin_role === 'Department Head' || $admin_role === 'HR Manager' || $admin_role === 'Recruiter') && !empty($admin_department)) {
    $query = "SELECT 
                ja.id,
                ja.full_name,
                ja.applicant_email,
                ja.position,
                ja.applied_date,
                ja.rejected_date,
                ja.rejection_reason,
                ja.status,
                ja.workflow_stage,
                ja.assigned_to_department,
                a.profile_picture
              FROM job_applicants ja
              LEFT JOIN applicants a ON ja.user_id = a.id
              WHERE ja.workflow_stage IN ('rejected', 'cancelled')
              AND ja.assigned_to_department = ?
              ORDER BY ja.rejected_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $admin_department);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $archived = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $archived[] = $row;
        }
    }
    
    echo json_encode($archived);
} else {
    // No department assigned or invalid role - return empty
    echo json_encode([]);
}

$conn->close();
?>
