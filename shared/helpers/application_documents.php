<?php
/**
 * Applicant/application document helpers.
 *
 * job_applicants remains the source of truth for the active document snapshot on
 * each application. application_document_versions only adds replacement history.
 */

if (!function_exists('nc_application_document_definitions')) {
    function nc_application_document_definitions(): array
    {
        return [
            'application_letter' => ['label' => 'Application Letter', 'input' => 'applicationLetter', 'required' => true, 'multiple' => false],
            'resume' => ['label' => 'Updated and Comprehensive Resume', 'input' => 'resume_file', 'required' => true, 'multiple' => false],
            'letter_of_intent' => ['label' => 'Letter of Intent', 'input' => 'letter_of_intent', 'required' => true, 'multiple' => false],
            'tor' => ['label' => 'Transcript of Records (TOR)', 'input' => 'transcript', 'required' => true, 'multiple' => false],
            'diploma' => ['label' => 'Diploma', 'input' => 'diploma', 'required' => true, 'multiple' => false],
            'professional_license' => ['label' => 'Professional License', 'input' => 'license', 'required' => false, 'multiple' => false],
            'coe' => ['label' => 'Certificate of Employment (COE)', 'input' => 'coe', 'required' => true, 'multiple' => false],
            'seminars_trainings' => ['label' => 'Seminars/Training Certificates', 'input' => 'certificates[]', 'required' => true, 'multiple' => true],
            'masteral_cert' => ['label' => "Master's/Doctorate Certification", 'input' => 'masteral_cert', 'required' => false, 'multiple' => false],
            'certificate_of_grades' => ['label' => "Master's/Doctorate Grades", 'input' => 'certificate_of_grades', 'required' => false, 'multiple' => false],
            'proof_of_enrollment' => ['label' => 'Proof of Enrollment', 'input' => 'proof_of_enrollment', 'required' => false, 'multiple' => false],
        ];
    }
}

if (!function_exists('nc_parse_document_list')) {
    function nc_parse_document_list($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        $value = trim((string)$value);
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded)));
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}

if (!function_exists('nc_document_file_names')) {
    function nc_document_file_names(?string $value, bool $multiple = false): array
    {
        $value = trim((string)$value);
        if ($value === '') {
            return [];
        }

        $files = $multiple ? explode(',', $value) : [$value];
        return array_values(array_filter(array_map('trim', $files)));
    }
}

if (!function_exists('nc_find_reusable_documents')) {
    /**
     * Return the newest valid value for every document owned by an applicant.
     * A document explicitly requested for resubmission on an application is not
     * reusable from that application; an older valid version may still be used.
     */
    function nc_find_reusable_documents(mysqli $conn, int $userId, ?int $excludeApplicationId = null): array
    {
        $definitions = nc_application_document_definitions();
        $documents = [];
        $blockedFields = [];

        // Prefer explicitly versioned files because a user may update an older
        // application after a newer application was submitted.
        if (function_exists('nc_table_exists') && nc_table_exists($conn, 'application_document_versions')) {
            $versionSql = "SELECT v.document_type, v.file_name, v.application_id, v.uploaded_at, ja.resubmission_documents
                           FROM application_document_versions v
                           INNER JOIN job_applicants ja ON ja.id = v.application_id
                           WHERE v.applicant_id = ? AND v.is_active = 1";
            if ($excludeApplicationId !== null) {
                $versionSql .= ' AND v.application_id <> ?';
            }
            $versionSql .= ' ORDER BY v.uploaded_at DESC, v.id DESC';
            $versionStmt = $conn->prepare($versionSql);
            if ($versionStmt) {
                if ($excludeApplicationId !== null) {
                    $versionStmt->bind_param('ii', $userId, $excludeApplicationId);
                } else {
                    $versionStmt->bind_param('i', $userId);
                }
                $versionStmt->execute();
                $versionResult = $versionStmt->get_result();
                while ($versionRow = $versionResult->fetch_assoc()) {
                    $field = $versionRow['document_type'];
                    if (!isset($definitions[$field]) || isset($documents[$field]) || isset($blockedFields[$field])) {
                        continue;
                    }
                    $invalid = array_flip(nc_parse_document_list($versionRow['resubmission_documents'] ?? null));
                    if (isset($invalid[$field])) {
                        $blockedFields[$field] = true;
                        continue;
                    }
                    $definition = $definitions[$field];
                    $documents[$field] = [
                        'document_type' => $field,
                        'label' => $definition['label'],
                        'input_name' => $definition['input'],
                        'file_name' => $versionRow['file_name'],
                        'files' => nc_document_file_names($versionRow['file_name'], (bool)$definition['multiple']),
                        'source_application_id' => (int)$versionRow['application_id'],
                        'uploaded_at' => $versionRow['uploaded_at'],
                        'status' => 'Current',
                        'required' => (bool)$definition['required'],
                    ];
                }
                $versionStmt->close();
            }
        }

        $columns = implode(', ', array_map(static fn(string $field): string => "`{$field}`", array_keys($definitions)));
        $sql = "SELECT id, applied_date, status, resubmission_documents, {$columns}
                FROM job_applicants
                WHERE user_id = ?";
        if ($excludeApplicationId !== null) {
            $sql .= ' AND id <> ?';
        }
        $sql .= ' ORDER BY applied_date DESC, id DESC';

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['is_existing_applicant' => false, 'application_count' => 0, 'documents' => []];
        }
        if ($excludeApplicationId !== null) {
            $stmt->bind_param('ii', $userId, $excludeApplicationId);
        } else {
            $stmt->bind_param('i', $userId);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $applicationCount = 0;
        while ($row = $result->fetch_assoc()) {
            $applicationCount++;
            $invalidOnThisApplication = array_flip(nc_parse_document_list($row['resubmission_documents'] ?? null));
            foreach ($definitions as $field => $definition) {
                if (isset($documents[$field]) || isset($blockedFields[$field])) {
                    continue;
                }
                if (isset($invalidOnThisApplication[$field])) {
                    $blockedFields[$field] = true;
                    continue;
                }
                $value = trim((string)($row[$field] ?? ''));
                if ($value === '') {
                    continue;
                }
                $documents[$field] = [
                    'document_type' => $field,
                    'label' => $definition['label'],
                    'input_name' => $definition['input'],
                    'file_name' => $value,
                    'files' => nc_document_file_names($value, (bool)$definition['multiple']),
                    'source_application_id' => (int)$row['id'],
                    'uploaded_at' => $row['applied_date'],
                    'status' => 'Current',
                    'required' => (bool)$definition['required'],
                ];
            }
        }
        $stmt->close();

        return [
            'is_existing_applicant' => $applicationCount > 0,
            'application_count' => $applicationCount,
            'documents' => $documents,
        ];
    }
}

if (!function_exists('nc_merge_reusable_document_values')) {
    function nc_merge_reusable_document_values(array $values, array $reusableDocuments): array
    {
        foreach (nc_application_document_definitions() as $field => $_definition) {
            if (empty($values[$field]) && !empty($reusableDocuments[$field]['file_name'])) {
                $values[$field] = $reusableDocuments[$field]['file_name'];
            }
        }
        return $values;
    }
}

if (!function_exists('nc_required_application_document_fields')) {
    function nc_required_application_document_fields(bool $requiresOngoingGraduateDocuments = false): array
    {
        $required = [];
        foreach (nc_application_document_definitions() as $field => $definition) {
            if (!empty($definition['required'])) {
                $required[] = $field;
            }
        }
        if ($requiresOngoingGraduateDocuments) {
            $required[] = 'certificate_of_grades';
            $required[] = 'proof_of_enrollment';
        }
        return array_values(array_unique($required));
    }
}

if (!function_exists('nc_missing_application_documents')) {
    function nc_missing_application_documents(array $values, bool $requiresOngoingGraduateDocuments = false): array
    {
        $definitions = nc_application_document_definitions();
        $missing = [];
        foreach (nc_required_application_document_fields($requiresOngoingGraduateDocuments) as $field) {
            if (empty($values[$field])) {
                $missing[$field] = $definitions[$field]['label'] ?? $field;
            }
        }
        return $missing;
    }
}

if (!function_exists('nc_record_application_document_version')) {
    function nc_record_application_document_version(
        mysqli $conn,
        int $userId,
        int $applicationId,
        string $documentType,
        string $fileName,
        ?string $originalName = null,
        ?int $inheritedFromApplicationId = null
    ): bool {
        if (!function_exists('nc_table_exists') || !nc_table_exists($conn, 'application_document_versions')) {
            return false;
        }
        if (!isset(nc_application_document_definitions()[$documentType]) || trim($fileName) === '') {
            return false;
        }

        $versionStmt = $conn->prepare('SELECT COALESCE(MAX(version_number), 0) + 1 AS next_version FROM application_document_versions WHERE application_id = ? AND document_type = ?');
        if (!$versionStmt) {
            return false;
        }
        $versionStmt->bind_param('is', $applicationId, $documentType);
        $versionStmt->execute();
        $version = (int)($versionStmt->get_result()->fetch_assoc()['next_version'] ?? 1);
        $versionStmt->close();

        $insert = $conn->prepare('INSERT INTO application_document_versions (applicant_id, application_id, document_type, file_name, original_name, version_number, is_active, inherited_from_application_id) VALUES (?, ?, ?, ?, ?, ?, 1, ?)');
        if (!$insert) {
            return false;
        }
        $insert->bind_param('iisssii', $userId, $applicationId, $documentType, $fileName, $originalName, $version, $inheritedFromApplicationId);
        $ok = $insert->execute();
        $newVersionId = (int)$conn->insert_id;
        $insert->close();
        if (!$ok) {
            return false;
        }

        // Only retire the old version after the new row exists. If insertion
        // fails, the previously active document remains safely active.
        $deactivate = $conn->prepare('UPDATE application_document_versions SET is_active = 0, replaced_at = NOW() WHERE application_id = ? AND document_type = ? AND is_active = 1 AND id <> ?');
        if (!$deactivate) {
            $conn->query('DELETE FROM application_document_versions WHERE id = ' . $newVersionId);
            return false;
        }
        $deactivate->bind_param('isi', $applicationId, $documentType, $newVersionId);
        $deactivated = $deactivate->execute();
        $deactivate->close();
        if (!$deactivated) {
            $conn->query('DELETE FROM application_document_versions WHERE id = ' . $newVersionId);
            return false;
        }
        return true;
    }
}

if (!function_exists('nc_seed_legacy_document_version')) {
    function nc_seed_legacy_document_version(mysqli $conn, int $userId, int $applicationId, string $documentType, ?string $fileName, ?string $uploadedAt = null): void
    {
        if (empty($fileName) || !function_exists('nc_table_exists') || !nc_table_exists($conn, 'application_document_versions')) {
            return;
        }
        $check = $conn->prepare('SELECT id FROM application_document_versions WHERE application_id = ? AND document_type = ? LIMIT 1');
        if (!$check) {
            return;
        }
        $check->bind_param('is', $applicationId, $documentType);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();
        if ($exists) {
            return;
        }

        $originalName = basename($fileName);
        $insert = $conn->prepare('INSERT INTO application_document_versions (applicant_id, application_id, document_type, file_name, original_name, version_number, is_active, uploaded_at) VALUES (?, ?, ?, ?, ?, 1, 1, COALESCE(?, NOW()))');
        if ($insert) {
            $insert->bind_param('iissss', $userId, $applicationId, $documentType, $fileName, $originalName, $uploadedAt);
            $insert->execute();
            $insert->close();
        }
    }
}
