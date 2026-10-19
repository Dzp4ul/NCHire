<?php
/**
 * Replaces the legacy flat ongoing-Master's rate with the approved unit bands.
 *
 * Run from the project root:
 * php database/migrations/master_hourly_rate_bands.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../shared/helpers/recruitment.php';

$conn->begin_transaction();

try {
    if (!nc_table_exists($conn, 'graduate_unit_rate_rules')) {
        throw new RuntimeException('Run teaching_load_capacity_assignments.php before this migration.');
    }

    $configuration = nc_salary_configuration();
    $rules = $configuration['graduate_unit_rate_rules']['master_ongoing'] ?? [];
    if (!is_array($rules) || count($rules) === 0) {
        throw new RuntimeException('No ongoing Master\'s unit-rate bands are configured.');
    }

    $qualificationKey = 'master_ongoing';
    $delete = $conn->prepare('DELETE FROM graduate_unit_rate_rules WHERE qualification_key = ?');
    $delete->bind_param('s', $qualificationKey);
    if (!$delete->execute()) {
        throw new RuntimeException('Unable to replace the legacy ongoing Master\'s rate: ' . $delete->error);
    }
    $delete->close();

    $insert = $conn->prepare('
        INSERT INTO graduate_unit_rate_rules
            (qualification_key, minimum_units, maximum_units, hourly_rate, source_note)
        VALUES (?, ?, ?, ?, ?)
    ');
    foreach ($rules as $rule) {
        $minimumUnits = filter_var($rule['minimum_units'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $maximumUnits = ($rule['maximum_units'] ?? null) === null
            ? null
            : filter_var($rule['maximum_units'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $hourlyRate = $rule['hourly_rate'] ?? null;
        if ($minimumUnits === false || $maximumUnits === false || !is_numeric($hourlyRate) || (float)$hourlyRate <= 0) {
            throw new RuntimeException('An invalid ongoing Master\'s unit-rate band was configured.');
        }
        $hourlyRate = (float)$hourlyRate;
        $sourceNote = 'Approved NCHire ongoing Master\'s completed-unit salary band.';
        $insert->bind_param('siids', $qualificationKey, $minimumUnits, $maximumUnits, $hourlyRate, $sourceNote);
        if (!$insert->execute()) {
            throw new RuntimeException('Unable to save an ongoing Master\'s unit-rate band: ' . $insert->error);
        }
    }
    $insert->close();

    $conn->commit();
    echo "Master's hourly rate bands migrated successfully.\n";
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
