<?php
/**
 * Normalize existing NC Full-Time Instructor I postings to SG13.
 *
 * Run from the project root:
 * php database/migrations/instructor_i_salary_grade.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../shared/helpers/recruitment.php';

$select = $conn->query("SELECT id, job_title, job_type, salary_grade FROM job");
if (!$select) {
    throw new RuntimeException('Unable to inspect job classifications: ' . $conn->error);
}

$update = $conn->prepare('UPDATE job SET salary_grade = ? WHERE id = ?');
if (!$update) {
    throw new RuntimeException('Unable to prepare Salary Grade normalization: ' . $conn->error);
}

$updated = 0;
$configuration = nc_salary_configuration();
while ($job = $select->fetch_assoc()) {
    if (nc_normalize_employment_type($job['job_type'] ?? '') !== 'full_time') {
        continue;
    }

    $resolved = nc_resolve_job_salary_grade($job, $configuration);
    if ($resolved !== 'SG13' || nc_normalize_salary_grade($job['salary_grade'] ?? '') === 'SG13') {
        continue;
    }

    $jobId = (int)$job['id'];
    $update->bind_param('si', $resolved, $jobId);
    if (!$update->execute()) {
        throw new RuntimeException('Unable to update job #' . $jobId . ': ' . $update->error);
    }
    $updated += $update->affected_rows;
}

$update->close();
$conn->close();
echo "Instructor I Salary Grade normalization complete; {$updated} job(s) updated to SG13.\n";
