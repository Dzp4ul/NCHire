<?php
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// Check if a specific job ID is requested
$jobId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($jobId) {
    // Fetch single job with all fields
    $sql = "SELECT id, job_title, department_role, job_type, locations, salary_range, application_deadline, 
                   job_description, job_requirements, education, experience, training, eligibility, duties, competency 
            FROM job WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $jobId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $job = $result->fetch_assoc();
        echo json_encode($job);
    } else {
        echo json_encode(["error" => "Job not found"]);
    }
    $stmt->close();
} else {
    // Fetch all jobs with all fields
    $sql = "SELECT id, job_title, department_role, job_type, locations, salary_range, application_deadline, 
                   job_description, job_requirements, education, experience, training, eligibility, duties, competency 
            FROM job ORDER BY id DESC";
    $result = $conn->query($sql);

    $jobs = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $jobs[] = $row;
        }
    }
    echo json_encode($jobs);
}

$conn->close();
?>
