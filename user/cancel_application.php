<?php
// Disable display errors but log them
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// ALWAYS output JSON no matter what
header('Content-Type: application/json');

try {
    session_start();
    
    // Database connection
    $conn = new mysqli("127.0.0.1", "root", "", "nchire");
    
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $conn->connect_error]);
        exit;
    }
    
    // Get application ID
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id <= 0) {
        echo '{"success":false,"error":"Invalid ID"}';
        exit;
    }
    
    // Get user ID from session
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$user_id) {
        echo '{"success":false,"error":"Not authenticated"}';
        exit;
    }
    
    // Update application: set status, workflow_stage, rejected_date, and rejection_reason
    $cancellation_reason = "Application cancelled by applicant";
    
    $stmt = $conn->prepare("UPDATE job_applicants 
                             SET status = 'Cancelled', 
                                 workflow_stage = 'cancelled', 
                                 rejected_date = NOW(),
                                 rejection_reason = ?
                             WHERE id = ? AND user_id = ?");
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Failed to prepare statement: ' . $conn->error]);
        $conn->close();
        exit;
    }
    
    $stmt->bind_param("sii", $cancellation_reason, $id, $user_id);
    
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'error' => 'Failed to execute update: ' . $error]);
        exit;
    }
    
    if ($stmt->affected_rows === 0) {
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'error' => 'Application not found or already cancelled']);
        exit;
    }
    
    $stmt->close();
    
    // Try to apply ban (don't fail if this doesn't work)
    $ban_date = date('Y-m-d H:i:s', strtotime('+4 months'));
    $ban_stmt = $conn->prepare("UPDATE applicants SET rejection_ban_until = ? WHERE id = ?");
    if ($ban_stmt) {
        $ban_stmt->bind_param("si", $ban_date, $user_id);
        $ban_stmt->execute();
        $ban_stmt->close();
    }
    
    $conn->close();
    
    // Success
    echo json_encode(['success' => true, 'message' => 'Application cancelled successfully. You cannot apply for new positions for 4 months.']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
}
?>
