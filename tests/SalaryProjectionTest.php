<?php

require_once __DIR__ . '/../shared/helpers/recruitment.php';

$passed = 0;
$failed = 0;

function salary_check(bool $condition, string $message): void
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

function salary_education(string $level, string $status, ?int $units = null): array
{
    return [[
        'education_level' => $level,
        'education_status' => $status,
        'completed_units' => $units,
        'degree' => ucfirst($level),
    ]];
}

$permanent = nc_calculate_salary_projection_from_education(
    salary_education('master', 'ongoing', 12),
    ['job_type' => 'Permanent', 'job_title' => 'Instructor I', 'salary_grade' => 'SG 15', 'salary_range' => '45,000', 'teaching_hours_per_week' => 6]
);
salary_check($permanent['calculation_type'] === 'salary_grade', 'permanent faculty use Salary Grade calculation');
salary_check($permanent['projected_salary'] === null && $permanent['applicable_hourly_rate'] === null, 'permanent faculty never use part-time hourly rules or expose an amount');
salary_check($permanent['salary_display'] === 'SG13', 'NC Instructor I projection uses SG13');
salary_check(strpos($permanent['salary_display'], '45,000') === false, 'permanent compensation hides the underlying salary amount');

$otherPermanent = nc_calculate_salary_projection_from_education(
    salary_education('doctorate', 'completed'),
    ['job_type' => 'Full-time', 'job_title' => 'Assistant Professor I', 'salary_grade' => 'SG 16', 'salary_range' => '50,000']
);
salary_check($otherPermanent['salary_display'] === 'SG16', 'non-Instructor-I permanent jobs show only their assigned Salary Grade');

$masterCompleted = nc_calculate_salary_projection_from_education(
    salary_education('master', 'completed'),
    ['job_type' => 'Part-time']
);
salary_check($masterCompleted['applicable_hourly_rate'] === 200.0, "completed Master's receives 200/hour");
salary_check($masterCompleted['salary_display'] === '₱200/hour', 'part-time job without hours displays only its hourly rate');

$masterOngoing = nc_calculate_salary_projection_from_education(
    salary_education('master', 'ongoing', 3),
    ['job_type' => 'Part-time']
);
salary_check($masterOngoing['applicable_hourly_rate'] === 150.0, "ongoing Master's receives 150/hour without a minimum-unit threshold");
salary_check(strpos($masterOngoing['qualification'], '3 completed units') !== false, 'ongoing completed units remain visible in the qualification');

$masterUpdated = nc_calculate_salary_projection_from_education(
    salary_education('master', 'completed', 3),
    ['job_type' => 'Part-time']
);
salary_check($masterOngoing['applicable_hourly_rate'] === 150.0 && $masterUpdated['applicable_hourly_rate'] === 200.0, 'changing current profile status updates future projections');

$doctorate = nc_calculate_salary_projection_from_education(
    array_merge(salary_education('master', 'completed'), salary_education('doctorate', 'completed')),
    ['job_type' => 'Part-time', 'teaching_hours_per_week' => 6]
);
salary_check($doctorate['applicable_hourly_rate'] === 220.0, 'completed Doctorate takes priority over completed Master\'s');
salary_check($doctorate['projected_salary'] === 1320.0, 'hourly rate is multiplied by configured weekly teaching hours');
salary_check($doctorate['salary_display'] === '₱220/hour' && $doctorate['projected_salary_display'] === '₱1,320/week', 'dashboard stays hourly while detail data retains the weekly projection');

$masterOverDoctorateOngoing = nc_calculate_salary_projection_from_education(
    array_merge(salary_education('doctorate', 'ongoing', 9), salary_education('master', 'completed')),
    ['job_type' => 'Part-time']
);
salary_check($masterOverDoctorateOngoing['applicable_hourly_rate'] === 200.0, "completed Master's applies when Doctorate is only ongoing");

$doctorateOngoingOnly = nc_calculate_salary_projection_from_education(
    salary_education('doctorate', 'ongoing', 9),
    ['job_type' => 'Part-time']
);
salary_check($doctorateOngoingOnly['applicable_hourly_rate'] === null && $doctorateOngoingOnly['salary_display'] === 'Rate to be determined', 'ongoing Doctorate alone safely returns rate to be determined');

$missingStatus = nc_calculate_salary_projection_from_education(
    [['education_level' => 'master', 'degree' => 'Master of Science']],
    ['job_type' => 'Part-time']
);
salary_check($missingStatus['applicable_hourly_rate'] === null, 'missing education status does not assume a rate');

$unknownEmployment = nc_calculate_salary_projection_from_education(
    salary_education('doctorate', 'completed'),
    ['job_type' => 'Contractual']
);
salary_check($unknownEmployment['applicable_hourly_rate'] === null, 'unknown employment type does not receive a guessed part-time rate');

$legacyFullTime = nc_calculate_salary_projection_from_education(
    [],
    ['job_type' => 'Full-time', 'job_title' => 'Instructor', 'salary_range' => '20,000 - 25,000']
);
salary_check($legacyFullTime['salary_display'] === 'SG13', 'legacy generic Instructor postings resolve to NC Instructor I SG13');
salary_check(strpos($legacyFullTime['salary_display'], '20,000') === false, 'legacy full-time salary ranges are no longer exposed');

$changedConfig = nc_calculate_salary_projection_from_education(
    salary_education('master', 'completed'),
    ['job_type' => 'Part-time'],
    ['part_time_rates' => ['master_completed' => ['hourly_rate' => 275, 'label' => "Master's - Completed"]]]
);
salary_check($changedConfig['applicable_hourly_rate'] === 275.0, 'central configuration changes apply without frontend changes');
salary_check(!empty($changedConfig['disclaimer']), 'every projection includes the guide-only disclaimer');

echo "\nSalary projection tests: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
