# NCHire System Flowchart Analysis

Analysis date: October 22, 2026

## Scope and method

The diagrams were derived from the current PHP source, JavaScript user interfaces, live MySQL schema, migration files, shared workflow helpers, and automated tests in this project. The implementation was treated as authoritative when older Markdown documentation used a different stage name or sequence.

The four diagrams use uncompressed Draw.io XML. Every symbol, label, and connector is an independent editable Draw.io cell. The combined file contains four pages with the requested page names.

## Implemented role boundaries

| Role in the diagrams | Implemented account/role | Recruitment scope |
|---|---|---|
| Applicant | `applicants` account | Maintains a profile, browses active teaching loads, applies, supplies documents, responds to resubmission, tracks progress, uploads the psychological-exam receipt, and views a final teaching assignment. |
| Secretary | `admin_users.role = 'Secretary'` | Manages teaching loads and their dynamic document checklists, reviews applications at `secretary_review`, requests document replacement, rejects, or transfers an application to the Dean workflow. |
| Dean | `admin_users.role = 'Department Head'`; displayed as Dean | Sees only assigned-department applications at Dean stages, conducts the interview/demo workflow, may reject a nonterminal application, reviews the psychological-exam receipt, and finalizes the teaching assignment. |
| Administrator | `admin_users.role = 'Admin'` | Monitors dashboard information and manages staff accounts. The Administrator is not part of application approval or hiring decisions. |

## 1. Applicant workflow

An applicant either signs in or registers. Registration validates the submitted identity/email/password data, sends a verification code, and creates the applicant record only after the code matches. Failed login can enter the temporary-password recovery route; a temporary password forces a strong-password change and a new login.

After login, the applicant can maintain personal details, education, work experience, skills, and qualifications. The applicant browses only active, unexpired teaching loads with available sections, opens a load to view its qualifications, salary projection, and dynamic document checklist, accepts the terms, and attempts to apply.

Submission is accepted only when the required personal fields and work experience exist, no active application ban applies, the load remains open with a vacancy, and any full-time education rule is satisfied. Required checklist documents must be supplied or reused from eligible stored documents. Uploads are checked for supported file type, MIME type, and a maximum size of 5 MB. A successful application starts with status `Submitted` and stage `secretary_review`.

The applicant tracks the application and notifications. If the Secretary requests resubmission, only the specifically requested documents are replaced; a valid response becomes `Resubmitted` and returns to Secretary review. The applicant may cancel, which sets stage `cancelled` and creates a four-month application restriction. After a passed demo, the applicant can upload a PDF/JPG/PNG psychological-exam receipt; a valid upload sets `psych_completed`. A passed application exposes its created subject/units/hours/compensation record in My Teaching Load.

## 2. Secretary workflow

The Secretary signs in through the staff authentication path. Only an Active staff account is accepted, and a temporary password forces a password change and re-login.

The dashboard provides all-department statistics, activity, notifications, and teaching-load management. The Secretary can view/filter loads; create, edit, close, reopen, or delete them; and configure required or optional application documents. When applications already exist, requirement changes require impact confirmation. Newly required documents are recorded and affected applicants are notified.

The actionable Secretary queue is restricted to `secretary_review`. The Secretary reviews applicant identity, education, experience, skills, qualifications, submitted documents, and completion totals. The implemented outcomes are:

- Transfer: records optional notes and moves the application to `waiting_interview_schedule`, notifying the assigned Dean and applicant.
- Resubmission: requires at least one specifically selected document and a reason, keeps the application at `secretary_review`, and notifies the applicant.
- Reject: records a reason, sets `rejected`, archives the record, and creates a four-month application restriction.

After transfer, the Secretary can monitor later stages in view-only mode. Rejected and cancelled records are available in the Secretary archive.

## 3. Dean workflow

The Dean uses the `Department Head` staff role and the same Active-account/forced-password-change rules as the Secretary. Dashboard and applicant queries are limited to the Dean's assigned department, including the Computing Studies/Computer Science alias handled by the source.

The Dean can manage teaching loads. Dynamic application requirements are editable only when the load's department matches the Dean's department. For a transferred application, the Dean reviews the full applicant record and then performs the stage-guarded workflow:

1. Schedule an interview from `waiting_interview_schedule` or the legacy `department_head_review` stage.
2. Reschedule or approve an `interview_scheduled` application. Approval produces `interview_completed`.
3. Schedule a demo from `interview_completed`.
4. Reschedule or approve a `demo_scheduled` application. Approval produces `demo_completed`.
5. Receive the applicant's psychological-exam receipt (`psych_completed`).
6. Mark the application passed. The shared finalizer creates one teaching assignment, snapshots the workload/compensation, decrements `available_sections`, closes the load when capacity reaches zero, and sets the application to `passed`.

Interview scheduling enforces a future weekday, an active room, and a time from 8:00 AM through 4:00 PM. Demo scheduling enforces a future weekday, location/room data, and the same time range. The final pass is blocked without a psychological-exam receipt or when no teaching-load vacancy remains. The Dean can reject at any nonterminal Dean stage; rejection archives the application and creates the four-month restriction. Paired `R` connectors in the diagram lead to this common rejection routine.

The backend also implements and tests `schedule_psych` / `schedule_psych_exam`, which writes `psych_scheduled` and notifies the applicant. The diagram includes that implemented system action. The present `admin.js` action-button logic, however, does not show a separate psych-scheduling button after `demo_completed`; it instead displays the final-pass button disabled until a receipt exists. This discrepancy is retained here rather than hidden.

## 4. Administrator workflow

The Administrator uses the staff authentication path, including Active-account enforcement, temporary-password recovery, and forced password change.

The implemented Administrator navigation consists of the dashboard and staff user management. The dashboard monitors all-department totals, vacancies, recent teaching loads/applicants, activity, notifications, and chart/filter data. Staff management allows the Administrator to view/search/filter accounts, create an account, and edit name, email, fixed role, Dean department, account status, password, phone, or profile image.

Creation validates required data, a unique email, and an optional supported image no larger than 5 MB. It creates an Active account with a hashed temporary password, requires a password change, and emails credentials. Editing prevents self-deactivation. A retained Admin-only DELETE API prevents self-deletion and deletion of the last Administrator, although the current UI intentionally exposes no Delete control. Paired `U` connectors in the diagram keep this API-only return path readable.

## Core application stages verified in code

| Event | Status/stage result |
|---|---|
| New application | `Submitted` / `secretary_review` |
| Secretary requests replacements | `Resubmission Required` / `secretary_review` |
| Applicant replaces requested files | `Resubmitted` / `secretary_review` |
| Secretary transfers | `Waiting for Interview Schedule` / `waiting_interview_schedule` |
| Dean schedules interview | `Interview Scheduled` / `interview_scheduled` |
| Dean approves interview | `Interview Passed` / `interview_completed` |
| Dean schedules demo | `Demo Scheduled` / `demo_scheduled` |
| Dean approves demo | `Demo Passed` / `demo_completed` |
| Backend psych scheduling action | `Psychological Exam` / `psych_scheduled` |
| Applicant uploads psych receipt | `Psychological Exam` / `psych_completed` |
| Dean finalizes assignment | `Passed` / `passed` |
| Secretary/Dean rejection | `Rejected` / `rejected` |
| Applicant cancellation | `Cancelled` / `cancelled` |

`department_head_review`, `initially_hired`, `permanently_hired`, and `hired` remain in schema/API compatibility paths. The current Secretary transfer writes `waiting_interview_schedule`, and all current final-hiring action aliases call the same teaching-assignment finalizer and resolve to the passed workflow.

## Source modules used

### Authentication and account lifecycle

- `public/index.php`
- `public/verify.php`
- `public/process_forgot_password.php`
- `config/auth.php`

### Applicant UI and endpoints

- `user/user.php`
- `user/user_application.php`
- `user/save_profile_data.php`
- `user/get_application_details.php`
- `user/get_user_applications.php`
- `user/cancel_application.php`
- `user/upload_psych_receipt.php`
- `user/upload_requirement_documents.php`
- `user/includes/profile-handlers.php`
- `user/get_jobs_paginated.php`

### Secretary and Dean UI/endpoints

- `admin/index.php`
- `admin/admin.js`
- `admin/api/applicants.php`
- `admin/api/secretary_actions.php`
- `admin/view_applicant.php`
- `admin/process_applicant_action.php`
- `admin/get_archive.php`
- `admin/add_job.php`
- `admin/update_job.php`
- `admin/delete_job.php`
- `admin/toggle_job_status.php`
- `admin/gets_job.php`

### Administrator endpoints

- `admin/index.php`
- `admin/admin.js`
- `admin/api/users.php`

### Shared workflow, schema, and verification sources

- `shared/helpers/recruitment.php`
- `shared/helpers/application_documents.php`
- `shared/helpers/dynamic_application_requirements.php`
- Current MySQL table definitions for `applicants`, `admin_users`, `job`, `job_applicants`, profile tables, requirement/document tables, bans, history, notifications, and teaching assignments
- Relevant files under `database/migrations/`
- `tests/e2e/application-workflow.spec.ts`
- `tests/e2e/teaching-loads.spec.ts`
- `tests/e2e/profile-salary.spec.ts`
- `tests/e2e/auth-account.spec.ts`
- `tests/e2e/role-security.spec.ts`
- `tests/e2e/staff-interactions.spec.ts`
- `tests/e2e/admin-users.spec.ts`
- `tests/TeachingLoadWorkflowTest.php`
- `tests/ProfileEducationCrudTest.php`
- `tests/ApplicationDocumentReuseTest.php`
- `tests/DynamicApplicationRequirementsTest.php`
- `tests/SalaryProjectionTest.php`

## Features deliberately excluded

- Administrator approval, rejection, interview, demo, or hiring actions: not authorized or exposed for Admin.
- Administrator teaching-load management and archive pages: not present in the Admin navigation.
- A reports module or system-settings module: no implemented modules were found.
- Candidate ranking: ranking-related storage remains, but the current ranking page/API is removed or unavailable and tests assert that it cannot be accessed.
- Granular permission editing: the system assigns fixed roles; it does not provide a per-permission editor.
- A staff action that separately marks each applicant qualification as verified: no such current action was found.
- A separate Secretary “requirements approved” flag: transfer is the implemented positive review outcome.

## Verified uncertainties and implementation caveats

- The registration email says a code expires after 15 minutes, but `public/verify.php` does not enforce a server-side expiration timestamp. The diagrams therefore show only “Code matches?”
- The application endpoint has no duplicate-application guard or unique database constraint for the same applicant/load. The end-to-end suite records this as a known defect; no “already applied” decision was invented.
- Applicant passwords are compared as stored plaintext in the current login implementation, while staff passwords use hashes. The diagrams show authentication behavior without implying a storage method.
- The psychological-receipt upload endpoint checks ownership and file validity but has no workflow-stage guard. The diagram follows the intended visible sequence after demo completion.
- The cancellation UI suggests future reapplication, while the backend immediately creates a four-month restriction. The diagram follows the backend result.
- Dean checklist edits are department-scoped, but some teaching-load CRUD endpoints are role-gated without consistently applying the same department restriction. The diagram states the confirmed checklist scope and does not claim broader permission enforcement.
- Older documentation names the post-Secretary stage `department_head_review`; the live transfer endpoint uses `waiting_interview_schedule`. The diagrams use the live value.

## Quality assurance completed

- Parsed all five `.drawio` files as well-formed XML.
- Verified one diagram page in every standalone file and four correctly named pages in the combined file.
- Verified every edge has valid editable source and target cells.
- Automated geometry validation found zero out-of-bounds shapes, shape overlaps, non-orthogonal segments, connector-to-shape intersections, connector crossings, or connector overlaps.
- Browser-rendered all four complete pages from the same geometry and visually inspected alignment, spacing, labels, arrows, and continuation connectors.
- Opened every standalone file and the combined file in the live diagrams.net editor. All titles loaded without parser errors; the combined file displayed all four requested page tabs.

Diagram size summary:

| Page | Editable nodes | Editable connectors | Canvas |
|---|---:|---:|---:|
| Applicant | 58 | 71 | 2400 × 4580 |
| Secretary | 37 | 44 | 2400 × 2860 |
| Dean | 60 | 68 | 2500 × 4540 |
| Administrator | 32 | 39 | 2200 × 2650 |
