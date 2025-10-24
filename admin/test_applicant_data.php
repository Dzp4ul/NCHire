<?php
// Test applicant data retrieval
$servername = "127.0.0.1";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get applicant with ID 34
$stmt = $conn->prepare("SELECT * FROM job_applicants WHERE id = 34");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $applicant = $result->fetch_assoc();
    
    echo "Applicant Data:\n";
    echo "ID: " . $applicant['id'] . "\n";
    echo "Name: " . $applicant['applicant_name'] . "\n";
    echo "Email: " . $applicant['applicant_email'] . "\n";
    echo "User ID: " . $applicant['user_id'] . "\n";
    echo "\nDocuments:\n";
    echo "Application Letter: " . ($applicant['application_letter'] ?? 'NULL') . "\n";
    echo "Resume: " . ($applicant['resume'] ?? 'NULL') . "\n";
    echo "TOR: " . ($applicant['tor'] ?? 'NULL') . "\n";
    echo "Diploma: " . ($applicant['diploma'] ?? 'NULL') . "\n";
    echo "Professional License: " . ($applicant['professional_license'] ?? 'NULL') . "\n";
    echo "COE: " . ($applicant['coe'] ?? 'NULL') . "\n";
    echo "Seminars/Trainings: " . ($applicant['seminars_trainings'] ?? 'NULL') . "\n";
    
    // Check if user profile data exists
    $user_id = $applicant['user_id'];
    if ($user_id) {
        echo "\nUser Profile Data for user_id: $user_id\n";
        
        // Check education
        $edu_stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_education WHERE user_id = ?");
        $edu_stmt->bind_param("i", $user_id);
        $edu_stmt->execute();
        $edu_result = $edu_stmt->get_result();
        $edu_count = $edu_result->fetch_assoc()['count'];
        echo "Education records: $edu_count\n";
        
        // Check experience
        $exp_stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_experience WHERE user_id = ?");
        $exp_stmt->bind_param("i", $user_id);
        $exp_stmt->execute();
        $exp_result = $exp_stmt->get_result();
        $exp_count = $exp_result->fetch_assoc()['count'];
        echo "Experience records: $exp_count\n";
        
        // Check skills
        $skill_stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_skills WHERE user_id = ?");
        $skill_stmt->bind_param("i", $user_id);
        $skill_stmt->execute();
        $skill_result = $skill_stmt->get_result();
        $skill_count = $skill_result->fetch_assoc()['count'];
        echo "Skills records: $skill_count\n";
    }
} else {
    echo "No applicant found with ID 34\n";
}

$conn->close();
?>
