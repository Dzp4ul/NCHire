<?php
/**
 * Check User Ban Status API
 * Returns ban information if user is currently banned from applying
 */

session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['banned' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Database connection
require_once __DIR__ . '/../config/db.php';

try {
    // Check ban status
    $stmt = $conn->prepare("SELECT rejection_ban_until, ban_reason, banned_by, rejection_count 
                           FROM applicants 
                           WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $ban_data = $result->fetch_assoc();
        $ban_until = $ban_data['rejection_ban_until'];
        
        if ($ban_until) {
            $ban_expiry_timestamp = strtotime($ban_until);
            $current_timestamp = time();
            
            if ($current_timestamp < $ban_expiry_timestamp) {
                // Ban is active
                $days_remaining = ceil(($ban_expiry_timestamp - $current_timestamp) / (60 * 60 * 24));
                $hours_remaining = ceil(($ban_expiry_timestamp - $current_timestamp) / (60 * 60));
                
                echo json_encode([
                    'banned' => true,
                    'ban_until' => $ban_until,
                    'ban_until_formatted' => date('F j, Y \\a\\t g:i A', $ban_expiry_timestamp),
                    'ban_reason' => $ban_data['ban_reason'],
                    'banned_by' => $ban_data['banned_by'],
                    'days_remaining' => $days_remaining,
                    'hours_remaining' => $hours_remaining,
                    'rejection_count' => $ban_data['rejection_count']
                ]);
                exit();
            } else {
                // Ban expired, clear it
                $clear_stmt = $conn->prepare("UPDATE applicants 
                                              SET rejection_ban_until = NULL, 
                                                  ban_reason = NULL, 
                                                  banned_by = NULL 
                                              WHERE id = ?");
                $clear_stmt->bind_param("i", $user_id);
                $clear_stmt->execute();
                $clear_stmt->close();
            }
        }
    }
    
    $stmt->close();
    
    // No active ban
    echo json_encode(['banned' => false]);
    
} catch (Exception $e) {
    echo json_encode(['banned' => false, 'error' => $e->getMessage()]);
}

$conn->close();
