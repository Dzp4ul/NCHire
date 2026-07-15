<?php
session_start();

// Process forgot password request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $_SESSION['fp_error'] = 'Please enter your email address.';
        header('Location: forgot_password.php');
        exit();
    }
    
    // Database connection
    $host = "127.0.0.1";
    $user = "root";
    $pass = "";
    $dbname = "nchire";
    
    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        $_SESSION['fp_error'] = 'Database connection failed. Please try again later.';
        header('Location: forgot_password.php');
        exit();
    }
    
    $user_found = false;
    $user_name = '';
    $user_type = '';
    $update_query = '';
    
    // Check admin_users table first (Admin, Department Head, Secretary, etc.)
    $stmt = $conn->prepare("SELECT id, full_name, role FROM admin_users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $user_found = true;
        $user_name = $row['full_name'];
        $user_type = $row['role'];
        $user_id = $row['id'];
        $table = 'admin_users';
    }
    $stmt->close();
    
    // Check applicants table if not found in admin_users
    if (!$user_found) {
        $stmt = $conn->prepare("SELECT id, first_name, last_name FROM applicants WHERE applicant_email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $user_found = true;
            $user_name = $row['first_name'] . ' ' . $row['last_name'];
            $user_type = 'Applicant';
            $user_id = $row['id'];
            $table = 'applicants';
        }
        $stmt->close();
    }
    
    // If user not found
    if (!$user_found) {
        $_SESSION['fp_error'] = 'No account found with that email address.';
        $conn->close();
        header('Location: forgot_password.php');
        exit();
    }
    
    // Generate temporary password (10 characters)
    $temporaryPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%'), 0, 10);
    
    // Update database based on user type
    if ($table === 'admin_users') {
        // Admin users use hashed passwords
        $hashed_password = password_hash($temporaryPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admin_users SET password = ?, password_change_required = 1 WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
    } else {
        // Applicants use plain text passwords (as per current system)
        $stmt = $conn->prepare("UPDATE applicants SET applicant_password = ?, password_change_required = 1 WHERE id = ?");
        $stmt->bind_param("si", $temporaryPassword, $user_id);
    }
    
    if (!$stmt->execute()) {
        $_SESSION['fp_error'] = 'Failed to update password. Please try again.';
        $stmt->close();
        $conn->close();
        header('Location: forgot_password.php');
        exit();
    }
    $stmt->close();
    
    // Send email with temporary password
    require_once '../shared/helpers/send_temp_password_email.php';
    $emailResult = sendForgotPasswordEmail($email, $user_name, $temporaryPassword, $user_type);
    
    if ($emailResult['success']) {
        $_SESSION['fp_success'] = 'A temporary password has been sent to your email address. Please check your inbox.';
        
        // Log the password reset activity for admin users
        if ($table === 'admin_users') {
            $activity_stmt = $conn->prepare("INSERT INTO admin_activity (activity_type, description, user_name, created_at) VALUES (?, ?, ?, NOW())");
            $activity_type = "password_reset";
            $activity_desc = "$user_name requested a password reset";
            $activity_stmt->bind_param("sss", $activity_type, $activity_desc, $user_name);
            $activity_stmt->execute();
            $activity_stmt->close();
        }
    } else {
        $_SESSION['fp_error'] = 'Password was updated but email notification failed. Please contact support.';
    }
    
    $conn->close();
    header('Location: forgot_password.php');
    exit();
}

// If accessed directly without POST, redirect to forgot password page
header('Location: forgot_password.php');
exit();
?>
