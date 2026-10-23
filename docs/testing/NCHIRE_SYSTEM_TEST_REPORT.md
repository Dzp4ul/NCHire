# NCHire system test report

Test date: 2026-10-21 (Asia/Singapore)  
Environment: local XAMPP/PHP 8.3.33, MariaDB/MySQL, isolated `nchire_e2e` database  
Automation: Playwright 1.64.1 plus the repository's PHP integration tests

## Executive result

The application was analyzed from source, schema, routes, live behavior, and role workflows before the browser suite was written. The automated suite now covers public, Applicant, Secretary, Department Head/Dean, and Administrator behavior; application and teaching-load lifecycles; salary rules; dynamic documents; CRUD; authorization; negative cases; and responsive/browser smoke paths.

The test run completed with:

- 64 Playwright cases executed: 56 ordinary passes and 8 expected failures that reproduce confirmed defects. Playwright reports the aggregate as 64 passed because every known defect is explicitly annotated with `test.fail`; the raw JSON shows 56 `passed/passed` and 8 `failed/failed` outcomes.
- Browser/device smoke coverage passed in desktop Chromium, Firefox, WebKit, Chromium tablet, and Chromium mobile.
- 91 PHP assertions passed: 42 salary, 14 dynamic-requirement, 16 document-reuse, 10 teaching-load workflow, and 9 profile-education CRUD assertions.
- Groq provider connectivity passed.
- PHP syntax lint passed for every PHP file in the workspace.
- No production account or production row was changed. State-changing tests ran only against `nchire_e2e`, which is recreated from schema and populated with synthetic records.

The system is functionally broad and the main recruitment flow works, but it should not be considered production-safe until the critical and high security findings below are fixed.

## System architecture discovered

- Frontend: server-rendered PHP pages, Tailwind-style utility CSS, Font Awesome, and vanilla JavaScript/fetch. There is no active SPA framework.
- Backend: PHP endpoints using `mysqli`, PHP sessions, JSON/form/multipart APIs, PHPMailer SMTP delivery, and a Groq-backed recruitment chatbot.
- Database: MySQL/MariaDB. The active schema contains 25 tables.
- Application areas:
  - `public/`: landing, authentication, registration verification, forgot/reset password, terms, and privacy.
  - `user/`: applicant dashboard, jobs, applications, attachments, notifications, profile, salary projection, and current teaching load.
  - `admin/`: shared staff workspace for Admin, Secretary, and Department Head/Dean, plus teaching-load and recruitment APIs.
  - `api/`: public job feed and chatbot endpoint/services.
  - `shared/`: recruitment, requirement, document, and email helpers.

Authentication uses two session families: `user_*` fields for applicants and `admin_*` fields for staff. Admin write routes generally use a session CSRF token. Applicant application submission uses a token, but several other applicant mutations do not.

## Roles and effective permissions

| Role | Effective access discovered |
|---|---|
| Applicant | Public jobs, applicant dashboard, profile sections, application wizard, reusable/updated attachments, notifications, cancellation, status tracking, and assigned teaching load. |
| Administrator | Dashboard statistics, user directory, create/edit/activate/deactivate staff, role assignment, notifications, and activity logging. The UI intentionally omits teaching-load/applicant management. |
| Secretary | Dashboard, all teaching loads, dynamic requirements, applicant document review, resubmission/rejection, transfer to Department Head, archive, notifications, profile, and chatbot. |
| Department Head (shown as Dean) | Department-scoped dashboard, teaching loads, applicants, interview/demo/psych/final hiring transitions, archive, notifications, profile, and chatbot. |
| HR Manager / Recruiter | Present in role enums and some shared navigation/authorization code, but not represented by seeded production records or a distinct completed workflow. They are not treated as fully implemented product roles. |

Authorization positives verified include applicant-to-staff blocking, staff account create/delete restrictions, Secretary denial of Dean actions, Dean department scoping, application ownership checks, CSRF enforcement on tested admin mutations, and logout invalidation.

## Page and interaction inventory

### Public/account pages

- Landing page and live teaching-load cards.
- Desktop and mobile navigation.
- Sign-in and sign-up modals, password visibility, verification modal/success state.
- Forgot-password and forced-password-change pages.
- Terms and privacy pages.

### Applicant workspace

- Job search, sorting, pagination, detail modal, status/deadline/capacity eligibility, and apply action.
- Terms acceptance and dynamic requirement checklist.
- Required/optional uploads, file extension/MIME/size validation, reusable prior documents, and resubmission attachments.
- Application list, detail/progress modal, attachments, cancellation, psych receipt upload, and status visibility.
- Current teaching-load view after final assignment.
- Profile: personal information, education, experience, skills, qualifications, supporting files, and salary projection.
- Notification dropdown/read behavior, profile/security navigation, save/cancel/edit/delete controls.

### Staff workspace

- Dashboard counts/charts and role-aware navigation.
- Teaching-load search, status/department/type/deadline filters, view, create, edit, close/reopen, and delete.
- Required instructors/available sections, academic period, lecture/laboratory units, calculated weekly hours, job type, department, and description.
- Dynamic requirements: add/edit/remove, category, required/optional, per-load independence, published-load confirmation, and preservation of historical submissions.
- Applicant search/date/status filters, details, documents, qualifications, salary information, review notes, resubmission/reject/transfer, Dean schedules and approvals, psych completion, final assignment, and archive.
- Notification menu, staff profile modal, password visibility, chatbot panel, sidebar, modals, confirmations, and reset controls.
- Administrator user search/filters, create, duplicate validation, edit role/department/status, self-deactivation protection, and retained delete-API authorization.

## API and route inventory

Public/auth routes include `public/index.php`, `verify.php`, `forgot_password.php`, `process_forgot_password.php`, `process_reset_password.php`, `terms.php`, `privacy.php`, and `api/get_homepage_jobs.php`.

Applicant data routes include:

- Jobs/applications: `get_jobs_paginated.php`, `get_job_details.php`, `get_user_applications.php`, `get_application_details.php`, `get_application_requirements.php`, and `get_reusable_documents.php`.
- Applicant mutations: the application POST in `user.php`, `cancel_application.php`, `manage_application_attachments.php`, `upload_requirement_documents.php`, `upload_psych_receipt.php`, `save_draft.php`, and `save_profile_data.php`.
- Profile/notifications/files: `get_profile_data.php`, `get_profile_lists.php`, `get_notifications.php`, `mark_notification_read.php`, `download_application_file.php`, and `download_requirement.php`.

Staff routes include:

- Teaching loads: `gets_job.php`, `add_job.php`, `update_job.php`, `toggle_job_status.php`, `delete_job.php`, and `auto_close_expired_jobs.php`.
- Applicants/workflow: `gets_applicants.php`, `view_applicant.php`, `process_applicant_action.php`, `get_archive.php`, applicant/stat APIs, and `api/secretary_actions.php`.
- Admin/users: `api/users.php`, `dashboard_api.php`, `api/dashboard_stats.php`, `api/admin_notifications.php`, profile/password routes, logout, and activity logging.
- Chatbot: `api/chatbot/message.php` and its intent, recruitment-data, and Groq services.

Obsolete candidate-ranking page/API probes return 404 and no ranking control is rendered for Secretary or Dean. The legacy database table remains; see finding DATA-02.

## Database interaction inventory

| Area | Tables |
|---|---|
| Identity and administration | `applicants`, `admin_users`, legacy `admin_user`, legacy `users`, `applicants_info`, `admin_activity`, `admin_notifications`, `notifications` |
| Applicant profile | `user_education`, `user_experience`, `user_skills`, `user_qualifications`, `instructor_files`, `user_draft_documents` |
| Recruitment | `job`, `job_applicants`, `workflow_history`, `application_bans`, `rooms` |
| Requirements/documents | `teaching_load_requirements`, `applicant_requirement_submissions`, `application_document_versions` |
| Hiring/pay | `teaching_assignments`, `graduate_unit_rate_rules` |
| Removed feature residue | `candidate_rankings` |

CRUD persistence was checked through UI refreshes and direct API/database-backed reads, not just DOM changes. The isolated fixture includes active/closed/expired loads, different department requirement sets, incomplete/banned applicants, each recruitment stage, notifications, rooms, and all active roles.

## Business rules verified

### Salary/hourly rate

| Qualification | Verified result |
|---|---:|
| Ongoing Master's, 0, 9, 10, or 17 units | ₱150/hour |
| Ongoing Master's, 18, 19, 24, 36, or 999 units | ₱170/hour |
| Completed Master's | ₱200/hour, regardless of recorded unit count |
| Completed Doctorate | ₱220/hour and takes precedence |
| Ongoing Master's with missing, nonnumeric, or negative units | No assumed rate / validation rejection |
| Full-time/Permanent Instructor I | SG13; part-time hourly rules are not applied |

Rates were verified after add/edit/delete, through reload/API reads, in teaching-load projections, and in the final assignment snapshot. Weekly projection uses the configured teaching hours.

### Recruitment workflow

The automated workflow completed:

`Applicant submission → Secretary review → transfer → Dean interview schedule/pass → demo schedule/pass → psych schedule → applicant receipt upload → psych completion → final assignment/hire`

The test also verified notification/status propagation, department restrictions, room/schedule data, capacity decrement, idempotent final hiring, automatic load closure at zero capacity, over-capacity rejection, and the applicant's final current-load view.

### Dynamic requirements

Verified required versus optional behavior, two independent posting checklists, add/edit/remove, refresh persistence, published-posting change confirmation, optional-to-required and required-to-optional changes, missing required rejection, optional omission acceptance, soft-deactivation after removal, and preservation of already submitted document history.

## Confirmed defects and security findings

| ID | Severity | Finding | Evidence/result | Recommended correction |
|---|---|---|---|---|
| SEC-01 | Critical | Two deployed diagnostic pages create an authenticated Secretary session without credentials. | Reproduced against `admin/api/test_secretary_api.php` and `admin/test_secretary_transfer_notification.php`; a protected API becomes accessible afterward. | Remove these files from deployed code immediately or hard-deny outside a local CLI/test environment. Invalidate current sessions. |
| SEC-02 | Critical | A non-admin staff member can update their own `role` to Admin through `admin/api/users.php`. | Direct PUT succeeds because only record ownership is checked; role/status/password fields remain writable. | Allow non-admin self-service only for an explicit safe-field allowlist. Require Admin for role, department, status, and password-administration fields. |
| SEC-03 | Critical | SMTP credentials are hardcoded in tracked email helper source. | Static source inspection. The credential value is intentionally omitted from this report. | Revoke/rotate the exposed credential, purge it from history, and load secrets from an untracked environment or secret manager. |
| SEC-04 | High | Applicant passwords are stored and compared as plaintext, and verification debugging logs POST/session data containing password and code values. | Login uses direct string comparison; registration/change/reset store plaintext; `public/verify.php` logs the full pending signup. | Migrate all applicant passwords with `password_hash`/`password_verify`, support a one-time legacy upgrade, and remove/redact sensitive logging. |
| SEC-05 | High | Login does not rotate the PHP session identifier. | The pre-login and post-login `PHPSESSID` values are identical. | Call `session_regenerate_id(true)` immediately after successful staff/applicant authentication. |
| SEC-06 | High | Several authenticated applicant mutations have no CSRF validation. | Static inspection of cancellation, deletion, attachment, psych-receipt, and general profile mutation routes; only the main application POST consistently validates the token. | Add a shared CSRF guard to every state-changing POST/PUT/DELETE route and send the token from fetch/forms. |
| DATA-01 | High | Duplicate applications to the same teaching load are accepted by the backend. | A second application for the same `(user_id, job_id)` succeeds; no matching unique constraint exists. | Add a transactional backend existence check and a unique database index, with a clear conflict response. Define whether cancelled/rejected records permit reapplication. |
| ROUTE-01 | Medium | Direct anonymous access to protected pages redirects through the project-root index and ends at 404 when the project directory contains an encoded space. | Reproduced for admin and applicant protected pages. | Redirect directly to the canonical `/public/index.php`, or normalize decoded URL paths in root routing. |
| FUNC-01 | Medium | Applicant phone edits do not persist correctly. | The visible `#profilePhone` validates, but save copies the first unscoped `input[name="applicant_num"]`, which is the earlier hidden field. | Scope the copy to `#profilePhone`/`#profileMainContent`, or remove the duplicate hidden field/name. |
| DATA-02 | Medium | Removed AI candidate-ranking data remains in the database. | UI and obsolete routes are gone, but `candidate_rankings` still exists and contained 14 rows in the inspected source database. | Confirm retention requirements, export if legally needed, then migrate/drop or anonymize the obsolete table. |
| API-01 | Low | `admin/api/dashboard_stats.php` returns HTTP 200 for an anonymous unauthorized response. | The response body says unauthorized, but status remains 200. | Set HTTP 401 or 403 before returning the JSON error. |
| UI-01 | Low | Staff notifications and profile dropdowns can remain open simultaneously. | Opening the profile menu stops event propagation and does not close the notification menu. | Explicitly close the peer dropdown in each toggle handler. |
| UX-01 | Low | Applicant email appears editable despite UI copy implying it is immutable. | The page removes read-only behavior and the profile endpoint accepts an email update. | Either enforce immutability in UI and backend or document/reverify email-change behavior. |

## Remediation update — 2026-10-23

The following findings were addressed after this report was captured:

| ID | Status | Change |
|---|---|---|
| SEC-01 | Fixed | Diagnostic session-creation endpoints now return 404; the unauthenticated debug panel was removed. |
| SEC-02 | Fixed | Non-admin staff cannot change `role`, `department`, `status`, or `password` through the users API. |
| SEC-03 | Code fixed | SMTP credentials were removed from tracked source and are read from `NCHIRE_SMTP_*` environment variables. The leaked credential must still be rotated in the mail provider. |
| SEC-04 | Fixed | Applicant passwords are stored with bcrypt (`PASSWORD_BCRYPT`); legacy plaintext credentials upgrade automatically on the next successful login. Password/code logging was removed. |
| SEC-05 | Fixed | Admin and applicant logins call `session_regenerate_id(true)`. |
| SEC-06 | Fixed for applicant mutations | CSRF validation now covers profile, cancellation, attachment, psych-receipt, draft, notification, and password-change requests. |
| DATA-01 | Fixed at application layer | Duplicate active applications to the same teaching load are rejected. Existing seed duplicates prevent adding a blanket unique index without a data migration. |
| ROUTE-01 | Fixed | Protected-page redirects and the default test URL no longer depend on the encoded project-root path. |
| FUNC-01 | Fixed | Phone edits now use the visible phone field and persist in `+639XXXXXXXXX` form. |
| API-01 | Fixed | Unauthorized dashboard-statistics requests return HTTP 401. |
| UI-01 | Fixed | Opening one staff header dropdown closes the other. |
| UX-01 | Fixed | Admin user-email editing stays read-only, matching the UI copy. |

The portable PHP runner is now `tests/run_php_tests.mjs`; the PowerShell script remains available for Windows.

## Negative and edge coverage

Covered empty required forms, invalid credentials, inactive users, invalid email, name sanitization, weak/mismatched passwords, duplicate registration, invalid verification code, unknown forgot-password account, multiple tabs, refresh/persistence, logout, unauthorized pages/APIs, missing/invalid CSRF, cross-role actions, cross-department actions, invalid uploads, optional omissions, incomplete profile, banned applicant, full-time qualification rules, closed/expired postings, duplicate application, invalid/negative/large Master's units, modal cancel paths, repeated final hiring, and capacity exhaustion.

The suite records screenshots, videos, and traces for failing/expected-failing tests. It avoids fixed waits for application behavior and uses role/label/id locators plus observable state assertions.

## Scope limitations

- Full state-changing regression runs once in Chromium desktop; Firefox, WebKit, tablet, and mobile run role/navigation/modal/responsive smoke coverage. This avoids multiplying destructive fixture transitions while still checking engine/device compatibility.
- Real SMTP delivery is suppressed in `NCHIRE_TEST_MODE`; account flows and success/failure handling are tested deterministically. The discovered SMTP secret was not used.
- Groq connectivity was tested once, and the chatbot widget/open-close/focus behavior was tested. Free-form model answer quality is nondeterministic and is not asserted as exact text.
- Session expiration was assessed through logout and invalid-session behavior; the application does not expose a short configurable expiry suitable for a deterministic wall-clock expiry test.
- The source database was inspected read-only to understand schema/roles. All CRUD and workflow mutations were redirected to the isolated database.

## Automation artifacts

- Playwright configuration: `playwright.config.ts`
- Browser suites: `tests/e2e/`
- Isolated schema/fixture setup: `tests/setup_e2e_database.php`
- PHP test runner: `tests/run_php_tests.mjs` (PowerShell alternative: `tests/run_php_tests.ps1`)
- Upload fixtures: `tests/fixtures/`
- HTML report: `playwright-report/index.html`
- Machine-readable report: `test-results/playwright-results.json`
- Failure evidence: `test-results/playwright/`

Run instructions and synthetic account details are in the root `TESTING.md`.
