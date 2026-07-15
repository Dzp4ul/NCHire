<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Department Routing</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1e3a8a; border-bottom: 3px solid #fbbf24; padding-bottom: 10px; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .info { color: #3b82f6; }
        .button { display: inline-block; padding: 10px 20px; background: #1e3a8a; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🏢 Add Department Routing to Applications</h1>
    
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

echo "<p>Connecting to database...</p>";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo "<p class='error'>❌ Connection failed: " . $conn->connect_error . "</p>";
    die("</div></body></html>");
}

echo "<p class='success'>✅ Connected to database successfully!</p>";

// Add assigned_to_department column to job_applicants table
echo "<h2>Step 1: Adding assigned_to_department column</h2>";

// Check if column already exists
$check_column = $conn->query("SHOW COLUMNS FROM job_applicants LIKE 'assigned_to_department'");

if ($check_column && $check_column->num_rows > 0) {
    echo "<p class='info'>ℹ️ Column assigned_to_department already exists</p>";
} else {
    // Column doesn't exist, add it
    $add_column = "ALTER TABLE job_applicants ADD COLUMN assigned_to_department VARCHAR(100) DEFAULT NULL AFTER job_id";
    
    if ($conn->query($add_column)) {
        echo "<p class='success'>✅ Added assigned_to_department column successfully!</p>";
    } else {
        echo "<p class='error'>❌ Error adding column: " . $conn->error . "</p>";
    }
}

// Update existing applications with department from job table
echo "<h2>Step 2: Updating existing applications with department info</h2>";
$update_existing = "UPDATE job_applicants ja 
                    INNER JOIN job j ON ja.job_id = j.id 
                    SET ja.assigned_to_department = j.department_role 
                    WHERE ja.assigned_to_department IS NULL";

if ($conn->query($update_existing)) {
    $affected = $conn->affected_rows;
    echo "<p class='success'>✅ Updated $affected existing applications with department information!</p>";
} else {
    echo "<p class='error'>❌ Error updating existing applications: " . $conn->error . "</p>";
}

// Show summary
echo "<hr>";
echo "<h2 class='success'>✅ Department Routing Setup Complete!</h2>";
echo "<p>Applications will now be automatically routed to the appropriate department heads based on the job's department.</p>";

echo "<div class='info' style='padding: 15px; background: #eff6ff; border-radius: 5px; margin: 15px 0;'>";
echo "<h3>🎯 How it works:</h3>";
echo "<ul>";
echo "<li><strong>Computer Science Department:</strong> Applications for CS jobs go to CS Department Head</li>";
echo "<li><strong>Hospitality Management Department:</strong> Applications for Hospitality jobs go to Hospitality Department Head</li>";
echo "<li><strong>Education Department:</strong> Applications for Education jobs go to Education Department Head</li>";
echo "</ul>";
echo "<p><strong>Note:</strong> Admins with 'Admin' role can see all applications from all departments.</p>";
echo "</div>";

echo "<a href='index.php' class='button'>Go to Admin Dashboard</a>";

$conn->close();
?>
</div>
</body>
</html>
