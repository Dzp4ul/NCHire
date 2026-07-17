<?php
// Quick database check for hired applicants
$conn = new mysqli('127.0.0.1', 'root', '', 'nchire');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Hired Applicants Database Check</h2>";
echo "<hr>";

// Check 1: Count by status
$result1 = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE status IN ('Initially Hired', 'Permanently Hired', 'Hired')");
$row1 = $result1->fetch_assoc();
echo "<p><strong>Count with hired status:</strong> " . $row1['count'] . "</p>";

// Check 2: Count by workflow_stage
$result2 = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE workflow_stage = 'hired'");
$row2 = $result2->fetch_assoc();
echo "<p><strong>Count with hired workflow_stage:</strong> " . $row2['count'] . "</p>";

// Check 3: Detailed breakdown
echo "<h3>Detailed Breakdown:</h3>";
$result3 = $conn->query("SELECT status, workflow_stage, assigned_to_department, COUNT(*) as count 
                         FROM job_applicants 
                         WHERE status IN ('Initially Hired', 'Permanently Hired', 'Hired') OR workflow_stage = 'hired'
                         GROUP BY status, workflow_stage, assigned_to_department");

if ($result3->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Status</th><th>Workflow Stage</th><th>Department</th><th>Count</th></tr>";
    while ($row = $result3->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['status'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['workflow_stage'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['assigned_to_department'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p><strong>No hired applicants found in database!</strong></p>";
}

// Check 4: Sample records
echo "<h3>Sample Hired Records:</h3>";
$result4 = $conn->query("SELECT id, full_name, position, status, workflow_stage, assigned_to_department 
                         FROM job_applicants 
                         WHERE status IN ('Initially Hired', 'Permanently Hired', 'Hired') OR workflow_stage = 'hired'
                         LIMIT 5");

if ($result4->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Position</th><th>Status</th><th>Workflow</th><th>Department</th></tr>";
    while ($row = $result4->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['position']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['workflow_stage'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['assigned_to_department'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No sample records available.</p>";
}

$conn->close();
?>
