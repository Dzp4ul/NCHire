<?php
/**
 * Database Configuration
 * Centralized database connection
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database credentials
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

/**
 * Get database connection
 * @return mysqli
 */
function getDbConnection() {
    global $conn;
    return $conn;
}
?>
