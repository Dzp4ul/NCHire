<?php
header('Content-Type: application/json');

// Database connection parameters
$host = 'localhost';
$dbname = 'nchire';
$username = 'root';
$password = '12345678';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Exclude rejected applicants - they appear in Archive section
    $stmt = $pdo->query("SELECT id, full_name, position, applied_date, status, applicant_email, contact_num FROM job_applicants WHERE status != 'Rejected' ORDER BY applied_date DESC");
    $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($applicants);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
