<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to output

try {
    session_start();
    header('Content-Type: application/json');

    // Database connection
    $host = "127.0.0.1";
    $user = "root";
    $pass = "12345678";
    $dbname = "nchire";

    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $conn->connect_error]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Initialization error: ' . $e->getMessage()]);
    exit;
}

try {
    $application_id = isset($_POST['application_id']) ? intval($_POST['application_id']) : 0;
    $user_id = $_SESSION['user_id'] ?? null;
    $user_email = $_SESSION['email'] ?? ($_SESSION['applicant_email'] ?? null);

    if ($application_id === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid application ID']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Parameter error: ' . $e->getMessage()]);
    exit;
}

try {
    // Verify application belongs to user
    $verify_stmt = $conn->prepare("SELECT id FROM job_applicants WHERE id = ? AND (user_id = ? OR applicant_email = ?)");
    $verify_stmt->bind_param("iis", $application_id, $user_id, $user_email);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Application not found or access denied']);
        exit;
    }
    $verify_stmt->close();

    // Handle file upload
    $uploadDir = "uploads/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (!isset($_FILES['psych_receipt']) || $_FILES['psych_receipt']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'File upload failed']);
        exit;
    }

    $file = $_FILES['psych_receipt'];
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only PDF, JPG, and PNG are allowed.']);
        exit;
    }

    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'error' => 'File size must be less than 5MB']);
        exit;
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'psych_receipt_' . $application_id . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['success' => false, 'error' => 'Failed to save file']);
        exit;
    }

    // Update database - set receipt filename and change status to "Psychological Exam"
    $update_stmt = $conn->prepare("UPDATE job_applicants SET 
                                    psych_exam_receipt = ?,
                                    status = 'Psychological Exam'
                                    WHERE id = ?");
    $update_stmt->bind_param("si", $filename, $application_id);

    if ($update_stmt->execute()) {
        // Create notification for admin (optional - may fail if table doesn't exist)
        try {
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_email, user_name, title, message, type, created_at) 
                                           VALUES ('admin', 'Admin', ?, ?, 'info', NOW())");
            if ($notif_stmt) {
                $notif_title = "New Psych Exam Receipt Uploaded";
                $notif_message = "An applicant has uploaded their psychological exam receipt. Please review and approve.";
                $notif_stmt->bind_param("ss", $notif_title, $notif_message);
                $notif_stmt->execute();
                $notif_stmt->close();
            }
        } catch (Exception $e) {
            // Notification failed, but upload succeeded - continue
        }
        
        echo json_encode(['success' => true, 'filename' => $filename]);
    } else {
        // Delete uploaded file if database update fails
        $error_msg = $update_stmt->error;
        unlink($filepath);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $error_msg]);
    }

    $update_stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Script error: ' . $e->getMessage()]);
}
?>
