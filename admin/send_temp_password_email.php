<?php
// Fix path - go up to root, then to vendor
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendTemporaryPasswordEmail($recipientEmail, $recipientName, $temporaryPassword, $role) {
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
        $logoPath = __DIR__ . '/../assets/images/image-removebg-preview (1).png';
        if (file_exists($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'college_logo', 'logo.png', 'base64', 'image/png');
        }

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Your NCHire Admin Account - Temporary Password';
        
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
                .alert-box { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0; border-radius: 4px; }
                .credentials-box { background: #f8fafc; border: 2px solid #e2e8f0; padding: 20px; margin: 20px 0; border-radius: 8px; text-align: center; }
                .credential-item { margin: 15px 0; }
                .credential-label { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
                .credential-value { font-size: 18px; font-weight: 700; color: #1e3a8a; background: white; padding: 10px 15px; border-radius: 6px; display: inline-block; margin-top: 5px; border: 1px solid #cbd5e1; }
                .warning-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px; }
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
                    <h1>Welcome to NCHire Admin Panel</h1>
                </div>
                
                <div class='content'>
                    <p>Dear <strong>$recipientName</strong>,</p>
                    
                    <div class='alert-box'>
                        <strong>🎉 Your admin account has been created!</strong><br>
                        You have been granted <strong>$role</strong> access to the NCHire Admin Panel.
                    </div>
                    
                    <p>Your account has been set up with a temporary password. For security reasons, you will be required to change this password when you first log in.</p>
                    
                    <div class='credentials-box'>
                        <h3 style='margin-top: 0; color: #1e3a8a;'>🔐 Your Login Credentials</h3>
                        
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
                        • Your new password must be at least 6 characters long
                    </div>
                    
                    <div class='steps'>
                        <h3 style='margin-top: 0; color: #1e3a8a;'>Getting Started:</h3>
                        <div class='step'>Visit the NCHire Admin Panel login page</div>
                        <div class='step'>Enter your email address and temporary password</div>
                        <div class='step'>You'll be prompted to create a new password</div>
                        <div class='step'>Once changed, you'll have full access to the admin panel</div>
                    </div>
                    
                    <div style='text-align: center;'>
                        <a href='http://localhost/FinalResearch%20-%20Copy/admin' class='btn'>Go to Admin Login</a>
                    </div>
                    
                    <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 14px;'>
                        If you did not expect this email or have any questions, please contact the system administrator immediately.
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
        $mail->AltBody = "Your NCHire Admin Account\n\nDear $recipientName,\n\nYour admin account has been created with $role access.\n\nEmail: $recipientEmail\nTemporary Password: $temporaryPassword\n\nPlease log in and change your password immediately.\n\nNCHire - Norzagaray College";
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        error_log("Email Error: " . $mail->ErrorInfo);
        return ['success' => false, 'message' => 'Email could not be sent: ' . $mail->ErrorInfo];
    }
}
?>
