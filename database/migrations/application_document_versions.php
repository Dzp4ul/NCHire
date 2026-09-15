<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../shared/helpers/recruitment.php';
require_once __DIR__ . '/../../shared/helpers/application_documents.php';

if (!isset($conn) || $conn->connect_error) {
    throw new RuntimeException('Database connection failed.');
}

$sql = "CREATE TABLE IF NOT EXISTS application_document_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT NOT NULL,
    application_id INT NOT NULL,
    document_type VARCHAR(64) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    version_number INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    inherited_from_application_id INT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    replaced_at DATETIME NULL,
    UNIQUE KEY uq_application_document_version (application_id, document_type, version_number),
    KEY idx_applicant_current_documents (applicant_id, document_type, is_active),
    KEY idx_application_current_documents (application_id, document_type, is_active),
    CONSTRAINT fk_document_version_applicant FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE,
    CONSTRAINT fk_document_version_application FOREIGN KEY (application_id) REFERENCES job_applicants(id) ON DELETE CASCADE,
    CONSTRAINT fk_document_version_source_application FOREIGN KEY (inherited_from_application_id) REFERENCES job_applicants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$conn->query($sql)) {
    throw new RuntimeException('Unable to create application_document_versions: ' . $conn->error);
}

// Correct legacy category values: the first application keeps "new" and every
// later application for the same applicant is a renewal/reapplication.
if (nc_column_exists($conn, 'job_applicants', 'application_type')) {
    $categorySql = "UPDATE job_applicants ja
                    INNER JOIN (
                        SELECT current_app.id,
                               CASE WHEN EXISTS (
                                   SELECT 1
                                   FROM job_applicants earlier_app
                                   WHERE earlier_app.user_id = current_app.user_id
                                     AND (
                                         earlier_app.applied_date < current_app.applied_date
                                         OR (earlier_app.applied_date = current_app.applied_date AND earlier_app.id < current_app.id)
                                     )
                               ) THEN 'renewing' ELSE 'new' END AS derived_type
                        FROM job_applicants current_app
                        WHERE current_app.user_id IS NOT NULL
                    ) classified ON classified.id = ja.id
                    SET ja.application_type = classified.derived_type
                    WHERE ja.application_type <> classified.derived_type";
    if (!$conn->query($categorySql)) {
        throw new RuntimeException('Unable to backfill application categories: ' . $conn->error);
    }
}

$definitions = nc_application_document_definitions();
$columns = implode(', ', array_map(static fn(string $field): string => "`{$field}`", array_keys($definitions)));
$result = $conn->query("SELECT id, user_id, applied_date, {$columns} FROM job_applicants WHERE user_id IS NOT NULL ORDER BY id");
if ($result) {
    while ($application = $result->fetch_assoc()) {
        foreach ($definitions as $field => $definition) {
            if (empty($application[$field])) {
                continue;
            }
            nc_seed_legacy_document_version(
                $conn,
                (int)$application['user_id'],
                (int)$application['id'],
                $field,
                $application[$field],
                $application['applied_date']
            );
        }
    }
}

echo "application_document_versions is ready; legacy document snapshots checked.\n";
$conn->close();
