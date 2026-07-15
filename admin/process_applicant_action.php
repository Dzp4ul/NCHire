<?php
session_start();
header('Content-Type: application/json');

// Include email helper and admin notification helper
require_once __DIR__ . '/email_helper.php';
require_once __DIR__ . '/admin_notification_helper.php';

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Get admin info from session
$admin_name = $_SESSION['admin_name'] ?? 'Unknown Admin';
$admin_id = $_SESSION['admin_id'] ?? null;

$action = $_POST['action'] ?? '';
$applicant_id = $_POST['applicant_id'] ?? '';

if (empty($action) || empty($applicant_id)) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

try {
    switch ($action) {
        case 'schedule_interview':
            $interview_date = $_POST['interview_date'] ?? '';
            $interview_time = $_POST['interview_time'] ?? '';
            $interview_location = $_POST['interview_location'] ?? '';
            $interview_room = $_POST['interview_room'] ?? '';
            $interview_notes = $_POST['interview_notes'] ?? '';
            
            if (empty($interview_date) || empty($interview_time)) {
                echo json_encode(['success' => false, 'error' => 'Interview date and time are required']);
                exit;
            }
            
            if (empty($interview_location) || empty($interview_room)) {
                echo json_encode(['success' => false, 'error' => 'Interview location and room are required']);
                exit;
            }
            
            // Combine date and time
            $interview_datetime = $interview_date . ' ' . $interview_time;
            
            // Validate that the date/time is not in the past
            $selected_timestamp = strtotime($interview_datetime);
            $current_timestamp = time();
            
            if ($selected_timestamp < $current_timestamp) {
                echo json_encode(['success' => false, 'error' => 'Please select a future date and time for the interview']);
                exit;
            }
            
            // Validate time is within business hours (8:00 AM - 4:00 PM)
            $time_parts = explode(':', $interview_time);
            $hour = (int)$time_parts[0];
            $minute = (int)$time_parts[1];
            $time_in_minutes = $hour * 60 + $minute;
            
            if ($time_in_minutes < 480 || $time_in_minutes > 960) { // 8:00 AM = 480 min, 4:00 PM = 960 min
                echo json_encode(['success' => false, 'error' => 'Please select a time between 8:00 AM and 4:00 PM']);
                exit;
            }
            
            // Update applicant record
            $stmt = $conn->prepare("UPDATE job_applicants SET 
                                    status = 'Interview Scheduled',
                                    workflow_stage = 'interview_scheduled',
                                    interview_date = ?,
                                    interview_location = ?,
                                    interview_room = ?,
                                    interview_notes = ?
                                    WHERE id = ?");
            $stmt->bind_param("ssssi", $interview_datetime, $interview_location, $interview_room, $interview_notes, $applicant_id);
            
            if ($stmt->execute()) {
                // Simple notification system using email matching
                $applicant_stmt = $conn->prepare("SELECT applicant_email, full_name, position FROM job_applicants WHERE id = ?");
                $applicant_stmt->bind_param("i", $applicant_id);
                $applicant_stmt->execute();
                $applicant_result = $applicant_stmt->get_result();
                
                if ($applicant_result->num_rows > 0) {
                    $applicant_data = $applicant_result->fetch_assoc();
                    $applicant_email = $applicant_data['applicant_email'];
                    $applicant_name = $applicant_data['full_name'];
                    $position = $applicant_data['position'];
                    
                    // Create notification using email as identifier
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_email, user_name, title, message, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $title = "Interview Scheduled";
                    $message = "Your interview has been scheduled for " . date('F j, Y \\a\\t g:i A', strtotime($interview_datetime)) . " at " . $interview_location . ", " . $interview_room . ". " . ($interview_notes ? "Notes: " . $interview_notes : "");
                    $type = "info";
                    $notif_stmt->bind_param("sssss", $applicant_email, $applicant_name, $title, $message, $type);
                    
                    if ($notif_stmt->execute()) {
                        error_log("Interview notification created for email: $applicant_email");
                    } else {
                        error_log("Failed to create interview notification: " . $notif_stmt->error);
                    }
                    
                    // Send email notification to applicant
                    sendInterviewScheduleEmail($applicant_email, $applicant_name, $interview_datetime, $interview_location, $interview_room, $interview_notes);
                    
                    // Create admin notification for all admins
                    $admin_title = "Interview Scheduled";
                    $admin_message = "Interview scheduled for " . $applicant_name . " on " . date('F j, Y \\a\\t g:i A', strtotime($interview_datetime)) . " at " . $interview_location . ", " . $interview_room;
                    createAdminNotification($conn, $admin_title, $admin_message, 'info', 'interview_scheduled', $applicant_id, $applicant_name, true);
                    
                    // Log admin activity
                    $activity_stmt = $conn->prepare("INSERT INTO admin_activity (activity_type, description, user_name, related_table, related_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $activity_type = "interview_scheduled";
                    $activity_desc = "$admin_name scheduled an interview for $applicant_name ($position) on " . date('M j, Y \\a\\t g:i A', strtotime($interview_datetime));
                    $related_table = "job_applicants";
                    $activity_stmt->bind_param("ssssi", $activity_type, $activity_desc, $admin_name, $related_table, $applicant_id);
                    $activity_stmt->execute();
                }
                
                echo json_encode(['success' => true, 'message' => 'Interview scheduled successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to schedule interview']);
            }
            break;
            
        case 'request_resubmission':
            $documents = $_POST['documents'] ?? '';
            $notes = $_POST['notes'] ?? '';
            
            if (empty($documents) || empty($notes)) {
                echo json_encode(['success' => false, 'error' => 'Documents and notes are required']);
                exit;
            }
            
            // Update applicant record
            $stmt = $conn->prepare("UPDATE job_applicants SET 
                                    status = 'Resubmission Required',
                                    resubmission_documents = ?,
                                    resubmission_notes = ?
                                    WHERE id = ?");
            $stmt->bind_param("ssi", $documents, $notes, $applicant_id);
            
            if ($stmt->execute()) {
                // Simple notification system using email matching
                $applicant_stmt = $conn->prepare("SELECT applicant_email, full_name, position FROM job_applicants WHERE id = ?");
                $applicant_stmt->bind_param("i", $applicant_id);
                $applicant_stmt->execute();
                $applicant_result = $applicant_stmt->get_result();
                
                if ($applicant_result->num_rows > 0) {
                    $applicant_data = $applicant_result->fetch_assoc();
                    $applicant_email = $applicant_data['applicant_email'];
                    $applicant_name = $applicant_data['full_name'];
                    $position = $applicant_data['position'];
                    
                    // Create notification using email as identifier
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_email, user_name, title, message, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $title = "Document Resubmission Required";
                    $message = "Please resubmit the following documents: " . $documents . ". Reason: " . $notes;
                    $type = "warning";
                    $notif_stmt->bind_param("sssss", $applicant_email, $applicant_name, $title, $message, $type);
                    
                    if ($notif_stmt->execute()) {
                        error_log("Resubmission notification created for email: $applicant_email");
                    } else {
                        error_log("Failed to create resubmission notification: " . $notif_stmt->error);
                    }
                    
                    // Send email notification
                    sendResubmissionEmail($applicant_email, $applicant_name, $documents, $notes);
                    
                    // Log admin activity
                    $activity_stmt = $conn->prepare("INSERT INTO admin_activity (activity_type, description, user_name, related_table, related_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $activity_type = "resubmission_requested";
                    $activity_desc = "$admin_name requested resubmission from $applicant_name ($position)";
                    $related_table = "job_applicants";
                    $activity_stmt->bind_param("ssssi", $activity_type, $activity_desc, $admin_name, $related_table, $applicant_id);
                    $activity_stmt->execute();
                }
                
                echo json_encode(['success' => true, 'message' => 'Resubmission request sent successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to request resubmission']);
            }
            break;
            
        case 'reject_application':
            $rejection_reason = $_POST['rejection_reason'] ?? '';
            
            if (empty($rejection_reason)) {
                echo json_encode(['success' => false, 'error' => 'Rejection reason is required']);
                exit;
            }
            
            // Get full applicant data including user_id for ban
            $applicant_data_stmt = $conn->prepare("SELECT user_id, applicant_email, full_name, position FROM job_applicants WHERE id = ?");
            $applicant_data_stmt->bind_param("i", $applicant_id);
            $applicant_data_stmt->execute();
            $applicant_result = $applicant_data_stmt->get_result();
            $applicant_data = $applicant_result->fetch_assoc();
            $user_id = $applicant_data['user_id'] ?? null;
            $applicant_data_stmt->close();
            
            // Calculate ban expiration date (4 months from now)
            $ban_expires = date('Y-m-d H:i:s', strtotime('+4 months'));
            
            // Update applicant record and set rejected_date to archive
            $stmt = $conn->prepare("UPDATE job_applicants SET 
                                    status = 'Rejected',
                                    workflow_stage = 'rejected',
                                    rejection_reason = ?,
                                    rejected_date = NOW()
                                    WHERE id = ?");
            $stmt->bind_param("si", $rejection_reason, $applicant_id);
            
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
                    $ban_by = "Department Head: $admin_name";
                    $ban_reason = "Application rejected by Department Head. Reason: $rejection_reason";
                    $ban_stmt->bind_param("sssi", $ban_expires, $ban_reason, $ban_by, $user_id);
                    $ban_stmt->execute();
                    $ban_stmt->close();
                    
                    // Log ban in application_bans table for audit trail
                    $log_stmt = $conn->prepare("INSERT INTO application_bans 
                                               (applicant_id, applicant_email, application_id, banned_date, 
                                                ban_expires, ban_reason, banned_by_id, banned_by_name, 
                                                banned_by_role, rejection_reason, position_applied)
                                               VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, 'Department Head', ?, ?)");
                    $position = $applicant_data['position'] ?? 'Unknown Position';
                    $log_stmt->bind_param("isisissss", $user_id, $applicant_data['applicant_email'], 
                                         $applicant_id, $ban_expires, $ban_reason, 
                                         $admin_id, $admin_name, $rejection_reason, $position);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                
                // Simple notification system using email matching
                if ($applicant_data) {
                    $applicant_email = $applicant_data['applicant_email'];
                    $applicant_name = $applicant_data['full_name'];
                    $position = $applicant_data['position'];
                    
                    // Create notification using email as identifier
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_email, user_name, title, message, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $title = "Application Rejected";
                    $message = "Unfortunately, your application has been rejected. Reason: " . $rejection_reason . ". You cannot apply for new positions for 4 months.";
                    $type = "error";
                    $notif_stmt->bind_param("sssss", $applicant_email, $applicant_name, $title, $message, $type);
                    
                    if ($notif_stmt->execute()) {
                        error_log("Rejection notification created for email: $applicant_email");
                    } else {
                        error_log("Failed to create rejection notification: " . $notif_stmt->error);
                    }
                    
                    // Send email notification
                    sendRejectionEmail($applicant_email, $applicant_name, $rejection_reason);
                    
                    // Log admin activity
                    $activity_stmt = $conn->prepare("INSERT INTO admin_activity (activity_type, description, user_name, related_table, related_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $activity_type = "applicant_rejected";
                    $activity_desc = "$admin_name rejected application from $applicant_name for $position - 4 month ban applied";
                    $related_table = "job_applicants";
                    $activity_stmt->bind_param("ssssi", $activity_type, $activity_desc, $admin_name, $related_table, $applicant_id);
                    $activity_stmt->execute();
                }
                
                echo json_encode(['success' => true, 'message' => 'Application rejected successfully. Applicant banned from applying for 4 months.']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to reject application']);
            }
            break;
            
        case 'schedule_demo':
            // Reuse interview fields for demo scheduling
            $demo_date = $_POST['interview_date'] ?? '';
            $demo_time = $_POST['interview_time'] ?? '';
            $demo_location = $_POST['interview_location'] ?? '';
            $demo_room = $_POST['interview_room'] ?? '';
            $demo_notes = $_POST['interview_notes'] ?? '';
            
            if (empty($demo_date) || empty($demo_time)) {
                echo json_encode(['success' => false, 'error' => 'Demo date and time are required']);
                exit;
            }
            
            if (empty($demo_location) || empty($demo_room)) {
                echo json_encode(['success' => false, 'error' => 'Demo location and room are required']);
                exit;
            }
            
            // Combine date and time
            $demo_datetime = $demo_date . ' ' . $demo_time;
            
            // Validate that the date/time is not in the past
            $selected_timestamp = strtotime($demo_datetime);
            $current_timestamp = time();
            
            if ($selected_timestamp < $current_timestamp) {
                echo json_encode(['success' => false, 'error' => 'Please select a future date and time for the demo teaching']);
                exit;
            }
            
            // Validate time is within business hours (8:00 AM - 4:00 PM)
            $time_parts = explode(':', $demo_time);
            $hour = (int)$time_parts[0];
            $minute = (int)$time_parts[1];
            $time_in_minutes = $hour * 60 + $minute;
            
            if ($time_in_minutes < 480 || $time_in_minutes > 960) { // 8:00 AM = 480 min, 4:00 PM = 960 min
                echo json_encode(['success' => false, 'error' => 'Please select a time between 8:00 AM and 4:00 PM']);
                exit;
            }
            
            // Update applicant record
            $stmt = $conn->prepare("UPDATE job_applicants SET 
                                    status = 'Demo Scheduled',
                                    workflow_stage = 'demo_scheduled',
                                    demo_date = ?,
                                    demo_location = ?,
                                    demo_room = ?,
                                    demo_notes = ?
                                    WHERE id = ?");
            $stmt->bind_param("ssssi", $demo_datetime, $demo_location, $demo_room, $demo_notes, $applicant_id);
            
            if ($stmt->execute()) {
                // Create notification
                $applicant_stmt = $conn->prepare("SELECT applicant_email, full_name, position FROM job_applicants WHERE id = ?");
                $applicant_stmt->bind_param("i", $applicant_id);
                $applicant_stmt->execute();
                $applicant_result = $applicant_stmt->get_result();
                
                if ($applicant_result->num_rows > 0) {
                    $applicant_data = $applicant_result->fetch_assoc();
                    $applicant_email = $applicant_data['applicant_email'];
                    $applicant_name = $applicant_data['full_name'];
                    $position = $applicant_data['position'];
                    
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_email, user_name, title, message, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $title = "Demo Teaching Scheduled";
                    $message = "Your demo teaching has been scheduled for " . date('F j, Y \\a\\t g:i A', strtotime($demo_datetime)) . " at " . $demo_location . ", " . $demo_room . ". " . ($demo_notes ? "Notes: " . $demo_notes : "");
                    $type = "info";
                    $notif_stmt->bind_param("sssss", $applicant_email, $applicant_name, $title, $message, $type);
                    $notif_stmt->execute();
                    
                    // Send email notification to applicant
                    sendDemoScheduleEmail($applicant_email, $applicant_name, $demo_datetime, $demo_location, $demo_room, $demo_notes);
                    
                    // Create admin notification for all admins
                    $admin_title = "Demo Teaching Scheduled";
                    $admin_message = "Demo teaching scheduled for " . $applicant_name . " on " . date('F j, Y \\a\\t g:i A', strtotime($demo_datetime)) . " at " . $demo_location . ", " . $demo_room;
                    createAdminNotification($conn, $admin_title, $admin_message, 'info', 'demo_scheduled', $applicant_id, $applicant_name, true);
                    
                    // Log admin activity
                    $activity_stmt = $conn->prepare("INSERT INTO admin_activity (activity_type, description, user_name, related_table, related_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $activity_type = "demo_scheduled";
                    $activity_desc = "$admin_name scheduled demo teaching for $applicant_name ($position) on " . date('M j, Y \\a\\t g:i A', strtotime($demo_datetime));
                    $related_table = "job_applicants";
                    $activity_stmt->bind_param("ssssi", $activity_type, $activity_desc, $admin_name, $related_table, $applicant_id);
                    $activity_stmt->execute();
                }
                
                echo json_encode(['success' => true, 'message' => 'Demo scheduled successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to schedule demo']);
            }
            break;
            
        case 'approve_interview':
            $interview_notes = $_POST['interview_notes'] ?? '';
            
            // Update applicant record to Interview Passed status
            $stmt = $conn->prepare("UPDATE job_applicants SET 
                                    status = 'Interview Passed',
                                    workflow_stage = 'interview_completed',
                                    interview_notes = ?
                                    WHERE id = ?");
            $stmt->bind_param("si", $interview_notes, $applicant_id);
            
            if ($stmt->execute()) {
                // Create notification
                $applicant_stmt = $conn->prepare("SELECT applicant_email, full_name, position FROM job_applicants WHERE id = ?");
                $applicant_stmt->bind_param("i", $applicant_id);
                $applicant_stmt->execute();
                $applicant_result = $applicant_stmt->get_result();
                
                if ($applicant_result->num_rows > 0) {
                    $applicant_data = $applicant_result->fetch_assoc();
                    $applicant_email = $applicant_data['applicant_email'];
                    $applicant_name = $applicant_data['full_name'];
                    $position = $applicant_data['position'];
                    
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_email, user_name, title, message, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $title = "Interview Approved";
                    $message = "Congratulations! You have successfully passed the interview. " . ($interview_notes ? "Feedback: " . $interview_notes : "Please wait for the demo teaching schedule.");
                    $type = "success";
                    $notif_stmt->bind_param("sssss", $applicant_email, $applicant_name, $title, $message, $type);
                    $notif_stmt->execute();
                    
                    // Send email notification
                    sendInterviewApprovedEmail($applicant_email, $applicant_name, $interview_notes);
                    
                    // Log admin activity
                    $activity_stmt = $conn->prepare("INSERT INTO admin_activity (activity_type, description, user_name, related_table, related_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $activity_type = "interview_approved";
                    $activity_desc = "$admin_name approved interview for $applicant_name ($position)";
                    $related_table = "job_applicants";
                    $activity_stmt->bind_param("ssssi", $activity_type, $activity_desc, $admin_name, $related_table, $applicant_id);
                    $activity_stmt->execute();
                }
                
                echo json_encode(['success' => true, 'message' => 'Interview approved successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to approve interview']);
            }
            break;
            
        case 'approve_demo':
            $demo_notes = $_POST['demo_notes'] ?? '';
            
            // Update applicant record to Demo Passed status
            $stmt = $conn->prepare("UPDATE job_applicants SET 
                                    status = 'Demo Passed',
                                    workflow_stage = 'demo_completed',
                                    demo_notes = ?
                                    WHERE id = ?");
            $stmt->bind_param("si", $demo_notes, $applicant_id);
            
            if ($stmt->execute()) {
                // Create notification
                $applicant_stmt = $conn->prepare("SELECT applicant_email, full_name, position FROM job_applicants WHERE id = ?");
                $applicant_stmt->bind_param("i", $applicant_id);
                $applicant_stmt->execute();
                $applicant_result = $applicant_stmt->get_result();
                
                if ($applicant_result->num_rows > 0) {
                    $applicant_data = $applicant_result->fetch_assoc();
                    $applicant_email = $applicant_data['applicant_email'];
                    $applicant_name = $applicant_data['full_name'];
                    $position = $applicant_data['position'];
                    
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_email, user_name, title, message, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $title = "Demo Teaching Approved";
                    $message = "Congratulations! You have successfully passed the demo teaching. " . ($demo_notes ? "Feedback: " . $demo_notes : "Please wait for further instructions.");
                    $type = "success";
                    $notif_stmt->bind_param("sssss", $applicant_email, $applicant_name, $title, $message, $type);
                    $notif_stmt->execute();
                    
                    // Send email notification
                    sendDemoApprovedEmail($applicant_email, $applicant_name, $demo_notes);
                    
                    // Log admin activity
                    $activity_stmt = $conn->prepare("INSERT INTO admin_activity (activity_type, description, user_name, related_table, related_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $activity_type = "demo_approved";
                    $activity_desc = "$admin_name approved demo teaching for $applicant_name ($position)";
                    $related_table = "job_applicants";
                    $activity_stmt->bind_param("ssssi", $activity_type, $activity_desc, $admin_name, $related_table, $applicant_id);
                    $activity_stmt->execute();
                }
                
                echo json_encode(['success' => true, 'message' => 'Demo approved successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to approve demo']);
            }
            break;
            
        case 'schedule_psych':
        case 'schedule_psych_exam':
            // Reuse interview fields for psych exam scheduling
            $psych_exam_date = $_POST['interview_date'] ?? '';
            $psych_exam_time = $_POST['interview_time'] ?? '';
            $psych_exam_notes = $_POST['interview_notes'] ?? '';
            
            if (empty($psych_exam_date) || empty($psych_exam_time)) {
                echo json_encode(['success' => false, 'error' => 'Psychological exam date and time are required']);
                exit;
            }
            
            // Combine date and time
            $psych_exam_datetime = $psych_exam_date . ' ' . $psych_exam_time;
            
            // Update applicant record
            $stmt = $conn->prepare("UPDATE job_applicants SET 
                                    status = 'Psychological Exam',
                                    psych_exam_date = ?
                                    WHERE id = ?");
            $stmt->bind_param("si", $psych_exam_datetime, $applicant_id);
            
            if ($stmt->execute()) {
                // Create notification
                $applicant_stmt = $conn->prepare("SELECT applicant_email, full_name, position FROM job_applicants WHERE id = ?");
                $applicant_stmt->bind_param("i", $applicant_id);
                $applicant_stmt->execute();
                $applicant_result = $applicant_stmt->get_result();
                
                if ($applicant_result->num_rows > 0) {
                    $applicant_data = $applicant_result->fetch_assoc();
                    $applicant_email = $applicant_data['applicant_email'];
                    $applicant_name = $applicant_data['full_name'];
                    $position = $applicant_data['position'];
                    
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_email, user_name, title, message, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $title = "Psychological Exam Scheduled";
                    $message = "Your psychological examination has been scheduled for " . date('F j, Y \\a\\t g:i A', strtotime($psych_exam_datetime)) . ". Please upload your receipt after taking the exam. " . ($psych_exam_notes ? "Notes: " . $psych_exam_notes : "");
                    $type = "info";
                    $notif_stmt->bind_param("sssss", $applicant_email, $applicant_name, $title, $message, $type);
                    $notif_stmt->execute();
                    
                    // Send email notification
                    sendPsychExamScheduleEmail($applicant_email, $applicant_name, $psych_exam_datetime, $psych_exam_notes);
                    
                    // Log admin activity
                    $activity_stmt = $conn->prepare("INSERT INTO admin_activity (activity_type, description, user_name, related_table, related_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $activity_type = "psych_exam_scheduled";
                    $activity_desc = "$admin_name scheduled psychological exam for $applicant_name ($position) on " . date('M j, Y \\a\\t g:i A', strtotime($psych_exam_datetime));
                    $related_table = "job_applicants";
                    $activity_stmt->bind_param("ssssi", $activity_type, $activity_desc, $admin_name, $related_table, $applicant_id);
                    $activity_stmt->execute();
                }
                
                echo json_encode(['success' => true, 'message' => 'Psychological exam scheduled successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to schedule psychological exam']);
            }
            break;
            
        case 'mark_initially_hired':
            $initially_hired_notes = $_POST['initially_hired_notes'] ?? '';
            
            // Update applicant record - mark as Hired (final status)
            $stmt = $conn->prepare("UPDATE job_applicants SET 
                                    status = 'Hired',
                                    workflow_stage = 'hired',
                                    hired_date = NOW(),
                                    hire_notes = ?
                                    WHERE id = ?");
            $stmt->bind_param("si", $initially_hired_notes, $applicant_id);
            
            if ($stmt->execute()) {
                // Get applicant info
                $applicant_stmt = $conn->prepare("SELECT applicant_email, full_name, position FROM job_applicants WHERE id = ?");
                $applicant_stmt->bind_param("i", $applicant_id);
                $applicant_stmt->execute();
                $applicant_result = $applicant_stmt->get_result();
                
                if ($applicant_result->num_rows > 0) {
                    $applicant_data = $applicant_result->fetch_assoc();
                    $applicant_email = $applicant_data['applicant_email'];
                    $applicant_name = $applicant_data['full_name'];
                    $position = $applicant_data['position'];
                    
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_email, user_name, title, message, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $title = "Congratulations! You're Hired!";
                    $message = "Congratulations! We are pleased to inform you that you have been hired for the position. " . ($initially_hired_notes ? "Notes: " . $initially_hired_notes : "Please wait for onboarding instructions.");
                    $type = "success";
                    $notif_stmt->bind_param("sssss", $applicant_email, $applicant_name, $title, $message, $type);
                    $notif_stmt->execute();
                    
                    // Send email notification
                    if (function_exists('sendHiredEmail')) {
                        sendHiredEmail($applicant_email, $applicant_name, $initially_hired_notes);
                    }
                    
                    // Log admin activity
                    $activity_stmt = $conn->prepare("INSERT INTO admin_activity (activity_type, description, user_name, related_table, related_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $activity_type = "applicant_hired";
                    $activity_desc = "$admin_name hired $applicant_name for $position";
                    $related_table = "job_applicants";
                    $activity_stmt->bind_param("ssssi", $activity_type, $activity_desc, $admin_name, $related_table, $applicant_id);
                    $activity_stmt->execute();
                }
                
                echo json_encode(['success' => true, 'message' => 'Applicant hired successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to hire applicant']);
            }
            break;
            
        case 'mark_permanently_hired':
            $hired_notes = $_POST['hired_notes'] ?? '';
            
            // Update applicant record to final Hired status
            $stmt = $conn->prepare("UPDATE job_applicants SET 
                                    status = 'Hired',
                                    workflow_stage = 'hired',
                                    hired_date = NOW(),
                                    hired_notes = ?
                                    WHERE id = ?");
            $stmt->bind_param("si", $hired_notes, $applicant_id);
            
            if ($stmt->execute()) {
                // Create notification
                $applicant_stmt = $conn->prepare("SELECT applicant_email, full_name, position FROM job_applicants WHERE id = ?");
                $applicant_stmt->bind_param("i", $applicant_id);
                $applicant_stmt->execute();
                $applicant_result = $applicant_stmt->get_result();
                
                if ($applicant_result->num_rows > 0) {
                    $applicant_data = $applicant_result->fetch_assoc();
                    $applicant_email = $applicant_data['applicant_email'];
                    $applicant_name = $applicant_data['full_name'];
                    $position = $applicant_data['position'];
                    
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_email, user_name, title, message, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $title = "Permanently Hired - Welcome Aboard!";
                    $message = "Congratulations! You have been permanently hired as a regular employee. Welcome to the team! " . ($hired_notes ? "Details: " . $hired_notes : "Please await onboarding instructions.");
                    $type = "success";
                    $notif_stmt->bind_param("sssss", $applicant_email, $applicant_name, $title, $message, $type);
                    $notif_stmt->execute();
                    
                    // Send email notification
                    sendPermanentlyHiredEmail($applicant_email, $applicant_name, $hired_notes);
                    
                    // Log admin activity
                    $activity_stmt = $conn->prepare("INSERT INTO admin_activity (activity_type, description, user_name, related_table, related_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $activity_type = "applicant_hired";
                    $activity_desc = "$admin_name permanently hired $applicant_name for $position";
                    $related_table = "job_applicants";
                    $activity_stmt->bind_param("ssssi", $activity_type, $activity_desc, $admin_name, $related_table, $applicant_id);
                    $activity_stmt->execute();
                }
                
                echo json_encode(['success' => true, 'message' => 'Applicant permanently hired successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to mark as permanently hired']);
            }
            break;
            
        case 'hire_applicant':
            $hire_notes = $_POST['hire_notes'] ?? '';
            
            // Update applicant record
            $stmt = $conn->prepare("UPDATE job_applicants SET 
                                    status = 'Hired',
                                    workflow_stage = 'hired',
                                    hire_notes = ?
                                    WHERE id = ?");
            $stmt->bind_param("si", $hire_notes, $applicant_id);
            
            if ($stmt->execute()) {
                // Simple notification system using email matching
                $applicant_stmt = $conn->prepare("SELECT applicant_email, full_name FROM job_applicants WHERE id = ?");
                $applicant_stmt->bind_param("i", $applicant_id);
                $applicant_stmt->execute();
                $applicant_result = $applicant_stmt->get_result();
                
                if ($applicant_result->num_rows > 0) {
                    $applicant_data = $applicant_result->fetch_assoc();
                    $applicant_email = $applicant_data['applicant_email'];
                    $applicant_name = $applicant_data['full_name'];
                    
                    // Create notification using email as identifier
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_email, user_name, title, message, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $title = "Congratulations! You're Hired!";
                    $message = "Congratulations! We are pleased to inform you that you have been selected for the position. " . ($hire_notes ? "Additional information: " . $hire_notes : "Please wait for further instructions regarding your onboarding process.");
                    $type = "success";
                    $notif_stmt->bind_param("sssss", $applicant_email, $applicant_name, $title, $message, $type);
                    
                    if ($notif_stmt->execute()) {
                        error_log("Hire notification created for email: $applicant_email");
                    } else {
                        error_log("Failed to create hire notification: " . $notif_stmt->error);
                    }
                    
                    // Send email notification to applicant
                    sendHiredEmail($applicant_email, $applicant_name, $hire_notes);
                    
                    // Create admin notification for all admins
                    $admin_title = "Applicant Hired";
                    $admin_message = $applicant_name . " has been hired!";
                    createAdminNotification($conn, $admin_title, $admin_message, 'success', 'hired', $applicant_id, $applicant_name, true);
                }
                
                echo json_encode(['success' => true, 'message' => 'Applicant hired successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to hire applicant']);
            }
            break;
            
        case 'reschedule_interview':
            $interview_date = $_POST['interview_date'] ?? '';
            $interview_time = $_POST['interview_time'] ?? '';
            $interview_location = $_POST['interview_location'] ?? '';
            $interview_room = $_POST['interview_room'] ?? '';
            $interview_notes = $_POST['interview_notes'] ?? '';
            
            if (empty($interview_date) || empty($interview_time)) {
                echo json_encode(['success' => false, 'error' => 'Interview date and time are required']);
                exit;
            }
            
            if (empty($interview_location) || empty($interview_room)) {
                echo json_encode(['success' => false, 'error' => 'Interview location and room are required']);
                exit;
            }
            
            // Combine date and time
            $interview_datetime = $interview_date . ' ' . $interview_time;
            
            // Validate that the date/time is not in the past
            $selected_timestamp = strtotime($interview_datetime);
            $current_timestamp = time();
            
            if ($selected_timestamp < $current_timestamp) {
                echo json_encode(['success' => false, 'error' => 'Please select a future date and time for the new interview schedule']);
                exit;
            }
            
            // Validate time is within business hours (8:00 AM - 4:00 PM)
            $time_parts = explode(':', $interview_time);
            $hour = (int)$time_parts[0];
            $minute = (int)$time_parts[1];
            $time_in_minutes = $hour * 60 + $minute;
            
            if ($time_in_minutes < 480 || $time_in_minutes > 960) { // 8:00 AM = 480 min, 4:00 PM = 960 min
                echo json_encode(['success' => false, 'error' => 'Please select a time between 8:00 AM and 4:00 PM']);
                exit;
            }
            
            // Update applicant record - status remains "Interview Scheduled"
            $stmt = $conn->prepare("UPDATE job_applicants SET 
                                    interview_date = ?,
                                    interview_location = ?,
                                    interview_room = ?,
                                    interview_notes = ?
                                    WHERE id = ?");
            $stmt->bind_param("ssssi", $interview_datetime, $interview_location, $interview_room, $interview_notes, $applicant_id);
            
            if ($stmt->execute()) {
                // Get applicant info for notification
                $applicant_stmt = $conn->prepare("SELECT applicant_email, full_name FROM job_applicants WHERE id = ?");
                $applicant_stmt->bind_param("i", $applicant_id);
                $applicant_stmt->execute();
                $applicant_result = $applicant_stmt->get_result();
                
                if ($applicant_result->num_rows > 0) {
                    $applicant_data = $applicant_result->fetch_assoc();
                    $applicant_email = $applicant_data['applicant_email'];
                    $applicant_name = $applicant_data['full_name'];
                    
                    // Create notification
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_email, user_name, title, message, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $title = "Interview Rescheduled";
                    $message = "Your interview has been rescheduled to " . date('F j, Y \\a\\t g:i A', strtotime($interview_datetime)) . " at " . $interview_location . ", " . $interview_room . ". " . ($interview_notes ? "Reason: " . $interview_notes : "");
                    $type = "warning";
                    $notif_stmt->bind_param("sssss", $applicant_email, $applicant_name, $title, $message, $type);
                    $notif_stmt->execute();
                    
                    // Send email notification to applicant
                    sendInterviewRescheduledEmail($applicant_email, $applicant_name, $interview_datetime, $interview_location, $interview_room, $interview_notes);
                    
                    // Create admin notification for all admins
                    $admin_title = "Interview Rescheduled";
                    $admin_message = "Interview rescheduled for " . $applicant_name . " to " . date('F j, Y \\a\\t g:i A', strtotime($interview_datetime)) . " at " . $interview_location . ", " . $interview_room;
                    createAdminNotification($conn, $admin_title, $admin_message, 'warning', 'interview_rescheduled', $applicant_id, $applicant_name, true);
                }
                
                echo json_encode(['success' => true, 'message' => 'Interview rescheduled successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to reschedule interview']);
            }
            break;
            
        case 'reschedule_demo':
            $demo_date = $_POST['demo_date'] ?? '';
            $demo_time = $_POST['demo_time'] ?? '';
            $demo_location = $_POST['demo_location'] ?? '';
            $demo_room = $_POST['demo_room'] ?? '';
            $demo_notes = $_POST['demo_notes'] ?? '';
            
            if (empty($demo_date) || empty($demo_time)) {
                echo json_encode(['success' => false, 'error' => 'Demo date and time are required']);
                exit;
            }
            
            if (empty($demo_location) || empty($demo_room)) {
                echo json_encode(['success' => false, 'error' => 'Demo location and room are required']);
                exit;
            }
            
            // Combine date and time
            $demo_datetime = $demo_date . ' ' . $demo_time;
            
            // Validate that the date/time is not in the past
            $selected_timestamp = strtotime($demo_datetime);
            $current_timestamp = time();
            
            if ($selected_timestamp < $current_timestamp) {
                echo json_encode(['success' => false, 'error' => 'Please select a future date and time for the new demo schedule']);
                exit;
            }
            
            // Validate time is within business hours (8:00 AM - 4:00 PM)
            $time_parts = explode(':', $demo_time);
            $hour = (int)$time_parts[0];
            $minute = (int)$time_parts[1];
            $time_in_minutes = $hour * 60 + $minute;
            
            if ($time_in_minutes < 480 || $time_in_minutes > 960) { // 8:00 AM = 480 min, 4:00 PM = 960 min
                echo json_encode(['success' => false, 'error' => 'Please select a time between 8:00 AM and 4:00 PM']);
                exit;
            }
            
            // Update applicant record - status remains "Demo Scheduled"
            $stmt = $conn->prepare("UPDATE job_applicants SET 
                                    demo_date = ?,
                                    demo_location = ?,
                                    demo_room = ?
                                    WHERE id = ?");
            $stmt->bind_param("sssi", $demo_datetime, $demo_location, $demo_room, $applicant_id);
            
            if ($stmt->execute()) {
                // Get applicant info for notification
                $applicant_stmt = $conn->prepare("SELECT applicant_email, full_name FROM job_applicants WHERE id = ?");
                $applicant_stmt->bind_param("i", $applicant_id);
                $applicant_stmt->execute();
                $applicant_result = $applicant_stmt->get_result();
                
                if ($applicant_result->num_rows > 0) {
                    $applicant_data = $applicant_result->fetch_assoc();
                    $applicant_email = $applicant_data['applicant_email'];
                    $applicant_name = $applicant_data['full_name'];
                    
                    // Create notification
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_email, user_name, title, message, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $title = "Demo Teaching Rescheduled";
                    $message = "Your demo teaching has been rescheduled to " . date('F j, Y \\a\\t g:i A', strtotime($demo_datetime)) . " at " . $demo_location . ", " . $demo_room . ". " . ($demo_notes ? "Reason: " . $demo_notes : "");
                    $type = "warning";
                    $notif_stmt->bind_param("sssss", $applicant_email, $applicant_name, $title, $message, $type);
                    $notif_stmt->execute();
                    
                    // Send email notification to applicant
                    sendDemoRescheduledEmail($applicant_email, $applicant_name, $demo_datetime, $demo_location, $demo_room, $demo_notes);
                    
                    // Create admin notification for all admins
                    $admin_title = "Demo Rescheduled";
                    $admin_message = "Demo teaching rescheduled for " . $applicant_name . " to " . date('F j, Y \\a\\t g:i A', strtotime($demo_datetime)) . " at " . $demo_location . ", " . $demo_room;
                    createAdminNotification($conn, $admin_title, $admin_message, 'warning', 'demo_rescheduled', $applicant_id, $applicant_name, true);
                }
                
                echo json_encode(['success' => true, 'message' => 'Demo rescheduled successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to reschedule demo']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>
