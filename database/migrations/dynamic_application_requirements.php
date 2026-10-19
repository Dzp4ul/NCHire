<?php
/**
 * Idempotent migration for teaching-load-specific application requirements.
 * Run with: php database/migrations/dynamic_application_requirements.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../shared/helpers/dynamic_application_requirements.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

try {
    $conn->begin_transaction();

    $conn->query("CREATE TABLE IF NOT EXISTS teaching_load_requirements (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        teaching_load_id INT NOT NULL,
        requirement_key VARCHAR(80) NOT NULL,
        requirement_name VARCHAR(160) NOT NULL,
        description VARCHAR(1000) NULL,
        category VARCHAR(100) NOT NULL DEFAULT 'Supporting Documents',
        is_required TINYINT(1) NOT NULL DEFAULT 1,
        display_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_load_requirement_key (teaching_load_id, requirement_key),
        KEY idx_load_requirements_active (teaching_load_id, is_active, display_order),
        CONSTRAINT fk_load_requirement_job FOREIGN KEY (teaching_load_id) REFERENCES job(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS applicant_requirement_submissions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        application_id INT NOT NULL,
        requirement_id BIGINT UNSIGNED NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        original_filename VARCHAR(255) NOT NULL,
        storage_area ENUM('legacy','requirements') NOT NULL DEFAULT 'requirements',
        version_number INT NOT NULL DEFAULT 1,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        replaced_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_application_requirement_version (application_id, requirement_id, version_number),
        KEY idx_application_requirement_active (application_id, requirement_id, is_active),
        KEY idx_requirement_submissions (requirement_id, is_active),
        CONSTRAINT fk_requirement_submission_application FOREIGN KEY (application_id) REFERENCES job_applicants(id) ON DELETE CASCADE,
        CONSTRAINT fk_requirement_submission_requirement FOREIGN KEY (requirement_id) REFERENCES teaching_load_requirements(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seed legacy defaults only for teaching loads that have never been
    // configured. Rerunning the migration must not add defaults back after an
    // administrator intentionally customized or removed them.
    $jobs = $conn->query('SELECT j.id FROM job j
        LEFT JOIN teaching_load_requirements r ON r.teaching_load_id = j.id
        GROUP BY j.id HAVING COUNT(r.id) = 0 ORDER BY j.id');
    while ($job = $jobs->fetch_assoc()) {
        nc_seed_default_teaching_load_requirements($conn, (int)$job['id']);
    }

    $legacyDefinitions = nc_requirement_legacy_definitions();
    $requirements = $conn->query("SELECT id, teaching_load_id, requirement_key FROM teaching_load_requirements WHERE requirement_key NOT LIKE 'custom\\_%'");
    // MySQL cannot parameterize column identifiers. Prepare one whitelist-based
    // statement per legacy definition instead.
    while ($requirement = $requirements->fetch_assoc()) {
        $key = (string)$requirement['requirement_key'];
        if (!isset($legacyDefinitions[$key])) {
            continue;
        }
        $column = $legacyDefinitions[$key]['column'];
        $sql = "INSERT IGNORE INTO applicant_requirement_submissions
            (application_id, requirement_id, file_path, original_filename, storage_area, version_number, is_active, uploaded_at)
            SELECT ja.id, ?, ja.`{$column}`, ja.`{$column}`, 'legacy', 1, 1, COALESCE(ja.applied_date, CURRENT_TIMESTAMP)
            FROM job_applicants ja
            WHERE ja.job_id = ? AND ja.`{$column}` IS NOT NULL AND TRIM(ja.`{$column}`) <> ''";
        $backfill = $conn->prepare($sql);
        $requirementId = (int)$requirement['id'];
        $jobId = (int)$requirement['teaching_load_id'];
        $backfill->bind_param('ii', $requirementId, $jobId);
        $backfill->execute();
        $backfill->close();
    }

    $conn->commit();
    echo "Dynamic application requirements migration completed successfully.\n";
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}

