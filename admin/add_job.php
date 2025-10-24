<?php
header('Content-Type: application/json');
// Prevent PHP notices/warnings from corrupting JSON output
ini_set('display_errors', 0);

$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    echo json_encode(["success" => false, "message" => "No input data"]);
    exit;
}

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

try {
    // Use prepared statements with new fields
    $sql = "INSERT INTO job (job_title, department_role, job_type, locations, salary_range, application_deadline, job_description, job_requirements, education, experience, training, eligibility, competency) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        $conn->close();
        exit;
    }
    $stmt->bind_param("sssssssssssss", $title, $department, $type, $location, $salary, $deadline, $description, $requirements, $education, $experience, $training, $eligibility, $competency);
    $ok = $stmt->execute();
    if ($ok) {
        // Log the activity
        $activity_sql = "INSERT INTO admin_activity (activity_type, description, created_at) VALUES (?, ?, NOW())";
        $astmt = $conn->prepare($activity_sql);
        if ($astmt) {
            $activity_type = "job_created";
            $desc = "Created job posting: " . $title;
            $astmt->bind_param("ss", $activity_type, $desc);
            $astmt->execute();
            $astmt->close();
        }
        echo json_encode(["success" => true, "message" => "Job added successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Insert failed: " . $stmt->error]);
    }
    $stmt->close();
} catch (Throwable $e) {
    // Log the error server-side and return a clean JSON error
    error_log('add_job.php error: ' . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Server error while adding job."]);
}

$conn->close();
?>
