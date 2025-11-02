<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['activity_type']) || !isset($input['description'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

try {
    // Create admin_activity table if it doesn't exist
    $create_table_query = "CREATE TABLE IF NOT EXISTS admin_activity (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_type VARCHAR(50) NOT NULL,
        description TEXT NOT NULL,
        user_name VARCHAR(100),
        related_table VARCHAR(50),
        related_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($create_table_query);

    // Insert activity log
    $stmt = $conn->prepare("INSERT INTO admin_activity (activity_type, description, user_name, related_table, related_id) VALUES (?, ?, ?, ?, ?)");
    
    $activity_type = $input['activity_type'];
    $description = $input['description'];
    $user_name = $input['user_name'] ?? 'Admin';
    $related_table = $input['related_table'] ?? null;
    $related_id = $input['related_id'] ?? null;
    
    $stmt->bind_param("ssssi", $activity_type, $description, $user_name, $related_table, $related_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Activity logged successfully',
            'id' => $conn->insert_id
        ]);
    } else {
        throw new Exception('Failed to insert activity log');
    }
    
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
