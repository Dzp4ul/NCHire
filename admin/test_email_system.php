<?php
/**
 * Email Notification System Test File
 * 
 * This file allows you to test the email notification system
 * before using it in production with real applicants.
 */

require_once __DIR__ . '/email_helper.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Email Notification Test - NCHire</title>
    <script src='https://cdn.tailwindcss.com'></script>
</head>
<body class='bg-gray-100 p-8'>
    <div class='max-w-4xl mx-auto bg-white rounded-lg shadow-lg p-8'>
        <h1 class='text-3xl font-bold text-blue-900 mb-6'>📧 Email Notification System Test</h1>
        <p class='text-gray-600 mb-8'>Test the email notification system by sending sample emails to your test email address.</p>
";

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_email = $_POST['test_email'] ?? '';
    $test_name = $_POST['test_name'] ?? 'Test Applicant';
    $test_type = $_POST['test_type'] ?? '';
    
    if (empty($test_email)) {
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>
                <strong>Error:</strong> Please enter a test email address.
              </div>";
    } else {
        echo "<div class='bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4'>
                <strong>Sending test email...</strong>
              </div>";
        
        $result = false;
        
        switch ($test_type) {
            case 'interview':
                $interview_datetime = date('Y-m-d H:i:s', strtotime('+3 days'));
                $result = sendInterviewScheduleEmail($test_email, $test_name, $interview_datetime, 'Please bring your resume and valid ID.');
                break;
                
            case 'demo':
                $demo_datetime = date('Y-m-d H:i:s', strtotime('+5 days'));
                $result = sendDemoScheduleEmail($test_email, $test_name, $demo_datetime, 'Prepare a 30-minute lesson plan.');
                break;
                
            case 'hired':
                $result = sendHiredEmail($test_email, $test_name, 'Please report to HR office on Monday at 9:00 AM.');
                break;
                
            case 'initially_hired':
                $result = sendInitiallyHiredEmail($test_email, $test_name, 'Contract signing scheduled for next week.');
                break;
                
            case 'permanently_hired':
                $result = sendPermanentlyHiredEmail($test_email, $test_name, 'Orientation will be held on the first Monday of next month.');
                break;
                
            case 'rejection':
                $result = sendRejectionEmail($test_email, $test_name, 'We received many qualified applications and had to make difficult decisions.');
                break;
                
            case 'resubmission':
                $result = sendResubmissionEmail($test_email, $test_name, 'Resume, Transcript of Records', 'The documents submitted were unclear or incomplete.');
                break;
                
            case 'interview_approved':
                $result = sendInterviewApprovedEmail($test_email, $test_name, 'Excellent communication skills and subject matter expertise.');
                break;
                
            case 'demo_approved':
                $result = sendDemoApprovedEmail($test_email, $test_name, 'Great teaching methodology and student engagement.');
                break;
                
            case 'psych_exam':
                $psych_exam_datetime = date('Y-m-d H:i:s', strtotime('+7 days'));
                $result = sendPsychExamScheduleEmail($test_email, $test_name, $psych_exam_datetime, 'Bring valid ID and pen.');
                break;
                
            default:
                echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>
                        <strong>Error:</strong> Invalid test type selected.
                      </div>";
                break;
        }
        
        if ($result) {
            echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>
                    <strong>✅ Success!</strong> Test email sent to <strong>$test_email</strong>. Please check your inbox (and spam folder).
                  </div>";
        } else {
            echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>
                    <strong>❌ Failed!</strong> Could not send test email. Please check your email configuration and error logs.
                  </div>";
        }
    }
}

echo "
        <form method='POST' class='space-y-6'>
            <div>
                <label class='block text-sm font-medium text-gray-700 mb-2'>Test Email Address</label>
                <input type='email' name='test_email' required 
                       class='w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent'
                       placeholder='your.email@example.com'>
                <p class='text-sm text-gray-500 mt-1'>Enter your email address to receive the test notification</p>
            </div>
            
            <div>
                <label class='block text-sm font-medium text-gray-700 mb-2'>Test Applicant Name</label>
                <input type='text' name='test_name' value='Test Applicant' required 
                       class='w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent'>
            </div>
            
            <div>
                <label class='block text-sm font-medium text-gray-700 mb-2'>Notification Type</label>
                <select name='test_type' required 
                        class='w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent'>
                    <option value=''>-- Select Notification Type --</option>
                    <option value='interview'>📅 Interview Scheduled</option>
                    <option value='demo'>👨‍🏫 Demo Teaching Scheduled</option>
                    <option value='psych_exam'>🧠 Psychological Exam Scheduled</option>
                    <option value='interview_approved'>✅ Interview Approved</option>
                    <option value='demo_approved'>✅ Demo Teaching Approved</option>
                    <option value='initially_hired'>🎉 Initially Hired</option>
                    <option value='permanently_hired'>🎊 Permanently Hired</option>
                    <option value='hired'>🎉 Hired</option>
                    <option value='rejection'>❌ Application Rejected</option>
                    <option value='resubmission'>⚠️ Document Resubmission Required</option>
                </select>
            </div>
            
            <button type='submit' 
                    class='w-full bg-blue-900 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-800 transition-colors'>
                📧 Send Test Email
            </button>
        </form>
        
        <div class='mt-8 p-6 bg-gray-50 rounded-lg'>
            <h2 class='text-xl font-bold text-gray-800 mb-4'>ℹ️ System Information</h2>
            <ul class='space-y-2 text-sm text-gray-700'>
                <li><strong>SMTP Server:</strong> smtp.gmail.com</li>
                <li><strong>Port:</strong> 465 (SSL)</li>
                <li><strong>From Email:</strong> no-reply@nchire.local</li>
                <li><strong>Status:</strong> <span class='text-green-600 font-semibold'>✅ System Ready</span></li>
            </ul>
            
            <div class='mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded'>
                <p class='text-sm text-yellow-800'>
                    <strong>⚠️ Note:</strong> If emails are not being received, check your spam/junk folder 
                    and verify that your Gmail account has \"Less secure app access\" enabled or you're using an App Password.
                </p>
            </div>
        </div>
        
        <div class='mt-6'>
            <a href='index.php' class='text-blue-600 hover:text-blue-800 font-medium'>
                ← Back to Admin Dashboard
            </a>
        </div>
    </div>
</body>
</html>";
?>
