<?php

class CandidateRankingAiService
{
    private $groq;

    public function __construct($groqService)
    {
        $this->groq = $groqService;
    }

    public function analyze(array $job, array $profile, array $score): array
    {
        if (!$this->groq->isConfigured()) {
            throw new GroqApiException('missing_api_key', 'Groq API key is not configured.');
        }

        $context = [
            'job' => [
                'title' => $job['job_title'] ?? null,
                'subject' => $job['subject_name'] ?? ($job['subject'] ?? null),
                'program' => $job['program'] ?? null,
                'department' => $job['department_role'] ?? null,
                'description' => $job['job_description'] ?? null,
                'requirements' => $score['requirements'] ?? [],
            ],
            'candidate' => [
                'education' => array_map(static fn(array $row): array => [
                    'level' => $row['education_level'] ?? 'other',
                    'degree' => $row['degree'] ?? null,
                    'field_of_study' => $row['field_of_study'] ?? null,
                    'status' => $row['education_status'] ?? 'completed',
                    'completed_units' => $row['completed_units'] ?? null,
                ], $profile['education'] ?? []),
                'experience' => array_map(static fn(array $row): array => [
                    'job_title' => $row['job_title'] ?? null,
                    'experience_type' => $row['experience_type'] ?? 'other',
                    'description' => $row['description'] ?? null,
                    'start_date' => $row['start_date'] ?? null,
                    'end_date' => $row['end_date'] ?? null,
                    'is_current' => !empty($row['is_current']),
                ], $profile['experience'] ?? []),
                'skills' => array_map(static fn(array $row): array => [
                    'name' => $row['skill_name'] ?? null,
                    'level' => isset($row['skill_level']) ? (int)$row['skill_level'] : null,
                ], $profile['skills'] ?? []),
                'qualifications' => array_map(static fn(array $row): array => [
                    'type' => $row['qualification_type'] ?? null,
                    'title' => $row['title'] ?? null,
                    'issuer' => $row['issuing_organization'] ?? null,
                    'issued_date' => $row['issued_date'] ?? null,
                    'expiry_date' => $row['expiry_date'] ?? null,
                    'proof_on_file' => !empty($row['proof_document']),
                    'verification_status' => $row['verification_status'] ?? 'unverified',
                ], $profile['qualifications'] ?? []),
            ],
            'deterministic_result' => [
                'overall_score' => $score['overall_score'],
                'qualification_label' => $score['qualification_label'],
                'categories' => $score['categories'],
                'matching_qualifications' => $score['matching_qualifications'],
                'missing_requirements' => $score['missing_requirements'],
            ],
        ];

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are an NCHire decision-support analyst. Use only facts in the supplied JSON. Never invent degrees, units, schools, employment, skills, certifications, licenses, training, or document verification. Do not change or recalculate the deterministic score. Missing data must be described as not provided. Return one JSON object only with keys summary (string), strengths (array of strings), missingRequirements (array of strings), jobMatchExplanation (string), and recommendation (one of Highly Qualified, Very Qualified, Qualified, Partially Qualified, Does Not Fully Meet Requirements). The recommendation must be consistent with deterministic_result.qualification_label.',
            ],
            [
                'role' => 'user',
                'content' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
        ];

        $raw = $this->groq->chat($messages, [
            'temperature' => 0.0,
            'max_completion_tokens' => 700,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'candidate_ranking_analysis',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'summary' => ['type' => 'string'],
                            'strengths' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 8],
                            'missingRequirements' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 8],
                            'jobMatchExplanation' => ['type' => 'string'],
                            'recommendation' => [
                                'type' => 'string',
                                'enum' => ['Highly Qualified', 'Very Qualified', 'Qualified', 'Partially Qualified', 'Does Not Fully Meet Requirements'],
                            ],
                        ],
                        'required' => ['summary', 'strengths', 'missingRequirements', 'jobMatchExplanation', 'recommendation'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'timeout' => 20,
            'connect_timeout' => 8,
        ]);

        $decoded = $this->decodeJson($raw);
        return $this->validate(
            $decoded,
            (string)$score['qualification_label'],
            $score['matching_qualifications'] ?? [],
            $score['missing_requirements'] ?? []
        );
    }

    private function decodeJson(string $raw): array
    {
        $decoded = json_decode(trim($raw), true);
        if (is_array($decoded)) return $decoded;

        if (preg_match('/\{.*\}/s', $raw, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) return $decoded;
        }
        throw new GroqApiException('invalid_json', 'Groq returned invalid ranking JSON.');
    }

    private function validate(array $data, string $deterministicLabel, array $deterministicStrengths, array $deterministicMissing): array
    {
        $summary = $this->cleanText($data['summary'] ?? '', 1200);
        $explanation = $this->cleanText($data['jobMatchExplanation'] ?? '', 1600);
        if ($summary === '' || $explanation === '') {
            throw new GroqApiException('invalid_json', 'Groq ranking JSON is missing required text fields.');
        }

        return [
            'summary' => $summary,
            // Evidence lists remain deterministic so model output cannot introduce qualifications.
            'strengths' => $this->cleanList($deterministicStrengths),
            'missing_requirements' => $this->cleanList($deterministicMissing),
            'job_match_explanation' => $explanation,
            // The deterministic label is authoritative and is never overridden by AI output.
            'recommendation' => $deterministicLabel,
        ];
    }

    private function cleanList($items): array
    {
        if (!is_array($items)) return [];
        $clean = [];
        foreach (array_slice($items, 0, 8) as $item) {
            $value = $this->cleanText($item, 350);
            if ($value !== '') $clean[] = $value;
        }
        return array_values(array_unique($clean));
    }

    private function cleanText($value, int $maximum): string
    {
        if (!is_scalar($value)) return '';
        $value = trim(strip_tags((string)$value));
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        if (strlen($value) > $maximum) $value = substr($value, 0, $maximum);
        return trim($value);
    }
}
