<?php

class RecruitmentDataService
{
    private $conn;
    private $admin;

    private $departmentHeadStages = [
        'department_head_review',
        'interview_scheduled',
        'interview_completed',
        'demo_scheduled',
        'demo_completed',
        'psych_scheduled',
        'psych_completed',
        'initially_hired',
        'permanently_hired',
        'hired',
    ];

    public function __construct(mysqli $conn, array $admin)
    {
        $this->conn = $conn;
        $this->admin = $admin;
    }

    public function getApplicantMatchesByName($name, $limit = 5)
    {
        $scope = $this->applicationScope('ja');
        $search = '%' . strtolower($name) . '%';
        $limit = max(1, min((int) $limit, 10));

        $sql = "
            SELECT
                ja.id,
                ja.full_name,
                ja.position,
                ja.status,
                ja.workflow_stage,
                ja.assigned_to_department,
                ja.applied_date,
                ja.interview_date,
                ja.demo_date,
                ja.job_id,
                j.job_title,
                j.department_role
            FROM job_applicants ja
            LEFT JOIN job j ON j.id = ja.job_id
            WHERE {$scope['sql']}
              AND (LOWER(ja.full_name) LIKE ? OR LOWER(ja.applicant_email) LIKE ?)
            ORDER BY ja.applied_date DESC, ja.id DESC
            LIMIT ?";

        return $this->fetchAll($sql, $scope['types'] . 'ssi', array_merge($scope['params'], [$search, $search, $limit]));
    }

    public function getApplicantSummaryById($applicantId)
    {
        $scope = $this->applicationScope('ja');
        $sql = "
            SELECT
                ja.id,
                ja.full_name,
                ja.applicant_email,
                ja.contact_num,
                ja.position,
                ja.status,
                ja.workflow_stage,
                ja.assigned_to_department,
                ja.applied_date,
                ja.hired_date,
                ja.interview_date,
                ja.interview_location,
                ja.interview_room,
                ja.demo_date,
                ja.demo_location,
                ja.demo_room,
                ja.psych_exam_date,
                ja.secretary_notes,
                ja.interview_notes,
                ja.demo_notes,
                ja.psych_exam_notes,
                ja.initially_hired_date,
                ja.initially_hired_notes,
                ja.rejection_reason,
                ja.resubmission_documents,
                ja.resubmission_notes,
                ja.resume,
                ja.diploma,
                ja.transcript,
                ja.tor,
                ja.certificate,
                ja.id_picture,
                ja.letter_of_intent,
                ja.application_letter,
                ja.professional_license,
                ja.coe,
                ja.seminars_trainings,
                ja.masteral_cert,
                ja.psych_exam_receipt,
                ja.address AS application_address,
                ja.user_id,
                ja.job_id,
                j.job_title,
                j.department_role,
                j.job_type,
                j.locations,
                j.application_deadline,
                j.subject,
                a.first_name,
                a.last_name,
                a.contact_number,
                a.address AS applicant_address
            FROM job_applicants ja
            LEFT JOIN job j ON j.id = ja.job_id
            LEFT JOIN applicants a ON a.id = ja.user_id
            WHERE {$scope['sql']}
              AND ja.id = ?
            LIMIT 1";

        $row = $this->fetchOne($sql, $scope['types'] . 'i', array_merge($scope['params'], [(int) $applicantId]));
        if (!$row) {
            return null;
        }

        $userId = (int) ($row['user_id'] ?? 0);

        return [
            'applicant' => $this->sanitizeApplicantRecord($row),
            'education' => $userId > 0 ? $this->getEducation($userId) : [],
            'experience' => $userId > 0 ? $this->getExperience($userId) : [],
            'skills' => $userId > 0 ? $this->getSkills($userId) : [],
            'documents' => $this->getSubmittedDocuments($row),
        ];
    }

    public function getApplicantsByJob($jobQuery, $limit = 12)
    {
        $scope = $this->applicationScope('ja');
        $search = '%' . strtolower($jobQuery) . '%';
        $limit = max(1, min((int) $limit, 20));

        $sql = "
            SELECT
                ja.id,
                ja.full_name,
                ja.position,
                ja.status,
                ja.workflow_stage,
                ja.assigned_to_department,
                ja.applied_date,
                ja.interview_date,
                ja.demo_date,
                j.job_title,
                j.department_role,
                j.job_type
            FROM job_applicants ja
            LEFT JOIN job j ON j.id = ja.job_id
            WHERE {$scope['sql']}
              AND (
                LOWER(ja.position) LIKE ?
                OR LOWER(j.job_title) LIKE ?
                OR LOWER(j.department_role) LIKE ?
              )
            ORDER BY ja.applied_date DESC, ja.id DESC
            LIMIT ?";

        return $this->fetchAll($sql, $scope['types'] . 'sssi', array_merge($scope['params'], [$search, $search, $search, $limit]));
    }

    public function getApplicationCountByStatus($statusTerm)
    {
        $scope = $this->applicationScope('ja');
        $status = $this->statusCondition($statusTerm, 'ja');

        $sql = "SELECT COUNT(*) AS count FROM job_applicants ja WHERE {$scope['sql']} AND {$status['sql']}";
        $countRow = $this->fetchOne($sql, $scope['types'] . $status['types'], array_merge($scope['params'], $status['params']));

        $sampleSql = "
            SELECT id, full_name, position, status, workflow_stage, assigned_to_department, applied_date
            FROM job_applicants ja
            WHERE {$scope['sql']} AND {$status['sql']}
            ORDER BY applied_date DESC, id DESC
            LIMIT 5";
        $samples = $this->fetchAll($sampleSql, $scope['types'] . $status['types'], array_merge($scope['params'], $status['params']));

        return [
            'status_label' => $status['label'],
            'count' => (int) ($countRow['count'] ?? 0),
            'samples' => $samples,
        ];
    }

    public function getRecruitmentStatistics()
    {
        $scope = $this->applicationScope('ja');
        $total = $this->fetchOne("SELECT COUNT(*) AS count FROM job_applicants ja WHERE {$scope['sql']}", $scope['types'], $scope['params']);
        $byStatus = $this->fetchAll("
            SELECT status, COUNT(*) AS count
            FROM job_applicants ja
            WHERE {$scope['sql']}
            GROUP BY status
            ORDER BY count DESC, status ASC", $scope['types'], $scope['params']);
        $byWorkflow = $this->fetchAll("
            SELECT workflow_stage, COUNT(*) AS count
            FROM job_applicants ja
            WHERE {$scope['sql']}
            GROUP BY workflow_stage
            ORDER BY count DESC, workflow_stage ASC", $scope['types'], $scope['params']);

        return [
            'total_applications' => (int) ($total['count'] ?? 0),
            'by_status' => $byStatus,
            'by_workflow_stage' => $byWorkflow,
            'available_jobs' => $this->getAvailableJobPostings(8),
            'positions_with_applicants' => $this->getPositionsWithApplicants(8),
        ];
    }

    public function getAvailableJobPostings($limit = 10)
    {
        $limit = max(1, min((int) $limit, 20));
        $where = "WHERE j.application_deadline >= CURDATE()";
        $types = '';
        $params = [];

        if (($this->admin['role'] ?? '') === 'Department Head') {
            $department = $this->admin['department'] ?? '';
            if ($department === '') {
                return [];
            }
            $where .= " AND j.department_role IN (?, ?)";
            $types .= 'ss';
            $params[] = $department;
            $params[] = $this->departmentAlias($department);
        }

        $sql = "
            SELECT
                j.id,
                j.job_title,
                j.department_role,
                j.job_type,
                j.locations,
                j.application_deadline,
                COUNT(ja.id) AS application_count
            FROM job j
            LEFT JOIN job_applicants ja ON ja.job_id = j.id AND ja.workflow_stage != 'rejected'
            $where
            GROUP BY j.id, j.job_title, j.department_role, j.job_type, j.locations, j.application_deadline
            ORDER BY j.application_deadline ASC, j.id DESC
            LIMIT ?";

        return $this->fetchAll($sql, $types . 'i', array_merge($params, [$limit]));
    }

    public function getPositionsWithApplicants($limit = 10)
    {
        $scope = $this->applicationScope('ja');
        $limit = max(1, min((int) $limit, 20));

        $sql = "
            SELECT
                COALESCE(j.job_title, ja.position) AS position,
                COALESCE(j.department_role, ja.assigned_to_department) AS department,
                COUNT(*) AS application_count
            FROM job_applicants ja
            LEFT JOIN job j ON j.id = ja.job_id
            WHERE {$scope['sql']}
            GROUP BY COALESCE(j.job_title, ja.position), COALESCE(j.department_role, ja.assigned_to_department)
            ORDER BY application_count DESC, position ASC
            LIMIT ?";

        return $this->fetchAll($sql, $scope['types'] . 'i', array_merge($scope['params'], [$limit]));
    }

    private function getEducation($userId)
    {
        return $this->fetchAll("
            SELECT degree, field_of_study, institution, start_year, end_year, gpa
            FROM user_education
            WHERE user_id = ?
            ORDER BY end_year DESC
            LIMIT 5", 'i', [$userId]);
    }

    private function getExperience($userId)
    {
        $rows = $this->fetchAll("
            SELECT job_title, company, location, start_date, end_date, is_current, description
            FROM user_experience
            WHERE user_id = ?
            ORDER BY start_date DESC
            LIMIT 5", 'i', [$userId]);

        foreach ($rows as &$row) {
            if (isset($row['description'])) {
                $row['description'] = $this->truncate((string) $row['description'], 240);
            }
        }

        return $rows;
    }

    private function getSkills($userId)
    {
        return $this->fetchAll("
            SELECT skill_name, skill_category, skill_level
            FROM user_skills
            WHERE user_id = ?
            ORDER BY skill_category, skill_name
            LIMIT 25", 'i', [$userId]);
    }

    private function sanitizeApplicantRecord(array $row)
    {
        return [
            'id' => (int) $row['id'],
            'full_name' => $row['full_name'] ?? '',
            'position' => $row['position'] ?? '',
            'job_title' => $row['job_title'] ?? '',
            'department' => $row['department_role'] ?? ($row['assigned_to_department'] ?? ''),
            'assigned_to_department' => $row['assigned_to_department'] ?? '',
            'job_type' => $row['job_type'] ?? '',
            'subject' => $row['subject'] ?? '',
            'status' => $row['status'] ?? '',
            'workflow_stage' => $row['workflow_stage'] ?? '',
            'applied_date' => $row['applied_date'] ?? '',
            'application_deadline' => $row['application_deadline'] ?? '',
            'interview_date' => $row['interview_date'] ?? '',
            'interview_location' => $row['interview_location'] ?? '',
            'interview_room' => $row['interview_room'] ?? '',
            'demo_date' => $row['demo_date'] ?? '',
            'demo_location' => $row['demo_location'] ?? '',
            'demo_room' => $row['demo_room'] ?? '',
            'psych_exam_date' => $row['psych_exam_date'] ?? '',
            'hired_date' => $row['hired_date'] ?? '',
            'notes' => [
                'secretary' => $this->truncate((string) ($row['secretary_notes'] ?? ''), 240),
                'interview' => $this->truncate((string) ($row['interview_notes'] ?? ''), 240),
                'demo' => $this->truncate((string) ($row['demo_notes'] ?? ''), 240),
                'psych_exam' => $this->truncate((string) ($row['psych_exam_notes'] ?? ''), 240),
                'initial_hire' => $this->truncate((string) ($row['initially_hired_notes'] ?? ''), 240),
                'resubmission' => $this->truncate(trim(($row['resubmission_documents'] ?? '') . ' ' . ($row['resubmission_notes'] ?? '')), 240),
                'rejection' => $this->truncate((string) ($row['rejection_reason'] ?? ''), 240),
            ],
        ];
    }

    private function getSubmittedDocuments(array $row)
    {
        $columns = [
            'resume' => 'Resume',
            'diploma' => 'Diploma',
            'transcript' => 'Transcript',
            'tor' => 'TOR',
            'certificate' => 'Certificate',
            'id_picture' => 'ID picture',
            'letter_of_intent' => 'Letter of intent',
            'application_letter' => 'Application letter',
            'professional_license' => 'Professional license',
            'coe' => 'Certificate of employment',
            'seminars_trainings' => 'Seminars/trainings',
            'masteral_cert' => 'Masteral certificate',
            'psych_exam_receipt' => 'Psych exam receipt',
        ];

        $submitted = [];
        foreach ($columns as $column => $label) {
            if (!empty($row[$column])) {
                $submitted[] = $label;
            }
        }

        return $submitted;
    }

    private function applicationScope($alias)
    {
        $prefix = $alias ? $alias . '.' : '';
        $role = $this->admin['role'] ?? '';
        $department = $this->admin['department'] ?? '';

        if ($role === 'Secretary') {
            return [
                'sql' => "{$prefix}workflow_stage != ? AND ({$prefix}secretary_id IS NULL OR {$prefix}secretary_id = 0 OR {$prefix}workflow_stage = ? OR {$prefix}secretary_id = ?)",
                'types' => 'ssi',
                'params' => ['rejected', 'secretary_review', (int) ($this->admin['id'] ?? 0)],
            ];
        }

        if ($role === 'Department Head') {
            if ($department === '') {
                return ['sql' => '1 = 0', 'types' => '', 'params' => []];
            }

            $placeholders = "'" . implode("','", $this->departmentHeadStages) . "'";
            return [
                'sql' => "{$prefix}workflow_stage IN ($placeholders) AND {$prefix}assigned_to_department IN (?, ?)",
                'types' => 'ss',
                'params' => [$department, $this->departmentAlias($department)],
            ];
        }

        return ['sql' => '1 = 0', 'types' => '', 'params' => []];
    }

    private function statusCondition($statusTerm, $alias)
    {
        $prefix = $alias ? $alias . '.' : '';
        $statusTerm = strtolower(trim((string) $statusTerm));

        switch ($statusTerm) {
            case 'pending':
                return [
                    'label' => 'pending applications',
                    'sql' => "({$prefix}status = ? OR {$prefix}workflow_stage IN ('secretary_review','department_head_review'))",
                    'types' => 's',
                    'params' => ['Pending'],
                ];
            case 'approved':
                return [
                    'label' => 'approved applications',
                    'sql' => "({$prefix}workflow_stage IN ('secretary_approved','department_head_review','interview_scheduled','interview_completed','demo_scheduled','demo_completed','psych_scheduled','psych_completed','initially_hired','permanently_hired','hired') AND {$prefix}status != ?)",
                    'types' => 's',
                    'params' => ['Rejected'],
                ];
            case 'rejected':
                return [
                    'label' => 'rejected applications',
                    'sql' => "({$prefix}status = ? OR {$prefix}workflow_stage = ?)",
                    'types' => 'ss',
                    'params' => ['Rejected', 'rejected'],
                ];
            case 'hired':
                return [
                    'label' => 'hired applications',
                    'sql' => "({$prefix}status IN ('Initially Hired','Permanently Hired','Hired') OR {$prefix}workflow_stage IN ('initially_hired','permanently_hired','hired'))",
                    'types' => '',
                    'params' => [],
                ];
            case 'interview':
                return [
                    'label' => 'interview applications',
                    'sql' => "({$prefix}status = ? OR {$prefix}workflow_stage IN ('interview_scheduled','interview_completed'))",
                    'types' => 's',
                    'params' => ['Interview Scheduled'],
                ];
            case 'demo':
                return [
                    'label' => 'demo applications',
                    'sql' => "({$prefix}status = ? OR {$prefix}workflow_stage IN ('demo_scheduled','demo_completed'))",
                    'types' => 's',
                    'params' => ['Demo Scheduled'],
                ];
            case 'cancelled':
                return [
                    'label' => 'cancelled applications',
                    'sql' => "({$prefix}status = ? OR {$prefix}workflow_stage = ?)",
                    'types' => 'ss',
                    'params' => ['Cancelled', 'cancelled'],
                ];
            case 'resubmission':
                return [
                    'label' => 'resubmission applications',
                    'sql' => "{$prefix}status = ?",
                    'types' => 's',
                    'params' => ['Resubmission Required'],
                ];
            default:
                return [
                    'label' => 'visible applications',
                    'sql' => '1 = 1',
                    'types' => '',
                    'params' => [],
                ];
        }
    }

    private function departmentAlias($department)
    {
        if ($department === 'Computing Studies') {
            return 'Computer Science';
        }
        if ($department === 'Computer Science') {
            return 'Computing Studies';
        }
        return $department;
    }

    private function fetchAll($sql, $types = '', array $params = [])
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

    private function fetchOne($sql, $types = '', array $params = [])
    {
        $stmt = $this->prepareAndExecute($sql, $types, $params);
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row ? $this->sanitizeRow($row) : null;
    }

    private function prepareAndExecute($sql, $types = '', array $params = [])
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare recruitment data query.');
        }

        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to execute recruitment data query.');
        }

        return $stmt;
    }

    private function sanitizeRow(array $row)
    {
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $row[$key] = trim($value);
            }
        }
        return $row;
    }

    private function truncate($value, $maxLength)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (strlen($value) <= $maxLength) {
            return $value;
        }
        return substr($value, 0, $maxLength - 3) . '...';
    }
}
