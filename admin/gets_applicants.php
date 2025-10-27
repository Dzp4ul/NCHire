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
    // Join with applicants table to get profile_picture
    $stmt = $pdo->query("
        SELECT 
            ja.id, 
            ja.full_name, 
            ja.position, 
            ja.applied_date, 
            ja.status, 
            ja.applicant_email, 
            ja.contact_num,
            a.profile_picture
        FROM job_applicants ja
        LEFT JOIN applicants a ON ja.user_id = a.id
        WHERE ja.status != 'Rejected' 
        ORDER BY ja.applied_date DESC
    ");
    $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($applicants);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
