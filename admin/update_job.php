<?php
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
    echo json_encode(["success" => false, "message" => "Missing job ID"]);
    exit;
}

$id = (int)$data['id'];
$title = $conn->real_escape_string($data['job_title']);
$dept  = $conn->real_escape_string($data['department_role']);
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
    // Log the activity
    $activity_sql = "INSERT INTO admin_activity (activity_type, description, created_at) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($activity_sql);
    $activity_type = "job_edited";
    $description = "Updated job posting: " . $title;
    $stmt->bind_param("ss", $activity_type, $description);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => $conn->error]);
}

$conn->close();
?>
