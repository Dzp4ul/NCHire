<?php
/**
 * Idempotent demo data for the AI Candidate Ranking interface.
 *
 * Run from the project root:
 * php database/seed_ai_candidate_ranking_demo.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/ranking/services/CandidateScoringService.php';
require_once __DIR__ . '/../api/ranking/services/CandidateRankingService.php';

function demoApplicantId(mysqli $conn, string $email): ?int
{
    $stmt = $conn->prepare('SELECT id FROM applicants WHERE applicant_email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $id = $stmt->get_result()->fetch_assoc()['id'] ?? null;
    $stmt->close();
    return $id === null ? null : (int)$id;
}

function createDemoApplicant(mysqli $conn, array $candidate): int
{
    $existingId = demoApplicantId($conn, $candidate['email']);
    if ($existingId !== null) return $existingId;

    // The random hash is intentionally not a usable demo login password.
    $disabledPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $verified = 0;
    $contact = '00000000000';
    $address = '[AI Candidate Ranking Demo Record]';
    $passwordChangeRequired = 'demo_disabled';
    $stmt = $conn->prepare('INSERT INTO applicants (first_name, last_name, applicant_email, applicant_password, is_verified, contact_number, address, password_change_required) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ssssisss', $candidate['first_name'], $candidate['last_name'], $candidate['email'], $disabledPassword, $verified, $contact, $address, $passwordChangeRequired);
    if (!$stmt->execute()) throw new RuntimeException('Unable to create demo applicant: ' . $stmt->error);
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}

function addDemoEducation(mysqli $conn, int $userId, array $row): void
{
    $stmt = $conn->prepare('INSERT INTO user_education (user_id, education_level, degree, field_of_study, institution, education_status, completed_units, year_completed, start_year, end_year, gpa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('isssssiiiis', $userId, $row['level'], $row['degree'], $row['field'], $row['institution'], $row['status'], $row['units'], $row['year_completed'], $row['start_year'], $row['end_year'], $row['gpa']);
    if (!$stmt->execute()) throw new RuntimeException('Unable to create demo education: ' . $stmt->error);
    $stmt->close();
}

function addDemoExperience(mysqli $conn, int $userId, array $row): void
{
    $stmt = $conn->prepare('INSERT INTO user_experience (user_id, experience_type, job_title, company, location, start_date, end_date, description, is_current) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('isssssssi', $userId, $row['type'], $row['title'], $row['company'], $row['location'], $row['start'], $row['end'], $row['description'], $row['current']);
    if (!$stmt->execute()) throw new RuntimeException('Unable to create demo experience: ' . $stmt->error);
    $stmt->close();
}

function addDemoSkill(mysqli $conn, int $userId, string $name, int $level): void
{
    $category = 'general';
    $stmt = $conn->prepare('INSERT INTO user_skills (user_id, skill_name, skill_category, skill_level) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('issi', $userId, $name, $category, $level);
    if (!$stmt->execute()) throw new RuntimeException('Unable to create demo skill: ' . $stmt->error);
    $stmt->close();
}

function addDemoQualification(mysqli $conn, int $userId, array $row): void
{
    $verification = 'unverified';
    $stmt = $conn->prepare('INSERT INTO user_qualifications (user_id, qualification_type, title, issuing_organization, issued_date, expiry_date, verification_status) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('issssss', $userId, $row['type'], $row['title'], $row['issuer'], $row['issued'], $row['expiry'], $verification);
    if (!$stmt->execute()) throw new RuntimeException('Unable to create demo qualification: ' . $stmt->error);
    $stmt->close();
}

function ensureDemoApplication(mysqli $conn, int $userId, int $jobId, array $candidate, string $jobTitle): int
{
    $academicYear = '2026-2027';
    $semester = 'First Semester';
    $applicationType = 'new';
    $find = $conn->prepare('SELECT id FROM job_applicants WHERE user_id = ? AND job_id = ? AND academic_year = ? AND semester = ? AND application_type = ? LIMIT 1');
    $find->bind_param('iisss', $userId, $jobId, $academicYear, $semester, $applicationType);
    $find->execute();
    $existing = $find->get_result()->fetch_assoc()['id'] ?? null;
    $find->close();
    if ($existing !== null) return (int)$existing;

    $fullName = trim($candidate['first_name'] . ' ' . $candidate['last_name']);
    $appliedDate = date('Y-m-d');
    $status = 'Under Department Review';
    $workflow = 'department_head_review';
    $contact = '00000000000';
    $address = '[AI Candidate Ranking Demo Record]';
    $department = 'Computing Studies';
    $letter = '';
    $stmt = $conn->prepare('INSERT INTO job_applicants (applicant_name, position, applied_date, status, workflow_stage, full_name, applicant_email, contact_num, address, user_id, job_id, application_type, academic_year, semester, assigned_to_department, letter_of_intent, transferred_to_dept_head_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->bind_param('sssssssssiisssss', $fullName, $jobTitle, $appliedDate, $status, $workflow, $fullName, $candidate['email'], $contact, $address, $userId, $jobId, $applicationType, $academicYear, $semester, $department, $letter);
    if (!$stmt->execute()) throw new RuntimeException('Unable to create demo application: ' . $stmt->error);
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}

$candidates = [
    [
        'first_name' => 'Demo Alexandra',
        'last_name' => 'Reyes',
        'email' => 'ai-ranking.alexandra@example.invalid',
        'education' => [
            ['level' => 'bachelor', 'degree' => 'Bachelor of Science', 'field' => 'Computer Science', 'institution' => 'Demo State University', 'status' => 'completed', 'units' => null, 'year_completed' => 2016, 'start_year' => 2012, 'end_year' => 2016, 'gpa' => '1.45'],
            ['level' => 'master', 'degree' => 'Master of Science', 'field' => 'Computer Science', 'institution' => 'Demo State University', 'status' => 'completed', 'units' => null, 'year_completed' => 2019, 'start_year' => 2017, 'end_year' => 2019, 'gpa' => '1.30'],
        ],
        'experience' => [
            ['type' => 'teaching', 'title' => 'Computer Programming Instructor', 'company' => 'Demo Technical College', 'location' => 'Bulacan', 'start' => '2018-06-01', 'end' => null, 'description' => 'Teaches computer programming, database management, and software development with classroom assessment and student mentoring.', 'current' => 1],
        ],
        'skills' => [['Programming', 5], ['Database Management', 5], ['Communication', 5]],
        'qualifications' => [
            ['type' => 'certification', 'title' => 'Teaching Certification', 'issuer' => 'Demo Education Institute', 'issued' => '2020-01-15', 'expiry' => null],
            ['type' => 'license', 'title' => 'Licensed Professional Teacher', 'issuer' => 'Demo Professional Board', 'issued' => '2020-03-20', 'expiry' => '2030-03-20'],
            ['type' => 'training', 'title' => 'Outcomes-Based Education Training', 'issuer' => 'Demo Faculty Development Center', 'issued' => '2025-05-10', 'expiry' => null],
        ],
    ],
    [
        'first_name' => 'Demo Benjamin',
        'last_name' => 'Cruz',
        'email' => 'ai-ranking.benjamin@example.invalid',
        'education' => [
            ['level' => 'bachelor', 'degree' => 'Bachelor of Science', 'field' => 'Information Technology', 'institution' => 'Demo City College', 'status' => 'completed', 'units' => null, 'year_completed' => 2020, 'start_year' => 2016, 'end_year' => 2020, 'gpa' => '1.75'],
            ['level' => 'master', 'degree' => 'Master in Information Technology', 'field' => 'Information Technology', 'institution' => 'Demo Graduate School', 'status' => 'ongoing', 'units' => 12, 'year_completed' => null, 'start_year' => 2024, 'end_year' => 0, 'gpa' => null],
        ],
        'experience' => [
            ['type' => 'industry', 'title' => 'Software Developer', 'company' => 'Demo Software Studio', 'location' => 'Bulacan', 'start' => '2020-07-01', 'end' => '2024-05-31', 'description' => 'Developed programming and database applications.', 'current' => 0],
            ['type' => 'teaching', 'title' => 'Part-Time Programming Trainer', 'company' => 'Demo Training Center', 'location' => 'Bulacan', 'start' => '2024-06-01', 'end' => null, 'description' => 'Conducts introductory programming workshops.', 'current' => 1],
        ],
        'skills' => [['Programming', 5], ['Database Management', 4]],
        'qualifications' => [
            ['type' => 'training', 'title' => 'Outcomes-Based Education Training', 'issuer' => 'Demo Faculty Development Center', 'issued' => '2025-07-12', 'expiry' => null],
        ],
    ],
    [
        'first_name' => 'Demo Camille',
        'last_name' => 'Santos',
        'email' => 'ai-ranking.camille@example.invalid',
        'education' => [
            ['level' => 'bachelor', 'degree' => 'Bachelor of Science', 'field' => 'Business Administration', 'institution' => 'Demo Business College', 'status' => 'completed', 'units' => null, 'year_completed' => 2022, 'start_year' => 2018, 'end_year' => 2022, 'gpa' => '1.90'],
        ],
        'experience' => [
            ['type' => 'industry', 'title' => 'Retail Associate', 'company' => 'Demo Retail Company', 'location' => 'Bulacan', 'start' => '2022-08-01', 'end' => '2024-08-01', 'description' => 'Handled retail operations and customer concerns.', 'current' => 0],
        ],
        'skills' => [],
        'qualifications' => [],
    ],
    [
        'first_name' => 'Demo Daniel',
        'last_name' => 'Mendoza',
        'email' => 'ai-ranking.daniel@example.invalid',
        'education' => [],
        'experience' => [],
        'skills' => [],
        'qualifications' => [],
    ],
];

$conn->begin_transaction();
try {
    $jobTitle = '[DEMO] Computer Programming Instructor';
    $jobResult = $conn->query("SELECT id FROM job WHERE subject_code='AI-DEMO-101' AND job_title='[DEMO] Computer Programming Instructor' LIMIT 1");
    $jobId = $jobResult && ($jobRow = $jobResult->fetch_assoc()) ? (int)$jobRow['id'] : 0;
    if ($jobId === 0) {
        $jobSql = "INSERT INTO job (
            job_title, department_role, job_type, locations, salary_range, application_deadline, status,
            subject, subject_code, subject_name, program, academic_year, semester, teaching_schedule,
            teaching_hours_per_week, load_units, required_instructors, salary_grade, job_description,
            minimum_education_level, required_degree_fields, graduate_requirement, minimum_graduate_units,
            minimum_experience_years, teaching_experience_requirement, required_skills,
            required_certifications, required_licenses, required_training, preferred_qualifications,
            education, experience, training, eligibility, competency, duties
        ) VALUES (
            '[DEMO] Computer Programming Instructor', 'Computing Studies', 'Part-time', 'Norzagaray College',
            'Demo only', '2099-12-31', 'Active', 'Computer Programming', 'AI-DEMO-101',
            'Computer Programming', 'BS Computer Science', '2026-2027', 'First Semester',
            'Monday and Wednesday, 9:00 AM-12:00 PM', 6.00, 3.00, 1, 'DEMO',
            'Demo teaching load used to preview transparent, job-specific candidate ranking.',
            'bachelor', 'Computer Science, Information Technology, Software Engineering', 'preferred', 18,
            3.00, 'required', 'Programming\nDatabase Management\nCommunication',
            'Teaching Certification', 'Licensed Professional Teacher', 'Outcomes-Based Education Training', '',
            'Bachelor degree in a computing-related field; graduate study preferred.',
            'At least three years of relevant experience; teaching experience required.',
            'Outcomes-Based Education Training', 'Teaching certification and professional teaching license.',
            'Programming, database management, and communication.',
            'Teach programming subjects and assess student learning.'
        )";
        if (!$conn->query($jobSql)) throw new RuntimeException('Unable to create demo job: ' . $conn->error);
        $jobId = (int)$conn->insert_id;
    }

    foreach ($candidates as $candidate) {
        $existingApplicant = demoApplicantId($conn, $candidate['email']);
        $userId = createDemoApplicant($conn, $candidate);
        if ($existingApplicant === null) {
            foreach ($candidate['education'] as $education) addDemoEducation($conn, $userId, $education);
            foreach ($candidate['experience'] as $experience) addDemoExperience($conn, $userId, $experience);
            foreach ($candidate['skills'] as [$name, $level]) addDemoSkill($conn, $userId, $name, $level);
            foreach ($candidate['qualifications'] as $qualification) addDemoQualification($conn, $userId, $qualification);
        }
        ensureDemoApplication($conn, $userId, $jobId, $candidate, $jobTitle);
    }

    $secretary = $conn->query("SELECT id, department FROM admin_users WHERE role='Secretary' AND status='Active' ORDER BY id LIMIT 1")->fetch_assoc();
    $reviewer = [
        'id' => (int)($secretary['id'] ?? 0),
        'role' => 'Secretary',
        'department' => (string)($secretary['department'] ?? 'General'),
    ];
    $rankings = (new CandidateRankingService($conn, new CandidateScoringService()))->listForJob($jobId, $reviewer, true);
    $conn->commit();

    echo "AI Candidate Ranking demo seed completed.\n";
    echo "Demo job ID: {$jobId}\n";
    foreach ($rankings['rankings'] as $ranking) {
        $score = $ranking['overall_score'] === null ? 'N/A' : number_format((float)$ranking['overall_score'], 2) . '%';
        echo $ranking['rank'] . '. ' . $ranking['applicant_name'] . ' - ' . $score . ' - ' . $ranking['qualification_label'] . "\n";
    }
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, 'Demo seed failed: ' . $e->getMessage() . "\n");
    exit(1);
}

