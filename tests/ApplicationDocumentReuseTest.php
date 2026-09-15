<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/recruitment.php';
require_once __DIR__ . '/../shared/helpers/application_documents.php';

$passed = 0;
$failed = 0;
function documentCheck(bool $condition, string $name): void
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

if (!nc_table_exists($conn, 'application_document_versions')) {
    fwrite(STDERR, "Run database/migrations/application_document_versions.php before this test.\n");
    exit(1);
}

$conn->begin_transaction();
try {
    $email = 'document-test-' . bin2hex(random_bytes(5)) . '@example.invalid';
    $password = password_hash('test-only', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO applicants (first_name, last_name, applicant_email, applicant_password, is_verified) VALUES ('Document', 'Test', ?, ?, 1)");
    $stmt->bind_param('ss', $email, $password);
    $stmt->execute();
    $userId = (int)$conn->insert_id;
    $stmt->close();

    $firstLookup = nc_find_reusable_documents($conn, $userId);
    documentCheck(!$firstLookup['is_existing_applicant'] && !$firstLookup['documents'], '1 first-time applicant has no reusable documents');
    documentCheck(count(nc_missing_application_documents([], false)) === 7, '2 first-time applicant is still missing every base required document');

    $firstDocuments = [
        'application_letter' => 'first-application-letter.pdf',
        'resume' => 'first-resume.pdf',
        'letter_of_intent' => 'first-letter-of-intent.pdf',
        'tor' => 'first-tor.pdf',
        'diploma' => 'first-diploma.pdf',
        'professional_license' => null,
        'coe' => 'first-coe.pdf',
        'seminars_trainings' => 'first-seminar.pdf',
        'masteral_cert' => 'master-certification-first-sem.pdf',
        'certificate_of_grades' => 'master-grades-first-sem.pdf',
        'proof_of_enrollment' => 'enrollment-first-sem.pdf',
    ];
    $insert = $conn->prepare("INSERT INTO job_applicants
        (applicant_name, position, applied_date, status, workflow_stage, full_name, applicant_email, contact_num, user_id, job_id, application_type, academic_year, semester, application_letter, resume, letter_of_intent, tor, diploma, professional_license, coe, seminars_trainings, masteral_cert, certificate_of_grades, proof_of_enrollment)
        VALUES ('Document', 'TEST-101 Instructor', CURDATE(), 'Passed', 'passed', 'Document Test', ?, '000', ?, NULL, 'new', '2026-2027', 'First Semester', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insert->bind_param(
        'sisssssssssss',
        $email,
        $userId,
        $firstDocuments['application_letter'],
        $firstDocuments['resume'],
        $firstDocuments['letter_of_intent'],
        $firstDocuments['tor'],
        $firstDocuments['diploma'],
        $firstDocuments['professional_license'],
        $firstDocuments['coe'],
        $firstDocuments['seminars_trainings'],
        $firstDocuments['masteral_cert'],
        $firstDocuments['certificate_of_grades'],
        $firstDocuments['proof_of_enrollment']
    );
    $insert->execute();
    $firstApplicationId = (int)$conn->insert_id;
    $insert->close();
    foreach ($firstDocuments as $field => $fileName) {
        if ($fileName) {
            nc_record_application_document_version($conn, $userId, $firstApplicationId, $field, $fileName, $fileName);
        }
    }

    $renewalLookup = nc_find_reusable_documents($conn, $userId);
    documentCheck($renewalLookup['is_existing_applicant'], '3 prior application is recognized as an existing applicant');
    documentCheck(($renewalLookup['documents']['masteral_cert']['file_name'] ?? null) === $firstDocuments['masteral_cert'], '4 first-semester academic document is reusable');

    $secondUploads = array_fill_keys(array_keys(nc_application_document_definitions()), null);
    $secondUploads['certificate_of_grades'] = 'master-grades-second-sem.pdf';
    $secondDocuments = nc_merge_reusable_document_values($secondUploads, $renewalLookup['documents']);
    documentCheck($secondDocuments['tor'] === $firstDocuments['tor'] && $secondDocuments['certificate_of_grades'] === 'master-grades-second-sem.pdf', '5 renewal keeps unchanged files and overrides only the selected document');
    documentCheck(!nc_missing_application_documents($secondDocuments, true), '6 renewal passes when existing and updated required documents are complete');

    $insert = $conn->prepare("INSERT INTO job_applicants
        (applicant_name, position, applied_date, status, workflow_stage, full_name, applicant_email, contact_num, user_id, job_id, application_type, academic_year, semester, application_letter, resume, letter_of_intent, tor, diploma, professional_license, coe, seminars_trainings, masteral_cert, certificate_of_grades, proof_of_enrollment)
        VALUES ('Document', 'TEST-202 Instructor', CURDATE(), 'Submitted', 'secretary_review', 'Document Test', ?, '000', ?, NULL, 'renewing', '2026-2027', 'Second Semester', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insert->bind_param(
        'sisssssssssss',
        $email,
        $userId,
        $secondDocuments['application_letter'],
        $secondDocuments['resume'],
        $secondDocuments['letter_of_intent'],
        $secondDocuments['tor'],
        $secondDocuments['diploma'],
        $secondDocuments['professional_license'],
        $secondDocuments['coe'],
        $secondDocuments['seminars_trainings'],
        $secondDocuments['masteral_cert'],
        $secondDocuments['certificate_of_grades'],
        $secondDocuments['proof_of_enrollment']
    );
    $insert->execute();
    $secondApplicationId = (int)$conn->insert_id;
    $insert->close();
    foreach ($secondDocuments as $field => $fileName) {
        if ($fileName) {
            $sourceId = empty($secondUploads[$field]) ? $firstApplicationId : null;
            nc_record_application_document_version($conn, $userId, $secondApplicationId, $field, $fileName, $fileName, $sourceId);
        }
    }

    $applicantCount = (int)$conn->query("SELECT COUNT(*) AS count FROM applicants WHERE id = {$userId}")->fetch_assoc()['count'];
    $applicationCount = (int)$conn->query("SELECT COUNT(*) AS count FROM job_applicants WHERE user_id = {$userId}")->fetch_assoc()['count'];
    documentCheck($applicantCount === 1 && $applicationCount === 2, '7 another subject/load reuses one applicant record and creates application history only');

    nc_seed_legacy_document_version($conn, $userId, $secondApplicationId, 'masteral_cert', $secondDocuments['masteral_cert']);
    $updatedMaster = 'master-certification-updated.pdf';
    $update = $conn->prepare('UPDATE job_applicants SET masteral_cert = ? WHERE id = ? AND user_id = ?');
    $update->bind_param('sii', $updatedMaster, $secondApplicationId, $userId);
    $update->execute();
    $update->close();
    nc_record_application_document_version($conn, $userId, $secondApplicationId, 'masteral_cert', $updatedMaster, $updatedMaster);

    $firstRow = $conn->query("SELECT masteral_cert FROM job_applicants WHERE id = {$firstApplicationId}")->fetch_assoc();
    $secondRow = $conn->query("SELECT masteral_cert, tor FROM job_applicants WHERE id = {$secondApplicationId}")->fetch_assoc();
    documentCheck($firstRow['masteral_cert'] === $firstDocuments['masteral_cert'], '8 replacing a second-semester document does not alter the first-semester application');
    documentCheck($secondRow['masteral_cert'] === $updatedMaster && $secondRow['tor'] === $firstDocuments['tor'], '9 only the selected second-semester document is replaced');

    $history = $conn->query("SELECT version_number, is_active, file_name FROM application_document_versions WHERE application_id = {$secondApplicationId} AND document_type = 'masteral_cert' ORDER BY version_number")->fetch_all(MYSQLI_ASSOC);
    documentCheck(count($history) === 2 && (int)$history[0]['is_active'] === 0 && (int)$history[1]['is_active'] === 1 && $history[1]['file_name'] === $updatedMaster, '10 replacement history is preserved and the latest document is active');

    $currentLookup = nc_find_reusable_documents($conn, $userId);
    documentCheck(($currentLookup['documents']['masteral_cert']['file_name'] ?? null) === $updatedMaster, '11 the latest update becomes the applicant-level reusable document');

    $conn->query("UPDATE job_applicants SET resubmission_documents = '[\"tor\"]' WHERE id = {$secondApplicationId}");
    $invalidLookup = nc_find_reusable_documents($conn, $userId);
    documentCheck(!isset($invalidLookup['documents']['tor']), '12 a document currently requested for resubmission is not treated as reusable');
    $conn->query("UPDATE job_applicants SET resubmission_documents = NULL WHERE id = {$secondApplicationId}");

    $incompleteEmail = 'document-incomplete-' . bin2hex(random_bytes(5)) . '@example.invalid';
    $stmt = $conn->prepare("INSERT INTO applicants (first_name, last_name, applicant_email, applicant_password, is_verified) VALUES ('Incomplete', 'Test', ?, ?, 1)");
    $stmt->bind_param('ss', $incompleteEmail, $password);
    $stmt->execute();
    $incompleteUserId = (int)$conn->insert_id;
    $stmt->close();
    $insert = $conn->prepare("INSERT INTO job_applicants (applicant_name, position, applied_date, status, workflow_stage, full_name, applicant_email, contact_num, user_id, letter_of_intent, application_letter) VALUES ('Incomplete', 'TEST', CURDATE(), 'Submitted', 'secretary_review', 'Incomplete Test', ?, '000', ?, '', 'only-one-file.pdf')");
    $insert->bind_param('si', $incompleteEmail, $incompleteUserId);
    $insert->execute();
    $insert->close();
    $incompleteLookup = nc_find_reusable_documents($conn, $incompleteUserId);
    $incompleteValues = nc_merge_reusable_document_values(array_fill_keys(array_keys($definitions = nc_application_document_definitions()), null), $incompleteLookup['documents']);
    documentCheck(count(nc_missing_application_documents($incompleteValues, false)) === 6, '13 an existing applicant is still asked for missing required documents');

    $conn->rollback();
} catch (Throwable $exception) {
    $conn->rollback();
    fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . "\n");
    exit(1);
}

echo "\n{$passed} passed, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);
