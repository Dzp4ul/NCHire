<?php

require_once dirname(__DIR__, 3) . '/shared/helpers/dynamic_application_requirements.php';

/**
 * Read-only, role-scoped data access for the recruitment assistant.
 *
 * Every filter is converted to application-owned SQL. The language model
 * never supplies SQL, table names, column names, or ordering expressions.
 */
class RecruitmentDataService
{
    private const DEAN_STAGES = [
        'secretary_approved',
        'waiting_interview_schedule',
        'department_head_review',
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
        'cancelled',
    ];

    private $conn;
    private $admin;

    public function __construct(mysqli $conn, array $admin)
    {
        $this->conn = $conn;
        $this->admin = $admin;
    }

    public function getScopeSummary(): array
    {
        $role = (string)($this->admin['role'] ?? '');
        if ($role === 'Department Head') {
            return [
                'role' => 'Dean',
                'department' => $this->normalizeDepartment((string)($this->admin['department'] ?? '')),
                'record_access' => 'Department applications transferred into the Dean workflow, including that department archive.',
            ];
        }
        if ($role === 'Secretary') {
            return [
                'role' => 'Secretary',
                'department' => null,
                'record_access' => 'Secretary-visible applications across departments and the Secretary archive.',
            ];
        }
        if ($role === 'Admin') {
            return [
                'role' => 'Admin',
                'department' => null,
                'record_access' => 'System-wide dashboard statistics, teaching-load data, and basic application records. Detailed applicant qualifications are restricted to recruitment staff.',
            ];
        }
        return ['role' => $role, 'department' => null, 'record_access' => 'No recruitment record access.'];
    }

    public function searchApplications(array $filters): array
    {
        $scope = $this->applicationScope('ja');
        $where = [$scope['sql']];
        $types = $scope['types'];
        $params = $scope['params'];

        $status = strtolower((string)($filters['status'] ?? 'all'));
        $includeArchived = !empty($filters['include_archived']) || in_array($status, ['rejected', 'cancelled'], true);
        if (!$includeArchived) {
            $where[] = "COALESCE(ja.workflow_stage, '') NOT IN ('rejected','cancelled')";
        }

        if (!empty($filters['application_ids'])) {
            $ids = array_values(array_unique(array_map('intval', $filters['application_ids'])));
            $ids = array_values(array_filter($ids, static fn($id) => $id > 0));
            if ($ids) {
                $where[] = 'ja.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $types .= str_repeat('i', count($ids));
                array_push($params, ...$ids);
            }
        }

        $query = trim((string)($filters['query'] ?? ''));
        if ($query !== '') {
            $like = '%' . strtolower($query) . '%';
            $where[] = "(LOWER(COALESCE(NULLIF(TRIM(CONCAT_WS(' ', a.first_name, a.last_name)), ''), ja.full_name)) LIKE ?
                OR LOWER(COALESCE(j.job_title, ja.position)) LIKE ?
                OR LOWER(COALESCE(j.subject_name, j.subject, '')) LIKE ?
                OR LOWER(COALESCE(j.program, j.department_role, ja.assigned_to_department, '')) LIKE ?)";
            $types .= 'ssss';
            array_push($params, $like, $like, $like, $like);
        }

        $name = trim((string)($filters['applicant_name'] ?? ''));
        if ($name !== '') {
            $where[] = "LOWER(COALESCE(NULLIF(TRIM(CONCAT_WS(' ', a.first_name, a.last_name)), ''), ja.full_name)) LIKE ?";
            $types .= 's';
            $params[] = '%' . strtolower($name) . '%';
        }

        $jobQuery = trim((string)($filters['job_query'] ?? ''));
        if ($jobQuery !== '') {
            $like = '%' . strtolower($jobQuery) . '%';
            $where[] = "(LOWER(COALESCE(j.job_title, ja.position)) LIKE ? OR LOWER(COALESCE(j.subject_code, '')) LIKE ? OR LOWER(COALESCE(j.subject_name, j.subject, '')) LIKE ?)";
            $types .= 'sss';
            array_push($params, $like, $like, $like);
        }

        $department = trim((string)($filters['department'] ?? ''));
        if ($department !== '') {
            [$primary, $alias] = $this->departmentPair($department);
            $where[] = 'COALESCE(j.department_role, ja.assigned_to_department) IN (?, ?)';
            $types .= 'ss';
            array_push($params, $primary, $alias);
        }

        $statusCondition = $this->applicationStatusCondition($status, 'ja');
        if ($statusCondition['sql'] !== '1=1') {
            $where[] = $statusCondition['sql'];
            $types .= $statusCondition['types'];
            array_push($params, ...$statusCondition['params']);
        }

        foreach (['applied_from' => '>=', 'applied_to' => '<='] as $key => $operator) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value !== '') {
                $where[] = "ja.applied_date {$operator} ?";
                $types .= 's';
                $params[] = $key === 'applied_to' && strlen($value) === 10
                    ? $value . ' 23:59:59'
                    : $value;
            }
        }

        $requirementState = strtolower((string)($filters['requirement_state'] ?? 'any'));
        $requiredTotalSql = "(SELECT COUNT(*) FROM teaching_load_requirements tlr WHERE tlr.teaching_load_id = ja.job_id AND tlr.is_active = 1 AND tlr.is_required = 1)";
        $requiredCompleteSql = "(SELECT COUNT(DISTINCT ars.requirement_id)
            FROM applicant_requirement_submissions ars
            INNER JOIN teaching_load_requirements tlr2 ON tlr2.id = ars.requirement_id
            WHERE ars.application_id = ja.id AND ars.is_active = 1 AND tlr2.is_active = 1 AND tlr2.is_required = 1)";
        if ($requirementState === 'complete') {
            $where[] = "{$requiredCompleteSql} >= {$requiredTotalSql}";
        } elseif ($requirementState === 'missing') {
            $where[] = "{$requiredCompleteSql} < {$requiredTotalSql}";
        }

        $educationLevel = strtolower((string)($filters['education_level'] ?? 'any'));
        if ($educationLevel !== 'any' && $educationLevel !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM user_education ue WHERE ue.user_id = ja.user_id AND ue.education_level = ?)';
            $types .= 's';
            $params[] = $educationLevel;
        }
        $educationStatus = strtolower((string)($filters['education_status'] ?? 'any'));
        if ($educationStatus !== 'any' && $educationStatus !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM user_education ue2 WHERE ue2.user_id = ja.user_id AND ue2.education_status = ?)';
            $types .= 's';
            $params[] = $educationStatus;
        }
        if (isset($filters['minimum_master_units'])) {
            $where[] = "EXISTS (SELECT 1 FROM user_education ue3 WHERE ue3.user_id = ja.user_id AND ue3.education_level = 'master' AND COALESCE(ue3.completed_units, 0) >= ?)";
            $types .= 'i';
            $params[] = (int)$filters['minimum_master_units'];
        }
        if (array_key_exists('has_teaching_experience', $filters)) {
            $exists = 'EXISTS (SELECT 1 FROM user_experience ux WHERE ux.user_id = ja.user_id AND ux.experience_type = \'teaching\')';
            $where[] = !empty($filters['has_teaching_experience']) ? $exists : 'NOT ' . $exists;
        }

        $whereSql = implode(' AND ', $where);
        $identitySql = "COALESCE(NULLIF(CAST(ja.user_id AS CHAR), ''), NULLIF(LOWER(ja.applicant_email), ''), CONCAT('application:', ja.id))";
        $count = $this->fetchOne("SELECT COUNT(*) AS total_applications, COUNT(DISTINCT {$identitySql}) AS unique_applicants
            FROM job_applicants ja
            LEFT JOIN job j ON j.id = ja.job_id
            LEFT JOIN applicants a ON a.id = ja.user_id
            WHERE {$whereSql}", $types, $params);

        $limit = max(1, min(25, (int)($filters['limit'] ?? 10)));
        $page = max(1, min(1000, (int)($filters['page'] ?? 1)));
        $offset = ($page - 1) * $limit;
        $sql = "SELECT
                ja.id AS application_id,
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', a.first_name, a.last_name)), ''), ja.full_name) AS applicant_name,
                COALESCE(j.job_title, ja.position) AS position,
                j.subject_code,
                COALESCE(j.subject_name, j.subject) AS subject_name,
                COALESCE(j.program, j.department_role, ja.assigned_to_department) AS department,
                ja.status,
                ja.workflow_stage,
                ja.applied_date,
                ja.interview_date,
                ja.demo_date,
                ja.psych_exam_date,
                {$requiredTotalSql} AS required_documents,
                {$requiredCompleteSql} AS completed_required_documents
            FROM job_applicants ja
            LEFT JOIN job j ON j.id = ja.job_id
            LEFT JOIN applicants a ON a.id = ja.user_id
            WHERE {$whereSql}
            ORDER BY ja.applied_date DESC, ja.id DESC
            LIMIT ? OFFSET ?";
        $rows = $this->fetchAll($sql, $types . 'ii', array_merge($params, [$limit, $offset]));
        foreach ($rows as &$row) {
            $row['application_id'] = (int)$row['application_id'];
            $row['required_documents'] = (int)$row['required_documents'];
            $row['completed_required_documents'] = (int)$row['completed_required_documents'];
            $row['requirements_complete'] = $row['completed_required_documents'] >= $row['required_documents'];
        }
        unset($row);

        $total = (int)($count['total_applications'] ?? 0);
        return $this->withMetadata([
            'definition' => $statusCondition['label'],
            'total_applications' => $total,
            'unique_applicants' => (int)($count['unique_applicants'] ?? 0),
            'records' => $rows,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_pages' => max(1, (int)ceil($total / $limit)),
                'has_more' => $offset + count($rows) < $total,
            ],
            'filters_applied' => $this->publicFilters($filters),
        ]);
    }

    public function getApplicationDetails(array $arguments): array
    {
        $scope = $this->applicationScope('ja');
        $where = [$scope['sql']];
        $types = $scope['types'];
        $params = $scope['params'];

        $ids = $arguments['application_ids'] ?? [];
        if (isset($arguments['application_id'])) {
            array_unshift($ids, (int)$arguments['application_id']);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)$ids), static fn($id) => $id > 0)));
        if ($ids) {
            $where[] = 'ja.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $types .= str_repeat('i', count($ids));
            array_push($params, ...$ids);
        } else {
            $name = trim((string)($arguments['applicant_name'] ?? ''));
            if ($name === '') {
                return $this->withMetadata(['status' => 'needs_identifier', 'message' => 'Provide an application ID or applicant name.']);
            }
            $where[] = "LOWER(COALESCE(NULLIF(TRIM(CONCAT_WS(' ', a.first_name, a.last_name)), ''), ja.full_name)) LIKE ?";
            $types .= 's';
            $params[] = '%' . strtolower($name) . '%';
        }

        $sql = "SELECT
                ja.*,
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', a.first_name, a.last_name)), ''), ja.full_name) AS current_name,
                j.job_title,
                j.department_role,
                j.program,
                j.job_type,
                j.subject_code,
                j.subject_name,
                j.subject,
                j.academic_year AS job_academic_year,
                j.semester AS job_semester,
                j.application_deadline,
                j.status AS job_status
            FROM job_applicants ja
            LEFT JOIN job j ON j.id = ja.job_id
            LEFT JOIN applicants a ON a.id = ja.user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY ja.applied_date DESC, ja.id DESC
            LIMIT 10";
        $matches = $this->fetchAll($sql, $types, $params);
        if (!$matches) {
            return $this->withMetadata(['status' => 'not_found', 'message' => 'No matching application is available within the authenticated user\'s scope.']);
        }
        if (!$ids && count($matches) > 1) {
            return $this->withMetadata([
                'status' => 'ambiguous',
                'message' => 'Several accessible applications match that name. Ask the user to identify the intended application.',
                'candidates' => array_map(fn($row) => $this->basicApplication($row), $matches),
            ]);
        }

        $details = [];
        foreach ($matches as $row) {
            if (count($details) >= 10) {
                break;
            }
            $basic = $this->basicApplication($row);
            if (!$this->canViewDetailedApplicantData()) {
                $details[] = [
                    'access_level' => 'basic',
                    'application' => $basic,
                    'notice' => 'The Admin workspace exposes dashboard and basic application data, but detailed qualifications and submitted requirements are restricted to recruitment staff.',
                ];
                continue;
            }

            $userId = (int)($row['user_id'] ?? 0);
            $applicationId = (int)$row['id'];
            $jobId = (int)($row['job_id'] ?? 0);
            $education = $userId > 0 ? $this->fetchAll("SELECT education_level, degree, field_of_study, institution, education_status, completed_units, year_completed, start_year, end_year, gpa
                FROM user_education WHERE user_id = ? ORDER BY COALESCE(year_completed, end_year) DESC, id DESC LIMIT 20", 'i', [$userId]) : [];
            $experience = $userId > 0 ? $this->fetchAll("SELECT experience_type, job_title, company, location, start_date, end_date, is_current, description
                FROM user_experience WHERE user_id = ? ORDER BY start_date DESC, id DESC LIMIT 15", 'i', [$userId]) : [];
            foreach ($experience as &$item) {
                $item['description'] = $this->truncate((string)($item['description'] ?? ''), 300);
                $item['is_current'] = (bool)$item['is_current'];
            }
            unset($item);
            $skills = $userId > 0 ? $this->fetchAll("SELECT skill_name, skill_category, skill_level FROM user_skills WHERE user_id = ? ORDER BY skill_category, skill_name LIMIT 40", 'i', [$userId]) : [];
            $qualifications = $userId > 0 ? $this->fetchAll("SELECT qualification_type, title, issuing_organization, issued_date, expiry_date, verification_status
                FROM user_qualifications WHERE user_id = ? ORDER BY issued_date DESC, id DESC LIMIT 25", 'i', [$userId]) : [];

            $requirements = $jobId > 0 ? nc_requirement_completion($this->conn, $applicationId, $jobId) : [
                'requirements' => [], 'required_total' => 0, 'required_complete' => 0, 'required_remaining' => 0, 'is_complete' => false, 'missing' => [],
            ];
            $requirementRows = [];
            foreach (array_slice($requirements['requirements'], 0, 50) as $requirement) {
                $requirementRows[] = [
                    'name' => $requirement['requirement_name'],
                    'category' => $requirement['category'],
                    'required' => (bool)$requirement['is_required'],
                    'submitted' => !empty($requirement['completed']),
                    'uploaded_at' => $requirement['submission']['uploaded_at'] ?? null,
                ];
            }

            $history = $this->fetchAll("SELECT from_stage, to_stage, action_by_role, action_type, created_at
                FROM workflow_history WHERE application_id = ? ORDER BY created_at ASC, id ASC LIMIT 40", 'i', [$applicationId]);

            $details[] = [
                'access_level' => 'recruitment_detail',
                'application' => $basic,
                'education' => $education,
                'experience' => $experience,
                'skills' => $skills,
                'qualifications' => $qualifications,
                'requirements' => [
                    'required_total' => (int)$requirements['required_total'],
                    'required_complete' => (int)$requirements['required_complete'],
                    'required_remaining' => (int)$requirements['required_remaining'],
                    'is_complete' => (bool)$requirements['is_complete'],
                    'items' => $requirementRows,
                    'items_truncated' => count($requirements['requirements']) > count($requirementRows),
                ],
                'workflow_history' => $history,
            ];
        }

        return $this->withMetadata(['status' => 'ok', 'applications' => $details]);
    }

    public function searchTeachingLoads(array $filters): array
    {
        $where = [];
        $types = '';
        $params = [];
        $jobScope = $this->jobScope('j');
        $where[] = $jobScope['sql'];
        $types .= $jobScope['types'];
        array_push($params, ...$jobScope['params']);

        $query = trim((string)($filters['query'] ?? ''));
        if ($query !== '') {
            $like = '%' . strtolower($query) . '%';
            $where[] = "(LOWER(j.job_title) LIKE ? OR LOWER(COALESCE(j.subject_code, '')) LIKE ? OR LOWER(COALESCE(j.subject_name, j.subject, '')) LIKE ? OR LOWER(COALESCE(j.program, j.department_role, '')) LIKE ?)";
            $types .= 'ssss';
            array_push($params, $like, $like, $like, $like);
        }
        $department = trim((string)($filters['department'] ?? ''));
        if ($department !== '') {
            [$primary, $alias] = $this->departmentPair($department);
            $where[] = 'j.department_role IN (?, ?)';
            $types .= 'ss';
            array_push($params, $primary, $alias);
        }
        $status = strtolower((string)($filters['status'] ?? 'any'));
        if ($status === 'active') {
            $where[] = "j.status = 'Active'";
        } elseif ($status === 'closed') {
            $where[] = "j.status = 'Closed'";
        } elseif ($status === 'open') {
            $where[] = "j.status = 'Active' AND j.application_deadline >= ? AND COALESCE(j.available_sections, j.required_instructors, 0) > 0";
            $types .= 's';
            $params[] = (new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
        }
        foreach (['deadline_from' => '>=', 'deadline_to' => '<='] as $key => $operator) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value !== '') {
                $where[] = "j.application_deadline {$operator} ?";
                $types .= 's';
                $params[] = $value;
            }
        }
        if (isset($filters['minimum_available_sections'])) {
            $where[] = 'COALESCE(j.available_sections, j.required_instructors, 0) >= ?';
            $types .= 'i';
            $params[] = (int)$filters['minimum_available_sections'];
        }

        $applicationCountSql = '(SELECT COUNT(*) FROM job_applicants ja_count WHERE ja_count.job_id = j.id)';
        if (!empty($filters['no_applicants'])) {
            $where[] = "{$applicationCountSql} = 0";
        }

        $whereSql = implode(' AND ', $where);
        $count = $this->fetchOne("SELECT COUNT(*) AS total FROM job j WHERE {$whereSql}", $types, $params);
        $limit = max(1, min(25, (int)($filters['limit'] ?? 10)));
        $page = max(1, min(1000, (int)($filters['page'] ?? 1)));
        $offset = ($page - 1) * $limit;
        $sort = strtolower((string)($filters['sort'] ?? 'deadline'));
        $orderBy = $sort === 'most_applications'
            ? 'application_count DESC, j.application_deadline ASC, j.id DESC'
            : ($sort === 'newest_record' ? 'j.id DESC' : 'j.application_deadline ASC, j.id DESC');
        $sql = "SELECT j.id AS teaching_load_id, j.job_title, j.department_role, j.job_type, j.locations,
                j.status, j.subject_code, j.subject_name, j.subject, j.program, j.academic_year, j.semester,
                j.teaching_schedule, j.teaching_hours_per_week, j.load_units, j.lecture_units, j.laboratory_units,
                j.required_instructors, j.available_sections, j.salary_grade, j.application_deadline,
                j.job_description, j.job_requirements, j.minimum_education_level, j.required_degree_fields,
                j.graduate_requirement, j.minimum_graduate_units, j.minimum_experience_years,
                j.teaching_experience_requirement, j.required_skills, j.required_certifications,
                j.required_licenses, j.required_training, j.preferred_qualifications,
                {$applicationCountSql} AS application_count
            FROM job j
            WHERE {$whereSql}
            ORDER BY {$orderBy}
            LIMIT ? OFFSET ?";
        $rows = $this->fetchAll($sql, $types . 'ii', array_merge($params, [$limit, $offset]));
        $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
        foreach ($rows as &$row) {
            $row['teaching_load_id'] = (int)$row['teaching_load_id'];
            $row['application_count'] = (int)$row['application_count'];
            $row['available_sections'] = $row['available_sections'] === null ? null : (int)$row['available_sections'];
            $row['is_open'] = $row['status'] === 'Active'
                && $row['application_deadline'] >= $today
                && (int)($row['available_sections'] ?? $row['required_instructors'] ?? 0) > 0;
            $row['job_description'] = $this->truncate((string)$row['job_description'], 500);
            if (!empty($filters['include_requirements'])) {
                $requirements = nc_get_teaching_load_requirements($this->conn, $row['teaching_load_id'], true);
                $visibleRequirements = array_slice($requirements, 0, 50);
                $row['application_requirements'] = array_map(static fn($item) => [
                    'name' => $item['requirement_name'],
                    'category' => $item['category'],
                    'required' => (bool)$item['is_required'],
                ], $visibleRequirements);
                $row['requirements_truncated'] = count($requirements) > count($visibleRequirements);
            }
        }
        unset($row);

        $warnings = [];
        if ($sort === 'newest_record') {
            $warnings[] = 'The job table has no posting timestamp. Results are ordered by newest record ID, which is not proof of the posting date.';
        }
        $total = (int)($count['total'] ?? 0);
        return $this->withMetadata([
            'total_teaching_loads' => $total,
            'teaching_loads' => $rows,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_pages' => max(1, (int)ceil($total / $limit)),
                'has_more' => $offset + count($rows) < $total,
            ],
            'warnings' => $warnings,
            'open_definition' => 'Status is Active, deadline is today or later, and at least one available section remains.',
        ]);
    }

    public function getSchedules(array $filters): array
    {
        [$from, $to, $periodLabel] = $this->resolveSchedulePeriod($filters);
        $requestedType = strtolower((string)($filters['schedule_type'] ?? 'all'));
        $typesToQuery = $requestedType === 'all' ? ['interview', 'demo', 'psych_exam'] : [$requestedType];
        $outcome = strtolower((string)($filters['outcome'] ?? 'any'));
        $limit = max(1, min(30, (int)($filters['limit'] ?? 15)));
        $results = [];
        $total = 0;

        $definitions = [
            'interview' => ['date' => 'interview_date', 'location' => 'interview_location', 'room' => 'interview_room', 'scheduled' => 'interview_scheduled', 'completed' => ['interview_completed','demo_scheduled','demo_completed','psych_scheduled','psych_completed','initially_hired','permanently_hired','passed','hired']],
            'demo' => ['date' => 'demo_date', 'location' => 'demo_location', 'room' => 'demo_room', 'scheduled' => 'demo_scheduled', 'completed' => ['demo_completed','psych_scheduled','psych_completed','initially_hired','permanently_hired','passed','hired']],
            'psych_exam' => ['date' => 'psych_exam_date', 'location' => null, 'room' => null, 'scheduled' => 'psych_scheduled', 'completed' => ['psych_completed','initially_hired','permanently_hired','passed','hired']],
        ];

        foreach ($typesToQuery as $type) {
            if (!isset($definitions[$type])) {
                continue;
            }
            $definition = $definitions[$type];
            $scope = $this->applicationScope('ja');
            $where = [$scope['sql'], 'ja.' . $definition['date'] . ' IS NOT NULL'];
            $bindTypes = $scope['types'];
            $params = $scope['params'];
            if ($from !== null) {
                $where[] = 'ja.' . $definition['date'] . ' >= ?';
                $bindTypes .= 's';
                $params[] = $from;
            }
            if ($to !== null) {
                $where[] = 'ja.' . $definition['date'] . ' <= ?';
                $bindTypes .= 's';
                $params[] = $to;
            }
            if ($outcome === 'scheduled') {
                $where[] = 'ja.workflow_stage = ?';
                $bindTypes .= 's';
                $params[] = $definition['scheduled'];
            } elseif ($outcome === 'completed') {
                $where[] = 'ja.workflow_stage IN (' . implode(',', array_fill(0, count($definition['completed']), '?')) . ')';
                $bindTypes .= str_repeat('s', count($definition['completed']));
                array_push($params, ...$definition['completed']);
            } elseif ($outcome === 'awaiting_result') {
                $where[] = 'ja.workflow_stage = ? AND ja.' . $definition['date'] . ' < NOW()';
                $bindTypes .= 's';
                $params[] = $definition['scheduled'];
            } elseif ($outcome === 'upcoming') {
                $where[] = 'ja.' . $definition['date'] . ' >= ?';
                $bindTypes .= 's';
                $params[] = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
            }
            $whereSql = implode(' AND ', $where);
            $count = $this->fetchOne("SELECT COUNT(*) AS total FROM job_applicants ja WHERE {$whereSql}", $bindTypes, $params);
            $total += (int)($count['total'] ?? 0);
            $locationSelect = $definition['location'] ? 'ja.' . $definition['location'] : 'NULL';
            $roomSelect = $definition['room'] ? 'ja.' . $definition['room'] : 'NULL';
            $sql = "SELECT ja.id AS application_id,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(' ', a.first_name, a.last_name)), ''), ja.full_name) AS applicant_name,
                    COALESCE(j.job_title, ja.position) AS position,
                    COALESCE(j.program, j.department_role, ja.assigned_to_department) AS department,
                    ja.status, ja.workflow_stage, ja.{$definition['date']} AS scheduled_at,
                    {$locationSelect} AS location, {$roomSelect} AS room
                FROM job_applicants ja
                LEFT JOIN job j ON j.id = ja.job_id
                LEFT JOIN applicants a ON a.id = ja.user_id
                WHERE {$whereSql}
                ORDER BY ja.{$definition['date']} ASC, ja.id ASC
                LIMIT ?";
            $rows = $this->fetchAll($sql, $bindTypes . 'i', array_merge($params, [$limit]));
            foreach ($rows as $row) {
                $row['application_id'] = (int)$row['application_id'];
                $row['schedule_type'] = $type;
                $results[] = $row;
            }
        }

        usort($results, static fn($a, $b) => strcmp((string)$a['scheduled_at'], (string)$b['scheduled_at']));
        $results = array_slice($results, 0, $limit);
        return $this->withMetadata([
            'period' => ['label' => $periodLabel, 'from' => $from, 'to' => $to],
            'total_schedules' => $total,
            'schedules' => $results,
            'truncated' => $total > count($results),
        ]);
    }

    public function getRecruitmentOverview(array $filters): array
    {
        $scope = $this->applicationScope('ja');
        $where = [$scope['sql']];
        $types = $scope['types'];
        $params = $scope['params'];
        if (empty($filters['include_archived'])) {
            $where[] = "COALESCE(ja.workflow_stage, '') NOT IN ('rejected','cancelled')";
        }
        foreach (['applied_from' => '>=', 'applied_to' => '<='] as $key => $operator) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value !== '') {
                $where[] = "ja.applied_date {$operator} ?";
                $types .= 's';
                $params[] = $key === 'applied_to' && strlen($value) === 10
                    ? $value . ' 23:59:59'
                    : $value;
            }
        }
        $department = trim((string)($filters['department'] ?? ''));
        if ($department !== '') {
            [$primary, $alias] = $this->departmentPair($department);
            $where[] = 'COALESCE(j.department_role, ja.assigned_to_department) IN (?, ?)';
            $types .= 'ss';
            array_push($params, $primary, $alias);
        }
        $whereSql = implode(' AND ', $where);
        $identitySql = "COALESCE(NULLIF(CAST(ja.user_id AS CHAR), ''), NULLIF(LOWER(ja.applicant_email), ''), CONCAT('application:', ja.id))";
        $summary = $this->fetchOne("SELECT
                COUNT(*) AS total_applications,
                COUNT(DISTINCT {$identitySql}) AS unique_applicants,
                SUM(ja.workflow_stage = 'secretary_review') AS secretary_review,
                SUM(ja.workflow_stage = 'department_head_review') AS department_review,
                SUM(ja.workflow_stage = 'waiting_interview_schedule') AS waiting_interview_schedule,
                SUM(ja.workflow_stage = 'interview_scheduled') AS interview_scheduled,
                SUM(ja.workflow_stage = 'interview_completed') AS interview_completed,
                SUM(ja.workflow_stage = 'demo_scheduled') AS demo_scheduled,
                SUM(ja.workflow_stage = 'demo_completed') AS demo_completed,
                SUM(ja.workflow_stage = 'psych_scheduled') AS psych_scheduled,
                SUM(ja.workflow_stage = 'psych_completed') AS psych_completed,
                SUM(ja.workflow_stage IN ('passed','hired','permanently_hired') OR ja.status IN ('Passed','Application Passed','Hired','Permanently Hired')) AS passed,
                SUM(ja.workflow_stage = 'rejected' OR ja.status = 'Rejected') AS rejected,
                SUM(ja.workflow_stage = 'cancelled' OR ja.status = 'Cancelled') AS cancelled
            FROM job_applicants ja
            LEFT JOIN job j ON j.id = ja.job_id
            WHERE {$whereSql}", $types, $params) ?: [];
        foreach ($summary as $key => $value) {
            $summary[$key] = (int)($value ?? 0);
        }
        $summary['pending_review'] = ($summary['secretary_review'] ?? 0) + ($summary['department_review'] ?? 0);

        $byStatus = $this->fetchAll("SELECT COALESCE(NULLIF(ja.status, ''), 'Not recorded') AS label, COUNT(*) AS count
            FROM job_applicants ja LEFT JOIN job j ON j.id = ja.job_id WHERE {$whereSql}
            GROUP BY ja.status ORDER BY count DESC, label ASC", $types, $params);
        $byStage = $this->fetchAll("SELECT COALESCE(NULLIF(ja.workflow_stage, ''), 'not_recorded') AS label, COUNT(*) AS count
            FROM job_applicants ja LEFT JOIN job j ON j.id = ja.job_id WHERE {$whereSql}
            GROUP BY ja.workflow_stage ORDER BY count DESC, label ASC", $types, $params);
        $byDepartment = $this->fetchAll("SELECT COALESCE(j.program, j.department_role, ja.assigned_to_department, 'Not recorded') AS label, COUNT(*) AS count
            FROM job_applicants ja LEFT JOIN job j ON j.id = ja.job_id WHERE {$whereSql}
            GROUP BY label ORDER BY count DESC, label ASC", $types, $params);
        $topPositions = $this->fetchAll("SELECT COALESCE(j.job_title, ja.position, 'Not recorded') AS label, COUNT(*) AS count
            FROM job_applicants ja LEFT JOIN job j ON j.id = ja.job_id WHERE {$whereSql}
            GROUP BY label ORDER BY count DESC, label ASC LIMIT 10", $types, $params);

        $transitionWhere = [$scope['sql']];
        $transitionTypes = $scope['types'];
        $transitionParams = $scope['params'];
        foreach (['transition_from' => '>=', 'transition_to' => '<='] as $key => $operator) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value !== '') {
                $transitionWhere[] = "wh.created_at {$operator} ?";
                $transitionTypes .= 's';
                $transitionParams[] = $key === 'transition_to' && strlen($value) === 10 ? $value . ' 23:59:59' : $value;
            }
        }
        $transitions = $this->fetchAll("SELECT wh.from_stage, wh.to_stage, wh.action_type, COUNT(*) AS count
            FROM workflow_history wh
            INNER JOIN job_applicants ja ON ja.id = wh.application_id
            WHERE " . implode(' AND ', $transitionWhere) . "
            GROUP BY wh.from_stage, wh.to_stage, wh.action_type
            ORDER BY count DESC, wh.to_stage ASC LIMIT 20", $transitionTypes, $transitionParams);

        $nowManila = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
        $upcoming = $this->fetchOne("SELECT
                SUM(ja.interview_date >= ?) AS interviews,
                SUM(ja.demo_date >= ?) AS demonstrations,
                SUM(ja.psych_exam_date >= ?) AS psychological_exams
            FROM job_applicants ja WHERE {$scope['sql']}", 'sss' . $scope['types'], array_merge([$nowManila, $nowManila, $nowManila], $scope['params'])) ?: [];
        foreach ($upcoming as $key => $value) {
            $upcoming[$key] = (int)($value ?? 0);
        }

        return $this->withMetadata([
            'counts' => $summary,
            'by_status' => $byStatus,
            'by_workflow_stage' => $byStage,
            'by_department' => $byDepartment,
            'positions_by_application_count' => $topPositions,
            'workflow_transitions' => $transitions,
            'upcoming_schedules' => $upcoming,
            'definitions' => [
                'pending_review' => 'Applications currently in secretary_review or department_head_review.',
                'unique_applicants' => 'Distinct linked applicant account, falling back to email and then application ID.',
                'total_applications' => 'Application rows; one person may have more than one application.',
            ],
            'filters_applied' => $this->publicFilters($filters),
        ]);
    }

    public function compareQualifications(array $arguments): array
    {
        if (!$this->canViewDetailedApplicantData()) {
            return $this->withMetadata(['status' => 'forbidden', 'message' => 'Detailed qualification comparison is not available in the Admin workspace.']);
        }
        $jobs = $this->findTeachingLoadsForComparison($arguments);
        if (!$jobs) {
            return $this->withMetadata(['status' => 'job_not_found', 'message' => 'No accessible teaching load matched the supplied identifier.']);
        }
        if (count($jobs) > 1) {
            return $this->withMetadata([
                'status' => 'ambiguous_job',
                'message' => 'Several teaching loads matched. Ask the user to identify the intended teaching load.',
                'teaching_loads' => array_map(static fn($job) => [
                    'teaching_load_id' => (int)$job['id'],
                    'title' => $job['job_title'],
                    'subject_code' => $job['subject_code'],
                    'department' => $job['department_role'],
                ], $jobs),
            ]);
        }
        $job = $jobs[0];
        $detailArgs = [];
        if (!empty($arguments['application_ids'])) {
            $detailArgs['application_ids'] = $arguments['application_ids'];
        } elseif (!empty($arguments['applicant_name'])) {
            $detailArgs['applicant_name'] = $arguments['applicant_name'];
        } else {
            $scope = $this->applicationScope('ja');
            $rows = $this->fetchAll("SELECT ja.id FROM job_applicants ja WHERE {$scope['sql']} AND ja.job_id = ? ORDER BY ja.applied_date DESC LIMIT 10",
                $scope['types'] . 'i', array_merge($scope['params'], [(int)$job['id']]));
            $detailArgs['application_ids'] = array_map(static fn($row) => (int)$row['id'], $rows);
        }
        $detailsResult = $this->getApplicationDetails($detailArgs);
        if (($detailsResult['status'] ?? '') !== 'ok') {
            return $detailsResult;
        }
        $comparisons = [];
        foreach ($detailsResult['applications'] as $detail) {
            $comparisons[] = $this->evaluateQualificationEvidence($detail, $job);
        }
        return $this->withMetadata([
            'status' => 'ok',
            'teaching_load' => [
                'teaching_load_id' => (int)$job['id'],
                'title' => $job['job_title'],
                'department' => $job['department_role'],
                'minimum_education_level' => $job['minimum_education_level'],
                'graduate_requirement' => $job['graduate_requirement'],
                'minimum_graduate_units' => $job['minimum_graduate_units'],
                'minimum_experience_years' => $job['minimum_experience_years'],
                'teaching_experience_requirement' => $job['teaching_experience_requirement'],
                'required_skills' => $job['required_skills'],
                'required_certifications' => $job['required_certifications'],
                'required_licenses' => $job['required_licenses'],
                'required_training' => $job['required_training'],
            ],
            'comparisons' => $comparisons,
            'decision_notice' => 'This is an objective evidence comparison, not a ranking, score, hiring recommendation, or final eligibility decision. Unstructured requirements require staff review.',
        ]);
    }

    private function findTeachingLoadsForComparison(array $arguments): array
    {
        $scope = $this->jobScope('j');
        $where = [$scope['sql']];
        $types = $scope['types'];
        $params = $scope['params'];
        if (!empty($arguments['teaching_load_id'])) {
            $where[] = 'j.id = ?';
            $types .= 'i';
            $params[] = (int)$arguments['teaching_load_id'];
        } else {
            $query = trim((string)($arguments['teaching_load_query'] ?? ''));
            if ($query === '') {
                return [];
            }
            $like = '%' . strtolower($query) . '%';
            $where[] = "(LOWER(j.job_title) LIKE ? OR LOWER(COALESCE(j.subject_code, '')) LIKE ? OR LOWER(COALESCE(j.subject_name, j.subject, '')) LIKE ?)";
            $types .= 'sss';
            array_push($params, $like, $like, $like);
        }
        return $this->fetchAll('SELECT j.* FROM job j WHERE ' . implode(' AND ', $where) . ' ORDER BY j.id DESC LIMIT 10', $types, $params);
    }

    private function evaluateQualificationEvidence(array $detail, array $job): array
    {
        $education = $detail['education'] ?? [];
        $experience = $detail['experience'] ?? [];
        $levels = ['high_school' => 1, 'associate' => 2, 'bachelor' => 3, 'master' => 4, 'doctorate' => 5, 'other' => 0];
        $highest = 'not_recorded';
        $highestRank = -1;
        $completedMaster = false;
        $masterUnits = null;
        foreach ($education as $row) {
            $level = strtolower((string)($row['education_level'] ?? 'other'));
            $rank = $levels[$level] ?? 0;
            if ($rank > $highestRank) {
                $highestRank = $rank;
                $highest = $level;
            }
            if ($level === 'master' && ($row['education_status'] ?? '') === 'completed') {
                $completedMaster = true;
            }
            if ($level === 'master' && is_numeric($row['completed_units'] ?? null)) {
                $masterUnits = max((int)($masterUnits ?? 0), (int)$row['completed_units']);
            }
        }
        $teachingExperience = array_values(array_filter($experience, static fn($row) => ($row['experience_type'] ?? '') === 'teaching'));
        $recordedYears = 0.0;
        foreach ($experience as $row) {
            $start = strtotime((string)($row['start_date'] ?? ''));
            $end = !empty($row['end_date']) ? strtotime((string)$row['end_date']) : time();
            if ($start && $end && $end >= $start) {
                $recordedYears += ($end - $start) / 31557600;
            }
        }

        $checks = [];
        $minimumLevel = strtolower(trim((string)($job['minimum_education_level'] ?? '')));
        if ($minimumLevel !== '') {
            $checks[] = [
                'criterion' => 'minimum_education_level',
                'required' => $minimumLevel,
                'recorded' => $highest,
                'result' => $highestRank >= ($levels[$minimumLevel] ?? PHP_INT_MAX) ? 'supported' : 'not_supported_by_records',
            ];
        }
        $graduateRequirement = strtolower(trim((string)($job['graduate_requirement'] ?? '')));
        if (in_array($graduateRequirement, ['master_required', 'master_completed'], true)) {
            $checks[] = ['criterion' => 'completed_master_degree', 'required' => true, 'recorded' => $completedMaster, 'result' => $completedMaster ? 'supported' : 'not_supported_by_records'];
        }
        if (is_numeric($job['minimum_graduate_units'] ?? null) && (int)$job['minimum_graduate_units'] > 0) {
            $requiredUnits = (int)$job['minimum_graduate_units'];
            $checks[] = ['criterion' => 'minimum_master_units', 'required' => $requiredUnits, 'recorded' => $masterUnits, 'result' => $masterUnits !== null && $masterUnits >= $requiredUnits ? 'supported' : 'not_supported_by_records'];
        }
        if (is_numeric($job['minimum_experience_years'] ?? null) && (float)$job['minimum_experience_years'] > 0) {
            $requiredYears = (float)$job['minimum_experience_years'];
            $checks[] = ['criterion' => 'minimum_recorded_experience_years', 'required' => $requiredYears, 'recorded' => round($recordedYears, 2), 'result' => $recordedYears >= $requiredYears ? 'supported' : 'not_supported_by_records'];
        }
        if (strtolower((string)($job['teaching_experience_requirement'] ?? '')) === 'required') {
            $checks[] = ['criterion' => 'teaching_experience', 'required' => true, 'recorded_entries' => count($teachingExperience), 'result' => $teachingExperience ? 'supported' : 'not_supported_by_records'];
        }

        $hasUnstructured = false;
        foreach (['required_skills','required_certifications','required_licenses','required_training','required_degree_fields'] as $field) {
            if (trim((string)($job[$field] ?? '')) !== '') {
                $hasUnstructured = true;
                break;
            }
        }
        $allSupported = $checks !== [] && !array_filter($checks, static fn($check) => $check['result'] !== 'supported');
        return [
            'application' => $detail['application'],
            'recorded_evidence' => [
                'highest_education_level' => $highest,
                'completed_master_degree' => $completedMaster,
                'maximum_recorded_master_units' => $masterUnits,
                'recorded_experience_years_cumulative' => round($recordedYears, 2),
                'teaching_experience_entries' => count($teachingExperience),
                'skills' => array_column($detail['skills'] ?? [], 'skill_name'),
                'qualifications' => array_column($detail['qualifications'] ?? [], 'title'),
            ],
            'structured_criteria_checks' => $checks,
            'structured_criteria_supported' => $allSupported,
            'manual_review_required' => $hasUnstructured || !$allSupported || $checks === [],
        ];
    }

    private function basicApplication(array $row): array
    {
        return [
            'application_id' => (int)$row['id'],
            'applicant_name' => $row['current_name'] ?? $row['applicant_name'] ?? $row['full_name'] ?? '',
            'position' => $row['job_title'] ?? $row['position'] ?? '',
            'subject_code' => $row['subject_code'] ?? '',
            'subject_name' => $row['subject_name'] ?? $row['subject'] ?? '',
            'department' => $row['program'] ?? $row['department_role'] ?? $row['assigned_to_department'] ?? '',
            'status' => $row['status'] ?? '',
            'workflow_stage' => $row['workflow_stage'] ?? '',
            'applied_date' => $row['applied_date'] ?? null,
            'academic_year' => $row['academic_year'] ?? $row['job_academic_year'] ?? null,
            'semester' => $row['semester'] ?? $row['job_semester'] ?? null,
            'interview' => [
                'scheduled_at' => $row['interview_date'] ?? null,
                'location' => $row['interview_location'] ?? null,
                'room' => $row['interview_room'] ?? null,
            ],
            'teaching_demonstration' => [
                'scheduled_at' => $row['demo_date'] ?? null,
                'location' => $row['demo_location'] ?? null,
                'room' => $row['demo_room'] ?? null,
            ],
            'psychological_exam' => [
                'scheduled_at' => $row['psych_exam_date'] ?? null,
                'receipt_submitted' => !empty($row['psych_exam_receipt']),
            ],
            'hired_date' => $row['hired_date'] ?? null,
        ];
    }

    private function applicationScope(string $alias): array
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $role = (string)($this->admin['role'] ?? '');
        if ($role === 'Admin') {
            return ['sql' => '1=1', 'types' => '', 'params' => []];
        }
        if ($role === 'Secretary') {
            return [
                'sql' => "({$prefix}workflow_stage IN ('rejected','cancelled') OR ({$prefix}workflow_stage <> 'rejected' AND ({$prefix}secretary_id IS NULL OR {$prefix}secretary_id = 0 OR {$prefix}workflow_stage = 'secretary_review' OR {$prefix}secretary_id = ?)))",
                'types' => 'i',
                'params' => [(int)($this->admin['id'] ?? 0)],
            ];
        }
        if ($role === 'Department Head') {
            $department = $this->normalizeDepartment((string)($this->admin['department'] ?? ''));
            if ($department === '') {
                return ['sql' => '1=0', 'types' => '', 'params' => []];
            }
            [, $aliasDepartment] = $this->departmentPair($department);
            $stagePlaceholders = implode(',', array_fill(0, count(self::DEAN_STAGES), '?'));
            return [
                'sql' => "{$prefix}assigned_to_department IN (?, ?) AND {$prefix}workflow_stage IN ({$stagePlaceholders})",
                'types' => 'ss' . str_repeat('s', count(self::DEAN_STAGES)),
                'params' => array_merge([$department, $aliasDepartment], self::DEAN_STAGES),
            ];
        }
        return ['sql' => '1=0', 'types' => '', 'params' => []];
    }

    private function jobScope(string $alias): array
    {
        $role = (string)($this->admin['role'] ?? '');
        if ($role !== 'Department Head') {
            return ['sql' => '1=1', 'types' => '', 'params' => []];
        }
        $department = $this->normalizeDepartment((string)($this->admin['department'] ?? ''));
        if ($department === '') {
            return ['sql' => '1=0', 'types' => '', 'params' => []];
        }
        [, $departmentAlias] = $this->departmentPair($department);
        return [
            'sql' => $alias . '.department_role IN (?, ?)',
            'types' => 'ss',
            'params' => [$department, $departmentAlias],
        ];
    }

    private function applicationStatusCondition(string $status, string $alias): array
    {
        $prefix = $alias . '.';
        $map = [
            'all' => ['1=1', '', [], 'All accessible applications matching the supplied filters.'],
            'active' => ["{$prefix}workflow_stage NOT IN ('rejected','cancelled','passed','hired','permanently_hired')", '', [], 'Applications still in the active recruitment pipeline.'],
            'pending' => ["{$prefix}workflow_stage IN ('secretary_review','department_head_review') AND {$prefix}status <> 'Rejected'", '', [], 'Pending means applications currently in Secretary review or Department/Dean review.'],
            'secretary_review' => ["{$prefix}workflow_stage = ?", 's', ['secretary_review'], 'Applications in the verified secretary_review stage.'],
            'initial_screening' => ["{$prefix}workflow_stage = ?", 's', ['secretary_review'], 'NCHire does not store an initial_screening stage; this query maps initial screening to secretary_review.'],
            'department_review' => ["{$prefix}workflow_stage = ?", 's', ['department_head_review'], 'Applications in the verified department_head_review stage.'],
            'waiting_interview' => ["{$prefix}workflow_stage = ?", 's', ['waiting_interview_schedule'], 'Applications transferred to the Dean and waiting for interview scheduling.'],
            'interview_scheduled' => ["{$prefix}workflow_stage = ?", 's', ['interview_scheduled'], 'Applications with an interview scheduled and no recorded completion yet.'],
            'interview_completed' => ["{$prefix}workflow_stage IN ('interview_completed','demo_scheduled','demo_completed','psych_scheduled','psych_completed','initially_hired','permanently_hired','passed','hired')", '', [], 'Applications that completed/passed the interview and reached that or a later stage.'],
            'waiting_demo' => ["{$prefix}workflow_stage = ?", 's', ['interview_completed'], 'Applications that completed the interview and are waiting for a teaching demonstration schedule.'],
            'demo_scheduled' => ["{$prefix}workflow_stage = ?", 's', ['demo_scheduled'], 'Applications with a teaching demonstration scheduled.'],
            'demo_completed' => ["{$prefix}workflow_stage IN ('demo_completed','psych_scheduled','psych_completed','initially_hired','permanently_hired','passed','hired')", '', [], 'Applications that completed/passed the teaching demonstration and reached that or a later stage.'],
            'psych_scheduled' => ["{$prefix}workflow_stage = ?", 's', ['psych_scheduled'], 'Applications with a psychological examination scheduled.'],
            'passed' => ["({$prefix}workflow_stage IN ('passed','hired','permanently_hired') OR {$prefix}status IN ('Passed','Application Passed','Hired','Permanently Hired'))", '', [], 'Applications recorded as passed or in an equivalent final passed/hired stage.'],
            'accepted' => ["({$prefix}workflow_stage IN ('passed','hired','permanently_hired') OR {$prefix}status IN ('Passed','Application Passed','Hired','Permanently Hired'))", '', [], 'Accepted is mapped to NCHire passed/hired terminal records.'],
            'rejected' => ["({$prefix}workflow_stage = 'rejected' OR {$prefix}status = 'Rejected')", '', [], 'Rejected applications in the archive.'],
            'cancelled' => ["({$prefix}workflow_stage = 'cancelled' OR {$prefix}status = 'Cancelled')", '', [], 'Applicant-cancelled applications in the archive.'],
            'resubmission' => ["{$prefix}status = ?", 's', ['Resubmission Required'], 'Applications whose recorded status is Resubmission Required.'],
        ];
        $entry = $map[$status] ?? $map['all'];
        return ['sql' => $entry[0], 'types' => $entry[1], 'params' => $entry[2], 'label' => $entry[3]];
    }

    private function resolveSchedulePeriod(array $filters): array
    {
        $period = strtolower((string)($filters['period'] ?? 'upcoming'));
        $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
        switch ($period) {
            case 'today':
                return [$today->format('Y-m-d 00:00:00'), $today->format('Y-m-d 23:59:59'), 'today'];
            case 'tomorrow':
                $day = $today->modify('+1 day');
                return [$day->format('Y-m-d 00:00:00'), $day->format('Y-m-d 23:59:59'), 'tomorrow'];
            case 'this_week':
                $start = $today->modify('monday this week');
                return [$start->format('Y-m-d 00:00:00'), $start->modify('+6 days')->format('Y-m-d 23:59:59'), 'this week'];
            case 'next_week':
                $start = $today->modify('monday next week');
                return [$start->format('Y-m-d 00:00:00'), $start->modify('+6 days')->format('Y-m-d 23:59:59'), 'next week'];
            case 'custom':
                $from = trim((string)($filters['date_from'] ?? ''));
                $to = trim((string)($filters['date_to'] ?? ''));
                return [$from !== '' ? $from . (strlen($from) === 10 ? ' 00:00:00' : '') : null, $to !== '' ? $to . (strlen($to) === 10 ? ' 23:59:59' : '') : null, 'custom range'];
            case 'all':
                return [null, null, 'all recorded dates'];
            case 'upcoming':
            default:
                return [(new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s'), null, 'upcoming'];
        }
    }

    private function canViewDetailedApplicantData(): bool
    {
        return in_array((string)($this->admin['role'] ?? ''), ['Secretary', 'Department Head'], true);
    }

    private function normalizeDepartment(string $department): string
    {
        $department = trim($department);
        return $department === 'Computer Science' ? 'Computing Studies' : $department;
    }

    private function departmentPair(string $department): array
    {
        $primary = $this->normalizeDepartment($department);
        $alias = $primary === 'Computing Studies' ? 'Computer Science' : $primary;
        return [$primary, $alias];
    }

    private function publicFilters(array $filters): array
    {
        $safe = $filters;
        unset($safe['application_ids']);
        return $safe;
    }

    private function withMetadata(array $result): array
    {
        $result['retrieved_at'] = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format(DateTimeInterface::ATOM);
        $result['authorization_scope'] = $this->getScopeSummary();
        $result['source'] = 'NCHire database (live read-only query)';
        return $result;
    }

    private function fetchAll(string $sql, string $types = '', array $params = []): array
    {
        $stmt = $this->prepareAndExecute($sql, $types, $params);
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $this->sanitizeRow($row);
        }
        $stmt->close();
        return $rows;
    }

    private function fetchOne(string $sql, string $types = '', array $params = []): ?array
    {
        $stmt = $this->prepareAndExecute($sql, $types, $params);
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? $this->sanitizeRow($row) : null;
    }

    private function prepareAndExecute(string $sql, string $types, array $params): mysqli_stmt
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('The requested NCHire data could not be prepared.');
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('The requested NCHire data could not be retrieved.');
        }
        return $stmt;
    }

    private function sanitizeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $row[$key] = trim($value);
            }
        }
        return $row;
    }

    private function truncate(string $value, int $maxLength): string
    {
        $value = trim($value);
        if (strlen($value) <= $maxLength) {
            return $value;
        }
        return substr($value, 0, max(0, $maxLength - 3)) . '...';
    }
}
