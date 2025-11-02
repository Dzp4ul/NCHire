<?php
/**
 * Auto-Close Expired Jobs
 * This script automatically sets job status to 'Closed' for jobs past their deadline
 * Can be run manually or set up as a cron job to run daily
 */

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if status column exists
$check_column = "SHOW COLUMNS FROM job LIKE 'status'";
$result = $conn->query($check_column);

if ($result->num_rows == 0) {
    echo "⚠️ Status column doesn't exist. Please run add_job_status_column.php first.<br>";
    echo "<a href='add_job_status_column.php'>→ Run Setup Script</a>";
    exit;
}

// Auto-close jobs with expired deadlines
$update_query = "UPDATE job 
                 SET status = 'Closed' 
                 WHERE application_deadline < CURDATE() 
                 AND status = 'Active'";

$result = $conn->query($update_query);

if ($result) {
    $affected_rows = $conn->affected_rows;
    
    echo "<h2>Auto-Close Expired Jobs</h2>";
    echo "<div style='padding: 20px; background: #f0f9ff; border-left: 4px solid #3b82f6; margin: 20px 0;'>";
    
    if ($affected_rows > 0) {
        echo "✓ Successfully closed <strong>$affected_rows</strong> expired job(s)!<br>";
        echo "<small>Jobs with deadlines before today have been set to 'Closed' status.</small>";
    } else {
        echo "✓ All jobs are up to date!<br>";
        echo "<small>No expired jobs found that need to be closed.</small>";
    }
    
    echo "</div>";
    
    // Show current job status summary
    $summary_query = "SELECT 
                        status,
                        COUNT(*) as count,
                        SUM(CASE WHEN application_deadline < CURDATE() THEN 1 ELSE 0 END) as expired,
                        SUM(CASE WHEN application_deadline >= CURDATE() THEN 1 ELSE 0 END) as active_deadline
                      FROM job 
                      GROUP BY status";
    
    $summary_result = $conn->query($summary_query);
    
    echo "<h3>Job Status Summary</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f3f4f6;'>";
    echo "<th>Status</th><th>Total Jobs</th><th>Expired Deadline</th><th>Active Deadline</th>";
    echo "</tr>";
    
    while ($row = $summary_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . $row['status'] . "</strong></td>";
        echo "<td>" . $row['count'] . "</td>";
        echo "<td>" . ($row['expired'] ?? 0) . "</td>";
        echo "<td>" . ($row['active_deadline'] ?? 0) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} else {
    echo "✗ Error closing expired jobs: " . $conn->error;
}

$conn->close();

echo "<br><br><a href='index.php'>← Back to Admin Panel</a>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 40px auto;
    padding: 20px;
}
h2 {
    color: #1e3a8a;
}
table {
    margin-top: 20px;
}
th {
    text-align: left;
}
a {
    color: #3b82f6;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
</style>
