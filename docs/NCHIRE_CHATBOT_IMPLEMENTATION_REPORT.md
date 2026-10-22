# NCHire Database-Aware AI Recruitment Assistant

## Delivery status

The Admin, Secretary, and Department Head/Dean dashboards now use one authenticated, read-only recruitment assistant. Groq performs natural-language tool selection and response wording; the PHP backend alone applies authorization, validates arguments, executes fixed prepared queries, and returns verified records. The model never receives database credentials and cannot submit SQL.

This implementation preserves the existing dashboard widget, recruitment workflow, account permissions, and database records. No production recruitment record was changed during development or verification.

## 1. System analysis

### Application architecture

- The application is a server-rendered PHP/MySQL system with role-specific behavior concentrated in `admin/index.php`, browser interactions in `admin/admin.js`, and JSON endpoints under `api/`.
- Staff authentication uses the PHP session and an `admin_users` row. The implemented staff roles are `Admin`, `Secretary`, and `Department Head`; the UI labels a Department Head as a Dean where appropriate.
- Browser mutations use a session CSRF token. The chatbot endpoint now follows the same control.
- The public/applicant side stores applicant profiles and submitted applications separately from staff accounts.
- Existing shared recruitment helpers remain the source for dynamic-requirement completion rules. The chatbot does not introduce a second definition.

### Relevant database relationships

The schema and migrations were inspected together with the queries used by the live application. The chatbot reads these existing entities:

| Entity | Relationship and chatbot use |
|---|---|
| `admin_users` | Authenticated staff identity, role, active status, and Dean department scope. |
| `applicants` | Applicant identity/profile joined through `job_applicants.user_id`. Sensitive account and contact fields are not sent to Groq. |
| `job` | Teaching load/posting, department/program, deadline, capacity, status, qualifications, and subject information. |
| `job_applicants` | Application source of truth: applicant, load, application date, status, workflow stage, schedules, results, and decision dates. |
| `user_education`, `user_experience`, `user_skills`, `user_qualifications` | Evidence used for authorized qualification summaries and objective comparisons. |
| `teaching_load_requirements` | Active requirements configured for each teaching load. |
| `applicant_requirement_submissions` | Active applicant submissions joined to configured requirements to determine complete/missing state. |
| `workflow_history` | Recorded recruitment-stage transitions and transition-period statistics. |
| `teaching_assignments` | Capacity/assignment context associated with successful applications. |

One applicant can have more than one application, so every result explicitly distinguishes total applications from unique applicants where the distinction matters.

### Implemented recruitment workflow

The database enum and workflow handlers contain these stages:

`secretary_review`, `secretary_approved`, `department_head_review`, `waiting_interview_schedule`, `interview_scheduled`, `interview_completed`, `demo_scheduled`, `demo_completed`, `psych_scheduled`, `psych_completed`, `initially_hired`, `permanently_hired`, `passed`, `hired`, `rejected`, and `cancelled`.

The active UI flow is Secretary review, transfer/waiting for interview, interview, teaching demonstration, psychological examination, then passed. Rejected and cancelled records appear in Archive. The code and schema still recognize older hired-stage values. The assistant reports these real values and does not invent extra stages.

For chatbot queries, **pending** is defined as applications in `secretary_review` or `department_head_review`, matching the implemented review queues. The definition is included in structured results so Groq cannot silently substitute a generic meaning.

### Existing permission boundaries

- **Admin:** system-wide dashboard aggregates, teaching-load information, and basic application records. The current Admin navigation does not expose recruitment-detail or Archive workspaces, so detailed education, experience, requirements, and qualifications remain hidden from the Admin chatbot.
- **Secretary:** authorized operational application records and teaching loads across the Secretary workflow, including requirement review and archive access already present in the UI.
- **Dean/Department Head:** only the assigned department and only applications transferred into the Dean/downstream workflow. Direct record IDs and conversation references are passed through the same scope, preventing an ID or prior message from bypassing department restrictions.

## 2. Problems in the previous chatbot

- The old intent layer depended mainly on regular expressions and covered only a small set of counts, job searches, and basic applicant summaries.
- Groq was commonly asked to answer without a verified database tool result, which made broad questions and paraphrases unreliable.
- Suggested questions were UI text, not a guarantee that the underlying request performed the correct dynamic retrieval.
- There was no general tool dispatcher for schedules, qualification evidence, dynamic requirements, workflow summaries, or comparisons.
- Conversation state did not provide robust, user-bound record references for phrases such as “them” or “his requirements.”
- The chatbot endpoint did not require a CSRF token and did not revalidate the active staff row on each request.
- Admin did not receive the widget, while parts of the Dean stage filter omitted implemented waiting/passed states.
- Database/API failures could not consistently be distinguished from an empty result, increasing the risk of a confident but unverified answer.
- Tool-capable Groq models can serialize unused optional arguments as JSON `null`. The initial schema rejected these calls; nullable schemas plus server-side null normalization now handle that valid model behavior.

## 3. Changes implemented

### Backend

- `api/chatbot/services/GroqService.php`
  - Preserves assistant tool calls through a normalized completion method.
  - Supports local tools, tool choice, structured responses, reasoning effort, bounded timeouts, and a pseudonymous user identifier.
  - Classifies authentication, model, timeout, network, service, and rate-limit failures without returning credentials.

- `api/chatbot/services/RecruitmentDataService.php`
  - Provides fixed, read-only prepared queries for application search/counts, applicant detail, teaching loads/vacancies, schedules, recruitment overview/statistics, requirements, and qualifications.
  - Applies role and department scope inside every query path.
  - Supports filters, bounded pagination, relative schedule periods, unique-applicant counts, active/archive scope, dynamic requirements, education level/status, master's units, and teaching experience.
  - Minimizes personally identifying data and never returns passwords, contact details, addresses, raw document paths, or document contents.

- `api/chatbot/services/ChatbotToolService.php`
  - Exposes seven local tools: application search, application details, teaching-load search, schedule retrieval, recruitment overview, qualification comparison, and implemented-system help.
  - Whitelists all arguments, enums, date formats, ID lists, booleans, lengths, ranges, and page sizes. Unknown fields such as `sql` are rejected.
  - Accepts `null` only as an omitted optional filter, removes it, and then performs normal strict validation.
  - Contains role-aware help text derived from existing routes and controls, not invented product documentation.

- `api/chatbot/services/ChatbotService.php`
  - Uses a two-step local-tool flow: forced tool selection, authorized backend execution, then final wording from the verified result.
  - Disables parallel tool calls for the configured model and limits the number of calls and response tokens.
  - Adds a retrieval timestamp in Asia/Manila to grounded responses.
  - Falls back to deterministic formatting if data retrieval succeeded but Groq's final wording call failed.
  - Stores up to ten messages plus bounded application/load references in a session state tied to staff ID, role, department, and email, with a four-hour expiry.
  - Re-queries the database for follow-ups instead of trusting stale answer text.
  - Records technical activity categories without logging user prompts or applicant data.
  - Enforces narrow-panel response conventions and converts any model-generated Markdown table into compact numbered record blocks before storing or returning it.

- `api/chatbot/message.php`
  - Returns JSON even when the database is unavailable.
  - Requires authentication, an allowed role, active-account revalidation, JSON input, a valid session CSRF token, and a 1,000-character maximum message.
  - Supports message, history, and clear actions with no-cache headers.

- `admin/logout.php`
  - Invalidates both new and legacy chatbot context on logout.

### User interface

- `admin/index.php`
  - Enables the existing assistant for Admin, Secretary, and Department Head/Dean.
  - Supplies role-relevant suggested questions that go through the same live retrieval path as typed questions.

- `admin/admin.js`
  - Sends CSRF tokens for all chatbot actions.
  - Restores server-side history, keeps scrolling/follow-up behavior, and preserves toggle, close, clear, and submission controls.
  - Retains the loading indicator and displays controlled database/Groq errors.
  - Renders headings, bold text, lists, retrieval metadata, and legacy Markdown tables using DOM text nodes rather than injecting model-provided HTML.
  - Converts table-shaped answers already present in chat history into responsive record cards and gives assistant answers the full available message width.

### Tests

- `tests/ChatbotDataServiceTest.php` verifies application/unique counts, the real pending definition, role/department scoping, direct-ID denial, dynamic requirements, Admin data minimization, open vacancies, Asia/Manila schedule retrieval, overviews, SQL-injection resistance, unknown argument rejection, system help, and nullable optional arguments.
- Date-filter coverage also verifies that an inclusive end date includes applications submitted later that day rather than stopping at midnight.
- `tests/ChatbotServiceTest.php` verifies Groq tool orchestration, verified-result grounding, history isolation/clearing, retrieval timestamps, and formatting fallback behavior.
- `tests/e2e/chatbot.spec.ts` verifies the widget for all roles, CSRF enforcement, verified database answers, contextual follow-up, Dean isolation, and Admin's basic-data boundary. Live Groq cases are opt-in through `NCHIRE_RUN_GROQ_E2E=1`.
- The browser formatting scenario verifies that a wide teaching-load Markdown table is displayed as responsive cards without raw pipe/separator text.
- `tests/run_php_tests.ps1` now includes both deterministic chatbot suites.

## 4. Groq integration and grounding flow

1. The endpoint authenticates the PHP session, verifies CSRF, reloads the active staff record, and constructs the role/department scope.
2. Groq receives the user question, limited conversation history/references, the current Asia/Manila time, and local-tool schemas. It must choose a tool; it receives no credentials or direct database connection.
3. PHP validates every generated argument against a whitelist. No tool accepts SQL, table names, column names, or arbitrary sort expressions.
4. The selected data service executes prepared, read-only queries with role conditions embedded in the query.
5. Only minimized structured results are returned to Groq for wording.
6. The response is accepted as grounded only after a result with a retrieval timestamp. If retrieval fails, the endpoint explicitly says it could not verify the information and does not generate a substitute answer.

The configured `openai/gpt-oss-120b` model was checked against Groq's current [local tool-calling guide](https://console.groq.com/docs/tool-use/local-tool-calling), [structured-output guide](https://console.groq.com/docs/structured-outputs), and [model documentation](https://console.groq.com/docs/models). Parallel tool calling is disabled for this flow.

## 5. Security and anti-hallucination controls

- Authorization is enforced in PHP/SQL and is never delegated to a prompt.
- Follow-up IDs are only hints; the data layer reapplies current role and department scope.
- Prepared statements and fixed select lists prevent SQL injection. The model cannot provide executable SQL.
- User messages and database strings are explicitly treated as untrusted content.
- Applicant PII, passwords, tokens, credentials, raw paths, and document contents are excluded.
- Qualification comparisons are evidence lists only. No AI ranking, score, hiring recommendation, or automatic decision was introduced.
- Empty, ambiguous, unauthorized, database-unavailable, and model-unavailable conditions are distinct. Hidden records are not confirmed to unauthorized users.
- Conversation state is isolated by the authenticated actor fingerprint, expires after four hours, is bounded, and is cleared on logout/account change.

## 6. Verification results

### Deterministic PHP suites

`npm run test:php` passed all included suites:

- Salary projection: 42/42
- Dynamic application requirements: 14/14
- Application document reuse: 16/16
- Teaching-load workflow: 10/10
- Chatbot data service: passed
- Chatbot Groq orchestration with a deterministic fake client: passed
- Profile education CRUD: 10/10

The chatbot tests were rerun after the nullable-tool compatibility fix and passed.

### Groq connectivity and live calls

- `php tests/GroqConnectivityTest.php`: passed with the configured server-side key/model and strict structured JSON.
- A live Admin tool-call E2E test passed after the nullable-argument fix.
- In the complete live chatbot run, dashboard/CSRF checks and the Dean department-isolation query passed. Rapid subsequent calls reached the Groq account's external rate limit; the Secretary follow-up and final Admin request correctly returned HTTP 503 with the explicit `rate_limit` category and no fabricated answer.
- Live tests intentionally fail on a provider rate limit so deployment capacity issues remain visible. Normal test runs skip provider-billed cases.

### Browser regression suite

The Chromium desktop suite completed with 47 passes, 3 skipped live-Groq cases, and one unrelated existing test-marker issue. The protected-page redirect scenario now passes, but its test is marked `test.fail()` for an older known defect, so Playwright reports the unexpected pass as a failure. No chatbot regression was found in the non-live run.

### Verified isolated-database comparisons

Against the synthetic `nchire_e2e` fixture, service results matched direct expected records:

- Secretary: 3 active application rows belonging to 2 unique applicants.
- Pending: 1 application under the implemented review-stage definition.
- Dynamic requirements: 1 complete application and 2 with missing requirements.
- Open vacancies: 2 for Secretary scope and 1 for the Computing Dean's department.
- Computing Dean: 1 transferred Computing application; a direct Education application ID returned `not_found` rather than leaking the record.
- Admin detail: basic application data only, with detailed education/qualification collections absent.
- A SQL-injection-shaped search value remained a bound value and left the application table intact.

These fixture values demonstrate query correctness without disclosing or modifying production applicant data. At runtime the same services query the current authorized NCHire database.

## 7. Remaining limitations and recommendations

### Completed behavior with environmental constraint

- Groq rate limiting is handled safely, but the present provider/account quota can interrupt bursts because each successful question normally requires an intent/tool call and a wording call. A formatting failure after successful retrieval already uses a grounded local fallback; an initial tool-selection rate limit cannot safely be bypassed for arbitrary natural-language questions.

### Schema limitation

- The current `job` table has no reliable posting-created timestamp. “Recently posted” can only use the newest record ID and the response includes that limitation. Add an immutable `created_at` column in a separately reviewed migration if exact posted-date questions are required.

### Deliberately not implemented

- No candidate ranking, scoring, inferred suitability, or hiring recommendations.
- No chatbot write tools. It cannot modify requirements, stages, schedules, loads, users, or decisions.
- No change to existing workflow transitions, account permissions, or unrelated known test defects.

### Deployment checks

- Keep `.env` and `GROQ_API_KEY` outside public web access and rotate the key if it has ever been shared.
- A pre-existing SMTP application credential is stored directly in `config/config.php`. It was not changed as part of the chatbot scope, but it should be rotated and moved to server environment configuration before production deployment.
- Confirm the Groq production quota supports expected concurrency and monitor categorized 429 events.
- Run `NCHIRE_RUN_GROQ_E2E=1 npx playwright test tests/e2e/chatbot.spec.ts --project=chromium-desktop` in a controlled environment after quota changes.

## 8. Final verification conclusion

The implemented assistant is database-aware rather than answer-template-driven. It supports flexible questions across applicants, workflow stages, requirements, schedules, teaching loads, vacancies, qualifications, statistics, and implemented navigation. Every record answer passes through current authentication, backend role scope, validated local tools, prepared read-only queries, and a verified result before Groq writes the response. When that chain cannot be completed, the assistant says the information was not verified instead of guessing.
