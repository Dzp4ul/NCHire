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
// Join with applicants table to get profile_picture
$query = "SELECT 
            ja.id,
            ja.full_name,
            ja.applicant_email,
            ja.position,
            ja.applied_date,
            ja.rejected_date,
            ja.rejection_reason,
            ja.status,
            a.profile_picture
          FROM job_applicants ja
          LEFT JOIN applicants a ON ja.user_id = a.id
          WHERE ja.status = 'Rejected'
          ORDER BY ja.rejected_date DESC";

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
