<?php
session_start();
header('Content-Type: application/json');

// Database connection parameters
$host = 'localhost';
$dbname = 'nchire';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get admin role and department from session
    $admin_role = $_SESSION['admin_role'] ?? 'Admin';
    $admin_department = $_SESSION['admin_department'] ?? '';
    $department_alias = $admin_department === 'Computing Studies' ? 'Computer Science' : ($admin_department === 'Computer Science' ? 'Computing Studies' : $admin_department);
    $admin_id = $_SESSION['admin_id'] ?? 1;

    $stats = [
        'total_applicants' => 0,
        'interview_scheduled' => 0,
        'demo_scheduled' => 0,
        'hired' => 0
    ];

    // Secretary: See stats for ALL applications they can view
    if ($admin_role === 'Secretary') {
        // Total Applicants (excluding rejected)
        $sql = "SELECT COUNT(*) as count 
                FROM job_applicants 
                WHERE workflow_stage != 'rejected' 
                AND (secretary_id IS NULL OR secretary_id = 0 OR workflow_stage = 'secretary_review' 
                     OR secretary_id = :secretary_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':secretary_id', $admin_id);
        $stmt->execute();
        $stats['total_applicants'] = $stmt->fetchColumn();

        // Interviews Scheduled
        $sql = "SELECT COUNT(*) as count 
                FROM job_applicants 
                WHERE status = 'Interview Scheduled'
                AND workflow_stage != 'rejected'
                AND (secretary_id IS NULL OR secretary_id = 0 OR workflow_stage = 'secretary_review' 
                     OR secretary_id = :secretary_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':secretary_id', $admin_id);
        $stmt->execute();
        $stats['interview_scheduled'] = $stmt->fetchColumn();

        // Demo Scheduled
        $sql = "SELECT COUNT(*) as count 
                FROM job_applicants 
                WHERE status = 'Demo Scheduled'
                AND workflow_stage != 'rejected'
                AND (secretary_id IS NULL OR secretary_id = 0 OR workflow_stage = 'secretary_review' 
                     OR secretary_id = :secretary_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':secretary_id', $admin_id);
        $stmt->execute();
        $stats['demo_scheduled'] = $stmt->fetchColumn();

        // Hired (Initially Hired, Permanently Hired, Hired)
        $sql = "SELECT COUNT(*) as count 
                FROM job_applicants 
                WHERE status IN ('Initially Hired', 'Permanently Hired', 'Hired')
                AND workflow_stage != 'rejected'
                AND (secretary_id IS NULL OR secretary_id = 0 OR workflow_stage = 'secretary_review' 
                     OR secretary_id = :secretary_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':secretary_id', $admin_id);
        $stmt->execute();
        $stats['hired'] = $stmt->fetchColumn();
    }
    // Department Head: Stats for their department only
    elseif ($admin_role === 'Department Head' && !empty($admin_department)) {
        // Total Applicants
        $sql = "SELECT COUNT(*) as count 
                FROM job_applicants 
                WHERE workflow_stage IN ('department_head_review', 'interview_scheduled', 'interview_completed',
                                        'demo_scheduled', 'demo_completed', 'psych_scheduled', 'psych_completed',
                                        'initially_hired', 'permanently_hired', 'hired')
                AND assigned_to_department IN (:department, :department_alias)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':department', $admin_department);
        $stmt->bindValue(':department_alias', $department_alias);
        $stmt->execute();
        $stats['total_applicants'] = $stmt->fetchColumn();

        // Interviews Scheduled
        $sql = "SELECT COUNT(*) as count 
                FROM job_applicants 
                WHERE status = 'Interview Scheduled'
                AND assigned_to_department IN (:department, :department_alias)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':department', $admin_department);
        $stmt->bindValue(':department_alias', $department_alias);
        $stmt->execute();
        $stats['interview_scheduled'] = $stmt->fetchColumn();

        // Demo Scheduled
        $sql = "SELECT COUNT(*) as count 
                FROM job_applicants 
                WHERE status = 'Demo Scheduled'
                AND assigned_to_department IN (:department, :department_alias)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':department', $admin_department);
        $stmt->bindValue(':department_alias', $department_alias);
        $stmt->execute();
        $stats['demo_scheduled'] = $stmt->fetchColumn();

        // Hired
        $sql = "SELECT COUNT(*) as count 
                FROM job_applicants 
                WHERE status IN ('Initially Hired', 'Permanently Hired', 'Hired')
                AND assigned_to_department IN (:department, :department_alias)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':department', $admin_department);
        $stmt->bindValue(':department_alias', $department_alias);
        $stmt->execute();
        $stats['hired'] = $stmt->fetchColumn();
    }
    // HR Manager and Recruiter: Stats for their department
    elseif (($admin_role === 'HR Manager' || $admin_role === 'Recruiter') && !empty($admin_department)) {
        // Total Applicants
        $sql = "SELECT COUNT(*) as count 
                FROM job_applicants 
                WHERE status != 'Rejected'
                AND assigned_to_department IN (:department, :department_alias)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':department', $admin_department);
        $stmt->bindValue(':department_alias', $department_alias);
        $stmt->execute();
        $stats['total_applicants'] = $stmt->fetchColumn();

        // Interviews Scheduled
        $sql = "SELECT COUNT(*) as count 
                FROM job_applicants 
                WHERE status = 'Interview Scheduled'
                AND assigned_to_department IN (:department, :department_alias)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':department', $admin_department);
        $stmt->bindValue(':department_alias', $department_alias);
        $stmt->execute();
        $stats['interview_scheduled'] = $stmt->fetchColumn();

        // Demo Scheduled
        $sql = "SELECT COUNT(*) as count 
                FROM job_applicants 
                WHERE status = 'Demo Scheduled'
                AND assigned_to_department IN (:department, :department_alias)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':department', $admin_department);
        $stmt->bindValue(':department_alias', $department_alias);
        $stmt->execute();
        $stats['demo_scheduled'] = $stmt->fetchColumn();

        // Hired
        $sql = "SELECT COUNT(*) as count 
                FROM job_applicants 
                WHERE status IN ('Initially Hired', 'Permanently Hired', 'Hired')
                AND assigned_to_department IN (:department, :department_alias)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':department', $admin_department);
        $stmt->bindValue(':department_alias', $department_alias);
        $stmt->execute();
        $stats['hired'] = $stmt->fetchColumn();
    }

    echo json_encode($stats);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
