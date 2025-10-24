<?php
// Database connection configuration
$host = "127.0.0.1"; // or localhost
$user = "root"; // your MySQL username
$pass = "12345678"; // your MySQL password
$dbname = "nchire"; // your database name

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");
?>
