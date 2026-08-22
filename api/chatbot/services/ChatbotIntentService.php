<?php

class ChatbotIntentService
{
    public function detect($message, array $sessionContext = [])
    {
        $text = trim($message);
        $lower = strtolower($text);

        $followUp = $this->detectFollowUpApplicant($lower, $sessionContext);
        if ($followUp) {
            return $followUp;
        }

        if ($this->looksLikeStatistics($lower)) {
            return [
                'type' => 'application_statistics',
                'status' => $this->extractStatusTerm($lower),
            ];
        }

        if ($this->looksLikeJobApplicants($lower)) {
            return [
                'type' => 'job_applicants',
                'job_query' => $this->extractJobQuery($text),
            ];
        }

        if ($this->looksLikeJobInfo($lower)) {
            return [
                'type' => 'job_information',
            ];
        }

        if (preg_match('/\b(summary|summarize|quick summary|profile summary)\b/i', $text)) {
            return [
                'type' => 'applicant_summary',
                'name' => $this->extractApplicantName($text),
            ];
        }

        if (preg_match('/\b(status|stage|progress)\b/i', $text) && preg_match('/\b(applicant|application|of|for|is)\b/i', $text)) {
            return [
                'type' => 'applicant_status',
                'name' => $this->extractApplicantName($text),
            ];
        }

        if (preg_match('/\b(find|lookup|look up|show|search)\b/i', $text) && preg_match('/\b(applicant|application)\b/i', $text)) {
            return [
                'type' => 'applicant_lookup',
                'name' => $this->extractApplicantName($text),
            ];
        }

        return [
            'type' => 'general_recruitment_question',
        ];
    }

    private function detectFollowUpApplicant($lower, array $sessionContext)
    {
        $lastApplicants = $sessionContext['last_applicants'] ?? [];
        if (empty($lastApplicants) || !is_array($lastApplicants)) {
            return null;
        }

        $index = null;
        $ordinals = [
            'first' => 0,
            '1st' => 0,
            'second' => 1,
            '2nd' => 1,
            'third' => 2,
            '3rd' => 2,
            'fourth' => 3,
            '4th' => 3,
            'fifth' => 4,
            '5th' => 4,
        ];

        foreach ($ordinals as $word => $position) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\s+applicant\b/', $lower)) {
                $index = $position;
                break;
            }
        }

        if ($index === null || !isset($lastApplicants[$index]['id'])) {
            return null;
        }

        if (preg_match('/\b(status|stage|progress)\b/', $lower)) {
            return [
                'type' => 'applicant_status',
                'applicant_id' => (int) $lastApplicants[$index]['id'],
            ];
        }

        return [
            'type' => 'applicant_summary',
            'applicant_id' => (int) $lastApplicants[$index]['id'],
        ];
    }

    private function looksLikeStatistics($lower)
    {
        return preg_match('/\b(how many|count|number of|total)\b/', $lower) &&
            preg_match('/\b(applicant|application|pending|approved|rejected|hired|interview|demo)\b/', $lower);
    }

    private function looksLikeJobApplicants($lower)
    {
        return preg_match('/\b(who applied|applicants? for|applications? for|show me the applicants|give me .*applicants)\b/', $lower);
    }

    private function looksLikeJobInfo($lower)
    {
        return preg_match('/\b(job vacancies|vacancies|available jobs|job postings|open positions|positions currently have applicants|positions have applicants)\b/', $lower);
    }

    private function extractStatusTerm($lower)
    {
        $statusTerms = ['pending', 'approved', 'rejected', 'hired', 'interview', 'demo', 'cancelled', 'resubmission'];
        foreach ($statusTerms as $term) {
            if (strpos($lower, $term) !== false) {
                return $term;
            }
        }
        return 'all';
    }

    private function extractJobQuery($message)
    {
        $patterns = [
            '/applicants?\s+for\s+(.+)$/i',
            '/applications?\s+for\s+(.+)$/i',
            '/who\s+applied\s+for\s+(.+)$/i',
            '/give\s+me\s+.*applicants?\s+for\s+(.+)$/i',
            '/show\s+me\s+.*applicants?\s+for\s+(.+)$/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return $this->cleanEntityText($matches[1]);
            }
        }

        return $this->cleanEntityText($message);
    }

    private function extractApplicantName($message)
    {
        $patterns = [
            '/(?:summary|summarize|quick summary|status|stage|progress)\s+(?:the\s+)?(?:application\s+)?(?:of|for)\s+(.+)$/i',
            '/(?:what\s+is|whats|show|find|lookup|look up)\s+(?:the\s+)?(?:current\s+)?(?:application\s+)?(?:status|stage|progress)\s+(?:of|for)\s+(.+)$/i',
            '/(?:find|lookup|look up|show|search)\s+(?:the\s+)?(?:applicant|application)\s+(.+)$/i',
            '/(?:summary|summarize|quick summary)\s+(.+)$/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return $this->cleanEntityText($matches[1]);
            }
        }

        $cleaned = preg_replace('/\b(summary|summarize|quick|application|applicant|status|current|what|is|of|for|the|show|me|find|lookup|look|up)\b/i', ' ', $message);
        return $this->cleanEntityText($cleaned);
    }

    private function cleanEntityText($value)
    {
        $value = trim($value);
        $value = preg_replace('/[\?\.\!]+$/', '', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($value === '' || preg_match('/^\[[^\]]+\]$/', $value)) {
            return '';
        }

        return $value;
    }
}
