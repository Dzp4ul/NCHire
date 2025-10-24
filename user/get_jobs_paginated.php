<?php
header('Content-Type: application/json');

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit();
}

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 4; // Default 4 jobs per page
$offset = ($page - 1) * $limit;

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$department = isset($_GET['department']) ? trim($_GET['department']) : '';
$jobType = isset($_GET['job_type']) ? trim($_GET['job_type']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

try {
    // Build the WHERE clause
    $whereConditions = [];
    $params = [];
    $types = '';
    
    // Always exclude expired/closed jobs - check both deadline and status
    // Jobs must have a future deadline AND be in Active status
    $whereConditions[] = "application_deadline >= CURDATE()";
    
    // Check if status column exists and filter by it
    $checkStatusColumn = $conn->query("SHOW COLUMNS FROM job LIKE 'status'");
    if ($checkStatusColumn && $checkStatusColumn->num_rows > 0) {
        $whereConditions[] = "status = 'Active'";
    }
    
    // Search condition
    if (!empty($search)) {
        $whereConditions[] = "(job_title LIKE ? OR department_role LIKE ? OR job_description LIKE ? OR locations LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $types .= 'ssss';
    }
    
    // Department filter
    if (!empty($department)) {
        $whereConditions[] = "LOWER(department_role) LIKE ?";
        $params[] = "%$department%";
        $types .= 's';
    }
    
    // Job type filter
    if (!empty($jobType)) {
        $whereConditions[] = "LOWER(job_type) LIKE ?";
        $params[] = "%$jobType%";
        $types .= 's';
    }
    
    // Location filter
    if (!empty($location)) {
        $whereConditions[] = "LOWER(locations) LIKE ?";
        $params[] = "%$location%";
        $types .= 's';
    }
    
    // Build WHERE clause
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    }
    
    // Build ORDER BY clause
    $orderClause = "ORDER BY ";
    switch ($sort) {
        case 'oldest':
            $orderClause .= "id ASC";
            break;
        case 'title_asc':
            $orderClause .= "job_title ASC";
            break;
        case 'title_desc':
            $orderClause .= "job_title DESC";
            break;
        case 'department_asc':
            $orderClause .= "department_role ASC";
            break;
        case 'department_desc':
            $orderClause .= "department_role DESC";
            break;
        case 'newest':
        default:
            $orderClause .= "id DESC";
            break;
    }
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM job $whereClause";
    if (!empty($params)) {
        $countStmt = $conn->prepare($countSql);
        $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalJobs = $countResult->fetch_assoc()['total'];
        $countStmt->close();
    } else {
        $countResult = $conn->query($countSql);
        $totalJobs = $countResult->fetch_assoc()['total'];
    }
    
    // Calculate pagination info
    $totalPages = ceil($totalJobs / $limit);
    $hasNext = $page < $totalPages;
    $hasPrev = $page > 1;
    
    // Get jobs for current page
    $sql = "SELECT * FROM job $whereClause $orderClause LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        // Format salary range properly
        $salaryParts = explode(' - ', $row['salary_range']);
        $formattedSalary = '₱' . $salaryParts[0];
        if (isset($salaryParts[1])) {
            $formattedSalary .= ' - ₱' . $salaryParts[1];
        }
        
        $jobs[] = [
            'id' => $row['id'],
            'job_title' => $row['job_title'],
            'department_role' => $row['department_role'],
            'job_type' => $row['job_type'],
            'locations' => $row['locations'],
            'job_description' => $row['job_description'],
            'salary_range' => $formattedSalary,
            'application_deadline' => $row['application_deadline']
        ];
    }
    
    $stmt->close();
    
    // Return paginated response
    echo json_encode([
        'success' => true,
        'jobs' => $jobs,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_jobs' => $totalJobs,
            'jobs_per_page' => $limit,
            'has_next' => $hasNext,
            'has_prev' => $hasPrev,
            'showing_from' => $offset + 1,
            'showing_to' => min($offset + $limit, $totalJobs)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching jobs: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
