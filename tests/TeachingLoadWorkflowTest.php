<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/recruitment.php';

$passed = 0;
$failed = 0;
$jobId = 0;
$userId = 0;
$applicationIds = [];

function teaching_load_check(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS: {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL: {$message}\n";
}

function create_test_application(mysqli $conn, int $userId, int $jobId, string $email, int $sequence): int
{
    $name = "Teaching Load Test {$sequence}";
    $position = 'TST 101 - Transaction Test';
    $status = 'Demo Passed';
    $stage = 'psych_completed';
    $contact = '0000000000';
    $intent = 'test-only-letter.pdf';
    $department = 'Computing Studies';
    $receipt = 'test-only-receipt.pdf';
    $stmt = $conn->prepare("INSERT INTO job_applicants (applicant_name, position, applied_date, status, workflow_stage, full_name, applicant_email, contact_num, user_id, job_id, assigned_to_department, letter_of_intent, psych_exam_receipt, academic_year, semester) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $academicYear = nc_current_academic_year();
    $semester = nc_current_semester();
    $stmt->bind_param('sssssssiisssss', $name, $position, $status, $stage, $name, $email, $contact, $userId, $jobId, $department, $intent, $receipt, $academicYear, $semester);
    if (!$stmt->execute()) {
        throw new RuntimeException('Unable to create test application: ' . $stmt->error);
    }
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}

try {
    teaching_load_check(nc_calculate_teaching_hours(3, 1) === 6.0, '3 lecture units plus 1 laboratory unit equals 6 teaching hours');
    teaching_load_check(nc_calculate_teaching_hours(2, 2) === 8.0, '2 lecture units plus 2 laboratory units equals 8 teaching hours');
    teaching_load_check(nc_calculate_teaching_hours(1.5, 0.5) === 3.0, 'fractional non-negative units are calculated consistently');

    $unique = bin2hex(random_bytes(6));
    $email = "teaching-load-test-{$unique}@example.invalid";
    $password = password_hash('test-only-password', PASSWORD_DEFAULT);
    $userStmt = $conn->prepare("INSERT INTO applicants (first_name, last_name, applicant_email, applicant_password, is_verified) VALUES ('Teaching', 'Load Test', ?, ?, 1)");
    $userStmt->bind_param('ss', $email, $password);
    if (!$userStmt->execute()) {
        throw new RuntimeException('Unable to create test applicant: ' . $userStmt->error);
    }
    $userId = (int)$userStmt->insert_id;
    $userStmt->close();

    $educationStmt = $conn->prepare("INSERT INTO user_education (user_id, education_level, degree, field_of_study, institution, education_status, completed_units, start_year, end_year) VALUES (?, 'master', 'Master in Information Technology', 'Information Technology', 'NCHire Test Institution', 'ongoing', 24, 2026, 0)");
    $educationStmt->bind_param('i', $userId);
    if (!$educationStmt->execute()) {
        throw new RuntimeException('Unable to create test education: ' . $educationStmt->error);
    }
    $educationStmt->close();

    $jobStmt = $conn->prepare("INSERT INTO job (job_title, department_role, job_type, locations, salary_range, application_deadline, status, subject, subject_code, subject_name, program, academic_year, semester, teaching_hours_per_week, lecture_units, laboratory_units, required_instructors, initial_available_sections, available_sections, job_description) VALUES ('TST 101 - Transaction Test', 'Computing Studies', 'Part-time', 'Norzagaray College', '', '2099-12-31', 'Active', 'Computing Studies Professional Subjects', 'TST 101', 'Transaction Test', 'BS Computer Science', ?, ?, 6, 3, 1, 2, 2, 2, 'Automated integration test record')");
    $academicYear = nc_current_academic_year();
    $semester = nc_current_semester();
    $jobStmt->bind_param('ss', $academicYear, $semester);
    if (!$jobStmt->execute()) {
        throw new RuntimeException('Unable to create test teaching load: ' . $jobStmt->error);
    }
    $jobId = (int)$jobStmt->insert_id;
    $jobStmt->close();

    $applicationIds[] = create_test_application($conn, $userId, $jobId, $email, 1);
    $first = nc_finalize_teaching_assignment($conn, $applicationIds[0], null, 'Automated transaction test');
    teaching_load_check($first['success'] === true && empty($first['already_assigned']), 'first final hiring creates a teaching assignment');

    $capacity = (int)$conn->query("SELECT available_sections FROM job WHERE id = {$jobId}")->fetch_assoc()['available_sections'];
    teaching_load_check($capacity === 1, 'first final hiring decrements capacity from 2 to 1');

    $repeat = nc_finalize_teaching_assignment($conn, $applicationIds[0], null, 'Automated retry');
    $capacityAfterRepeat = (int)$conn->query("SELECT available_sections FROM job WHERE id = {$jobId}")->fetch_assoc()['available_sections'];
    teaching_load_check($repeat['success'] === true && !empty($repeat['already_assigned']) && $capacityAfterRepeat === 1, 'reprocessing the same hiring transaction does not decrement twice');

    $assignment = $conn->query("SELECT * FROM teaching_assignments WHERE application_id = {$applicationIds[0]}")->fetch_assoc();
    teaching_load_check((float)$assignment['projected_hourly_rate'] === 150.0, "ongoing Master's units use the configured graduate-unit rate");
    teaching_load_check((float)$assignment['projected_weekly_compensation'] === 900.0, 'assignment snapshots projected hourly rate multiplied by teaching hours');

    $applicationIds[] = create_test_application($conn, $userId, $jobId, $email, 2);
    $second = nc_finalize_teaching_assignment($conn, $applicationIds[1], null, 'Automated transaction test');
    $closedJob = $conn->query("SELECT available_sections, status FROM job WHERE id = {$jobId}")->fetch_assoc();
    teaching_load_check($second['success'] === true && (int)$closedJob['available_sections'] === 0 && $closedJob['status'] === 'Closed', 'last assignment closes the teaching load at zero sections');

    $applicationIds[] = create_test_application($conn, $userId, $jobId, $email, 3);
    $third = nc_finalize_teaching_assignment($conn, $applicationIds[2], null, 'Automated over-capacity test');
    $finalCapacity = (int)$conn->query("SELECT available_sections FROM job WHERE id = {$jobId}")->fetch_assoc()['available_sections'];
    teaching_load_check($third['success'] === false && $finalCapacity === 0, 'an over-capacity assignment is rejected without making capacity negative');
} catch (Throwable $e) {
    $failed++;
    echo 'FAIL: integration test exception: ' . $e->getMessage() . "\n";
} finally {
    if ($jobId > 0) {
        $conn->query("DELETE FROM teaching_assignments WHERE job_id = {$jobId}");
        $conn->query("DELETE FROM job_applicants WHERE job_id = {$jobId}");
        $conn->query("DELETE FROM job WHERE id = {$jobId}");
    }
    if ($userId > 0) {
        $conn->query("DELETE FROM user_education WHERE user_id = {$userId}");
        $conn->query("DELETE FROM applicants WHERE id = {$userId}");
    }
}

echo "\nTeaching load workflow tests: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
