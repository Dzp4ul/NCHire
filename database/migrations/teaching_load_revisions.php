<?php
/**
 * NCHire teaching-load recruitment revisions.
 *
 * Run from the project root:
 * php database/migrations/teaching_load_revisions.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../shared/helpers/recruitment.php';

function migration_column_exists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS count
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    if (!$stmt) {
        throw new RuntimeException("Column check prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $exists = (int)($row["count"] ?? 0) > 0;
    $stmt->close();
    return $exists;
}

function migration_add_column(mysqli $conn, string $table, string $column, string $definition): void
{
    if (!migration_column_exists($conn, $table, $column)) {
        $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
        if (!$conn->query($sql)) {
            throw new RuntimeException("Failed adding {$table}.{$column}: " . $conn->error);
        }
        echo "Added {$table}.{$column}\n";
    } else {
        echo "Kept existing {$table}.{$column}\n";
    }
}

function migration_index_exists(mysqli $conn, string $table, string $index): bool
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS count
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ");
    if (!$stmt) {
        throw new RuntimeException("Index check prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ss", $table, $index);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $exists = (int)($row["count"] ?? 0) > 0;
    $stmt->close();
    return $exists;
}

function migration_add_index(mysqli $conn, string $table, string $index, string $columns): void
{
    if (!migration_index_exists($conn, $table, $index)) {
        $sql = "CREATE INDEX `$index` ON `$table` ($columns)";
        if (!$conn->query($sql)) {
            throw new RuntimeException("Failed adding index {$index}: " . $conn->error);
        }
        echo "Added index {$index}\n";
    }
}

$conn->begin_transaction();

try {
    // Teaching-load metadata extends the existing job table for backward compatibility.
    migration_add_column($conn, 'job', 'status', "ENUM('Active','Closed') NOT NULL DEFAULT 'Active' AFTER application_deadline");
    migration_add_column($conn, 'job', 'subject_code', "VARCHAR(50) NULL AFTER subject");
    migration_add_column($conn, 'job', 'subject_name', "VARCHAR(255) NULL AFTER subject_code");
    migration_add_column($conn, 'job', 'program', "VARCHAR(255) NULL AFTER subject_name");
    migration_add_column($conn, 'job', 'academic_year', "VARCHAR(20) NULL AFTER program");
    migration_add_column($conn, 'job', 'semester', "ENUM('First Semester','Second Semester','Summer') NULL AFTER academic_year");
    migration_add_column($conn, 'job', 'teaching_schedule', "TEXT NULL AFTER semester");
    migration_add_column($conn, 'job', 'teaching_hours_per_week', "DECIMAL(6,2) NULL AFTER teaching_schedule");
    migration_add_column($conn, 'job', 'load_units', "DECIMAL(6,2) NULL AFTER teaching_hours_per_week");
    migration_add_column($conn, 'job', 'required_instructors', "INT NOT NULL DEFAULT 1 AFTER load_units");
    migration_add_column($conn, 'job', 'salary_grade', "VARCHAR(50) NULL AFTER required_instructors");

    $conn->query("
        UPDATE job
        SET status = CASE WHEN application_deadline < CURDATE() THEN 'Closed' ELSE 'Active' END
        WHERE status IS NULL OR status = ''
    ");
    $conn->query("UPDATE job SET subject_name = NULLIF(subject, '') WHERE (subject_name IS NULL OR subject_name = '')");
    $conn->query("UPDATE job SET program = department_role WHERE (program IS NULL OR program = '')");
    $conn->query("
        UPDATE job
        SET academic_year = CASE
            WHEN MONTH(CURDATE()) >= 6 THEN CONCAT(YEAR(CURDATE()), '-', YEAR(CURDATE()) + 1)
            ELSE CONCAT(YEAR(CURDATE()) - 1, '-', YEAR(CURDATE()))
        END
        WHERE academic_year IS NULL OR academic_year = ''
    ");
    $conn->query("
        UPDATE job
        SET semester = CASE
            WHEN MONTH(CURDATE()) BETWEEN 1 AND 5 THEN 'Second Semester'
            ELSE 'First Semester'
        END
        WHERE semester IS NULL OR semester = ''
    ");

    migration_add_index($conn, 'job', 'idx_job_academic_period', '`academic_year`, `semester`');
    migration_add_index($conn, 'job', 'idx_job_subject_code', '`subject_code`');
    migration_add_index($conn, 'job', 'idx_job_status_deadline', '`status`, `application_deadline`');

    // Graduate education metadata.
    migration_add_column($conn, 'user_education', 'education_level', "ENUM('bachelor','master','doctorate','other') NOT NULL DEFAULT 'other' AFTER user_id");
    migration_add_column($conn, 'user_education', 'education_status', "ENUM('completed','ongoing') NOT NULL DEFAULT 'completed' AFTER institution");
    migration_add_column($conn, 'user_education', 'completed_units', "INT NULL AFTER education_status");
    migration_add_column($conn, 'user_education', 'year_completed', "INT NULL AFTER completed_units");
    migration_add_column($conn, 'user_education', 'certificate_of_grades', "VARCHAR(255) NULL AFTER year_completed");
    migration_add_column($conn, 'user_education', 'proof_of_enrollment', "VARCHAR(255) NULL AFTER certificate_of_grades");
    $conn->query("
        UPDATE user_education
        SET education_level = CASE
            WHEN LOWER(degree) LIKE '%doctor%' OR LOWER(degree) LIKE '%ph.d%' OR LOWER(degree) LIKE '%phd%' THEN 'doctorate'
            WHEN LOWER(degree) LIKE '%master%' OR LOWER(degree) LIKE '%masteral%' THEN 'master'
            WHEN LOWER(degree) LIKE '%bachelor%' OR LOWER(degree) LIKE '%baccalaureate%' THEN 'bachelor'
            ELSE education_level
        END
    ");
    $conn->query("UPDATE user_education SET year_completed = end_year WHERE year_completed IS NULL AND education_status = 'completed'");

    // Application cycle/history, document, and salary snapshot fields.
    migration_add_column($conn, 'job_applicants', 'application_type', "ENUM('new','renewing') NOT NULL DEFAULT 'new' AFTER job_id");
    migration_add_column($conn, 'job_applicants', 'academic_year', "VARCHAR(20) NULL AFTER application_type");
    migration_add_column($conn, 'job_applicants', 'semester', "VARCHAR(30) NULL AFTER academic_year");
    migration_add_column($conn, 'job_applicants', 'certificate_of_grades', "VARCHAR(255) NULL AFTER masteral_cert");
    migration_add_column($conn, 'job_applicants', 'proof_of_enrollment', "VARCHAR(255) NULL AFTER certificate_of_grades");
    migration_add_column($conn, 'job_applicants', 'applicable_hourly_rate', "DECIMAL(10,2) NULL AFTER proof_of_enrollment");
    migration_add_column($conn, 'job_applicants', 'salary_projection', "DECIMAL(12,2) NULL AFTER applicable_hourly_rate");
    migration_add_column($conn, 'job_applicants', 'salary_projection_basis', "TEXT NULL AFTER salary_projection");
    migration_add_column($conn, 'job_applicants', 'salary_projection_computed_at', "DATETIME NULL AFTER salary_projection_basis");
    migration_add_column($conn, 'job_applicants', 'application_passed_date', "DATETIME NULL AFTER hired_date");
    migration_add_column($conn, 'job_applicants', 'application_passed_by', "INT NULL AFTER application_passed_date");
    migration_add_column($conn, 'job_applicants', 'interview_room_id', "INT NULL AFTER interview_room");
    migration_add_column($conn, 'job_applicants', 'demo_room_id', "INT NULL AFTER demo_room");

    $conn->query("
        UPDATE job_applicants ja
        INNER JOIN job j ON j.id = ja.job_id
        SET ja.academic_year = COALESCE(NULLIF(ja.academic_year, ''), j.academic_year),
            ja.semester = COALESCE(NULLIF(ja.semester, ''), j.semester)
        WHERE ja.job_id IS NOT NULL
    ");

    migration_add_index($conn, 'job_applicants', 'idx_application_teaching_load_cycle', '`user_id`, `job_id`, `academic_year`, `semester`, `application_type`');
    migration_add_index($conn, 'job_applicants', 'idx_application_status_cycle', '`status`, `workflow_stage`, `academic_year`, `semester`');

    $workflowColumn = $conn->query("SHOW COLUMNS FROM job_applicants LIKE 'workflow_stage'")->fetch_assoc();
    if ($workflowColumn) {
        $type = $workflowColumn['Type'] ?? '';
        if (strpos($type, 'waiting_interview_schedule') === false || strpos($type, "'passed'") === false) {
            $sql = "ALTER TABLE job_applicants MODIFY COLUMN workflow_stage ENUM(
                'secretary_review',
                'secretary_approved',
                'department_head_review',
                'waiting_interview_schedule',
                'interview_scheduled',
                'interview_completed',
                'demo_scheduled',
                'demo_completed',
                'psych_scheduled',
                'psych_completed',
                'initially_hired',
                'permanently_hired',
                'passed',
                'hired',
                'rejected',
                'cancelled'
            ) DEFAULT 'secretary_review'";
            if (!$conn->query($sql)) {
                throw new RuntimeException("Failed updating workflow_stage enum: " . $conn->error);
            }
            echo "Updated workflow_stage enum\n";
        }
    }

    // Reusable document drafts.
    migration_add_column($conn, 'user_draft_documents', 'certificate_of_grades', "VARCHAR(255) NULL AFTER masteral_cert");
    migration_add_column($conn, 'user_draft_documents', 'proof_of_enrollment', "VARCHAR(255) NULL AFTER certificate_of_grades");

    // Manageable room catalog.
    $conn->query("
        CREATE TABLE IF NOT EXISTS rooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_name VARCHAR(150) NOT NULL UNIQUE,
            campus_location VARCHAR(255) NOT NULL DEFAULT 'Norzagaray College',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $roomCount = $conn->query("SELECT COUNT(*) AS count FROM rooms")->fetch_assoc()['count'] ?? 0;
    if ((int)$roomCount === 0) {
        $rooms = [
            'Computer Laboratory 1',
            'Computer Laboratory 2',
            'Faculty Room',
            'Conference Room',
            'Room 201',
        ];
        $stmt = $conn->prepare("INSERT INTO rooms (room_name, campus_location) VALUES (?, 'Norzagaray College')");
        foreach ($rooms as $room) {
            $stmt->bind_param("s", $room);
            $stmt->execute();
        }
        $stmt->close();
        echo "Seeded default manageable room catalog\n";
    }

    // Idempotent sample teaching loads for the active applicant dashboard.
    $sampleAcademicYear = nc_current_academic_year();
    $sampleSemester = nc_current_semester();
    $sampleDeadline = date('Y-m-d', strtotime('+45 days'));
    $sampleTeachingLoads = [
        [
            'subject_code' => 'CC 101',
            'subject_name' => 'Introduction to Computing',
            'subject' => 'Computing Studies Professional Subjects',
            'program' => 'BS Computer Science',
            'department_role' => 'Computing Studies',
            'job_type' => 'Part-time',
            'schedule' => 'Mon/Wed 8:00 AM-10:00 AM',
            'hours' => 4.0,
            'units' => 3.0,
            'required' => 2,
            'salary_grade' => '',
            'description' => 'Teaching load for foundational computing concepts, digital literacy, and introductory laboratory activities.'
        ],
        [
            'subject_code' => 'HCM 201',
            'subject_name' => 'Food and Beverage Service Operations',
            'subject' => 'Hospitality Management Professional Subjects',
            'program' => 'BS Hospitality Management',
            'department_role' => 'Hospitality Management',
            'job_type' => 'Part-time',
            'schedule' => 'Tue/Thu 1:00 PM-3:00 PM',
            'hours' => 4.0,
            'units' => 3.0,
            'required' => 1,
            'salary_grade' => '',
            'description' => 'Teaching load for hospitality service standards, dining operations, and practical food and beverage laboratory supervision.'
        ],
        [
            'subject_code' => 'EDUC 105',
            'subject_name' => 'Assessment of Learning',
            'subject' => 'BSEd and BEED Professional Education Subjects',
            'program' => 'Teacher Education',
            'department_role' => 'Education',
            'job_type' => 'Full-time',
            'schedule' => 'Fri 8:00 AM-12:00 PM',
            'hours' => 4.0,
            'units' => 3.0,
            'required' => 1,
            'salary_grade' => 'SG-11',
            'description' => 'Teaching load for assessment design, learner evaluation, rubrics, and classroom measurement practices.'
        ],
    ];

    $existsStmt = $conn->prepare("SELECT id FROM job WHERE subject_code = ? AND academic_year = ? AND semester = ? LIMIT 1");
    $insertStmt = $conn->prepare("
        INSERT INTO job (
            job_title, department_role, job_type, locations, salary_range, application_deadline,
            job_description, subject, status, subject_code, subject_name, program, academic_year,
            semester, teaching_schedule, teaching_hours_per_week, load_units, required_instructors, salary_grade
        ) VALUES (?, ?, ?, 'Norzagaray College', '', ?, ?, ?, 'Active', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $seededLoads = 0;
    foreach ($sampleTeachingLoads as $load) {
        $existsStmt->bind_param('sss', $load['subject_code'], $sampleAcademicYear, $sampleSemester);
        $existsStmt->execute();
        if ($existsStmt->get_result()->num_rows > 0) {
            continue;
        }

        $title = trim($load['subject_code'] . ' - ' . $load['subject_name'] . ' Instructor');
        $insertStmt->bind_param(
            'ssssssssssssddis',
            $title,
            $load['department_role'],
            $load['job_type'],
            $sampleDeadline,
            $load['description'],
            $load['subject'],
            $load['subject_code'],
            $load['subject_name'],
            $load['program'],
            $sampleAcademicYear,
            $sampleSemester,
            $load['schedule'],
            $load['hours'],
            $load['units'],
            $load['required'],
            $load['salary_grade']
        );
        $insertStmt->execute();
        $seededLoads++;
    }
    $existsStmt->close();
    $insertStmt->close();
    if ($seededLoads > 0) {
        echo "Seeded {$seededLoads} sample teaching loads\n";
    }

    $conn->commit();
    echo "Teaching-load revision migration completed.\n";
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
?>
