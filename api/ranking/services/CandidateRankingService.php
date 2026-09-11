<?php

class CandidateRankingService
{
    private mysqli $conn;
    private CandidateScoringService $scorer;
    private ?CandidateRankingAiService $ai;

    public function __construct(mysqli $conn, CandidateScoringService $scorer, ?CandidateRankingAiService $ai = null)
    {
        $this->conn = $conn;
        $this->scorer = $scorer;
        $this->ai = $ai;
    }

    public function listForJob(int $jobId, array $admin, bool $force = false): array
    {
        $job = $this->loadJob($jobId);
        $this->assertJobVisible($job, $admin);
        $applications = $this->loadVisibleApplications($admin, $jobId);
        $rankings = [];
        foreach ($applications as $application) {
            $rankings[] = $this->calculate($job, $application, $force);
        }
        usort($rankings, static function (array $a, array $b): int {
            $left = $a['overall_score'] ?? -1;
            $right = $b['overall_score'] ?? -1;
            return $right <=> $left ?: strcmp((string)$a['applicant_name'], (string)$b['applicant_name']);
        });
        foreach ($rankings as $index => &$ranking) $ranking['rank'] = $index + 1;
        unset($ranking);

        return [
            'job' => $this->publicJob($job),
            'rankings' => $rankings,
            'count' => count($rankings),
            'decision_support_notice' => 'Rankings support review only. The authorized NCHire reviewer makes the final hiring decision.',
        ];
    }

    public function details(int $applicationId, array $admin, bool $requestAi = false): array
    {
        $application = $this->loadVisibleApplication($admin, $applicationId);
        $jobId = (int)($application['job_id'] ?? 0);
        if ($jobId <= 0) throw new RankingException('missing_job', 'This application is not linked to an available job posting.', 422);
        $job = $this->loadJob($jobId);
        $this->assertJobVisible($job, $admin);
        $ranking = $this->calculate($job, $application, false, true);
        $profile = $this->loadProfile((int)$application['user_id']);

        if ($requestAi) {
            $ranking['ai_analysis'] = $this->getOrCreateAiAnalysis($job, $profile, $application, $ranking);
        }

        return [
            'job' => $this->publicJob($job),
            'applicant' => [
                'application_id' => (int)$application['id'],
                'applicant_id' => (int)$application['user_id'],
                'name' => $application['full_name'],
                'application_status' => $application['status'],
                'applied_date' => $application['applied_date'],
            ],
            'profile' => $this->publicProfile($profile),
            'ranking' => $ranking,
            'decision_support_notice' => 'AI analysis never changes the score or automatically rejects an applicant.',
        ];
    }

    private function calculate(array $job, array $application, bool $force = false, bool $includeDetails = false): array
    {
        $applicantId = (int)($application['user_id'] ?? 0);
        $profile = $this->loadProfile($applicantId);
        $hash = $this->scorer->inputHash($job, $profile, $application);
        $stored = $this->loadStored((int)$application['id']);

        if (!$force && $stored && hash_equals((string)$stored['input_hash'], $hash)) {
            $score = json_decode((string)$stored['score_breakdown'], true);
            if (!is_array($score)) $score = $this->scorer->score($job, $profile, $application);
        } else {
            $score = $this->scorer->score($job, $profile, $application);
            $this->persistScore($application, $score, $hash, $stored);
            $stored = $this->loadStored((int)$application['id']);
        }

        $result = [
            'application_id' => (int)$application['id'],
            'applicant_id' => $applicantId,
            'job_id' => (int)$application['job_id'],
            'applicant_name' => $application['full_name'],
            'overall_score' => $score['overall_score'],
            'qualification_label' => $score['qualification_label'],
            'categories' => $score['categories'],
            'profile_completeness' => $score['profile_completeness'],
            'job_requirement_completeness' => $score['job_requirement_completeness'],
            'job_requirement_warnings' => $score['job_requirement_warnings'],
            'scoring_version' => $score['scoring_version'],
            'ai_status' => $stored['ai_status'] ?? 'not_requested',
            'calculated_at' => $stored['deterministic_updated_at'] ?? null,
        ];

        if ($includeDetails) {
            $result['matching_qualifications'] = $score['matching_qualifications'];
            $result['missing_requirements'] = $score['missing_requirements'];
            $result['requirements'] = $score['requirements'];
            $result['ai_analysis'] = $this->storedAi($stored, $hash);
        }
        return $result;
    }

    private function getOrCreateAiAnalysis(array $job, array $profile, array $application, array $ranking): array
    {
        $stored = $this->loadStored((int)$application['id']);
        $inputHash = (string)($stored['input_hash'] ?? '');
        $cached = $this->storedAi($stored, $inputHash);
        if (($cached['available'] ?? false) === true) return $cached;

        if ($this->ai === null) {
            return ['available' => false, 'status' => 'unavailable', 'message' => 'AI analysis is unavailable; deterministic ranking remains available.'];
        }

        try {
            $score = [
                'overall_score' => $ranking['overall_score'],
                'qualification_label' => $ranking['qualification_label'],
                'categories' => $ranking['categories'],
                'matching_qualifications' => $ranking['matching_qualifications'] ?? [],
                'missing_requirements' => $ranking['missing_requirements'] ?? [],
                'requirements' => $ranking['requirements'] ?? [],
            ];
            $analysis = $this->ai->analyze($job, $profile, $score);
            $this->persistAi((int)$application['id'], $inputHash, $analysis, 'complete');
            return ['available' => true, 'status' => 'complete'] + $analysis;
        } catch (GroqApiException $e) {
            $category = $e->getCategory();
            $this->persistAi((int)$application['id'], '', [], 'failed:' . substr($category, 0, 40));
            error_log('Candidate ranking AI unavailable: ' . $category);
            return [
                'available' => false,
                'status' => $category,
                'message' => $category === 'missing_api_key'
                    ? 'AI analysis is not configured. Deterministic ranking is shown.'
                    : 'AI analysis is temporarily unavailable. Deterministic ranking is shown.',
            ];
        } catch (Throwable $e) {
            $this->persistAi((int)$application['id'], '', [], 'failed:server_error');
            error_log('Candidate ranking AI error: ' . get_class($e));
            return ['available' => false, 'status' => 'server_error', 'message' => 'AI analysis is temporarily unavailable. Deterministic ranking is shown.'];
        }
    }

    private function loadJob(int $jobId): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM job WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $jobId);
        $stmt->execute();
        $job = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$job) throw new RankingException('job_not_found', 'Job posting was not found.', 404);
        return $job;
    }

    private function loadProfile(int $applicantId): array
    {
        if ($applicantId <= 0) return ['education' => [], 'experience' => [], 'skills' => [], 'qualifications' => []];
        return [
            'education' => $this->fetchAll('SELECT * FROM user_education WHERE user_id = ? ORDER BY start_year DESC, id DESC', $applicantId),
            'experience' => $this->fetchAll('SELECT * FROM user_experience WHERE user_id = ? ORDER BY start_date DESC, id DESC', $applicantId),
            'skills' => $this->fetchAll('SELECT * FROM user_skills WHERE user_id = ? ORDER BY skill_name, id', $applicantId),
            'qualifications' => $this->tableExists('user_qualifications')
                ? $this->fetchAll('SELECT * FROM user_qualifications WHERE user_id = ? ORDER BY issued_date DESC, id DESC', $applicantId)
                : [],
        ];
    }

    private function fetchAll(string $sql, int $id): array
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        $stmt->close();
        return $rows;
    }

    private function loadVisibleApplications(array $admin, int $jobId): array
    {
        [$where, $types, $params] = $this->visibilityClause($admin);
        $sql = 'SELECT ja.* FROM job_applicants ja WHERE ja.job_id = ? AND ' . $where . ' ORDER BY ja.applied_date DESC, ja.id DESC';
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) throw new RankingException('database_error', 'Unable to load applications.', 500);
        $bindTypes = 'i' . $types;
        $bindValues = array_merge([$jobId], $params);
        $stmt->bind_param($bindTypes, ...$bindValues);
        $stmt->execute();
        $rows = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        $stmt->close();
        return $rows;
    }

    private function loadVisibleApplication(array $admin, int $applicationId): array
    {
        [$where, $types, $params] = $this->visibilityClause($admin);
        $sql = 'SELECT ja.* FROM job_applicants ja WHERE ja.id = ? AND ' . $where . ' LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        $bindTypes = 'i' . $types;
        $bindValues = array_merge([$applicationId], $params);
        $stmt->bind_param($bindTypes, ...$bindValues);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) throw new RankingException('forbidden', 'Application was not found or is outside your authorized scope.', 403);
        return $row;
    }

    private function visibilityClause(array $admin): array
    {
        $role = (string)($admin['role'] ?? '');
        $department = $this->normalizeDepartment((string)($admin['department'] ?? ''));
        $alias = $department === 'Computing Studies' ? 'Computer Science' : ($department === 'Computer Science' ? 'Computing Studies' : $department);

        if ($role === 'Secretary') {
            return ["ja.workflow_stage != 'rejected' AND (ja.secretary_id IS NULL OR ja.secretary_id = 0 OR ja.workflow_stage = 'secretary_review' OR ja.secretary_id = ?)", 'i', [(int)$admin['id']]];
        }
        if ($role === 'Department Head' && $department !== '') {
            return ["ja.workflow_stage IN ('waiting_interview_schedule','department_head_review','interview_scheduled','interview_completed','demo_scheduled','demo_completed','psych_scheduled','psych_completed','initially_hired','permanently_hired','passed','hired') AND ja.assigned_to_department IN (?, ?)", 'ss', [$department, $alias]];
        }
        if (in_array($role, ['HR Manager', 'Recruiter'], true) && $department !== '') {
            return ["ja.status != 'Rejected' AND ja.assigned_to_department IN (?, ?)", 'ss', [$department, $alias]];
        }
        throw new RankingException('forbidden', 'Your role is not authorized to access candidate rankings.', 403);
    }

    private function assertJobVisible(array $job, array $admin): void
    {
        if (($admin['role'] ?? '') === 'Secretary') return;
        $department = $this->normalizeDepartment((string)($admin['department'] ?? ''));
        $jobDepartment = $this->normalizeDepartment((string)($job['department_role'] ?? ''));
        if ($department === '' || $jobDepartment !== $department) {
            throw new RankingException('forbidden', 'This job posting is outside your authorized department.', 403);
        }
    }

    private function normalizeDepartment(string $department): string
    {
        return $department === 'Computer Science' ? 'Computing Studies' : trim($department);
    }

    private function loadStored(int $applicationId): ?array
    {
        if (!$this->tableExists('candidate_rankings')) return null;
        $stmt = $this->conn->prepare('SELECT * FROM candidate_rankings WHERE application_id = ? LIMIT 1');
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $row;
    }

    private function persistScore(array $application, array $score, string $hash, ?array $stored): void
    {
        if (!$this->tableExists('candidate_rankings')) throw new RankingException('migration_required', 'Candidate ranking database migration has not been run.', 503);
        $applicationId = (int)$application['id'];
        $applicantId = (int)$application['user_id'];
        $jobId = (int)$application['job_id'];
        $overall = $score['overall_score'];
        $education = $score['categories']['education']['score'];
        $experience = $score['categories']['experience']['score'];
        $skills = $score['categories']['skills']['score'];
        $certifications = $score['categories']['certifications']['score'];
        $requirements = $score['categories']['requirements']['score'];
        $label = $score['qualification_label'];
        $breakdown = json_encode($score, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $matching = json_encode($score['matching_qualifications'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $missing = json_encode($score['missing_requirements'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $completeness = json_encode($score['profile_completeness'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $version = $score['scoring_version'];

        if ($stored) {
            $stmt = $this->conn->prepare("UPDATE candidate_rankings SET overall_score=?, education_score=?, experience_score=?, skills_score=?, certification_score=?, requirements_score=?, qualification_label=?, score_breakdown=?, matching_qualifications=?, missing_requirements=?, profile_completeness=?, input_hash=?, scoring_version=?, ai_status=IF(ai_input_hash IS NULL, 'not_requested', IF(ai_input_hash=?, ai_status, 'stale')), deterministic_updated_at=NOW(), updated_at=NOW() WHERE application_id=?");
            $stmt->bind_param('ddddddssssssssi', $overall, $education, $experience, $skills, $certifications, $requirements, $label, $breakdown, $matching, $missing, $completeness, $hash, $version, $hash, $applicationId);
        } else {
            $stmt = $this->conn->prepare("INSERT INTO candidate_rankings (application_id, applicant_id, job_posting_id, overall_score, education_score, experience_score, skills_score, certification_score, requirements_score, qualification_label, score_breakdown, matching_qualifications, missing_requirements, profile_completeness, input_hash, scoring_version, ai_status, deterministic_updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'not_requested', NOW())");
            $stmt->bind_param('iiiddddddsssssss', $applicationId, $applicantId, $jobId, $overall, $education, $experience, $skills, $certifications, $requirements, $label, $breakdown, $matching, $missing, $completeness, $hash, $version);
        }
        if (!$stmt || !$stmt->execute()) {
            $message = $stmt ? $stmt->error : $this->conn->error;
            if ($stmt) $stmt->close();
            error_log('Candidate ranking persistence error: ' . $message);
            throw new RankingException('database_error', 'Unable to save candidate ranking.', 500);
        }
        $stmt->close();
    }

    private function persistAi(int $applicationId, string $inputHash, array $analysis, string $status): void
    {
        $summary = $analysis['summary'] ?? null;
        $strengths = json_encode($analysis['strengths'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $missing = json_encode($analysis['missing_requirements'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $explanation = $analysis['job_match_explanation'] ?? null;
        $recommendation = $analysis['recommendation'] ?? null;
        $stmt = $this->conn->prepare('UPDATE candidate_rankings SET ai_summary=?, ai_strengths=?, ai_missing_requirements=?, ai_job_match_explanation=?, ai_recommendation=?, ai_status=?, ai_input_hash=?, ai_updated_at=NOW(), updated_at=NOW() WHERE application_id=?');
        if (!$stmt) return;
        $stmt->bind_param('sssssssi', $summary, $strengths, $missing, $explanation, $recommendation, $status, $inputHash, $applicationId);
        $stmt->execute();
        $stmt->close();
    }

    private function storedAi(?array $stored, string $inputHash): array
    {
        if (!$stored || ($stored['ai_status'] ?? '') !== 'complete' || !hash_equals((string)($stored['ai_input_hash'] ?? ''), $inputHash)) {
            return ['available' => false, 'status' => $stored['ai_status'] ?? 'not_requested'];
        }
        return [
            'available' => true,
            'status' => 'complete',
            'summary' => $stored['ai_summary'] ?? '',
            'strengths' => json_decode((string)($stored['ai_strengths'] ?? '[]'), true) ?: [],
            'missing_requirements' => json_decode((string)($stored['ai_missing_requirements'] ?? '[]'), true) ?: [],
            'job_match_explanation' => $stored['ai_job_match_explanation'] ?? '',
            'recommendation' => $stored['ai_recommendation'] ?? '',
        ];
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->conn->prepare('SELECT COUNT(*) count FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $exists = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0) > 0;
        $stmt->close();
        return $exists;
    }

    private function publicJob(array $job): array
    {
        $fields = ['id','job_title','department_role','subject_code','subject_name','subject','program','academic_year','semester','education','experience','training','eligibility','competency','job_requirements','minimum_education_level','required_degree_fields','graduate_requirement','minimum_graduate_units','minimum_experience_years','teaching_experience_requirement','required_skills','required_certifications','required_licenses','required_training','preferred_qualifications'];
        return array_intersect_key($job, array_flip($fields));
    }

    private function publicProfile(array $profile): array
    {
        return [
            'education' => array_map(static fn(array $row): array => array_intersect_key($row, array_flip(['education_level','degree','field_of_study','institution','education_status','completed_units','year_completed','start_year','end_year'])), $profile['education']),
            'experience' => array_map(static fn(array $row): array => array_intersect_key($row, array_flip(['job_title','company','location','start_date','end_date','description','is_current','experience_type'])), $profile['experience']),
            'skills' => array_map(static fn(array $row): array => array_intersect_key($row, array_flip(['skill_name','skill_category','skill_level'])), $profile['skills']),
            'qualifications' => array_map(static fn(array $row): array => array_intersect_key($row, array_flip(['qualification_type','title','issuing_organization','issued_date','expiry_date','proof_document','verification_status'])), $profile['qualifications']),
        ];
    }
}

class RankingException extends RuntimeException
{
    private string $category;
    private int $httpStatus;

    public function __construct(string $category, string $message, int $httpStatus = 400)
    {
        parent::__construct($message);
        $this->category = $category;
        $this->httpStatus = $httpStatus;
    }

    public function getCategory(): string { return $this->category; }
    public function getHttpStatus(): int { return $this->httpStatus; }
}
