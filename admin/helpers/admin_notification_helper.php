<?php
require_once __DIR__ . '/email_helper.php';

/**
 * Create notification for all admin users
 * @param mysqli $conn Database connection
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $type Notification type (info, success, warning, danger)
 * @param string $action_type Action type (interview_scheduled, demo_scheduled, hired, etc.)
 * @param int $applicant_id Applicant ID
 * @param string $applicant_name Applicant name
 * @param bool $send_email Whether to send email notifications
 * @return bool Success status
 */
function createAdminNotification($conn, $title, $message, $type, $action_type, $applicant_id = null, $applicant_name = null, $send_email = true) {
    try {
        // Create notification for all admins (admin_id = NULL means all admins will see it)
        $stmt = $conn->prepare("INSERT INTO admin_notifications (admin_id, title, message, type, action_type, applicant_id, applicant_name, created_at) VALUES (NULL, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssis", $title, $message, $type, $action_type, $applicant_id, $applicant_name);
        
        $notification_created = $stmt->execute();
        
        if ($notification_created) {
            error_log("Admin notification created: $title");
            
            // Send email to all active admins if requested
            if ($send_email) {
                sendEmailToAllAdmins($conn, $title, $message, $type);
            }
        } else {
            error_log("Failed to create admin notification: " . $stmt->error);
        }
        
        return $notification_created;
    } catch (Exception $e) {
        error_log("Error creating admin notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email notification to all active admin users
 * @param mysqli $conn Database connection
 * @param string $title Email subject/title
 * @param string $message Email message
 * @param string $type Notification type for styling
 */
function sendEmailToAllAdmins($conn, $title, $message, $type = 'info') {
    try {
        // Fetch all active admin users
        $stmt = $conn->prepare("SELECT id, full_name, email FROM admin_users WHERE status = 'Active'");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $admin_count = 0;
        while ($admin = $result->fetch_assoc()) {
            // Send email to each admin
            $success = sendAdminNotificationEmail(
                $admin['email'],
                $admin['full_name'],
                $title,
                $message,
                $type
            );
            
            if ($success) {
                $admin_count++;
                error_log("Email sent to admin: " . $admin['email']);
            }
        }
        
        error_log("Admin notification emails sent to $admin_count admins");
    } catch (Exception $e) {
        error_log("Error sending admin emails: " . $e->getMessage());
    }
}

/**
 * Send email notification to a specific admin
 * @param string $email Admin email
 * @param string $name Admin name
 * @param string $title Email title
 * @param string $message Email message
 * @param string $type Notification type (info, success, warning, danger)
 * @return bool Success status
 */
function sendAdminNotificationEmail($email, $name, $title, $message, $type = 'info') {
    // Map type to color
    $colors = [
        'info' => '#3b82f6',
        'success' => '#10b981',
        'warning' => '#f59e0b',
        'danger' => '#ef4444'
    ];
    $color = $colors[$type] ?? $colors['info'];
    
    $subject = "NCHire Admin - $title";
    
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); padding: 30px; text-align: center;'>
            <h1 style='color: white; margin: 0;'>NCHire Admin Panel</h1>
        </div>
        
        <div style='padding: 30px; background: #f9fafb;'>
            <div style='background: white; padding: 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);'>
                <h2 style='color: #1f2937; margin-top: 0;'>Hello $name,</h2>
                
                <div style='background: " . ($type === 'danger' ? '#fef2f2' : ($type === 'warning' ? '#fffbeb' : ($type === 'success' ? '#f0fdf4' : '#eff6ff'))) . "; 
                            border-left: 4px solid $color; 
                            padding: 15px; 
                            margin: 20px 0; 
                            border-radius: 4px;'>
                    <h3 style='color: $color; margin: 0 0 10px 0;'>$title</h3>
                    <p style='color: #374151; margin: 0; line-height: 1.6;'>$message</p>
                </div>
                
                <div style='text-align: center; margin-top: 30px;'>
                    <a href='http://localhost/FinalResearch - Copy/admin/index.php' 
                       style='background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
                              color: white;
                              padding: 12px 30px;
                              text-decoration: none;
                              border-radius: 6px;
                              display: inline-block;
                              font-weight: 600;'>
                        View in Admin Panel
                    </a>
                </div>
            </div>
        </div>
        
        <div style='background: #1f2937; color: #9ca3af; padding: 20px; text-align: center; font-size: 12px;'>
            <p style='margin: 5px 0;'>NCHire - Norzagaray College Hiring System</p>
            <p style='margin: 5px 0;'>This is an automated notification. Please do not reply to this email.</p>
        </div>
    </div>
    ";
    
    return sendEmailNotification($email, $name, $subject, $title, $message, $type);
}
?>
