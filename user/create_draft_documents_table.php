<?php
/**
 * Create table for storing draft documents
 * Run this file once to set up the draft documents table
 */

$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create draft_documents table
$sql = "CREATE TABLE IF NOT EXISTS user_draft_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    application_letter VARCHAR(255) DEFAULT NULL,
    resume VARCHAR(255) DEFAULT NULL,
    tor VARCHAR(255) DEFAULT NULL,
    diploma VARCHAR(255) DEFAULT NULL,
    professional_license VARCHAR(255) DEFAULT NULL,
    coe VARCHAR(255) DEFAULT NULL,
    seminars_trainings TEXT DEFAULT NULL,
    masteral_cert VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES applicants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_draft (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "✅ Table 'user_draft_documents' created successfully!<br>";
    echo "Users can now save their uploaded documents as drafts and reuse them across multiple job applications.<br>";
} else {
    echo "❌ Error creating table: " . $conn->error . "<br>";
}

$conn->close();
?>
