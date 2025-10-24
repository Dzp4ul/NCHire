<?php
session_start();

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get current user's email from session or use a test email
$user_email = $_SESSION['user_email'] ?? null;

if (!$user_email) {
    // Try to get email from applicants table for testing
    $result = $conn->query("SELECT applicant_email FROM applicants LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $user_email = $row['applicant_email'];
        $_SESSION['user_email'] = $user_email; // Set for testing
    } else {
        $user_email = "test@example.com"; // Fallback test email
        $_SESSION['user_email'] = $user_email;
    }
}

echo "<h1>Creating Test Notifications</h1>";
echo "<p>Using email: $user_email</p>";

// Create sample notifications
$notifications = [
    [
        'title' => 'Interview Scheduled',
        'message' => 'Your interview has been scheduled for January 20, 2025 at 2:00 PM. Please bring your resume and valid ID.',
        'type' => 'info'
    ],
    [
        'title' => 'Document Resubmission Required',
        'message' => 'Please resubmit the following documents: Resume, Transcript of Records. Reason: Documents are not clear enough.',
        'type' => 'warning'
    ],
    [
        'title' => 'Application Rejected',
        'message' => 'Unfortunately, your application has been rejected. Reason: Position has been filled by another candidate.',
        'type' => 'error'
    ],
    [
        'title' => 'Application Received',
        'message' => 'We have received your application and it is currently under review. We will contact you soon.',
        'type' => 'success'
    ]
];

foreach ($notifications as $notif) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_email, user_name, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
    $user_name = "Test User";
    $stmt->bind_param("sssss", $user_email, $user_name, $notif['title'], $notif['message'], $notif['type']);
    
    if ($stmt->execute()) {
        echo "<p>✓ Created: {$notif['title']}</p>";
    } else {
        echo "<p>✗ Failed to create: {$notif['title']} - " . $stmt->error . "</p>";
    }
}

echo "<h2>Current Notifications in Database:</h2>";
$result = $conn->query("SELECT * FROM notifications WHERE user_email = '$user_email' ORDER BY created_at DESC");
if ($result && $result->num_rows > 0) {
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        $read_status = $row['is_read'] ? 'Read' : 'Unread';
        echo "<li><strong>{$row['title']}</strong> ({$row['type']}) - {$read_status} - {$row['created_at']}</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No notifications found.</p>";
}

echo "<p><a href='user/user.php'>Go to User Dashboard to see notifications</a></p>";

$conn->close();
?>
