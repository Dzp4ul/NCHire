<?php
/**
 * Shared Email Function for Sending Temporary Passwords
 * Used by forgot password feature for all user types
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send temporary password email to any user type
 * 
 * @param string $recipientEmail User's email address
 * @param string $recipientName User's full name
 * @param string $temporaryPassword Generated temporary password
 * @param string $userType Type of user (Admin, Department Head, Secretary, Applicant)
 * @return array ['success' => bool, 'message' => string]
 */
function sendForgotPasswordEmail($recipientEmail, $recipientName, $temporaryPassword, $userType = 'User') {
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
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Embed the logo
        $logoPath = __DIR__ . '/../../assets/images/image-removebg-preview (1).png';
        if (file_exists($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'college_logo', 'logo.png', 'base64', 'image/png');
        }

        // Determine login URL based on user type
        $loginUrl = 'http://localhost/FinalResearch%20-%20Copy/index.php';
        if (in_array($userType, ['Admin', 'Department Head', 'Secretary', 'HR Manager', 'Recruiter'])) {
            $loginUrl = 'http://localhost/FinalResearch%20-%20Copy/admin';
        }

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset - Temporary Password';
        
        $emailBody = "
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
                .content { padding: 30px; }
                .alert-box { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px; }
                .credentials-box { background: #f8fafc; border: 2px solid #e2e8f0; padding: 20px; margin: 20px 0; border-radius: 8px; text-align: center; }
                .credential-item { margin: 15px 0; }
                .credential-label { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
                .credential-value { font-size: 18px; font-weight: 700; color: #1e3a8a; background: white; padding: 10px 15px; border-radius: 6px; display: inline-block; margin-top: 5px; border: 1px solid #cbd5e1; }
                .warning-box { background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; border-radius: 4px; }
                .btn { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 20px 0; }
                .footer { background: #f8fafc; padding: 20px; text-align: center; color: #64748b; font-size: 12px; border-top: 1px solid #e2e8f0; }
                .steps { background: #f1f5f9; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .step { margin: 10px 0; padding-left: 30px; position: relative; }
                .step::before { content: '✓'; position: absolute; left: 0; top: 0; background: #3b82f6; color: white; width: 20px; height: 20px; border-radius: 50%; text-align: center; line-height: 20px; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <img src='cid:college_logo' alt='Norzagaray College' class='header-logo'>
                    <h1>Password Reset Request</h1>
                </div>
                
                <div class='content'>
                    <p>Dear <strong>$recipientName</strong>,</p>
                    
                    <div class='alert-box'>
                        <strong>🔑 Password Reset Requested</strong><br>
                        We received a request to reset your password for your <strong>$userType</strong> account.
                    </div>
                    
                    <p>A temporary password has been generated for your account. For security reasons, you will be required to change this password when you first log in.</p>
                    
                    <div class='credentials-box'>
                        <h3 style='margin-top: 0; color: #1e3a8a;'>🔐 Your Temporary Password</h3>
                        
                        <div class='credential-item'>
                            <div class='credential-label'>Email Address</div>
                            <div class='credential-value'>$recipientEmail</div>
                        </div>
                        
                        <div class='credential-item'>
                            <div class='credential-label'>Temporary Password</div>
                            <div class='credential-value' style='letter-spacing: 2px; font-family: monospace;'>$temporaryPassword</div>
                        </div>
                    </div>
                    
                    <div class='warning-box'>
                        <strong>⚠️ Important Security Information</strong><br>
                        • This is a temporary password and must be changed on first login<br>
                        • Do not share this password with anyone<br>
                        • You will be prompted to create a new password immediately after logging in<br>
                        • Your new password must be at least 6 characters long<br>
                        • If you did not request this password reset, please contact support immediately
                    </div>
                    
                    <div class='steps'>
                        <h3 style='margin-top: 0; color: #1e3a8a;'>Next Steps:</h3>
                        <div class='step'>Visit the NCHire login page</div>
                        <div class='step'>Enter your email address and temporary password</div>
                        <div class='step'>You'll be prompted to create a new password</div>
                        <div class='step'>Once changed, you'll have full access to your account</div>
                    </div>
                    
                    <div style='text-align: center;'>
                        <a href='$loginUrl' class='btn'>Go to Login Page</a>
                    </div>
                    
                    <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 14px;'>
                        If you did not request this password reset, please ignore this email or contact the system administrator immediately. Your account is still secure.
                    </p>
                </div>
                
                <div class='footer'>
                    <strong>NCHire - Norzagaray College</strong><br>
                    Recruitment Management System<br>
                    © " . date('Y') . " Norzagaray College. All rights reserved.
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->Body = $emailBody;
        $mail->AltBody = "Password Reset - NCHire\n\nDear $recipientName,\n\nWe received a request to reset your password for your $userType account.\n\nEmail: $recipientEmail\nTemporary Password: $temporaryPassword\n\nPlease log in and change your password immediately.\n\nIf you did not request this reset, please contact support.\n\nNCHire - Norzagaray College";
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        error_log("Forgot Password Email Error: " . $mail->ErrorInfo);
        return ['success' => false, 'message' => 'Email could not be sent: ' . $mail->ErrorInfo];
    }
}
?>
