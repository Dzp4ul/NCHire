<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../public/index.php");
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

echo "<h1>Fix Workflow Stages for Existing Applications</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    h1 { color: #1e3a8a; }
    h2 { color: #3b82f6; margin-top: 30px; }
    .info-box { background: #e3f2fd; padding: 15px; margin: 10px 0; border-left: 4px solid #2196F3; border-radius: 4px; }
    .success-box { background: #e8f5e9; padding: 15px; margin: 10px 0; border-left: 4px solid #4caf50; border-radius: 4px; }
    .warning-box { background: #fff3e0; padding: 15px; margin: 10px 0; border-left: 4px solid #ff9800; border-radius: 4px; }
    .error-box { background: #ffebee; padding: 15px; margin: 10px 0; border-left: 4px solid #f44336; border-radius: 4px; }
    button { background: #1e3a8a; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
    button:hover { background: #1e40af; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #1e3a8a; color: white; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .back-link { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #6b7280; color: white; text-decoration: none; border-radius: 4px; }
    .back-link:hover { background: #4b5563; }
</style>";

echo "<div class='container'>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_workflows'])) {
    echo "<h2>Fixing Workflow Stages...</h2>";
    
    $fixed_count = 0;
    $errors = [];
    
    // 1. Fix applications with NULL or empty workflow_stage
    // These should go to secretary_review if they're pending or just submitted
    $update1 = "UPDATE job_applicants 
                SET workflow_stage = 'secretary_review' 
                WHERE (workflow_stage IS NULL OR workflow_stage = '')
                AND status IN ('Pending', 'Application Received', 'Resubmitted')
                AND status != 'Rejected'";
    
    if ($conn->query($update1)) {
        $count1 = $conn->affected_rows;
        if ($count1 > 0) {
            echo "<div class='success-box'>✓ Set workflow_stage to 'secretary_review' for $count1 pending application(s)</div>";
            $fixed_count += $count1;
        }
    } else {
        $errors[] = "Error updating pending applications: " . $conn->error;
    }
    
    // 2. Applications that have been transferred but don't have workflow_stage set
    $update2 = "UPDATE job_applicants 
                SET workflow_stage = 'department_head_review' 
                WHERE (workflow_stage IS NULL OR workflow_stage = '')
                AND (status LIKE '%Interview%' OR status LIKE '%Demo%' OR status LIKE '%Psych%' OR status LIKE '%Hired%')
                AND status != 'Rejected'";
    
    if ($conn->query($update2)) {
        $count2 = $conn->affected_rows;
        if ($count2 > 0) {
            echo "<div class='success-box'>✓ Set workflow_stage to 'department_head_review' for $count2 in-progress application(s)</div>";
            $fixed_count += $count2;
        }
    } else {
        $errors[] = "Error updating in-progress applications: " . $conn->error;
    }
    
    // 3. Set proper workflow stages based on current status
    $status_mappings = [
        ['status' => 'Interview Scheduled', 'workflow' => 'interview_scheduled'],
        ['status' => 'Demo Scheduled', 'workflow' => 'demo_scheduled'],
        ['status' => 'Psychological Exam', 'workflow' => 'psych_scheduled'],
        ['status' => 'Initially Hired', 'workflow' => 'initially_hired'],
        ['status' => 'Permanently Hired', 'workflow' => 'permanently_hired'],
        ['status' => 'Hired', 'workflow' => 'hired']
    ];
    
    foreach ($status_mappings as $mapping) {
        $update = "UPDATE job_applicants 
                   SET workflow_stage = ? 
                   WHERE status = ? 
                   AND (workflow_stage IS NULL OR workflow_stage = '' OR workflow_stage = 'department_head_review')";
        
        $stmt = $conn->prepare($update);
        $stmt->bind_param("ss", $mapping['workflow'], $mapping['status']);
        
        if ($stmt->execute()) {
            $count = $stmt->affected_rows;
            if ($count > 0) {
                echo "<div class='success-box'>✓ Updated $count '{$mapping['status']}' application(s) to '{$mapping['workflow']}'</div>";
                $fixed_count += $count;
            }
        } else {
            $errors[] = "Error updating {$mapping['status']}: " . $conn->error;
        }
    }
    
    // Show results
    echo "<h2>Migration Complete</h2>";
    if ($fixed_count > 0) {
        echo "<div class='success-box'><strong>✓ Successfully fixed $fixed_count application(s)</strong></div>";
    } else {
        echo "<div class='info-box'>No applications needed to be fixed. All workflow stages are already set correctly.</div>";
    }
    
    if (!empty($errors)) {
        echo "<div class='error-box'><strong>Errors encountered:</strong><ul>";
        foreach ($errors as $error) {
            echo "<li>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul></div>";
    }
    
} else {
    // Show current state
    echo "<div class='info-box'>";
    echo "<strong>About This Tool:</strong><br>";
    echo "This tool fixes applications that don't have a workflow_stage set. ";
    echo "It assigns the correct workflow_stage based on the application's current status.";
    echo "</div>";
    
    echo "<h2>Current State Analysis</h2>";
    
    // Count applications without workflow_stage
    $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE workflow_stage IS NULL OR workflow_stage = ''");
    $missing_count = $result->fetch_assoc()['count'];
    
    if ($missing_count > 0) {
        echo "<div class='warning-box'><strong>⚠ Found $missing_count application(s) without workflow_stage</strong></div>";
    } else {
        echo "<div class='success-box'><strong>✓ All applications have workflow_stage set!</strong></div>";
    }
    
    // Show breakdown by status for applications without workflow_stage
    if ($missing_count > 0) {
        echo "<h3>Applications Without Workflow Stage (By Status)</h3>";
        $result = $conn->query("SELECT status, COUNT(*) as count 
                                FROM job_applicants 
                                WHERE workflow_stage IS NULL OR workflow_stage = ''
                                GROUP BY status");
        
        if ($result->num_rows > 0) {
            echo "<table>";
            echo "<tr><th>Status</th><th>Count</th><th>Will Be Set To</th></tr>";
            while ($row = $result->fetch_assoc()) {
                $will_be = 'secretary_review';
                if (stripos($row['status'], 'Interview') !== false || 
                    stripos($row['status'], 'Demo') !== false || 
                    stripos($row['status'], 'Hired') !== false) {
                    $will_be = 'department_head_review (or specific stage)';
                }
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                echo "<td>{$row['count']}</td>";
                echo "<td>$will_be</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        echo "<form method='POST'>";
        echo "<button type='submit' name='fix_workflows' onclick='return confirm(\"This will update $missing_count application(s). Continue?\")'>Fix Workflow Stages Now</button>";
        echo "</form>";
    }
    
    // Show current distribution
    echo "<h2>Current Workflow Stage Distribution</h2>";
    $result = $conn->query("SELECT 
                                COALESCE(workflow_stage, '(NULL/Empty)') as stage, 
                                COUNT(*) as count 
                            FROM job_applicants 
                            GROUP BY workflow_stage 
                            ORDER BY count DESC");
    
    if ($result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>Workflow Stage</th><th>Count</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['stage']) . "</td>";
            echo "<td>{$row['count']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

$conn->close();

echo "<a href='index.php' class='back-link'>← Back to Admin Dashboard</a>";
echo "<br><a href='debug_pending_stats.php' class='back-link' style='background: #6366f1; margin-left: 10px;'>View Debug Stats</a>";
echo "</div>";
?>
