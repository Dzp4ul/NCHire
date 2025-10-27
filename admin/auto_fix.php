<?php
// AUTO-FIX DATABASE - Run once to clean everything
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed");
}

echo "<!DOCTYPE html><html><head><title>Auto-Fix Database</title></head><body style='font-family: Arial; padding: 20px;'>";
echo "<h1 style='color: #1e3a8a;'>🔧 AUTO-FIX: Cleaning Database</h1>";

// Step 1: Show what we're about to delete
echo "<h2>Step 1: Checking for bad entries...</h2>";
$bad_entries = $conn->query("SELECT id, activity_type, description FROM admin_activity WHERE activity_type IS NULL OR activity_type = '' OR activity_type = 'application' ORDER BY id DESC");

if ($bad_entries->num_rows > 0) {
    echo "<div style='background: #fee; border: 2px solid red; padding: 15px; margin: 10px 0;'>";
    echo "<strong style='color: red;'>Found {$bad_entries->num_rows} BAD entries:</strong><br><br>";
    while ($row = $bad_entries->fetch_assoc()) {
        echo "ID: {$row['id']} | Type: " . ($row['activity_type'] ?: 'NULL') . " | " . htmlspecialchars(substr($row['description'], 0, 60)) . "...<br>";
    }
    echo "</div>";
    
    // Step 2: DELETE them
    echo "<h2>Step 2: Deleting bad entries...</h2>";
    $result1 = $conn->query("DELETE FROM admin_activity WHERE activity_type IS NULL");
    echo "✓ Deleted NULL entries: {$conn->affected_rows} rows<br>";
    
    $result2 = $conn->query("DELETE FROM admin_activity WHERE activity_type = ''");
    echo "✓ Deleted empty entries: {$conn->affected_rows} rows<br>";
    
    $result3 = $conn->query("DELETE FROM admin_activity WHERE activity_type = 'application'");
    echo "✓ Deleted 'application' entries: {$conn->affected_rows} rows<br>";
    
    echo "<div style='background: #dfd; border: 2px solid green; padding: 15px; margin: 20px 0; text-align: center;'>";
    echo "<h2 style='color: green; margin: 0;'>✓✓✓ DATABASE CLEANED! ✓✓✓</h2>";
    echo "</div>";
    
} else {
    echo "<div style='background: #dfd; border: 2px solid green; padding: 15px; margin: 10px 0;'>";
    echo "<strong style='color: green;'>✓ No bad entries found! Database is clean.</strong>";
    echo "</div>";
}

// Step 3: Show remaining entries
echo "<h2>Step 3: Current entries in admin_activity:</h2>";
$good_entries = $conn->query("SELECT activity_type, description, user_name, created_at FROM admin_activity ORDER BY created_at DESC LIMIT 10");

if ($good_entries->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #1e3a8a; color: white;'><th>Type</th><th>Description</th><th>User</th><th>Created</th></tr>";
    while ($row = $good_entries->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>{$row['activity_type']}</strong></td>";
        echo "<td>" . htmlspecialchars($row['description']) . "</td>";
        echo "<td>{$row['user_name']}</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No activities in database.</p>";
}

$conn->close();

echo "<br><br>";
echo "<div style='text-align: center; margin: 30px 0;'>";
echo "<a href='index.php' style='background: #1e3a8a; color: white; padding: 20px 40px; text-decoration: none; font-size: 20px; border-radius: 5px; display: inline-block;'>➜ GO TO DASHBOARD (Press Ctrl+Shift+R to refresh)</a>";
echo "</div>";

echo "<hr>";
echo "<p style='color: #666;'><strong>Note:</strong> After clicking the button above, make sure to do a HARD REFRESH (Ctrl+Shift+R or Ctrl+F5) to clear browser cache!</p>";

echo "</body></html>";
?>
