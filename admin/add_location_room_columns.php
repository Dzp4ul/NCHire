<?php
// Database migration to add location and room columns for interview and demo scheduling

$host = 'localhost';
$dbname = 'nchire';
$username = 'root';
$password = '';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>Adding Location and Room Columns to job_applicants Table</h2>";
    
    // Add interview location and room columns
    $alterQueries = [
        "ALTER TABLE job_applicants ADD COLUMN IF NOT EXISTS interview_location VARCHAR(255) DEFAULT NULL AFTER interview_date",
        "ALTER TABLE job_applicants ADD COLUMN IF NOT EXISTS interview_room VARCHAR(255) DEFAULT NULL AFTER interview_location",
        "ALTER TABLE job_applicants ADD COLUMN IF NOT EXISTS demo_location VARCHAR(255) DEFAULT NULL AFTER demo_date",
        "ALTER TABLE job_applicants ADD COLUMN IF NOT EXISTS demo_room VARCHAR(255) DEFAULT NULL AFTER demo_location"
    ];
    
    foreach ($alterQueries as $query) {
        if ($conn->query($query) === TRUE) {
            echo "<p style='color: green;'>✓ Successfully executed: " . htmlspecialchars($query) . "</p>";
        } else {
            // Check if error is because column already exists
            if (strpos($conn->error, "Duplicate column name") !== false) {
                echo "<p style='color: orange;'>⚠ Column already exists, skipping...</p>";
            } else {
                echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
            }
        }
    }
    
    echo "<h3>Verifying columns...</h3>";
    $result = $conn->query("DESCRIBE job_applicants");
    
    $columnsFound = [
        'interview_location' => false,
        'interview_room' => false,
        'demo_location' => false,
        'demo_room' => false
    ];
    
    while ($row = $result->fetch_assoc()) {
        if (isset($columnsFound[$row['Field']])) {
            $columnsFound[$row['Field']] = true;
            echo "<p style='color: green;'>✓ Column '{$row['Field']}' exists - Type: {$row['Type']}</p>";
        }
    }
    
    foreach ($columnsFound as $column => $exists) {
        if (!$exists) {
            echo "<p style='color: red;'>✗ Column '$column' was not added successfully!</p>";
        }
    }
    
    echo "<h3 style='color: green;'>✓ Migration completed!</h3>";
    echo "<p><a href='index.php'>← Return to Admin Panel</a></p>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
