<?php
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

// Get job ID and new status
$job_id = $_POST['job_id'] ?? '';
$new_status = $_POST['status'] ?? '';

if (empty($job_id)) {
    echo json_encode(['success' => false, 'error' => 'Job ID is required']);
    exit;
}

// Validate status
if (!in_array($new_status, ['Active', 'Closed'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid status. Must be Active or Closed']);
    exit;
}

// Update job status
$stmt = $conn->prepare("UPDATE job SET status = ? WHERE id = ?");
$stmt->bind_param("si", $new_status, $job_id);

if ($stmt->execute()) {
    // Get updated job info
    $getStmt = $conn->prepare("SELECT job_title, status, application_deadline FROM job WHERE id = ?");
    $getStmt->bind_param("i", $job_id);
    $getStmt->execute();
    $result = $getStmt->get_result();
    $job = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'message' => 'Job status updated successfully',
        'job' => $job
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update job status: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
