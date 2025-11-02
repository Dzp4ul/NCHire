<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_email'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get user ID from session with fallback
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id && isset($_SESSION['user_email'])) {
    $email_stmt = $conn->prepare("SELECT id FROM applicants WHERE applicant_email = ?");
    $email_stmt->bind_param("s", $_SESSION['user_email']);
    $email_stmt->execute();
    $email_result = $email_stmt->get_result();
    if ($email_result->num_rows > 0) {
        $user_row = $email_result->fetch_assoc();
        $user_id = $user_row['id'];
        $_SESSION['user_id'] = $user_id;
    }
    $email_stmt->close();
}

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID not found']);
    exit();
}

$type = $_GET['type'] ?? '';

if ($type === 'education') {
    // Fetch education data
    $sql = "SELECT * FROM user_education WHERE user_id = ? ORDER BY end_year DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
    $stmt->close();
} elseif ($type === 'experience') {
    // Fetch work experience data
    $sql = "SELECT * FROM user_experience WHERE user_id = ? ORDER BY start_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
    $stmt->close();
} elseif ($type === 'skills') {
    // Fetch skills data
    $sql = "SELECT * FROM user_skills WHERE user_id = ? ORDER BY skill_category, skill_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
}

$conn->close();
?>
