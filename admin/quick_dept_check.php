<?php
// Quick department diagnostic
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) die("Connection failed");

echo "<h2>Jobs and Their Departments:</h2>";
$jobs = $conn->query("SELECT id, job_title, department_role FROM job ORDER BY id DESC LIMIT 10");
echo "<table border='1'><tr><th>ID</th><th>Title</th><th>Department</th></tr>";
while ($j = $jobs->fetch_assoc()) {
    echo "<tr><td>{$j['id']}</td><td>{$j['job_title']}</td><td><strong>{$j['department_role']}</strong></td></tr>";
}
echo "</table>";

echo "<h2>Recent Applications and Assignments:</h2>";
$apps = $conn->query("SELECT id, full_name, position, job_id, assigned_to_department FROM job_applicants ORDER BY id DESC LIMIT 10");
echo "<table border='1'><tr><th>App ID</th><th>Name</th><th>Position</th><th>Job ID</th><th>Assigned Dept</th></tr>";
while ($a = $apps->fetch_assoc()) {
    $dept = $a['assigned_to_department'] ?: '<span style="color:red">NULL</span>';
    echo "<tr><td>{$a['id']}</td><td>{$a['full_name']}</td><td>{$a['position']}</td><td>{$a['job_id']}</td><td>{$dept}</td></tr>";
}
echo "</table>";

echo "<h2>Admin Users and Their Departments:</h2>";
$admins = $conn->query("SELECT id, full_name, role, department FROM admin_users");
echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Role</th><th>Department</th></tr>";
while ($ad = $admins->fetch_assoc()) {
    echo "<tr><td>{$ad['id']}</td><td>{$ad['full_name']}</td><td>{$ad['role']}</td><td><strong>{$ad['department']}</strong></td></tr>";
}
echo "</table>";

$conn->close();
?>
