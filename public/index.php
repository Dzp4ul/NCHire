<?php
session_start(); // Must be first line

// Database connection
$host = "127.0.0.1"; // XAMPP default host
$user = "root"; // XAMPP default MySQL username
$pass = ""; // leave empty unless you set a password
$dbname = "nchire"; // change to your DB name

$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Unified login handler for both admin and users
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login_submit'])) {
    // Don't clear session - allow both admin and applicant to be logged in simultaneously
    // session_unset(); // REMOVED - this was logging out the other user type
    
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $login_found = false;

    // Check admin_users table first (new admin system)
    $stmt = $conn->prepare("SELECT id, full_name, email, password, role, department, profile_picture, password_change_required, status FROM admin_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $stmt->close();

        // Check password with hash verification
        if (password_verify($password, $row['password'])) {
            // Check if account is active
            if ($row['status'] !== 'Active') {
                $_SESSION['signin_error'] = 'Your account has been deactivated. Please contact the administrator.';
                header("Location: index.php");
                exit();
            } 
            // Set session variables for admin
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $row['id'];
            $_SESSION['admin_name'] = $row['full_name'];
            $_SESSION['admin_email'] = $row['email'];
            $_SESSION['admin_role'] = $row['role'];
            $_SESSION['admin_department'] = $row['department'];
            $_SESSION['admin_profile_picture'] = $row['profile_picture'];
            $_SESSION['password_change_required'] = $row['password_change_required'];
            
            // Update last login timestamp
            $update_stmt = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
            $update_stmt->bind_param("i", $row['id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Check if password change is required
            if ($row['password_change_required'] == 1) {
                header("Location: ../admin/change_password.php");
            } else {
                header("Location: ../admin/index.php");
            }
            exit();
        }
        // If admin password wrong, continue to check applicants table
    } else {
        $stmt->close();
    }
    
    // Check applicants table (runs if admin not found OR admin password wrong)
    $stmt = $conn->prepare("SELECT * FROM applicants WHERE applicant_email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($password === $row['applicant_password']) {
            // Save user info in session
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_email'] = $row['applicant_email'];
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['first_name'] = $row['first_name'];
            $_SESSION['password_change_required'] = $row['password_change_required'] ?? 0;

            // Check if password change is required
            if (isset($row['password_change_required']) && $row['password_change_required'] == 1) {
                header("Location: ../user/change_password.php");
                exit();
            }

            // Check if there's a redirect saved from email link
            $redirect_url = '../user/user.php';
            if (isset($_SESSION['redirect_after_login'])) {
                $redirect_url = '../user/' . $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']);
            }
            
            // Check if there's a job_id to view from homepage
            if (isset($_SESSION['view_job_id'])) {
                $job_id = $_SESSION['view_job_id'];
                unset($_SESSION['view_job_id']);
                $redirect_url = '../user/user.php?view_job=' . $job_id;
            }
            
            header("Location: " . $redirect_url);
            exit();
        } else {
            $login_error = "Invalid password.";
            $login_found = true;
        }
    } else {
        $stmt->close();
    }
    
    // If no login was found in either table
    if (!$login_found && !isset($login_error)) {
        $login_error = "Email not found.";
    }
}


if (isset($_GET['logout'])) {
    // Only unset applicant-specific session variables (keep admin login if exists)
    unset($_SESSION['user_logged_in']);
    unset($_SESSION['user_email']);
    unset($_SESSION['user_id']);
    unset($_SESSION['first_name']);
    unset($_SESSION['pending_signup']);
    unset($_SESSION['signup_email']);
    unset($_SESSION['redirect_after_login']);
    
    header("Location: index.php");
    exit();
}


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php'; // Include Composer's autoloader



if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['signup_submit'])) {
    $signup_firstname = $_POST['signup_firstname'] ?? '';
    $signup_lastname = $_POST['signup_lastname'] ?? '';
    $signup_email = $_POST['signup_email'] ?? '';
    $signup_password = $_POST['signup_password'] ?? '';
    $signup_confirm_password = $_POST['signup_confirm_password'] ?? '';

    // Validate password strength
    if (strlen($signup_password) < 8) {
        $signup_error = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Za-z]/', $signup_password)) {
        $signup_error = "Password must contain at least one letter.";
    } elseif (!preg_match('/[0-9]/', $signup_password)) {
        $signup_error = "Password must contain at least one number.";
    } elseif (!preg_match('/[^A-Za-z0-9]/', $signup_password)) {
        $signup_error = "Password must contain at least one symbol (e.g., !@#$%^&*).";
    } elseif ($signup_password !== $signup_confirm_password) {
        $signup_error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT * FROM applicants WHERE applicant_email = ? LIMIT 1");
        $stmt->bind_param("s", $signup_email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $signup_error = "Email already exists. Please try a different email address.";
        } else {
            // Generate a verification code
            $verification_code = strtoupper(bin2hex(random_bytes(3))); // random 6-character code

            // Store user data in session temporarily (don't save to database yet)
            $_SESSION['pending_signup'] = [
                'first_name' => $signup_firstname,
                'last_name' => $signup_lastname,
                'email' => $signup_email,
                'password' => $signup_password,
                'verification_code' => $verification_code
            ];

            // Send verification email without saving to database first
            $mail = new PHPMailer(true);
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'manansalajohnpaul120@gmail.com'; // Your Gmail
                $mail->Password   = 'dcuv npdb mmnz lyfa';            // App password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                // Recipients
                $mail->setFrom('no-reply@nchire.local', 'NCHire - Norzagaray College');
                $mail->addAddress($signup_email, "$signup_firstname $signup_lastname");
                
                // Embed the logo image
                $logoPath = __DIR__ . '/assets/images/image-removebg-preview (1).png';
                if (file_exists($logoPath)) {
                    $mail->addEmbeddedImage($logoPath, 'college_logo', 'logo.png', 'base64', 'image/png');
                }

                // Professional email template matching admin notifications
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
                        .verification-box { background: linear-gradient(135deg, #3b82f6 0%, #1e3a8a 100%); color: white; padding: 30px; border-radius: 8px; text-align: center; margin: 20px 0; }
                        .verification-code { font-size: 32px; font-weight: bold; letter-spacing: 8px; margin: 15px 0; background: white; color: #1e3a8a; padding: 15px 30px; border-radius: 6px; display: inline-block; }
                        .alert { padding: 15px 20px; border-radius: 6px; margin-bottom: 20px; background-color: #3b82f615; border-left: 4px solid #3b82f6; }
                        .alert-title { color: #3b82f6; font-size: 18px; font-weight: 600; margin: 0 0 10px 0; }
                        .alert-message { color: #374151; font-size: 14px; margin: 0; line-height: 1.6; }
                        .footer { background-color: #f9fafb; padding: 20px; text-align: center; color: #6b7280; font-size: 12px; border-top: 1px solid #e5e7eb; }
                        .footer p { margin: 5px 0; }
                        .divider { height: 1px; background-color: #e5e7eb; margin: 20px 0; }
                        .info-text { color: #6b7280; font-size: 14px; text-align: center; margin: 20px 0; }
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
                                <h2 class='alert-title'>Welcome to NCHire!</h2>
                                <p class='alert-message'>Hello <strong>" . htmlspecialchars($signup_firstname . ' ' . $signup_lastname) . "</strong>,<br><br>Thank you for registering with the Norzagaray College Hiring Portal. To complete your registration and verify your email address, please use the verification code below.</p>
                            </div>
                            
                            <div class='verification-box'>
                                <p style='margin: 0 0 10px 0; font-size: 16px;'>Your Verification Code</p>
                                <div class='verification-code'>" . htmlspecialchars($verification_code) . "</div>
                                <p style='margin: 10px 0 0 0; font-size: 14px; opacity: 0.9;'>Enter this code in the verification page</p>
                            </div>
                            
                            <div class='divider'></div>
                            
                            <p class='info-text'>
                                <strong>Important:</strong> This verification code will expire in 15 minutes.<br>
                                If you did not request this registration, please ignore this email.
                            </p>
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
                $mail->Subject = 'Email Verification Code - NCHire';
                $mail->Body    = $emailTemplate;
                $mail->AltBody = "Hello $signup_firstname $signup_lastname,\n\nThank you for registering with NCHire - Norzagaray College Hiring Portal!\n\nYour verification code is: $verification_code\n\nPlease enter this code to complete your registration.\n\nThis code will expire in 15 minutes.\n\nIf you did not request this registration, please ignore this email.";

                $mail->send();

                $signup_success = "Registration initiated! Please check your email for your verification code to complete the process.";
                $_SESSION['signup_email'] = $signup_email; // Store email for verification
                $show_signup_form = false;
            } catch (Exception $e) {
                $signup_error = "Failed to send verification email. Mailer Error: {$mail->ErrorInfo}";
                // Clear the pending signup data if email fails
                unset($_SESSION['pending_signup']);
            }
        }
    }
}

$conn->close();
?>




<!DOCTYPE html>
<html lang="en">
<head><script src="https://static.readdy.ai/static/e.js"></script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NCHire - Norzagaray College Hiring Portal</title>
<link rel="icon" type="image/png" href="assets/images/image-removebg-preview (1).png">
<link rel="shortcut icon" type="image/png" href="assets/images/image-removebg-preview (1).png">
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
<script>
tailwind.config = {
theme: {
extend: {
colors: {
primary: '#1e3a8a',
secondary: '#fbbf24'
},
borderRadius: {
'none': '0px',
'sm': '4px',
DEFAULT: '8px',
'md': '12px',
'lg': '16px',
'xl': '20px',
'2xl': '24px',
'3xl': '32px',
'full': '9999px',
'button': '8px'
}
}
},
plugins: []
}
</script>
<style>
/* Line clamp utilities for text truncation */
.line-clamp-1 {
  overflow: hidden;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 1;
  line-clamp: 1;
}

.line-clamp-2 {
  overflow: hidden;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
}

.line-clamp-3 {
  overflow: hidden;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 3;
  line-clamp: 3;
}

/* Fix for mobile viewport height (addresses iOS Safari issues) */
@supports (-webkit-touch-callout: none) {
  .min-h-screen {
    min-height: -webkit-fill-available;
  }
}
</style>
<style>
:where([class^="ri-"])::before {
content: "\f3c2";
}

/* Mobile Navigation Styles */
.mobile-menu {
  transform: translateX(100%);
  transition: transform 0.3s ease-in-out;
}

.mobile-menu.active {
  transform: translateX(0);
}

/* Prevent body scroll when mobile menu is open */
body.menu-open {
  overflow: hidden;
}

/* Mobile carousel adjustments */
@media (max-width: 768px) {
  .carousel-arrow-left {
    left: 0 !important;
  }
  
  .carousel-arrow-right {
    right: 0 !important;
  }
}
</style>
<!-- Custom Toasts + Alert Override -->
<script>
function showToast(message, type = 'info', duration = 3000) {
const id = 'toast-container';
let container = document.getElementById(id);
if (!container) {
container = document.createElement('div');
container.id = id;
container.className = 'fixed top-4 right-4 z-[9999] space-y-2';
document.body.appendChild(container);
}

const base = 'px-4 py-3 rounded border shadow flex items-center max-w-sm bg-white';
const variants = {
success: 'border-green-300 text-green-800 bg-green-50',
error: 'border-red-300 text-red-800 bg-red-50',
info: 'border-blue-300 text-blue-800 bg-blue-50',
warning: 'border-yellow-300 text-yellow-800 bg-yellow-50'
};
const icon = {
success: '<i class="ri-check-line mr-2"></i>',
error: '<i class="ri-error-warning-line mr-2"></i>',
info: '<i class="ri-information-line mr-2"></i>',
warning: '<i class="ri-alert-line mr-2"></i>'
};

const toast = document.createElement('div');
toast.className = `${base} ${variants[type] || variants.info}`;
toast.innerHTML = `${icon[type] || icon.info}<span class="text-sm"></span>`;
toast.querySelector('span').textContent = String(message || '');
container.appendChild(toast);

setTimeout(() => {
toast.remove();
if (container.children.length === 0) container.remove();
}, duration);
}

window.alert = function(message) { showToast(message, 'info'); };
</script>
</head>
<body class="bg-gray-50">
<nav class="bg-primary text-white px-4 md:px-6 py-4 sticky top-0 z-50 shadow-md">
<div class="flex items-center justify-between">
<!-- Logo -->
<div class="flex items-center gap-2 md:gap-3">
<img src="https://static.readdy.ai/image/2d44f09b25f25697de5dc274e7f0a5a3/04242d6bffded145c33d09c9dcfae98c.png" alt="Norzagaray College Logo" class="w-10 h-10 md:w-12 md:h-12 object-contain">
<span class="text-xl md:text-2xl font-bold">NCHire</span>
</div>

<!-- Desktop Navigation -->
<div class="hidden lg:flex items-center gap-8">
    <a href="#home" class="text-white hover:text-secondary transition-colors">Home</a>
    <a href="#mission" class="text-white hover:text-secondary transition-colors">Mission</a>
    <a href="#vision" class="text-white hover:text-secondary transition-colors">Vision</a>
    <a href="#about" class="text-white hover:text-secondary transition-colors">About NC</a>
</div>

<!-- Desktop Buttons -->
<div class="hidden lg:flex items-center gap-3">
<a href="#" id="openSignIn" class="px-4 py-2 border border-white text-white hover:bg-white hover:text-primary transition-colors !rounded-button whitespace-nowrap inline-block">Sign In</a>
<a href="#" id="openSignUp" class="px-4 py-2 bg-secondary text-primary hover:bg-yellow-300 transition-colors !rounded-button whitespace-nowrap">Sign Up</a>
</div>

<!-- Mobile Menu Button -->
<button id="mobileMenuBtn" class="lg:hidden text-white focus:outline-none">
<i class="ri-menu-line text-2xl"></i>
</button>
</div>
</nav>

<!-- Mobile Menu Overlay -->
<div id="mobileMenuOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden"></div>

<!-- Mobile Menu -->
<div id="mobileMenu" class="mobile-menu fixed top-0 right-0 h-full w-64 bg-primary text-white z-50 shadow-2xl lg:hidden">
<div class="flex flex-col h-full">
<!-- Close Button -->
<div class="flex items-center justify-between p-4 border-b border-blue-700">
<span class="text-xl font-bold">Menu</span>
<button id="closeMobileMenu" class="text-white focus:outline-none">
<i class="ri-close-line text-2xl"></i>
</button>
</div>

<!-- Navigation Links -->
<div class="flex flex-col gap-1 p-4">
<a href="#home" class="mobile-nav-link px-4 py-3 text-white hover:bg-blue-700 rounded transition-colors">Home</a>
<a href="#mission" class="mobile-nav-link px-4 py-3 text-white hover:bg-blue-700 rounded transition-colors">Mission</a>
<a href="#vision" class="mobile-nav-link px-4 py-3 text-white hover:bg-blue-700 rounded transition-colors">Vision</a>
<a href="#about" class="mobile-nav-link px-4 py-3 text-white hover:bg-blue-700 rounded transition-colors">About NC</a>
</div>

<!-- Mobile Buttons -->
<div class="mt-auto p-4 border-t border-blue-700 space-y-3">
<a href="#" id="openSignInMobile" class="block w-full px-4 py-3 border border-white text-white text-center hover:bg-white hover:text-primary transition-colors !rounded-button">Sign In</a>
<a href="#" id="openSignUpMobile" class="block w-full px-4 py-3 bg-secondary text-primary text-center hover:bg-yellow-300 transition-colors !rounded-button">Sign Up</a>
</div>
</div>
</div>

<div id="signInModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50 p-4">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 md:p-8 relative max-h-[90vh] overflow-y-auto">
    <!-- Close Button -->
    <button id="closeSignIn" class="absolute top-3 right-3 md:top-4 md:right-4 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>

    <!-- Title -->
    <h2 class="text-2xl md:text-3xl font-bold text-primary mb-4 md:mb-6 text-center">Sign In</h2>

    <!-- Sign In Form -->
    <form method="POST" action="" autocomplete="off">
      <!-- Honeypot fields to prevent autofill -->
      <input type="text" name="fake_email" style="position:absolute;top:-9999px;left:-9999px;" tabindex="-1" autocomplete="off">
      <input type="password" name="fake_password" style="position:absolute;top:-9999px;left:-9999px;" tabindex="-1" autocomplete="new-password">
      
      <div class="mb-4">
        <label class="block text-gray-700 mb-2">Email</label>
        <input type="text" name="fake_input_email" id="signInEmail" class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter your email" autocomplete="off" autocomplete="disabled" data-real-name="email" data-real-type="email" value="<?php echo isset($_POST['email']) && isset($login_error) ? htmlspecialchars($_POST['email']) : ''; ?>" onfocus="if(this.hasAttribute('data-autofilled')){this.value='';this.removeAttribute('data-autofilled');}" required>
      </div>
      <div class="mb-4">
        <label class="block text-gray-700 mb-2">Password</label>
        <div class="relative">
          <input type="text" name="fake_input_password" id="passwordInput" class="w-full border border-gray-300 rounded-lg p-3 pr-12 focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter your password" autocomplete="off" autocomplete="disabled" data-real-name="password" data-real-type="password" onfocus="if(this.hasAttribute('data-autofilled')){this.value='';this.removeAttribute('data-autofilled');}" required>
          <button type="button" id="togglePassword" class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-primary">
            <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
  <!-- Eye outline -->
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
    d="M2 2l20 20M4.5 4.5l15 15M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
  <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5l1 2M7 3l1 2M9 2l1 2M15 2l-1 2M17 3l-1 2M19 5l-1 2" />`;
</svg>

          </button>
        </div>

        <p class="text-right text-sm mt-1">
  <a href="forgot_password.php" class="text-primary hover:underline">Forgot Password?</a>
</p>


      </div>
      <?php if (isset($login_error)): ?>
        <p class="text-red-500 text-center mb-4"><?php echo htmlspecialchars($login_error); ?></p>
      <?php endif; ?>
      <button type="submit" name="login_submit" class="w-full bg-primary text-white py-3 rounded-lg hover:bg-blue-800 transition">Sign In</button>

      <!-- Sign Up Link -->
      <p class="mt-4 text-center text-gray-600">
        Don’t have an account? 
        <a href="#" id="openSignUpFromSignIn" class="text-blue-500 hover:underline">Sign up here</a>

      </p>
    </form>
  </div>
</div>

<!-- SIGN UP MODAL -->
<div id="signUpModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50 p-4">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 md:p-8 relative max-h-[90vh] overflow-y-auto">
    <!-- Close Button -->
    <button id="closeSignUp" class="absolute top-3 right-3 md:top-4 md:right-4 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>

    <!-- Title -->
    <h2 class="text-2xl md:text-3xl font-bold text-primary mb-4 md:mb-6 text-center">Sign Up</h2>

    <!-- Sign Up Form -->
    <form method="POST" action="" autocomplete="off">
      <!-- Honeypot fields to prevent autofill -->
      <input type="text" name="fake_signup_email" style="position:absolute;top:-9999px;left:-9999px;" tabindex="-1" autocomplete="off">
      <input type="password" name="fake_signup_password" style="position:absolute;top:-9999px;left:-9999px;" tabindex="-1" autocomplete="new-password">
      
      <div class="mb-4">
    <label class="block text-gray-700 mb-2">First Name</label>
    <input type="text" name="signup_firstname" id="signUpFirstName" class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter your first name" autocomplete="off" pattern="[A-Za-z\s\-']+" title="Please enter only letters, spaces, hyphens, and apostrophes" value="<?php echo isset($_POST['signup_firstname']) && isset($signup_error) ? htmlspecialchars($_POST['signup_firstname']) : ''; ?>" required>
  </div>

  <div class="mb-4">
    <label class="block text-gray-700 mb-2">Last Name</label>
    <input type="text" name="signup_lastname" id="signUpLastName" class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter your last name" autocomplete="off" pattern="[A-Za-z\s\-']+" title="Please enter only letters, spaces, hyphens, and apostrophes" value="<?php echo isset($_POST['signup_lastname']) && isset($signup_error) ? htmlspecialchars($_POST['signup_lastname']) : ''; ?>" required>
  </div>
      <div class="mb-4">
        <label class="block text-gray-700 mb-2">Email</label>
        <input type="text" name="fake_signup_email_input" id="signUpEmailInput" class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter your email" autocomplete="off" autocomplete="disabled" data-real-name="signup_email" data-real-type="email" value="<?php echo isset($_POST['signup_email']) && isset($signup_error) ? htmlspecialchars($_POST['signup_email']) : ''; ?>" onfocus="if(this.hasAttribute('data-autofilled')){this.value='';this.removeAttribute('data-autofilled');}" required>
      </div>


      <div class="mb-4">
        <label class="block text-gray-700 mb-2">Password</label>
        <p class="text-sm text-gray-600 mb-2">Must be at least 8 characters with numbers, letters, and symbols</p>
        <div class="relative">
          <input type="text" name="fake_signup_pass" id="signUpPassword" class="w-full border border-gray-300 rounded-lg p-3 pr-12 focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter your password" autocomplete="off" autocomplete="disabled" data-real-name="signup_password" data-real-type="password" onfocus="if(this.hasAttribute('data-autofilled')){this.value='';this.removeAttribute('data-autofilled');}" minlength="8" required>
          <button type="button" id="toggleSignUpPassword" class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-primary">
            <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
  <!-- Eye outline -->
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
    d="M2 2l20 20M4.5 4.5l15 15M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
  <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5l1 2M7 3l1 2M9 2l1 2M15 2l-1 2M17 3l-1 2M19 5l-1 2" />`;
</svg>

          </button>
        </div>
      </div>

      <div class="mb-6">
        <label class="block text-gray-700 mb-2">Confirm Password</label>
        <div class="relative">
          <input type="text" name="fake_confirm_pass" id="signUpConfirmPassword" class="w-full border border-gray-300 rounded-lg p-3 pr-12 focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Confirm your password" autocomplete="off" autocomplete="disabled" data-real-name="signup_confirm_password" data-real-type="password" required>
          <button type="button" id="toggleSignUpConfirmPassword" class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-primary">
            <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
  <!-- Eye outline -->
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
    d="M2 2l20 20M4.5 4.5l15 15M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
  <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5l1 2M7 3l1 2M9 2l1 2M15 2l-1 2M17 3l-1 2M19 5l-1 2" />`;
</svg>

          </button>
        </div>
      </div>

      <?php if (isset($signup_error)): ?>
        <p class="text-red-500 text-center mb-4"><?php echo htmlspecialchars($signup_error); ?></p>
      <?php endif; ?>
      <button type="submit" name="signup_submit" class="w-full bg-primary text-white py-3 rounded-lg hover:bg-blue-800 transition">Sign Up</button>

      <!-- Sign In Link -->
      <p class="mt-4 text-center text-gray-600">
        Already have an account? 
        <a href="#" id="openSignInFromSignUp" class="text-primary hover:underline">Sign in here</a>
      </p>
    </form>
  </div>
</div>


<!-- VERIFICATION MODAL -->
<div id="verifyModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50 p-4">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 md:p-8 relative max-h-[90vh] overflow-y-auto">
    <button id="closeVerify" class="absolute top-3 right-3 md:top-4 md:right-4 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
    <h2 class="text-2xl md:text-3xl font-bold text-primary mb-4 md:mb-6 text-center">Email Verification</h2>

   <form id="verifyForm">
  <div class="mb-4">
    <label class="block text-gray-700 mb-2">Verification Code</label>
    <input type="text" name="verification_code" required 
           class="w-full border border-gray-300 rounded-lg p-3">
  </div>
  <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg hover:bg-blue-800 transition">
    Verify Code
  </button>
</form>
<p id="verifyMessage" class="mt-4 text-center text-sm"></p>

  </div>
</div>

<!-- FORGOT PASSWORD MODAL -->
<div id="forgotPasswordModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50 p-4">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 md:p-8 relative max-h-[90vh] overflow-y-auto">
    <!-- Close Button -->
    <button id="closeForgotPassword" class="absolute top-3 right-3 md:top-4 md:right-4 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>

    <!-- Title -->
    <h2 class="text-2xl md:text-3xl font-bold text-primary mb-4 md:mb-6 text-center">Forgot Password</h2>

    <!-- Forgot Password Form -->
    <form method="POST" action="process_forgot_password.php" autocomplete="off">
      <div class="mb-6">
        <label for="forgot_email" class="block text-gray-700 mb-2">Email Address</label>
        <input type="email" name="email" id="forgot_email" class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter your email" autocomplete="off" required>
      </div>
      <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg hover:bg-blue-800 transition">Send Reset Link</button>
    </form>

    <p class="mt-4 text-center text-gray-600">
      Remembered your password? 
      <a href="#" id="openSignInFromForgot" class="text-primary hover:underline">Sign in here</a>
    </p>
  </div>
</div>

<!-- TERMS & CONDITIONS MODAL -->
<div id="termsModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50 p-4">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl max-h-[90vh] overflow-hidden relative">
    <!-- Close Button -->
    <button id="closeTerms" class="absolute top-3 right-3 md:top-4 md:right-4 text-gray-500 hover:text-gray-700 text-2xl z-10">&times;</button>
    
    <!-- Scrollable Content -->
    <div class="overflow-y-auto max-h-[90vh] p-6 md:p-8">
      <h2 class="text-3xl md:text-4xl font-bold text-primary mb-4">Terms & Conditions</h2>
      <p class="text-gray-600 mb-6">Last Updated: January 2025</p>

      <div class="space-y-6 text-gray-700">
        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">1. Acceptance of Terms</h3>
          <p>By accessing and using the NCHire (Norzagaray College Hiring Portal), you accept and agree to be bound by the terms and provisions of this agreement.</p>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">2. Use of the Portal</h3>
          <p>The NCHire portal is intended for job seekers interested in employment opportunities at Norzagaray College.</p>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">3. User Accounts</h3>
          <p class="mb-2"><strong>Registration:</strong> Users must provide accurate, current, and complete information during registration.</p>
          <p><strong>Account Security:</strong> You are responsible for all activities that occur under your account.</p>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">4. Application Submission</h3>
          <p class="mb-2"><strong>Accuracy:</strong> All information submitted must be truthful, accurate, and complete.</p>
          <p class="mb-2"><strong>Documents:</strong> Submit documents in PDF format, within 5MB size limits.</p>
          <p><strong>Deadlines:</strong> Applications after the deadline will not be considered.</p>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">5. User Conduct</h3>
          <p>Users agree not to:</p>
          <ul class="list-disc ml-6 mt-2 space-y-1">
            <li>Submit false or fraudulent applications</li>
            <li>Attempt unauthorized access to the system</li>
            <li>Interfere with the portal's proper functioning</li>
          </ul>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">6. Recruitment Process</h3>
          <p class="mb-2"><strong>Review Timeline:</strong> Applications reviewed within 5-7 business days.</p>
          <p class="mb-2"><strong>Selection:</strong> May include interviews, demonstration teaching, and examinations.</p>
          <p><strong>Final Decision:</strong> All hiring decisions are at Norzagaray College's sole discretion.</p>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">7. Privacy</h3>
          <p>Your use is governed by our <a href="#" id="openPrivacyFromTerms" class="text-primary hover:underline">Privacy Policy</a>.</p>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">8. Limitation of Liability</h3>
          <p>Norzagaray College shall not be liable for any damages arising from your use of the portal.</p>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">9. Governing Law</h3>
          <p>These terms are governed by the laws of the Republic of the Philippines.</p>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">10. Contact</h3>
          <div class="bg-gray-50 p-4 rounded-lg">
            <p><strong>Norzagaray College Human Resources</strong></p>
            <p>Email: norzagaraycollege.edu.ph</p>
            <p>Location: Norzagaray, Bulacan</p>
          </div>
        </section>
      </div>
    </div>
  </div>
</div>

<!-- PRIVACY POLICY MODAL -->
<div id="privacyModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50 p-4">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl max-h-[90vh] overflow-hidden relative">
    <!-- Close Button -->
    <button id="closePrivacy" class="absolute top-3 right-3 md:top-4 md:right-4 text-gray-500 hover:text-gray-700 text-2xl z-10">&times;</button>
    
    <!-- Scrollable Content -->
    <div class="overflow-y-auto max-h-[90vh] p-6 md:p-8">
      <h2 class="text-3xl md:text-4xl font-bold text-primary mb-4">Privacy Policy</h2>
      <p class="text-gray-600 mb-6">Last Updated: January 2025</p>

      <div class="space-y-6 text-gray-700">
        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">1. Introduction</h3>
          <p>Norzagaray College is committed to protecting your privacy. This policy explains how we collect, use, and safeguard your information.</p>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">2. Information We Collect</h3>
          <p class="mb-2"><strong>Personal Information:</strong> Name, email, phone, address, profile picture</p>
          <p class="mb-2"><strong>Professional Information:</strong> Education, work experience, licenses, skills</p>
          <p class="mb-2"><strong>Documents:</strong> Resume, transcripts, diplomas, certificates</p>
          <p><strong>Technical:</strong> IP address, browser type, device information</p>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">3. How We Use Your Information</h3>
          <ul class="list-disc ml-6 space-y-1">
            <li>Process and evaluate job applications</li>
            <li>Send notifications about application status</li>
            <li>Verify documents and information</li>
            <li>Maintain your account</li>
            <li>Ensure system security</li>
            <li>Comply with legal obligations</li>
          </ul>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">4. Information Sharing</h3>
          <p class="mb-2"><strong>Internal:</strong> Shared with HR staff, department heads, and selection committees</p>
          <p class="mb-2"><strong>External:</strong> Educational institutions and licensing boards for verification</p>
          <p><strong>No Sale:</strong> We do not sell your personal information</p>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">5. Data Security</h3>
          <ul class="list-disc ml-6 space-y-1">
            <li>SSL encryption</li>
            <li>Password-protected accounts</li>
            <li>Role-based access controls</li>
            <li>Regular security audits</li>
            <li>Secure file storage</li>
          </ul>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">6. Data Retention</h3>
          <p class="mb-1"><strong>Active Applications:</strong> Duration + 1 year</p>
          <p class="mb-1"><strong>Hired Employees:</strong> Per employment records policy</p>
          <p><strong>Unsuccessful:</strong> 1 year or upon request</p>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">7. Your Rights (Data Privacy Act 2012)</h3>
          <ul class="list-disc ml-6 space-y-1">
            <li>Right to access your information</li>
            <li>Right to correct inaccurate data</li>
            <li>Right to request deletion</li>
            <li>Right to object to processing</li>
            <li>Right to data portability</li>
          </ul>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">8. Cookies</h3>
          <p>We use session cookies to maintain your login, remember preferences, and enhance user experience.</p>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">9. Email Communications</h3>
          <p>We send essential emails about verification, application status, interviews, and hiring decisions.</p>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">10. Compliance</h3>
          <p>We comply with the Data Privacy Act of 2012 (RA 10173) and its implementing rules.</p>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">11. Contact</h3>
          <div class="bg-gray-50 p-4 rounded-lg">
            <p><strong>Data Protection Officer</strong></p>
            <p>Norzagaray College</p>
            <p>Email: norzagaraycollege.edu.ph</p>
            <p>Location: Norzagaray, Bulacan</p>
          </div>
        </section>

        <section>
          <h3 class="text-xl font-bold text-gray-900 mb-2">12. File a Complaint</h3>
          <div class="bg-blue-50 border-l-4 border-primary p-4">
            <p><strong>National Privacy Commission (NPC)</strong></p>
            <p class="text-sm">5th Floor, Philippines Postal Corporation Building</p>
            <p class="text-sm">Liwasang Bonifacio, Manila 1000</p>
            <p class="text-sm">Website: privacy.gov.ph</p>
          </div>
        </section>
      </div>
    </div>
  </div>
</div>

<script>
// Immediately show modals if there are errors (before DOMContentLoaded)
window.addEventListener('DOMContentLoaded', function() {
  // Declare variables once
  const signInModal = document.getElementById('signInModal');
  const signUpModal = document.getElementById('signUpModal');
  const verifyModal = document.getElementById('verifyModal');
  
  // Check for inactive account parameter
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('inactive') === '1') {
    showToast('Your account has been deactivated. Please contact the administrator.', 'error', 5000);
    if (signInModal) {
      signInModal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
    }
    // Clean URL
    window.history.replaceState({}, document.title, window.location.pathname);
  }
  
  <?php if (isset($_SESSION['signin_error'])): ?>
  showToast('<?php echo addslashes($_SESSION['signin_error']); ?>', 'error', 5000);
  if (signInModal) {
    signInModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }
  <?php unset($_SESSION['signin_error']); ?>
  <?php endif; ?>
  
  <?php if (isset($login_error)): ?>
  if (signInModal) {
    signInModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }
  <?php endif; ?>
  
  <?php if (isset($signup_error)): ?>
  if (signUpModal) {
    signUpModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }
  <?php endif; ?>
  
  <?php if (isset($signup_success)): ?>
  // Clear URL hash and scroll to top
  if (window.location.hash) {
    history.replaceState(null, null, window.location.pathname);
  }
  window.scrollTo(0, 0);
  
  if (signUpModal) signUpModal.classList.add('hidden');
  if (verifyModal) {
    verifyModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }
  <?php endif; ?>
});
</script>


<!-- SCRIPT -->
<script>

document.addEventListener("DOMContentLoaded", () => {
  const forgotPasswordModal = document.getElementById("forgotPasswordModal");
  const openForgotPasswordBtn = document.getElementById("openForgotPassword");
  const closeForgotPasswordBtn = document.getElementById("closeForgotPassword");
  const openSignInFromForgot = document.getElementById("openSignInFromForgot");

  // Open Forgot Password modal from Sign In modal
  openForgotPasswordBtn.addEventListener("click", (e) => {
    e.preventDefault();
    document.getElementById("signInModal").classList.add("hidden");
    document.querySelector('#forgot_email').value = ''; // clear input when opening
    forgotPasswordModal.classList.remove("hidden");
    // No need to call lockBodyScroll() as body is already locked from Sign In modal
  });

  // Close Forgot Password modal
  closeForgotPasswordBtn.addEventListener("click", () => {
    forgotPasswordModal.classList.add("hidden");
    document.body.style.overflow = ''; // Unlock body scroll
    document.querySelector('#forgot_email').value = ''; // clear input when closing
  });

  // Switch from Forgot Password modal back to Sign In modal
  openSignInFromForgot.addEventListener("click", (e) => {
    e.preventDefault();
    forgotPasswordModal.classList.add("hidden");
    document.querySelector('#forgot_email').value = ''; // clear forgot password field
    document.querySelector('input[name="email"]').value = ''; // clear sign in fields
    document.querySelector('input[name="password"]').value = '';
    document.getElementById("signInModal").classList.remove("hidden");
    // No need to call lockBodyScroll() as body is already locked
  });

  // Disable closing the Forgot Password modal by clicking outside
  forgotPasswordModal.addEventListener("click", (e) => {
    /* no-op: keep modal open unless explicit close button is clicked */
  });

  // Handle Verification Modal close
  const verifyModal = document.getElementById("verifyModal");
  const closeVerifyBtn = document.getElementById("closeVerify");
  
  if (closeVerifyBtn) {
    closeVerifyBtn.addEventListener("click", () => {
      verifyModal.classList.add("hidden");
      document.body.style.overflow = ''; // Unlock body scroll
      // Clear verification code input
      const verificationInput = document.querySelector('input[name="verification_code"]');
      if (verificationInput) {
        verificationInput.value = '';
      }
    });
  }
});

// Terms & Conditions and Privacy Policy Modal Handlers
document.addEventListener("DOMContentLoaded", () => {
  const termsModal = document.getElementById("termsModal");
  const privacyModal = document.getElementById("privacyModal");
  const openTermsBtn = document.getElementById("openTerms");
  const closeTermsBtn = document.getElementById("closeTerms");
  const openPrivacyBtn = document.getElementById("openPrivacy");
  const closePrivacyBtn = document.getElementById("closePrivacy");
  const openPrivacyFromTerms = document.getElementById("openPrivacyFromTerms");

  // Open Terms Modal
  if (openTermsBtn) {
    openTermsBtn.addEventListener("click", (e) => {
      e.preventDefault();
      termsModal.classList.remove("hidden");
      document.body.style.overflow = 'hidden';
    });
  }

  // Close Terms Modal
  if (closeTermsBtn) {
    closeTermsBtn.addEventListener("click", () => {
      termsModal.classList.add("hidden");
      document.body.style.overflow = '';
    });
  }

  // Open Privacy Modal
  if (openPrivacyBtn) {
    openPrivacyBtn.addEventListener("click", (e) => {
      e.preventDefault();
      privacyModal.classList.remove("hidden");
      document.body.style.overflow = 'hidden';
    });
  }

  // Close Privacy Modal
  if (closePrivacyBtn) {
    closePrivacyBtn.addEventListener("click", () => {
      privacyModal.classList.add("hidden");
      document.body.style.overflow = '';
    });
  }

  // Switch from Terms to Privacy Modal
  if (openPrivacyFromTerms) {
    openPrivacyFromTerms.addEventListener("click", (e) => {
      e.preventDefault();
      termsModal.classList.add("hidden");
      privacyModal.classList.remove("hidden");
    });
  }

  // Close modals when clicking outside
  termsModal?.addEventListener("click", (e) => {
    if (e.target === termsModal) {
      termsModal.classList.add("hidden");
      document.body.style.overflow = '';
    }
  });

  privacyModal?.addEventListener("click", (e) => {
    if (e.target === privacyModal) {
      privacyModal.classList.add("hidden");
      document.body.style.overflow = '';
    }
  });

  // Close modals with ESC key
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      if (!termsModal.classList.contains("hidden")) {
        termsModal.classList.add("hidden");
        document.body.style.overflow = '';
      }
      if (!privacyModal.classList.contains("hidden")) {
        privacyModal.classList.add("hidden");
        document.body.style.overflow = '';
      }
    }
  });
});

document.addEventListener("DOMContentLoaded", () => {
    const openSignInBtn = document.getElementById("openSignIn");
    const signInModal = document.getElementById("signInModal");
    const closeSignInBtn = document.getElementById("closeSignIn");

    const openSignUpBtn = document.getElementById("openSignUp");
    const signUpModal = document.getElementById("signUpModal");
    const closeSignUpBtn = document.getElementById("closeSignUp");

    const openSignInFromSignUp = document.getElementById("openSignInFromSignUp");
    const openSignUpFromSignIn = document.getElementById("openSignUpFromSignIn"); // NEW

    // Function to restore correct input types and names before submission
    function restoreInputTypesAndNames(modalSelector) {
        const inputs = document.querySelectorAll(`${modalSelector} input[data-real-type]`);
        inputs.forEach(input => {
            const realType = input.getAttribute('data-real-type');
            const realName = input.getAttribute('data-real-name');
            
            // Clear value first
            input.value = '';
            
            // Change type and name after clearing
            setTimeout(() => {
                if (realType) input.type = realType;
                if (realName) input.name = realName;
            }, 100);
        });
    }
    
    // Function to restore before form submission
    function prepareFormForSubmit(formElement) {
        const inputs = formElement.querySelectorAll('input[data-real-name]');
        inputs.forEach(input => {
            const realName = input.getAttribute('data-real-name');
            const realType = input.getAttribute('data-real-type');
            if (realName) input.name = realName;
            if (realType) input.type = realType;
        });
    }
    
    // Prevent numbers from being entered in first name and last name fields
    function preventNumbers(event) {
        const char = event.key;
        // Allow navigation keys, backspace, delete, tab
        if (event.ctrlKey || event.metaKey || 
            ['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(char)) {
            return;
        }
        // Block if character is a number
        if (/[0-9]/.test(char)) {
            event.preventDefault();
            showToast('Numbers are not allowed in name fields', 'warning', 2000);
        }
    }
    
    // Add real-time validation for name fields
    const firstNameInput = document.getElementById('signUpFirstName');
    const lastNameInput = document.getElementById('signUpLastName');
    
    if (firstNameInput) {
        firstNameInput.addEventListener('keydown', preventNumbers);
        // Also prevent pasted numbers
        firstNameInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[0-9]/g, '');
        });
    }
    
    if (lastNameInput) {
        lastNameInput.addEventListener('keydown', preventNumbers);
        // Also prevent pasted numbers
        lastNameInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[0-9]/g, '');
        });
    }
    
    // Only clear fields if there's no error (don't clear when modal auto-opens with error)
    const hasLoginError = <?php echo isset($login_error) ? 'true' : 'false'; ?>;
    const hasSignupError = <?php echo isset($signup_error) ? 'true' : 'false'; ?>;
    
    if (!hasLoginError && !hasSignupError) {
        // Aggressive clearing of form fields to prevent autofill
        clearSignInFields();
        clearSignUpFields();
        
        // Multiple clearing attempts with different delays
        [50, 100, 200, 500, 1000].forEach(delay => {
            setTimeout(() => {
                clearSignInFields();
                clearSignUpFields();
            }, delay);
        });
    }

    // PASSWORD TOGGLE - Sign In
    const togglePassword = document.getElementById("togglePassword");
    const passwordInput = document.getElementById("passwordInput");

    togglePassword.addEventListener("click", (e) => {
        e.preventDefault();
        // Ensure type is password first if it's text (from anti-autofill)
        if (passwordInput.type === "text" && !passwordInput.getAttribute('data-showing')) {
            passwordInput.type = "password";
        }
        
        const svg = togglePassword.querySelector("svg");
        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            passwordInput.setAttribute('data-showing', 'true');
            svg.innerHTML = `
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
    d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
  <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5l1 2M7 3l1 2M9 2l1 2M15 2l-1 2M17 3l-1 2M19 5l-1 2" />`;

        } else {
            passwordInput.type = "password";
            passwordInput.removeAttribute('data-showing');
            svg.innerHTML = `
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
    d="M2 2l20 20M4.5 4.5l15 15M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
  <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5l1 2M7 3l1 2M9 2l1 2M15 2l-1 2M17 3l-1 2M19 5l-1 2" />`;

        }
    });


    

    // PASSWORD TOGGLE - Sign Up
    const toggleSignUpPassword = document.getElementById("toggleSignUpPassword");
    const signUpPasswordInput = document.getElementById("signUpPassword");

    toggleSignUpPassword.addEventListener("click", (e) => {
        e.preventDefault();
        // Ensure type is password first if it's text (from anti-autofill)
        if (signUpPasswordInput.type === "text" && !signUpPasswordInput.getAttribute('data-showing')) {
            signUpPasswordInput.type = "password";
        }
        
        const svg = toggleSignUpPassword.querySelector("svg");
        if (signUpPasswordInput.type === "password") {
            signUpPasswordInput.type = "text";
            signUpPasswordInput.setAttribute('data-showing', 'true');
            svg.innerHTML = `
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
    d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
  <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5l1 2M7 3l1 2M9 2l1 2M15 2l-1 2M17 3l-1 2M19 5l-1 2" />`;

        } else {
            signUpPasswordInput.type = "password";
            signUpPasswordInput.removeAttribute('data-showing');
            svg.innerHTML = `
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
    d="M2 2l20 20M4.5 4.5l15 15M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
  <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5l1 2M7 3l1 2M9 2l1 2M15 2l-1 2M17 3l-1 2M19 5l-1 2" />`;

        }
    });

    const toggleSignUpConfirmPassword = document.getElementById("toggleSignUpConfirmPassword");
    const signUpConfirmPasswordInput = document.getElementById("signUpConfirmPassword");

    toggleSignUpConfirmPassword.addEventListener("click", (e) => {
        e.preventDefault();
        // Ensure type is password first if it's text (from anti-autofill)
        if (signUpConfirmPasswordInput.type === "text" && !signUpConfirmPasswordInput.getAttribute('data-showing')) {
            signUpConfirmPasswordInput.type = "password";
        }
        
        const svg = toggleSignUpConfirmPassword.querySelector("svg");
        if (signUpConfirmPasswordInput.type === "password") {
            signUpConfirmPasswordInput.type = "text";
            signUpConfirmPasswordInput.setAttribute('data-showing', 'true');
            svg.innerHTML = `
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
    d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
  <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5l1 2M7 3l1 2M9 2l1 2M15 2l-1 2M17 3l-1 2M19 5l-1 2" />`;

        } else {
            signUpConfirmPasswordInput.type = "password";
            signUpConfirmPasswordInput.removeAttribute('data-showing');
            svg.innerHTML = `
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
    d="M2 2l20 20M4.5 4.5l15 15M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
  <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5l1 2M7 3l1 2M9 2l1 2M15 2l-1 2M17 3l-1 2M19 5l-1 2" />`;

        }
    });


    // Function to clear Sign In fields
    function clearSignInFields() {
        const emailInput = document.getElementById('signInEmail');
        const passwordInput = document.getElementById('passwordInput');
        if (emailInput) emailInput.value = '';
        if (passwordInput) passwordInput.value = '';
    }

    // Function to clear Sign Up fields
    function clearSignUpFields() {
        const fieldIds = [
            'signUpFirstName',
            'signUpLastName',
            'signUpEmailInput',
            'signUpPassword',
            'signUpConfirmPassword'
        ];
        fieldIds.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) field.value = '';
        });
    }

    // Function to lock body scroll
    function lockBodyScroll() {
        document.body.style.overflow = 'hidden';
    }
    
    // Function to unlock body scroll
    function unlockBodyScroll() {
        document.body.style.overflow = '';
    }

    // Open Sign In
    openSignInBtn.addEventListener("click", (e) => {
        e.preventDefault();
        signInModal.classList.remove("hidden");
        lockBodyScroll();
        
        // Only clear and reset fields if there's no error
        if (!hasLoginError) {
            // Mark any fields with values as autofilled
            const emailField = document.getElementById('signInEmail');
            const passField = document.getElementById('passwordInput');
            if (emailField && emailField.value) emailField.setAttribute('data-autofilled', 'true');
            if (passField && passField.value) passField.setAttribute('data-autofilled', 'true');
            
            // Clear fields immediately
            clearSignInFields();
        
        // Restore types and names after multiple delays
        setTimeout(() => {
            clearSignInFields();
            // Mark again if browser refilled
            if (emailField && emailField.value) {
                emailField.setAttribute('data-autofilled', 'true');
                emailField.value = '';
            }
            if (passField && passField.value) {
                passField.setAttribute('data-autofilled', 'true');
                passField.value = '';
            }
            restoreInputTypesAndNames('#signInModal');
        }, 100);
        
        setTimeout(() => {
            clearSignInFields();
            // Final check and mark
            if (emailField && emailField.value) {
                emailField.setAttribute('data-autofilled', 'true');
                emailField.value = '';
            }
            if (passField && passField.value) {
                passField.setAttribute('data-autofilled', 'true');
                passField.value = '';
            }
        }, 300);
        
            setTimeout(() => {
                clearSignInFields();
            }, 500);
        }
    });

    // Close Sign In
    closeSignInBtn.addEventListener("click", () => {
        signInModal.classList.add("hidden");
        unlockBodyScroll();
        clearSignInFields(); // Clear fields when closing
        // Hide error message if it exists
        const loginErrorMsg = signInModal.querySelector('.text-red-500');
        if (loginErrorMsg) loginErrorMsg.style.display = 'none';
    });

    // Restore real field names before Sign In form submission
    document.querySelector('#signInModal form').addEventListener('submit', function(e) {
        prepareFormForSubmit(this);
    });

    // Open Sign Up
    openSignUpBtn.addEventListener("click", (e) => {
        e.preventDefault();
        signUpModal.classList.remove("hidden");
        lockBodyScroll();
        
        // Only clear and reset fields if there's no error
        if (!hasSignupError) {
            // Mark any fields with values as autofilled
            const signupEmailField = document.getElementById('signUpEmailInput');
            const signupPassField = document.getElementById('signUpPassword');
            if (signupEmailField && signupEmailField.value) signupEmailField.setAttribute('data-autofilled', 'true');
            if (signupPassField && signupPassField.value) signupPassField.setAttribute('data-autofilled', 'true');
            
            // Clear fields immediately
            clearSignUpFields();
        
        // Restore types and names after multiple delays
        setTimeout(() => {
            clearSignUpFields();
            // Mark again if browser refilled
            if (signupEmailField && signupEmailField.value) {
                signupEmailField.setAttribute('data-autofilled', 'true');
                signupEmailField.value = '';
            }
            if (signupPassField && signupPassField.value) {
                signupPassField.setAttribute('data-autofilled', 'true');
                signupPassField.value = '';
            }
            restoreInputTypesAndNames('#signUpModal');
        }, 100);
        
        setTimeout(() => {
            clearSignUpFields();
            // Final check and mark
            if (signupEmailField && signupEmailField.value) {
                signupEmailField.setAttribute('data-autofilled', 'true');
                signupEmailField.value = '';
            }
            if (signupPassField && signupPassField.value) {
                signupPassField.setAttribute('data-autofilled', 'true');
                signupPassField.value = '';
            }
        }, 300);
        
            setTimeout(() => {
                clearSignUpFields();
            }, 500);
        }
    });

    // Close Sign Up
    closeSignUpBtn.addEventListener("click", () => {
        signUpModal.classList.add("hidden");
        unlockBodyScroll();
        clearSignUpFields(); // Clear fields when closing
        // Hide error message if it exists
        const signupErrorMsg = signUpModal.querySelector('.text-red-500');
        if (signupErrorMsg) signupErrorMsg.style.display = 'none';
    });

    // Restore real field names before Sign Up form submission
    document.querySelector('#signUpModal form').addEventListener('submit', function(e) {
        prepareFormForSubmit(this);
    });

    // Switch from Sign Up → Sign In
    openSignInFromSignUp.addEventListener("click", (e) => {
        e.preventDefault();
        signUpModal.classList.add("hidden");
        clearSignUpFields(); // Clear sign up fields
        clearSignInFields(); // Clear sign in fields
        // Hide signup error message
        const signupErrorMsg = signUpModal.querySelector('.text-red-500');
        if (signupErrorMsg) signupErrorMsg.style.display = 'none';
        signInModal.classList.remove("hidden");
        // No need to call lockBodyScroll() as body is already locked
    });

    // Switch from Sign In → Sign Up
    openSignUpFromSignIn.addEventListener("click", (e) => {
        e.preventDefault();
        signInModal.classList.add("hidden");
        clearSignInFields(); // Clear sign in fields
        clearSignUpFields(); // Clear sign up fields
        // Hide login error message
        const loginErrorMsg = signInModal.querySelector('.text-red-500');
        if (loginErrorMsg) loginErrorMsg.style.display = 'none';
        signUpModal.classList.remove("hidden");
        // No need to call lockBodyScroll() as body is already locked
    });

    // Disable closing Sign In/Sign Up when clicking outside the modal content
    [signInModal, signUpModal].forEach(modal => {
        modal.addEventListener("click", (e) => {
            /* no-op on outside click to prevent closing */
        });
    });
});
</script>

<script>
// Client-side password validation for signup form
document.addEventListener('DOMContentLoaded', function() {
    const signupForm = document.querySelector('#signUpModal form');
    const passwordInput = document.getElementById('signUpPassword');
    const confirmPasswordInput = document.getElementById('signUpConfirmPassword');
    
    if (signupForm) {
        signupForm.addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            // Check password requirements
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
                passwordInput.focus();
                return false;
            }
            
            if (!/[A-Za-z]/.test(password)) {
                e.preventDefault();
                alert('Password must contain at least one letter.');
                passwordInput.focus();
                return false;
            }
            
            if (!/[0-9]/.test(password)) {
                e.preventDefault();
                alert('Password must contain at least one number.');
                passwordInput.focus();
                return false;
            }
            
            if (!/[^A-Za-z0-9]/.test(password)) {
                e.preventDefault();
                alert('Password must contain at least one symbol (e.g., !@#$%^&*).');
                passwordInput.focus();
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match.');
                confirmPasswordInput.focus();
                return false;
            }
        });
    }
});
</script>

<script>
// Function to show verification success popup
function showVerificationSuccessPopup(message) {
    console.log('showVerificationSuccessPopup called with message:', message);
    
    // Create popup HTML
    const popupHTML = `
        <div id="verificationSuccessPopup" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="z-index: 9999;">
            <div class="bg-white rounded-xl max-w-md w-full mx-4 p-6 text-center transform transition-all duration-300 ease-out scale-95 opacity-0" id="popupContent">
                <div class="mb-4">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="ri-check-line text-3xl text-green-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Email Verified!</h3>
                    <p class="text-gray-600">${message}</p>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
                    <p class="text-sm text-green-800">
                        <i class="ri-information-line mr-1"></i>
                        You can now sign in with your credentials.
                    </p>
                </div>
                <button onclick="closeVerificationPopup()" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    Continue to Sign In
                </button>
            </div>
        </div>
    `;
    
    // Add popup to body
    console.log('Adding popup to body');
    $('body').append(popupHTML);
    
    // Check if popup was added
    const addedPopup = $('#verificationSuccessPopup');
    console.log('Popup added, length:', addedPopup.length);
    
    // Animate popup in smoothly
    setTimeout(function() {
        const popupContent = document.getElementById('popupContent');
        if (popupContent) {
            popupContent.classList.remove('scale-95', 'opacity-0');
            popupContent.classList.add('scale-100', 'opacity-100');
        }
    }, 50);
    
    // Auto close after 5 seconds
    setTimeout(function() {
        console.log('Auto-closing popup after 5 seconds');
        closeVerificationPopup();
    }, 5000);
}

// Function to close verification popup
function closeVerificationPopup() {
    console.log('closeVerificationPopup called');
    const popupContent = document.getElementById('popupContent');
    if (popupContent) {
        // Animate out smoothly
        popupContent.classList.remove('scale-100', 'opacity-100');
        popupContent.classList.add('scale-95', 'opacity-0');
        
        // Remove after animation completes
        setTimeout(function() {
            $('#verificationSuccessPopup').remove();
            console.log('Popup removed');
            
            // Close verification modal and open sign-in modal
            $('#verifyModal').addClass('hidden');
            $('#signInModal').removeClass('hidden');
            document.body.style.overflow = 'hidden';
            console.log('Sign-in modal opened automatically');
        }, 300);
    } else {
        // Fallback if no popup content found
        $('#verificationSuccessPopup').remove();
        console.log('Popup removed (fallback)');
        
        // Close verification modal and open sign-in modal
        $('#verifyModal').addClass('hidden');
        $('#signInModal').removeClass('hidden');
        document.body.style.overflow = 'hidden';
        console.log('Sign-in modal opened automatically (fallback)');
    }
}

// Test function to verify popup works
function testPopup() {
    console.log('Testing popup...');
    showVerificationSuccessPopup('This is a test popup to verify functionality works!');
}

// Test function to check if verify.php is accessible
function testVerifyPHP() {
    console.log('Testing verify.php accessibility...');
    $.ajax({
        url: 'verify.php',
        type: 'GET',
        success: function(response) {
            console.log('verify.php GET response:', response);
        },
        error: function(xhr, status, error) {
            console.error('verify.php GET error:', {
                status: status,
                error: error,
                responseText: xhr.responseText,
                statusCode: xhr.status
            });
        }
    });
}

$(document).ready(function() {
    // Test if jQuery is working
    console.log('jQuery loaded successfully!', $);
    
    $('#verifyForm').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: 'verify.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                console.log('Raw response:', response); // Debug log
                console.log('Response type:', typeof response); // Debug log
                
                // Try to parse response if it's a string
                let parsedResponse;
                try {
                    parsedResponse = typeof response === 'string' ? JSON.parse(response) : response;
                } catch (e) {
                    console.error('Failed to parse response:', e);
                    alert('Invalid response from server: ' + response);
                    return;
                }
                
                console.log('Parsed response:', parsedResponse); // Debug log
                
                if (parsedResponse.status === 'success') {
                    console.log('SUCCESS: Showing popup with message:', parsedResponse.message);
                    
                    // Show success popup
                    showVerificationSuccessPopup(parsedResponse.message);
                    
                    // Show debug info if available
                    if (parsedResponse.debug) {
                        console.log('Debug info:', parsedResponse.debug);
                    }

                    // ✅ Close verification modal (using your existing modal system)
                    const verifyModal = document.getElementById('verifyModal');
                    if (verifyModal) {
                        verifyModal.classList.add('hidden');
                    }

                    // ✅ Open Sign In modal after popup is closed
                    setTimeout(function() {
                        const signinModal = document.getElementById('signinModal');
                        if (signinModal) {
                            signinModal.classList.remove('hidden');
                        }
                    }, 3000); // Show signin modal after 3 seconds
                } else {
                    console.error('VERIFICATION FAILED:', parsedResponse);
                    alert('Verification failed: ' + parsedResponse.message + (parsedResponse.debug ? '\nDebug: ' + parsedResponse.debug : ''));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                alert("AJAX Error: " + status + " - " + error + "\nResponse: " + xhr.responseText);
            }
        });
    });
});
</script>


<section class="relative bg-primary text-white min-h-[500px] md:min-h-[600px] flex items-center" style="background-image: url('assets/images/520382375_1065446909052636_3412465913398569974_n.jpg'); background-size: cover; background-position: center;">
<div class="absolute inset-0 bg-primary bg-opacity-80"></div>
<div class="relative z-10 w-full px-4 md:px-6">
<div class="max-w-7xl mx-auto">
<div class="max-w-2xl">
<h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold mb-4 md:mb-6">
YOUR JOB JOURNEY<br>
<span class="text-secondary">BEGINS HERE</span>
</h1>
<p class="text-base md:text-lg lg:text-xl mb-6 md:mb-8 leading-relaxed">
At NCHire, we connect talented individuals with top employers to help you take the next step in your career. Are you ready to start your journey?
</p>
<div class="flex flex-col sm:flex-row gap-3 md:gap-4">
<button onclick="document.getElementById('openSignIn').click()" class="px-6 md:px-8 py-3 md:py-4 border-2 border-white text-white hover:bg-white hover:text-primary transition-colors text-base md:text-lg font-semibold !rounded-button whitespace-nowrap">APPLY NOW</button>
<button onclick="scrollToJobs()" class="px-6 md:px-8 py-3 md:py-4 bg-blue-600 text-white hover:bg-blue-700 transition-colors text-base md:text-lg font-semibold !rounded-button whitespace-nowrap">LEARN MORE</button>
</div>
</div>
</div>
</div>
</section>
<section id="browseJobs" class="py-16 md:py-20 px-4 md:px-6">
<div class="max-w-7xl mx-auto">
<h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-primary mb-8 md:mb-12">Browse Career<br>Opportunities</h2>

<!-- Loading State -->
<div id="jobsLoading" class="text-center py-12">
<i class="ri-loader-4-line text-4xl text-primary animate-spin"></i>
<p class="text-gray-600 mt-4">Loading job opportunities...</p>
</div>

<!-- Error State -->
<div id="jobsError" class="hidden text-center py-12">
<i class="ri-error-warning-line text-4xl text-red-500"></i>
<p class="text-gray-600 mt-4">Failed to load job opportunities. Please try again later.</p>
</div>

<!-- No Jobs State -->
<div id="noJobs" class="hidden text-center py-12">
<i class="ri-briefcase-line text-4xl text-gray-400"></i>
<p class="text-gray-600 mt-4">No job opportunities available at the moment.</p>
</div>

<!-- Jobs Carousel -->
<div id="jobsCarousel" class="hidden relative px-12 md:px-16 lg:px-20">
<!-- Left Arrow -->
<button id="carouselPrev" class="carousel-arrow-left absolute left-0 md:left-2 top-1/2 -translate-y-1/2 z-20 bg-primary shadow-2xl rounded-full p-2 md:p-3 hover:bg-secondary hover:scale-110 transition-all duration-300 group disabled:opacity-30 disabled:cursor-not-allowed">
<i class="ri-arrow-left-s-line text-xl md:text-2xl text-white"></i>
</button>

<!-- Jobs Container -->
<div class="overflow-hidden mx-0 py-8">
<div id="jobsTrack" class="flex transition-transform duration-500 ease-in-out gap-6">
<!-- Job cards will be inserted here dynamically -->
</div>
</div>

<!-- Right Arrow -->
<button id="carouselNext" class="carousel-arrow-right absolute right-0 md:right-2 top-1/2 -translate-y-1/2 z-20 bg-primary shadow-2xl rounded-full p-2 md:p-3 hover:bg-secondary hover:scale-110 transition-all duration-300 group disabled:opacity-30 disabled:cursor-not-allowed">
<i class="ri-arrow-right-s-line text-xl md:text-2xl text-white"></i>
</button>

<!-- Carousel Indicators -->
<div id="carouselIndicators" class="flex justify-center gap-2 mt-8">
<!-- Indicators will be inserted here dynamically -->
</div>
</div>
</div>
</section>

<!-- Job Carousel Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadJobsCarousel();
});

let currentSlide = 0;
let totalSlides = 0;
let jobsData = [];

async function loadJobsCarousel() {
    const loading = document.getElementById('jobsLoading');
    const error = document.getElementById('jobsError');
    const noJobs = document.getElementById('noJobs');
    const carousel = document.getElementById('jobsCarousel');
    
    try {
        console.log('Fetching jobs from API...');
        const response = await fetch('../api/get_homepage_jobs.php');
        console.log('Response status:', response.status);
        
        const data = await response.json();
        console.log('API Response:', data);
        
        if (data.success && data.jobs && data.jobs.length > 0) {
            console.log(`Loaded ${data.jobs.length} jobs successfully`);
            jobsData = data.jobs;
            totalSlides = Math.ceil(jobsData.length / 3); // 3 jobs per slide
            
            loading.classList.add('hidden');
            carousel.classList.remove('hidden');
            
            renderJobs();
            renderIndicators();
            updateCarouselButtons();
        } else if (data.success && data.jobs && data.jobs.length === 0) {
            console.log('No jobs found in database');
            loading.classList.add('hidden');
            noJobs.classList.remove('hidden');
        } else {
            console.error('API returned error:', data);
            loading.classList.add('hidden');
            error.classList.remove('hidden');
            if (data.details) {
                console.error('Error details:', data.details);
            }
        }
    } catch (err) {
        console.error('Error loading jobs:', err);
        loading.classList.add('hidden');
        error.classList.remove('hidden');
    }
}

function renderJobs() {
    const track = document.getElementById('jobsTrack');
    track.innerHTML = '';
    
    jobsData.forEach((job, index) => {
        const card = document.createElement('div');
        card.className = 'flex-shrink-0 w-[calc(100%-3rem)] md:w-[calc((100%-3rem)/3)] bg-primary text-white p-6 md:p-8 rounded-2xl relative group hover:-translate-y-2 hover:shadow-2xl transition-all duration-300 overflow-hidden shadow-lg';
        
        // Increase border radius on hover to compensate for scale
        card.addEventListener('mouseenter', function() {
            this.style.borderRadius = '1.25rem';
        });
        card.addEventListener('mouseleave', function() {
            this.style.borderRadius = '1rem';
        });
        
        // Get department icon based on department name
        const departmentIcons = {
            'Computer Science': 'ri-computer-line',
            'Education': 'ri-book-open-line',
            'Hospitality Management': 'ri-hotel-line',
            'default': 'ri-briefcase-line'
        };
        const iconClass = departmentIcons[job.department] || departmentIcons['default'];
        
        card.innerHTML = `
            <div class="mb-4 md:mb-6">
                <div class="w-full h-32 md:h-40 bg-blue-700 rounded mb-3 md:mb-4 flex items-center justify-center">
                    <i class="${iconClass} text-4xl md:text-6xl text-white opacity-50"></i>
                </div>
            </div>
            <div class="flex items-start justify-between gap-2 mb-2">
                <h3 class="text-lg md:text-xl font-bold flex-1 line-clamp-2">${escapeHtml(job.title)}</h3>
                <span class="flex-shrink-0 px-2 md:px-3 py-1 bg-secondary text-primary text-xs font-semibold rounded-full whitespace-nowrap">${escapeHtml(job.type)}</span>
            </div>
            <p class="text-secondary text-xs md:text-sm mb-2 font-semibold truncate">${escapeHtml(job.department)}</p>
            <p class="text-gray-200 text-xs md:text-sm mb-3 md:mb-4 line-clamp-2">${escapeHtml(job.description)}</p>
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-3 text-xs md:text-sm text-gray-300 mb-12 md:mb-16">
                <div class="flex items-center gap-1 min-w-0">
                    <i class="ri-map-pin-line flex-shrink-0"></i>
                    <span class="truncate">${escapeHtml(job.location)}</span>
                </div>
                <div class="flex items-center gap-1 min-w-0">
                    <i class="ri-calendar-line flex-shrink-0"></i>
                    <span class="truncate">${escapeHtml(job.deadline)}</span>
                </div>
            </div>
            <div class="absolute bottom-4 md:bottom-6 right-4 md:right-6 w-10 h-10 md:w-12 md:h-12 flex items-center justify-center cursor-pointer" onclick="showJobDetails(${job.id})">
                <i class="ri-arrow-right-line text-xl md:text-2xl group-hover:translate-x-1 transition-transform"></i>
            </div>
        `;
        
        track.appendChild(card);
    });
}

function renderIndicators() {
    const indicators = document.getElementById('carouselIndicators');
    indicators.innerHTML = '';
    
    for (let i = 0; i < totalSlides; i++) {
        const dot = document.createElement('button');
        dot.className = `w-3 h-3 rounded-full transition-all duration-300 ${i === 0 ? 'bg-primary w-8' : 'bg-gray-300'}`;
        dot.onclick = () => goToSlide(i);
        indicators.appendChild(dot);
    }
}

function goToSlide(index) {
    currentSlide = index;
    const track = document.getElementById('jobsTrack');
    const container = track.parentElement;
    const containerWidth = container.offsetWidth;
    // Calculate offset: 3 cards per slide
    const offset = currentSlide * containerWidth;
    track.style.transform = `translateX(-${offset}px)`;
    updateCarouselButtons();
    updateIndicators();
}

function updateIndicators() {
    const indicators = document.getElementById('carouselIndicators').children;
    for (let i = 0; i < indicators.length; i++) {
        if (i === currentSlide) {
            indicators[i].className = 'w-8 h-3 rounded-full bg-primary transition-all duration-300';
        } else {
            indicators[i].className = 'w-3 h-3 rounded-full bg-gray-300 transition-all duration-300';
        }
    }
}

function updateCarouselButtons() {
    const prevBtn = document.getElementById('carouselPrev');
    const nextBtn = document.getElementById('carouselNext');
    
    prevBtn.disabled = currentSlide === 0;
    nextBtn.disabled = currentSlide >= totalSlides - 1;
}

document.getElementById('carouselPrev')?.addEventListener('click', function() {
    if (currentSlide > 0) {
        goToSlide(currentSlide - 1);
    }
});

document.getElementById('carouselNext')?.addEventListener('click', function() {
    if (currentSlide < totalSlides - 1) {
        goToSlide(currentSlide + 1);
    }
});

// Touch/Swipe support for mobile
let touchStartX = 0;
let touchEndX = 0;

const carousel = document.getElementById('jobsTrack');
if (carousel) {
    carousel.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    carousel.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, { passive: true });
}

function handleSwipe() {
    const swipeThreshold = 50; // minimum distance for swipe
    
    if (touchEndX < touchStartX - swipeThreshold && currentSlide < totalSlides - 1) {
        // Swipe left - next slide
        goToSlide(currentSlide + 1);
    }
    
    if (touchEndX > touchStartX + swipeThreshold && currentSlide > 0) {
        // Swipe right - previous slide
        goToSlide(currentSlide - 1);
    }
}

function showJobDetails(jobId) {
    console.log('Job clicked, ID:', jobId);
    
    // Store job ID in localStorage for after login
    localStorage.setItem('pendingJobView', jobId);
    
    // Send job ID to server session via AJAX
    fetch('store_job_view.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ job_id: jobId })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Job ID stored in session:', data);
    })
    .catch(error => {
        console.error('Error storing job ID:', error);
    });
    
    // Open sign in modal
    document.getElementById('openSignIn')?.click();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}
</script>

<section id="mission" class="py-12 md:py-16 px-4 md:px-6 bg-white">
<div class="max-w-7xl mx-auto">
<div class="text-center mb-8 md:mb-12">
<h2 class="text-2xl md:text-3xl lg:text-4xl font-bold text-primary mb-3 md:mb-4">NORZAGARAY COLLEGE MISSION</h2>
<p class="text-base md:text-lg lg:text-xl text-gray-600 max-w-3xl mx-auto">Norzagaray College envision itself to transform lives of individuals through life long learning and productivity.</p>
</div>

</div>
</div>
</div>
</section>
<section class="py-12 md:py-16 px-4 md:px-6 bg-gray-100">
<div class="max-w-7xl mx-auto">
<div class="text-center mb-8 md:mb-12">
<h2 id="vision" class="text-2xl md:text-3xl lg:text-4xl font-bold text-primary mb-3 md:mb-4">NORZAGARAY COLLEGE VISION</h2>
<p class="text-base md:text-lg lg:text-xl text-gray-600">To be recognized nationally and internationally as a benchmark for excellence, innovation, integrity, and distinctiveness in bachelor's level education taught from global perspective.</p>
</div>



</div>
</div>
</div>
</section>

<section id="about" class="py-12 md:py-16 px-4 md:px-6 bg-white">
  <div class="max-w-7xl mx-auto">
    <div class="text-center mb-8 md:mb-12">
      <h2 class="text-2xl md:text-3xl lg:text-4xl font-bold text-primary mb-3 md:mb-4">About Norzagaray College</h2>
      <p class="text-sm md:text-base lg:text-lg text-gray-700 max-w-4xl mx-auto leading-relaxed whitespace-pre-line">
        On December 21, 2004, Hon. Mayor Dr. Matilde A. Legaspi announced to the public that the Municipality of Norzagaray will soon establish a college of its own, a non sectarian institution dedicated to help the marginalized and underprivileged sector of the community by providing quality education at a minimum cost. While faced with the growing educational needs of the community, the founders, former Mayor Matilde A. Legaspi, M.D., and Ermelito V. dela Merced, M.D., started the consultations with different government agencies on how to put up an institution of higher learning. Soon, through SANGGUNIANG BAYAN ORDINANCE NO.2006-10, the Norzagaray College was established and the Norzagaray College Charter was promulgated. It was first a three storey building with eighteen rooms housing five (5) courses – Bachelor of Science in Computer Science, Bachelor of Science in Hotel and Restaurant Management, Bachelor of Science in Nursing, Bachelor of Science in Secondary Education and Elementary Education.

        On March 20, 2007, CHED Regional Office III issued a certificate recognizing Norzagaray College as one of the Local Community Colleges in Region III.

        In June 2007, Norzagaray College started providing quality education with its five programs. In 2010, the Commission on Higher Education (CHED) granted the necessary permits for B.S. Computer Science (No. GR-035 Series of 2010), B.S. Hotel and Restaurant Management (No. GR-031 Series of 2010), Bachelor of Elementary Education (No. GR-056 Series of 2010) and Bachelor of Secondary Education (No.GR-57 Series of 2010). While, In 2011, the CERTIFICATE OF PROGRAM COMPLIANCE was granted to Norzagaray College for the Bachelor of Science in Nursing Program. At present, the Norzagaray College is upgrading its standards to make its graduates globally competitive.
      </p>
    </div>
  </div>
</section>


<section class="py-12 md:py-16 px-4 md:px-6 bg-primary text-white">
<div class="max-w-4xl mx-auto text-center">
<h2 class="text-2xl md:text-3xl lg:text-4xl font-bold mb-4 md:mb-6">Are you ready to start your journey?</h2>
<p class="text-base md:text-lg lg:text-xl mb-6 md:mb-8">we connect talented individuals with top employers to help you take the next step in your career.</p>
<div class="flex flex-col sm:flex-row gap-3 md:gap-4 justify-center">
<button onclick="document.getElementById('openSignIn').click()" class="px-6 md:px-8 py-3 md:py-4 bg-secondary text-primary hover:bg-yellow-300 transition-colors text-base md:text-lg font-semibold !rounded-button whitespace-nowrap">Start Your Application</button>
<button onclick="scrollToJobs()" class="px-6 md:px-8 py-3 md:py-4 border-2 border-white text-white hover:bg-white hover:text-primary transition-colors text-base md:text-lg font-semibold !rounded-button whitespace-nowrap">Browse Opportunities</button>
</div>
</div>
</section>
<footer class="bg-gray-900 text-white py-8 md:py-12 px-4 md:px-6">
<div class="max-w-7xl mx-auto">
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6 md:gap-8">
<div>
<div class="flex items-center gap-3 mb-4">
<img src="https://static.readdy.ai/image/2d44f09b25f25697de5dc274e7f0a5a3/04242d6bffded145c33d09c9dcfae98c.png" alt="Norzagaray College Logo" class="w-10 h-10 object-contain">
<span class="text-xl font-bold">NCHire</span>
</div>
<p class="text-gray-400">Connecting passionate instructors with opportunities to inspire at Norzagaray College.</p>
</div>
<div>
<h4 class="font-bold mb-4">Quick Links</h4>
<ul class="space-y-2 text-gray-400">
<li><a href="#" class="hover:text-white transition-colors">Home</a></li>
<li><a href="#mission" class="hover:text-white transition-colors">Mission</a></li>
<li><a href="#vision" class="hover:text-white transition-colors">Vision</a></li>
<li><a href="#" class="hover:text-white transition-colors">About NC</a></li>
</ul>
</div>
<div>
<h4 class="font-bold mb-4">Legal</h4>
<ul class="space-y-2 text-gray-400">
<li><a href="#" id="openTerms" class="hover:text-white transition-colors">Terms & Conditions</a></li>
<li><a href="#" id="openPrivacy" class="hover:text-white transition-colors">Privacy Policy</a></li>
</ul>
</div>
<div>
<h4 class="font-bold mb-4">Social Media</h4>
<ul class="space-y-2 text-gray-400">
<li><a href="https://www.facebook.com/norzagaraycollege2007" class="hover:text-white transition-colors">Facebook</a></li>
</ul>
</div>
<div>
<h4 class="font-bold mb-4">Contact</h4>
<ul class="space-y-2 text-gray-400">
<li class="flex items-center gap-2">
<div class="w-4 h-4 flex items-center justify-center">
<i class="ri-mail-line"></i>
</div>norzagaraycollege.edu.ph
</li>

<li class="flex items-center gap-2">
<div class="w-4 h-4 flex items-center justify-center">
<i class="ri-map-pin-line"></i>
</div>
Norzagaray, Bulacan
</li>
</ul>
</div>
</div>
<div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
<p>&copy; 2025 NCHire Research. All rights reserved.</p>
</div>
</div>
</footer>
<script id="mobile-menu-functionality">
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const closeMobileMenu = document.getElementById('closeMobileMenu');
    const mobileMenu = document.getElementById('mobileMenu');
    const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
    const mobileNavLinks = document.querySelectorAll('.mobile-nav-link');
    const openSignInMobile = document.getElementById('openSignInMobile');
    const openSignUpMobile = document.getElementById('openSignUpMobile');
    
    // Open mobile menu
    mobileMenuBtn?.addEventListener('click', function() {
        mobileMenu.classList.add('active');
        mobileMenuOverlay.classList.remove('hidden');
        document.body.classList.add('menu-open');
    });
    
    // Close mobile menu
    function closeMobileMenuFn() {
        mobileMenu.classList.remove('active');
        mobileMenuOverlay.classList.add('hidden');
        document.body.classList.remove('menu-open');
    }
    
    closeMobileMenu?.addEventListener('click', closeMobileMenuFn);
    mobileMenuOverlay?.addEventListener('click', closeMobileMenuFn);
    
    // Close menu when clicking navigation links
    mobileNavLinks.forEach(link => {
        link.addEventListener('click', function() {
            closeMobileMenuFn();
        });
    });
    
    // Connect mobile Sign In button to desktop Sign In modal
    openSignInMobile?.addEventListener('click', function(e) {
        e.preventDefault();
        closeMobileMenuFn();
        setTimeout(() => {
            document.getElementById('openSignIn')?.click();
        }, 300);
    });
    
    // Connect mobile Sign Up button to desktop Sign Up modal
    openSignUpMobile?.addEventListener('click', function(e) {
        e.preventDefault();
        closeMobileMenuFn();
        setTimeout(() => {
            document.getElementById('openSignUp')?.click();
        }, 300);
    });
});
</script>
<script id="navigation-interactions">
document.addEventListener('DOMContentLoaded', function() {
const navLinks = document.querySelectorAll('nav a');
navLinks.forEach(link => {
link.addEventListener('mouseenter', function() {
this.style.transform = 'translateY(-1px)';
});
link.addEventListener('mouseleave', function() {
this.style.transform = 'translateY(0)';
});
});
});
</script>
<script id="card-hover-effects">
document.addEventListener('DOMContentLoaded', function() {
const cards = document.querySelectorAll('.group');
cards.forEach(card => {
card.addEventListener('mouseenter', function() {
this.style.boxShadow = '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)';
});
card.addEventListener('mouseleave', function() {
this.style.boxShadow = '';
});
});
});
</script>
<script id="smooth-scroll">
// Enhanced scroll function with visual feedback
function scrollToJobs() {
    const jobsSection = document.getElementById('browseJobs');
    
    // Smooth scroll to the section
    jobsSection.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });
    
    // Add a subtle highlight animation
    const heading = jobsSection.querySelector('h2');
    if (heading) {
        heading.style.transition = 'transform 0.3s ease, color 0.3s ease';
        heading.style.transform = 'scale(1.05)';
        
        setTimeout(() => {
            heading.style.transform = 'scale(1)';
        }, 300);
    }
}

// Handle Browse Opportunities button clicks
document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('button');
    buttons.forEach(button => {
        if (button.textContent.includes('Browse Opportunities')) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                scrollToJobs();
            });
        }
    });
});
</script>
<script>
    !function (t, e) { var o, n, p, r; e.__SV || (window.posthog = e, e._i = [], e.init = function (i, s, a) { function g(t, e) { var o = e.split("."); 2 == o.length && (t = t[o[0]], e = o[1]), t[e] = function () { t.push([e].concat(Array.prototype.slice.call(arguments, 0))) } } (p = t.createElement("script")).type = "text/javascript", p.crossOrigin = "anonymous", p.async = !0, p.src = s.api_host.replace(".i.posthog.com", "-assets.i.posthog.com") + "/static/array.js", (r = t.getElementsByTagName("script")[0]).parentNode.insertBefore(p, r); var u = e; for (void 0 !== a ? u = e[a] = [] : a = "posthog", u.people = u.people || [], u.toString = function (t) { var e = "posthog"; return "posthog" !== a && (e += "." + a), t || (e += " (stub)"), e }, u.people.toString = function () { return u.toString(1) + ".people (stub)" }, o = "init capture register register_once register_for_session unregister unregister_for_session getFeatureFlag getFeatureFlagPayload isFeatureEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSessionId getSurveys getActiveMatchingSurveys renderSurvey canRenderSurvey getNextSurveyStep identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags reset get_distinct_id getGroups get_session_id get_session_replay_url alias set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_property getSessionProperty createPersonProfile opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing clear_opt_in_out_capturing debug".split(" "), n = 0; n < o.length; n++)g(u, o[n]); e._i.push([i, s, a]) }, e.__SV = 1) }(document, window.posthog || []);
    posthog.init('phc_t9tkQZJiyi2ps9zUYm8TDsL6qXo4YmZx0Ot5rBlAlEd', {
        api_host: 'https://us.i.posthog.com',
        autocapture: false,
        capture_pageview: false,
        capture_pageleave: false,
        capture_performance: {
            web_vitals: false,
        },
        rageclick: false,
    })
    window.shareKey = '6sdg46JxL-BLHTAWMV5C5g';
    window.host = 'readdy.ai';
</script>

<script>
// Auto-open login modal if redirected after password change
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('open_login') === '1') {
        const signInModal = document.getElementById('signInModal');
        if (signInModal) {
            signInModal.classList.remove('hidden');
            // Lock body scroll when modal opens
            document.body.style.overflow = 'hidden';
            
            // Show success message
            showToast('Password changed successfully! Please log in with your new password.', 'success', 5000);
            
            // Clean up URL (remove the parameter)
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
});
</script>

</body>
</html>
