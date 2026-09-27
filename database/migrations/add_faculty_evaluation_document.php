<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../shared/helpers/recruitment.php';

if (!isset($conn) || $conn->connect_error) {
    throw new RuntimeException('Database connection failed.');
}

$changes = [
    ['job_applicants', 'faculty_evaluation', "VARCHAR(255) NULL AFTER `proof_of_enrollment`"],
    ['user_draft_documents', 'faculty_evaluation', "VARCHAR(255) NULL AFTER `proof_of_enrollment`"],
];

foreach ($changes as [$table, $column, $definition]) {
    if (nc_column_exists($conn, $table, $column)) {
        echo "{$table}.{$column} already exists.\n";
        continue;
    }

    if (!$conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}")) {
        throw new RuntimeException("Unable to add {$table}.{$column}: " . $conn->error);
    }
    echo "Added nullable {$table}.{$column}.\n";
}

$conn->close();
