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

echo "<h2>Updating Job Table Structure</h2>";

// First, check current structure
echo "<h3>Current Job Table Structure:</h3>";
$result = $conn->query("DESCRIBE job");
if ($result) {
    $existing_columns = [];
    while ($row = $result->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
        echo "Field: " . $row['Field'] . ", Type: " . $row['Type'] . "<br>";
    }
} else {
    echo "Error describing job table: " . $conn->error . "<br>";
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

// Add each column if it doesn't exist
foreach ($new_columns as $column_name => $sql) {
    if (!in_array($column_name, $existing_columns)) {
        if ($conn->query($sql) === TRUE) {
            echo "✅ Successfully added column: $column_name<br>";
        } else {
            echo "❌ Error adding column $column_name: " . $conn->error . "<br>";
        }
    } else {
        echo "ℹ️ Column $column_name already exists<br>";
    }
}

echo "<br><h3>Updated Job Table Structure:</h3>";
$result = $conn->query("DESCRIBE job");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "Field: " . $row['Field'] . ", Type: " . $row['Type'] . ", Null: " . $row['Null'] . "<br>";
    }
} else {
    echo "Error describing updated job table: " . $conn->error . "<br>";
}

$conn->close();
echo "<br><h3>✅ Database update completed!</h3>";
?>
