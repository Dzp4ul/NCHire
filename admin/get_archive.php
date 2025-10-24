<?php
header('Content-Type: application/json');

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Fetch all rejected applicants (archived)
$query = "SELECT 
            id,
            full_name,
            applicant_email,
            position,
            applied_date,
            rejected_date,
            rejection_reason,
            status
          FROM job_applicants 
          WHERE status = 'Rejected'
          ORDER BY rejected_date DESC";

$result = $conn->query($query);

$archived = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $archived[] = $row;
    }
}

echo json_encode($archived);

$conn->close();
?>
