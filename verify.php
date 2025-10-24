<?php
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Add debugging at the very start
error_log("=== VERIFY.PHP CALLED ===");
error_log("POST data: " . json_encode($_POST));
error_log("Session data: " . json_encode($_SESSION));

include "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['verification_code'])) {
    $code = $_POST['verification_code'];
    $pending_signup = $_SESSION['pending_signup'] ?? null;

    if (!$pending_signup) {
        echo json_encode(["status" => "error", "message" => "No pending signup found in session"]);
        exit;
    }

    // Debug: Log the verification attempt
    error_log("Verification attempt - Code: $code, Expected: " . $pending_signup['verification_code']);
    
    // Check if the verification code matches
    if ($code === $pending_signup['verification_code']) {
        // Verification successful - now save to database
        try {
            // Debug: Log the data being inserted
            error_log("Inserting user data: " . json_encode($pending_signup));
            
            // Check if email already exists (double-check)
            $check_stmt = $conn->prepare("SELECT id FROM applicants WHERE applicant_email = ?");
            $check_stmt->bind_param("s", $pending_signup['email']);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                echo json_encode(["status" => "error", "message" => "Email already exists in database"]);
                exit;
            }
            $check_stmt->close();
            
            // Check current max ID before insertion
            $max_id_stmt = $conn->prepare("SELECT MAX(id) as max_id FROM applicants");
            $max_id_stmt->execute();
            $max_id_result = $max_id_stmt->get_result();
            $max_id_row = $max_id_result->fetch_assoc();
            $current_max_id = $max_id_row['max_id'] ?? 0;
            error_log("Current max ID before insertion: " . $current_max_id);
            $max_id_stmt->close();
            
            $is_verified = 1;
            $stmt = $conn->prepare("INSERT INTO applicants (first_name, last_name, applicant_email, applicant_password, is_verified) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", 
                $pending_signup['first_name'], 
                $pending_signup['last_name'], 
                $pending_signup['email'], 
                $pending_signup['password'],
                $is_verified
            );

            if ($stmt->execute()) {
                $new_user_id = $conn->insert_id;
                error_log("User inserted with ID: " . $new_user_id);
                
                // Immediately check if user exists
                $verify_stmt = $conn->prepare("SELECT id, first_name, last_name, applicant_email, is_verified FROM applicants WHERE id = ?");
                $verify_stmt->bind_param("i", $new_user_id);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                
                if ($verify_result->num_rows > 0) {
                    $inserted_user = $verify_result->fetch_assoc();
                    error_log("IMMEDIATE CHECK - User found: " . json_encode($inserted_user));
                    
                    // Wait 1 second and check again
                    sleep(1);
                    $verify_stmt2 = $conn->prepare("SELECT id, first_name, last_name, applicant_email, is_verified FROM applicants WHERE id = ?");
                    $verify_stmt2->bind_param("i", $new_user_id);
                    $verify_stmt2->execute();
                    $verify_result2 = $verify_stmt2->get_result();
                    
                    if ($verify_result2->num_rows > 0) {
                        $user_after_wait = $verify_result2->fetch_assoc();
                        error_log("AFTER 1 SECOND - User still exists: " . json_encode($user_after_wait));
                        
                        // Clear the pending signup data
                        unset($_SESSION['pending_signup']);
                        unset($_SESSION['signup_email']);
                        
                        echo json_encode([
                            "status" => "success", 
                            "message" => "Email verified successfully! Your account has been created.",
                            "show_popup" => true,
                            "debug" => "User ID: " . $new_user_id . " - Still exists after 1 second"
                        ]);
                    } else {
                        error_log("ERROR: User disappeared after 1 second!");
                        echo json_encode([
                            "status" => "error", 
                            "message" => "Account was created but disappeared - possible database trigger or constraint issue",
                            "debug" => "User ID " . $new_user_id . " existed immediately but disappeared after 1 second"
                        ]);
                    }
                    $verify_stmt2->close();
                } else {
                    error_log("ERROR: User not found immediately after insertion!");
                    echo json_encode([
                        "status" => "error", 
                        "message" => "Account creation failed - user not found immediately after insertion",
                        "debug" => "Insert ID: " . $new_user_id . " but user not found immediately"
                    ]);
                }
                $verify_stmt->close();
            } else {
                error_log("Database insert failed: " . $stmt->error . " | MySQL Error: " . $conn->error);
                echo json_encode([
                    "status" => "error", 
                    "message" => "Failed to create account: " . $stmt->error,
                    "mysql_error" => $conn->error,
                    "debug" => "Statement error: " . $stmt->error
                ]);
            }
            $stmt->close();
        } catch (Exception $e) {
            error_log("Exception during user creation: " . $e->getMessage());
            echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid verification code. Expected: " . $pending_signup['verification_code'] . ", Got: " . $code]);
    }
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid request - no POST data or verification_code missing"]);

// Catch any fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal error in verify.php: " . json_encode($error));
        if (!headers_sent()) {
            header('Content-Type: application/json');
            echo json_encode([
                "status" => "error", 
                "message" => "Server error: " . $error['message'],
                "debug" => "Fatal error in " . $error['file'] . " on line " . $error['line']
            ]);
        }
    }
});
