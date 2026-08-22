<?php
session_start();
require_once __DIR__ . '/../shared/helpers/recruitment.php';
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}

// Get admin info from session
$admin_name = $_SESSION['admin_name'] ?? 'Unknown Admin';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
    echo json_encode(["success" => false, "message" => "Missing job ID"]);
    exit;
}

$id = (int)$data['id'];
$title = $conn->real_escape_string($data['job_title']);
$department = $data['department_role'] ?? '';
if ($department === 'Computer Science') {
    $department = 'Computing Studies';
}
$dept  = $conn->real_escape_string($department);
$type  = $conn->real_escape_string($data['job_type']);
$loc   = $conn->real_escape_string($data['locations']);
$salary= $conn->real_escape_string($data['salary_range']);
$deadline = $conn->real_escape_string($data['application_deadline']);
$desc  = $conn->real_escape_string($data['job_description']);
$requirements = $conn->real_escape_string($data['job_requirements']);

// Additional fields
$education = isset($data['education']) ? $conn->real_escape_string($data['education']) : '';
$experience = isset($data['experience']) ? $conn->real_escape_string($data['experience']) : '';
$training = isset($data['training']) ? $conn->real_escape_string($data['training']) : '';
$eligibility = isset($data['eligibility']) ? $conn->real_escape_string($data['eligibility']) : '';
$duties = isset($data['duties']) ? $conn->real_escape_string($data['duties']) : '';
$competency = isset($data['competency']) ? $conn->real_escape_string($data['competency']) : '';

$subject_code = trim($data['subject_code'] ?? '');
$subject_name = trim($data['subject_name'] ?? '') ?: ($data['subject'] ?? $title);
$program = trim($data['program'] ?? '') ?: $department;
$academic_year = trim($data['academic_year'] ?? '') ?: nc_current_academic_year();
$semester = nc_normalize_semester($data['semester'] ?? '');
$teaching_schedule = trim($data['teaching_schedule'] ?? '');
$teaching_hours = (isset($data['teaching_hours_per_week']) && $data['teaching_hours_per_week'] !== '') ? (float)$data['teaching_hours_per_week'] : null;
$load_units = (isset($data['load_units']) && $data['load_units'] !== '') ? (float)$data['load_units'] : null;
$required_instructors = max(1, (int)($data['required_instructors'] ?? 1));
$salary_grade = trim($data['salary_grade'] ?? '');

$sql = "UPDATE job SET 
            job_title='$title',
            department_role='$dept',
            job_type='$type',
            locations='$loc',
            salary_range='$salary',
            application_deadline='$deadline',
            job_description='$desc',
            job_requirements='$requirements',
            education='$education',
            experience='$experience',
            training='$training',
            eligibility='$eligibility',
            duties='$duties',
            competency='$competency'
        WHERE id=$id";

if ($conn->query($sql) === TRUE) {
    if (nc_column_exists($conn, 'job', 'teaching_hours_per_week')) {
        $meta_sql = "UPDATE job SET subject_code = ?, subject_name = ?, program = ?, academic_year = ?, semester = ?, teaching_schedule = ?, teaching_hours_per_week = ?, load_units = ?, required_instructors = ?, salary_grade = ? WHERE id = ?";
        $meta_stmt = $conn->prepare($meta_sql);
        if ($meta_stmt) {
            $meta_stmt->bind_param("ssssssddisi", $subject_code, $subject_name, $program, $academic_year, $semester, $teaching_schedule, $teaching_hours, $load_units, $required_instructors, $salary_grade, $id);
            $meta_stmt->execute();
            $meta_stmt->close();
        }
    }
    // Log the activity with admin name
    $activity_sql = "INSERT INTO admin_activity (activity_type, description, user_name, related_table, related_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($activity_sql);
    $activity_type = "job_edited";
    $description = "$admin_name updated teaching load: $title";
    $related_table = "job";
    $stmt->bind_param("ssssi", $activity_type, $description, $admin_name, $related_table, $id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => $conn->error]);
}

$conn->close();
?>
