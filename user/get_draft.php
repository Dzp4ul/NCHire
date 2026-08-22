<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit();
}

$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $conn->prepare("SELECT application_letter, resume, tor, diploma, professional_license, coe, seminars_trainings, masteral_cert, certificate_of_grades, proof_of_enrollment, letter_of_intent, updated_at FROM user_draft_documents WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $draft = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'has_draft' => true,
            'user_id' => $user_id, // Include user_id for validation
            'draft' => $draft
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'has_draft' => false,
            'user_id' => $user_id,
            'draft' => null
        ]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
