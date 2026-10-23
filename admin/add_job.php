<?php
session_start();
header('Content-Type: application/json');
// Prevent PHP notices/warnings from corrupting JSON output
ini_set('display_errors', 0);
require_once __DIR__ . '/../shared/helpers/recruitment.php';
require_once __DIR__ . '/../shared/helpers/dynamic_application_requirements.php';

$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = getenv('NCHIRE_DB_NAME') ?: "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]);
    exit;
}

if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !in_array($_SESSION['admin_role'] ?? '', ['Secretary', 'Department Head', 'HR Manager', 'Recruiter'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get admin info from session
$admin_name = $_SESSION['admin_name'] ?? 'Unknown Admin';

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    echo json_encode(["success" => false, "message" => "No input data"]);
    exit;
}

$hasApplicationRequirements = array_key_exists('application_requirements', $data);
$applicationRequirements = null;
if ($hasApplicationRequirements) {
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    $requestToken = (string)($data['csrf_token'] ?? '');
    if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Your session token expired. Refresh the page and try again.']);
        exit;
    }
    if (!nc_admin_can_manage_requirements(
        (string)($_SESSION['admin_role'] ?? ''),
        (string)($_SESSION['admin_department'] ?? ''),
        (string)($data['department_role'] ?? '')
    )) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only the Secretary or the Dean for this department may manage application requirements.']);
        exit;
    }
    try {
        $applicationRequirements = nc_validate_requirement_payload($data['application_requirements']);
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
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
$load_units = (isset($data["load_units"]) && $data["load_units"] !== "") ? (float)$data["load_units"] : null;
$hasUnitBreakdown = array_key_exists('lecture_units', $data) || array_key_exists('laboratory_units', $data);
$lecture_units = ($data['lecture_units'] ?? '') !== '' ? filter_var($data['lecture_units'], FILTER_VALIDATE_FLOAT) : null;
$laboratory_units = ($data['laboratory_units'] ?? '') !== '' ? filter_var($data['laboratory_units'], FILTER_VALIDATE_FLOAT) : null;
$available_sections = ($data['available_sections'] ?? '') !== '' ? filter_var($data['available_sections'], FILTER_VALIDATE_INT) : null;
if ($hasUnitBreakdown && ($lecture_units === false || $laboratory_units === false || $lecture_units === null || $laboratory_units === null || $lecture_units < 0 || $laboratory_units < 0)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Lecture Units and Laboratory Units must be valid non-negative numbers.']);
    exit;
}
if ($hasUnitBreakdown && ($available_sections === false || $available_sections === null || $available_sections < 0)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Available Sections must be a non-negative whole number.']);
    exit;
}
if ($hasUnitBreakdown && ($title === '' || $department === '' || $type === '' || $deadline === '' || $subject_code === '' || $subject_name === '' || $program === '')) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Complete all required teaching load and subject fields.']);
    exit;
}
if ($deadline !== '' && $deadline < date('Y-m-d')) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Application deadline cannot be in the past.']);
    exit;
}
$teaching_hours = $hasUnitBreakdown
    ? nc_calculate_teaching_hours((float)$lecture_units, (float)$laboratory_units)
    : ((isset($data["teaching_hours_per_week"]) && $data["teaching_hours_per_week"] !== "") ? (float)$data["teaching_hours_per_week"] : null);
$required_instructors = $hasUnitBreakdown ? (int)$available_sections : max(1, (int)($data["required_instructors"] ?? 1));
$salary_grade = trim($data["salary_grade"] ?? "");
if (nc_normalize_employment_type($type) === 'full_time') {
    $salary_grade = nc_resolve_job_salary_grade([
        'job_title' => $title,
        'job_type' => $type,
        'salary_grade' => $salary_grade,
    ], nc_salary_configuration()) ?: $salary_grade;
}

if ($department === 'Computer Science') {
    $department = 'Computing Studies';
}

try {
    $conn->begin_transaction();
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
        if ($hasUnitBreakdown && nc_column_exists($conn, 'job', 'lecture_units')) {
            $newStatus = $available_sections > 0 ? 'Active' : 'Closed';
            $meta_sql = "UPDATE job SET status = ?, subject_code = ?, subject_name = ?, program = ?, academic_year = ?, semester = ?, teaching_schedule = NULL, teaching_hours_per_week = ?, load_units = NULL, lecture_units = ?, laboratory_units = ?, required_instructors = ?, initial_available_sections = ?, available_sections = ?, salary_grade = ? WHERE id = ?";
            $meta_stmt = $conn->prepare($meta_sql);
            if ($meta_stmt) {
                $meta_stmt->bind_param("ssssssdddiiisi", $newStatus, $subject_code, $subject_name, $program, $academic_year, $semester, $teaching_hours, $lecture_units, $laboratory_units, $required_instructors, $available_sections, $available_sections, $salary_grade, $job_id);
                if (!$meta_stmt->execute()) {
                    throw new RuntimeException('Unable to save teaching load details: ' . $meta_stmt->error);
                }
                $meta_stmt->close();
            } else {
                throw new RuntimeException('Unable to prepare teaching load details.');
            }
        } elseif (nc_column_exists($conn, 'job', 'teaching_hours_per_week')) {
            $meta_sql = "UPDATE job SET status = 'Active', subject_code = ?, subject_name = ?, program = ?, academic_year = ?, semester = ?, teaching_schedule = ?, teaching_hours_per_week = ?, load_units = ?, required_instructors = ?, salary_grade = ? WHERE id = ?";
            $meta_stmt = $conn->prepare($meta_sql);
            if ($meta_stmt) {
                $meta_stmt->bind_param("ssssssddisi", $subject_code, $subject_name, $program, $academic_year, $semester, $teaching_schedule, $teaching_hours, $load_units, $required_instructors, $salary_grade, $job_id);
                $meta_stmt->execute();
                $meta_stmt->close();
            }
        }
        if ($hasApplicationRequirements) {
            nc_sync_teaching_load_requirements($conn, $job_id, $applicationRequirements);
        } else {
            nc_seed_default_teaching_load_requirements($conn, $job_id);
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
        $conn->commit();
        echo json_encode(["success" => true, "message" => "Teaching load added successfully", "job_id" => $job_id]);
    } else {
        throw new RuntimeException('Insert failed: ' . $stmt->error);
    }
    $stmt->close();
} catch (Throwable $e) {
    $conn->rollback();
    // Log the error server-side and return a clean JSON error
    error_log('add_job.php error: ' . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Server error while adding teaching load."]);
}

$conn->close();
?>
