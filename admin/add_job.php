<?php
session_start();
header('Content-Type: application/json');
// Prevent PHP notices/warnings from corrupting JSON output
ini_set('display_errors', 0);
require_once __DIR__ . '/../shared/helpers/recruitment.php';

$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]);
    exit;
}

// Get admin info from session
$admin_name = $_SESSION['admin_name'] ?? 'Unknown Admin';

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
$subject = $data["subject"] ?? '';
$description = $data["job_description"] ?? '';
$requirements = $data["job_requirements"] ?? '';

// New fields from the enhanced form
$education = $data["education"] ?? '';
$experience = $data["experience"] ?? '';
$training = $data["training"] ?? '';
$eligibility = $data["eligibility"] ?? '';
$competency = $data["competency"] ?? '';

$subject_code = trim($data["subject_code"] ?? "");
$subject_name = trim($data["subject_name"] ?? "") ?: $subject;
$program = trim($data["program"] ?? "") ?: $department;
$academic_year = trim($data["academic_year"] ?? "") ?: nc_current_academic_year();
$semester = nc_normalize_semester($data["semester"] ?? "");
$teaching_schedule = trim($data["teaching_schedule"] ?? "");
$teaching_hours = (isset($data["teaching_hours_per_week"]) && $data["teaching_hours_per_week"] !== "") ? (float)$data["teaching_hours_per_week"] : null;
$load_units = (isset($data["load_units"]) && $data["load_units"] !== "") ? (float)$data["load_units"] : null;
$required_instructors = max(1, (int)($data["required_instructors"] ?? 1));
$salary_grade = trim($data["salary_grade"] ?? "");

if ($department === 'Computer Science') {
    $department = 'Computing Studies';
}

try {
    // Use prepared statements with new fields
    $sql = "INSERT INTO job (job_title, department_role, job_type, locations, salary_range, application_deadline, subject, job_description, job_requirements, education, experience, training, eligibility, competency) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        $conn->close();
        exit;
    }
    $stmt->bind_param("ssssssssssssss", $title, $department, $type, $location, $salary, $deadline, $subject, $description, $requirements, $education, $experience, $training, $eligibility, $competency);
    $ok = $stmt->execute();
    if ($ok) {
        $job_id = $conn->insert_id;
        if (nc_column_exists($conn, 'job', 'teaching_hours_per_week')) {
            $meta_sql = "UPDATE job SET status = 'Active', subject_code = ?, subject_name = ?, program = ?, academic_year = ?, semester = ?, teaching_schedule = ?, teaching_hours_per_week = ?, load_units = ?, required_instructors = ?, salary_grade = ? WHERE id = ?";
            $meta_stmt = $conn->prepare($meta_sql);
            if ($meta_stmt) {
                $meta_stmt->bind_param("ssssssddisi", $subject_code, $subject_name, $program, $academic_year, $semester, $teaching_schedule, $teaching_hours, $load_units, $required_instructors, $salary_grade, $job_id);
                $meta_stmt->execute();
                $meta_stmt->close();
            }
        }
        // Log the activity with admin name
        $activity_sql = "INSERT INTO admin_activity (activity_type, description, user_name, related_table, related_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $astmt = $conn->prepare($activity_sql);
        if ($astmt) {
            $activity_type = "job_created";
            $desc = "$admin_name created teaching load: $title";
            $related_table = "job";
            $astmt->bind_param("ssssi", $activity_type, $desc, $admin_name, $related_table, $job_id);
            $astmt->execute();
            $astmt->close();
        }
        echo json_encode(["success" => true, "message" => "Teaching load added successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Insert failed: " . $stmt->error]);
    }
    $stmt->close();
} catch (Throwable $e) {
    // Log the error server-side and return a clean JSON error
    error_log('add_job.php error: ' . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Server error while adding teaching load."]);
}

$conn->close();
?>
