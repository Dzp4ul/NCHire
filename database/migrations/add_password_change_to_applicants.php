<?php
/**
 * Add password_change_required column to applicants table
 * This enables temporary password functionality for forgot password feature
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

echo "<h2>Adding password_change_required column to applicants table...</h2>";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("<p style='color: red;'>❌ Connection failed: " . $conn->connect_error . "</p>");
}

// Check if column already exists
$check_column = "SHOW COLUMNS FROM applicants LIKE 'password_change_required'";
$result = $conn->query($check_column);

if ($result->num_rows > 0) {
    echo "<p style='color: orange;'>⚠️ Column 'password_change_required' already exists!</p>";
} else {
    // Add the column after applicant_password
    $add_column = "ALTER TABLE applicants ADD COLUMN password_change_required TINYINT(1) DEFAULT 0 AFTER applicant_password";
    
    if ($conn->query($add_column)) {
        echo "<p style='color: green;'>✅ Successfully added 'password_change_required' column!</p>";
        echo "<p>Default value is 0 (no password change required)</p>";
    } else {
        echo "<p style='color: red;'>❌ Error adding column: " . $conn->error . "</p>";
    }
}

// Show current table structure
echo "<h3>Current applicants table structure:</h3>";
$show_columns = "SHOW COLUMNS FROM applicants";
$columns_result = $conn->query($show_columns);

if ($columns_result) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $columns_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
echo "<p style='margin-top: 20px;'><a href='../../admin/index.php'>← Back to Admin Panel</a></p>";
?>
