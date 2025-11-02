<?php
/**
 * Authentication Helper Functions
 */

/**
 * Check if user is logged in
 */
function isUserLoggedIn() {
    return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
}

/**
 * Check if admin is logged in
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Require user authentication
 */
function requireUserAuth() {
    if (!isUserLoggedIn()) {
        header("Location: ../public/index.php");
        exit();
    }
}

/**
 * Require admin authentication
 */
function requireAdminAuth() {
    if (!isAdminLoggedIn()) {
        header("Location: ../public/index.php");
        exit();
    }
}

/**
 * Get current user ID
 */
function getUserId($conn) {
    $user_id = $_SESSION['user_id'] ?? null;
    
    // Fallback: try to get from email
    if (!$user_id && isset($_SESSION['user_email'])) {
        $stmt = $conn->prepare("SELECT id FROM applicants WHERE applicant_email = ?");
        $stmt->bind_param("s", $_SESSION['user_email']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $user_id = $row['id'];
            $_SESSION['user_id'] = $user_id;
        }
        $stmt->close();
    }
    
    return $user_id;
}

/**
 * Get user initials from name
 */
function getInitials($name) {
    $parts = explode(' ', trim($name));
    $initials = '';
    
    foreach ($parts as $part) {
        if (!empty($part)) {
            $initials .= strtoupper(substr($part, 0, 1));
        }
    }
    
    return $initials ?: 'U';
}

/**
 * Get current admin ID
 */
function getAdminId() {
    return $_SESSION['admin_id'] ?? null;
}
?>
