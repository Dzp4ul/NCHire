<?php
session_start();
header('Content-Type: application/json');

// Database connection parameters
$host = 'localhost';
$dbname = 'nchire';
$username = 'root';
$password = '12345678';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get admin role and department from session
    $admin_role = $_SESSION['admin_role'] ?? 'Admin';
    $admin_department = $_SESSION['admin_department'] ?? '';

    // Admin role should NOT see applications - return empty array
    if ($admin_role === 'Admin') {
        echo json_encode([]);
        exit();
    }

    // Build SQL based on role
    $sql = "";
    $params = [];
    
    // Secretary: See ALL applications (not just secretary_review)
    // This allows them to track progress after transfer
    if ($admin_role === 'Secretary') {
        $sql = "
            SELECT 
                ja.id, 
                ja.full_name, 
                ja.position, 
                ja.applied_date, 
                ja.status, 
                ja.applicant_email, 
                ja.contact_num,
                ja.assigned_to_department,
                ja.workflow_stage,
                ja.secretary_id,
                a.profile_picture
            FROM job_applicants ja
            LEFT JOIN applicants a ON ja.user_id = a.id
            WHERE ja.workflow_stage != 'rejected' 
            AND (ja.secretary_id IS NULL OR ja.secretary_id = 0 OR ja.workflow_stage = 'secretary_review' 
                 OR ja.secretary_id = :secretary_id)
            ORDER BY 
                CASE 
                    WHEN ja.workflow_stage = 'secretary_review' THEN 1
                    ELSE 2
                END,
                ja.applied_date DESC";
        $params['secretary_id'] = $_SESSION['admin_id'] ?? 1;
    }
    // Department Head: Only see applications transferred to them
    elseif ($admin_role === 'Department Head' && !empty($admin_department)) {
        $sql = "
            SELECT 
                ja.id, 
                ja.full_name, 
                ja.position, 
                ja.applied_date, 
                ja.status, 
                ja.applicant_email, 
                ja.contact_num,
                ja.assigned_to_department,
                ja.workflow_stage,
                a.profile_picture
            FROM job_applicants ja
            LEFT JOIN applicants a ON ja.user_id = a.id
            WHERE ja.workflow_stage IN ('department_head_review', 'interview_scheduled', 'interview_completed',
                                        'demo_scheduled', 'demo_completed', 'psych_scheduled', 'psych_completed',
                                        'initially_hired', 'permanently_hired', 'hired')
            AND ja.assigned_to_department = :department
            ORDER BY ja.applied_date DESC";
        $params['department'] = $admin_department;
    }
    // HR Manager and Recruiter: See all applications in their department (except rejected)
    elseif (($admin_role === 'HR Manager' || $admin_role === 'Recruiter') && !empty($admin_department)) {
        $sql = "
            SELECT 
                ja.id, 
                ja.full_name, 
                ja.position, 
                ja.applied_date, 
                ja.status, 
                ja.applicant_email, 
                ja.contact_num,
                ja.assigned_to_department,
                ja.workflow_stage,
                a.profile_picture
            FROM job_applicants ja
            LEFT JOIN applicants a ON ja.user_id = a.id
            WHERE ja.status != 'Rejected'
            AND ja.assigned_to_department = :department
            ORDER BY ja.applied_date DESC";
        $params['department'] = $admin_department;
    }
    
    // Execute query if SQL was built
    if (!empty($sql)) {
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();
        $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($applicants);
    } else {
        // No valid role/department - return empty
        echo json_encode([]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
