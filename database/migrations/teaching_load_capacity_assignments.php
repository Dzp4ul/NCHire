<?php
/**
 * Adds section-based teaching loads, configurable graduate-unit rates, and
 * immutable instructor assignment history.
 *
 * Run from the project root:
 * php database/migrations/teaching_load_capacity_assignments.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../shared/helpers/recruitment.php';

function capacity_migration_column_exists(mysqli $conn, string $table, string $column): bool
{
    return nc_column_exists($conn, $table, $column);
}

function capacity_migration_add_column(mysqli $conn, string $table, string $column, string $definition): void
{
    if (!capacity_migration_column_exists($conn, $table, $column)) {
        if (!$conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition")) {
            throw new RuntimeException("Failed adding {$table}.{$column}: " . $conn->error);
        }
        echo "Added {$table}.{$column}\n";
    } else {
        echo "Kept existing {$table}.{$column}\n";
    }
}

function capacity_migration_index_exists(mysqli $conn, string $table, string $index): bool
{
    $stmt = $conn->prepare("SELECT COUNT(*) count FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    if (!$stmt) {
        throw new RuntimeException('Unable to inspect database indexes: ' . $conn->error);
    }
    $stmt->bind_param('ss', $table, $index);
    $stmt->execute();
    $exists = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0) > 0;
    $stmt->close();
    return $exists;
}

$conn->begin_transaction();

try {
    // Legacy load_units and teaching_schedule stay in place for old records.
    // The new fields are the source of truth for all newly created loads.
    capacity_migration_add_column($conn, 'job', 'lecture_units', "DECIMAL(6,2) NULL AFTER load_units");
    capacity_migration_add_column($conn, 'job', 'laboratory_units', "DECIMAL(6,2) NULL AFTER lecture_units");
    capacity_migration_add_column($conn, 'job', 'initial_available_sections', "INT NULL AFTER required_instructors");
    capacity_migration_add_column($conn, 'job', 'available_sections', "INT NULL AFTER initial_available_sections");

    // required_instructors is a deterministic capacity source for legacy rows.
    // Unit breakdowns are intentionally not fabricated from generic load_units.
    $conn->query("
        UPDATE job j
        LEFT JOIN (
            SELECT job_id, COUNT(*) assigned_count
            FROM job_applicants
            WHERE job_id IS NOT NULL
              AND (status IN ('Passed','Application Passed','Hired','Permanently Hired')
                   OR workflow_stage IN ('passed','hired','permanently_hired'))
            GROUP BY job_id
        ) assigned ON assigned.job_id = j.id
        SET j.initial_available_sections = COALESCE(j.initial_available_sections, GREATEST(COALESCE(j.required_instructors, 1), 0)),
            j.available_sections = COALESCE(j.available_sections, GREATEST(COALESCE(j.required_instructors, 1) - COALESCE(assigned.assigned_count, 0), 0))
    ");
    $conn->query("UPDATE job SET status = 'Closed' WHERE available_sections = 0");

    $conn->query("
        CREATE TABLE IF NOT EXISTS graduate_unit_rate_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            qualification_key VARCHAR(60) NOT NULL,
            minimum_units INT NOT NULL DEFAULT 0,
            maximum_units INT NULL,
            hourly_rate DECIMAL(10,2) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            source_note VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_graduate_rate_band (qualification_key, minimum_units, maximum_units),
            KEY idx_graduate_rate_lookup (qualification_key, is_active, minimum_units, maximum_units),
            CONSTRAINT chk_graduate_rate_units CHECK (minimum_units >= 0 AND (maximum_units IS NULL OR maximum_units >= minimum_units)),
            CONSTRAINT chk_graduate_rate_amount CHECK (hourly_rate >= 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Preserve the existing approved flat ongoing-Master's rate as the broad
    // fallback band. Administrators can split this into narrower bands later.
    $salaryConfig = nc_salary_configuration();
    $ongoingRateConfig = $salaryConfig['part_time_rates']['master_ongoing'] ?? null;
    $ongoingRate = is_array($ongoingRateConfig) ? ($ongoingRateConfig['hourly_rate'] ?? null) : $ongoingRateConfig;
    if (is_numeric($ongoingRate) && (float)$ongoingRate >= 0) {
        $qualificationKey = 'master_ongoing';
        $minimumUnits = 0;
        $sourceNote = 'Migrated from the existing NCHire ongoing Master\'s rate configuration.';
        $rateStmt = $conn->prepare("
            INSERT INTO graduate_unit_rate_rules (qualification_key, minimum_units, maximum_units, hourly_rate, source_note)
            SELECT ?, ?, NULL, ?, ?
            WHERE NOT EXISTS (
                SELECT 1 FROM graduate_unit_rate_rules
                WHERE qualification_key = ? AND minimum_units = ? AND maximum_units IS NULL
            )
        ");
        $rate = (float)$ongoingRate;
        $rateStmt->bind_param('sidssi', $qualificationKey, $minimumUnits, $rate, $sourceNote, $qualificationKey, $minimumUnits);
        if (!$rateStmt->execute()) {
            throw new RuntimeException('Failed seeding the ongoing Master\'s rate rule: ' . $rateStmt->error);
        }
        $rateStmt->close();
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS teaching_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            application_id INT NOT NULL,
            user_id INT NOT NULL,
            job_id INT NOT NULL,
            subject_code VARCHAR(50) NULL,
            subject_name VARCHAR(255) NOT NULL,
            program VARCHAR(255) NULL,
            academic_year VARCHAR(20) NOT NULL,
            semester VARCHAR(30) NOT NULL,
            assigned_sections INT NOT NULL DEFAULT 1,
            lecture_units DECIMAL(6,2) NULL,
            laboratory_units DECIMAL(6,2) NULL,
            teaching_hours_per_week DECIMAL(6,2) NULL,
            employment_type VARCHAR(50) NULL,
            salary_grade VARCHAR(50) NULL,
            projected_hourly_rate DECIMAL(10,2) NULL,
            projected_weekly_compensation DECIMAL(12,2) NULL,
            compensation_basis TEXT NULL,
            assignment_status ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
            assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            assigned_by INT NULL,
            completed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_assignment_application (application_id),
            KEY idx_assignment_user_period (user_id, academic_year, semester, assignment_status),
            KEY idx_assignment_job_status (job_id, assignment_status),
            CONSTRAINT chk_assignment_sections CHECK (assigned_sections > 0),
            CONSTRAINT chk_assignment_lecture_units CHECK (lecture_units IS NULL OR lecture_units >= 0),
            CONSTRAINT chk_assignment_laboratory_units CHECK (laboratory_units IS NULL OR laboratory_units >= 0),
            CONSTRAINT fk_assignment_application FOREIGN KEY (application_id) REFERENCES job_applicants(id),
            CONSTRAINT fk_assignment_job FOREIGN KEY (job_id) REFERENCES job(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Snapshot existing successful applications without changing capacity a
    // second time; the earlier backfill already accounted for these records.
    $assignmentBackfill = $conn->prepare("
        INSERT IGNORE INTO teaching_assignments (
            application_id, user_id, job_id, subject_code, subject_name, program,
            academic_year, semester, assigned_sections, lecture_units,
            laboratory_units, teaching_hours_per_week, employment_type,
            salary_grade, projected_hourly_rate, projected_weekly_compensation,
            compensation_basis, assignment_status, assigned_at, assigned_by
        )
        SELECT ja.id, ja.user_id, ja.job_id, j.subject_code,
               COALESCE(NULLIF(j.subject_name, ''), NULLIF(j.subject, ''), j.job_title),
               COALESCE(NULLIF(j.program, ''), j.department_role),
               COALESCE(NULLIF(ja.academic_year, ''), NULLIF(j.academic_year, ''), 'Legacy'),
               COALESCE(NULLIF(ja.semester, ''), NULLIF(j.semester, ''), 'Legacy'),
               1, j.lecture_units, j.laboratory_units, j.teaching_hours_per_week,
               j.job_type, j.salary_grade, ja.applicable_hourly_rate,
               ja.salary_projection, ja.salary_projection_basis,
               CASE
                   WHEN COALESCE(NULLIF(ja.academic_year, ''), j.academic_year) = ?
                    AND COALESCE(NULLIF(ja.semester, ''), j.semester) = ? THEN 'active'
                   ELSE 'completed'
               END,
               COALESCE(ja.hired_date, ja.application_passed_date, CONCAT(ja.applied_date, ' 00:00:00')),
               ja.application_passed_by
        FROM job_applicants ja
        INNER JOIN job j ON j.id = ja.job_id
        WHERE ja.user_id IS NOT NULL
          AND (ja.status IN ('Passed','Application Passed','Hired','Permanently Hired')
               OR ja.workflow_stage IN ('passed','hired','permanently_hired'))
    ");
    if (!$assignmentBackfill) {
        throw new RuntimeException('Failed preparing assignment history backfill: ' . $conn->error);
    }
    $currentAcademicYear = nc_current_academic_year();
    $currentSemester = nc_current_semester();
    $assignmentBackfill->bind_param('ss', $currentAcademicYear, $currentSemester);
    if (!$assignmentBackfill->execute()) {
        throw new RuntimeException('Failed backfilling assignment history: ' . $assignmentBackfill->error);
    }
    $assignmentBackfill->close();

    if (!capacity_migration_index_exists($conn, 'job', 'idx_job_available_sections')) {
        $conn->query("CREATE INDEX idx_job_available_sections ON job (status, available_sections, application_deadline)");
    }

    $conn->commit();
    echo "Teaching-load capacity and assignment migration completed.\n";
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
