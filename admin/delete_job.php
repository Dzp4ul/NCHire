<?php
session_start();
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

// Read JSON body
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["id"])) {
    echo json_encode(["success" => false, "message" => "No job ID provided"]);
    exit;
}

$id = intval($data["id"]);

// Get job title before deletion for logging
$title_sql = "SELECT job_title FROM job WHERE id = $id";
$title_result = $conn->query($title_sql);
$job_title = "Unknown Job";
if ($title_result && $title_result->num_rows > 0) {
    $row = $title_result->fetch_assoc();
    $job_title = $row['job_title'];
}

$sql = "DELETE FROM job WHERE id = $id";

if ($conn->query($sql) === TRUE) {
    // Log the activity with admin name
    $activity_sql = "INSERT INTO admin_activity (activity_type, description, user_name, related_table, related_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($activity_sql);
    $activity_type = "job_deleted";
    $description = "$admin_name deleted job posting: $job_title";
    $related_table = "job";
    $stmt->bind_param("ssssi", $activity_type, $description, $admin_name, $related_table, $id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(["success" => true, "message" => "Job deleted successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Error: " . $conn->error]);
}

$conn->close();
?>
