<?php
// Quick diagnostic to check what's in admin_activity table
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Recent Admin Activity in Database:</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Activity Type</th><th>Description</th><th>User Name</th><th>Created At</th></tr>";

$result = $conn->query("SELECT * FROM admin_activity ORDER BY created_at DESC LIMIT 20");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td style='background: " . (empty($row['activity_type']) ? 'red' : 'lightgreen') . "'>" . 
             (empty($row['activity_type']) ? 'NULL/EMPTY' : htmlspecialchars($row['activity_type'])) . "</td>";
        echo "<td>" . htmlspecialchars($row['description']) . "</td>";
        echo "<td>" . htmlspecialchars($row['user_name']) . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5'>No activities found</td></tr>";
}

echo "</table>";

echo "<br><br><h3>Fix: Delete entries with NULL activity_type?</h3>";
echo "<a href='?fix=1' style='background: red; color: white; padding: 10px; text-decoration: none;'>DELETE NULL ENTRIES</a>";

if (isset($_GET['fix']) && $_GET['fix'] == 1) {
    $conn->query("DELETE FROM admin_activity WHERE activity_type IS NULL OR activity_type = ''");
    echo "<br><br><strong style='color: green;'>✓ Deleted entries with NULL activity_type!</strong>";
    echo "<br><a href='check_activity.php'>Refresh to see results</a>";
}

$conn->close();
?>
