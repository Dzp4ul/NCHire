<?php

/**
 * Defines the small local-tool surface exposed to Groq and validates every
 * argument again before dispatch. No tool accepts SQL or arbitrary field names.
 */
class ChatbotToolService
{
    private $data;
    private $admin;

    public function __construct(RecruitmentDataService $data, array $admin)
    {
        $this->data = $data;
        $this->admin = $admin;
    }

    public function definitions(): array
    {
        return [
            $this->tool('search_applications',
                'Search or count authorized NCHire application records. Use this for applicant lists, application counts, stages, dates, departments, requirement completion, education level, master units, or teaching experience. Use application_ids from conversation references for follow-ups such as "which of them".',
                [
                    'query' => $this->stringSchema('Broad applicant, position, subject, or department text.'),
                    'application_ids' => $this->integerArraySchema('Previously returned application IDs to re-check with fresh database data.'),
                    'applicant_name' => $this->stringSchema('Applicant name or partial name.'),
                    'job_query' => $this->stringSchema('Job title, subject code, or subject name.'),
                    'department' => $this->stringSchema('Department name.'),
                    'status' => $this->enumSchema(['all','active','pending','secretary_review','initial_screening','department_review','waiting_interview','interview_scheduled','interview_completed','waiting_demo','demo_scheduled','demo_completed','psych_scheduled','passed','accepted','rejected','cancelled','resubmission']),
                    'requirement_state' => $this->enumSchema(['any','complete','missing']),
                    'education_level' => $this->enumSchema(['any','bachelor','master','doctorate']),
                    'education_status' => $this->enumSchema(['any','completed','ongoing']),
                    'minimum_master_units' => ['type' => ['integer','null'], 'minimum' => 0, 'maximum' => 300],
                    'has_teaching_experience' => ['type' => ['boolean','null']],
                    'applied_from' => $this->dateSchema('Inclusive application start date.'),
                    'applied_to' => $this->dateSchema('Inclusive application end date.'),
                    'include_archived' => ['type' => ['boolean','null']],
                    'page' => ['type' => ['integer','null'], 'minimum' => 1, 'maximum' => 1000],
                    'limit' => ['type' => ['integer','null'], 'minimum' => 1, 'maximum' => 25],
                ]),
            $this->tool('get_application_details',
                'Retrieve fresh authorized application details, education, experience, skills, qualifications, dynamic requirement completion, schedules, and workflow history. For ambiguous names, the result returns candidates and you must ask the user which application they mean.',
                [
                    'application_id' => ['type' => ['integer','null'], 'minimum' => 1],
                    'application_ids' => $this->integerArraySchema('One or more application IDs, usually from conversation references.', 10),
                    'applicant_name' => $this->stringSchema('Applicant name or partial name.'),
                ]),
            $this->tool('search_teaching_loads',
                'Search or count authorized teaching loads and vacancies, including capacity, status, qualifications, requirements, and application counts. An open vacancy means Active, unexpired, and with an available section.',
                [
                    'query' => $this->stringSchema('Teaching-load title, subject code, subject name, or program.'),
                    'department' => $this->stringSchema('Department name.'),
                    'status' => $this->enumSchema(['any','active','closed','open']),
                    'deadline_from' => $this->dateSchema('Inclusive deadline start date.'),
                    'deadline_to' => $this->dateSchema('Inclusive deadline end date.'),
                    'minimum_available_sections' => ['type' => ['integer','null'], 'minimum' => 0, 'maximum' => 10000],
                    'no_applicants' => ['type' => ['boolean','null']],
                    'include_requirements' => ['type' => ['boolean','null']],
                    'sort' => $this->enumSchema(['deadline','most_applications','newest_record']),
                    'page' => ['type' => ['integer','null'], 'minimum' => 1, 'maximum' => 1000],
                    'limit' => ['type' => ['integer','null'], 'minimum' => 1, 'maximum' => 25],
                ]),
            $this->tool('get_recruitment_schedules',
                'Retrieve authorized interview, teaching demonstration, or psychological examination schedules. Prefer named periods so the server resolves relative dates in Asia/Manila.',
                [
                    'schedule_type' => $this->enumSchema(['all','interview','demo','psych_exam']),
                    'period' => $this->enumSchema(['today','tomorrow','this_week','next_week','upcoming','custom','all']),
                    'date_from' => $this->dateSchema('Start date for a custom period.'),
                    'date_to' => $this->dateSchema('End date for a custom period.'),
                    'outcome' => $this->enumSchema(['any','scheduled','completed','awaiting_result','upcoming']),
                    'limit' => ['type' => ['integer','null'], 'minimum' => 1, 'maximum' => 30],
                ]),
            $this->tool('get_recruitment_overview',
                'Calculate authorized recruitment totals, unique applicants, workflow counts, department comparisons, top positions, upcoming schedule counts, and recorded workflow transitions. Use this for summaries and multi-category statistics.',
                [
                    'applied_from' => $this->dateSchema('Inclusive application start date.'),
                    'applied_to' => $this->dateSchema('Inclusive application end date.'),
                    'transition_from' => $this->dateSchema('Inclusive workflow-transition start date.'),
                    'transition_to' => $this->dateSchema('Inclusive workflow-transition end date.'),
                    'department' => $this->stringSchema('Department name.'),
                    'include_archived' => ['type' => ['boolean','null']],
                ]),
            $this->tool('compare_applicant_qualifications',
                'Compare verified applicant education and experience evidence against one teaching load without ranking, scoring, recommending, or making a hiring decision.',
                [
                    'teaching_load_id' => ['type' => ['integer','null'], 'minimum' => 1],
                    'teaching_load_query' => $this->stringSchema('Teaching-load title, subject code, or subject name.'),
                    'application_ids' => $this->integerArraySchema('Applications to compare. Omit to compare accessible applicants for the load.', 10),
                    'applicant_name' => $this->stringSchema('A single applicant name when comparing one person.'),
                ]),
            $this->tool('get_system_help',
                'Explain only NCHire functionality and navigation verified from the implemented dashboard, permissions, and recruitment workflow. Use for how-to, role capability, navigation, archive, requirements, teaching load, and process questions.',
                [
                    'topic' => $this->enumSchema(['general','role_capabilities','teaching_loads','applicant_review','requirements','schedules','recruitment_workflow','archive','users','chatbot']),
                ]),
        ];
    }

    public function dispatch(string $name, array $arguments): array
    {
        $arguments = $this->validate($name, $arguments);
        switch ($name) {
            case 'search_applications':
                return $this->data->searchApplications($arguments);
            case 'get_application_details':
                return $this->data->getApplicationDetails($arguments);
            case 'search_teaching_loads':
                return $this->data->searchTeachingLoads($arguments);
            case 'get_recruitment_schedules':
                return $this->data->getSchedules($arguments);
            case 'get_recruitment_overview':
                return $this->data->getRecruitmentOverview($arguments);
            case 'compare_applicant_qualifications':
                return $this->data->compareQualifications($arguments);
            case 'get_system_help':
                return $this->systemHelp((string)($arguments['topic'] ?? 'general'));
            default:
                throw new InvalidArgumentException('Unknown recruitment tool.');
        }
    }

    private function validate(string $name, array $arguments): array
    {
        // Some Groq tool-capable models serialize omitted optional properties as
        // JSON null. Null means "filter not supplied" and is removed before the
        // same strict whitelist and type/range validation used for user values.
        foreach ($arguments as $key => $value) {
            if ($value === null) unset($arguments[$key]);
        }

        $rules = [
            'search_applications' => [
                'strings' => ['query','applicant_name','job_query','department'],
                'dates' => ['applied_from','applied_to'],
                'enums' => [
                    'status' => ['all','active','pending','secretary_review','initial_screening','department_review','waiting_interview','interview_scheduled','interview_completed','waiting_demo','demo_scheduled','demo_completed','psych_scheduled','passed','accepted','rejected','cancelled','resubmission'],
                    'requirement_state' => ['any','complete','missing'],
                    'education_level' => ['any','bachelor','master','doctorate'],
                    'education_status' => ['any','completed','ongoing'],
                ],
                'integers' => ['minimum_master_units' => [0,300], 'page' => [1,1000], 'limit' => [1,25]],
                'booleans' => ['has_teaching_experience','include_archived'],
                'integer_arrays' => ['application_ids' => 25],
            ],
            'get_application_details' => [
                'strings' => ['applicant_name'],
                'integers' => ['application_id' => [1,PHP_INT_MAX]],
                'integer_arrays' => ['application_ids' => 10],
            ],
            'search_teaching_loads' => [
                'strings' => ['query','department'],
                'dates' => ['deadline_from','deadline_to'],
                'enums' => ['status' => ['any','active','closed','open'], 'sort' => ['deadline','most_applications','newest_record']],
                'integers' => ['minimum_available_sections' => [0,10000], 'page' => [1,1000], 'limit' => [1,25]],
                'booleans' => ['no_applicants','include_requirements'],
            ],
            'get_recruitment_schedules' => [
                'dates' => ['date_from','date_to'],
                'enums' => [
                    'schedule_type' => ['all','interview','demo','psych_exam'],
                    'period' => ['today','tomorrow','this_week','next_week','upcoming','custom','all'],
                    'outcome' => ['any','scheduled','completed','awaiting_result','upcoming'],
                ],
                'integers' => ['limit' => [1,30]],
            ],
            'get_recruitment_overview' => [
                'strings' => ['department'],
                'dates' => ['applied_from','applied_to','transition_from','transition_to'],
                'booleans' => ['include_archived'],
            ],
            'compare_applicant_qualifications' => [
                'strings' => ['teaching_load_query','applicant_name'],
                'integers' => ['teaching_load_id' => [1,PHP_INT_MAX]],
                'integer_arrays' => ['application_ids' => 10],
            ],
            'get_system_help' => [
                'enums' => ['topic' => ['general','role_capabilities','teaching_loads','applicant_review','requirements','schedules','recruitment_workflow','archive','users','chatbot']],
            ],
        ];
        if (!isset($rules[$name])) {
            throw new InvalidArgumentException('Unknown recruitment tool.');
        }
        $rule = $rules[$name];
        $allowed = [];
        foreach (['strings','dates','booleans'] as $group) {
            foreach ($rule[$group] ?? [] as $key) {
                $allowed[$key] = true;
            }
        }
        foreach (['enums','integers','integer_arrays'] as $group) {
            foreach (array_keys($rule[$group] ?? []) as $key) {
                $allowed[$key] = true;
            }
        }
        foreach ($arguments as $key => $_) {
            if (!isset($allowed[$key])) {
                throw new InvalidArgumentException('Unsupported tool argument: ' . $key);
            }
        }

        $clean = [];
        foreach ($rule['strings'] ?? [] as $key) {
            if (!array_key_exists($key, $arguments)) continue;
            if (!is_string($arguments[$key])) throw new InvalidArgumentException($key . ' must be text.');
            $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $arguments[$key]) ?? '');
            if (mb_strlen($value) > 200) throw new InvalidArgumentException($key . ' is too long.');
            $clean[$key] = $value;
        }
        foreach ($rule['dates'] ?? [] as $key) {
            if (!array_key_exists($key, $arguments) || $arguments[$key] === '') continue;
            if (!is_string($arguments[$key]) || !$this->validDate($arguments[$key])) {
                throw new InvalidArgumentException($key . ' must use YYYY-MM-DD.');
            }
            $clean[$key] = $arguments[$key];
        }
        foreach ($rule['enums'] ?? [] as $key => $values) {
            if (!array_key_exists($key, $arguments)) continue;
            if (!is_string($arguments[$key]) || !in_array(strtolower($arguments[$key]), $values, true)) {
                throw new InvalidArgumentException($key . ' has an unsupported value.');
            }
            $clean[$key] = strtolower($arguments[$key]);
        }
        foreach ($rule['integers'] ?? [] as $key => [$min, $max]) {
            if (!array_key_exists($key, $arguments)) continue;
            if (!is_int($arguments[$key]) && !ctype_digit((string)$arguments[$key])) {
                throw new InvalidArgumentException($key . ' must be an integer.');
            }
            $value = (int)$arguments[$key];
            if ($value < $min || $value > $max) throw new InvalidArgumentException($key . ' is outside the allowed range.');
            $clean[$key] = $value;
        }
        foreach ($rule['booleans'] ?? [] as $key) {
            if (!array_key_exists($key, $arguments)) continue;
            if (!is_bool($arguments[$key])) throw new InvalidArgumentException($key . ' must be true or false.');
            $clean[$key] = $arguments[$key];
        }
        foreach ($rule['integer_arrays'] ?? [] as $key => $maximum) {
            if (!array_key_exists($key, $arguments)) continue;
            if (!is_array($arguments[$key]) || count($arguments[$key]) > $maximum) {
                throw new InvalidArgumentException($key . ' must be a short list of application IDs.');
            }
            $values = [];
            foreach ($arguments[$key] as $value) {
                if ((!is_int($value) && !ctype_digit((string)$value)) || (int)$value < 1) {
                    throw new InvalidArgumentException($key . ' contains an invalid ID.');
                }
                $values[] = (int)$value;
            }
            $clean[$key] = array_values(array_unique($values));
        }
        return $clean;
    }

    private function systemHelp(string $topic): array
    {
        $role = (string)($this->admin['role'] ?? '');
        $displayRole = $role === 'Department Head' ? 'Dean' : $role;
        $roleCapabilities = [
            'Admin' => [
                'Dashboard: view system-wide dashboard recruitment statistics and recent activity.',
                'Users: create and manage staff accounts and their status.',
                'AI Assistant: query dashboard-level recruitment data, teaching loads, and basic application records; detailed applicant qualifications remain recruitment-staff-only.',
            ],
            'Secretary' => [
                'Teaching Loads: create, edit, filter, open/close, and manage per-load application requirements.',
                'Applicants: review submitted documents, request resubmission, reject during Secretary review, or transfer a reviewed application to the Dean.',
                'Archive: view rejected and cancelled applications.',
            ],
            'Department Head' => [
                'Teaching Loads: manage teaching loads for the assigned department.',
                'Applicants: work only with applications transferred to the assigned department.',
                'Recruitment actions: schedule/reschedule interviews, record interview completion, schedule/reschedule teaching demonstrations, record demo completion, schedule the psychological examination, and mark an application passed when the required receipt and load capacity exist.',
                'Archive: view rejected and cancelled applications for the assigned department.',
            ],
        ];
        $topics = [
            'general' => [
                'Use the left sidebar to open Dashboard, Teaching Loads, Applicants, Archive, or Users when that item is available to the signed-in role.',
                'The AI Assistant is read-only: it can explain and retrieve data but cannot modify recruitment records.',
            ],
            'teaching_loads' => [
                'Open Teaching Loads from the left sidebar, then use Create Teaching Load.',
                'The form records employment type, deadline, academic period, department, subject code/name, program, lecture/laboratory units, calculated weekly hours, available sections, compensation classification, description, qualifications, and application requirements.',
                'A public vacancy is available only while the posting is Active, its deadline has not passed, and an available section remains.',
            ],
            'applicant_review' => [
                'Open Applicants, search or filter the list, then choose View Details.',
                'The details workspace contains Requirements, Overview, Recruitment, and Decision tabs. Available actions depend on the signed-in role and current workflow stage.',
            ],
            'requirements' => [
                'To change the documents required for a position, open Teaching Loads, edit the load, and use Application Requirements. Secretary and the Dean for that department can manage these rows.',
                'To review applicant submissions, open Applicants, choose View Details, and use the Requirements tab. Request resubmission from the applicant action area when permitted.',
                'Completion is calculated from active required teaching-load requirements and active applicant submissions; uploaded file contents are not sent to Groq.',
            ],
            'schedules' => [
                'Schedules are stored on each application and shown in the Applicants detail Recruitment tab and relevant status views.',
                'Dean scheduling actions use weekday dates, campus rooms, and the implemented recruitment-stage rules. Interview and teaching-demo scheduling enforce 8:00 AM to 4:00 PM.',
            ],
            'recruitment_workflow' => [
                'Implemented flow: secretary_review -> waiting_interview_schedule/department_head_review -> interview_scheduled -> interview_completed -> demo_scheduled -> demo_completed -> psych_scheduled -> passed.',
                'Rejected and cancelled applications are terminal archive stages. Legacy initially_hired, permanently_hired, and hired values remain supported by the code and schema.',
                'Secretary review may transfer, request document resubmission, or reject. Dean actions are department-scoped and stage-scoped.',
            ],
            'archive' => [
                'Open Archive from the left sidebar. The archive contains rejected and cancelled applications.',
                'Secretary access spans the Secretary archive; Dean access is restricted to the assigned department; the Admin navigation does not expose the Archive module.',
            ],
            'users' => [
                'The Users section is available to Admin accounts for staff-account management.',
                'Non-Admin staff can view only their own account through the users API and cannot create or delete staff accounts.',
            ],
            'chatbot' => [
                'Ask in natural language. NCHire sends the question to Groq for tool selection, executes only validated read-only server tools, and sends the verified result back to Groq for wording.',
                'Use Clear conversation to remove this signed-in staff member\'s chat history and references.',
            ],
            'role_capabilities' => $roleCapabilities[$role] ?? ['No implemented recruitment capabilities were found for this role.'],
        ];
        return [
            'topic' => $topic,
            'current_role' => $displayRole,
            'current_department' => $role === 'Department Head' ? ($this->admin['department'] ?? null) : null,
            'instructions' => $topics[$topic] ?? $topics['general'],
            'role_capabilities' => $roleCapabilities[$role] ?? [],
            'authorization_scope' => $this->data->getScopeSummary(),
            'source' => 'Implemented NCHire dashboard routes and workflow handlers',
            'retrieved_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format(DateTimeInterface::ATOM),
        ];
    }

    private function tool(string $name, string $description, array $properties): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object)$properties,
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function stringSchema(string $description): array
    {
        return ['type' => ['string','null'], 'description' => $description, 'maxLength' => 200];
    }

    private function dateSchema(string $description): array
    {
        return ['type' => ['string','null'], 'description' => $description, 'pattern' => '^\\d{4}-\\d{2}-\\d{2}$'];
    }

    private function enumSchema(array $values): array
    {
        $values[] = null;
        return ['type' => ['string','null'], 'enum' => $values];
    }

    private function integerArraySchema(string $description, int $maximum = 25): array
    {
        return [
            'type' => ['array','null'],
            'description' => $description,
            'items' => ['type' => 'integer', 'minimum' => 1],
            'maxItems' => $maximum,
            'uniqueItems' => true,
        ];
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Manila'));
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
