<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$first_name = trim($input['first_name'] ?? '');
$last_name = trim($input['last_name'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
    exit;
}

if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^a-zA-Z0-9]/', $password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain letters, numbers, and symbols']);
    exit;
}

$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$conn->set_charset("utf8mb4");

// Check if email exists in applicants or admin_users
$stmt = $conn->prepare("SELECT id FROM applicants WHERE applicant_email = ? UNION SELECT id FROM admin_users WHERE email = ?");
$stmt->bind_param("ss", $email, $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already registered']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// Insert applicant (auto-verified for SPA)
$stmt = $conn->prepare("INSERT INTO applicants (first_name, last_name, applicant_email, applicant_password, is_verified) VALUES (?, ?, ?, ?, 1)");
$stmt->bind_param("ssss", $first_name, $last_name, $email, $password);

if ($stmt->execute()) {
    $user_id = $conn->insert_id;

    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_email'] = $email;
    $_SESSION['first_name'] = $first_name;

    echo json_encode([
        'success' => true,
        'user_type' => 'applicant',
        'user' => [
            'id' => $user_id,
            'name' => $first_name . ' ' . $last_name,
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create account']);
}

$stmt->close();
$conn->close();
?>
