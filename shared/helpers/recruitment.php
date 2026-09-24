<?php
/**
 * Shared recruitment helpers for NCHire teaching-load revisions.
 */

if (!function_exists('nc_column_exists')) {
    function nc_column_exists(mysqli $conn, string $table, string $column): bool
    {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS count
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("ss", $table, $column);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $exists = (int)($row["count"] ?? 0) > 0;
        $stmt->close();
        return $exists;
    }
}

if (!function_exists('nc_table_exists')) {
    function nc_table_exists(mysqli $conn, string $table): bool
    {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS count
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("s", $table);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $exists = (int)($row["count"] ?? 0) > 0;
        $stmt->close();
        return $exists;
    }
}

if (!function_exists('nc_current_academic_year')) {
    function nc_current_academic_year(): string
    {
        $year = (int)date('Y');
        $month = (int)date('n');
        $start = $month >= 6 ? $year : $year - 1;
        return $start . '-' . ($start + 1);
    }
}

if (!function_exists('nc_current_semester')) {
    function nc_current_semester(): string
    {
        $month = (int)date('n');
        if ($month >= 1 && $month <= 5) {
            return 'Second Semester';
        }
        return 'First Semester';
    }
}

if (!function_exists('nc_normalize_semester')) {
    function nc_normalize_semester(?string $semester): string
    {
        $value = strtolower(trim((string)$semester));
        if (in_array($value, ['first', '1st', 'first semester'], true)) {
            return 'First Semester';
        }
        if (in_array($value, ['second', '2nd', 'second semester'], true)) {
            return 'Second Semester';
        }
        if ($value === 'summer') {
            return 'Summer';
        }
        return $semester ?: nc_current_semester();
    }
}

if (!function_exists('nc_format_teaching_load_title')) {
    function nc_format_teaching_load_title(array $job): string
    {
        $subjectCode = trim((string)($job['subject_code'] ?? ''));
        $subjectName = trim((string)($job['subject_name'] ?? ''));
        if ($subjectName === '') {
            $subjectName = trim((string)($job['subject'] ?? ''));
        }
        if ($subjectName === '') {
            $subjectName = trim((string)($job['job_title'] ?? 'Teaching Load'));
        }

        $base = $subjectCode !== '' ? ($subjectCode . ' - ' . $subjectName) : $subjectName;
        $title = trim((string)($job['job_title'] ?? 'Instructor'));
        if (stripos($base, 'instructor') === false && stripos($title, 'instructor') !== false) {
            $base .= ' Instructor';
        }
        return trim($base);
    }
}

if (!function_exists('nc_format_academic_period')) {
    function nc_format_academic_period(array $job): string
    {
        $academicYear = trim((string)($job['academic_year'] ?? '')) ?: nc_current_academic_year();
        $semester = nc_normalize_semester($job['semester'] ?? '');
        return "Academic Year {$academicYear}, {$semester}";
    }
}

if (!function_exists('nc_get_assigned_instructor_count')) {
    function nc_get_assigned_instructor_count(mysqli $conn, int $jobId): int
    {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS assigned_count
            FROM job_applicants
            WHERE job_id = ?
              AND (
                    status IN ('Passed', 'Application Passed', 'Hired', 'Permanently Hired')
                    OR workflow_stage IN ('passed', 'hired', 'permanently_hired')
                  )
        ");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("i", $jobId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return max(0, (int)($row['assigned_count'] ?? 0));
    }
}

if (!function_exists('nc_remaining_vacancies')) {
    function nc_remaining_vacancies(mysqli $conn, array $job): int
    {
        $required = max(1, (int)($job['required_instructors'] ?? 1));
        $assigned = array_key_exists('assigned_instructors', $job)
            ? (int)$job['assigned_instructors']
            : nc_get_assigned_instructor_count($conn, (int)$job['id']);
        return max(0, $required - $assigned);
    }
}

if (!function_exists('nc_classify_education_level')) {
    function nc_classify_education_level(array $education): string
    {
        $explicit = strtolower(trim((string)($education['education_level'] ?? '')));
        if (in_array($explicit, ['high_school', 'associate', 'bachelor', 'master', 'doctorate', 'other'], true)) {
            return $explicit;
        }

        $degree = strtolower((string)($education['degree'] ?? ''));
        if (strpos($degree, 'doctor') !== false || strpos($degree, 'ph.d') !== false || strpos($degree, 'phd') !== false) {
            return 'doctorate';
        }
        if (strpos($degree, 'master') !== false || strpos($degree, 'masteral') !== false) {
            return 'master';
        }
        if (strpos($degree, 'bachelor') !== false || strpos($degree, 'baccalaureate') !== false) {
            return 'bachelor';
        }
        if (strpos($degree, 'associate') !== false) {
            return 'associate';
        }
        if (strpos($degree, 'high school') !== false || strpos($degree, 'secondary') !== false) {
            return 'high_school';
        }
        return 'other';
    }
}

if (!function_exists('nc_get_education_rows')) {
    function nc_get_education_rows(mysqli $conn, int $userId): array
    {
        $rows = [];
        $stmt = $conn->prepare("SELECT * FROM user_education WHERE user_id = ? ORDER BY end_year DESC, start_year DESC");
        if (!$stmt) {
            return $rows;
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['education_level'] = nc_classify_education_level($row);
            $row['education_status'] = strtolower(trim((string)($row['education_status'] ?? 'completed'))) ?: 'completed';
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('nc_get_master_status')) {
    function nc_get_master_status(mysqli $conn, int $userId): array
    {
        $educationRows = nc_get_education_rows($conn, $userId);
        $result = [
            'has_master' => false,
            'status' => null,
            'completed_units' => null,
            'row' => null,
            'requires_ongoing_documents' => false,
        ];

        foreach ($educationRows as $row) {
            if (($row['education_level'] ?? '') !== 'master') {
                continue;
            }

            $status = strtolower(trim((string)($row['education_status'] ?? 'completed')));
            $units = isset($row['completed_units']) && $row['completed_units'] !== null ? (int)$row['completed_units'] : null;
            $result = [
                'has_master' => true,
                'status' => $status ?: 'completed',
                'completed_units' => $units,
                'row' => $row,
                'requires_ongoing_documents' => $status === 'ongoing',
            ];

            if ($status === 'ongoing') {
                return $result;
            }
        }

        return $result;
    }
}

if (!function_exists('nc_check_full_time_education_eligibility')) {
    function nc_check_full_time_education_eligibility(array $educationRows): array
    {
        $hasCompletedMasters = false;
        $hasCompletedDoctorate = false;
        $hasOngoingMasters = false;

        foreach ($educationRows as $education) {
            $level = nc_classify_education_level($education);
            $status = strtolower(trim((string)($education['education_status'] ?? 'completed')));

            if (in_array($status, ['completed', 'graduated'], true)) {
                $hasCompletedMasters = $hasCompletedMasters || $level === 'master';
                $hasCompletedDoctorate = $hasCompletedDoctorate || $level === 'doctorate';
            }

            if ($level === 'master' && in_array($status, ['ongoing', 'currently_pursuing', 'in_progress', 'not_yet_completed'], true)) {
                $hasOngoingMasters = true;
            }
        }

        return [
            'eligible' => $hasCompletedMasters || $hasCompletedDoctorate,
            'has_completed_masters' => $hasCompletedMasters,
            'has_completed_doctorate' => $hasCompletedDoctorate,
            'has_ongoing_masters' => $hasOngoingMasters,
        ];
    }
}

if (!function_exists('nc_salary_configuration')) {
    function nc_salary_configuration(?array $overrides = null): array
    {
        static $configuration = null;
        if ($configuration === null) {
            $path = __DIR__ . '/../../config/salary.php';
            $loaded = is_file($path) ? require $path : [];
            $configuration = is_array($loaded) ? $loaded : [];
        }

        return $overrides === null
            ? $configuration
            : array_replace_recursive($configuration, $overrides);
    }
}

if (!function_exists('nc_normalize_employment_type')) {
    function nc_normalize_employment_type(?string $employmentType): string
    {
        $value = strtolower(trim((string)$employmentType));
        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?: '';

        if (strpos($value, 'part time') !== false || strpos($value, 'parttime') !== false) {
            return 'part_time';
        }
        if (strpos($value, 'full time') !== false || strpos($value, 'fulltime') !== false
            || strpos($value, 'permanent') !== false || strpos($value, 'regular') !== false) {
            return 'full_time';
        }
        return 'unknown';
    }
}

if (!function_exists('nc_normalize_salary_grade')) {
    function nc_normalize_salary_grade(?string $salaryGrade): string
    {
        $value = strtoupper(trim((string)$salaryGrade));
        if ($value === '') {
            return '';
        }
        if (preg_match('/(?:SG\s*[- ]?\s*)?(\d+)/i', $value, $matches)) {
            return 'SG' . (int)$matches[1];
        }
        return preg_replace('/\s+/', '-', $value) ?: $value;
    }
}

if (!function_exists('nc_resolve_job_salary_grade')) {
    function nc_resolve_job_salary_grade(array $job, array $configuration): string
    {
        $title = strtolower(trim(implode(' ', array_filter([
            $job['job_title'] ?? null,
            $job['position'] ?? null,
            $job['teaching_load_title'] ?? null,
        ]))));
        $explicitGrade = nc_normalize_salary_grade($job['salary_grade'] ?? '');

        // NCHire's generic Instructor position represents Instructor I unless
        // a higher Instructor rank is explicitly present in the position name.
        $hasHigherInstructorRank = preg_match('/\binstructor\s+(?:ii|iii|iv|v|vi|[2-6])\b/i', $title) === 1;
        $isInstructorOne = !$hasHigherInstructorRank && preg_match('/\binstructor(?:\s+(?:i|1))?\b/i', $title) === 1;
        if ($isInstructorOne) {
            return nc_normalize_salary_grade($configuration['full_time_salary_grades']['instructor_i'] ?? 'SG13');
        }

        return $explicitGrade;
    }
}

if (!function_exists('nc_format_money')) {
    function nc_format_money(float $amount, string $currencySymbol = '₱'): string
    {
        $decimals = abs($amount - round($amount)) < 0.00001 ? 0 : 2;
        return $currencySymbol . number_format($amount, $decimals);
    }
}

if (!function_exists('nc_calculate_salary_projection_from_education')) {
    function nc_calculate_salary_projection_from_education(array $educationRows, array $job, ?array $configuration = null): array
    {
        $configuration = nc_salary_configuration($configuration);
        $currencySymbol = (string)($configuration['currency_symbol'] ?? '₱');
        $disclaimer = trim((string)($configuration['projection_disclaimer'] ?? 'Guide only—not the actual salary. Final compensation may vary based on the applicant profile.'));
        $employmentCategory = nc_normalize_employment_type($job['job_type'] ?? $job['employment_type'] ?? '');
        $employmentType = trim((string)($job['job_type'] ?? $job['employment_type'] ?? ''));
        $salaryGrade = nc_normalize_salary_grade($job['salary_grade'] ?? '');
        $hours = isset($job['teaching_hours_per_week']) && is_numeric($job['teaching_hours_per_week'])
            && (float)$job['teaching_hours_per_week'] > 0
            ? (float)$job['teaching_hours_per_week']
            : null;

        $result = [
            'employment_type' => $employmentType ?: 'Not specified',
            'employment_category' => $employmentCategory,
            'calculation_type' => 'undetermined',
            'salary_grade' => $salaryGrade ?: null,
            'qualification_key' => null,
            'qualification' => 'No applicable graduate qualification recorded',
            'master_status' => null,
            'completed_master_units' => null,
            'applicable_hourly_rate' => null,
            'teaching_hours_per_week' => $hours,
            'projected_salary' => null,
            'rate_display' => 'Rate to be determined',
            'projected_salary_display' => 'Rate to be determined',
            'salary_display' => 'Rate to be determined',
            'projection_basis' => 'Rate to be determined: employment type or salary information is incomplete.',
            'disclaimer' => $disclaimer,
        ];

        if ($employmentCategory === 'full_time') {
            $result['calculation_type'] = 'salary_grade';
            $salaryGrade = nc_resolve_job_salary_grade($job, $configuration);
            $result['salary_grade'] = $salaryGrade ?: null;
            $result['salary_display'] = $salaryGrade !== '' ? $salaryGrade : 'Salary Grade to be determined';
            $result['rate_display'] = $result['salary_display'];
            $result['projected_salary_display'] = $result['salary_display'];
            $result['projection_basis'] = $salaryGrade !== ''
                ? 'Permanent/Full-Time compensation follows the ' . $salaryGrade . ' classification; the actual salary amount is not displayed.'
                : 'Salary Grade to be determined: no classification is assigned.';
            return $result;
        }

        if ($employmentCategory !== 'part_time') {
            return $result;
        }

        $result['calculation_type'] = 'hourly';
        $hasCompletedDoctorate = false;
        $hasCompletedMaster = false;
        $hasOngoingMaster = false;
        $ongoingMasterUnits = null;

        foreach ($educationRows as $row) {
            $level = $row['education_level'] ?? nc_classify_education_level($row);
            $status = strtolower(trim((string)($row['education_status'] ?? '')));
            $units = isset($row['completed_units']) && $row['completed_units'] !== null && $row['completed_units'] !== ''
                ? (int)$row['completed_units']
                : null;

            if ($level === 'doctorate' && $status === 'completed') {
                $hasCompletedDoctorate = true;
            } elseif ($level === 'master' && $status === 'completed') {
                $hasCompletedMaster = true;
            } elseif ($level === 'master' && $status === 'ongoing') {
                $hasOngoingMaster = true;
                if ($units !== null && ($ongoingMasterUnits === null || $units > $ongoingMasterUnits)) {
                    $ongoingMasterUnits = $units;
                }
            }
        }

        $qualificationKey = $hasCompletedDoctorate
            ? 'doctorate_completed'
            : ($hasCompletedMaster ? 'master_completed' : ($hasOngoingMaster ? 'master_ongoing' : null));
        if ($qualificationKey === null) {
            $result['projection_basis'] = 'Rate to be determined: no applicable completed Doctorate, completed Master\'s, or ongoing Master\'s is recorded.';
            return $result;
        }

        $rateConfiguration = $configuration['part_time_rates'][$qualificationKey] ?? null;
        $rate = is_array($rateConfiguration) ? ($rateConfiguration['hourly_rate'] ?? null) : $rateConfiguration;
        if (!is_numeric($rate) || (float)$rate <= 0) {
            $result['qualification_key'] = $qualificationKey;
            $result['qualification'] = is_array($rateConfiguration)
                ? (string)($rateConfiguration['label'] ?? ucwords(str_replace('_', ' ', $qualificationKey)))
                : ucwords(str_replace('_', ' ', $qualificationKey));
            $result['projection_basis'] = 'Rate to be determined: the applicable qualification rate is not configured.';
            return $result;
        }

        $rate = round((float)$rate, 2);
        $qualification = is_array($rateConfiguration)
            ? (string)($rateConfiguration['label'] ?? ucwords(str_replace('_', ' ', $qualificationKey)))
            : ucwords(str_replace('_', ' ', $qualificationKey));
        if ($qualificationKey === 'master_ongoing' && $ongoingMasterUnits !== null) {
            $qualification .= ' (' . $ongoingMasterUnits . ' completed units)';
        }

        $rateDisplay = nc_format_money($rate, $currencySymbol) . '/hour';
        $result['qualification_key'] = $qualificationKey;
        $result['qualification'] = $qualification;
        $result['master_status'] = $qualificationKey === 'master_ongoing'
            ? 'Ongoing'
            : ($qualificationKey === 'master_completed' ? 'Completed' : null);
        $result['completed_master_units'] = $ongoingMasterUnits;
        $result['applicable_hourly_rate'] = $rate;
        $result['rate_display'] = $rateDisplay;

        if ($hours === null) {
            $result['projected_salary_display'] = $rateDisplay;
            $result['salary_display'] = $rateDisplay;
            $result['projection_basis'] = 'Part-Time hourly rate based on ' . $qualification . '; teaching hours are not configured.';
            return $result;
        }

        $projection = round($rate * $hours, 2);
        $hoursDisplay = rtrim(rtrim(number_format($hours, 2), '0'), '.');
        $projectionDisplay = nc_format_money($projection, $currencySymbol) . '/week';
        $result['projected_salary'] = $projection;
        $result['projected_salary_display'] = $projectionDisplay;
        // Dashboard compensation stays as an hourly guide. The weekly total is
        // retained separately for detail/review screens when load hours exist.
        $result['salary_display'] = $rateDisplay;
        $result['projection_basis'] = 'Part-Time ' . $qualification . ' rate x ' . $hoursDisplay . ' teaching hours/week.';
        return $result;
    }
}

if (!function_exists('nc_calculate_salary_projection')) {
    function nc_calculate_salary_projection(mysqli $conn, int $userId, array $job): array
    {
        return nc_calculate_salary_projection_from_education(nc_get_education_rows($conn, $userId), $job);
    }
}

if (!function_exists('nc_is_weekday_date')) {
    function nc_is_weekday_date(string $date): bool
    {
        $timestamp = strtotime($date);
        if (!$timestamp) {
            return false;
        }
        $day = (int)date('N', $timestamp);
        return $day >= 1 && $day <= 5;
    }
}

if (!function_exists('nc_get_active_rooms')) {
    function nc_get_active_rooms(mysqli $conn): array
    {
        if (!nc_table_exists($conn, 'rooms')) {
            return [];
        }
        $rooms = [];
        $result = $conn->query("SELECT id, room_name, campus_location FROM rooms WHERE is_active = 1 ORDER BY room_name ASC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rooms[] = $row;
            }
        }
        return $rooms;
    }
}

if (!function_exists('nc_get_room_by_id')) {
    function nc_get_room_by_id(mysqli $conn, int $roomId): ?array
    {
        if (!nc_table_exists($conn, 'rooms')) {
            return null;
        }
        $stmt = $conn->prepare("SELECT id, room_name, campus_location FROM rooms WHERE id = ? AND is_active = 1 LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param("i", $roomId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('nc_log_workflow_history')) {
    function nc_log_workflow_history(mysqli $conn, int $applicationId, ?string $fromStage, string $toStage, int $actionById, string $actionByRole, string $actionType, ?string $notes = null): void
    {
        if (!nc_table_exists($conn, 'workflow_history')) {
            return;
        }

        $stmt = $conn->prepare("
            INSERT INTO workflow_history (application_id, from_stage, to_stage, action_by_id, action_by_role, action_type, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param("ississs", $applicationId, $fromStage, $toStage, $actionById, $actionByRole, $actionType, $notes);
        $stmt->execute();
        $stmt->close();
    }
}
?>
