<?php
// Suppress all errors to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

session_start();
ob_clean();
header('Content-Type: application/json');

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Get application ID
$application_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($application_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid application ID']);
    exit;
}

// Get user identification
$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['email'] ?? ($_SESSION['applicant_email'] ?? null);

if (!$user_id && !$user_email) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}

// Get application details
$app_query = "SELECT user_id, applicant_email FROM job_applicants WHERE id = ?";
$app_stmt = $conn->prepare($app_query);
$app_stmt->bind_param("i", $application_id);
$app_stmt->execute();
$app_result = $app_stmt->get_result();

if ($app_result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Application not found']);
    $app_stmt->close();
    $conn->close();
    exit;
}

$app_data = $app_result->fetch_assoc();
$app_user_id = $app_data['user_id'];
$app_email = $app_data['applicant_email'];
$app_stmt->close();

// Verify ownership
if ($user_id && $app_user_id != $user_id) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    $conn->close();
    exit;
}

// Update application status to Cancelled
$update_stmt = $conn->prepare("UPDATE job_applicants SET status = 'Cancelled' WHERE id = ?");
$update_stmt->bind_param("i", $application_id);

if (!$update_stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'Failed to cancel application']);
    $update_stmt->close();
    $conn->close();
    exit;
}
$update_stmt->close();

// Apply 4-month ban
$ban_expires = date('Y-m-d H:i:s', strtotime('+4 months'));
if ($app_user_id) {
    $ban_stmt = $conn->prepare("UPDATE applicants 
                                SET rejection_ban_until = ?,
                                    ban_reason = 'You cancelled your application. You cannot apply for 4 months.',
                                    banned_by = 'System (Self-Cancellation)'
                                WHERE id = ?");
    if ($ban_stmt) {
        $ban_stmt->bind_param("si", $ban_expires, $app_user_id);
        $ban_stmt->execute();
        $ban_stmt->close();
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Application cancelled successfully. You cannot apply for new positions for 4 months.'
]);

$conn->close();
ob_end_flush();
?>
