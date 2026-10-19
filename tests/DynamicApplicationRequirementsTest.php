<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/dynamic_application_requirements.php';

$passed = 0;
$failed = 0;
function requirementCheck(bool $condition, string $name): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS: {$name}\n";
    } else {
        $failed++;
        echo "FAIL: {$name}\n";
    }
}

if (!nc_requirement_tables_ready($conn)) {
    fwrite(STDERR, "Run database/migrations/dynamic_application_requirements.php first.\n");
    exit(1);
}

$conn->begin_transaction();
try {
    $insertJob = $conn->prepare("INSERT INTO job
        (job_title, department_role, job_type, locations, salary_range, application_deadline, job_description)
        VALUES (?, ?, 'Part-time', 'Norzagaray College', '', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'Dynamic requirement test')");
    $titleA = 'REQ-A-' . bin2hex(random_bytes(3));
    $departmentA = 'Computing Studies';
    $insertJob->bind_param('ss', $titleA, $departmentA);
    $insertJob->execute();
    $jobA = (int)$conn->insert_id;
    $titleB = 'REQ-B-' . bin2hex(random_bytes(3));
    $departmentB = 'Education';
    $insertJob->bind_param('ss', $titleB, $departmentB);
    $insertJob->execute();
    $jobB = (int)$conn->insert_id;
    $insertJob->close();

    nc_sync_teaching_load_requirements($conn, $jobA, [
        ['requirement_name' => 'Application Letter', 'description' => 'Required test file', 'category' => 'Application Documents', 'is_required' => true],
        ['requirement_name' => 'Teaching Portfolio', 'description' => 'Optional test file', 'category' => 'Supporting Documents', 'is_required' => false],
    ]);
    nc_sync_teaching_load_requirements($conn, $jobB, [
        ['requirement_key' => 'professional_license', 'requirement_name' => 'PRC License', 'description' => '', 'category' => 'Supporting Documents', 'is_required' => true],
    ]);
    $requirementsA = nc_get_teaching_load_requirements($conn, $jobA);
    $requirementsB = nc_get_teaching_load_requirements($conn, $jobB);
    requirementCheck(count($requirementsA) === 2 && count($requirementsB) === 1, '1 teaching loads keep independent requirement sets');
    requirementCheck($requirementsA[0]['is_required'] && !$requirementsA[1]['is_required'], '2 required and optional statuses are stored independently');
    requirementCheck($requirementsB[0]['input_name'] === 'license' && $requirementsB[0]['legacy_document_type'] === 'professional_license', '2a predefined create-form requirements retain legacy upload compatibility');

    $email = 'dynamic-requirement-' . bin2hex(random_bytes(5)) . '@example.invalid';
    $password = password_hash('test-only', PASSWORD_DEFAULT);
    $applicant = $conn->prepare("INSERT INTO applicants (first_name, last_name, applicant_email, applicant_password, is_verified) VALUES ('Dynamic', 'Test', ?, ?, 1)");
    $applicant->bind_param('ss', $email, $password);
    $applicant->execute();
    $userId = (int)$conn->insert_id;
    $applicant->close();

    $application = $conn->prepare("INSERT INTO job_applicants
        (applicant_name, position, applied_date, status, workflow_stage, full_name, applicant_email, contact_num, user_id, job_id, assigned_to_department, letter_of_intent)
        VALUES ('Dynamic', ?, CURDATE(), 'Submitted', 'secretary_review', 'Dynamic Test', ?, '000', ?, ?, ?, '')");
    $application->bind_param('ssiis', $titleA, $email, $userId, $jobA, $departmentA);
    $application->execute();
    $applicationId = (int)$conn->insert_id;
    $application->close();

    $completion = nc_requirement_completion($conn, $applicationId, $jobA);
    requirementCheck($completion['required_total'] === 1 && $completion['required_remaining'] === 1, '3 only required documents count as missing');

    nc_record_requirement_submission($conn, $applicationId, (int)$requirementsA[0]['id'], 'test/application-letter.pdf', 'application-letter.pdf');
    $completion = nc_requirement_completion($conn, $applicationId, $jobA);
    requirementCheck($completion['is_complete'] && $completion['required_remaining'] === 0, '4 a submitted required document completes the requirements stage');

    nc_sync_teaching_load_requirements($conn, $jobA, [
        ['id' => $requirementsA[0]['id'], 'requirement_name' => 'Application Letter', 'description' => 'Renamed description', 'category' => 'Application Documents', 'is_required' => true],
        ['id' => $requirementsA[1]['id'], 'requirement_name' => 'Teaching Portfolio', 'description' => 'Now mandatory', 'category' => 'Supporting Documents', 'is_required' => true],
    ]);
    $completion = nc_requirement_completion($conn, $applicationId, $jobA);
    requirementCheck($completion['required_remaining'] === 1 && !$completion['is_complete'], '5 changing optional to required recalculates completion');
    requirementCheck(!empty($completion['requirements'][0]['submission']), '6 editing requirement text preserves its existing submission');

    nc_record_requirement_submission($conn, $applicationId, (int)$requirementsA[1]['id'], 'test/portfolio.pdf', 'portfolio.pdf');
    nc_sync_teaching_load_requirements($conn, $jobA, [
        ['id' => $requirementsA[0]['id'], 'requirement_name' => 'Application Letter', 'description' => '', 'category' => 'Application Documents', 'is_required' => true],
    ]);
    $activeAfterRemoval = nc_get_teaching_load_requirements($conn, $jobA, true);
    $allAfterRemoval = nc_get_teaching_load_requirements($conn, $jobA, false);
    $submissionAfterRemoval = nc_application_requirement_submission_map($conn, $applicationId);
    $removedRow = array_values(array_filter($allAfterRemoval, static fn(array $row): bool => (int)$row['id'] === (int)$requirementsA[1]['id']))[0] ?? null;
    requirementCheck(count($activeAfterRemoval) === 1 && $removedRow && !$removedRow['is_active'], '7 removing a requirement soft-deactivates it');
    requirementCheck(isset($submissionAfterRemoval[(int)$requirementsA[1]['id']]), '8 soft removal preserves the applicant submission history');
    $completion = nc_requirement_completion($conn, $applicationId, $jobA);
    requirementCheck($completion['is_complete'] && $completion['required_total'] === 1, '9 removed requirements no longer affect current completion');

    requirementCheck(nc_admin_can_manage_requirements('Secretary', '', 'Education'), '10 Secretary can manage teaching-load requirements');
    requirementCheck(nc_admin_can_manage_requirements('Department Head', 'Computing Studies', 'Computer Science'), '11 Dean department aliases are authorized');
    requirementCheck(!nc_admin_can_manage_requirements('Department Head', 'Education', 'Computing Studies'), '12 a Dean cannot manage another department');
    requirementCheck(!nc_admin_can_manage_requirements('Recruiter', 'Computing Studies', 'Computing Studies'), '13 unauthorized roles cannot manage requirements');

    $conn->rollback();
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "\n{$passed} passed, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);

