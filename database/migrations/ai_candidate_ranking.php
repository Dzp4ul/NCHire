<?php
/**
 * Backward-compatible AI candidate ranking schema for NCHire.
 * Run from the project root: php database/migrations/ai_candidate_ranking.php
 */

require_once __DIR__ . '/../../config/database.php';

function ranking_column_exists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare('SELECT COUNT(*) count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0) > 0;
    $stmt->close();
    return $exists;
}

function ranking_add_column(mysqli $conn, string $table, string $column, string $definition): void
{
    if (!ranking_column_exists($conn, $table, $column)) {
        if (!$conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition")) {
            throw new RuntimeException("Unable to add {$table}.{$column}: " . $conn->error);
        }
        echo "Added {$table}.{$column}\n";
    } else {
        echo "Kept existing {$table}.{$column}\n";
    }
}

function ranking_index_exists(mysqli $conn, string $table, string $index): bool
{
    $stmt = $conn->prepare('SELECT COUNT(*) count FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    $stmt->bind_param('ss', $table, $index);
    $stmt->execute();
    $exists = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0) > 0;
    $stmt->close();
    return $exists;
}

$conn->begin_transaction();
try {
    // Preserve all existing education rows while making attainment explicit.
    $educationColumn = $conn->query("SHOW COLUMNS FROM user_education LIKE 'education_level'")->fetch_assoc();
    if ($educationColumn && strpos((string)$educationColumn['Type'], 'high_school') === false) {
        if (!$conn->query("ALTER TABLE user_education MODIFY COLUMN education_level ENUM('high_school','associate','bachelor','master','doctorate','other') NOT NULL DEFAULT 'other'")) {
            throw new RuntimeException('Unable to extend education levels: ' . $conn->error);
        }
        echo "Extended user_education.education_level\n";
    }

    ranking_add_column($conn, 'user_experience', 'experience_type', "ENUM('teaching','industry','other') NOT NULL DEFAULT 'other' AFTER user_id");

    if (!$conn->query("CREATE TABLE IF NOT EXISTS user_qualifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        qualification_type ENUM('certification','license','training') NOT NULL,
        title VARCHAR(255) NOT NULL,
        issuing_organization VARCHAR(255) NULL,
        issued_date DATE NULL,
        expiry_date DATE NULL,
        proof_document VARCHAR(255) NULL,
        verification_status ENUM('unverified','verified','rejected') NOT NULL DEFAULT 'unverified',
        verified_by INT NULL,
        verified_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_qualification (user_id, qualification_type),
        INDEX idx_qualification_title (title)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
        throw new RuntimeException('Unable to create user_qualifications: ' . $conn->error);
    }

    $jobColumns = [
        'minimum_education_level' => "VARCHAR(20) NULL AFTER job_requirements",
        'required_degree_fields' => "TEXT NULL AFTER minimum_education_level",
        'graduate_requirement' => "VARCHAR(30) NULL AFTER required_degree_fields",
        'minimum_graduate_units' => "INT NULL AFTER graduate_requirement",
        'minimum_experience_years' => "DECIMAL(5,2) NULL AFTER minimum_graduate_units",
        'teaching_experience_requirement' => "VARCHAR(20) NULL AFTER minimum_experience_years",
        'required_skills' => "TEXT NULL AFTER teaching_experience_requirement",
        'required_certifications' => "TEXT NULL AFTER required_skills",
        'required_licenses' => "TEXT NULL AFTER required_certifications",
        'required_training' => "TEXT NULL AFTER required_licenses",
        'preferred_qualifications' => "TEXT NULL AFTER required_training",
    ];
    foreach ($jobColumns as $column => $definition) ranking_add_column($conn, 'job', $column, $definition);

    if (!$conn->query("CREATE TABLE IF NOT EXISTS candidate_rankings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        applicant_id INT NOT NULL,
        job_posting_id INT NOT NULL,
        overall_score DECIMAL(5,2) NULL,
        education_score DECIMAL(5,2) NULL,
        experience_score DECIMAL(5,2) NULL,
        skills_score DECIMAL(5,2) NULL,
        certification_score DECIMAL(5,2) NULL,
        requirements_score DECIMAL(5,2) NULL,
        qualification_label VARCHAR(100) NOT NULL,
        score_breakdown LONGTEXT NOT NULL,
        matching_qualifications LONGTEXT NULL,
        missing_requirements LONGTEXT NULL,
        profile_completeness LONGTEXT NULL,
        ai_summary TEXT NULL,
        ai_strengths LONGTEXT NULL,
        ai_missing_requirements LONGTEXT NULL,
        ai_job_match_explanation TEXT NULL,
        ai_recommendation VARCHAR(100) NULL,
        ai_status VARCHAR(60) NOT NULL DEFAULT 'not_requested',
        input_hash CHAR(64) NOT NULL,
        ai_input_hash CHAR(64) NULL,
        scoring_version VARCHAR(30) NOT NULL,
        deterministic_updated_at DATETIME NOT NULL,
        ai_updated_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_candidate_ranking_application (application_id),
        INDEX idx_candidate_ranking_job_score (job_posting_id, overall_score),
        INDEX idx_candidate_ranking_applicant (applicant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
        throw new RuntimeException('Unable to create candidate_rankings: ' . $conn->error);
    }

    if (!ranking_index_exists($conn, 'user_experience', 'idx_user_experience_type')) {
        if (!$conn->query('CREATE INDEX idx_user_experience_type ON user_experience (user_id, experience_type)')) {
            throw new RuntimeException('Unable to create user experience ranking index: ' . $conn->error);
        }
    }

    $conn->commit();
    echo "AI candidate ranking migration completed.\n";
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
