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
        if (in_array($explicit, ['bachelor', 'master', 'doctorate', 'other'], true)) {
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

if (!function_exists('nc_calculate_salary_projection')) {
    function nc_calculate_salary_projection(mysqli $conn, int $userId, array $job): array
    {
        $educationRows = nc_get_education_rows($conn, $userId);
        $rate = null;
        $qualification = 'No qualifying graduate education found';
        $masterStatus = null;
        $completedMasterUnits = null;

        foreach ($educationRows as $row) {
            $level = $row['education_level'] ?? nc_classify_education_level($row);
            $status = strtolower(trim((string)($row['education_status'] ?? 'completed'))) ?: 'completed';
            $units = isset($row['completed_units']) && $row['completed_units'] !== null ? (int)$row['completed_units'] : null;

            if ($level === 'doctorate' && $status === 'completed') {
                $rate = 220.00;
                $qualification = 'Doctorate - Completed';
                break;
            }

            if ($level === 'master') {
                $masterStatus = $status === 'ongoing' ? 'Ongoing' : 'Completed';
                $completedMasterUnits = $units;

                if ($status === 'completed' && $rate === null) {
                    $rate = 200.00;
                    $qualification = 'Master\'s - Completed';
                } elseif ($status === 'ongoing' && $units !== null && $units >= 9 && $rate === null) {
                    $rate = 150.00;
                    $qualification = "Master's - Ongoing ({$units} completed units)";
                }
            }
        }

        $hours = isset($job['teaching_hours_per_week']) && $job['teaching_hours_per_week'] !== null
            ? (float)$job['teaching_hours_per_week']
            : null;
        $projection = null;
        $basis = 'Projection unavailable: teaching hours are not configured for this teaching load.';

        if ($rate === null) {
            $basis = 'Projection unavailable: applicant does not have a qualifying completed graduate degree or ongoing master\'s with at least 9 completed units.';
        } elseif ($hours !== null && $hours > 0) {
            $projection = round($rate * $hours, 2);
            $basis = 'Applicable Hourly Rate x ' . rtrim(rtrim(number_format($hours, 2), '0'), '.') . ' compensable teaching hours/week';
        }

        return [
            'qualification' => $qualification,
            'master_status' => $masterStatus,
            'completed_master_units' => $completedMasterUnits,
            'applicable_hourly_rate' => $rate,
            'teaching_hours_per_week' => $hours,
            'projected_salary' => $projection,
            'projection_basis' => $basis,
        ];
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
