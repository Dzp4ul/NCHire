<?php
// DIRECT DATABASE CLEANUP - Delete all bad entries NOW
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>DELETING BAD ENTRIES FROM DATABASE</h1>";

// Count before deletion
$count_before = $conn->query("SELECT COUNT(*) as count FROM admin_activity WHERE activity_type IS NULL OR activity_type = '' OR activity_type = 'application'")->fetch_assoc()['count'];
echo "<p>Found <strong style='color: red;'>$count_before</strong> bad entries to delete...</p>";

// DELETE entries with NULL activity_type
$conn->query("DELETE FROM admin_activity WHERE activity_type IS NULL");
echo "✓ Deleted NULL entries<br>";

// DELETE entries with empty activity_type
$conn->query("DELETE FROM admin_activity WHERE activity_type = ''");
echo "✓ Deleted empty entries<br>";

// DELETE entries with 'application' activity_type
$conn->query("DELETE FROM admin_activity WHERE activity_type = 'application'");
echo "✓ Deleted 'application' entries<br>";

// Count after deletion
$count_after = $conn->query("SELECT COUNT(*) as count FROM admin_activity")->fetch_assoc()['count'];

echo "<br><h2 style='color: green;'>✓✓✓ SUCCESS! ✓✓✓</h2>";
echo "<p>Deleted <strong>$count_before</strong> bad entries</p>";
echo "<p>Remaining entries: <strong>$count_after</strong></p>";

echo "<br><h3>Remaining entries in database:</h3>";
$result = $conn->query("SELECT id, activity_type, description, created_at FROM admin_activity ORDER BY created_at DESC LIMIT 10");
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr style='background: #1e3a8a; color: white;'><th>ID</th><th>Type</th><th>Description</th><th>Created At</th></tr>";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td><strong>{$row['activity_type']}</strong></td>";
        echo "<td>" . htmlspecialchars(substr($row['description'], 0, 80)) . "...</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='4'>No activities found</td></tr>";
}
echo "</table>";

echo "<br><br><div style='background: green; color: white; padding: 20px; text-align: center; font-size: 20px;'>";
echo "✓ DATABASE CLEANED SUCCESSFULLY!";
echo "</div>";

echo "<br><a href='index.php' style='background: blue; color: white; padding: 15px 30px; text-decoration: none; font-size: 18px; display: inline-block;'>➜ GO TO DASHBOARD NOW</a>";

$conn->close();
?>
