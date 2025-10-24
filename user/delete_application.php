<?php
session_start();
header('Content-Type: application/json');

$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['email'] ?? ($_SESSION['applicant_email'] ?? null);

if (!$user_id && !$user_email) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$application_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($application_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid application id']);
    exit;
}

try {
    if ($user_id) {
        $stmt = $conn->prepare("DELETE FROM job_applicants WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $application_id, $user_id);
    } else {
        $stmt = $conn->prepare("DELETE FROM job_applicants WHERE id = ? AND applicant_email = ?");
        $stmt->bind_param("is", $application_id, $user_email);
    }

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Delete failed or not authorized']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
