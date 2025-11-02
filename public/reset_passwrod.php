<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Forgot Password - NCHire</title>
<link href="https://cdn.tailwindcss.com" rel="stylesheet" />
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen">
  <div class="bg-white p-8 rounded shadow-md w-full max-w-md">
    <h2 class="text-2xl font-bold mb-6 text-center text-primary">Forgot Password</h2>

    <?php if (isset($_SESSION['fp_success'])): ?>
      <p class="text-green-600 mb-4"><?= htmlspecialchars($_SESSION['fp_success']) ?></p>
      <?php unset($_SESSION['fp_success']); ?>
    <?php elseif (isset($_SESSION['fp_error'])): ?>
      <p class="text-red-600 mb-4"><?= htmlspecialchars($_SESSION['fp_error']) ?></p>
      <?php unset($_SESSION['fp_error']); ?>
    <?php endif; ?>

    <form method="POST" action="process_forgot_password.php">
      <label for="email" class="block mb-2 font-semibold text-gray-700">Enter your email address</label>
      <input type="email" name="email" id="email" required class="w-full p-3 border border-gray-300 rounded mb-6 focus:outline-none focus:ring-2 focus:ring-primary" placeholder="you@example.com" />
      <button type="submit" class="w-full bg-primary text-white py-3 rounded hover:bg-blue-800 transition">Send Reset Link</button>
    </form>

    <p class="mt-4 text-center text-gray-600">
      Remembered your password? <a href="index.php" class="text-primary hover:underline">Sign In</a>
    </p>
  </div>
</body>
</html>
