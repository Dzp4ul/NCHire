<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

try {
    $conn = new mysqli($host, $user, $pass, $dbname);

    if ($conn->connect_error) {
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed',
            'details' => $conn->connect_error
        ]);
        exit();
    }

    // First, check if status column exists, if not, fetch all jobs
    $checkQuery = "SHOW COLUMNS FROM job LIKE 'status'";
    $checkResult = $conn->query($checkQuery);
    
    if ($checkResult && $checkResult->num_rows > 0) {
        // Status column exists, use it
        $query = "SELECT id, job_title, department_role, job_type, locations, salary_range, 
                         application_deadline, job_description 
                  FROM job 
                  WHERE status = 'active' 
                  ORDER BY id DESC 
                  LIMIT 10";
    } else {
        // Status column doesn't exist, fetch all jobs
        $query = "SELECT id, job_title, department_role, job_type, locations, salary_range, 
                         application_deadline, job_description 
                  FROM job 
                  ORDER BY id DESC 
                  LIMIT 10";
    }

    $result = $conn->query($query);

    if (!$result) {
        echo json_encode([
            'success' => false,
            'error' => 'Query failed',
            'details' => $conn->error,
            'query' => $query
        ]);
        exit();
    }

    $jobs = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Format deadline
            $deadline = 'N/A';
            if (!empty($row['application_deadline'])) {
                $deadline = date('F d, Y', strtotime($row['application_deadline']));
            }
            
            // Get short description (first 150 characters)
            $description = $row['job_description'] ?? 'No description available.';
            if (strlen($description) > 150) {
                $description = substr($description, 0, 150) . '...';
            }
            
            $jobs[] = [
                'id' => $row['id'],
                'title' => $row['job_title'] ?? 'Untitled Position',
                'department' => $row['department_role'] ?? 'General',
                'type' => $row['job_type'] ?? 'Full-time',
                'location' => $row['locations'] ?? 'N/A',
                'salary' => $row['salary_range'] ?? 'Competitive',
                'deadline' => $deadline,
                'description' => $description
            ];
        }
    }

    $conn->close();

    echo json_encode([
        'success' => true,
        'jobs' => $jobs,
        'count' => count($jobs)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Exception occurred',
        'details' => $e->getMessage()
    ]);
}
?>
