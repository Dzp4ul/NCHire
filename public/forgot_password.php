<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - NCHire</title>
    <link rel="icon" type="image/png" href="assets/images/image-removebg-preview (1).png">
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
<body class="relative min-h-screen flex items-center justify-center p-4" style="background-image: url('assets/images/520382375_1065446909052636_3412465913398569974_n.jpg'); background-size: cover; background-position: center; background-attachment: fixed;">
    <!-- Dark Overlay -->
    <div class="absolute inset-0 bg-primary bg-opacity-80"></div>
    
    <!-- Content Wrapper -->
    <div class="relative z-10 max-w-md w-full">
        <!-- Logo -->
        <div class="text-center mb-8">
            <img src="assets/images/image-removebg-preview (1).png" 
                 alt="NCHire Logo" class="w-20 h-20 mx-auto mb-4">
            <h1 class="text-3xl font-bold text-white">NCHire</h1>
            <p class="text-gray-200 mt-2">Norzagaray College</p>
        </div>

        <!-- Forgot Password Card -->
        <div class="bg-white rounded-lg shadow-xl p-8">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-key text-amber-600 text-2xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-900">Forgot Password?</h2>
                <p class="text-gray-600 mt-2">Enter your email to receive a temporary password</p>
            </div>

            <?php if (isset($_SESSION['fp_success'])): ?>
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700"><?= htmlspecialchars($_SESSION['fp_success']) ?></p>
                        </div>
                    </div>
                </div>
                <?php unset($_SESSION['fp_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['fp_error'])): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700"><?= htmlspecialchars($_SESSION['fp_error']) ?></p>
                        </div>
                    </div>
                </div>
                <?php unset($_SESSION['fp_error']); ?>
            <?php endif; ?>

            <form method="POST" action="process_forgot_password.php">
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-envelope mr-2"></i>Email Address
                    </label>
                    <input type="email" 
                           name="email" 
                           id="email" 
                           required 
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" 
                           placeholder="you@example.com">
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-6">
                    <p class="text-xs text-gray-700">
                        <i class="fas fa-info-circle text-blue-600 mr-1"></i>
                        You'll receive a temporary password via email. You must change it after your first login.
                    </p>
                </div>

                <button type="submit" 
                        class="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-blue-800 transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-paper-plane"></i>
                    Send Temporary Password
                </button>
            </form>

            <div class="mt-6 text-center space-y-2">
                <a href="index.php" class="block text-sm text-gray-600 hover:text-gray-800">
                    <i class="fas fa-arrow-left mr-1"></i>Back to Login
                </a>
                <p class="text-xs text-gray-500">
                    For security reasons, we'll send a temporary password instead of a reset link.
                </p>
            </div>
        </div>

        <p class="text-center text-white text-sm mt-4">
            © <?php echo date('Y'); ?> Norzagaray College. All rights reserved.
        </p>
    </div>
</body>
</html>
