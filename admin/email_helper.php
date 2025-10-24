<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Send email notification to applicant
 * 
 * @param string $to_email Recipient email address
 * @param string $to_name Recipient name
 * @param string $subject Email subject
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $type Notification type (success, info, warning, danger)
 * @return bool True on success, false on failure
 */
function sendEmailNotification($to_email, $to_name, $subject, $title, $message, $type = 'info') {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'manansalajohnpaul120@gmail.com';
        $mail->Password   = 'dcuv npdb mmnz lyfa';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        // Recipients
        $mail->setFrom('no-reply@nchire.local', 'NCHire - Norzagaray College');
        $mail->addAddress($to_email, $to_name);
        
        // Embed the logo image
        $logoPath = __DIR__ . '/../assets/images/image-removebg-preview (1).png';
        if (file_exists($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'college_logo', 'logo.png', 'base64', 'image/png');
            error_log("Logo embedded successfully from: $logoPath");
        } else {
            error_log("Logo file not found at: $logoPath");
        }

        // Email styling
        $typeColors = [
            'success' => '#10b981',
            'info' => '#3b82f6',
            'warning' => '#f59e0b',
            'danger' => '#ef4444'
        ];
        $bgColor = $typeColors[$type] ?? $typeColors['info'];
        
        // Email template
        $emailTemplate = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white; padding: 30px 20px; text-align: center; }
                .header-logo { max-width: 100px; height: auto; margin: 0 auto 15px; display: block; }
                .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
                .content { padding: 30px 20px; }
                .alert { padding: 15px 20px; border-radius: 6px; margin-bottom: 20px; background-color: " . $bgColor . "15; border-left: 4px solid " . $bgColor . "; }
                .alert-title { color: " . $bgColor . "; font-size: 18px; font-weight: 600; margin: 0 0 10px 0; }
                .alert-message { color: #374151; font-size: 14px; margin: 0; line-height: 1.6; }
                .footer { background-color: #f9fafb; padding: 20px; text-align: center; color: #6b7280; font-size: 12px; border-top: 1px solid #e5e7eb; }
                .footer p { margin: 5px 0; }
                .button { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white; text-decoration: none; border-radius: 6px; font-weight: 600; margin-top: 20px; }
                .divider { height: 1px; background-color: #e5e7eb; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <img src='cid:college_logo' alt='Norzagaray College Logo' class='header-logo'>
                    <h1>NCHire - Norzagaray College</h1>
                </div>
                <div class='content'>
                    <div class='alert'>
                        <h2 class='alert-title'>" . htmlspecialchars($title) . "</h2>
                        <p class='alert-message'>" . nl2br(htmlspecialchars($message)) . "</p>
                    </div>
                    <div class='divider'></div>
                    <p style='color: #6b7280; font-size: 14px;'>
                        Please log in to your NCHire account to view more details about your application status.
                    </p>
                    <a href='http://localhost/FinalResearch%20-%20Copy/user/user.php?view=applications' class='button' style='color: white;'>
                        View My Applications
                    </a>
                </div>
                <div class='footer'>
                    <p><strong>Norzagaray College</strong></p>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>If you have questions, please contact the HR department.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $emailTemplate;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message));

        $mail->send();
        error_log("Email sent successfully to: $to_email");
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed to $to_email. Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Send interview schedule email notification
 */
function sendInterviewScheduleEmail($to_email, $to_name, $interview_datetime, $interview_notes = '') {
    $formatted_date = date('F j, Y \\a\\t g:i A', strtotime($interview_datetime));
    $subject = "Interview Scheduled - NCHire";
    $title = "Interview Scheduled";
    $message = "Your interview has been scheduled for $formatted_date.\n\n";
    if ($interview_notes) {
        $message .= "Additional Notes:\n$interview_notes\n\n";
    }
    $message .= "Please arrive 15 minutes before your scheduled interview time. Good luck!";
    
    return sendEmailNotification($to_email, $to_name, $subject, $title, $message, 'info');
}

/**
 * Send demo schedule email notification
 */
function sendDemoScheduleEmail($to_email, $to_name, $demo_datetime, $demo_notes = '') {
    $formatted_date = date('F j, Y \\a\\t g:i A', strtotime($demo_datetime));
    $subject = "Demo Teaching Scheduled - NCHire";
    $title = "Demo Teaching Scheduled";
    $message = "Your demo teaching has been scheduled for $formatted_date.\n\n";
    if ($demo_notes) {
        $message .= "Additional Notes:\n$demo_notes\n\n";
    }
    $message .= "Please prepare your teaching materials and arrive 15 minutes before your scheduled time. Good luck!";
    
    return sendEmailNotification($to_email, $to_name, $subject, $title, $message, 'info');
}

/**
 * Send interview rescheduled email notification
 */
function sendInterviewRescheduledEmail($to_email, $to_name, $interview_datetime, $reschedule_reason = '') {
    $formatted_date = date('F j, Y \\a\\t g:i A', strtotime($interview_datetime));
    $subject = "Interview Rescheduled - NCHire";
    $title = "Interview Rescheduled";
    $message = "Your interview has been rescheduled to $formatted_date.\n\n";
    if ($reschedule_reason) {
        $message .= "Reason for Rescheduling:\n$reschedule_reason\n\n";
    }
    $message .= "We apologize for any inconvenience. Please arrive 15 minutes before your new scheduled interview time. If you have any questions or concerns, please contact us.";
    
    return sendEmailNotification($to_email, $to_name, $subject, $title, $message, 'warning');
}

/**
 * Send demo teaching rescheduled email notification
 */
function sendDemoRescheduledEmail($to_email, $to_name, $demo_datetime, $reschedule_reason = '') {
    $formatted_date = date('F j, Y \\a\\t g:i A', strtotime($demo_datetime));
    $subject = "Demo Teaching Rescheduled - NCHire";
    $title = "Demo Teaching Rescheduled";
    $message = "Your demo teaching has been rescheduled to $formatted_date.\n\n";
    if ($reschedule_reason) {
        $message .= "Reason for Rescheduling:\n$reschedule_reason\n\n";
    }
    $message .= "We apologize for any inconvenience. Please prepare your teaching materials and arrive 15 minutes before your new scheduled time. If you have any questions or concerns, please contact us.";
    
    return sendEmailNotification($to_email, $to_name, $subject, $title, $message, 'warning');
}

/**
 * Send hired notification email
 */
function sendHiredEmail($to_email, $to_name, $hired_notes = '') {
    $subject = "Congratulations! You're Hired - NCHire";
    $title = "Congratulations! You're Hired!";
    $message = "We are pleased to inform you that you have been selected for the position at Norzagaray College.\n\n";
    if ($hired_notes) {
        $message .= "Additional Information:\n$hired_notes\n\n";
    }
    $message .= "Welcome to the Norzagaray College team! Please await further instructions regarding your onboarding process.";
    
    return sendEmailNotification($to_email, $to_name, $subject, $title, $message, 'success');
}

/**
 * Send initially hired notification email
 */
function sendInitiallyHiredEmail($to_email, $to_name, $initially_hired_notes = '') {
    $subject = "Marked as Initially Hired - NCHire";
    $title = "Congratulations! Marked as Initially Hired";
    $message = "Congratulations! You have been marked as initially hired at Norzagaray College.\n\n";
    if ($initially_hired_notes) {
        $message .= "Additional Information:\n$initially_hired_notes\n\n";
    }
    $message .= "Please wait for final approval and onboarding instructions. We will contact you soon with next steps.";
    
    return sendEmailNotification($to_email, $to_name, $subject, $title, $message, 'success');
}

/**
 * Send permanently hired notification email
 */
function sendPermanentlyHiredEmail($to_email, $to_name, $hired_notes = '') {
    $subject = "Permanently Hired - Welcome Aboard! - NCHire";
    $title = "Permanently Hired - Welcome Aboard!";
    $message = "Congratulations! You have been permanently hired as a regular employee at Norzagaray College.\n\n";
    if ($hired_notes) {
        $message .= "Details:\n$hired_notes\n\n";
    }
    $message .= "Welcome to the team! Please await onboarding instructions and orientation details.";
    
    return sendEmailNotification($to_email, $to_name, $subject, $title, $message, 'success');
}

/**
 * Send rejection notification email
 */
function sendRejectionEmail($to_email, $to_name, $rejection_reason = '') {
    $subject = "Application Status Update - NCHire";
    $title = "Application Update";
    $message = "We regret to inform you that your application has not been successful at this time.\n\n";
    if ($rejection_reason) {
        $message .= "Feedback:\n$rejection_reason\n\n";
    }
    $message .= "We appreciate your interest in Norzagaray College and encourage you to apply for future openings.";
    
    return sendEmailNotification($to_email, $to_name, $subject, $title, $message, 'info');
}

/**
 * Send resubmission request email
 */
function sendResubmissionEmail($to_email, $to_name, $documents, $notes) {
    $subject = "Document Resubmission Required - NCHire";
    $title = "Document Resubmission Required";
    $message = "Please resubmit the following documents:\n$documents\n\n";
    $message .= "Reason:\n$notes\n\n";
    $message .= "Please log in to your account and submit the required documents at your earliest convenience.";
    
    return sendEmailNotification($to_email, $to_name, $subject, $title, $message, 'warning');
}

/**
 * Send interview approved email
 */
function sendInterviewApprovedEmail($to_email, $to_name, $interview_notes = '') {
    $subject = "Interview Approved - NCHire";
    $title = "Interview Approved!";
    $message = "Congratulations! You have successfully passed the interview.\n\n";
    if ($interview_notes) {
        $message .= "Feedback:\n$interview_notes\n\n";
    }
    $message .= "Please wait for the demo teaching schedule. We will contact you soon with further details.";
    
    return sendEmailNotification($to_email, $to_name, $subject, $title, $message, 'success');
}

/**
 * Send demo approved email
 */
function sendDemoApprovedEmail($to_email, $to_name, $demo_notes = '') {
    $subject = "Demo Teaching Approved - NCHire";
    $title = "Demo Teaching Approved!";
    $message = "Congratulations! You have successfully passed the demo teaching.\n\n";
    if ($demo_notes) {
        $message .= "Feedback:\n$demo_notes\n\n";
    }
    $message .= "Please wait for further instructions. We will contact you soon with next steps.";
    
    return sendEmailNotification($to_email, $to_name, $subject, $title, $message, 'success');
}

/**
 * Send psychological exam schedule email
 */
function sendPsychExamScheduleEmail($to_email, $to_name, $psych_exam_datetime, $psych_exam_notes = '') {
    $formatted_date = date('F j, Y \\a\\t g:i A', strtotime($psych_exam_datetime));
    $subject = "Psychological Exam Scheduled - NCHire";
    $title = "Psychological Examination Scheduled";
    $message = "Your psychological examination has been scheduled for $formatted_date.\n\n";
    if ($psych_exam_notes) {
        $message .= "Additional Notes:\n$psych_exam_notes\n\n";
    }
    $message .= "Please upload your receipt after taking the exam. Contact us if you have any questions.";
    
    return sendEmailNotification($to_email, $to_name, $subject, $title, $message, 'info');
}
?>
