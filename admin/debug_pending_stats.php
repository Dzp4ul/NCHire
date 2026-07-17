<?php
session_start();

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Pending Reviews Debug Information</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #4CAF50; color: white; }
    tr:nth-child(even) { background-color: #f2f2f2; }
    .info-box { background: #e3f2fd; padding: 15px; margin: 10px 0; border-left: 4px solid #2196F3; }
    .warning-box { background: #fff3e0; padding: 15px; margin: 10px 0; border-left: 4px solid #ff9800; }
    .success-box { background: #e8f5e9; padding: 15px; margin: 10px 0; border-left: 4px solid #4caf50; }
</style>";

// Check total applications
$result = $conn->query("SELECT COUNT(*) as total FROM job_applicants");
$total = $result->fetch_assoc()['total'];
echo "<div class='info-box'><strong>Total Applications in Database:</strong> $total</div>";

// Check applications by workflow_stage
echo "<h2>Applications by Workflow Stage</h2>";
$result = $conn->query("SELECT workflow_stage, COUNT(*) as count FROM job_applicants GROUP BY workflow_stage");
if ($result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>Workflow Stage</th><th>Count</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $stage = $row['workflow_stage'] ?: '(NULL/Empty)';
        echo "<tr><td>" . htmlspecialchars($stage) . "</td><td>{$row['count']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<div class='warning-box'>No applications found in any workflow stage</div>";
}

// Check applications by status
echo "<h2>Applications by Status</h2>";
$result = $conn->query("SELECT status, COUNT(*) as count FROM job_applicants GROUP BY status");
if ($result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>Status</th><th>Count</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>" . htmlspecialchars($row['status']) . "</td><td>{$row['count']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<div class='warning-box'>No applications found with any status</div>";
}

// Check secretary_review applications specifically
echo "<h2>Secretary Review Queue (What Secretary Should See)</h2>";
$result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE workflow_stage = 'secretary_review'");
$secretary_all = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE workflow_stage = 'secretary_review' AND status != 'Rejected'");
$secretary_active = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE workflow_stage = 'secretary_review' AND status = 'Rejected'");
$secretary_rejected = $result->fetch_assoc()['count'];

echo "<div class='info-box'>";
echo "<strong>Total in secretary_review:</strong> $secretary_all<br>";
echo "<strong>Active (Not Rejected):</strong> $secretary_active<br>";
echo "<strong>Rejected:</strong> $secretary_rejected";
echo "</div>";

if ($secretary_active > 0) {
    echo "<div class='success-box'><strong>✓ Secretary should see $secretary_active pending application(s)</strong></div>";
} else {
    echo "<div class='warning-box'><strong>⚠ No active applications in secretary_review stage!</strong></div>";
}

// Show actual applications in secretary_review
echo "<h2>Applications in Secretary Review Queue (Details)</h2>";
$result = $conn->query("SELECT id, full_name, position, applicant_email, status, workflow_stage, applied_date 
                        FROM job_applicants 
                        WHERE workflow_stage = 'secretary_review' 
                        ORDER BY applied_date DESC 
                        LIMIT 20");

if ($result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Position</th><th>Email</th><th>Status</th><th>Workflow Stage</th><th>Applied Date</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $highlight = ($row['status'] == 'Rejected') ? 'style="background-color: #ffebee;"' : '';
        echo "<tr $highlight>";
        echo "<td>{$row['id']}</td>";
        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['position']) . "</td>";
        echo "<td>" . htmlspecialchars($row['applicant_email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['workflow_stage']) . "</td>";
        echo "<td>" . htmlspecialchars($row['applied_date']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='warning-box'>No applications found in secretary_review workflow stage</div>";
}

// Check ALL recent applications (last 20)
echo "<h2>Recent Applications (All Stages)</h2>";
$result = $conn->query("SELECT id, full_name, position, status, workflow_stage, applied_date 
                        FROM job_applicants 
                        ORDER BY applied_date DESC 
                        LIMIT 20");

if ($result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Position</th><th>Status</th><th>Workflow Stage</th><th>Applied Date</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $stage = $row['workflow_stage'] ?: '(NULL)';
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['position']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($stage) . "</td>";
        echo "<td>" . htmlspecialchars($row['applied_date']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='warning-box'>No applications found in the database at all!</div>";
}

// Check if workflow_stage column exists
echo "<h2>Database Schema Check</h2>";
$result = $conn->query("SHOW COLUMNS FROM job_applicants LIKE 'workflow_stage'");
if ($result->num_rows > 0) {
    echo "<div class='success-box'>✓ workflow_stage column exists in job_applicants table</div>";
    $column = $result->fetch_assoc();
    echo "<pre>" . print_r($column, true) . "</pre>";
} else {
    echo "<div class='warning-box'>⚠ workflow_stage column does NOT exist in job_applicants table!</div>";
    echo "<p><strong>This is the problem!</strong> The workflow_stage column needs to be added to the database.</p>";
}

$conn->close();
?>

<hr>
<p><strong>Diagnosis Summary:</strong></p>
<ul>
    <li>If workflow_stage column doesn't exist, you need to run a database migration</li>
    <li>If there are no applications with workflow_stage = 'secretary_review', old applications may need to be updated</li>
    <li>New applications should automatically get workflow_stage = 'secretary_review'</li>
</ul>

<p><a href="index.php">← Back to Admin Dashboard</a></p>
