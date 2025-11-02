<?php
// Run database update script
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully to database<br><br>";

// Read SQL file
$sql = file_get_contents(__DIR__ . '/database_update.sql');

// Split into individual statements
$statements = array_filter(
    array_map('trim',
    explode(';', $sql)),
    function($stmt) {
        return !empty($stmt) && !preg_match('/^--/', $stmt);
    }
);

echo "Executing SQL statements...<br><br>";

$success_count = 0;
$error_count = 0;

foreach ($statements as $statement) {
    if (trim($statement)) {
        echo "Executing: " . substr($statement, 0, 80) . "...<br>";
        
        if ($conn->query($statement)) {
            echo "✓ Success<br><br>";
            $success_count++;
        } else {
            echo "✗ Error: " . $conn->error . "<br><br>";
            $error_count++;
        }
    }
}

echo "<hr>";
echo "<strong>Summary:</strong><br>";
echo "✓ Successful: $success_count<br>";
echo "✗ Errors: $error_count<br><br>";

if ($error_count == 0) {
    echo "<div style='color: green; font-size: 18px; font-weight: bold;'>✓ Database updated successfully!</div>";
    echo "<br><a href='admin/index.php'>Go to Admin Panel</a>";
} else {
    echo "<div style='color: orange;'>Some errors occurred but the database may still be functional.</div>";
}

$conn->close();
?>
