<?php

class CandidateScoringService
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? require __DIR__ . '/../../../config/candidate_ranking.php';
    }

    public function getVersion(): string
    {
        return (string)$this->config['scoring_version'];
    }

    public function score(array $job, array $profile, array $application = []): array
    {
        $requirements = $this->resolveRequirements($job);
        $education = array_values($profile['education'] ?? []);
        $experience = array_values($profile['experience'] ?? []);
        $skills = array_values($profile['skills'] ?? []);
        $qualifications = array_values($profile['qualifications'] ?? []);

        $categories = [
            'education' => $this->scoreEducation($education, $requirements),
            'experience' => $this->scoreExperience($experience, $requirements),
            'skills' => $this->scoreSkills($skills, $requirements),
            'certifications' => $this->scoreCertifications($qualifications, $requirements),
            'requirements' => $this->scoreOtherRequirements($education, $experience, $skills, $qualifications, $application, $requirements),
        ];

        $earned = 0.0;
        $possible = 0.0;
        foreach ($categories as $key => &$category) {
            $category['maximum'] = (float)$this->config['weights'][$key];
            if ($category['applicable']) {
                $earned += (float)$category['score'];
                $possible += (float)$category['maximum'];
            }
        }
        unset($category);

        $overall = $possible > 0 ? round(($earned / $possible) * 100, 2) : null;
        $label = $overall === null ? 'Insufficient Job Requirements' : $this->labelFor($overall);

        $matches = [];
        $missing = [];
        foreach ($categories as $category) {
            foreach ($category['criteria'] as $criterion) {
                if (($criterion['status'] ?? '') === 'met') {
                    $matches[] = $criterion['explanation'];
                } elseif (in_array(($criterion['status'] ?? ''), ['missing', 'not_met', 'partial'], true)) {
                    $missing[] = $criterion['explanation'];
                }
            }
        }

        $profileCompleteness = [
            'education' => $this->educationDataStatus($education),
            'experience' => $this->experienceDataStatus($experience),
            'skills' => $this->skillDataStatus($skills),
            'certifications_licenses_training' => $this->qualificationDataStatus($qualifications),
        ];
        foreach (['education', 'experience', 'skills'] as $categoryName) {
            if ($categories[$categoryName]['applicable']) {
                $categories[$categoryName]['data_status'] = $profileCompleteness[$categoryName];
            }
        }
        if ($categories['certifications']['applicable']) {
            $categories['certifications']['data_status'] = $profileCompleteness['certifications_licenses_training'];
        }

        return [
            'overall_score' => $overall,
            'qualification_label' => $label,
            'categories' => $categories,
            'matching_qualifications' => array_values(array_unique($matches)),
            'missing_requirements' => array_values(array_unique($missing)),
            'profile_completeness' => $profileCompleteness,
            'job_requirement_completeness' => $requirements['completeness'],
            'job_requirement_warnings' => $requirements['warnings'],
            'requirements' => $this->publicRequirements($requirements),
            'scoring_version' => $this->getVersion(),
        ];
    }

    public function inputHash(array $job, array $profile, array $application = []): string
    {
        $documentFields = [
            'resume', 'tor', 'diploma', 'professional_license', 'coe',
            'seminars_trainings', 'masteral_cert', 'certificate_of_grades',
            'proof_of_enrollment',
        ];
        $documents = [];
        foreach ($documentFields as $field) {
            $documents[$field] = !empty($application[$field]);
        }

        $payload = [
            'version' => $this->getVersion(),
            'date' => date('Y-m-d'),
            'job' => $job,
            'profile' => $profile,
            'documents' => $documents,
        ];
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function scoreEducation(array $education, array $requirements): array
    {
        $criteria = [];
        $levelRanks = $this->config['education_levels'];
        $minimum = $requirements['minimum_education_level'];

        if ($minimum !== null && isset($levelRanks[$minimum])) {
            $requiredRank = (int)$levelRanks[$minimum];
            $highestRank = -1;
            $highestLabel = null;
            foreach ($education as $record) {
                if (($record['education_status'] ?? 'completed') !== 'completed') {
                    continue;
                }
                $level = $this->educationLevel($record);
                $rank = (int)($levelRanks[$level] ?? 0);
                if ($rank > $highestRank) {
                    $highestRank = $rank;
                    $highestLabel = $level;
                }
            }
            $ratio = $highestRank >= $requiredRank ? 1.0 : ($highestRank === $requiredRank - 1 ? 0.4 : 0.0);
            $criteria[] = $this->criterion(
                'Minimum education level',
                10,
                10 * $ratio,
                empty($education) ? 'missing' : ($ratio >= 1 ? 'met' : 'not_met'),
                empty($education)
                    ? 'Highest educational attainment is not provided.'
                    : 'Highest completed education is ' . $this->humanize($highestLabel ?? 'other') . '; the job requires ' . $this->humanize($minimum) . '.'
            );
        }

        $degreeTargets = $requirements['required_degree_fields'];
        $degreeTargetSource = 'required';
        if (empty($degreeTargets)) {
            $degreeTargets = $requirements['context_fields'];
            $degreeTargetSource = 'position context';
        }
        if (!empty($degreeTargets)) {
            $best = 0.0;
            $bestCandidate = '';
            $bestTarget = '';
            foreach ($education as $record) {
                $candidate = trim(($record['degree'] ?? '') . ' ' . ($record['field_of_study'] ?? ''));
                foreach ($degreeTargets as $target) {
                    $match = $this->phraseMatch($target, $candidate);
                    if ($match > $best) {
                        $best = $match;
                        $bestCandidate = $candidate;
                        $bestTarget = $target;
                    }
                }
            }
            $criteria[] = $this->criterion(
                'Degree/course relevance',
                10,
                10 * $best,
                empty($education) ? 'missing' : ($best >= 0.75 ? 'met' : ($best > 0 ? 'partial' : 'not_met')),
                empty($education)
                    ? 'Bachelor/graduate degree or course information is not provided.'
                    : ($best > 0
                        ? 'Recorded degree/field "' . $bestCandidate . '" was compared with ' . $degreeTargetSource . ' "' . $bestTarget . '".'
                        : 'No recorded degree/course matches the job\'s ' . $degreeTargetSource . '.')
            );
        }

        $graduateRequirement = $requirements['graduate_requirement'];
        $minimumUnits = $requirements['minimum_graduate_units'];
        if ($graduateRequirement !== 'none' || $minimumUnits !== null) {
            $best = 0.0;
            $bestText = '';
            foreach ($education as $record) {
                $level = $this->educationLevel($record);
                $status = strtolower((string)($record['education_status'] ?? 'completed'));
                $unitsProvided = isset($record['completed_units']) && $record['completed_units'] !== '' && is_numeric($record['completed_units']);
                $units = $unitsProvided ? max(0, (int)$record['completed_units']) : 0;
                $levelRank = (int)($levelRanks[$level] ?? 0);
                $requiredGraduateRank = $graduateRequirement === 'doctorate_required' ? 5 : 4;

                if ($graduateRequirement === 'preferred') {
                    if ($levelRank >= 4 && $status === 'completed') {
                        $candidate = 1.0;
                    } elseif ($levelRank >= 4 && $status === 'ongoing') {
                        $candidate = $minimumUnits !== null
                            ? ($unitsProvided ? min(0.7, 0.7 * ($units / max(1, $minimumUnits))) : 0.0)
                            : 0.6;
                    } else {
                        $candidate = 0.0;
                    }
                } elseif (in_array($graduateRequirement, ['master_required', 'doctorate_required'], true)) {
                    if ($levelRank >= $requiredGraduateRank && $status === 'completed') {
                        $candidate = 1.0;
                    } elseif ($levelRank >= $requiredGraduateRank && $status === 'ongoing') {
                        $candidate = $minimumUnits !== null
                            ? ($unitsProvided ? min(0.7, 0.7 * ($units / max(1, $minimumUnits))) : 0.0)
                            : 0.5;
                    } else {
                        $candidate = 0.0;
                    }
                } else {
                    $candidate = $minimumUnits !== null && $unitsProvided ? min(1.0, $units / max(1, $minimumUnits)) : 0.0;
                }
                if ($candidate > $best) {
                    $best = $candidate;
                    $bestText = $this->humanize($level) . ' (' . $status . ($status === 'ongoing' ? ($unitsProvided ? ', ' . $units . ' units' : ', units not provided') : '') . ')';
                }
            }
            $criteria[] = $this->criterion(
                'Graduate qualification',
                5,
                5 * $best,
                empty($education) ? 'missing' : ($best >= 1 ? 'met' : ($best > 0 ? 'partial' : 'not_met')),
                empty($education)
                    ? 'Master\'s/Doctorate status and graduate units are not provided.'
                    : ($bestText !== '' ? 'Best recorded graduate qualification: ' . $bestText . '.' : 'The required or preferred graduate qualification is not recorded.')
            );
        }

        return $this->categoryResult('education', $criteria, !empty($education));
    }

    private function scoreExperience(array $experience, array $requirements): array
    {
        $criteria = [];
        $minimumYears = $requirements['minimum_experience_years'];
        $totalYears = $this->experienceYears($experience);

        if ($minimumYears !== null && $minimumYears > 0) {
            $ratio = min(1.0, $totalYears / $minimumYears);
            $criteria[] = $this->criterion(
                'Minimum years of experience',
                12,
                12 * $ratio,
                empty($experience) ? 'missing' : ($ratio >= 1 ? 'met' : ($ratio > 0 ? 'partial' : 'not_met')),
                empty($experience)
                    ? 'Years of experience cannot be calculated because work experience is not provided.'
                    : number_format($totalYears, 1) . ' years are recorded; ' . number_format($minimumYears, 1) . ' years are required.'
            );
        }

        if (!empty($requirements['context_fields'])) {
            $best = 0.0;
            $bestExperience = '';
            foreach ($experience as $record) {
                $candidate = trim(($record['job_title'] ?? '') . ' ' . ($record['description'] ?? ''));
                foreach ($requirements['context_fields'] as $target) {
                    $match = $this->phraseMatch($target, $candidate);
                    if ($match > $best) {
                        $best = $match;
                        $bestExperience = (string)($record['job_title'] ?? 'recorded experience');
                    }
                }
            }
            $criteria[] = $this->criterion(
                'Job-related experience',
                8,
                8 * $best,
                empty($experience) ? 'missing' : ($best >= 0.75 ? 'met' : ($best > 0 ? 'partial' : 'not_met')),
                empty($experience)
                    ? 'Job-related experience is not provided.'
                    : ($best > 0 ? 'Most relevant recorded role: ' . $bestExperience . '.' : 'Recorded experience does not match the position context.')
            );
        }

        $teachingRequirement = $requirements['teaching_experience_requirement'];
        if ($teachingRequirement !== 'not_required') {
            $teachingRecords = array_filter($experience, fn(array $record): bool => $this->isTeachingExperience($record));
            $teachingYears = $this->experienceYears(array_values($teachingRecords));
            $hasTeaching = count($teachingRecords) > 0;
            $scoreRatio = $hasTeaching ? 1.0 : 0.0;
            $criteria[] = $this->criterion(
                'Teaching experience',
                5,
                5 * $scoreRatio,
                empty($experience) ? 'missing' : ($hasTeaching ? 'met' : 'not_met'),
                empty($experience)
                    ? 'Teaching experience is not provided.'
                    : ($hasTeaching ? number_format($teachingYears, 1) . ' years of teaching-related experience are recorded.' : 'No experience is identified as teaching-related.')
            );
        }

        return $this->categoryResult('experience', $criteria, !empty($experience));
    }

    private function scoreSkills(array $skills, array $requirements): array
    {
        $required = $requirements['required_skills'];
        if (empty($required)) {
            return $this->categoryResult('skills', [], !empty($skills));
        }

        $earned = 0.0;
        $explanations = [];
        foreach ($required as $requirement) {
            $best = 0.0;
            $bestName = '';
            foreach ($skills as $skill) {
                $match = $this->phraseMatch($requirement, (string)($skill['skill_name'] ?? ''));
                $level = max(1, min(5, (int)($skill['skill_level'] ?? 1)));
                $match *= 0.70 + ($level * 0.06);
                if ($match > $best) {
                    $best = $match;
                    $bestName = (string)($skill['skill_name'] ?? '');
                }
            }
            $earned += min(1.0, $best);
            $explanations[] = $best > 0
                ? $requirement . ' matched by ' . $bestName
                : $requirement . ' is not recorded';
        }
        $ratio = $earned / count($required);
        $criterion = $this->criterion(
            'Required skills',
            20,
            20 * $ratio,
            empty($skills) ? 'missing' : ($ratio >= 0.95 ? 'met' : ($ratio > 0 ? 'partial' : 'not_met')),
            implode('; ', $explanations) . '.'
        );
        return $this->categoryResult('skills', [$criterion], !empty($skills));
    }

    private function scoreCertifications(array $qualifications, array $requirements): array
    {
        $requiredGroups = [
            'certification' => $requirements['required_certifications'],
            'license' => $requirements['required_licenses'],
        ];
        $criteria = [];

        foreach ($requiredGroups as $type => $required) {
            if (empty($required)) {
                continue;
            }
            $matches = 0.0;
            $notes = [];
            foreach ($required as $requirement) {
                $best = 0.0;
                $bestTitle = '';
                foreach ($qualifications as $qualification) {
                    if (($qualification['qualification_type'] ?? '') !== $type || $this->isExpired($qualification)) {
                        continue;
                    }
                    $match = $this->phraseMatch($requirement, (string)($qualification['title'] ?? ''));
                    if ($match > $best) {
                        $best = $match;
                        $bestTitle = (string)($qualification['title'] ?? '');
                    }
                }
                $matches += $best;
                $notes[] = $best > 0 ? $requirement . ' matched by ' . $bestTitle : $requirement . ' is not recorded';
            }
            $ratio = $matches / count($required);
            $criteria[] = $this->criterion(
                ucfirst($type) . ' requirements',
                count($required),
                $matches,
                empty($qualifications) ? 'missing' : ($ratio >= 0.95 ? 'met' : ($ratio > 0 ? 'partial' : 'not_met')),
                implode('; ', $notes) . '.'
            );
        }

        return $this->categoryResult('certifications', $criteria, !empty($qualifications));
    }

    private function scoreOtherRequirements(array $education, array $experience, array $skills, array $qualifications, array $application, array $requirements): array
    {
        $items = array_merge($requirements['required_training'], $requirements['preferred_qualifications'], $requirements['other_requirements']);
        if (empty($items)) {
            return $this->categoryResult('requirements', [], !empty($education) || !empty($experience) || !empty($skills) || !empty($qualifications));
        }

        $evidence = [];
        foreach ($education as $row) {
            $evidence[] = trim(($row['degree'] ?? '') . ' ' . ($row['field_of_study'] ?? '') . ' ' . ($row['education_level'] ?? '') . ' ' . ($row['education_status'] ?? ''));
        }
        foreach ($experience as $row) {
            $evidence[] = trim(($row['job_title'] ?? '') . ' ' . ($row['description'] ?? '') . ' ' . ($row['experience_type'] ?? ''));
        }
        foreach ($skills as $row) {
            $evidence[] = (string)($row['skill_name'] ?? '');
        }
        foreach ($qualifications as $row) {
            if (!$this->isExpired($row)) {
                $evidence[] = trim(($row['title'] ?? '') . ' ' . ($row['qualification_type'] ?? '') . ' ' . ($row['issuing_organization'] ?? ''));
            }
        }
        $documentLabels = [
            'resume' => 'resume curriculum vitae',
            'tor' => 'transcript of records',
            'diploma' => 'diploma',
            'professional_license' => 'professional license document',
            'coe' => 'certificate of employment proof of employment',
            'seminars_trainings' => 'seminar training certificate documents',
            'masteral_cert' => 'master certificate document',
            'certificate_of_grades' => 'certificate of grades graduate units',
            'proof_of_enrollment' => 'proof of enrollment graduate studies',
        ];
        foreach ($documentLabels as $field => $label) {
            if (!empty($application[$field])) {
                $evidence[] = $label;
            }
        }

        $matches = 0.0;
        $notes = [];
        foreach ($items as $requirement) {
            $best = 0.0;
            $bestEvidence = '';
            foreach ($evidence as $candidate) {
                $match = $this->phraseMatch($requirement, $candidate);
                if ($match > $best) {
                    $best = $match;
                    $bestEvidence = $candidate;
                }
            }
            $matches += $best;
            $notes[] = $best > 0 ? $requirement . ' matched by recorded profile/application information' : $requirement . ' is not evidenced';
        }
        $ratio = $matches / count($items);
        $criterion = $this->criterion(
            'Other job requirements',
            count($items),
            $matches,
            empty($evidence) ? 'missing' : ($ratio >= 0.95 ? 'met' : ($ratio > 0 ? 'partial' : 'not_met')),
            implode('; ', $notes) . '.'
        );
        return $this->categoryResult('requirements', [$criterion], !empty($evidence));
    }

    private function categoryResult(string $key, array $criteria, bool $hasApplicantData): array
    {
        $maximum = (float)$this->config['weights'][$key];
        if (empty($criteria)) {
            return [
                'score' => null,
                'maximum' => $maximum,
                'applicable' => false,
                'data_status' => 'Not applicable',
                'criteria' => [],
            ];
        }

        $earned = array_sum(array_column($criteria, 'score'));
        $criterionMax = array_sum(array_column($criteria, 'maximum'));
        $score = $criterionMax > 0 ? round(($earned / $criterionMax) * $maximum, 2) : 0.0;
        return [
            'score' => $score,
            'maximum' => $maximum,
            'applicable' => true,
            'data_status' => $hasApplicantData ? 'Complete' : 'Not provided',
            'criteria' => $criteria,
        ];
    }

    private function criterion(string $name, float $maximum, float $score, string $status, string $explanation): array
    {
        return [
            'name' => $name,
            'score' => round(max(0, min($maximum, $score)), 2),
            'maximum' => $maximum,
            'status' => $status,
            'explanation' => $explanation,
        ];
    }

    private function educationDataStatus(array $records): string
    {
        if (empty($records)) return 'Not provided';
        foreach ($records as $record) {
            $level = $this->educationLevel($record);
            $status = strtolower(trim((string)($record['education_status'] ?? '')));
            if (trim((string)($record['degree'] ?? '')) === '' || trim((string)($record['field_of_study'] ?? '')) === '' ||
                trim((string)($record['institution'] ?? '')) === '' || !in_array($status, ['completed', 'ongoing'], true)) {
                return 'Information incomplete';
            }
            if ($status === 'ongoing' && in_array($level, ['master', 'doctorate'], true) && !is_numeric($record['completed_units'] ?? null)) {
                return 'Information incomplete';
            }
        }
        return 'Complete';
    }

    private function experienceDataStatus(array $records): string
    {
        if (empty($records)) return 'Not provided';
        foreach ($records as $record) {
            if (trim((string)($record['job_title'] ?? '')) === '' || trim((string)($record['start_date'] ?? '')) === '' ||
                (empty($record['is_current']) && trim((string)($record['end_date'] ?? '')) === '')) {
                return 'Information incomplete';
            }
        }
        return 'Complete';
    }

    private function skillDataStatus(array $records): string
    {
        if (empty($records)) return 'Not provided';
        foreach ($records as $record) {
            if (trim((string)($record['skill_name'] ?? '')) === '' || !is_numeric($record['skill_level'] ?? null)) return 'Information incomplete';
        }
        return 'Complete';
    }

    private function qualificationDataStatus(array $records): string
    {
        if (empty($records)) return 'Not provided';
        foreach ($records as $record) {
            if (trim((string)($record['title'] ?? '')) === '' || !in_array(($record['qualification_type'] ?? ''), ['certification', 'license', 'training'], true)) {
                return 'Information incomplete';
            }
        }
        return 'Complete';
    }

    private function resolveRequirements(array $job): array
    {
        $warnings = [];
        $structuredKeys = [
            'minimum_education_level', 'required_degree_fields', 'graduate_requirement',
            'minimum_graduate_units', 'minimum_experience_years',
            'teaching_experience_requirement', 'required_skills',
            'required_certifications', 'required_licenses', 'required_training',
            'preferred_qualifications',
        ];
        $structuredCount = 0;
        foreach ($structuredKeys as $key) {
            if (isset($job[$key]) && trim((string)$job[$key]) !== '' && !in_array((string)$job[$key], ['none', 'not_required'], true)) {
                $structuredCount++;
            }
        }

        $legacyText = trim(implode("\n", array_filter([
            $job['education'] ?? '', $job['experience'] ?? '', $job['training'] ?? '',
            $job['eligibility'] ?? '', $job['competency'] ?? '', $job['job_requirements'] ?? '',
        ])));
        if ($structuredCount === 0) {
            $warnings[] = $legacyText === ''
                ? 'This posting has no explicit structured ranking requirements; only position context can be compared.'
                : 'This older posting uses free-text requirements; deterministic legacy parsing was applied conservatively.';
        }

        $minimumEducation = $this->nullableString($job['minimum_education_level'] ?? null);
        if ($minimumEducation === null) {
            $minimumEducation = $this->inferEducationLevel((string)($job['education'] ?? ''));
        }

        $graduateRequirement = $this->nullableString($job['graduate_requirement'] ?? null) ?? 'none';
        if ($graduateRequirement === 'none') {
            $educationText = strtolower((string)($job['education'] ?? ''));
            if (preg_match('/doctor(?:ate|al)?|ph\.?d/', $educationText)) {
                $graduateRequirement = str_contains($educationText, 'required') ? 'doctorate_required' : 'preferred';
            } elseif (preg_match('/master(?:s|al)?/', $educationText)) {
                $graduateRequirement = str_contains($educationText, 'required') ? 'master_required' : 'preferred';
            }
        }

        $minimumUnits = $this->nullableNumber($job['minimum_graduate_units'] ?? null);
        if ($minimumUnits === null && preg_match('/(\d+)\s*(?:graduate\s*)?units?/i', (string)($job['education'] ?? ''), $matches)) {
            $minimumUnits = (float)$matches[1];
        }

        $minimumYears = $this->nullableNumber($job['minimum_experience_years'] ?? null);
        $experienceText = (string)($job['experience'] ?? '');
        if ($minimumYears === null && !preg_match('/no\s+experience\s+required/i', $experienceText) && preg_match('/(\d+(?:\.\d+)?)\s*(?:\+\s*)?years?/i', $experienceText, $matches)) {
            $minimumYears = (float)$matches[1];
        }

        $teachingRequirement = $this->nullableString($job['teaching_experience_requirement'] ?? null) ?? 'not_required';
        if ($teachingRequirement === 'not_required') {
            if (preg_match('/teach(?:er|ing)?\s+experience.*required|teaching\s+experience\s+required/i', $experienceText)) {
                $teachingRequirement = 'required';
            } elseif (preg_match('/teach(?:er|ing)?\s+experience.*preferred|instructor/i', $experienceText . ' ' . ($job['job_title'] ?? ''))) {
                $teachingRequirement = 'preferred';
            }
        }

        $contextFields = $this->cleanList([
            $job['subject_name'] ?? '', $job['subject'] ?? '', $job['program'] ?? '',
            $job['department_role'] ?? '', $job['job_title'] ?? '',
        ]);

        $requiredSkills = $this->parseList($job['required_skills'] ?? '');
        if (empty($requiredSkills)) {
            $requiredSkills = $this->parseLegacyList((string)($job['competency'] ?? ''));
        }
        $requiredCertifications = $this->parseList($job['required_certifications'] ?? '');
        $requiredLicenses = $this->parseList($job['required_licenses'] ?? '');
        if (empty($requiredCertifications) && empty($requiredLicenses)) {
            foreach ($this->parseLegacyList((string)($job['eligibility'] ?? '')) as $item) {
                if (preg_match('/licen[cs]|eligib|board|let\b/i', $item)) {
                    $requiredLicenses[] = $item;
                } elseif (preg_match('/certif/i', $item)) {
                    $requiredCertifications[] = $item;
                }
            }
        }

        return [
            'minimum_education_level' => $minimumEducation,
            'required_degree_fields' => $this->parseList($job['required_degree_fields'] ?? ''),
            'graduate_requirement' => $graduateRequirement,
            'minimum_graduate_units' => $minimumUnits,
            'minimum_experience_years' => $minimumYears,
            'teaching_experience_requirement' => $teachingRequirement,
            'required_skills' => $requiredSkills,
            'required_certifications' => array_values(array_unique($requiredCertifications)),
            'required_licenses' => array_values(array_unique($requiredLicenses)),
            'required_training' => $this->parseList($job['required_training'] ?? '') ?: $this->parseLegacyList((string)($job['training'] ?? '')),
            'preferred_qualifications' => $this->parseList($job['preferred_qualifications'] ?? ''),
            'other_requirements' => $this->parseLegacyList((string)($job['job_requirements'] ?? '')),
            'context_fields' => $contextFields,
            'completeness' => $structuredCount >= 4 ? 'Complete' : ($structuredCount > 0 || $legacyText !== '' ? 'Partial' : 'Missing'),
            'warnings' => $warnings,
            'source' => $structuredCount > 0 ? 'structured' : ($legacyText !== '' ? 'legacy_text' : 'position_context'),
        ];
    }

    private function publicRequirements(array $requirements): array
    {
        unset($requirements['context_fields']);
        return $requirements;
    }

    private function parseList($value): array
    {
        if (is_array($value)) {
            return $this->cleanList($value);
        }
        return $this->cleanList(preg_split('/[\r\n,;]+/', (string)$value) ?: []);
    }

    private function parseLegacyList(string $value): array
    {
        $items = $this->parseList($value);
        return array_values(array_filter($items, static function (string $item): bool {
            return !preg_match('/^(none|n\/a|not applicable|no .+ required|with no .+ required)$/i', trim($item));
        }));
    }

    private function cleanList(array $items): array
    {
        $clean = [];
        foreach ($items as $item) {
            $item = trim((string)$item, " \t\n\r\0\x0B-•");
            if ($item !== '') {
                $clean[] = $item;
            }
        }
        return array_values(array_unique($clean));
    }

    private function inferEducationLevel(string $text): ?string
    {
        $text = strtolower($text);
        if (preg_match('/doctor(?:ate|al)?|ph\.?d/', $text)) return 'doctorate';
        if (preg_match('/master(?:s|al)?/', $text)) return 'master';
        if (preg_match('/bachelor|baccalaureate/', $text)) return 'bachelor';
        if (preg_match('/associate/', $text)) return 'associate';
        if (preg_match('/high school|secondary/', $text)) return 'high_school';
        return null;
    }

    private function educationLevel(array $record): string
    {
        $level = strtolower(trim((string)($record['education_level'] ?? '')));
        if (isset($this->config['education_levels'][$level])) {
            return $level;
        }
        return $this->inferEducationLevel(trim(($record['degree'] ?? '') . ' ' . ($record['field_of_study'] ?? ''))) ?? 'other';
    }

    private function phraseMatch(string $requirement, string $candidate): float
    {
        $left = $this->normalizePhrase($requirement);
        $right = $this->normalizePhrase($candidate);
        if ($left === '' || $right === '') return 0.0;
        if ($left === $right) return 1.0;
        if (strlen($left) >= 3 && strlen($right) >= 3 && (str_contains($left, $right) || str_contains($right, $left))) return 0.95;
        $leftTokens = $this->tokens($left);
        $rightTokens = $this->tokens($right);
        if (empty($leftTokens) || empty($rightTokens)) return 0.0;
        $intersection = count(array_intersect($leftTokens, $rightTokens));
        $coverage = $intersection / count($leftTokens);
        if ($coverage >= 0.75) return 0.85;
        if ($coverage >= 0.5) return 0.65;
        if ($coverage > 0) return 0.30;
        return 0.0;
    }

    private function normalizePhrase(string $value): string
    {
        $value = strtolower(trim($value));
        foreach ($this->config['phrase_aliases'] as $from => $to) {
            $value = str_replace($from, $to, $value);
        }
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function tokens(string $value): array
    {
        $stop = array_flip($this->config['stop_words']);
        $tokens = preg_split('/\s+/', $value) ?: [];
        return array_values(array_unique(array_filter($tokens, static fn(string $token): bool => strlen($token) > 1 && !isset($stop[$token]))));
    }

    private function experienceYears(array $records): float
    {
        $intervals = [];
        $today = new DateTimeImmutable('today');
        foreach ($records as $record) {
            try {
                $start = new DateTimeImmutable((string)($record['start_date'] ?? ''));
                $end = (!empty($record['is_current']) || empty($record['end_date'])) ? $today : new DateTimeImmutable((string)$record['end_date']);
            } catch (Throwable $e) {
                continue;
            }
            if ($end < $start) continue;
            $intervals[] = [$start->getTimestamp(), $end->getTimestamp()];
        }
        if (empty($intervals)) return 0.0;
        usort($intervals, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($intervals as $interval) {
            $last = count($merged) - 1;
            if ($last < 0 || $interval[0] > $merged[$last][1]) {
                $merged[] = $interval;
            } else {
                $merged[$last][1] = max($merged[$last][1], $interval[1]);
            }
        }
        $seconds = 0;
        foreach ($merged as [$start, $end]) $seconds += max(0, $end - $start);
        return round($seconds / (365.2425 * 86400), 2);
    }

    private function isTeachingExperience(array $record): bool
    {
        if (($record['experience_type'] ?? '') === 'teaching') return true;
        $text = $this->normalizePhrase(trim(($record['job_title'] ?? '') . ' ' . ($record['description'] ?? '')));
        foreach ($this->config['teaching_terms'] as $term) {
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/', $text)) return true;
        }
        return false;
    }

    private function isExpired(array $qualification): bool
    {
        $expiry = trim((string)($qualification['expiry_date'] ?? ''));
        return $expiry !== '' && strtotime($expiry) !== false && strtotime($expiry) < strtotime(date('Y-m-d'));
    }

    private function nullableString($value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function nullableNumber($value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) return null;
        return max(0.0, (float)$value);
    }

    private function labelFor(float $score): string
    {
        foreach ($this->config['labels'] as $threshold) {
            if ($score >= (float)$threshold['minimum']) return (string)$threshold['label'];
        }
        return 'Does Not Fully Meet Requirements';
    }

    private function humanize(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
