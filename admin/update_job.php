<?php
session_start();
require_once __DIR__ . '/../shared/helpers/recruitment.php';
require_once __DIR__ . '/../shared/helpers/dynamic_application_requirements.php';
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}

header('Content-Type: application/json');
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !in_array($_SESSION['admin_role'] ?? '', ['Secretary', 'Department Head', 'HR Manager', 'Recruiter'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get admin info from session
$admin_name = $_SESSION['admin_name'] ?? 'Unknown Admin';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
    echo json_encode(["success" => false, "message" => "Missing job ID"]);
    exit;
}

$id = (int)$data['id'];
if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid teaching load ID is required.']);
    exit;
}
$existingJobStmt = $conn->prepare('SELECT id, department_role FROM job WHERE id = ? LIMIT 1');
$existingJobStmt->bind_param('i', $id);
$existingJobStmt->execute();
$existingJob = $existingJobStmt->get_result()->fetch_assoc();
$existingJobStmt->close();
if (!$existingJob) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Teaching load not found.']);
    exit;
}
$title = trim($data['job_title'] ?? '');
$department = $data['department_role'] ?? '';
if ($department === 'Computer Science') {
    $department = 'Computing Studies';
}
$type  = trim($data['job_type'] ?? '');
$loc   = trim($data['locations'] ?? '');
$salary= trim($data['salary_range'] ?? '');
$deadline = trim($data['application_deadline'] ?? '');
$desc  = trim($data['job_description'] ?? '');
$requirements = trim($data['job_requirements'] ?? '');
$hasApplicationRequirements = array_key_exists('application_requirements', $data);
$applicationRequirements = null;
$requirementChangeSummary = [];
$applicantCount = 0;
if ($hasApplicationRequirements) {
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    $requestToken = (string)($data['csrf_token'] ?? '');
    if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Your session token expired. Refresh the page and try again.']);
        exit;
    }
    $adminRole = (string)($_SESSION['admin_role'] ?? '');
    $adminDepartment = (string)($_SESSION['admin_department'] ?? '');
    if (!nc_admin_can_manage_requirements($adminRole, $adminDepartment, (string)$existingJob['department_role'])
        || !nc_admin_can_manage_requirements($adminRole, $adminDepartment, (string)$department)) {
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
    $existingRequirements = nc_get_teaching_load_requirements($conn, $id, false);
    $requirementChangeSummary = nc_requirements_change_summary($existingRequirements, $applicationRequirements);
    $countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM job_applicants WHERE job_id = ?');
    $countStmt->bind_param('i', $id);
    $countStmt->execute();
    $applicantCount = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $countStmt->close();
    if ($applicantCount > 0 && $requirementChangeSummary && empty($data['confirm_requirement_changes'])) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'confirmation_required' => true,
            'affected_applicants' => $applicantCount,
            'changes' => $requirementChangeSummary,
            'message' => 'These changes affect document completion for existing applicants. Their uploaded files and recruitment stages will be preserved.',
        ]);
        exit;
    }
}

// Additional fields
$education = trim($data['education'] ?? '');
$experience = trim($data['experience'] ?? '');
$training = trim($data['training'] ?? '');
$eligibility = trim($data['eligibility'] ?? '');
$duties = trim($data['duties'] ?? '');
$competency = trim($data['competency'] ?? '');

$subject_code = trim($data['subject_code'] ?? '');
$subject = trim($data['subject'] ?? '');
$subject_name = trim($data['subject_name'] ?? '') ?: ($subject ?: $title);
$program = trim($data['program'] ?? '') ?: $department;
$academic_year = trim($data['academic_year'] ?? '') ?: nc_current_academic_year();
$semester = nc_normalize_semester($data['semester'] ?? '');
$teaching_schedule = trim($data['teaching_schedule'] ?? '');
$load_units = (isset($data['load_units']) && $data['load_units'] !== '') ? (float)$data['load_units'] : null;
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
$teaching_hours = $hasUnitBreakdown
    ? nc_calculate_teaching_hours((float)$lecture_units, (float)$laboratory_units)
    : ((isset($data['teaching_hours_per_week']) && $data['teaching_hours_per_week'] !== '') ? (float)$data['teaching_hours_per_week'] : null);
$required_instructors = $hasUnitBreakdown ? (int)$available_sections : max(1, (int)($data['required_instructors'] ?? 1));
$salary_grade = trim($data['salary_grade'] ?? '');
if (nc_normalize_employment_type($type) === 'full_time') {
    $salary_grade = nc_resolve_job_salary_grade([
        'job_title' => $title,
        'job_type' => $type,
        'salary_grade' => $salary_grade,
    ], nc_salary_configuration()) ?: $salary_grade;
}

try {
    $conn->begin_transaction();
    $sql = "UPDATE job SET job_title=?, department_role=?, job_type=?, locations=?, salary_range=?, application_deadline=?, job_description=?, job_requirements=?, education=?, experience=?, training=?, eligibility=?, duties=?, competency=? WHERE id=?";
    $update_stmt = $conn->prepare($sql);
    if (!$update_stmt) {
        throw new RuntimeException('Unable to prepare the teaching load update.');
    }
    $update_stmt->bind_param('ssssssssssssssi', $title, $department, $type, $loc, $salary, $deadline, $desc, $requirements, $education, $experience, $training, $eligibility, $duties, $competency, $id);
    if (!$update_stmt->execute()) {
        throw new RuntimeException('Unable to update the teaching load.');
    }
    $update_stmt->close();

    if ($hasUnitBreakdown && nc_column_exists($conn, 'job', 'lecture_units')) {
        $newStatus = $available_sections > 0 ? 'Active' : 'Closed';
        $meta_sql = "UPDATE job SET status = ?, subject = ?, subject_code = ?, subject_name = ?, program = ?, academic_year = ?, semester = ?, teaching_schedule = NULL, teaching_hours_per_week = ?, load_units = NULL, lecture_units = ?, laboratory_units = ?, required_instructors = GREATEST(COALESCE(initial_available_sections, 0), ?), initial_available_sections = GREATEST(COALESCE(initial_available_sections, 0), ?), available_sections = ?, salary_grade = ? WHERE id = ?";
        $meta_stmt = $conn->prepare($meta_sql);
        if ($meta_stmt) {
            $meta_stmt->bind_param("sssssssdddiiisi", $newStatus, $subject, $subject_code, $subject_name, $program, $academic_year, $semester, $teaching_hours, $lecture_units, $laboratory_units, $required_instructors, $required_instructors, $available_sections, $salary_grade, $id);
            if (!$meta_stmt->execute()) {
                throw new RuntimeException('Unable to save teaching load details: ' . $meta_stmt->error);
            }
            $meta_stmt->close();
        } else {
            throw new RuntimeException('Unable to prepare teaching load details.');
        }
    } elseif (nc_column_exists($conn, 'job', 'teaching_hours_per_week')) {
        $meta_sql = "UPDATE job SET subject_code = ?, subject_name = ?, program = ?, academic_year = ?, semester = ?, teaching_schedule = ?, teaching_hours_per_week = ?, load_units = ?, required_instructors = ?, salary_grade = ? WHERE id = ?";
        $meta_stmt = $conn->prepare($meta_sql);
        if ($meta_stmt) {
            $meta_stmt->bind_param("ssssssddisi", $subject_code, $subject_name, $program, $academic_year, $semester, $teaching_schedule, $teaching_hours, $load_units, $required_instructors, $salary_grade, $id);
            $meta_stmt->execute();
            $meta_stmt->close();
        }
    }
    $notifiedApplicants = 0;
    if ($hasApplicationRequirements) {
        $newlyRequired = nc_sync_teaching_load_requirements($conn, $id, $applicationRequirements);
        if ($applicantCount > 0 && $newlyRequired) {
            $notifiedApplicants = nc_notify_new_required_documents($conn, $id, $newlyRequired);
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

    $conn->commit();
    echo json_encode([
        "success" => true,
        'requirements_changed' => $hasApplicationRequirements,
        'affected_applicants' => $applicantCount,
        'applicants_notified' => $notifiedApplicants ?? 0,
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('update_job.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error while updating the teaching load.']);
}

$conn->close();
?>
