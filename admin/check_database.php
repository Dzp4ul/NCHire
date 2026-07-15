<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
<html>
<head>
    <title>Database Check</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        h2 { color: #4ec9b0; border-bottom: 2px solid #569cd6; padding-bottom: 5px; margin-top: 30px; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; background: #252526; }
        th, td { padding: 8px; border: 1px solid #3e3e42; text-align: left; }
        th { background: #1e1e1e; color: #569cd6; }
        .null { color: #ce9178; font-style: italic; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        code { background: #1e1e1e; padding: 2px 6px; border-radius: 3px; color: #ce9178; }
    </style>
</head>
<body>

<h1 style="color: #569cd6;">🔍 NCHire Database Diagnostic</h1>

<h2>1. Check job_applicants Table Structure</h2>
<?php
$columns_query = "SHOW COLUMNS FROM job_applicants";
$columns_result = $conn->query($columns_query);

echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
$has_assigned_column = false;
while ($col = $columns_result->fetch_assoc()) {
    if ($col['Field'] == 'assigned_to_department') {
        $has_assigned_column = true;
        echo "<tr style='background: #1a472a;'>";
    } else {
        echo "<tr>";
    }
    echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Default'] ?: 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

if ($has_assigned_column) {
    echo "<p class='success'>✅ Column 'assigned_to_department' EXISTS</p>";
} else {
    echo "<p class='error'>❌ Column 'assigned_to_department' MISSING - Run migration!</p>";
}
?>

<h2>2. Recent Job Applications (Last 10)</h2>
<?php
$apps_query = "SELECT id, full_name, position, job_id, assigned_to_department, status, applied_date 
               FROM job_applicants 
               ORDER BY id DESC 
               LIMIT 10";
$apps_result = $conn->query($apps_query);

echo "<table>";
echo "<tr><th>ID</th><th>Applicant Name</th><th>Position</th><th>Job ID</th><th>Assigned Department</th><th>Status</th><th>Applied Date</th></tr>";
while ($app = $apps_result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $app['id'] . "</td>";
    echo "<td>" . htmlspecialchars($app['full_name']) . "</td>";
    echo "<td>" . htmlspecialchars($app['position']) . "</td>";
    echo "<td>" . $app['job_id'] . "</td>";
    echo "<td>";
    if (empty($app['assigned_to_department'])) {
        echo "<span class='null'>NULL</span>";
    } else {
        echo "<span class='success'>" . htmlspecialchars($app['assigned_to_department']) . "</span>";
    }
    echo "</td>";
    echo "<td>" . htmlspecialchars($app['status']) . "</td>";
    echo "<td>" . date('Y-m-d H:i', strtotime($app['applied_date'])) . "</td>";
    echo "</tr>";
}
echo "</table>";
?>

<h2>3. Jobs and Their Departments</h2>
<?php
$jobs_query = "SELECT id, job_title, department_role 
               FROM job 
               ORDER BY department_role, job_title";
$jobs_result = $conn->query($jobs_query);

echo "<table>";
echo "<tr><th>Job ID</th><th>Job Title</th><th>Department</th></tr>";
while ($job = $jobs_result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $job['id'] . "</td>";
    echo "<td>" . htmlspecialchars($job['job_title']) . "</td>";
    echo "<td><strong>" . htmlspecialchars($job['department_role']) . "</strong></td>";
    echo "</tr>";
}
echo "</table>";
?>

<h2>4. Admin Users and Their Departments</h2>
<?php
$admins_query = "SELECT id, full_name, email, role, department, status 
                 FROM admin_users 
                 ORDER BY role, department";
$admins_result = $conn->query($admins_query);

echo "<table>";
echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th></tr>";
while ($admin = $admins_result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $admin['id'] . "</td>";
    echo "<td>" . htmlspecialchars($admin['full_name']) . "</td>";
    echo "<td>" . htmlspecialchars($admin['email']) . "</td>";
    echo "<td>";
    if ($admin['role'] == 'Department Head') {
        echo "<span class='warning'>" . htmlspecialchars($admin['role']) . "</span>";
    } else {
        echo htmlspecialchars($admin['role']);
    }
    echo "</td>";
    echo "<td>";
    if (empty($admin['department'])) {
        echo "<span class='null'>NULL</span>";
    } else {
        echo "<strong>" . htmlspecialchars($admin['department']) . "</strong>";
    }
    echo "</td>";
    echo "<td>" . htmlspecialchars($admin['status']) . "</td>";
    echo "</tr>";
}
echo "</table>";
?>

<h2>5. Department Matching Test</h2>
<?php
// Get all unique departments from jobs
$dept_jobs = [];
$jobs_result = $conn->query("SELECT DISTINCT department_role FROM job WHERE department_role IS NOT NULL");
while ($row = $jobs_result->fetch_assoc()) {
    $dept_jobs[] = $row['department_role'];
}

// Get all unique departments from admin_users
$dept_admins = [];
$admins_result = $conn->query("SELECT DISTINCT department FROM admin_users WHERE department IS NOT NULL AND role = 'Department Head'");
while ($row = $admins_result->fetch_assoc()) {
    $dept_admins[] = $row['department'];
}

// Get all unique departments from applications
$dept_apps = [];
$apps_result = $conn->query("SELECT DISTINCT assigned_to_department FROM job_applicants WHERE assigned_to_department IS NOT NULL");
while ($row = $apps_result->fetch_assoc()) {
    $dept_apps[] = $row['assigned_to_department'];
}

echo "<table>";
echo "<tr><th>Source</th><th>Departments</th></tr>";
echo "<tr><td><strong>Jobs Table</strong></td><td>";
if (empty($dept_jobs)) {
    echo "<span class='null'>No departments found</span>";
} else {
    foreach ($dept_jobs as $dept) {
        echo "<code>" . htmlspecialchars($dept) . "</code> ";
    }
}
echo "</td></tr>";

echo "<tr><td><strong>Admin Users (Dept Heads)</strong></td><td>";
if (empty($dept_admins)) {
    echo "<span class='null'>No departments found</span>";
} else {
    foreach ($dept_admins as $dept) {
        echo "<code>" . htmlspecialchars($dept) . "</code> ";
    }
}
echo "</td></tr>";

echo "<tr><td><strong>Applications</strong></td><td>";
if (empty($dept_apps)) {
    echo "<span class='null'>No departments found</span>";
} else {
    foreach ($dept_apps as $dept) {
        echo "<code>" . htmlspecialchars($dept) . "</code> ";
    }
}
echo "</td></tr>";
echo "</table>";

// Check for mismatches
echo "<h3>Department Name Comparison:</h3>";
$all_depts = array_unique(array_merge($dept_jobs, $dept_admins, $dept_apps));
foreach ($all_depts as $dept) {
    $in_jobs = in_array($dept, $dept_jobs) ? '✅' : '❌';
    $in_admins = in_array($dept, $dept_admins) ? '✅' : '❌';
    $in_apps = in_array($dept, $dept_apps) ? '✅' : '❌';
    
    echo "<p><code>" . htmlspecialchars($dept) . "</code>: ";
    echo "Jobs=$in_jobs | Dept Heads=$in_admins | Applications=$in_apps</p>";
}
?>

<h2>6. SQL Query Test - What Department Head Should See</h2>
<?php
// Test query for each department head
$dept_heads_query = "SELECT id, full_name, department FROM admin_users WHERE role = 'Department Head'";
$dept_heads_result = $conn->query($dept_heads_query);

while ($dept_head = $dept_heads_result->fetch_assoc()) {
    $dept = $dept_head['department'];
    echo "<h3>" . htmlspecialchars($dept_head['full_name']) . " (Department: " . htmlspecialchars($dept) . ")</h3>";
    
    if (empty($dept)) {
        echo "<p class='error'>⚠️ No department assigned to this user!</p>";
        continue;
    }
    
    $test_query = "SELECT id, full_name, position, assigned_to_department, status 
                   FROM job_applicants 
                   WHERE status != 'Rejected' 
                   AND assigned_to_department = ?";
    $stmt = $conn->prepare($test_query);
    $stmt->bind_param("s", $dept);
    $stmt->execute();
    $test_result = $stmt->get_result();
    
    if ($test_result->num_rows > 0) {
        echo "<p class='success'>✅ Should see " . $test_result->num_rows . " application(s):</p>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>Position</th><th>Dept</th><th>Status</th></tr>";
        while ($app = $test_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $app['id'] . "</td>";
            echo "<td>" . htmlspecialchars($app['full_name']) . "</td>";
            echo "<td>" . htmlspecialchars($app['position']) . "</td>";
            echo "<td>" . htmlspecialchars($app['assigned_to_department']) . "</td>";
            echo "<td>" . htmlspecialchars($app['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>⚠️ No applications found for department: <code>" . htmlspecialchars($dept) . "</code></p>";
        echo "<p>Possible reasons:</p>";
        echo "<ul>";
        echo "<li>No applications submitted for jobs in this department yet</li>";
        echo "<li>Department name mismatch (check spelling and case)</li>";
        echo "<li>Applications need department assignment (run migration)</li>";
        echo "</ul>";
    }
    $stmt->close();
}
?>

<h2>7. Recommendations</h2>
<?php
$issues = [];

// Check if column exists
if (!$has_assigned_column) {
    $issues[] = "❌ Run migration: <code>admin/add_department_routing.php</code>";
}

// Check if applications have departments
$null_apps = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE assigned_to_department IS NULL")->fetch_assoc()['count'];
if ($null_apps > 0) {
    $issues[] = "⚠️ $null_apps applications without department assignment - run migration";
}

// Check if department heads have departments
$no_dept_heads = $conn->query("SELECT COUNT(*) as count FROM admin_users WHERE role = 'Department Head' AND (department IS NULL OR department = '')")->fetch_assoc()['count'];
if ($no_dept_heads > 0) {
    $issues[] = "⚠️ $no_dept_heads Department Head(s) without department assignment";
}

// Check department name consistency
if (!empty($dept_jobs) && !empty($dept_admins)) {
    $missing_in_admins = array_diff($dept_jobs, $dept_admins);
    if (!empty($missing_in_admins)) {
        $issues[] = "⚠️ Department(s) in jobs but no matching Department Head: " . implode(', ', array_map('htmlspecialchars', $missing_in_admins));
    }
}

if (empty($issues)) {
    echo "<p class='success'>✅ No major issues detected!</p>";
    echo "<p>If applications still don't show, try:</p>";
    echo "<ul>";
    echo "<li>Log out and log back in as Department Head</li>";
    echo "<li>Submit a new test application</li>";
    echo "<li>Check browser console for JavaScript errors</li>";
    echo "</ul>";
} else {
    echo "<p class='error'>Issues found:</p>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>$issue</li>";
    }
    echo "</ul>";
}
?>

<p style="margin-top: 30px;">
    <a href="add_department_routing.php" style="background: #007acc; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">Run Migration</a>
    <a href="index.php" style="background: #3e3e42; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-left: 10px;">← Back to Dashboard</a>
</p>

</body>
</html>

<?php
$conn->close();
?>
