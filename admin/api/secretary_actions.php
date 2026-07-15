<?php
/**
 * Secretary Actions API
 * Handles secretary-specific actions: Transfer to Department Head, Request Resubmission, Reject
 */

session_start();

// Set JSON header and turn off error display for clean JSON
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// For now, allow both Secretary and Department Head for testing
// In production, you can restrict to only Secretary
$admin_role = $_SESSION['admin_role'] ?? '';

// Try to connect to database
try {
    $db_file = __DIR__ . '/../../config/db.php';
    if (!file_exists($db_file)) {
        throw new Exception('Database config file not found');
    }
    require_once $db_file;
    
    if (!isset($conn)) {
        throw new Exception('Database connection not established');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}

// Email helper - try multiple paths
$email_helper_paths = [
    __DIR__ . '/../helpers/email_helper.php',
    __DIR__ . '/../email_helper.php',
    __DIR__ . '/../../admin/helpers/email_helper.php'
];

$email_helper_loaded = false;
foreach ($email_helper_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $email_helper_loaded = true;
        error_log("Email helper loaded from: $path");
        break;
    }
}

if (!$email_helper_loaded) {
    error_log("WARNING: Email helper not found in any of the expected paths");
}

$action = $_POST['action'] ?? '';
$application_id = $_POST['application_id'] ?? 0;
$secretary_id = $_SESSION['admin_id'] ?? 1; // Default to 1 if not set
$secretary_name = $_SESSION['admin_name'] ?? 'Admin';

if (!$application_id) {
    echo json_encode(['success' => false, 'message' => 'Application ID is required']);
    exit();
}

// Get application details with department info
try {
    $stmt = $conn->prepare("SELECT ja.*, a.applicant_email, a.first_name, a.last_name 
                            FROM job_applicants ja 
                            LEFT JOIN applicants a ON ja.user_id = a.id 
                            WHERE ja.id = ?");
    
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $application_id);
    $stmt->execute();
    $application = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$application) {
        echo json_encode(['success' => false, 'message' => 'Application not found']);
        exit();
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database query error: ' . $e->getMessage()]);
    exit();
}

switch ($action) {
    case 'transfer_to_dept_head':
        handleTransferToDeptHead($conn, $application_id, $application, $secretary_id, $secretary_name);
        break;
    
    case 'request_resubmission':
        handleRequestResubmission($conn, $application_id, $application, $secretary_id, $secretary_name);
        break;
    
    case 'reject':
        handleReject($conn, $application_id, $application, $secretary_id, $secretary_name);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit();
}

/**
 * Transfer application to Department Head
 */
function handleTransferToDeptHead($conn, $application_id, $application, $secretary_id, $secretary_name) {
    try {
        $notes = $_POST['notes'] ?? '';
        
        // Update application workflow
        $stmt = $conn->prepare("UPDATE job_applicants 
                               SET workflow_stage = 'department_head_review',
                                   secretary_id = ?,
                                   secretary_review_date = NOW(),
                                   secretary_notes = ?,
                                   transferred_to_dept_head_date = NOW()
                               WHERE id = ?");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("isi", $secretary_id, $notes, $application_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Log workflow history (optional - won't fail if table doesn't exist)
        try {
            logWorkflowHistory($conn, $application_id, 'secretary_review', 'department_head_review', 
                              $secretary_id, 'Secretary', 'transfer', $notes);
        } catch (Exception $e) {
            error_log("Workflow history logging failed: " . $e->getMessage());
        }
        
        // Log admin activity (optional - won't fail if table doesn't exist)
        try {
            logAdminActivity($conn, 'applicant_transferred', 
                            "$secretary_name transferred application for {$application['full_name']} to Department Head",
                            $secretary_name, 'job_applicants', $application_id);
        } catch (Exception $e) {
            error_log("Admin activity logging failed: " . $e->getMessage());
        }
        
        // Create in-app notification for applicant
        $applicant_email = $application['applicant_email'] ?? '';
        $applicant_name = $application['first_name'] ?? $application['full_name'];
        
        error_log("=== APPLICANT NOTIFICATION DEBUG ===");
        error_log("Applicant Email: " . ($applicant_email ?: 'EMPTY'));
        error_log("Applicant Name: " . ($applicant_name ?: 'EMPTY'));
        
        if ($applicant_email) {
            try {
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_email, user_name, title, message, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                if (!$notif_stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $notif_title = "Application Transferred to Dean";
                $notif_message = "Your application for {$application['position']} has been reviewed and forwarded to the dean for evaluation. You will be notified of the next steps.";
                $notif_type = "info";
                $notif_stmt->bind_param("sssss", $applicant_email, $applicant_name, $notif_title, $notif_message, $notif_type);
                
                if ($notif_stmt->execute()) {
                    $notif_id = $notif_stmt->insert_id;
                    error_log("✅ In-app notification created successfully (ID: $notif_id) for applicant: $applicant_email");
                } else {
                    error_log("❌ Failed to execute notification insert: " . $notif_stmt->error);
                }
                $notif_stmt->close();
            } catch (Exception $e) {
                error_log("❌ Exception creating in-app notification: " . $e->getMessage());
            }
        } else {
            error_log("⚠️ Skipping applicant notification - no email address");
        }
        
        // Send email notification to applicant
        error_log("=== APPLICANT EMAIL DEBUG ===");
        error_log("sendEmailNotification function exists: " . (function_exists('sendEmailNotification') ? 'YES' : 'NO'));
        
        if ($applicant_email && function_exists('sendEmailNotification')) {
            try {
                error_log("Attempting to send email to: $applicant_email");
                $email_result = sendEmailNotification(
                    $applicant_email,
                    $applicant_name,
                    'Application Transferred to Dean',
                    'Application Under Review',
                    'Your application for ' . $application['position'] . ' has been reviewed by our secretary and forwarded to the dean for further evaluation. You will be notified of the next steps.',
                    'info'
                );
                if ($email_result) {
                    error_log("✅ Email notification sent successfully to applicant: $applicant_email");
                } else {
                    error_log("⚠️ Email function returned false for applicant: $applicant_email");
                }
            } catch (Exception $e) {
                // Email failed but don't stop the process
                error_log("❌ Email notification exception: " . $e->getMessage());
            }
        } else {
            if (!$applicant_email) {
                error_log("⚠️ Skipping applicant email - no email address");
            }
            if (!function_exists('sendEmailNotification')) {
                error_log("⚠️ Skipping applicant email - sendEmailNotification function not available");
            }
        }
        
        // Get department head for this application's department
        $department = $application['assigned_to_department'] ?? '';
        
        error_log("=== DEPARTMENT HEAD NOTIFICATION DEBUG ===");
        error_log("Department: " . ($department ?: 'EMPTY'));
        
        if ($department) {
            try {
                $dept_head_stmt = $conn->prepare("SELECT id, full_name, email, department FROM admin_users WHERE role = 'Department Head' AND department = ? AND status = 'Active' LIMIT 1");
                $dept_head_stmt->bind_param("s", $department);
                $dept_head_stmt->execute();
                $dept_head_result = $dept_head_stmt->get_result();
                
                error_log("Department heads found: " . $dept_head_result->num_rows);
                
                if ($dept_head_result->num_rows > 0) {
                    $dept_head = $dept_head_result->fetch_assoc();
                    $dept_head_id = $dept_head['id'];
                    $dept_head_name = $dept_head['full_name'];
                    $dept_head_email = $dept_head['email'];
                    
                    error_log("Dept Head ID: $dept_head_id");
                    error_log("Dept Head Name: $dept_head_name");
                    error_log("Dept Head Email: $dept_head_email");
                    error_log("Dept Head Department: " . $dept_head['department']);
                    
                    // Create admin notification for department head
                    $admin_notif_stmt = $conn->prepare("INSERT INTO admin_notifications (admin_id, title, message, type, action_type, applicant_id, applicant_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    if (!$admin_notif_stmt) {
                        throw new Exception("Admin notification prepare failed: " . $conn->error);
                    }
                    $admin_title = "New Application Transferred";
                    $admin_message = "Application from {$application['full_name']} for {$application['position']} has been transferred to you by $secretary_name for review." . ($notes ? " Notes: $notes" : "");
                    $admin_type = "info";
                    $admin_action_type = "application_transferred";
                    $admin_notif_stmt->bind_param("isssiis", $dept_head_id, $admin_title, $admin_message, $admin_type, $admin_action_type, $application_id, $application['full_name']);
                    
                    if ($admin_notif_stmt->execute()) {
                        $admin_notif_id = $admin_notif_stmt->insert_id;
                        error_log("✅ Admin notification created successfully (ID: $admin_notif_id) for department head: $dept_head_name (ID: $dept_head_id)");
                    } else {
                        error_log("❌ Failed to execute admin notification: " . $admin_notif_stmt->error);
                    }
                    $admin_notif_stmt->close();
                    
                    // Send email notification to department head
                    error_log("=== DEPARTMENT HEAD EMAIL DEBUG ===");
                    error_log("sendEmailNotification function exists: " . (function_exists('sendEmailNotification') ? 'YES' : 'NO'));
                    
                    if (function_exists('sendEmailNotification')) {
                        try {
                            error_log("Attempting to send email to department head: $dept_head_email");
                            $dept_email_result = sendEmailNotification(
                                $dept_head_email,
                                $dept_head_name,
                                'New Application Transferred - NCHire',
                                'New Application for Review',
                                "Hello $dept_head_name,\n\nAn application from {$application['full_name']} for the position of {$application['position']} has been transferred to you by $secretary_name for review." . ($notes ? "\n\nSecretary Notes: $notes" : "") . "\n\nPlease log in to the admin panel to review the application.",
                                'info'
                            );
                            if ($dept_email_result) {
                                error_log("✅ Email notification sent successfully to department head: $dept_head_email");
                            } else {
                                error_log("⚠️ Email function returned false for department head: $dept_head_email");
                            }
                        } catch (Exception $e) {
                            error_log("❌ Failed to send email to department head: " . $e->getMessage());
                        }
                    } else {
                        error_log("⚠️ sendEmailNotification function not available for department head email");
                    }
                } else {
                    error_log("❌ No active department head found for department: $department");
                    // Show available department heads for debugging
                    $all_dept_heads = $conn->query("SELECT id, full_name, department, role, status FROM admin_users WHERE role = 'Department Head'");
                    if ($all_dept_heads && $all_dept_heads->num_rows > 0) {
                        error_log("Available department heads:");
                        while ($dh = $all_dept_heads->fetch_assoc()) {
                            error_log("  - ID: {$dh['id']}, Name: {$dh['full_name']}, Dept: {$dh['department']}, Status: {$dh['status']}");
                        }
                    } else {
                        error_log("  No department heads found in database at all!");
                    }
                }
                $dept_head_stmt->close();
            } catch (Exception $e) {
                error_log("❌ Exception notifying department head: " . $e->getMessage());
            }
        } else {
            error_log("⚠️ Skipping department head notification - no department assigned to application");
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Application successfully transferred to Dean'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to transfer application: ' . $e->getMessage()
        ]);
    }
}

/**
 * Request document resubmission
 */
function handleRequestResubmission($conn, $application_id, $application, $secretary_id, $secretary_name) {
    $documents = $_POST['documents'] ?? [];
    $reason = $_POST['reason'] ?? '';
    
    if (empty($documents)) {
        echo json_encode(['success' => false, 'message' => 'Please select at least one document']);
        exit();
    }
    
    $documents_json = json_encode($documents);
    
    // Update application status
    $stmt = $conn->prepare("UPDATE job_applicants 
                           SET status = 'Resubmission Required',
                               workflow_stage = 'secretary_review',
                               resubmission_documents = ?,
                               resubmission_reason = ?,
                               secretary_id = ?,
                               secretary_review_date = NOW(),
                               secretary_notes = ?
                           WHERE id = ?");
    $stmt->bind_param("ssisi", $documents_json, $reason, $secretary_id, $reason, $application_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Log workflow history
        logWorkflowHistory($conn, $application_id, 'secretary_review', 'secretary_review', 
                          $secretary_id, 'Secretary', 'resubmission_requested', $reason);
        
        // Log admin activity
        logAdminActivity($conn, 'resubmission_requested',
                        "$secretary_name requested document resubmission for {$application['full_name']}",
                        $secretary_name, 'job_applicants', $application_id);
        
        // Send email notification (optional)
        $applicant_email = $application['applicant_email'] ?? '';
        if ($applicant_email && function_exists('sendResubmissionEmail')) {
            try {
                sendResubmissionEmail(
                    $applicant_email,
                    $application['first_name'] ?? $application['full_name'],
                    $documents,
                    $reason
                );
            } catch (Exception $e) {
                error_log("Email notification failed: " . $e->getMessage());
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Resubmission request sent successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to request resubmission']);
    }
}

/**
 * Reject application
 */
function handleReject($conn, $application_id, $application, $secretary_id, $secretary_name) {
    $reason = $_POST['reason'] ?? 'Application rejected by secretary';
    
    // Calculate ban expiration date (4 months from now)
    $ban_expires = date('Y-m-d H:i:s', strtotime('+4 months'));
    $user_id = $application['user_id'] ?? null;
    
    // Update application status
    $stmt = $conn->prepare("UPDATE job_applicants 
                           SET status = 'Rejected',
                               workflow_stage = 'rejected',
                               rejection_reason = ?,
                               secretary_id = ?,
                               secretary_review_date = NOW(),
                               secretary_notes = ?
                           WHERE id = ?");
    $stmt->bind_param("sisi", $reason, $secretary_id, $reason, $application_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Update applicant's ban status if user_id exists
        if ($user_id) {
            $ban_stmt = $conn->prepare("UPDATE applicants 
                                       SET rejection_ban_until = ?,
                                           ban_reason = ?,
                                           banned_by = ?,
                                           rejection_count = rejection_count + 1
                                       WHERE id = ?");
            $ban_by = "Secretary: $secretary_name";
            $ban_reason = "Application rejected by Secretary. Reason: $reason";
            $ban_stmt->bind_param("sssi", $ban_expires, $ban_reason, $ban_by, $user_id);
            $ban_stmt->execute();
            $ban_stmt->close();
            
            // Log ban in application_bans table for audit trail
            $log_stmt = $conn->prepare("INSERT INTO application_bans 
                                       (applicant_id, applicant_email, application_id, banned_date, 
                                        ban_expires, ban_reason, banned_by_id, banned_by_name, 
                                        banned_by_role, rejection_reason, position_applied)
                                       VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, 'Secretary', ?, ?)");
            $position = $application['position'] ?? 'Unknown Position';
            $log_stmt->bind_param("isisissss", $user_id, $application['applicant_email'], 
                                 $application_id, $ban_expires, $ban_reason, 
                                 $secretary_id, $secretary_name, $reason, $position);
            $log_stmt->execute();
            $log_stmt->close();
        }
        
        // Log workflow history
        logWorkflowHistory($conn, $application_id, 'secretary_review', 'rejected',
                          $secretary_id, 'Secretary', 'reject', $reason);
        
        // Log admin activity
        logAdminActivity($conn, 'applicant_rejected',
                        "$secretary_name rejected application for {$application['full_name']} - 4 month ban applied",
                        $secretary_name, 'job_applicants', $application_id);
        
        // Send rejection email (optional)
        $applicant_email = $application['applicant_email'] ?? '';
        if ($applicant_email && function_exists('sendRejectionEmail')) {
            try {
                sendRejectionEmail(
                    $applicant_email,
                    $application['first_name'] ?? $application['full_name'],
                    $reason
                );
            } catch (Exception $e) {
                error_log("Email notification failed: " . $e->getMessage());
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Application rejected successfully. Applicant banned from applying for 4 months.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reject application']);
    }
}

/**
 * Log workflow history
 */
function logWorkflowHistory($conn, $application_id, $from_stage, $to_stage, $action_by_id, $action_by_role, $action_type, $notes) {
    try {
        // Check if workflow_history table exists
        $check = $conn->query("SHOW TABLES LIKE 'workflow_history'");
        if ($check->num_rows == 0) {
            return; // Table doesn't exist, skip logging
        }
        
        $stmt = $conn->prepare("INSERT INTO workflow_history 
                               (application_id, from_stage, to_stage, action_by_id, action_by_role, action_type, notes)
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ississs", $application_id, $from_stage, $to_stage, $action_by_id, $action_by_role, $action_type, $notes);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Workflow history error: " . $e->getMessage());
    }
}

/**
 * Log admin activity
 */
function logAdminActivity($conn, $activity_type, $description, $user_name, $related_table, $related_id) {
    try {
        // Check if admin_activity table exists
        $check = $conn->query("SHOW TABLES LIKE 'admin_activity'");
        if ($check->num_rows == 0) {
            return; // Table doesn't exist, skip logging
        }
        
        $stmt = $conn->prepare("INSERT INTO admin_activity (activity_type, description, user_name, related_table, related_id)
                               VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssssi", $activity_type, $description, $user_name, $related_table, $related_id);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Admin activity error: " . $e->getMessage());
    }
}

$conn->close();
