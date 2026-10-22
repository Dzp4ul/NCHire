# NCHIRE Revised Data Flow Diagram Report

Prepared: 22 October 2026  
System: NCHIRE — AI-Powered Cloud-Based Recruitment and Applicant Management System for Norzagaray College

## 1. Result

The DFD set was rebuilt from the current PHP/MySQL implementation, the two supplied draw.io references, and the official ERD PDF. The editable deliverables use a consistent black-and-white academic style, orthogonal connectors, stable process numbering, and ERD-aligned data-store identifiers.

The combined file contains 10 pages:

1. Level 0 Context Diagram
2. Level 1 Main Processes
3. Level 2 — 1.0 Manage Accounts & Access
4. Level 2 — 3.0 Manage Teaching Loads & Requirements
5. Level 2 — 4.0 Submit & Maintain Applications
6. Level 2 — 5.0 Review & Route Applications
7. Level 2 — 6.0 Schedule & Evaluate Recruitment
8. Level 2 — 7.0 Deliver & Track Notifications
9. Level 2 — 8.0 Administer Staff & Monitor Operations
10. Level 2 — 9.0 Provide AI Recruitment Assistant

Process 2.0 is not decomposed into a separate Level 2 page because its current profile-maintenance behavior is already represented accurately at Level 1; adding a page would have introduced unnecessary or invented detail.

## 2. Removed obsolete AI content

The old diagrams contained processes and flows for AI resume analysis, resume extraction and summaries, automated candidate matching/ranking, compatibility results, and recruitment insights/reports. Those processes, flows, and non-ERD stores were removed from every revised diagram.

The live database still contains a `candidate_rankings` table, but no current PHP route reads or writes it, and it is absent from the official ERD. It is therefore treated as a dormant legacy artifact and is not shown.

## 3. Preserved and revised current functionality

- Applicant registration, email verification, login, password reset, and role session establishment.
- Applicant personal profile, education, work experience, skills, and profile-document maintenance.
- Secretary/Dean teaching-load creation, editing, publication status, capacity, and requirement configuration.
- Applicant teaching-load selection, eligibility checks, draft/reusable documents, first submission, requested replacement, status tracking, and cancellation.
- Secretary document review, replacement request, transfer to Dean, rejection, ban recording, workflow history, and activity logging.
- Dean interview scheduling/result, demonstration scheduling/result, psychological-examination scheduling/receipt, rejection, final passing, and teaching-load capacity update.
- Applicant and staff in-app notifications plus PHPMailer/Gmail SMTP delivery.
- Administrator staff-account management, dashboard aggregation, and activity monitoring.
- The verified AI Recruitment Assistant chatbot for active Secretary and Department Head accounts. It reads role-scoped recruitment data, calls the Groq language-model API when required, keeps the last messages in the authenticated PHP session, and records usage in `admin_activity`. It has no chatbot database table and cannot update recruitment records.

## 4. Final Level 1 hierarchy

| Process | Current function |
|---|---|
| 1.0 | Manage Accounts & Access |
| 2.0 | Maintain Applicant Profiles |
| 3.0 | Manage Teaching Loads & Requirements |
| 4.0 | Submit & Maintain Applications |
| 5.0 | Review & Route Applications |
| 6.0 | Schedule & Evaluate Recruitment |
| 7.0 | Deliver & Track Notifications |
| 8.0 | Administer Staff & Monitor Operations |
| 9.0 | Provide AI Recruitment Assistant |

The principal recruitment sequence is 4.0 submission → 5.0 Secretary review → 6.0 Dean workflow → 7.0 notification delivery.

## 5. Level 2 numbering

- 1.1–1.5: applicant registration, verification, authentication, password maintenance, and authorized session establishment.
- 3.1–3.6: staff/department authorization, teaching-load maintenance, requirement configuration, status/deletion, publication, and activity logging.
- 4.1–4.6: load selection, eligibility/ban/vacancy validation, document loading, application creation, replacement submission, and tracking/cancellation.
- 5.1–5.5: Secretary queue, inspection, replacement request, transfer to Dean, and rejection/ban.
- 6.1–6.8: department queue, interview, demonstration, psychological-examination receipt, final passing, and rejection/ban.
- 7.1–7.6: notification event intake, recipient resolution, in-app storage, email delivery, feed retrieval, and read-state updates.
- 8.1–8.5: Admin authorization, staff-account management, temporary password delivery, dashboard aggregation, and operations monitoring.
- 9.1–9.7: staff authorization, intent classification, scoped retrieval, answer/prompt construction, Groq generation, session response/history, and usage logging.

## 6. ERD data stores represented

Numbering follows the official ERD list and remains stable in every diagram.

| ID | Exact ERD table | Diagram use |
|---|---|---|
| D1 | `applicants` | applicant identity, credentials, profile, and ban state |
| D2 | `admin_users` | active staff credentials, role, department, and account management |
| D3 | `admin_user` | reserved but intentionally not drawn; no current runtime reference |
| D4 | `job` | teaching loads, deadlines, status, vacancies, and capacity |
| D5 | `job_applicants` | application documents, workflow stage, schedules, and results |
| D6 | `application_bans` | Secretary/Dean rejection-ban audit records |
| D7 | `workflow_history` | Secretary workflow events |
| D8 | `user_draft_documents` | saved applicant draft documents |
| D9 | `user_education` | education profile and application eligibility context |
| D10 | `user_experience` | experience profile and eligibility context |
| D11 | `user_skills` | applicant skill records and chatbot context |
| D12 | `notifications` | applicant in-app notifications |
| D13 | `admin_notifications` | staff in-app notifications |
| D14 | `admin_activity` | staff actions, administration audit, and chatbot usage |
| D15 | `rooms` | active interview-room selection |

Fourteen ERD tables are shown. D3 remains reserved so that all later identifiers still match the authoritative ERD numbering.

## 7. Source-code and ERD differences

The running schema contains active implementation tables that are not in the official ERD. In accordance with the requirement that every DFD store must be an ERD table, these are disclosed but not drawn:

- `teaching_load_requirements` — per-teaching-load dynamic document checklist.
- `applicant_requirement_submissions` — applicant submissions for that checklist.
- `application_document_versions` — reusable/replacement document history.
- `teaching_assignments` — final teaching assignment created before D4/D5 completion.
- `user_qualifications` — applicant qualification/profile extension.
- `graduate_unit_rate_rules` — salary/rate computation rules.

Other identified implementation differences or defects:

- `admin_user` exists in the ERD and live database, but current routes use `admin_users` exclusively.
- `candidate_rankings` exists in the live database but has no current PHP reference and is outside the ERD.
- Secretary actions write `workflow_history`; the implemented Dean-stage action endpoint currently does not write D7 events.
- Applicant cancellation updates the ban fields in D1 but does not create a D6 row; Secretary/Dean rejection does create D6 records. The DFD reflects this distinction.
- The psychological-examination receipt endpoint writes a staff-oriented message to D12 with `user_email='admin'`, while the staff notification UI normally reads D13. This appears to be a notification-routing mismatch.
- Some dashboard logic still references the older `department_head_review` stage, while the current Secretary transfer sets `waiting_interview_schedule`.
- Applicant login currently compares the stored applicant password directly; staff login uses password hashes. This is an implementation security concern, not a DFD store change.

## 8. Verification performed

- Parsed all four final draw.io files as UTF-8 XML.
- Verified required page counts: Level 0 = 1, Level 1 = 1, Level 2 = 8, combined = 10.
- Verified 258 vertices and 287 labeled flows in the combined file.
- Verified every connector source/target reference.
- Verified every displayed store identifier/table pair against the 15-table ERD register.
- Verified Level 0 contains no data store.
- Verified there are no direct external-entity-to-store or store-to-store connectors.
- Verified every process has at least one input and one output.
- Verified no solid symbols overlap and no symbol is outside its page bounds.
- Verified removed legacy-AI terminology is absent from the revised diagram XML.
- PHP lint: 109 current PHP files checked, 0 syntax failures.
- Groq connectivity test: passed on 22 October 2026.
- Read-only live-schema scan and role scan completed; active roles are Admin, Department Head, and Secretary.
- High-resolution PNG previews were generated from the final page geometry and visually inspected.

## 9. Verification limits

- Real SMTP messages were not sent, to avoid delivering test emails to actual accounts. Email configuration and call paths were inspected instead.
- The state-changing end-to-end suite was not rerun against the working database. The supplied isolated test harness rebuilds an `_e2e` database, so it was left untouched; its definitions were inspected, while source inspection, PHP lint, and the non-mutating Groq connectivity test were used for this revision.
- The non-ERD persistence tables listed above could not be represented as DFD data stores without violating the official ERD constraint. Their affected processes include explicit ERD-constraint notes.
- diagrams.net’s hosted headless export endpoint rejected the automated renderer with HTTP 403. The PNGs were therefore rendered locally from the exact draw.io page geometry. The `.drawio` files are the authoritative editable deliverables.

## 10. Deliverables

- `NCHIRE_DFD_LEVEL_0_REVISED.drawio`
- `NCHIRE_DFD_LEVEL_1_REVISED.drawio`
- `NCHIRE_DFD_LEVEL_2_REVISED.drawio`
- `NCHIRE_COMPLETE_REVISED_DFD.drawio`
- `PNG/NCHIRE_DFD_LEVEL_0_REVISED.png`
- `PNG/NCHIRE_DFD_LEVEL_1_REVISED.png`
- Eight Level 2 PNGs in `PNG/`

The original reference files were not modified.
