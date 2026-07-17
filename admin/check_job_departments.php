<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "";
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
    <title>Check Job Departments</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h1 { color: #1e3a8a; border-bottom: 3px solid #fbbf24; padding-bottom: 10px; }
        h2 { color: #3b82f6; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #1e3a8a; color: white; font-weight: bold; }
        tr:hover { background-color: #f5f5f5; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .warning { color: #f59e0b; font-weight: bold; }
        .info { background: #eff6ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .button { display: inline-block; padding: 10px 20px; background: #1e3a8a; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Job Department Assignment Diagnostic</h1>

    <h2>1. Check All Jobs in Database</h2>
    <?php
    $jobs_query = "SELECT id, job_title, department_role, job_type, locations, created_at FROM job ORDER BY id DESC";
    $jobs_result = $conn->query($jobs_query);
    
    if ($jobs_result && $jobs_result->num_rows > 0):
    ?>
        <table>
            <tr>
                <th>Job ID</th>
                <th>Job Title</th>
                <th>Department Role</th>
                <th>Job Type</th>
                <th>Location</th>
                <th>Created At</th>
            </tr>
            <?php 
            $with_dept = 0;
            $without_dept = 0;
            while ($job = $jobs_result->fetch_assoc()): 
                if (!empty($job['department_role'])) {
                    $with_dept++;
                } else {
                    $without_dept++;
                }
            ?>
            <tr>
                <td><?php echo $job['id']; ?></td>
                <td><?php echo htmlspecialchars($job['job_title']); ?></td>
                <td>
                    <?php if (!empty($job['department_role'])): ?>
                        <strong class="success"><?php echo htmlspecialchars($job['department_role']); ?></strong>
                    <?php else: ?>
                        <span class="error">⚠️ NO DEPARTMENT</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($job['job_type']); ?></td>
                <td><?php echo htmlspecialchars($job['locations']); ?></td>
                <td><?php echo date('M j, Y', strtotime($job['created_at'])); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
        
        <div class="info">
            <strong>Summary:</strong><br>
            Total Jobs: <?php echo $with_dept + $without_dept; ?><br>
            With Department: <?php echo $with_dept; ?> ✅<br>
            Without Department: <?php echo $without_dept; ?> 
            <?php if ($without_dept > 0): ?>
                <span class="error">⚠️ These jobs need department assignment!</span>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="warning">No jobs found in database.</p>
    <?php endif; ?>
</div>

<div class="container">
    <h2>2. Check Recent Applications and Their Department Assignments</h2>
    <?php
    $apps_query = "SELECT ja.id, ja.full_name, ja.position, ja.assigned_to_department, ja.applied_date, ja.job_id, j.department_role as job_department 
                   FROM job_applicants ja
                   LEFT JOIN job j ON ja.job_id = j.id
                   WHERE ja.status != 'Rejected'
                   ORDER BY ja.applied_date DESC
                   LIMIT 20";
    $apps_result = $conn->query($apps_query);
    
    if ($apps_result && $apps_result->num_rows > 0):
    ?>
        <table>
            <tr>
                <th>App ID</th>
                <th>Applicant Name</th>
                <th>Position</th>
                <th>Job Dept (from job table)</th>
                <th>Assigned Dept (in application)</th>
                <th>Match?</th>
                <th>Applied Date</th>
            </tr>
            <?php 
            $correct = 0;
            $incorrect = 0;
            $missing = 0;
            
            while ($app = $apps_result->fetch_assoc()): 
                $match = false;
                if (empty($app['assigned_to_department'])) {
                    $missing++;
                    $status = '<span class="error">MISSING</span>';
                } else if ($app['assigned_to_department'] === $app['job_department']) {
                    $correct++;
                    $match = true;
                    $status = '<span class="success">✅ MATCH</span>';
                } else {
                    $incorrect++;
                    $status = '<span class="warning">⚠️ MISMATCH</span>';
                }
            ?>
            <tr>
                <td><?php echo $app['id']; ?></td>
                <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                <td><?php echo htmlspecialchars($app['position']); ?></td>
                <td>
                    <?php if (!empty($app['job_department'])): ?>
                        <strong><?php echo htmlspecialchars($app['job_department']); ?></strong>
                    <?php else: ?>
                        <span class="error">NO JOB DEPT</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($app['assigned_to_department'])): ?>
                        <?php echo htmlspecialchars($app['assigned_to_department']); ?>
                    <?php else: ?>
                        <span class="error">NOT ASSIGNED</span>
                    <?php endif; ?>
                </td>
                <td><?php echo $status; ?></td>
                <td><?php echo date('M j, Y', strtotime($app['applied_date'])); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
        
        <div class="info">
            <strong>Summary:</strong><br>
            Correct Assignments: <?php echo $correct; ?> ✅<br>
            Missing Assignments: <?php echo $missing; ?> <span class="error">⚠️</span><br>
            Mismatched Assignments: <?php echo $incorrect; ?> <span class="warning">⚠️</span><br>
            <?php if ($missing > 0 || $incorrect > 0): ?>
                <br><strong class="error">ACTION REQUIRED: Run fix_application_departments.php to repair assignments</strong>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p>No applications found.</p>
    <?php endif; ?>
</div>

<div class="container">
    <h2>3. Test Department Routing Query</h2>
    <p>This shows what department heads should see based on their department:</p>
    <?php
    $departments = ['Computer Science', 'Education', 'Hospitality Management'];
    
    foreach ($departments as $dept):
        $test_query = "SELECT COUNT(*) as count FROM job_applicants WHERE assigned_to_department = ? AND status != 'Rejected'";
        $stmt = $conn->prepare($test_query);
        $stmt->bind_param("s", $dept);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        $stmt->close();
    ?>
        <div style="padding: 10px; background: #f9fafb; margin: 10px 0; border-left: 4px solid #3b82f6;">
            <strong><?php echo $dept; ?> Department Head</strong> would see: 
            <strong class="<?php echo $count > 0 ? 'success' : 'warning'; ?>"><?php echo $count; ?> applications</strong>
        </div>
    <?php endforeach; ?>
</div>

<div class="container">
    <h2>4. Current Admin Session Info</h2>
    <?php if (isset($_SESSION['admin_role'])): ?>
        <p><strong>Role:</strong> <?php echo htmlspecialchars($_SESSION['admin_role']); ?></p>
        <p><strong>Department:</strong> <?php echo htmlspecialchars($_SESSION['admin_department'] ?? 'None'); ?></p>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Unknown'); ?></p>
    <?php else: ?>
        <p class="warning">⚠️ No admin session found. Please log in.</p>
    <?php endif; ?>
</div>

<div style="text-align: center; margin-top: 30px;">
    <a href="index.php" class="button">← Back to Admin Dashboard</a>
</div>

</body>
</html>
<?php
$conn->close();
?>
