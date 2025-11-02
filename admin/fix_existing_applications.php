<?php
/**
 * Fix Existing Applications - Set workflow_stage for old applications
 * Run this once to update existing applications that don't have workflow_stage set
 */

require_once '../config/db.php';

echo "<h2>Fixing Existing Applications</h2>";

// Check applications without workflow_stage
$result = $conn->query("SELECT id, full_name, position, status, workflow_stage FROM job_applicants WHERE workflow_stage IS NULL OR workflow_stage = ''");

if ($result->num_rows > 0) {
    echo "<p>Found " . $result->num_rows . " applications without workflow_stage. Updating...</p>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Position</th><th>Old Status</th><th>New Workflow Stage</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $status = $row['status'];
        
        // Determine appropriate workflow_stage based on current status
        $workflow_stage = 'secretary_review'; // Default
        
        switch($status) {
            case 'Pending':
            case 'Resubmission Required':
                $workflow_stage = 'secretary_review';
                break;
            case 'Interview Scheduled':
                $workflow_stage = 'interview_scheduled';
                break;
            case 'Interview Passed':
                $workflow_stage = 'interview_completed';
                break;
            case 'Demo Scheduled':
                $workflow_stage = 'demo_scheduled';
                break;
            case 'Demo Passed':
                $workflow_stage = 'demo_completed';
                break;
            case 'Psychological Exam':
                $workflow_stage = 'psych_scheduled';
                break;
            case 'Initially Hired':
                $workflow_stage = 'initially_hired';
                break;
            case 'Permanently Hired':
                $workflow_stage = 'permanently_hired';
                break;
            case 'Hired':
                $workflow_stage = 'hired';
                break;
            case 'Rejected':
                $workflow_stage = 'rejected';
                break;
            default:
                $workflow_stage = 'secretary_review';
        }
        
        // Update the application
        $update = $conn->prepare("UPDATE job_applicants SET workflow_stage = ? WHERE id = ?");
        $update->bind_param("si", $workflow_stage, $id);
        $update->execute();
        
        echo "<tr>";
        echo "<td>{$id}</td>";
        echo "<td>{$row['full_name']}</td>";
        echo "<td>{$row['position']}</td>";
        echo "<td>{$status}</td>";
        echo "<td style='color: green; font-weight: bold;'>{$workflow_stage}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "<p style='color: green; font-weight: bold;'>✓ All applications updated successfully!</p>";
} else {
    echo "<p style='color: green;'>✓ All applications already have workflow_stage set. No updates needed.</p>";
}

// Show current distribution
echo "<h3>Current Application Distribution by Workflow Stage:</h3>";
$distribution = $conn->query("SELECT workflow_stage, COUNT(*) as count FROM job_applicants GROUP BY workflow_stage ORDER BY count DESC");

echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Workflow Stage</th><th>Count</th></tr>";
while ($row = $distribution->fetch_assoc()) {
    echo "<tr><td>{$row['workflow_stage']}</td><td>{$row['count']}</td></tr>";
}
echo "</table>";

echo "<br><p><a href='index.php'>← Back to Admin Panel</a></p>";

$conn->close();
