<?php
/**
 * Database Migration Script
 * Adds 'subject' column to the job table
 * 
 * Run this script once to update your database schema
 */

$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Database Migration: Adding 'subject' column to job table</h2>";

// Check if column already exists
$check_sql = "SHOW COLUMNS FROM job LIKE 'subject'";
$result = $conn->query($check_sql);

if ($result->num_rows > 0) {
    echo "<p style='color: orange;'>✓ Column 'subject' already exists in the job table. No action needed.</p>";
} else {
    // Add the subject column
    $alter_sql = "ALTER TABLE job ADD COLUMN subject VARCHAR(255) DEFAULT '' AFTER application_deadline";
    
    if ($conn->query($alter_sql) === TRUE) {
        echo "<p style='color: green;'>✓ Successfully added 'subject' column to the job table!</p>";
        echo "<p>The column has been added with:</p>";
        echo "<ul>";
        echo "<li>Column name: subject</li>";
        echo "<li>Data type: VARCHAR(255)</li>";
        echo "<li>Default value: Empty string</li>";
        echo "<li>Position: After application_deadline</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>✗ Error adding column: " . $conn->error . "</p>";
    }
}

echo "<hr>";
echo "<h3>Current job table structure:</h3>";

// Display current table structure
$columns_sql = "SHOW COLUMNS FROM job";
$columns_result = $conn->query($columns_sql);

if ($columns_result) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    while ($column = $columns_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

$conn->close();

echo "<hr>";
echo "<p><strong>Migration completed!</strong> You can now create job postings with subjects.</p>";
echo "<p><a href='index.php'>← Back to Admin Dashboard</a></p>";
?>
