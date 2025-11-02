<?php
/**
 * Direct check of secretary_actions.php
 */

// Turn on all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>API Check</h1>";

// Check if file exists
$file = __DIR__ . '/secretary_actions.php';
echo "<p><strong>File exists:</strong> " . (file_exists($file) ? 'Yes' : 'No') . "</p>";

// Check for syntax errors
$output = [];
$return_var = 0;
exec("php -l " . escapeshellarg($file), $output, $return_var);
echo "<p><strong>Syntax check:</strong></p><pre>" . implode("\n", $output) . "</pre>";

// Check database connection
echo "<h2>Database Test</h2>";
try {
    require_once __DIR__ . '/../../config/db.php';
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    echo "<p>Database: " . $conn->query("SELECT DATABASE()")->fetch_row()[0] . "</p>";
    
    // Check tables
    echo "<h3>Tables Check:</h3>";
    $tables = ['job_applicants', 'workflow_history', 'admin_activity', 'applicants'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        $exists = $result && $result->num_rows > 0;
        echo "<p>" . ($exists ? '✓' : '✗') . " $table " . ($exists ? 'exists' : 'missing') . "</p>";
    }
    
    // Check workflow_stage column
    echo "<h3>workflow_stage Column Check:</h3>";
    $result = $conn->query("SHOW COLUMNS FROM job_applicants LIKE 'workflow_stage'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✓ workflow_stage column exists</p>";
    } else {
        echo "<p style='color: red;'>✗ workflow_stage column missing!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database error: " . $e->getMessage() . "</p>";
}

// Check session
echo "<h2>Session Check</h2>";
session_start();
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Try to include the file and catch errors
echo "<h2>Include Test</h2>";
ob_start();
try {
    // Simulate POST request
    $_POST['action'] = 'transfer_to_dept_head';
    $_POST['application_id'] = 999; // Non-existent ID for testing
    $_POST['notes'] = 'Test';
    
    // Capture output
    include $file;
    $output = ob_get_clean();
    
    echo "<p><strong>Output:</strong></p>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    // Try to parse as JSON
    $json = json_decode($output, true);
    if ($json) {
        echo "<p style='color: green;'>✓ Valid JSON output</p>";
        echo "<pre>" . print_r($json, true) . "</pre>";
    } else {
        echo "<p style='color: red;'>✗ Invalid JSON: " . json_last_error_msg() . "</p>";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
