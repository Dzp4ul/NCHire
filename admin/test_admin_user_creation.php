<?php
// Test script to check admin user creation system
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Admin User System Test</h2>";

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

echo "<h3>1. Database Connection</h3>";
if ($conn->connect_error) {
    echo "❌ FAILED: " . $conn->connect_error . "<br>";
    die();
} else {
    echo "✅ Connected successfully<br>";
}

echo "<h3>2. Check if admin_users table exists</h3>";
$check_table = $conn->query("SHOW TABLES LIKE 'admin_users'");
if ($check_table->num_rows > 0) {
    echo "✅ Table exists<br>";
    
    // Show table structure
    echo "<h4>Table Structure:</h4>";
    $structure = $conn->query("DESCRIBE admin_users");
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $structure->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ Table does NOT exist<br>";
    echo "<p><strong>ACTION REQUIRED:</strong> Please run <a href='create_admin_users_table.php'>create_admin_users_table.php</a> first!</p>";
    die();
}

echo "<h3>3. Check existing users</h3>";
$users = $conn->query("SELECT id, full_name, email, role, department, status FROM admin_users");
if ($users->num_rows > 0) {
    echo "Found {$users->num_rows} user(s):<br>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th></tr>";
    while ($row = $users->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['full_name']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "<td>{$row['role']}</td>";
        echo "<td>{$row['department']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No users found in database<br>";
}

echo "<h3>4. Test creating a new user</h3>";
// Test data
$test_name = "Test User " . time();
$test_email = "test" . time() . "@example.com";
$test_password = password_hash("test123", PASSWORD_DEFAULT);
$test_role = "Admin";
$test_department = "Computer Science";

echo "Attempting to create user:<br>";
echo "- Name: $test_name<br>";
echo "- Email: $test_email<br>";
echo "- Role: $test_role<br>";
echo "- Department: $test_department<br><br>";

$stmt = $conn->prepare("INSERT INTO admin_users (full_name, email, password, role, department, status) 
                        VALUES (?, ?, ?, ?, ?, 'Active')");

if (!$stmt) {
    echo "❌ PREPARE FAILED: " . $conn->error . "<br>";
} else {
    $stmt->bind_param("sssss", $test_name, $test_email, $test_password, $test_role, $test_department);
    
    if ($stmt->execute()) {
        echo "✅ User created successfully! ID: " . $conn->insert_id . "<br>";
        
        // Delete test user
        $conn->query("DELETE FROM admin_users WHERE email = '$test_email'");
        echo "Test user deleted (cleanup)<br>";
    } else {
        echo "❌ EXECUTE FAILED: " . $stmt->error . "<br>";
    }
}

echo "<h3>5. Test API Endpoint</h3>";
echo "API file location: " . realpath('api/users.php') . "<br>";
if (file_exists('api/users.php')) {
    echo "✅ API file exists<br>";
} else {
    echo "❌ API file not found<br>";
}

$conn->close();

echo "<br><hr><br>";
echo "<p><strong>If all tests pass, the system should work.</strong></p>";
echo "<p><a href='index.php'>← Back to Admin Dashboard</a></p>";
?>
