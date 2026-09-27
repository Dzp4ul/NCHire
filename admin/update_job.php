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

// Additional fields
$education = trim($data['education'] ?? '');
$experience = trim($data['experience'] ?? '');
$training = trim($data['training'] ?? '');
$eligibility = trim($data['eligibility'] ?? '');
$duties = trim($data['duties'] ?? '');
$competency = trim($data['competency'] ?? '');
$minimum_education_level = trim($data['minimum_education_level'] ?? '') ?: null;
$required_degree_fields = trim($data['required_degree_fields'] ?? '');
$graduate_requirement = trim($data['graduate_requirement'] ?? '') ?: null;
$minimum_graduate_units = ($data['minimum_graduate_units'] ?? '') !== '' ? max(0, min(200, (int)$data['minimum_graduate_units'])) : null;
$minimum_experience_years = ($data['minimum_experience_years'] ?? '') !== '' ? max(0, min(60, (float)$data['minimum_experience_years'])) : null;
$teaching_experience_requirement = trim($data['teaching_experience_requirement'] ?? '') ?: null;
$required_skills = trim($data['required_skills'] ?? '');
$required_certifications = trim($data['required_certifications'] ?? '');
$required_licenses = trim($data['required_licenses'] ?? '');
$required_training = trim($data['required_training'] ?? '');
$preferred_qualifications = trim($data['preferred_qualifications'] ?? '');
if ($minimum_education_level !== null && !in_array($minimum_education_level, ['high_school','associate','bachelor','master','doctorate'], true)) $minimum_education_level = null;
if ($graduate_requirement !== null && !in_array($graduate_requirement, ['none','preferred','master_required','doctorate_required'], true)) $graduate_requirement = null;
if ($teaching_experience_requirement !== null && !in_array($teaching_experience_requirement, ['not_required','preferred','required'], true)) $teaching_experience_requirement = null;

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
    if (nc_column_exists($conn, 'job', 'minimum_education_level')) {
        $ranking_sql = "UPDATE job SET minimum_education_level=?, required_degree_fields=?, graduate_requirement=?, minimum_graduate_units=?, minimum_experience_years=?, teaching_experience_requirement=?, required_skills=?, required_certifications=?, required_licenses=?, required_training=?, preferred_qualifications=? WHERE id=?";
        $ranking_stmt = $conn->prepare($ranking_sql);
        if ($ranking_stmt) {
            $ranking_stmt->bind_param('sssidssssssi', $minimum_education_level, $required_degree_fields, $graduate_requirement, $minimum_graduate_units, $minimum_experience_years, $teaching_experience_requirement, $required_skills, $required_certifications, $required_licenses, $required_training, $preferred_qualifications, $id);
            $ranking_stmt->execute();
            $ranking_stmt->close();
        }
    }
    if (nc_table_exists($conn, 'candidate_rankings')) {
        $invalidate_stmt = $conn->prepare("UPDATE candidate_rankings SET input_hash=REPEAT('0',64), ai_status='stale', updated_at=NOW() WHERE job_posting_id=?");
        if ($invalidate_stmt) {
            $invalidate_stmt->bind_param('i', $id);
            $invalidate_stmt->execute();
            $invalidate_stmt->close();
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
    echo json_encode(["success" => true]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('update_job.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error while updating the teaching load.']);
}

$conn->close();
?>
