<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

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
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
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

// Check admin_users first
$stmt = $conn->prepare("SELECT id, full_name, email, password, role, department, profile_picture, status FROM admin_users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row['status'] !== 'Active') {
        echo json_encode(['success' => false, 'message' => 'Account is inactive']);
        exit;
    }

    if (password_verify($password, $row['password'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['admin_name'] = $row['full_name'];
        $_SESSION['admin_email'] = $row['email'];
        $_SESSION['admin_role'] = $row['role'];
        $_SESSION['admin_department'] = $row['department'];

        echo json_encode([
            'success' => true,
            'user_type' => 'admin',
            'user' => [
                'id' => $row['id'],
                'name' => $row['full_name'],
                'email' => $row['email'],
                'role' => $row['role'],
                'department' => $row['department'],
                'profile_picture' => $row['profile_picture']
            ]
        ]);
        $conn->close();
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid password']);
        $conn->close();
        exit;
    }
}

// Check applicants
$stmt = $conn->prepare("SELECT id, first_name, last_name, applicant_email, applicant_password, is_verified, profile_picture FROM applicants WHERE applicant_email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row['is_verified']) {
        echo json_encode(['success' => false, 'message' => 'Account not verified']);
        $conn->close();
        exit;
    }

    if ($password === $row['applicant_password']) {
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_email'] = $row['applicant_email'];
        $_SESSION['first_name'] = $row['first_name'];

        echo json_encode([
            'success' => true,
            'user_type' => 'applicant',
            'user' => [
                'id' => $row['id'],
                'name' => $row['first_name'] . ' ' . $row['last_name'],
                'email' => $row['applicant_email'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'profile_picture' => $row['profile_picture']
            ]
        ]);
        $conn->close();
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid password']);
        $conn->close();
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Email not found']);
$conn->close();
?>
