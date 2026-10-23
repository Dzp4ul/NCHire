<?php

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$sourceDatabase = getenv('NCHIRE_SOURCE_DB') ?: 'nchire';
$targetDatabase = getenv('NCHIRE_DB_NAME') ?: 'nchire_e2e';

if (!preg_match('/^[a-zA-Z0-9_]+_e2e$/', $targetDatabase)) {
    fwrite(STDERR, "Refusing to rebuild a database that does not end in _e2e.\n");
    exit(1);
}
if ($sourceDatabase === $targetDatabase) {
    fwrite(STDERR, "Source and target databases must be different.\n");
    exit(1);
}

$server = new mysqli('127.0.0.1', 'root', '');
$server->set_charset('utf8mb4');

$quoteIdentifier = static fn(string $value): string => '`' . str_replace('`', '``', $value) . '`';
$source = $quoteIdentifier($sourceDatabase);
$target = $quoteIdentifier($targetDatabase);

$server->query("DROP DATABASE IF EXISTS {$target}");
$server->query("CREATE DATABASE {$target} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$tables = [];
$result = $server->query("SHOW FULL TABLES FROM {$source}");
while ($row = $result->fetch_row()) {
    if (($row[1] ?? '') === 'BASE TABLE') {
        $tables[] = (string)$row[0];
    }
}

$server->select_db($targetDatabase);
$server->query('SET FOREIGN_KEY_CHECKS = 0');
foreach ($tables as $table) {
    $showCreate = $server->query("SHOW CREATE TABLE {$source}." . $quoteIdentifier($table))->fetch_assoc();
    $createSql = $showCreate['Create Table'] ?? null;
    if (!is_string($createSql) || $createSql === '') {
        throw new RuntimeException("Could not read the schema for {$table}.");
    }
    $server->query($createSql);
}
$autoIncrementTables = $server->query(
    "SELECT DISTINCT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" .
    $server->real_escape_string($targetDatabase) . "' AND EXTRA LIKE '%auto_increment%'"
);
while ($row = $autoIncrementTables->fetch_assoc()) {
    $server->query('ALTER TABLE ' . $quoteIdentifier((string)$row['TABLE_NAME']) . ' AUTO_INCREMENT = 1');
}
$server->query('SET FOREIGN_KEY_CHECKS = 1');

// Keep fixture files inside the web-accessible upload roots so download links work.
$fixtureSource = __DIR__ . '/fixtures/valid-document.pdf';
$fixtureTargets = [
    dirname(__DIR__) . '/user/uploads/requirements/e2e',
    dirname(__DIR__) . '/user/uploads/education_documents/e2e',
];
foreach ($fixtureTargets as $targetDir) {
    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
        throw new RuntimeException("Could not create fixture directory {$targetDir}.");
    }
    if (!copy($fixtureSource, $targetDir . '/valid-document.pdf')) {
        throw new RuntimeException("Could not copy fixture into {$targetDir}.");
    }
}

$server->begin_transaction();
try {
    $adminInsert = $server->prepare(
        'INSERT INTO admin_users (full_name, email, password, password_change_required, role, department, status) VALUES (?, ?, ?, 0, ?, ?, ?)'
    );
    $adminPassword = password_hash('AdminPass1!', PASSWORD_DEFAULT);
    $accounts = [
        ['E2E Administrator', 'admin.e2e@example.test', $adminPassword, 'Admin', 'Administration', 'Active'],
        ['E2E Secretary', 'secretary.e2e@example.test', $adminPassword, 'Secretary', 'Registrar', 'Active'],
        ['E2E Security Secretary', 'security.secretary.e2e@example.test', $adminPassword, 'Secretary', 'Registrar', 'Active'],
        ['E2E Computing Dean', 'dean.cs.e2e@example.test', $adminPassword, 'Department Head', 'Computing Studies', 'Active'],
        ['E2E Education Dean', 'dean.edu.e2e@example.test', $adminPassword, 'Department Head', 'Education', 'Active'],
        ['E2E Inactive Admin', 'inactive.e2e@example.test', $adminPassword, 'Admin', 'Administration', 'Inactive'],
    ];
    foreach ($accounts as [$name, $email, $password, $role, $department, $status]) {
        $adminInsert->bind_param('ssssss', $name, $email, $password, $role, $department, $status);
        $adminInsert->execute();
    }
    $adminInsert->close();

    $applicantInsert = $server->prepare(
        'INSERT INTO applicants (first_name, last_name, applicant_email, applicant_password, is_verified, contact_number, address, password_change_required) VALUES (?, ?, ?, ?, 1, ?, ?, 0)'
    );
    $applicantPassword = password_hash('ApplicantPass1!', PASSWORD_DEFAULT);
    $applicants = [
        ['Avery', 'Applicant', 'applicant.e2e@example.test', $applicantPassword, '09170000001', 'Norzagaray, Bulacan'],
        ['Casey', 'Pipeline', 'pipeline.e2e@example.test', $applicantPassword, '09170000002', 'Norzagaray, Bulacan'],
        ['Ivy', 'Incomplete', 'incomplete.e2e@example.test', $applicantPassword, '', ''],
        ['Riley', 'Restricted', 'restricted.e2e@example.test', $applicantPassword, '09170000004', 'Norzagaray, Bulacan'],
    ];
    $applicantIds = [];
    foreach ($applicants as [$first, $last, $email, $password, $phone, $address]) {
        $applicantInsert->bind_param('ssssss', $first, $last, $email, $password, $phone, $address);
        $applicantInsert->execute();
        $applicantIds[$email] = (int)$server->insert_id;
    }
    $applicantInsert->close();

    $educationInsert = $server->prepare(
        "INSERT INTO user_education (user_id, education_level, degree, field_of_study, institution, education_status, completed_units, year_completed, certificate_of_grades, proof_of_enrollment, start_year, end_year, gpa) VALUES (?, ?, ?, ?, 'Norzagaray College Test University', ?, ?, ?, 'uploads/education_documents/e2e/valid-document.pdf', 'uploads/education_documents/e2e/valid-document.pdf', 2024, ?, '1.25')"
    );
    $educationRows = [
        [$applicantIds['applicant.e2e@example.test'], 'master', 'Master of Information Technology', 'Information Technology', 'ongoing', 17, null, 0],
        [$applicantIds['pipeline.e2e@example.test'], 'master', 'Master of Science in Computer Science', 'Computer Science', 'completed', 36, 2026, 2026],
        [$applicantIds['restricted.e2e@example.test'], 'doctorate', 'Doctor of Information Technology', 'Information Technology', 'completed', 45, 2025, 2025],
    ];
    foreach ($educationRows as [$userId, $level, $degree, $field, $status, $units, $yearCompleted, $endYear]) {
        $educationInsert->bind_param('issssiii', $userId, $level, $degree, $field, $status, $units, $yearCompleted, $endYear);
        $educationInsert->execute();
    }
    $educationInsert->close();

    $experienceInsert = $server->prepare(
        "INSERT INTO user_experience (user_id, experience_type, job_title, company, location, start_date, end_date, description, is_current) VALUES (?, 'teaching', 'Part-time Instructor', 'E2E Test Academy', 'Bulacan', '2023-01-01', NULL, 'Synthetic test experience', 1)"
    );
    foreach (['applicant.e2e@example.test', 'pipeline.e2e@example.test', 'restricted.e2e@example.test'] as $email) {
        $id = $applicantIds[$email];
        $experienceInsert->bind_param('i', $id);
        $experienceInsert->execute();
    }
    $experienceInsert->close();

    $skillInsert = $server->prepare("INSERT INTO user_skills (user_id, skill_name, skill_category, skill_level) VALUES (?, 'Curriculum Design', 'Teaching', 4)");
    foreach (['applicant.e2e@example.test', 'pipeline.e2e@example.test'] as $email) {
        $id = $applicantIds[$email];
        $skillInsert->bind_param('i', $id);
        $skillInsert->execute();
    }
    $skillInsert->close();

    $qualificationInsert = $server->prepare("INSERT INTO user_qualifications (user_id, qualification_type, title, issuing_organization, issued_date) VALUES (?, 'certification', 'E2E Teaching Certificate', 'Test Authority', '2025-01-15')");
    $primaryApplicantId = $applicantIds['applicant.e2e@example.test'];
    $qualificationInsert->bind_param('i', $primaryApplicantId);
    $qualificationInsert->execute();
    $qualificationInsert->close();

    $server->query("INSERT INTO graduate_unit_rate_rules (qualification_key, minimum_units, maximum_units, hourly_rate, is_active, source_note) VALUES
        ('master_ongoing', 0, 17, 150.00, 1, 'E2E fixture'),
        ('master_ongoing', 18, NULL, 170.00, 1, 'E2E fixture')");
    $server->query("INSERT INTO rooms (room_name, campus_location, is_active) VALUES
        ('Room 101', 'Norzagaray College', 1),
        ('Room 202', 'Norzagaray College', 1),
        ('Retired Room', 'Norzagaray College', 0)");

    $jobInsert = $server->prepare(
        "INSERT INTO job (job_title, department_role, job_type, locations, salary_range, application_deadline, status, subject, subject_code, subject_name, program, academic_year, semester, teaching_schedule, teaching_hours_per_week, load_units, lecture_units, laboratory_units, required_instructors, initial_available_sections, available_sections, salary_grade, job_description, job_requirements, minimum_education_level, graduate_requirement, minimum_graduate_units, minimum_experience_years, teaching_experience_requirement, education, experience, training, eligibility, competency, duties) VALUES (?, ?, ?, 'Norzagaray College', '', ?, ?, ?, ?, ?, ?, '2026-2027', 'First Semester', 'MWF 8:00-10:00', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $jobRows = [
        ['E2E CS 101 - Web Systems', 'Computing Studies', 'Part-time', '2099-12-31', 'Active', 'Computing Studies Professional Subjects', 'CS 101', 'Web Systems', 'BS Computer Science', 6.0, 4.0, 3.0, 1.0, 3, 3, 3, null, 'Synthetic active part-time teaching load.', 'Submit the configured documents.', 'bachelor', 'master_units', 0, 1.0, 'preferred', 'Relevant bachelor degree', 'One year relevant experience', 'Relevant seminars', 'Appropriate eligibility', 'Web development', 'Teach assigned sections'],
        ['E2E EDU 201 - Learning Design', 'Education', 'Full-time', '2099-12-31', 'Active', 'Education Professional Subjects', 'EDU 201', 'Learning Design', 'Bachelor of Secondary Education', 6.0, 3.0, 3.0, 0.0, 1, 1, 1, 'SG13', 'Synthetic active full-time teaching load.', 'Completed graduate degree required.', 'master', 'master_completed', 0, 2.0, 'required', 'Completed master degree', 'Two years teaching', 'Instructional design training', 'LET eligible', 'Instructional design', 'Lead classes'],
        ['E2E CS 404 - Closed Load', 'Computing Studies', 'Part-time', '2099-12-31', 'Closed', 'Computing Studies Professional Subjects', 'CS 404', 'Closed Load', 'BS Computer Science', 3.0, 3.0, 3.0, 0.0, 1, 1, 0, null, 'Synthetic closed teaching load.', '', 'bachelor', 'none', 0, 0.0, 'none', '', '', '', '', '', ''],
        ['E2E CS 410 - Expired Load', 'Computing Studies', 'Part-time', '2020-01-01', 'Active', 'Computing Studies Professional Subjects', 'CS 410', 'Expired Load', 'BS Computer Science', 3.0, 3.0, 3.0, 0.0, 1, 1, 1, null, 'Synthetic expired teaching load.', '', 'bachelor', 'none', 0, 0.0, 'none', '', '', '', '', '', ''],
    ];
    $jobIds = [];
    foreach ($jobRows as $row) {
        $jobInsert->bind_param(
            'sssssssssddddiiisssssidsssssss',
            $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8],
            $row[9], $row[10], $row[11], $row[12], $row[13], $row[14], $row[15], $row[16], $row[17],
            $row[18], $row[19], $row[20], $row[21], $row[22], $row[23], $row[24], $row[25], $row[26],
            $row[27], $row[28], $row[29]
        );
        $jobInsert->execute();
        $jobIds[$row[0]] = (int)$server->insert_id;
    }
    $jobInsert->close();

    $requirementInsert = $server->prepare(
        'INSERT INTO teaching_load_requirements (teaching_load_id, requirement_key, requirement_name, description, category, is_required, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
    );
    foreach ($jobIds as $jobTitle => $jobId) {
        $requirements = $jobTitle === 'E2E CS 101 - Web Systems'
            ? [
                ['application_letter', 'Application Letter', 'Required synthetic PDF.', 'Application Documents', 1, 1],
                ['custom_teaching_portfolio', 'Teaching Portfolio', 'Optional supporting portfolio.', 'Supporting Documents', 0, 2],
              ]
            : [['resume', 'Updated Resume', 'Required synthetic PDF.', 'Application Documents', 1, 1]];
        foreach ($requirements as [$key, $name, $description, $category, $required, $order]) {
            $requirementInsert->bind_param('issssii', $jobId, $key, $name, $description, $category, $required, $order);
            $requirementInsert->execute();
        }
    }
    $requirementInsert->close();

    $pipelineId = $applicantIds['pipeline.e2e@example.test'];
    $restrictedId = $applicantIds['restricted.e2e@example.test'];
    $csJobId = $jobIds['E2E CS 101 - Web Systems'];
    $eduJobId = $jobIds['E2E EDU 201 - Learning Design'];
    $applicationInsert = $server->prepare(
        "INSERT INTO job_applicants (applicant_name, position, applied_date, status, workflow_stage, full_name, applicant_email, contact_num, user_id, job_id, assigned_to_department, letter_of_intent, application_letter, resume, academic_year, semester) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, 'fixture-letter.pdf', 'fixture-application.pdf', 'fixture-resume.pdf', '2026-2027', 'First Semester')"
    );
    $applications = [
        ['Casey Pipeline', 'E2E CS 101 - Web Systems', 'Submitted', 'secretary_review', 'Casey Pipeline', 'pipeline.e2e@example.test', '09170000002', $pipelineId, $csJobId, 'Computing Studies'],
        ['Casey Pipeline', 'E2E CS 101 - Web Systems', 'Waiting for Interview Schedule', 'waiting_interview_schedule', 'Casey Pipeline', 'pipeline.e2e@example.test', '09170000002', $pipelineId, $csJobId, 'Computing Studies'],
        ['Riley Restricted', 'E2E EDU 201 - Learning Design', 'Waiting for Interview Schedule', 'waiting_interview_schedule', 'Riley Restricted', 'restricted.e2e@example.test', '09170000004', $restrictedId, $eduJobId, 'Education'],
        ['Riley Restricted', 'E2E CS 404 - Closed Load', 'Cancelled', 'cancelled', 'Riley Restricted', 'restricted.e2e@example.test', '09170000004', $restrictedId, $jobIds['E2E CS 404 - Closed Load'], 'Computing Studies'],
    ];
    $applicationIds = [];
    foreach ($applications as $row) {
        $applicationInsert->bind_param('sssssssiis', $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9]);
        $applicationInsert->execute();
        $applicationIds[] = (int)$server->insert_id;
    }
    $applicationInsert->close();

    $applicationLetterRequirement = $server->prepare(
        "SELECT id FROM teaching_load_requirements WHERE teaching_load_id = ? AND requirement_key = 'application_letter' LIMIT 1"
    );
    $applicationLetterRequirement->bind_param('i', $csJobId);
    $applicationLetterRequirement->execute();
    $requirementId = (int)($applicationLetterRequirement->get_result()->fetch_assoc()['id'] ?? 0);
    $applicationLetterRequirement->close();
    if ($requirementId > 0) {
        $submission = $server->prepare(
            "INSERT INTO applicant_requirement_submissions (application_id, requirement_id, file_path, original_filename, storage_area, version_number, is_active) VALUES (?, ?, 'e2e/valid-document.pdf', 'valid-document.pdf', 'requirements', 1, 1)"
        );
        $submission->bind_param('ii', $applicationIds[0], $requirementId);
        $submission->execute();
        $submission->close();
    }

    $banUntil = (new DateTimeImmutable('+14 days'))->format('Y-m-d H:i:s');
    $banReason = 'Synthetic active restriction';
    $restrictedEmail = 'restricted.e2e@example.test';
    $banUpdate = $server->prepare('UPDATE applicants SET rejection_ban_until = ?, ban_reason = ?, banned_by = ? WHERE id = ?');
    $bannedBy = 'E2E Secretary';
    $banUpdate->bind_param('sssi', $banUntil, $banReason, $bannedBy, $restrictedId);
    $banUpdate->execute();
    $banUpdate->close();

    $notification = $server->prepare("INSERT INTO notifications (user_id, user_email, user_name, title, message, type, is_read) VALUES (?, ?, 'Avery Applicant', 'E2E Notification', 'Synthetic unread notification.', 'info', 0)");
    $applicantEmail = 'applicant.e2e@example.test';
    $notification->bind_param('is', $primaryApplicantId, $applicantEmail);
    $notification->execute();
    $notification->close();

    $server->commit();
} catch (Throwable $error) {
    $server->rollback();
    throw $error;
}

echo "Prepared isolated database {$targetDatabase} with synthetic fixtures only.\n";

