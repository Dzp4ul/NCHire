<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "nchire";

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

// Get applicant details
$stmt = $conn->prepare("SELECT * FROM job_applicants WHERE id = ?");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Applicant not found']);
    exit;
}

$applicant = $result->fetch_assoc();

$role = $_SESSION['admin_role'] ?? '';
$department = $_SESSION['admin_department'] ?? '';
$departmentAlias = $department === 'Computing Studies' ? 'Computer Science' : ($department === 'Computer Science' ? 'Computing Studies' : $department);
$authorized = false;
if ($role === 'Secretary') {
    $secretaryId = (int)($_SESSION['admin_id'] ?? 0);
    $authorized = $applicant['workflow_stage'] !== 'rejected' && (empty($applicant['secretary_id']) || $applicant['workflow_stage'] === 'secretary_review' || (int)$applicant['secretary_id'] === $secretaryId);
} elseif ($role === 'Department Head') {
    $allowedStages = ['waiting_interview_schedule','department_head_review','interview_scheduled','interview_completed','demo_scheduled','demo_completed','psych_scheduled','psych_completed','initially_hired','permanently_hired','passed','hired'];
    $authorized = in_array($applicant['workflow_stage'], $allowedStages, true) && in_array($applicant['assigned_to_department'], [$department, $departmentAlias], true);
} elseif (in_array($role, ['HR Manager', 'Recruiter'], true)) {
    $authorized = $applicant['status'] !== 'Rejected' && in_array($applicant['assigned_to_department'], [$department, $departmentAlias], true);
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

// Get education data
$education = [];
if ($user_id) {
    $education_stmt = $conn->prepare("SELECT * FROM user_education WHERE user_id = ? ORDER BY end_year DESC");
    $education_stmt->bind_param("i", $user_id);
    $education_stmt->execute();
    $education_result = $education_stmt->get_result();
    while ($row = $education_result->fetch_assoc()) {
        $education[] = $row;
    }
}

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
    'qualifications' => $qualifications
]);

$conn->close();
?>
