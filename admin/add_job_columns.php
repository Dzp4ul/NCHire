<?php
// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Adding Missing Columns to Job Table</h2>";

// Check current structure first
echo "<h3>Current Job Table Structure:</h3>";
$result = $conn->query("DESCRIBE job");
if ($result) {
    $existing_columns = [];
    while ($row = $result->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
        echo "✓ " . $row['Field'] . " (" . $row['Type'] . ")<br>";
    }
} else {
    echo "❌ Error describing job table: " . $conn->error . "<br>";
    exit;
}

echo "<br><h3>Adding New Columns:</h3>";

// Define new columns to add
$new_columns = [
    'education' => "ALTER TABLE job ADD COLUMN education TEXT NULL",
    'experience' => "ALTER TABLE job ADD COLUMN experience TEXT NULL", 
    'training' => "ALTER TABLE job ADD COLUMN training TEXT NULL",
    'eligibility' => "ALTER TABLE job ADD COLUMN eligibility TEXT NULL",
    'competency' => "ALTER TABLE job ADD COLUMN competency TEXT NULL",
    'duties' => "ALTER TABLE job ADD COLUMN duties TEXT NULL"
];

$success_count = 0;
$error_count = 0;

// Add each column if it doesn't exist
foreach ($new_columns as $column_name => $sql) {
    if (!in_array($column_name, $existing_columns)) {
        if ($conn->query($sql) === TRUE) {
            echo "✅ Successfully added column: <strong>$column_name</strong><br>";
            $success_count++;
        } else {
            echo "❌ Error adding column $column_name: " . $conn->error . "<br>";
            $error_count++;
        }
    } else {
        echo "ℹ️ Column <strong>$column_name</strong> already exists<br>";
    }
}

echo "<br><h3>Updated Job Table Structure:</h3>";
$result = $conn->query("DESCRIBE job");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $is_new = in_array($row['Field'], array_keys($new_columns));
        $icon = $is_new ? "🆕" : "✓";
        echo "$icon " . $row['Field'] . " (" . $row['Type'] . ")<br>";
    }
} else {
    echo "❌ Error describing updated job table: " . $conn->error . "<br>";
}

echo "<br><h3>Summary:</h3>";
echo "✅ Columns added successfully: $success_count<br>";
if ($error_count > 0) {
    echo "❌ Errors encountered: $error_count<br>";
}

if ($success_count > 0 && $error_count == 0) {
    echo "<br><div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; border-radius: 5px;'>";
    echo "<strong>🎉 Database update completed successfully!</strong><br>";
    echo "The job creation form should now work without errors.";
    echo "</div>";
} else if ($error_count > 0) {
    echo "<br><div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 5px;'>";
    echo "<strong>⚠️ Some errors occurred during the update.</strong><br>";
    echo "Please check the errors above and try again.";
    echo "</div>";
}

$conn->close();
?>
