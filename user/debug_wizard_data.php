<?php
session_start();

echo "<h1>Wizard Data Debug Report</h1>";
echo "<style>body { font-family: Arial; padding: 20px; } table { border-collapse: collapse; width: 100%; margin: 20px 0; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background: #4CAF50; color: white; } .section { margin: 30px 0; padding: 20px; background: #f9f9f9; border-left: 4px solid #4CAF50; }</style>";

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("<p style='color: red;'>Connection failed: " . $conn->connect_error . "</p>");
}

echo "<div class='section'>";
echo "<h2>1. Session Information</h2>";
echo "<table>";
echo "<tr><th>Session Key</th><th>Value</th></tr>";
foreach ($_SESSION as $key => $value) {
    echo "<tr><td>" . htmlspecialchars($key) . "</td><td>" . htmlspecialchars(is_array($value) ? json_encode($value) : $value) . "</td></tr>";
}
echo "</table>";
$session_user_id = $_SESSION['user_id'] ?? null;
$session_email = $_SESSION['email'] ?? ($_SESSION['applicant_email'] ?? null);
echo "<p><strong>User ID for queries:</strong> " . ($session_user_id ?? 'NULL') . "</p>";
echo "<p><strong>Email for queries:</strong> " . ($session_email ?? 'NULL') . "</p>";
echo "</div>";

if (!$session_user_id) {
    echo "<div class='section' style='border-left-color: orange;'>";
    echo "<h2>⚠️ WARNING: No user_id in session!</h2>";
    echo "<p>The user is not properly logged in. This will cause data fetching to fail.</p>";
    echo "</div>";
}

// Check applicants table
echo "<div class='section'>";
echo "<h2>2. Applicants Table Data</h2>";
if ($session_user_id) {
    $stmt = $conn->prepare("SELECT * FROM applicants WHERE id = ?");
    $stmt->bind_param("i", $session_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "<table>";
        echo "<tr><th>Column</th><th>Value</th></tr>";
        foreach ($row as $col => $val) {
            echo "<tr><td>" . htmlspecialchars($col) . "</td><td>" . htmlspecialchars($val ?? 'NULL') . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'><strong>NO DATA FOUND</strong> for user_id = " . $session_user_id . "</p>";
    }
    $stmt->close();
} else {
    echo "<p style='color: orange;'>Cannot query - no user_id in session</p>";
}
echo "</div>";

// Check user_experience table
echo "<div class='section'>";
echo "<h2>3. Work Experience Data (user_experience table)</h2>";
if ($session_user_id) {
    $stmt = $conn->prepare("SELECT * FROM user_experience WHERE user_id = ? ORDER BY start_date DESC");
    $stmt->bind_param("i", $session_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<p><strong>Found " . $result->num_rows . " work experience record(s)</strong></p>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Job Title</th><th>Company</th><th>Location</th><th>Start Date</th><th>End Date</th><th>Current</th><th>Description</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['job_title'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['company'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['location'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['start_date'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['end_date'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['is_current'] ?? '0') . "</td>";
            echo "<td>" . htmlspecialchars(substr($row['description'] ?? '', 0, 50)) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'><strong>NO WORK EXPERIENCE FOUND</strong> for user_id = " . $session_user_id . "</p>";
        echo "<p>This is why the wizard shows 'No work experience found in your profile'</p>";
    }
    $stmt->close();
} else {
    echo "<p style='color: orange;'>Cannot query - no user_id in session</p>";
}
echo "</div>";

// Check user_education table
echo "<div class='section'>";
echo "<h2>4. Education Data (user_education table)</h2>";
if ($session_user_id) {
    $stmt = $conn->prepare("SELECT * FROM user_education WHERE user_id = ? ORDER BY start_year DESC");
    $stmt->bind_param("i", $session_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<p><strong>Found " . $result->num_rows . " education record(s)</strong></p>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Institution</th><th>Degree</th><th>Field of Study</th><th>Start Year</th><th>End Year</th><th>GPA</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['institution'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['degree'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['field_of_study'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['start_year'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['end_year'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['gpa'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'><strong>NO EDUCATION FOUND</strong> for user_id = " . $session_user_id . "</p>";
        echo "<p>This is why the wizard shows 'No education found in your profile'</p>";
    }
    $stmt->close();
} else {
    echo "<p style='color: orange;'>Cannot query - no user_id in session</p>";
}
echo "</div>";

// Check user_skills table
echo "<div class='section'>";
echo "<h2>5. Skills Data (user_skills table)</h2>";
if ($session_user_id) {
    $stmt = $conn->prepare("SELECT * FROM user_skills WHERE user_id = ? ORDER BY skill_name");
    $stmt->bind_param("i", $session_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<p><strong>Found " . $result->num_rows . " skill(s)</strong></p>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Skill Name</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['skill_name'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'><strong>NO SKILLS FOUND</strong> for user_id = " . $session_user_id . "</p>";
        echo "<p>This is why the wizard shows 'No skills found in your profile'</p>";
    }
    $stmt->close();
} else {
    echo "<p style='color: orange;'>Cannot query - no user_id in session</p>";
}
echo "</div>";

// Check job_applicants table for My Applications
echo "<div class='section'>";
echo "<h2>6. Job Applications Data (job_applicants table)</h2>";
if ($session_user_id) {
    $stmt = $conn->prepare("SELECT id, position, applied_date, status, applicant_name, applicant_email FROM job_applicants WHERE user_id = ? ORDER BY applied_date DESC LIMIT 10");
    $stmt->bind_param("i", $session_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<p><strong>Found " . $result->num_rows . " application(s)</strong></p>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Position</th><th>Applied Date</th><th>Status</th><th>Name</th><th>Email</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['position'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['applied_date'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['status'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['applicant_name'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['applicant_email'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'><strong>NO APPLICATIONS FOUND</strong> for user_id = " . $session_user_id . "</p>";
        echo "<p>This is why My Applications shows 'You haven't applied to any jobs yet'</p>";
    }
    $stmt->close();
} else {
    echo "<p style='color: orange;'>Cannot query - no user_id in session</p>";
}
echo "</div>";

// Summary and Recommendations
echo "<div class='section' style='border-left-color: #2196F3;'>";
echo "<h2>7. Summary & Recommendations</h2>";
echo "<ol>";
echo "<li><strong>If NO DATA is found in sections 3, 4, 5:</strong> The user has not filled out their profile yet. Direct them to complete their profile first (Profile page).</li>";
echo "<li><strong>If user_id is NULL:</strong> Session issue - user needs to log out and log back in.</li>";
echo "<li><strong>If data exists but wizard doesn't show it:</strong> JavaScript issue - check browser console for errors.</li>";
echo "<li><strong>If My Applications is empty:</strong> User hasn't submitted any applications yet, or applications were submitted with a different user_id/email.</li>";
echo "</ol>";
echo "</div>";

$conn->close();
?>
