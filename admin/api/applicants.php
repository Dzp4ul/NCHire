<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

function loadApplicants() {
    global $conn;
    
    // Get admin role and department from session
    $admin_role = $_SESSION['admin_role'] ?? '';
    $admin_department = $_SESSION['admin_department'] ?? '';
    
    // Build query based on role and workflow stage
    $where_conditions = [];
    $params = [];
    $types = '';
    
    // Secretary: Only see applications in secretary_review stage (excluding rejected)
    if ($admin_role === 'Secretary') {
        $where_conditions[] = "workflow_stage = ?";
        $where_conditions[] = "status != ?";
        $params[] = 'secretary_review';
        $params[] = 'Rejected';
        $types .= 'ss';
    }
    // Department Head: Only see applications transferred to them (department_head_review and beyond)
    elseif ($admin_role === 'Department Head') {
        $where_conditions[] = "workflow_stage IN ('department_head_review', 'interview_scheduled', 'interview_completed', 
                                                     'demo_scheduled', 'demo_completed', 'psych_scheduled', 'psych_completed',
                                                     'initially_hired', 'permanently_hired', 'hired')";
        if (!empty($admin_department)) {
            $where_conditions[] = "assigned_to_department = ?";
            $params[] = $admin_department;
            $types .= 's';
        }
    }
    // HR Manager and Recruiter: See all applications in their department
    elseif ($admin_role === 'HR Manager' || $admin_role === 'Recruiter') {
        if (!empty($admin_department)) {
            $where_conditions[] = "assigned_to_department = ?";
            $params[] = $admin_department;
            $types .= 's';
        }
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $sql = "SELECT 
        id,
        full_name as name,
        applicant_email as email,
        contact_num as phone,
        position,
        applied_date as appliedDate,
        status,
        workflow_stage,
        assigned_to_department,
        '' as experience,
        '' as education
    FROM job_applicants 
    $where_clause
    ORDER BY applied_date DESC";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    if ($result) {
        $applicants = [];
        while ($row = $result->fetch_assoc()) {
            $applicants[] = $row;
        }
        return $applicants;
    }
    return [];
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        echo json_encode(loadApplicants());
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $conn->prepare("INSERT INTO job_applicants 
            (full_name, applicant_email, contact_num, position, applied_date, status) 
            VALUES (?, ?, ?, ?, NOW(), 'Application Received')");
        $stmt->bind_param("ssss", 
            $input['name'], 
            $input['email'], 
            $input['phone'], 
            $input['position']
        );
        
        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            echo json_encode(['success' => true, 'applicant' => [
                'id' => $newId,
                'name' => $input['name'],
                'email' => $input['email'],
                'phone' => $input['phone'],
                'position' => $input['position'],
                'appliedDate' => date('Y-m-d'),
                'status' => 'Application Received'
            ]]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        $stmt->close();
        break;
        
    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $conn->prepare("UPDATE job_applicants SET 
            full_name = ?, 
            applicant_email = ?, 
            contact_num = ?, 
            position = ?, 
            status = ? 
            WHERE id = ?");
        $stmt->bind_param("sssssi", 
            $input['name'], 
            $input['email'], 
            $input['phone'], 
            $input['position'], 
            $input['status'],
            $input['id']
        );
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        $stmt->close();
        break;
        
    case 'DELETE':
        $id = $_GET['id'];
        
        $stmt = $conn->prepare("DELETE FROM job_applicants WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        $stmt->close();
        break;
}

$conn->close();
?>