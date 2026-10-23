<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../shared/helpers/recruitment.php';
require_once __DIR__ . '/../shared/helpers/dynamic_application_requirements.php';

if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = getenv('NCHIRE_DB_NAME') ?: "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$applicant_id = $_GET['id'] ?? '';

if (empty($applicant_id)) {
    echo json_encode(['success' => false, 'error' => 'Applicant ID is required']);
    exit;
}

// The account profile is the current identity; job_applicants keeps a copy for
// applications that are no longer linked to an account.
$stmt = $conn->prepare("
    SELECT ja.*,
           a.first_name AS current_first_name,
           a.last_name AS current_last_name,
           a.applicant_email AS current_email,
           a.contact_number AS current_contact_number,
           a.address AS current_address,
           a.profile_picture AS current_profile_picture
    FROM job_applicants ja
    LEFT JOIN applicants a ON a.id = ja.user_id
    WHERE ja.id = ?
");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Applicant not found']);
    exit;
}

$applicant = $result->fetch_assoc();

$current_name = trim(($applicant['current_first_name'] ?? '') . ' ' . ($applicant['current_last_name'] ?? ''));
if ($current_name !== '') {
    $applicant['full_name'] = $current_name;
    $applicant['applicant_name'] = $applicant['current_first_name'];
}
if (!empty($applicant['current_email'])) {
    $applicant['applicant_email'] = $applicant['current_email'];
}
if (!empty($applicant['current_contact_number'])) {
    $applicant['contact_num'] = $applicant['current_contact_number'];
}
if (!empty($applicant['current_address'])) {
    $applicant['address'] = $applicant['current_address'];
}
if (!empty($applicant['current_profile_picture'])) {
    $applicant['profile_picture'] = $applicant['current_profile_picture'];
}
unset(
    $applicant['current_first_name'],
    $applicant['current_last_name'],
    $applicant['current_email'],
    $applicant['current_contact_number'],
    $applicant['current_address'],
    $applicant['current_profile_picture']
);

$role = $_SESSION['admin_role'] ?? '';
$department = $_SESSION['admin_department'] ?? '';
$departmentAlias = $department === 'Computing Studies' ? 'Computer Science' : ($department === 'Computer Science' ? 'Computing Studies' : $department);
$workflowStage = strtolower((string)($applicant['workflow_stage'] ?? ''));
$applicationStatus = strtolower((string)($applicant['status'] ?? ''));
$isArchived = in_array($workflowStage, ['rejected', 'cancelled'], true);
$isInAssignedDepartment = in_array($applicant['assigned_to_department'], [$department, $departmentAlias], true);
$authorized = false;
if ($role === 'Secretary') {
    $secretaryId = (int)($_SESSION['admin_id'] ?? 0);
    // The Secretary archive intentionally spans all departments, matching
    // get_archive.php. Active records retain their existing ownership check.
    $authorized = $isArchived || (empty($applicant['secretary_id']) || $workflowStage === 'secretary_review' || (int)$applicant['secretary_id'] === $secretaryId);
} elseif ($role === 'Department Head') {
    $allowedStages = ['waiting_interview_schedule','department_head_review','interview_scheduled','interview_completed','demo_scheduled','demo_completed','psych_scheduled','psych_completed','initially_hired','permanently_hired','passed','hired'];
    $authorized = $isInAssignedDepartment && ($isArchived || in_array($workflowStage, $allowedStages, true));
} elseif (in_array($role, ['HR Manager', 'Recruiter'], true)) {
    // These roles use the same department-scoped archive listing.
    $authorized = $isInAssignedDepartment && ($isArchived || $applicationStatus !== 'rejected');
}
if (!$authorized) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Application is outside your authorized scope']);
    exit;
}

// Get the user_id from the applicant record
$user_id = $applicant['user_id'];

// If no address in job_applicants, try to get it from applicants table, also get profile picture
if (empty($applicant['address']) && $user_id) {
    $address_stmt = $conn->prepare("SELECT address, profile_picture FROM applicants WHERE id = ?");
    $address_stmt->bind_param("i", $user_id);
    $address_stmt->execute();
    $address_result = $address_stmt->get_result();
    if ($address_result->num_rows > 0) {
        $address_data = $address_result->fetch_assoc();
        $applicant['address'] = $address_data['address'];
        $applicant['profile_picture'] = $address_data['profile_picture'];
    }
    $address_stmt->close();
} else if ($user_id && empty($applicant['profile_picture'])) {
    // Get profile picture even if address exists
    $profile_stmt = $conn->prepare("SELECT profile_picture FROM applicants WHERE id = ?");
    $profile_stmt->bind_param("i", $user_id);
    $profile_stmt->execute();
    $profile_result = $profile_stmt->get_result();
    if ($profile_result->num_rows > 0) {
        $profile_data = $profile_result->fetch_assoc();
        $applicant['profile_picture'] = $profile_data['profile_picture'];
    }
    $profile_stmt->close();
}

// Education belongs to the applicant profile and drives the current projection.
$education = $user_id ? nc_get_education_rows($conn, (int)$user_id) : [];

$salary_projection = null;
$job = null;
if (!empty($applicant['job_id'])) {
    $job_stmt = $conn->prepare("SELECT * FROM job WHERE id = ? LIMIT 1");
    if ($job_stmt) {
        $job_stmt->bind_param("i", $applicant['job_id']);
        $job_stmt->execute();
        $job = $job_stmt->get_result()->fetch_assoc();
        $job_stmt->close();
        if ($job) {
            $job['remaining_vacancies'] = nc_remaining_vacancies($conn, $job);
            $job['teaching_load_title'] = nc_format_teaching_load_title($job);
            $salary_projection = nc_calculate_salary_projection_from_education($education, $job, null, $conn);
        }
    }
}

$requirement_completion = !empty($applicant['job_id'])
    ? nc_requirement_completion($conn, (int)$applicant['id'], (int)$applicant['job_id'])
    : ['requirements' => [], 'required_total' => 0, 'required_complete' => 0, 'required_remaining' => 0, 'is_complete' => true, 'missing' => []];
foreach ($requirement_completion['requirements'] as &$requirement) {
    if (!empty($requirement['submission']['id'])) {
        $requirement['submission']['download_url'] = '../user/download_requirement.php?submission_id=' . (int)$requirement['submission']['id'];
    }
}
unset($requirement);

// Get work experience data
$experience = [];
if ($user_id) {
    $experience_stmt = $conn->prepare("SELECT * FROM user_experience WHERE user_id = ? ORDER BY start_date DESC");
    $experience_stmt->bind_param("i", $user_id);
    $experience_stmt->execute();
    $experience_result = $experience_stmt->get_result();
    while ($row = $experience_result->fetch_assoc()) {
        $experience[] = $row;
    }
}

// Get skills data
$skills = [];
if ($user_id) {
    $skills_stmt = $conn->prepare("SELECT * FROM user_skills WHERE user_id = ? ORDER BY skill_category, skill_name");
    $skills_stmt->bind_param("i", $user_id);
    $skills_stmt->execute();
    $skills_result = $skills_stmt->get_result();
    while ($row = $skills_result->fetch_assoc()) {
        $skills[] = $row;
    }
}

// Group skills by category
$skills_by_category = [];
foreach ($skills as $skill) {
    $category = $skill['skill_category'] ?: 'Other';
    if (!isset($skills_by_category[$category])) {
        $skills_by_category[$category] = [];
    }
    $skills_by_category[$category][] = $skill;
}

$qualifications = [];
if ($user_id) {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'user_qualifications'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $qualification_stmt = $conn->prepare("SELECT qualification_type, title, issuing_organization, issued_date, expiry_date, proof_document, verification_status FROM user_qualifications WHERE user_id = ? ORDER BY issued_date DESC, id DESC");
        $qualification_stmt->bind_param("i", $user_id);
        $qualification_stmt->execute();
        $qualification_result = $qualification_stmt->get_result();
        while ($row = $qualification_result->fetch_assoc()) $qualifications[] = $row;
        $qualification_stmt->close();
    }
}

echo json_encode([
    'success' => true,
    'applicant' => $applicant,
    'education' => $education,
    'experience' => $experience,
    'skills' => $skills_by_category,
    'qualifications' => $qualifications,
    'job' => $job,
    'salary_projection' => $salary_projection,
    'application_requirements' => $requirement_completion['requirements'],
    'requirements_completion' => [
        'required_total' => $requirement_completion['required_total'],
        'required_complete' => $requirement_completion['required_complete'],
        'required_remaining' => $requirement_completion['required_remaining'],
        'is_complete' => $requirement_completion['is_complete'],
    ]
]);

$conn->close();
?>
