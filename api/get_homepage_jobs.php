<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../shared/helpers/recruitment.php';

$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "nchire";

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit();
    }
    $conn->set_charset('utf8mb4');

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
    $vacancyExpr = "GREATEST(COALESCE(j.required_instructors, 1) - COALESCE(a.assigned_count, 0), 0)";
    $query = "
        SELECT j.*, COALESCE(a.assigned_count, 0) AS assigned_instructors,
               {$vacancyExpr} AS remaining_vacancies
        FROM job j
        LEFT JOIN ({$assignedSql}) a ON a.job_id = j.id
        WHERE j.status = 'Active'
          AND j.application_deadline >= CURDATE()
          AND COALESCE(NULLIF(j.subject_name, ''), NULLIF(j.subject, ''), NULLIF(j.subject_code, '')) IS NOT NULL
          AND j.teaching_hours_per_week IS NOT NULL
          AND j.teaching_hours_per_week > 0
        HAVING remaining_vacancies > 0
        ORDER BY j.id DESC
        LIMIT 6
    ";

    $result = $conn->query($query);
    if (!$result) {
        echo json_encode(['success' => false, 'error' => 'Query failed', 'details' => $conn->error]);
        exit();
    }

    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $description = $row['job_description'] ?? 'No description available.';
        if (strlen($description) > 150) {
            $description = substr($description, 0, 150) . '...';
        }

        $isFullTime = stripos((string)$row['job_type'], 'full') !== false;
        $salaryDisplay = $isFullTime
            ? (($row['salary_grade'] ?? '') !== '' ? 'SGD ' . $row['salary_grade'] : 'SGD pending HR configuration')
            : 'Salary projection computed from qualification and load hours';

        $jobs[] = [
            'id' => (int)$row['id'],
            'title' => nc_format_teaching_load_title($row),
            'department' => $row['program'] ?: ($row['department_role'] ?? 'General'),
            'type' => $row['job_type'] ?? 'Full-time',
            'location' => $row['locations'] ?? 'Norzagaray College',
            'salary' => $salaryDisplay,
            'deadline' => !empty($row['application_deadline']) ? date('F d, Y', strtotime($row['application_deadline'])) : 'N/A',
            'description' => $description,
            'academic_year' => $row['academic_year'] ?: nc_current_academic_year(),
            'semester' => $row['semester'] ?: nc_current_semester(),
            'academic_period_label' => nc_format_academic_period($row),
            'teaching_schedule' => $row['teaching_schedule'] ?? '',
            'remaining_vacancies' => (int)$row['remaining_vacancies'],
            'required_instructors' => (int)($row['required_instructors'] ?? 1),
        ];
    }

    echo json_encode([
        'success' => true,
        'jobs' => $jobs,
        'count' => count($jobs),
        'title' => 'Available Teaching Loads for ' . nc_format_academic_period([
            'academic_year' => nc_current_academic_year(),
            'semester' => nc_current_semester(),
        ])
    ]);

    $conn->close();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Exception occurred', 'details' => $e->getMessage()]);
}
?>