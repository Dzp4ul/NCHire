<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

function loadApplicants() {
    global $conn;
    $result = $conn->query("SELECT 
        id,
        full_name as name,
        applicant_email as email,
        contact_num as phone,
        position,
        applied_date as appliedDate,
        status,
        '' as experience,
        '' as education
    FROM job_applicants 
    ORDER BY applied_date DESC");
    
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