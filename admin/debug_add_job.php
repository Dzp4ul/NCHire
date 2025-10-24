<?php
header('Content-Type: application/json');
// Enable error display for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

echo "Debug: Starting job creation process...\n";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]);
    exit;
}

echo "Debug: Database connected successfully\n";

// Check if job table has all required columns
$result = $conn->query("DESCRIBE job");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

echo "Debug: Job table columns: " . implode(', ', $columns) . "\n";

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    // For debugging, let's also check if we have POST data
    if (!empty($_POST)) {
        $data = $_POST;
        echo "Debug: Using POST data instead of JSON\n";
    } else {
        echo json_encode(["success" => false, "message" => "No input data received"]);
        exit;
    }
}

echo "Debug: Input data received: " . print_r($data, true) . "\n";

// Safely get fields with defaults
$title = $data["job_title"] ?? '';
$department = $data["department_role"] ?? '';
$type = $data["job_type"] ?? '';
$location = $data["locations"] ?? '';
$salary = $data["salary_range"] ?? '';
$deadline = $data["application_deadline"] ?? '';
$description = $data["job_description"] ?? '';
$requirements = $data["job_requirements"] ?? '';

// New fields from the enhanced form
$education = $data["education"] ?? '';
$experience = $data["experience"] ?? '';
$training = $data["training"] ?? '';
$eligibility = $data["eligibility"] ?? '';
$competency = $data["competency"] ?? '';
$duties = $data["duties"] ?? '';

echo "Debug: Processed fields - Title: $title, Department: $department\n";

try {
    // Check if all columns exist before inserting
    $required_columns = ['job_title', 'department_role', 'job_type', 'locations', 'salary_range', 'application_deadline', 'job_description', 'job_requirements', 'education', 'experience', 'training', 'eligibility', 'competency', 'duties'];
    
    $missing_columns = array_diff($required_columns, $columns);
    if (!empty($missing_columns)) {
        echo json_encode(["success" => false, "message" => "Missing columns in job table: " . implode(', ', $missing_columns)]);
        exit;
    }
    
    echo "Debug: All required columns exist\n";
    
    // Use prepared statements with new fields
    $sql = "INSERT INTO job (job_title, department_role, job_type, locations, salary_range, application_deadline, job_description, job_requirements, education, experience, training, eligibility, competency, duties) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    echo "Debug: SQL prepared: $sql\n";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        $conn->close();
        exit;
    }
    
    echo "Debug: Statement prepared successfully\n";
    
    $stmt->bind_param("ssssssssssssss", $title, $department, $type, $location, $salary, $deadline, $description, $requirements, $education, $experience, $training, $eligibility, $competency, $duties);
    
    echo "Debug: Parameters bound successfully\n";
    
    $ok = $stmt->execute();
    
    if ($ok) {
        echo "Debug: Insert successful\n";
        
        // Log the activity
        $activity_sql = "INSERT INTO admin_activity (activity_type, description, created_at) VALUES (?, ?, NOW())";
        $astmt = $conn->prepare($activity_sql);
        if ($astmt) {
            $activity_type = "job_created";
            $desc = "Created job posting: " . $title;
            $astmt->bind_param("ss", $activity_type, $desc);
            $astmt->execute();
            $astmt->close();
            echo "Debug: Activity logged\n";
        }
        echo json_encode(["success" => true, "message" => "Job added successfully"]);
    } else {
        echo "Debug: Execute failed\n";
        echo json_encode(["success" => false, "message" => "Insert failed: " . $stmt->error]);
    }
    $stmt->close();
} catch (Throwable $e) {
    echo "Debug: Exception caught: " . $e->getMessage() . "\n";
    echo "Debug: Stack trace: " . $e->getTraceAsString() . "\n";
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage()]);
}

$conn->close();
?>
