<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Zero Department Values</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h1 { color: #1e3a8a; border-bottom: 3px solid #fbbf24; padding-bottom: 10px; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .warning { color: #f59e0b; font-weight: bold; }
        .info { background: #eff6ff; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #3b82f6; }
        .button { display: inline-block; padding: 10px 20px; background: #1e3a8a; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .log-entry { padding: 8px; margin: 5px 0; background: #f9fafb; border-left: 3px solid #3b82f6; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Fix "0" Department Values</h1>
    <p>This script will fix applications where assigned_to_department = '0' or '0.0' or is NULL/empty.</p>
    
    <?php
    // First check the current state
    echo "<h2>Step 1: Finding Applications with Wrong Department Values</h2>";
    
    $check_query = "SELECT ja.id, ja.full_name, ja.position, ja.job_id, ja.assigned_to_department, j.department_role, j.job_title
                    FROM job_applicants ja
                    LEFT JOIN job j ON ja.job_id = j.id
                    WHERE ja.assigned_to_department IS NULL 
                       OR ja.assigned_to_department = '' 
                       OR ja.assigned_to_department = '0'
                       OR ja.assigned_to_department = '0.0'
                    ORDER BY ja.id DESC";
    
    $check_result = $conn->query($check_query);
    
    if ($check_result && $check_result->num_rows > 0) {
        $count = $check_result->num_rows;
        echo "<p class='error'>⚠️ Found $count application(s) with wrong department values!</p>";
        
        echo "<div class='info'><strong>Applications to fix:</strong><br>";
        $check_result->data_seek(0);
        while ($app = $check_result->fetch_assoc()) {
            echo "• App #{$app['id']} - {$app['full_name']} for {$app['job_title']} (Job Dept: {$app['department_role']}, Currently: " . ($app['assigned_to_department'] ?: 'NULL') . ")<br>";
        }
        echo "</div>";
        
        echo "<h2>Step 2: Fixing Department Assignments</h2>";
        
        $fixed = 0;
        $failed = 0;
        $no_dept = 0;
        
        // Reset pointer
        $check_result->data_seek(0);
        
        while ($app = $check_result->fetch_assoc()) {
            $app_id = $app['id'];
            $job_dept = $app['department_role'];
            $name = htmlspecialchars($app['full_name']);
            $job_title = htmlspecialchars($app['job_title']);
            $current_dept = $app['assigned_to_department'] ?: 'NULL';
            
            echo "<div class='log-entry'>";
            echo "<strong>App #{$app_id}:</strong> {$name} → {$job_title}<br>";
            echo "Current assigned_to_department: <span class='error'>\"{$current_dept}\"</span><br>";
            
            if (empty($job_dept)) {
                echo "<span class='error'>⚠️ Job has no department! Cannot fix. Please edit the job first.</span>";
                $no_dept++;
            } else {
                // Update the application with the correct department from job table
                $update_stmt = $conn->prepare("UPDATE job_applicants SET assigned_to_department = ? WHERE id = ?");
                $update_stmt->bind_param("si", $job_dept, $app_id);
                
                if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                    echo "New assigned_to_department: <span class='success'>\"{$job_dept}\" ✅</span>";
                    $fixed++;
                } else {
                    echo "<span class='error'>❌ Failed to update: " . $conn->error . "</span>";
                    $failed++;
                }
                $update_stmt->close();
            }
            echo "</div>";
        }
        
        echo "<div class='info'>";
        echo "<h3>✅ Fix Complete!</h3>";
        echo "<strong>Successfully Fixed:</strong> $fixed<br>";
        if ($failed > 0) {
            echo "<strong class='error'>Failed:</strong> $failed<br>";
        }
        if ($no_dept > 0) {
            echo "<strong class='warning'>Skipped (Job has no department):</strong> $no_dept<br>";
        }
        echo "</div>";
        
        // Verify the fix
        echo "<h2>Step 3: Verification</h2>";
        $verify = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE assigned_to_department = '0' OR assigned_to_department = '0.0' OR assigned_to_department = '' OR assigned_to_department IS NULL");
        if ($verify) {
            $remaining = $verify->fetch_assoc()['count'];
            if ($remaining == 0) {
                echo "<p class='success'>✅ Perfect! All applications now have proper department assignments!</p>";
            } else {
                echo "<p class='warning'>⚠️ Still $remaining application(s) without proper department assignment. They may be linked to jobs without departments.</p>";
            }
        }
        
    } else {
        echo "<p class='success'>✅ All applications already have correct department assignments!</p>";
        echo "<p>No applications found with assigned_to_department = '0' or NULL.</p>";
    }
    
    // Show current state by department
    echo "<h2>Step 4: Applications by Department</h2>";
    $dept_breakdown = $conn->query("SELECT assigned_to_department, COUNT(*) as count 
                                    FROM job_applicants 
                                    WHERE status != 'Rejected' 
                                    AND assigned_to_department IS NOT NULL 
                                    AND assigned_to_department != ''
                                    AND assigned_to_department != '0'
                                    GROUP BY assigned_to_department 
                                    ORDER BY count DESC");
    
    if ($dept_breakdown && $dept_breakdown->num_rows > 0) {
        echo "<div class='info'>";
        echo "<strong>Current Distribution:</strong><br>";
        while ($dept = $dept_breakdown->fetch_assoc()) {
            $dept_name = htmlspecialchars($dept['assigned_to_department']);
            $count = $dept['count'];
            echo "• <strong>{$dept_name}:</strong> {$count} application(s)<br>";
        }
        echo "</div>";
    } else {
        echo "<p class='warning'>No department assignments found.</p>";
    }
    ?>
    
    <div style="text-align: center; margin-top: 30px;">
        <a href="index.php" class="button">✅ Done - Go to Admin Dashboard</a>
        <a href="debug_education_dept.php" class="button">🔍 View Education Debug Report</a>
    </div>
</div>

</body>
</html>
<?php
$conn->close();
?>
