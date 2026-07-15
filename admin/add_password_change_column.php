<?php
// Add password_change_required column to admin_users table

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

echo "<h2>Adding password_change_required column to admin_users table...</h2>";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if column already exists
$check_column = "SHOW COLUMNS FROM admin_users LIKE 'password_change_required'";
$result = $conn->query($check_column);

if ($result->num_rows > 0) {
    echo "<p style='color: orange;'>Column 'password_change_required' already exists!</p>";
} else {
    // Add the column
    $add_column = "ALTER TABLE admin_users ADD COLUMN password_change_required TINYINT(1) DEFAULT 0 AFTER password";
    
    if ($conn->query($add_column)) {
        echo "<p style='color: green;'>✅ Successfully added 'password_change_required' column!</p>";
    } else {
        echo "<p style='color: red;'>❌ Error adding column: " . $conn->error . "</p>";
    }
}

$conn->close();
?>
