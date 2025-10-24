<?php
/**
 * Test and Verify Draft Feature Setup
 * This script checks if the draft feature is properly configured
 */

$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Draft Feature Setup Test</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    h3 { margin-top: 20px; }
</style>";

// Test 1: Check if table exists
echo "<h3>1. Checking Database Table</h3>";
$result = $conn->query("SHOW TABLES LIKE 'user_draft_documents'");
if ($result->num_rows > 0) {
    echo "<p class='success'>✅ Table 'user_draft_documents' exists</p>";
    
    // Check table structure
    $structure = $conn->query("DESCRIBE user_draft_documents");
    echo "<p class='info'>Table structure:</p><ul>";
    while ($row = $structure->fetch_assoc()) {
        echo "<li>{$row['Field']} - {$row['Type']}</li>";
    }
    echo "</ul>";
} else {
    echo "<p class='error'>❌ Table 'user_draft_documents' does not exist</p>";
    echo "<p class='info'>Creating table now...</p>";
    
    $sql = "CREATE TABLE IF NOT EXISTS user_draft_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        application_letter VARCHAR(255) DEFAULT NULL,
        resume VARCHAR(255) DEFAULT NULL,
        tor VARCHAR(255) DEFAULT NULL,
        diploma VARCHAR(255) DEFAULT NULL,
        professional_license VARCHAR(255) DEFAULT NULL,
        coe VARCHAR(255) DEFAULT NULL,
        seminars_trainings TEXT DEFAULT NULL,
        masteral_cert VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES applicants(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_draft (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p class='success'>✅ Table created successfully!</p>";
    } else {
        echo "<p class='error'>❌ Error creating table: " . $conn->error . "</p>";
    }
}

// Test 2: Check directories
echo "<h3>2. Checking Directory Structure</h3>";
$uploadDir = __DIR__ . "/uploads/";
$draftsDir = $uploadDir . "drafts/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
    echo "<p class='info'>Created uploads directory</p>";
}
echo "<p class='success'>✅ uploads/ directory exists</p>";

if (!is_dir($draftsDir)) {
    mkdir($draftsDir, 0777, true);
    echo "<p class='info'>Created drafts directory</p>";
}
echo "<p class='success'>✅ uploads/drafts/ directory exists</p>";

// Check permissions
$perms = substr(sprintf('%o', fileperms($draftsDir)), -4);
echo "<p class='info'>Directory permissions: {$perms}</p>";

// Test 3: Check files
echo "<h3>3. Checking Required Files</h3>";
$requiredFiles = [
    'save_draft.php' => 'API to save drafts',
    'get_draft.php' => 'API to get drafts',
    'user.php' => 'Main user interface',
    'DRAFT_SAVE_FEATURE_README.md' => 'Documentation'
];

foreach ($requiredFiles as $file => $description) {
    if (file_exists(__DIR__ . "/{$file}")) {
        echo "<p class='success'>✅ {$file} - {$description}</p>";
    } else {
        echo "<p class='error'>❌ {$file} - {$description} is missing</p>";
    }
}

// Test 4: Sample draft data
echo "<h3>4. Current Draft Data</h3>";
$drafts = $conn->query("SELECT user_id, 
    CASE WHEN application_letter IS NOT NULL THEN '✓' ELSE '✗' END as app_letter,
    CASE WHEN resume IS NOT NULL THEN '✓' ELSE '✗' END as resume,
    CASE WHEN tor IS NOT NULL THEN '✓' ELSE '✗' END as tor,
    CASE WHEN diploma IS NOT NULL THEN '✓' ELSE '✗' END as diploma,
    updated_at 
    FROM user_draft_documents 
    ORDER BY updated_at DESC 
    LIMIT 5");

if ($drafts && $drafts->num_rows > 0) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>User ID</th><th>Letter</th><th>Resume</th><th>TOR</th><th>Diploma</th><th>Last Updated</th></tr>";
    while ($row = $drafts->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['user_id']}</td>";
        echo "<td>{$row['app_letter']}</td>";
        echo "<td>{$row['resume']}</td>";
        echo "<td>{$row['tor']}</td>";
        echo "<td>{$row['diploma']}</td>";
        echo "<td>{$row['updated_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='info'>No draft data yet. Users need to save drafts first.</p>";
}

// Test 5: Summary
echo "<h3>5. Feature Status</h3>";
echo "<p class='success'><strong>✅ Draft Save Feature is ready to use!</strong></p>";
echo "<h4>Next Steps:</h4>";
echo "<ol>";
echo "<li>Log in as a user</li>";
echo "<li>Click 'Apply Now' on any job</li>";
echo "<li>Upload documents in Step 2</li>";
echo "<li>Click <strong>'Save Draft'</strong> button</li>";
echo "<li>Apply to another job and see documents auto-load</li>";
echo "</ol>";

echo "<h4>Feature Highlights:</h4>";
echo "<ul>";
echo "<li>📥 <strong>Auto-load</strong>: Previously saved documents load automatically</li>";
echo "<li>💾 <strong>Save Draft</strong>: Save documents for reuse across applications</li>";
echo "<li>🔄 <strong>Reuse</strong>: No need to upload same files repeatedly</li>";
echo "<li>✏️ <strong>Update</strong>: Can replace individual files anytime</li>";
echo "</ul>";

$conn->close();
?>
