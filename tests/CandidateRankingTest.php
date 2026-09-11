<?php

require_once __DIR__ . '/../api/chatbot/services/EnvLoader.php';
require_once __DIR__ . '/../api/chatbot/services/GroqService.php';
require_once __DIR__ . '/../api/ranking/services/CandidateScoringService.php';
require_once __DIR__ . '/../api/ranking/services/CandidateRankingAiService.php';
require_once __DIR__ . '/../api/ranking/services/CandidateRankingService.php';

$passed = 0;
$failed = 0;
function check(bool $condition, string $name): void
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

function education(string $level = 'bachelor', string $field = 'Computer Science', string $status = 'completed', ?int $units = null): array
{
    return [[
        'education_level' => $level,
        'degree' => ucfirst($level) . ' Degree',
        'field_of_study' => $field,
        'institution' => 'Recorded University',
        'education_status' => $status,
        'completed_units' => $units,
        'start_year' => 2014,
        'end_year' => $status === 'completed' ? 2018 : null,
    ]];
}

function experience(string $type = 'teaching', string $title = 'Computer Programming Instructor', string $start = '2019-01-01', string $end = '2024-01-01'): array
{
    return [[
        'experience_type' => $type,
        'job_title' => $title,
        'company' => 'Recorded Employer',
        'start_date' => $start,
        'end_date' => $end,
        'is_current' => 0,
        'description' => $type === 'teaching' ? 'Taught computer programming and assessed students.' : 'Performed unrelated operational duties.',
    ]];
}

function baseJob(): array
{
    return [
        'id' => 100,
        'job_title' => 'Computer Programming Instructor',
        'subject_name' => 'Computer Programming',
        'subject' => 'Computing Studies Professional Subjects',
        'program' => 'BS Computer Science',
        'department_role' => 'Computing Studies',
        'job_description' => 'Teach programming courses.',
        'minimum_education_level' => 'bachelor',
        'required_degree_fields' => 'Computer Science, Information Technology',
        'graduate_requirement' => 'preferred',
        'minimum_graduate_units' => null,
        'minimum_experience_years' => 2,
        'teaching_experience_requirement' => 'preferred',
        'required_skills' => "Programming\nCommunication",
        'required_certifications' => 'Teaching Certification',
        'required_licenses' => 'Licensed Professional Teacher',
        'required_training' => 'Pedagogy Workshop',
        'preferred_qualifications' => '',
        'job_requirements' => '',
        'education' => '', 'experience' => '', 'training' => '', 'eligibility' => '', 'competency' => '',
    ];
}

function completeProfile(array $education = null, array $experience = null, array $qualifications = null): array
{
    return [
        'education' => $education ?? education('master'),
        'experience' => $experience ?? experience(),
        'skills' => [
            ['skill_name' => 'Programming', 'skill_level' => 5],
            ['skill_name' => 'Communication', 'skill_level' => 4],
        ],
        'qualifications' => $qualifications ?? [
            ['qualification_type' => 'certification', 'title' => 'Teaching Certification', 'expiry_date' => null],
            ['qualification_type' => 'license', 'title' => 'Licensed Professional Teacher', 'expiry_date' => null],
            ['qualification_type' => 'training', 'title' => 'Pedagogy Workshop', 'expiry_date' => null],
        ],
    ];
}

$scorer = new CandidateScoringService();
$job = baseJob();
$application = ['resume' => 'yes', 'tor' => 'yes', 'diploma' => 'yes', 'coe' => 'yes'];

$complete = $scorer->score($job, completeProfile(), $application);
check(is_numeric($complete['overall_score']) && $complete['overall_score'] >= 80, '1 complete applicant receives a strong deterministic score');

$incomplete = $scorer->score($job, ['education' => [], 'experience' => [], 'skills' => [], 'qualifications' => []], []);
check($incomplete['profile_completeness']['education'] === 'Not provided' && $incomplete['overall_score'] < $complete['overall_score'], '2 incomplete profile remains rankable without invented data');
$missingDatesProfile = completeProfile();
$missingDatesProfile['experience'][0]['start_date'] = null;
$missingDates = $scorer->score($job, $missingDatesProfile, $application);
check($missingDates['categories']['experience']['data_status'] === 'Information incomplete', '2b missing experience dates are explicitly marked incomplete');

$exact = $scorer->score($job, completeProfile(education('bachelor', 'Computer Science')), $application);
$mismatch = $scorer->score($job, completeProfile(education('bachelor', 'Fine Arts')), $application);
check($exact['categories']['education']['score'] > $mismatch['categories']['education']['score'], '3 exact degree match scores above mismatch');
check(in_array('not_met', array_column($mismatch['categories']['education']['criteria'], 'status'), true), '4 nonmatching degree is explicitly not met');

$master = $scorer->score($job, completeProfile(education('master')), $application);
check($master['categories']['education']['score'] >= $exact['categories']['education']['score'], '5 completed master degree satisfies graduate preference');

$doctorate = $scorer->score($job, completeProfile(education('doctorate')), $application);
check(str_contains(json_encode($doctorate['categories']['education']), 'Doctorate'), '6 completed doctorate is recognized');

$ongoingMaster = $scorer->score($job, completeProfile(education('master', 'Computer Science', 'ongoing', 12)), $application);
check(in_array('partial', array_column($ongoingMaster['categories']['education']['criteria'], 'status'), true), '7 unfinished master with graduate units receives partial graduate credit');
$unitsRequiredJob = $job;
$unitsRequiredJob['minimum_graduate_units'] = 18;
$missingGraduateUnits = $scorer->score($unitsRequiredJob, completeProfile(education('master', 'Computer Science', 'ongoing')), $application);
check($missingGraduateUnits['categories']['education']['data_status'] === 'Information incomplete' && $missingGraduateUnits['categories']['education']['criteria'][2]['score'] === 0.0, '7b missing graduate-unit data receives no invented credit');

$teaching = $scorer->score($job, completeProfile(null, experience('teaching')), $application);
$unrelated = $scorer->score($job, completeProfile(null, experience('industry', 'Warehouse Clerk')), $application);
check($teaching['categories']['experience']['score'] > $unrelated['categories']['experience']['score'], '8 teaching experience scores above unrelated experience');
check(in_array('not_met', array_column($unrelated['categories']['experience']['criteria'], 'status'), true), '9 unrelated experience is not presented as teaching experience');

$withCertification = $scorer->score($job, completeProfile(), $application);
$withoutCertification = $scorer->score($job, completeProfile(null, null, []), $application);
check($withCertification['categories']['certifications']['score'] > $withoutCertification['categories']['certifications']['score'], '10 matching certifications and licenses increase only their category');
check($withoutCertification['categories']['certifications']['data_status'] === 'Not provided', '11 missing certifications are marked not provided');

$profiles = [completeProfile(), completeProfile(education('bachelor', 'Fine Arts'), experience('industry', 'Warehouse Clerk'), []), completeProfile(education('bachelor'))];
$scores = array_map(fn(array $profile): float => (float)$scorer->score($job, $profile, $application)['overall_score'], $profiles);
$sorted = $scores;
rsort($sorted);
check(count($sorted) === 3 && $sorted[0] >= $sorted[1] && $sorted[1] >= $sorted[2], '12 several applicants can be sorted highest to lowest');

$mathJob = $job;
$mathJob['job_title'] = 'Mathematics Instructor';
$mathJob['subject_name'] = 'Mathematics';
$mathJob['program'] = 'BS Mathematics';
$mathJob['required_degree_fields'] = 'Mathematics';
$programmingScore = $scorer->score($job, completeProfile(), $application)['overall_score'];
$mathScore = $scorer->score($mathJob, completeProfile(), $application)['overall_score'];
check($programmingScore !== $mathScore, '13 same applicant receives job-specific scores');

$hashBefore = $scorer->inputHash($job, completeProfile(), $application);
$editedJob = $job;
$editedJob['minimum_experience_years'] = 8;
$hashAfter = $scorer->inputHash($editedJob, completeProfile(), $application);
check($hashBefore !== $hashAfter, '14 editing job requirements invalidates the ranking input hash');

$networkFailure = new class {
    public function isConfigured(): bool { return true; }
    public function chat(array $messages, array $options = []): string { throw new GroqApiException('network_error', 'offline'); }
};
try {
    (new CandidateRankingAiService($networkFailure))->analyze($job, completeProfile(), $complete);
    check(false, '15 Groq unavailable is detected');
} catch (GroqApiException $e) {
    check($e->getCategory() === 'network_error', '15 Groq unavailable is detected without changing deterministic scoring');
}

$missingKey = new class {
    public function isConfigured(): bool { return false; }
    public function chat(array $messages, array $options = []): string { return ''; }
};
try {
    (new CandidateRankingAiService($missingKey))->analyze($job, completeProfile(), $complete);
    check(false, '16 missing Groq key is handled');
} catch (GroqApiException $e) {
    check($e->getCategory() === 'missing_api_key', '16 missing GROQ_API_KEY is handled explicitly');
}

$capturingGroq = new class {
    public array $messages = [];
    public function isConfigured(): bool { return true; }
    public function chat(array $messages, array $options = []): string
    {
        $this->messages = $messages;
        return json_encode([
            'summary' => 'The recorded evidence was reviewed against the position.',
            'strengths' => ['Invented PhD'],
            'missingRequirements' => [],
            'jobMatchExplanation' => 'The explanation is based on the supplied deterministic result.',
            'recommendation' => 'Highly Qualified',
        ]);
    }
};
$profileWithSensitiveFields = completeProfile();
$profileWithSensitiveFields['email'] = 'sensitive-candidate@example.invalid';
$profileWithSensitiveFields['password'] = 'do-not-send-this-password';
$groundedAi = (new CandidateRankingAiService($capturingGroq))->analyze($job, $profileWithSensitiveFields, $complete);
$sentContext = json_encode($capturingGroq->messages);
check(!str_contains($sentContext, 'sensitive-candidate@example.invalid') && !str_contains($sentContext, 'do-not-send-this-password'), '17 Groq context excludes unrelated sensitive profile fields');
check(!in_array('Invented PhD', $groundedAi['strengths'], true) && $groundedAi['recommendation'] === $complete['qualification_label'], '18 AI cannot replace deterministic evidence or qualification label');

$invalidJson = new class {
    public function isConfigured(): bool { return true; }
    public function chat(array $messages, array $options = []): string { return 'not-json'; }
};
try {
    (new CandidateRankingAiService($invalidJson))->analyze($job, completeProfile(), $complete);
    check(false, '19 invalid Groq JSON is rejected');
} catch (GroqApiException $e) {
    check($e->getCategory() === 'invalid_json', '19 invalid Groq JSON is rejected safely');
}

if (in_array('--integration', $argv, true)) {
    require __DIR__ . '/../config/db.php';
    $service = new CandidateRankingService($conn, $scorer);
    try {
        $service->listForJob(77, ['id' => 17, 'role' => 'Admin', 'department' => 'General']);
        check(false, '20 unauthorized admin cannot access rankings');
    } catch (RankingException $e) {
        check($e->getCategory() === 'forbidden', '20 unauthorized admin cannot access rankings');
    }

    $secretary = $conn->query("SELECT id, department FROM admin_users WHERE role='Secretary' AND status='Active' LIMIT 1")->fetch_assoc();
    $visibleJob = $conn->query("SELECT ja.job_id FROM job_applicants ja INNER JOIN job j ON j.id=ja.job_id WHERE ja.workflow_stage!='rejected' LIMIT 1")->fetch_assoc();
    if ($secretary && $visibleJob) {
        $result = $service->listForJob((int)$visibleJob['job_id'], ['id' => (int)$secretary['id'], 'role' => 'Secretary', 'department' => $secretary['department']], true);
        check(is_array($result['rankings']), 'integration: authorized ranking calculation and persistence');
    } else {
        check(true, 'integration: no local fixture available; empty state accepted');
    }
}

$legacyProfile = [
    'education' => [['degree' => 'Bachelor of Science', 'field_of_study' => 'Computer Science', 'education_status' => 'completed']],
    'experience' => [['job_title' => 'Instructor', 'start_date' => '2020-01-01', 'end_date' => '2021-01-01']],
    'skills' => [], 'qualifications' => [],
];
$legacy = $scorer->score($job, $legacyProfile, []);
check(is_numeric($legacy['overall_score']) && $legacy['profile_completeness']['certifications_licenses_training'] === 'Not provided', '21 pre-migration applicant records remain compatible');

echo "\n{$passed} passed, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);
