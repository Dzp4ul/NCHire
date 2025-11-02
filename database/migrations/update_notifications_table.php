<?php
// Script to update notifications table structure for email-based system
$servername = "127.0.0.1";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Updating notifications table structure...\n";

// Add new columns for email-based system
$alterQueries = [
    "ALTER TABLE notifications ADD COLUMN user_email VARCHAR(255) NULL AFTER user_id",
    "ALTER TABLE notifications ADD COLUMN user_name VARCHAR(255) NULL AFTER user_email",
    "ALTER TABLE notifications MODIFY COLUMN user_id INT NULL"
];

foreach ($alterQueries as $query) {
    if ($conn->query($query)) {
        echo "✓ Executed: " . substr($query, 0, 50) . "...\n";
    } else {
        // Check if column already exists
        if (strpos($conn->error, 'Duplicate column name') !== false) {
            echo "✓ Column already exists: " . substr($query, 0, 50) . "...\n";
        } else {
            echo "✗ Error: " . $conn->error . "\n";
        }
    }
}

echo "\nNotifications table structure updated successfully!\n";
echo "New structure supports both user_id and email-based identification.\n";

$conn->close();
?>
