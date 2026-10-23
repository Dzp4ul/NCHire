<?php

$sessionId = 'codexsalary' . bin2hex(random_bytes(12));
ini_set('session.use_cookies', '0');
session_cache_limiter('');
session_id($sessionId);

require_once __DIR__ . '/../config/database.php';

$passed = 0;
$failed = 0;
$userId = 0;
$educationId = 0;
$applicationId = 0;
$baseUrl = rtrim((string)(getenv('NCHIRE_TEST_BASE_URL') ?: 'http://localhost'), '/') . '/user';

function profile_crud_check(bool $condition, string $message): void
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

function profile_crud_request(string $url, string $sessionId, string $method = 'GET', array $data = []): array
{
    $headers = [
        'Cookie: PHPSESSID=' . $sessionId,
        'Accept: application/json',
        'X-CSRF-Token: test-csrf-token',
    ];
    $options = [
        'method' => $method,
        'ignore_errors' => true,
        'timeout' => 15,
    ];
    if ($method !== 'GET') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $options['content'] = http_build_query($data);
    }
    $options['header'] = implode("\r\n", $headers);
    $response = file_get_contents($url, false, stream_context_create(['http' => $options]));
    if ($response === false) {
        throw new RuntimeException('Profile endpoint did not return a response.');
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Profile endpoint returned invalid JSON: ' . substr($response, 0, 200));
    }
    return $decoded;
}

function profile_education_payload(array $overrides = []): array
{
    return array_replace([
        'saveEducation' => '1',
        'edit_id' => '',
        'education_level' => 'master',
        'education_status' => 'completed',
        'ed_degree' => 'Master of Arts in Education',
        'ed_fs' => 'Education',
        'ed_ins' => 'NCHire Test University',
        'ed_sy' => '2024',
        'ed_ey' => '2026',
        'year_completed' => '2026',
        'ed_gpa' => '',
        'completed_units' => '36',
    ], $overrides);
}

try {
    $email = 'salary-crud-' . bin2hex(random_bytes(8)) . '@example.invalid';
    $password = password_hash('test-only-password', PASSWORD_BCRYPT);
    $user = $conn->prepare("INSERT INTO applicants (first_name, last_name, applicant_email, applicant_password, is_verified) VALUES ('Salary', 'CRUD Test', ?, ?, 1)");
    $user->bind_param('ss', $email, $password);
    if (!$user->execute()) {
        throw new RuntimeException('Unable to create the temporary CRUD applicant: ' . $user->error);
    }
    $userId = (int)$user->insert_id;
    $user->close();

    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = $email;
    $_SESSION['csrf_token'] = 'test-csrf-token';
    session_write_close();

    $application = $conn->prepare("
        INSERT INTO job_applicants
            (applicant_name, position, applied_date, status, full_name, applicant_email, contact_num, user_id, letter_of_intent)
        VALUES
            ('Legacy', 'Profile identity sync test', CURDATE(), 'Submitted', 'Legacy Name', ?, '09000000000', ?, 'test-only-letter.pdf')
    ");
    $application->bind_param('si', $email, $userId);
    if (!$application->execute()) {
        throw new RuntimeException('Unable to create the identity-sync application: ' . $application->error);
    }
    $applicationId = (int)$application->insert_id;
    $application->close();

    $personal = profile_crud_request(
        $baseUrl . '/save_profile_data.php',
        $sessionId,
        'POST',
        [
            'savePersonal' => '1',
            'applicant_fname' => 'Updated',
            'applicant_lname' => 'Applicant',
            'applicant_email' => $email,
            'applicant_num' => '+639171234567',
            'applicant_address' => 'Test Address',
        ]
    );
    $syncedApplication = $conn->query("
        SELECT applicant_name, full_name, applicant_email, contact_num
        FROM job_applicants
        WHERE id = {$applicationId}
    ")->fetch_assoc();
    profile_crud_check(
        !empty($personal['success'])
        && ($syncedApplication['applicant_name'] ?? '') === 'Updated'
        && ($syncedApplication['full_name'] ?? '') === 'Updated Applicant'
        && ($syncedApplication['applicant_email'] ?? '') === $email
        && ($syncedApplication['contact_num'] ?? '') === '+639171234567',
        'editing account identity synchronizes previously submitted applications'
    );

    $invalid = profile_crud_request(
        $baseUrl . '/save_profile_data.php',
        $sessionId,
        'POST',
        profile_education_payload([
            'education_status' => 'ongoing',
            'ed_ey' => '',
            'year_completed' => '',
            'completed_units' => '-1',
        ])
    );
    profile_crud_check(empty($invalid['success']) && str_contains((string)($invalid['message'] ?? ''), 'whole number'), 'negative units are rejected by the save endpoint');

    $missing = profile_crud_request(
        $baseUrl . '/save_profile_data.php',
        $sessionId,
        'POST',
        profile_education_payload([
            'education_status' => 'ongoing',
            'ed_ey' => '',
            'year_completed' => '',
            'completed_units' => '',
        ])
    );
    profile_crud_check(empty($missing['success']) && str_contains((string)($missing['message'] ?? ''), 'are required'), 'missing ongoing Master units are rejected by the save endpoint');

    $created = profile_crud_request(
        $baseUrl . '/save_profile_data.php',
        $sessionId,
        'POST',
        profile_education_payload()
    );
    $educationId = (int)($created['id'] ?? 0);
    profile_crud_check(!empty($created['success']) && (float)($created['qualification_projection']['applicable_hourly_rate'] ?? 0) === 200.0, 'adding a completed Master record returns 200/hour');

    // Existing document paths exercise the normal edit flow without writing
    // disposable upload fixtures to the project.
    $documents = $conn->prepare("UPDATE user_education SET certificate_of_grades = 'test-only-certificate.png', proof_of_enrollment = 'test-only-enrollment.png' WHERE id = ? AND user_id = ?");
    $documents->bind_param('ii', $educationId, $userId);
    $documents->execute();
    $documents->close();

    $ongoingHigh = profile_crud_request(
        $baseUrl . '/save_profile_data.php',
        $sessionId,
        'POST',
        profile_education_payload([
            'edit_id' => (string)$educationId,
            'education_status' => 'ongoing',
            'ed_sy' => '2025',
            'ed_ey' => '',
            'year_completed' => '',
            'completed_units' => '18',
        ])
    );
    profile_crud_check(!empty($ongoingHigh['success']) && (float)($ongoingHigh['qualification_projection']['applicable_hourly_rate'] ?? 0) === 170.0, 'editing the saved record to 18 ongoing units returns 170/hour');

    $ongoingLow = profile_crud_request(
        $baseUrl . '/save_profile_data.php',
        $sessionId,
        'POST',
        profile_education_payload([
            'edit_id' => (string)$educationId,
            'education_status' => 'ongoing',
            'ed_sy' => '2025',
            'ed_ey' => '',
            'year_completed' => '',
            'completed_units' => '9',
        ])
    );
    profile_crud_check(!empty($ongoingLow['success']) && (float)($ongoingLow['qualification_projection']['applicable_hourly_rate'] ?? 0) === 150.0, 'editing the saved record to 9 ongoing units returns 150/hour');

    $completed = profile_crud_request(
        $baseUrl . '/save_profile_data.php',
        $sessionId,
        'POST',
        profile_education_payload([
            'edit_id' => (string)$educationId,
            'completed_units' => '3',
        ])
    );
    profile_crud_check(!empty($completed['success']) && (float)($completed['qualification_projection']['applicable_hourly_rate'] ?? 0) === 200.0, 'changing the saved record to completed returns 200/hour regardless of units');

    $reopened = profile_crud_request(
        $baseUrl . '/save_profile_data.php',
        $sessionId,
        'POST',
        profile_education_payload([
            'edit_id' => (string)$educationId,
            'education_status' => 'ongoing',
            'ed_sy' => '2025',
            'ed_ey' => '',
            'year_completed' => '',
            'completed_units' => '19',
        ])
    );
    profile_crud_check(!empty($reopened['success']) && (float)($reopened['qualification_projection']['applicable_hourly_rate'] ?? 0) === 170.0, 'changing completed status back to ongoing restores the 18+ unit rate');

    $list = profile_crud_request($baseUrl . '/get_profile_lists.php?type=education', $sessionId);
    profile_crud_check(
        !empty($list['success'])
        && count($list['data'] ?? []) === 1
        && (float)($list['qualification_projection']['applicable_hourly_rate'] ?? 0) === 170.0,
        'page-reload endpoint returns saved education and synchronized projection'
    );

    $deleted = profile_crud_request(
        $baseUrl . '/save_profile_data.php',
        $sessionId,
        'DELETE',
        ['delete_education' => '1', 'id' => (string)$educationId]
    );
    profile_crud_check(!empty($deleted['success']) && ($deleted['qualification_projection']['applicable_hourly_rate'] ?? null) === null, 'deleting education immediately returns the recalculated empty projection');
    $educationId = 0;
} catch (Throwable $e) {
    $failed++;
    echo 'FAIL: CRUD integration exception: ' . $e->getMessage() . "\n";
} finally {
    if ($userId > 0) {
        if ($applicationId > 0) {
            $conn->query("DELETE FROM job_applicants WHERE id = {$applicationId} AND user_id = {$userId}");
        }
        if ($educationId > 0) {
            $conn->query("DELETE FROM user_education WHERE id = {$educationId} AND user_id = {$userId}");
        }
        $conn->query("DELETE FROM applicants WHERE id = {$userId}");
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    session_destroy();
}

echo "\nProfile education CRUD tests: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
