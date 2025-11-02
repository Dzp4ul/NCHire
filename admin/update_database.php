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

// Add new columns to job_applicants table
$alterQueries = [
    "ALTER TABLE job_applicants ADD COLUMN interview_date DATETIME NULL",
    "ALTER TABLE job_applicants ADD COLUMN interview_notes TEXT NULL",
    "ALTER TABLE job_applicants ADD COLUMN rejection_reason TEXT NULL",
    "ALTER TABLE job_applicants ADD COLUMN resubmission_documents TEXT NULL",
    "ALTER TABLE job_applicants ADD COLUMN resubmission_notes TEXT NULL",
    "ALTER TABLE job_applicants ADD COLUMN address TEXT NULL",
    "ALTER TABLE job_applicants ADD COLUMN user_id INT NULL",
    "ALTER TABLE job_applicants ADD COLUMN application_letter VARCHAR(255) NULL",
    "ALTER TABLE job_applicants ADD COLUMN resume VARCHAR(255) NULL",
    "ALTER TABLE job_applicants ADD COLUMN tor VARCHAR(255) NULL",
    "ALTER TABLE job_applicants ADD COLUMN diploma VARCHAR(255) NULL",
    "ALTER TABLE job_applicants ADD COLUMN professional_license VARCHAR(255) NULL",
    "ALTER TABLE job_applicants ADD COLUMN coe VARCHAR(255) NULL",
    "ALTER TABLE job_applicants ADD COLUMN seminars_trainings VARCHAR(255) NULL"
];

foreach ($alterQueries as $query) {
    // Extract column name from query for checking
    preg_match('/ADD COLUMN (\w+)/', $query, $matches);
    $columnName = $matches[1] ?? '';
    
    // Check if column exists first
    $checkColumn = $conn->query("SHOW COLUMNS FROM job_applicants LIKE '$columnName'");
    
    if ($checkColumn->num_rows == 0) {
        // Column doesn't exist, add it
        if ($conn->query($query) === TRUE) {
            echo "Column added successfully: $columnName<br>";
        } else {
            echo "Error adding column $columnName: " . $conn->error . "<br>";
        }
    } else {
        echo "Column already exists: $columnName<br>";
    }
}

// Create users table if it doesn't exist
$createUsersTable = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($createUsersTable) === TRUE) {
    echo "Users table created/verified successfully<br>";
} else {
    echo "Error creating users table: " . $conn->error . "<br>";
}

$conn->close();
echo "Database update completed!";
?>
