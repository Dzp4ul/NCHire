<?php
session_start();
header('Content-Type: application/json');

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['user_email'] ?? ($_SESSION['email'] ?? ($_SESSION['applicant_email'] ?? null));

if ($application_id === 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid application ID']);
    exit;
}

// Fetch application details with first_name and last_name from applicants table
$stmt = $conn->prepare("
    SELECT ja.*, a.first_name, a.last_name, a.address 
    FROM job_applicants ja 
    LEFT JOIN applicants a ON ja.user_id = a.id 
    WHERE ja.id = ? AND (ja.user_id = ? OR ja.applicant_email = ?)
");
$stmt->bind_param("iis", $application_id, $user_id, $user_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Application not found or access denied']);
    exit;
}

$application = $result->fetch_assoc();

// If first_name and last_name are not available from applicants table, try to split full_name
if (empty($application['first_name']) && !empty($application['full_name'])) {
    $nameParts = explode(' ', $application['full_name'], 2);
    $application['first_name'] = $nameParts[0] ?? '';
    $application['last_name'] = $nameParts[1] ?? '';
}

// Format date for display
if ($application['applied_date']) {
    $application['applied_date_pretty'] = date('M d, Y', strtotime($application['applied_date']));
}

// Get work experience for this application's user
$work_experience = [];
if (!empty($application['user_id'])) {
    $exp_stmt = $conn->prepare("SELECT * FROM user_experience WHERE user_id = ? ORDER BY start_date DESC");
    $exp_stmt->bind_param("i", $application['user_id']);
    $exp_stmt->execute();
    $exp_result = $exp_stmt->get_result();
    while ($exp = $exp_result->fetch_assoc()) {
        $work_experience[] = $exp;
    }
    $exp_stmt->close();
}

// Get education for this application's user
$education = [];
if (!empty($application['user_id'])) {
    $edu_stmt = $conn->prepare("SELECT * FROM user_education WHERE user_id = ? ORDER BY start_year DESC");
    $edu_stmt->bind_param("i", $application['user_id']);
    $edu_stmt->execute();
    $edu_result = $edu_stmt->get_result();
    while ($edu = $edu_result->fetch_assoc()) {
        $education[] = $edu;
    }
    $edu_stmt->close();
}

// Get skills for this application's user
$skills = [];
if (!empty($application['user_id'])) {
    $skills_stmt = $conn->prepare("SELECT skill_name FROM user_skills WHERE user_id = ? ORDER BY skill_name");
    $skills_stmt->bind_param("i", $application['user_id']);
    $skills_stmt->execute();
    $skills_result = $skills_stmt->get_result();
    while ($skill = $skills_result->fetch_assoc()) {
        $skills[] = $skill['skill_name'];
    }
    $skills_stmt->close();
}

echo json_encode([
    'success' => true,
    'application' => $application,
    'work_experience' => $work_experience,
    'education' => $education,
    'skills' => $skills
]);

$stmt->close();
$conn->close();
?>
