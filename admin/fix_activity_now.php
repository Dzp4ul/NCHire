<?php
// DIRECT FIX - Delete all problematic activity entries
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Fixing Activity Log - DIRECT APPROACH</h1>";

// Step 1: Show current entries
echo "<h2>Step 1: Current Entries in admin_activity</h2>";
$result = $conn->query("SELECT id, activity_type, description, created_at FROM admin_activity ORDER BY created_at DESC LIMIT 20");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Activity Type</th><th>Description</th><th>Created At</th></tr>";
while ($row = $result->fetch_assoc()) {
    $highlight = (empty($row['activity_type']) || $row['activity_type'] == 'application') ? 'style="background: red; color: white;"' : '';
    echo "<tr $highlight>";
    echo "<td>{$row['id']}</td>";
    echo "<td>" . ($row['activity_type'] ?: 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($row['description']) . "</td>";
    echo "<td>{$row['created_at']}</td>";
    echo "</tr>";
}
echo "</table>";

// Step 2: DELETE problematic entries
echo "<br><br><h2>Step 2: DELETING Problematic Entries...</h2>";

// Delete entries with NULL activity_type
$result1 = $conn->query("DELETE FROM admin_activity WHERE activity_type IS NULL OR activity_type = ''");
echo "✓ Deleted entries with NULL activity_type: " . $conn->affected_rows . " rows<br>";

// Delete entries with 'application' activity_type
$result2 = $conn->query("DELETE FROM admin_activity WHERE activity_type = 'application'");
echo "✓ Deleted entries with 'application' activity_type: " . $conn->affected_rows . " rows<br>";

// Step 3: Show remaining entries
echo "<br><br><h2>Step 3: Remaining Entries After Cleanup</h2>";
$result = $conn->query("SELECT id, activity_type, description, created_at FROM admin_activity ORDER BY created_at DESC LIMIT 20");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Activity Type</th><th>Description</th><th>Created At</th></tr>";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['activity_type']}</td>";
        echo "<td>" . htmlspecialchars($row['description']) . "</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='4'>No activities found (table is empty)</td></tr>";
}
echo "</table>";

echo "<br><br><h2 style='color: green;'>✓✓✓ CLEANUP COMPLETE! ✓✓✓</h2>";
echo "<p><strong>Now go to your admin dashboard and do a HARD REFRESH (Ctrl + Shift + R)</strong></p>";
echo "<p><a href='index.php' style='background: blue; color: white; padding: 10px; text-decoration: none;'>Go to Admin Dashboard</a></p>";

$conn->close();
?>
