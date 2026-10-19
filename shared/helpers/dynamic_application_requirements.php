<?php
/**
 * Teaching-load-specific application requirement helpers.
 *
 * The legacy job_applicants document columns remain supported so older
 * applications, document reuse, and admin review screens continue to work.
 * Custom requirements are stored only in applicant_requirement_submissions.
 */

if (!function_exists('nc_requirement_legacy_definitions')) {
    function nc_requirement_legacy_definitions(): array
    {
        return [
            'application_letter' => ['name' => 'Application Letter', 'description' => 'A signed letter introducing your application.', 'category' => 'Application Documents', 'required' => true, 'input' => 'applicationLetter', 'column' => 'application_letter'],
            'resume' => ['name' => 'Updated and Comprehensive Resume', 'description' => 'Your current resume or curriculum vitae.', 'category' => 'Application Documents', 'required' => true, 'input' => 'resume_file', 'column' => 'resume'],
            'letter_of_intent' => ['name' => 'Letter of Intent', 'description' => 'A letter describing your interest in the teaching load.', 'category' => 'Application Documents', 'required' => true, 'input' => 'letter_of_intent', 'column' => 'letter_of_intent'],
            'tor' => ['name' => 'Transcript of Records (TOR)', 'description' => 'A clear copy of your official academic transcript.', 'category' => 'Educational Documents', 'required' => true, 'input' => 'transcript', 'column' => 'tor'],
            'diploma' => ['name' => 'Diploma', 'description' => 'A clear copy of your highest relevant diploma.', 'category' => 'Educational Documents', 'required' => true, 'input' => 'diploma', 'column' => 'diploma'],
            'professional_license' => ['name' => 'Professional License', 'description' => 'A current professional or PRC license, when applicable.', 'category' => 'Supporting Documents', 'required' => false, 'input' => 'license', 'column' => 'professional_license'],
            'coe' => ['name' => 'Certificate of Employment (COE)', 'description' => 'Proof of relevant employment experience.', 'category' => 'Supporting Documents', 'required' => true, 'input' => 'coe', 'column' => 'coe'],
            'seminars_trainings' => ['name' => 'Seminars/Training Certificates', 'description' => 'Relevant seminar or training certificates.', 'category' => 'Supporting Documents', 'required' => true, 'input' => 'certificates[]', 'column' => 'seminars_trainings'],
            'masteral_cert' => ['name' => 'Masteral Certificate', 'description' => 'Supporting proof of a completed master\'s degree.', 'category' => 'Educational Documents', 'required' => false, 'input' => 'masteral_cert', 'column' => 'masteral_cert'],
            'certificate_of_grades' => ['name' => 'Certificate of Grades', 'description' => 'Supporting graduate-school grade record.', 'category' => 'Educational Documents', 'required' => false, 'input' => 'certificate_of_grades', 'column' => 'certificate_of_grades'],
            'proof_of_enrollment' => ['name' => 'Proof of Enrollment', 'description' => 'Supporting proof of current graduate-school enrollment.', 'category' => 'Educational Documents', 'required' => false, 'input' => 'proof_of_enrollment', 'column' => 'proof_of_enrollment'],
            'faculty_evaluation' => ['name' => 'Faculty Evaluation', 'description' => 'Optional supporting evaluation for renewal applicants.', 'category' => 'Supporting Documents', 'required' => false, 'input' => 'faculty_evaluation', 'column' => 'faculty_evaluation'],
        ];
    }
}

if (!function_exists('nc_requirement_tables_ready')) {
    function nc_requirement_tables_ready(mysqli $conn): bool
    {
        $requiredTables = ['teaching_load_requirements', 'applicant_requirement_submissions'];
        foreach ($requiredTables as $table) {
            $escaped = $conn->real_escape_string($table);
            $result = $conn->query("SHOW TABLES LIKE '{$escaped}'");
            if (!$result || $result->num_rows === 0) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('nc_requirement_category')) {
    function nc_requirement_category($category): string
    {
        $category = trim((string)$category);
        $allowed = ['Application Documents', 'Educational Documents', 'Supporting Documents'];
        return in_array($category, $allowed, true) ? $category : 'Supporting Documents';
    }
}

if (!function_exists('nc_validate_requirement_payload')) {
    function nc_validate_requirement_payload($payload): array
    {
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Application requirements must be an array.');
        }
        if (count($payload) > 100) {
            throw new InvalidArgumentException('A teaching load may have at most 100 application requirements.');
        }

        $clean = [];
        $seenIds = [];
        $seenKeys = [];
        $legacyDefinitions = nc_requirement_legacy_definitions();
        foreach ($payload as $index => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Each application requirement must be an object.');
            }
            $id = isset($row['id']) && $row['id'] !== '' ? (int)$row['id'] : null;
            if ($id !== null && $id <= 0) {
                throw new InvalidArgumentException('An application requirement has an invalid ID.');
            }
            if ($id !== null && isset($seenIds[$id])) {
                throw new InvalidArgumentException('The same application requirement was submitted more than once.');
            }
            if ($id !== null) {
                $seenIds[$id] = true;
            }
            $requirementKey = trim((string)($row['requirement_key'] ?? ''));
            if ($requirementKey !== '' && !isset($legacyDefinitions[$requirementKey])) {
                $requirementKey = '';
            }
            if ($requirementKey !== '' && isset($seenKeys[$requirementKey])) {
                throw new InvalidArgumentException('A predefined document type was submitted more than once.');
            }
            if ($requirementKey !== '') {
                $seenKeys[$requirementKey] = true;
            }

            $name = trim((string)($row['requirement_name'] ?? $row['name'] ?? ''));
            $description = trim((string)($row['description'] ?? ''));
            if ($name === '') {
                throw new InvalidArgumentException('Requirement ' . ($index + 1) . ' needs a name.');
            }
            if (mb_strlen($name) > 160 || mb_strlen($description) > 1000) {
                throw new InvalidArgumentException('A requirement name or description is too long.');
            }
            $requiredValue = $row['is_required'] ?? $row['required'] ?? false;
            $isRequired = filter_var($requiredValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isRequired === null) {
                $isRequired = (int)$requiredValue === 1;
            }
            $clean[] = [
                'id' => $id,
                'requirement_key' => $requirementKey ?: null,
                'requirement_name' => $name,
                'description' => $description,
                'category' => nc_requirement_category($row['category'] ?? ''),
                'is_required' => $isRequired ? 1 : 0,
                'display_order' => $index + 1,
            ];
        }
        return $clean;
    }
}

if (!function_exists('nc_get_teaching_load_requirements')) {
    function nc_get_teaching_load_requirements(mysqli $conn, int $jobId, bool $activeOnly = true): array
    {
        if ($jobId <= 0 || !nc_requirement_tables_ready($conn)) {
            return [];
        }
        $sql = 'SELECT id, teaching_load_id, requirement_key, requirement_name, description, category, is_required, display_order, is_active, created_at, updated_at
                FROM teaching_load_requirements WHERE teaching_load_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY display_order ASC, id ASC';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $jobId);
        $stmt->execute();
        $result = $stmt->get_result();
        $requirements = [];
        $legacy = nc_requirement_legacy_definitions();
        while ($row = $result->fetch_assoc()) {
            $key = (string)($row['requirement_key'] ?? '');
            $row['id'] = (int)$row['id'];
            $row['teaching_load_id'] = (int)$row['teaching_load_id'];
            $row['is_required'] = (bool)$row['is_required'];
            $row['is_active'] = (bool)$row['is_active'];
            $row['display_order'] = (int)$row['display_order'];
            $row['legacy_document_type'] = isset($legacy[$key]) ? $key : null;
            $row['input_name'] = isset($legacy[$key]) ? $legacy[$key]['input'] : 'requirement_file_' . $row['id'];
            $requirements[] = $row;
        }
        $stmt->close();
        return $requirements;
    }
}

if (!function_exists('nc_seed_default_teaching_load_requirements')) {
    function nc_seed_default_teaching_load_requirements(mysqli $conn, int $jobId): void
    {
        if ($jobId <= 0 || !nc_requirement_tables_ready($conn)) {
            return;
        }
        $stmt = $conn->prepare('INSERT IGNORE INTO teaching_load_requirements
            (teaching_load_id, requirement_key, requirement_name, description, category, is_required, display_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare default application requirements.');
        }
        $order = 1;
        foreach (nc_requirement_legacy_definitions() as $key => $definition) {
            $name = $definition['name'];
            $description = $definition['description'];
            $category = $definition['category'];
            $required = !empty($definition['required']) ? 1 : 0;
            $stmt->bind_param('issssii', $jobId, $key, $name, $description, $category, $required, $order);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Unable to seed application requirements.');
            }
            $order++;
        }
        $stmt->close();
    }
}

if (!function_exists('nc_requirements_change_summary')) {
    function nc_requirements_change_summary(array $existing, array $incoming): array
    {
        $existingById = [];
        foreach ($existing as $row) {
            if (!empty($row['is_active'])) {
                $existingById[(int)$row['id']] = $row;
            }
        }
        $incomingIds = [];
        $impactful = [];
        foreach ($incoming as $row) {
            if ($row['id'] === null) {
                if (!empty($row['is_required'])) {
                    $impactful[] = 'New required document: ' . $row['requirement_name'];
                }
                continue;
            }
            $incomingIds[$row['id']] = true;
            if (!isset($existingById[$row['id']])) {
                continue;
            }
            $before = $existingById[$row['id']];
            if (empty($before['is_required']) && !empty($row['is_required'])) {
                $impactful[] = $row['requirement_name'] . ' will become required';
            } elseif (!empty($before['is_required']) && empty($row['is_required'])) {
                $impactful[] = $row['requirement_name'] . ' will become optional';
            }
        }
        foreach ($existingById as $id => $row) {
            if (!isset($incomingIds[$id]) && !empty($row['is_required'])) {
                $impactful[] = 'Required document removed: ' . $row['requirement_name'];
            }
        }
        return $impactful;
    }
}

if (!function_exists('nc_sync_teaching_load_requirements')) {
    /**
     * Synchronize editable rows. Missing saved rows are soft-deactivated.
     * Returns newly-required requirement IDs for applicant notifications.
     */
    function nc_sync_teaching_load_requirements(mysqli $conn, int $jobId, array $payload): array
    {
        if (!nc_requirement_tables_ready($conn)) {
            throw new RuntimeException('Run the dynamic application requirements migration first.');
        }
        $requirements = nc_validate_requirement_payload($payload);
        $existing = nc_get_teaching_load_requirements($conn, $jobId, false);
        $existingById = [];
        foreach ($existing as $row) {
            $existingById[(int)$row['id']] = $row;
        }

        $keptIds = [];
        $newlyRequired = [];
        $update = $conn->prepare('UPDATE teaching_load_requirements
            SET requirement_name = ?, description = ?, category = ?, is_required = ?, display_order = ?, is_active = 1, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND teaching_load_id = ?');
        $insert = $conn->prepare('INSERT INTO teaching_load_requirements
            (teaching_load_id, requirement_key, requirement_name, description, category, is_required, display_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
        if (!$update || !$insert) {
            throw new RuntimeException('Unable to prepare application requirement changes.');
        }

        foreach ($requirements as $row) {
            if ($row['id'] !== null) {
                $id = (int)$row['id'];
                if (!isset($existingById[$id])) {
                    throw new InvalidArgumentException('An application requirement does not belong to this teaching load.');
                }
                $update->bind_param('sssiiii', $row['requirement_name'], $row['description'], $row['category'], $row['is_required'], $row['display_order'], $id, $jobId);
                if (!$update->execute()) {
                    throw new RuntimeException('Unable to update an application requirement.');
                }
                $keptIds[$id] = true;
                if (empty($existingById[$id]['is_required']) && !empty($row['is_required'])) {
                    $newlyRequired[] = $id;
                }
                continue;
            }

            $requirementKey = $row['requirement_key'] ?: ('custom_' . bin2hex(random_bytes(12)));
            $insert->bind_param('issssii', $jobId, $requirementKey, $row['requirement_name'], $row['description'], $row['category'], $row['is_required'], $row['display_order']);
            if (!$insert->execute()) {
                throw new RuntimeException('Unable to add an application requirement.');
            }
            $newId = (int)$conn->insert_id;
            $keptIds[$newId] = true;
            if (!empty($row['is_required'])) {
                $newlyRequired[] = $newId;
            }
        }
        $update->close();
        $insert->close();

        $deactivate = $conn->prepare('UPDATE teaching_load_requirements SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE teaching_load_id = ? AND id = ?');
        if (!$deactivate) {
            throw new RuntimeException('Unable to prepare removed application requirements.');
        }
        foreach ($existingById as $id => $row) {
            if (isset($keptIds[$id]) || empty($row['is_active'])) {
                continue;
            }
            $deactivate->bind_param('ii', $jobId, $id);
            if (!$deactivate->execute()) {
                throw new RuntimeException('Unable to deactivate a removed application requirement.');
            }
        }
        $deactivate->close();
        return array_values(array_unique($newlyRequired));
    }
}

if (!function_exists('nc_application_requirement_submission_map')) {
    function nc_application_requirement_submission_map(mysqli $conn, int $applicationId): array
    {
        if ($applicationId <= 0 || !nc_requirement_tables_ready($conn)) {
            return [];
        }
        $stmt = $conn->prepare('SELECT s.id, s.application_id, s.requirement_id, s.file_path, s.original_filename, s.storage_area, s.version_number, s.uploaded_at, s.updated_at
            FROM applicant_requirement_submissions s
            WHERE s.application_id = ? AND s.is_active = 1
            ORDER BY s.requirement_id, s.version_number DESC, s.id DESC');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        $result = $stmt->get_result();
        $map = [];
        while ($row = $result->fetch_assoc()) {
            $requirementId = (int)$row['requirement_id'];
            if (isset($map[$requirementId])) {
                continue;
            }
            $row['id'] = (int)$row['id'];
            $row['application_id'] = (int)$row['application_id'];
            $row['requirement_id'] = $requirementId;
            $row['version_number'] = (int)$row['version_number'];
            $map[$requirementId] = $row;
        }
        $stmt->close();
        return $map;
    }
}

if (!function_exists('nc_record_requirement_submission')) {
    function nc_record_requirement_submission(
        mysqli $conn,
        int $applicationId,
        int $requirementId,
        string $filePath,
        string $originalFilename,
        string $storageArea = 'requirements'
    ): int {
        if (!nc_requirement_tables_ready($conn) || $applicationId <= 0 || $requirementId <= 0 || trim($filePath) === '') {
            throw new InvalidArgumentException('Invalid application requirement submission.');
        }

        $versionStmt = $conn->prepare('SELECT COALESCE(MAX(version_number), 0) + 1 AS next_version
            FROM applicant_requirement_submissions WHERE application_id = ? AND requirement_id = ? FOR UPDATE');
        if (!$versionStmt) {
            throw new RuntimeException('Unable to prepare requirement version history.');
        }
        $versionStmt->bind_param('ii', $applicationId, $requirementId);
        $versionStmt->execute();
        $version = (int)($versionStmt->get_result()->fetch_assoc()['next_version'] ?? 1);
        $versionStmt->close();

        $deactivate = $conn->prepare('UPDATE applicant_requirement_submissions SET is_active = 0, replaced_at = NOW() WHERE application_id = ? AND requirement_id = ? AND is_active = 1');
        if (!$deactivate) {
            throw new RuntimeException('Unable to prepare the previous requirement submission.');
        }
        $deactivate->bind_param('ii', $applicationId, $requirementId);
        $deactivate->execute();
        $deactivate->close();

        $insert = $conn->prepare('INSERT INTO applicant_requirement_submissions
            (application_id, requirement_id, file_path, original_filename, storage_area, version_number, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)');
        if (!$insert) {
            throw new RuntimeException('Unable to prepare the requirement submission.');
        }
        $insert->bind_param('iisssi', $applicationId, $requirementId, $filePath, $originalFilename, $storageArea, $version);
        if (!$insert->execute()) {
            throw new RuntimeException('Unable to save the requirement submission.');
        }
        $id = (int)$conn->insert_id;
        $insert->close();
        return $id;
    }
}

if (!function_exists('nc_requirement_completion')) {
    function nc_requirement_completion(mysqli $conn, int $applicationId, int $jobId): array
    {
        $requirements = nc_get_teaching_load_requirements($conn, $jobId, true);
        $submissions = nc_application_requirement_submission_map($conn, $applicationId);
        $requiredTotal = 0;
        $requiredComplete = 0;
        $missing = [];
        foreach ($requirements as &$requirement) {
            $submission = $submissions[(int)$requirement['id']] ?? null;
            $requirement['submission'] = $submission;
            $requirement['completed'] = $submission !== null;
            if (!$requirement['is_required']) {
                continue;
            }
            $requiredTotal++;
            if ($submission) {
                $requiredComplete++;
            } else {
                $missing[] = $requirement;
            }
        }
        unset($requirement);
        return [
            'requirements' => $requirements,
            'required_total' => $requiredTotal,
            'required_complete' => $requiredComplete,
            'required_remaining' => count($missing),
            'is_complete' => count($missing) === 0,
            'missing' => $missing,
        ];
    }
}

if (!function_exists('nc_store_secure_requirement_upload')) {
    function nc_store_secure_requirement_upload(array $file, int $userId): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The selected file could not be uploaded.');
        }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > 5 * 1024 * 1024) {
            throw new RuntimeException('Each document must be no larger than 5 MB.');
        }
        $originalName = basename((string)($file['name'] ?? 'document'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('Only PDF, DOC, DOCX, JPG, and PNG files are allowed.');
        }
        $allowedMimes = [
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip', 'image/jpeg', 'image/png', 'application/octet-stream',
        ];
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        $mime = $finfo ? finfo_file($finfo, (string)$file['tmp_name']) : false;
        if ($finfo) {
            finfo_close($finfo);
        }
        if ($mime && !in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('The selected file content is not an allowed document type.');
        }

        $relativeDirectory = max(1, $userId);
        $baseDirectory = dirname(__DIR__, 2) . '/user/uploads/requirements';
        $targetDirectory = $baseDirectory . '/' . $relativeDirectory;
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0750, true)) {
            throw new RuntimeException('The secure upload directory is unavailable.');
        }
        $safeName = bin2hex(random_bytes(20)) . '.' . $extension;
        $relativePath = $relativeDirectory . '/' . $safeName;
        if (!move_uploaded_file((string)$file['tmp_name'], $targetDirectory . '/' . $safeName)) {
            throw new RuntimeException('The selected file could not be stored.');
        }
        return ['file_path' => $relativePath, 'original_filename' => $originalName, 'storage_area' => 'requirements'];
    }
}

if (!function_exists('nc_admin_can_manage_requirements')) {
    function nc_admin_can_manage_requirements(string $role, string $adminDepartment, string $jobDepartment): bool
    {
        if ($role === 'Secretary') {
            return true;
        }
        if ($role !== 'Department Head') {
            return false;
        }
        $normalize = static function (string $department): string {
            $department = trim($department);
            return $department === 'Computer Science' ? 'Computing Studies' : $department;
        };
        return $normalize($adminDepartment) !== '' && $normalize($adminDepartment) === $normalize($jobDepartment);
    }
}

if (!function_exists('nc_notify_new_required_documents')) {
    function nc_notify_new_required_documents(mysqli $conn, int $jobId, array $requirementIds): int
    {
        $requirementIds = array_values(array_unique(array_filter(array_map('intval', $requirementIds))));
        if (!$requirementIds) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($requirementIds), '?'));
        $types = 'i' . str_repeat('i', count($requirementIds));
        $params = array_merge([$jobId], $requirementIds);
        $sql = "SELECT GROUP_CONCAT(requirement_name ORDER BY display_order SEPARATOR ', ') AS names
                FROM teaching_load_requirements WHERE teaching_load_id = ? AND id IN ({$placeholders}) AND is_active = 1 AND is_required = 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $names = trim((string)($stmt->get_result()->fetch_assoc()['names'] ?? ''));
        $stmt->close();
        if ($names === '') {
            return 0;
        }

        $applications = $conn->prepare('SELECT DISTINCT user_id FROM job_applicants WHERE job_id = ? AND user_id IS NOT NULL');
        $insert = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, 'Application requirements updated', ?, 'warning', 0, NOW())");
        if (!$applications || !$insert) {
            return 0;
        }
        $applications->bind_param('i', $jobId);
        $applications->execute();
        $result = $applications->get_result();
        $count = 0;
        $message = 'New required document(s) were added to your teaching-load application: ' . $names . '. Your existing uploads and recruitment stage were preserved.';
        while ($row = $result->fetch_assoc()) {
            $userId = (int)$row['user_id'];
            $insert->bind_param('is', $userId, $message);
            if ($insert->execute()) {
                $count++;
            }
        }
        $applications->close();
        $insert->close();
        return $count;
    }
}

