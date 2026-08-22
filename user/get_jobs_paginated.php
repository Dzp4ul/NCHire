<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../shared/helpers/recruitment.php';

$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit();
}
$conn->set_charset('utf8mb4');

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 9;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$department = isset($_GET['department']) ? trim($_GET['department']) : '';
$jobType = isset($_GET['job_type']) ? trim($_GET['job_type']) : '';
$academicYear = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : '';
$semester = isset($_GET['semester']) ? nc_normalize_semester($_GET['semester']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

try {
    $assignedSql = "
        SELECT job_id, COUNT(*) AS assigned_count
        FROM job_applicants
        WHERE job_id IS NOT NULL
          AND (
                status IN ('Passed', 'Application Passed', 'Hired', 'Permanently Hired')
                OR workflow_stage IN ('passed', 'hired', 'permanently_hired')
              )
        GROUP BY job_id
    ";

    $whereConditions = [
        "j.application_deadline >= CURDATE()",
        "j.status = 'Active'",
        "COALESCE(NULLIF(j.subject_name, ''), NULLIF(j.subject, ''), NULLIF(j.subject_code, '')) IS NOT NULL",
        "j.teaching_hours_per_week IS NOT NULL AND j.teaching_hours_per_week > 0"
    ];
    $params = [];
    $types = '';

    if ($search !== '') {
        $whereConditions[] = "(j.job_title LIKE ? OR j.department_role LIKE ? OR j.program LIKE ? OR j.subject LIKE ? OR j.subject_code LIKE ? OR j.subject_name LIKE ? OR j.job_description LIKE ? OR j.teaching_schedule LIKE ?)";
        $term = "%{$search}%";
        array_push($params, $term, $term, $term, $term, $term, $term, $term, $term);
        $types .= 'ssssssss';
    }

    if ($department !== '') {
        $whereConditions[] = "LOWER(COALESCE(j.program, j.department_role)) LIKE ?";
        $params[] = '%' . strtolower($department) . '%';
        $types .= 's';
    }

    if ($jobType !== '') {
        $whereConditions[] = "LOWER(j.job_type) LIKE ?";
        $params[] = '%' . strtolower($jobType) . '%';
        $types .= 's';
    }

    if ($academicYear !== '') {
        $whereConditions[] = "j.academic_year = ?";
        $params[] = $academicYear;
        $types .= 's';
    }

    if ($semester !== '') {
        $whereConditions[] = "j.semester = ?";
        $params[] = $semester;
        $types .= 's';
    }

    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    $vacancyExpr = "GREATEST(COALESCE(j.required_instructors, 1) - COALESCE(a.assigned_count, 0), 0)";

    $orderClause = "ORDER BY ";
    switch ($sort) {
        case 'oldest':
            $orderClause .= "j.id ASC";
            break;
        case 'title_asc':
            $orderClause .= "teaching_load_title ASC";
            break;
        case 'title_desc':
            $orderClause .= "teaching_load_title DESC";
            break;
        case 'department_asc':
            $orderClause .= "COALESCE(j.program, j.department_role) ASC";
            break;
        case 'department_desc':
            $orderClause .= "COALESCE(j.program, j.department_role) DESC";
            break;
        case 'newest':
        default:
            $orderClause .= "j.id DESC";
            break;
    }

    $baseFrom = "FROM job j LEFT JOIN ({$assignedSql}) a ON a.job_id = j.id {$whereClause}";
    $countSql = "SELECT COUNT(*) AS total FROM (SELECT j.id, {$vacancyExpr} AS remaining_vacancies {$baseFrom} HAVING remaining_vacancies > 0) x";

    if ($params) {
        $countStmt = $conn->prepare($countSql);
        $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $totalJobs = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $countStmt->close();
    } else {
        $totalJobs = (int)($conn->query($countSql)->fetch_assoc()['total'] ?? 0);
    }

    $totalPages = $totalJobs > 0 ? (int)ceil($totalJobs / $limit) : 1;
    $sql = "
        SELECT j.*, COALESCE(a.assigned_count, 0) AS assigned_instructors,
               {$vacancyExpr} AS remaining_vacancies,
               COALESCE(NULLIF(j.subject_name, ''), NULLIF(j.subject, ''), j.job_title) AS teaching_load_title
        {$baseFrom}
        HAVING remaining_vacancies > 0
        {$orderClause}
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);
    if ($params) {
        $queryParams = array_merge($params, [$limit, $offset]);
        $stmt->bind_param($types . 'ii', ...$queryParams);
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $title = nc_format_teaching_load_title($row);
        $isFullTime = stripos((string)$row['job_type'], 'full') !== false;
        $salaryDisplay = $isFullTime
            ? (($row['salary_grade'] ?? '') !== '' ? 'SGD ' . $row['salary_grade'] : 'SGD pending HR configuration')
            : 'Salary projection computed from qualification and load hours';

        $jobs[] = [
            'id' => (int)$row['id'],
            'job_title' => $row['job_title'],
            'teaching_load_title' => $title,
            'department_role' => $row['department_role'],
            'program' => $row['program'] ?: $row['department_role'],
            'job_type' => $row['job_type'],
            'locations' => $row['locations'],
            'subject' => $row['subject'] ?? '',
            'subject_code' => $row['subject_code'] ?? '',
            'subject_name' => $row['subject_name'] ?: ($row['subject'] ?? ''),
            'academic_year' => $row['academic_year'] ?: nc_current_academic_year(),
            'semester' => $row['semester'] ?: nc_current_semester(),
            'academic_period_label' => nc_format_academic_period($row),
            'teaching_schedule' => $row['teaching_schedule'] ?? '',
            'teaching_hours_per_week' => $row['teaching_hours_per_week'] !== null ? (float)$row['teaching_hours_per_week'] : null,
            'load_units' => $row['load_units'] !== null ? (float)$row['load_units'] : null,
            'required_instructors' => (int)($row['required_instructors'] ?? 1),
            'assigned_instructors' => (int)$row['assigned_instructors'],
            'remaining_vacancies' => (int)$row['remaining_vacancies'],
            'salary_display' => $salaryDisplay,
            'salary_range' => $salaryDisplay,
            'job_description' => $row['job_description'],
            'application_deadline' => $row['application_deadline'],
            'status' => $row['status']
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'jobs' => $jobs,
        'teaching_loads_title' => 'Available Teaching Loads for ' . nc_format_academic_period([
            'academic_year' => $academicYear ?: nc_current_academic_year(),
            'semester' => $semester ?: nc_current_semester(),
        ]),
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_jobs' => $totalJobs,
            'jobs_per_page' => $limit,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1,
            'showing_from' => $totalJobs > 0 ? $offset + 1 : 0,
            'showing_to' => min($offset + $limit, $totalJobs)
        ]
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching teaching loads: ' . $e->getMessage()]);
}

$conn->close();
?>