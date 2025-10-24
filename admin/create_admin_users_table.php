<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Users Table</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1e3a8a; border-bottom: 3px solid #fbbf24; padding-bottom: 10px; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .info { color: #3b82f6; }
        .code { background: #f3f4f6; padding: 15px; border-radius: 5px; font-family: monospace; overflow-x: auto; }
        .button { display: inline-block; padding: 10px 20px; background: #1e3a8a; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Create Admin Users Table</h1>
    
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

echo "<p>Connecting to database...</p>";
echo "<div class='code'>";
echo "Host: $host<br>";
echo "User: $user<br>";
echo "Database: $dbname<br>";
echo "</div>";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo "<p class='error'>❌ Connection failed: " . $conn->connect_error . "</p>";
    echo "<p><strong>Troubleshooting:</strong></p>";
    echo "<ul>";
    echo "<li>Make sure XAMPP MySQL is running</li>";
    echo "<li>Check if database '$dbname' exists</li>";
    echo "<li>Verify the password is correct</li>";
    echo "</ul>";
    die("</div></body></html>");
}

echo "<p class='success'>✅ Connected to database successfully!</p>";

// Create admin_users table
$create_table = "CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    department VARCHAR(100) NOT NULL,
    profile_picture VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    status ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_department (department),
    INDEX idx_status (status)
)";

echo "<p>Creating admin_users table...</p>";

if ($conn->query($create_table)) {
    echo "<p class='success'>✅ Admin users table created successfully!</p>";
    
    // Check if table is empty and add default admin
    $check = $conn->query("SELECT COUNT(*) as count FROM admin_users");
    $row = $check->fetch_assoc();
    
    if ($row['count'] == 0) {
        echo "<p>Adding default admin user...</p>";
        // Add default admin user (password: admin123)
        $default_password = password_hash('admin123', PASSWORD_DEFAULT);
        $insert_default = "INSERT INTO admin_users (full_name, email, password, role, department, status) 
                          VALUES ('Admin User', 'admin@norzagaraycollege.edu.ph', '$default_password', 'Admin', 'Computer Science', 'Active')";
        
        if ($conn->query($insert_default)) {
            echo "<p class='success'>✅ Default admin user created successfully!</p>";
            echo "<div class='code'>";
            echo "<strong>Login Credentials:</strong><br>";
            echo "Email: admin@norzagaraycollege.edu.ph<br>";
            echo "Password: admin123<br>";
            echo "</div>";
        } else {
            echo "<p class='error'>❌ Error creating default admin: " . $conn->error . "</p>";
        }
    } else {
        echo "<p class='info'>ℹ️ Admin users table already has " . $row['count'] . " user(s)</p>";
    }
    
    // Show success summary
    echo "<hr>";
    echo "<h2 class='success'>✅ Setup Complete!</h2>";
    echo "<p>The admin user management system is ready to use.</p>";
    echo "<a href='index.php' class='button'>Go to Admin Dashboard</a>";
    echo "<a href='setup_check.php' class='button'>Run System Check</a>";
    
} else {
    echo "<p class='error'>❌ Error creating table: " . $conn->error . "</p>";
    echo "<p><strong>SQL Error Details:</strong></p>";
    echo "<div class='code'>" . htmlspecialchars($create_table) . "</div>";
}

$conn->close();
?>
</div>
</body>
</html>
