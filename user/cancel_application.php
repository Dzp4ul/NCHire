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
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ]);
    exit;
}

// Get application ID
$application_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($application_id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid application ID'
    ]);
    exit;
}

// Get user identification
$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['email'] ?? ($_SESSION['applicant_email'] ?? null);

if (!$user_id && !$user_email) {
    echo json_encode([
        'success' => false,
        'error' => 'User not authenticated'
    ]);
    exit;
}

// Verify application belongs to user and update status to "Cancelled"
if ($user_id) {
    $stmt = $conn->prepare("UPDATE job_applicants SET status = 'Cancelled' WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $application_id, $user_id);
} else {
    $stmt = $conn->prepare("UPDATE job_applicants SET status = 'Cancelled' WHERE id = ? AND applicant_email = ?");
    $stmt->bind_param("is", $application_id, $user_email);
}

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Application cancelled successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Application not found or already cancelled'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to cancel application'
    ]);
}

$stmt->close();
$conn->close();
?>
