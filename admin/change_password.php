<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

// Check if password change is actually required
if (!isset($_SESSION['password_change_required']) || $_SESSION['password_change_required'] != 1) {
    header("Location: index.php");
    exit();
}

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure admin_name and admin_role are in session
if (!isset($_SESSION['admin_name']) || !isset($_SESSION['admin_role'])) {
    // Fetch from database using admin_id
    if (isset($_SESSION['admin_id'])) {
        $fetch_stmt = $conn->prepare("SELECT full_name, role FROM admin_users WHERE id = ?");
        $fetch_stmt->bind_param("i", $_SESSION['admin_id']);
        $fetch_stmt->execute();
        $fetch_result = $fetch_stmt->get_result();
        
        if ($fetch_result->num_rows > 0) {
            $admin_data = $fetch_result->fetch_assoc();
            $_SESSION['admin_name'] = $admin_data['full_name'];
            $_SESSION['admin_role'] = $admin_data['role'];
        }
        $fetch_stmt->close();
    }
}

$error = '';
$success = '';

// Handle password change submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    // Validation
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Za-z]/', $new_password)) {
        $error = 'Password must contain at least one letter.';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
        $error = 'Password must contain at least one symbol (e.g., !@#$%^&*).';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password and set password_change_required to 0
        $stmt = $conn->prepare("UPDATE admin_users SET password = ?, password_change_required = 0 WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $_SESSION['admin_id']);
        
        if ($stmt->execute()) {
            $_SESSION['password_change_required'] = 0;
            $success = 'Password changed successfully! Please log in again with your new password.';
            
            // Log the activity
            $activity_stmt = $conn->prepare("INSERT INTO admin_activity (activity_type, description, user_name, created_at) VALUES (?, ?, ?, NOW())");
            $activity_type = "password_changed";
            $admin_name = $_SESSION['admin_name'] ?? 'Unknown Admin';
            $activity_desc = "{$admin_name} changed their password";
            $activity_stmt->bind_param("sss", $activity_type, $activity_desc, $admin_name);
            $activity_stmt->execute();
            
            // Clear only admin session (keep applicant login if exists)
            unset($_SESSION['admin_logged_in']);
            unset($_SESSION['admin_id']);
            unset($_SESSION['admin_name']);
            unset($_SESSION['admin_email']);
            unset($_SESSION['admin_role']);
            unset($_SESSION['admin_department']);
            unset($_SESSION['admin_profile_picture']);
            unset($_SESSION['password_change_required']);
            
            // Redirect after 2 seconds to homepage with login modal parameter
            header("Refresh: 2; URL=../index.php?open_login=1");
        } else {
            $error = 'Failed to update password. Please try again.';
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - NCHire Admin</title>
    <link rel="icon" type="image/png" href="../public/assets/images/image-removebg-preview (1).png">
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e3a8a',
                        secondary: '#fbbf24'
                    }
                }
            }
        }
    </script>
</head>
<body class="relative min-h-screen flex items-center justify-center p-4" style="background-image: url('../public/assets/images/520382375_1065446909052636_3412465913398569974_n.jpg'); background-size: cover; background-position: center; background-attachment: fixed;">
    <!-- Dark Overlay -->
    <div class="absolute inset-0 bg-primary bg-opacity-80"></div>
    
    <!-- Content Wrapper -->
    <div class="relative z-10 max-w-md w-full">
        <!-- Logo -->
        <div class="text-center mb-8">
            <img src="../public/assets/images/image-removebg-preview (1).png" 
                 alt="NCHire Logo" class="w-20 h-20 mx-auto mb-4">
            <h1 class="text-3xl font-bold text-white">NCHire Admin</h1>
            <p class="text-gray-200 mt-2">Norzagaray College</p>
        </div>

        <!-- Change Password Card -->
        <div class="bg-white rounded-lg shadow-xl p-8">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-key text-amber-600 text-2xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-900">Change Your Password</h2>
                <p class="text-gray-600 mt-2">You must change your temporary password before continuing</p>
            </div>

            <!-- Info Box -->
            <div class="bg-blue-50 border-l-4 border-primary p-4 mb-6 rounded">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-primary"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-gray-700">
                            <strong>Logged in as:</strong> <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Unknown'); ?><br>
                            <strong>Role:</strong> <?php echo htmlspecialchars($_SESSION['admin_role'] ?? 'Unknown'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700"><?php echo htmlspecialchars($success); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock mr-2"></i>New Password
                        </label>
                        <div class="relative">
                            <input type="password" name="new_password" id="newPassword" required minlength="8"
                                   class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="Enter new password (min. 8 characters)">
                            <button type="button" onclick="togglePassword('newPassword', 'newPasswordIcon')"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700">
                                <i id="newPasswordIcon" class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock mr-2"></i>Confirm New Password
                        </label>
                        <div class="relative">
                            <input type="password" name="confirm_password" id="confirmPassword" required minlength="8"
                                   class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="Confirm your new password">
                            <button type="button" onclick="togglePassword('confirmPassword', 'confirmPasswordIcon')"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700">
                                <i id="confirmPasswordIcon" class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                        <p class="text-xs text-amber-800">
                            <i class="fas fa-shield-alt mr-1"></i>
                            <strong>Password Requirements:</strong><br>
                            • At least 8 characters long<br>
                            • Must contain at least one letter<br>
                            • Must contain at least one number<br>
                            • Must contain at least one symbol (!@#$%^&*)
                        </p>
                    </div>

                    <button type="submit" 
                            class="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-blue-800 transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-check"></i>
                        Change Password & Continue
                    </button>
                </div>
            </form>

            <div class="mt-6 text-center">
                <a href="../index.php?logout=1" class="text-sm text-gray-600 hover:text-gray-800">
                    <i class="fas fa-sign-out-alt mr-1"></i>Logout
                </a>
            </div>
        </div>

        <p class="text-center text-white text-sm mt-4">
            © <?php echo date('Y'); ?> Norzagaray College. All rights reserved.
        </p>
    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        }
    </script>
</body>
</html>
