<?php
/**
 * Database Migration Script
 * Changes all instances of "Computer Science" to "Computing Studies"
 * 
 * This updates:
 * 1. Department names in job table
 * 2. Department names in job_applicants table
 * 3. Department names in admin_users table
 * 4. Subject names (Computer Science Professional Subjects → Computing Studies Professional Subjects)
 */

$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Database Migration: Computer Science → Computing Studies</h2>";
echo "<hr>";

$updates = [];

// 1. Update job table - department_role column
echo "<h3>1. Updating job table (department_role)...</h3>";
$sql1 = "UPDATE job SET department_role = 'Computing Studies' WHERE department_role = 'Computer Science'";
if ($conn->query($sql1)) {
    $affected = $conn->affected_rows;
    echo "<p style='color: green;'>✓ Updated $affected job posting(s)</p>";
    $updates[] = "Jobs: $affected";
} else {
    echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
}

// 2. Update job table - subject column
echo "<h3>2. Updating job table (subject)...</h3>";
$sql2 = "UPDATE job SET subject = 'Computing Studies Professional Subjects' WHERE subject = 'Computer Science Professional Subjects'";
if ($conn->query($sql2)) {
    $affected = $conn->affected_rows;
    echo "<p style='color: green;'>✓ Updated $affected job subject(s)</p>";
    $updates[] = "Job Subjects: $affected";
} else {
    echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
}

// 3. Update job_applicants table - assigned_to_department column
echo "<h3>3. Updating job_applicants table (assigned_to_department)...</h3>";
$sql3 = "UPDATE job_applicants SET assigned_to_department = 'Computing Studies' WHERE assigned_to_department = 'Computer Science'";
if ($conn->query($sql3)) {
    $affected = $conn->affected_rows;
    echo "<p style='color: green;'>✓ Updated $affected applicant(s)</p>";
    $updates[] = "Applicants: $affected";
} else {
    echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
}

// 4. Update admin_users table - department column
echo "<h3>4. Updating admin_users table (department)...</h3>";
$sql4 = "UPDATE admin_users SET department = 'Computing Studies' WHERE department = 'Computer Science'";
if ($conn->query($sql4)) {
    $affected = $conn->affected_rows;
    echo "<p style='color: green;'>✓ Updated $affected admin user(s)</p>";
    $updates[] = "Admin Users: $affected";
} else {
    echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
}

echo "<hr>";
echo "<h3>Summary of Updates:</h3>";
echo "<ul>";
foreach ($updates as $update) {
    echo "<li>$update records updated</li>";
}
echo "</ul>";

echo "<hr>";
echo "<h3>Verification - Current Data:</h3>";

// Show updated job records
echo "<h4>Jobs with Computing Studies:</h4>";
$verify1 = $conn->query("SELECT id, job_title, department_role FROM job WHERE department_role = 'Computing Studies' LIMIT 5");
if ($verify1 && $verify1->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Job Title</th><th>Department</th></tr>";
    while ($row = $verify1->fetch_assoc()) {
        echo "<tr><td>{$row['id']}</td><td>{$row['job_title']}</td><td>{$row['department_role']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No jobs found with Computing Studies department.</p>";
}

// Show updated subjects
echo "<h4>Jobs with Computing Studies Professional Subjects:</h4>";
$verify2 = $conn->query("SELECT id, job_title, subject FROM job WHERE subject = 'Computing Studies Professional Subjects' LIMIT 5");
if ($verify2 && $verify2->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Job Title</th><th>Subject</th></tr>";
    while ($row = $verify2->fetch_assoc()) {
        echo "<tr><td>{$row['id']}</td><td>{$row['job_title']}</td><td>{$row['subject']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No jobs found with Computing Studies Professional Subjects.</p>";
}

// Check for any remaining "Computer Science" entries
echo "<hr>";
echo "<h3>Checking for remaining 'Computer Science' entries:</h3>";

$check1 = $conn->query("SELECT COUNT(*) as count FROM job WHERE department_role = 'Computer Science'");
$count1 = $check1->fetch_assoc()['count'];
echo "<p>Jobs with 'Computer Science' department: <strong>$count1</strong></p>";

$check2 = $conn->query("SELECT COUNT(*) as count FROM job WHERE subject = 'Computer Science Professional Subjects'");
$count2 = $check2->fetch_assoc()['count'];
echo "<p>Jobs with 'Computer Science Professional Subjects': <strong>$count2</strong></p>";

$check3 = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE assigned_to_department = 'Computer Science'");
$count3 = $check3->fetch_assoc()['count'];
echo "<p>Applicants assigned to 'Computer Science': <strong>$count3</strong></p>";

$check4 = $conn->query("SELECT COUNT(*) as count FROM admin_users WHERE department = 'Computer Science'");
$count4 = $check4->fetch_assoc()['count'];
echo "<p>Admin users in 'Computer Science': <strong>$count4</strong></p>";

$conn->close();

echo "<hr>";
echo "<p style='color: green; font-weight: bold;'>✅ Migration completed!</p>";
echo "<p><a href='index.php'>← Back to Admin Dashboard</a></p>";
?>
