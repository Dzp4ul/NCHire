<?php
session_start();
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
    <title>Fix Application Departments</title>
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
    <h1>🔧 Fix Application Department Assignments</h1>
    
    <?php
    // Check if assigned_to_department column exists
    $check_column = $conn->query("SHOW COLUMNS FROM job_applicants LIKE 'assigned_to_department'");
    
    if (!$check_column || $check_column->num_rows === 0) {
        echo "<div class='error'>❌ Column 'assigned_to_department' does not exist!</div>";
        echo "<p>Running fix to add the column...</p>";
        
        $add_column = "ALTER TABLE job_applicants ADD COLUMN assigned_to_department VARCHAR(100) DEFAULT NULL AFTER job_id";
        
        if ($conn->query($add_column)) {
            echo "<p class='success'>✅ Added 'assigned_to_department' column successfully!</p>";
        } else {
            echo "<p class='error'>❌ Error adding column: " . $conn->error . "</p>";
            echo "</div></body></html>";
            $conn->close();
            exit;
        }
    } else {
        echo "<p class='success'>✅ Column 'assigned_to_department' exists.</p>";
    }
    
    // Find applications that need department assignment
    echo "<h2>Step 1: Finding applications without department assignment</h2>";
    
    $find_query = "SELECT ja.id, ja.full_name, ja.position, ja.job_id, j.department_role, j.job_title
                   FROM job_applicants ja
                   LEFT JOIN job j ON ja.job_id = j.id
                   WHERE ja.assigned_to_department IS NULL OR ja.assigned_to_department = ''";
    
    $find_result = $conn->query($find_query);
    
    if ($find_result && $find_result->num_rows > 0) {
        $count = $find_result->num_rows;
        echo "<p class='warning'>⚠️ Found $count application(s) without department assignment.</p>";
        
        echo "<h2>Step 2: Fixing department assignments</h2>";
        
        $fixed = 0;
        $failed = 0;
        $no_dept = 0;
        
        // Reset pointer
        $find_result->data_seek(0);
        
        while ($app = $find_result->fetch_assoc()) {
            $app_id = $app['id'];
            $job_dept = $app['department_role'];
            $name = htmlspecialchars($app['full_name']);
            $job_title = htmlspecialchars($app['job_title']);
            
            echo "<div class='log-entry'>";
            echo "Processing App #$app_id: $name applying for $job_title<br>";
            
            if (empty($job_dept)) {
                echo "<span class='error'>⚠️ Job has no department assigned! Skipping...</span>";
                $no_dept++;
            } else {
                // Update the application with the job's department
                $update_stmt = $conn->prepare("UPDATE job_applicants SET assigned_to_department = ? WHERE id = ?");
                $update_stmt->bind_param("si", $job_dept, $app_id);
                
                if ($update_stmt->execute()) {
                    echo "<span class='success'>✅ Assigned to: $job_dept</span>";
                    $fixed++;
                } else {
                    echo "<span class='error'>❌ Failed: " . $update_stmt->error . "</span>";
                    $failed++;
                }
                $update_stmt->close();
            }
            echo "</div>";
        }
        
        echo "<div class='info'>";
        echo "<h3>Results:</h3>";
        echo "<strong>Successfully Fixed:</strong> $fixed ✅<br>";
        if ($failed > 0) {
            echo "<strong class='error'>Failed:</strong> $failed ❌<br>";
        }
        if ($no_dept > 0) {
            echo "<strong class='warning'>Skipped (Job has no department):</strong> $no_dept ⚠️<br>";
            echo "<p><em>These jobs need to be edited to have a department assigned first.</em></p>";
        }
        echo "</div>";
        
    } else {
        echo "<p class='success'>✅ All applications already have department assignments!</p>";
    }
    
    // Verify the fix
    echo "<h2>Step 3: Verification</h2>";
    
    $verify_query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN assigned_to_department IS NOT NULL AND assigned_to_department != '' THEN 1 ELSE 0 END) as with_dept,
                        SUM(CASE WHEN assigned_to_department IS NULL OR assigned_to_department = '' THEN 1 ELSE 0 END) as without_dept
                     FROM job_applicants
                     WHERE status != 'Rejected'";
    
    $verify_result = $conn->query($verify_query);
    if ($verify_result) {
        $stats = $verify_result->fetch_assoc();
        echo "<div class='info'>";
        echo "<strong>Current Status:</strong><br>";
        echo "Total Active Applications: " . $stats['total'] . "<br>";
        echo "With Department: " . $stats['with_dept'] . " ✅<br>";
        echo "Without Department: " . $stats['without_dept'];
        
        if ($stats['without_dept'] == 0) {
            echo " <span class='success'>✅ Perfect!</span>";
        } else {
            echo " <span class='warning'>⚠️ Still needs attention</span>";
        }
        echo "</div>";
    }
    
    // Show department breakdown
    echo "<h2>Step 4: Department Breakdown</h2>";
    
    $breakdown_query = "SELECT assigned_to_department, COUNT(*) as count
                        FROM job_applicants
                        WHERE status != 'Rejected' AND assigned_to_department IS NOT NULL
                        GROUP BY assigned_to_department
                        ORDER BY count DESC";
    
    $breakdown_result = $conn->query($breakdown_query);
    if ($breakdown_result && $breakdown_result->num_rows > 0) {
        echo "<div class='info'>";
        echo "<strong>Applications by Department:</strong><br>";
        while ($dept = $breakdown_result->fetch_assoc()) {
            $dept_name = htmlspecialchars($dept['assigned_to_department']);
            $count = $dept['count'];
            echo "• <strong>$dept_name:</strong> $count application(s)<br>";
        }
        echo "</div>";
    }
    ?>
    
    <div style="text-align: center; margin-top: 30px;">
        <a href="check_job_departments.php" class="button">View Diagnostic Report</a>
        <a href="index.php" class="button">← Back to Admin Dashboard</a>
    </div>
</div>

</body>
</html>
<?php
$conn->close();
?>
