<?php
// DIAGNOSTIC - Show exactly what's in the database
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

echo "<h1>DATABASE DIAGNOSTIC</h1>";
echo "<p>Showing EXACT data from admin_activity table:</p>";

$result = $conn->query("SELECT id, activity_type, description, user_name, created_at FROM admin_activity ORDER BY created_at DESC LIMIT 20");

echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #333; color: white;'>";
echo "<th>ID</th><th>Activity Type</th><th>Description</th><th>User</th><th>Created</th><th>ACTION</th>";
echo "</tr>";

$has_bad_entries = false;

while ($row = $result->fetch_assoc()) {
    $is_bad = (empty($row['activity_type']) || $row['activity_type'] == 'application');
    $bg_color = $is_bad ? 'background: #ff0000; color: white;' : '';
    
    if ($is_bad) $has_bad_entries = true;
    
    echo "<tr style='$bg_color'>";
    echo "<td>{$row['id']}</td>";
    echo "<td><strong>" . ($row['activity_type'] ?: 'NULL/EMPTY') . "</strong></td>";
    echo "<td>" . htmlspecialchars($row['description']) . "</td>";
    echo "<td>" . htmlspecialchars($row['user_name']) . "</td>";
    echo "<td>{$row['created_at']}</td>";
    
    if ($is_bad) {
        echo "<td><a href='?delete={$row['id']}' style='background: red; color: white; padding: 5px 10px; text-decoration: none;'>DELETE</a></td>";
    } else {
        echo "<td style='color: green;'>✓ OK</td>";
    }
    echo "</tr>";
}

echo "</table>";

// Handle deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM admin_activity WHERE id = $id");
    echo "<script>alert('Entry deleted!'); window.location='diagnose_now.php';</script>";
}

// Delete all bad entries button
if ($has_bad_entries) {
    echo "<br><br><div style='background: red; color: white; padding: 20px; text-align: center;'>";
    echo "<h2>⚠️ BAD ENTRIES FOUND (shown in RED)</h2>";
    echo "<a href='?delete_all=1' style='background: darkred; color: white; padding: 15px 30px; text-decoration: none; font-size: 18px; display: inline-block; margin-top: 10px;'>DELETE ALL BAD ENTRIES NOW</a>";
    echo "</div>";
}

if (isset($_GET['delete_all'])) {
    $conn->query("DELETE FROM admin_activity WHERE activity_type IS NULL OR activity_type = '' OR activity_type = 'application'");
    echo "<script>alert('All bad entries deleted!'); window.location='diagnose_now.php';</script>";
}

$conn->close();
?>
<br><br>
<a href="index.php" style="background: blue; color: white; padding: 15px 30px; text-decoration: none; font-size: 18px;">Go to Dashboard</a>
