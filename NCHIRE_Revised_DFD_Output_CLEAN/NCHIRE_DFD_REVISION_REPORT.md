# NCHIRE Revised Data Flow Diagrams — Final Audit Report

## Corrected deliverable structure

The revised package contains exactly three diagram pages:

1. **Level 0 — Context Diagram**
2. **Level 1 — Main System Processes**
3. **Level 2 — Core Recruitment Workflow**

Level 0 retains the clean four-corner arrangement of the original context diagram. Level 1 is a single-page modular view. Level 2 is a single-page, two-row workflow rather than a collection of decomposition tabs.

## Removed obsolete AI functionality

The diagrams do not contain the discontinued:

- AI Resume Analyzer, resume parsing, extracted profiles, summaries, or resume scoring
- AI applicant–job matching, compatibility percentages, candidate ranking, or recommendations
- AI Recruitment Insights, predictive analytics, recommendations, or insight reports

No invented AI data store is shown.

The **AI Recruitment Assistant chatbot remains represented** because the current implementation contains its active API endpoint and services, uses role-scoped recruitment data for Secretary and Dean users, calls the Groq language-model service, and records chatbot activity.

## Preserved and revised processes

Level 1 uses the following verified process hierarchy:

- **1.0** Manage Accounts & Access
- **2.0** Maintain Applicant Profiles
- **3.0** Manage Teaching Loads & Requirements
- **4.0** Submit & Maintain Applications
- **5.0** Review & Route Applications
- **6.0** Schedule & Evaluate Recruitment
- **7.0** Deliver & Track Notifications
- **8.0** Administer Staff & Monitor Operations
- **9.0** Provide AI Recruitment Assistant

Level 2 decomposes the central recruitment workflow on one page:

- **4.1** Validate Job, Profile, Eligibility & Ban
- **4.2** Submit, Resubmit or Cancel Application
- **5.1** Inspect Application & Documents
- **5.2** Record Secretary Decision
- **6.1** Schedule or Reschedule Interview
- **6.2** Record Interview & Schedule Demonstration
- **6.3** Record Demonstration & Schedule Psychological Exam
- **6.4** Receive Psychological-Exam Receipt
- **6.5** Record Final or Rejection Decision
- **7.1** Store & Deliver Notifications

## ERD data stores represented

The diagrams preserve the ERD identifiers and exact table names:

- **D1** `applicants`
- **D2** `admin_users`
- **D4** `job`
- **D5** `job_applicants`
- **D6** `application_bans`
- **D7** `workflow_history`
- **D8** `user_draft_documents`
- **D9** `user_education`
- **D10** `user_experience`
- **D11** `user_skills`
- **D12** `notifications`
- **D13** `admin_notifications`
- **D14** `admin_activity`
- **D15** `rooms`

**D3 `admin_user` is not drawn** because no current runtime route was verified as using it. Its identifier remains reserved so all other D-numbers stay consistent with the ERD.

## Source-code and ERD differences

The current code uses the following runtime tables that are not present in the supplied ERD:

- `teaching_load_requirements`
- `applicant_requirement_submissions`
- `application_document_versions`
- `teaching_assignments`
- `user_qualifications`
- `graduate_unit_rate_rules`

They are not shown as DFD data stores because the ERD was required to be authoritative. Their behavior is summarized through process/data-flow labels only.

A `candidate_rankings` table exists outside the supplied ERD, but no active runtime reference was verified; it is not shown. No runtime use of ERD table `admin_user` was verified.

Additional implementation observations:

- The database role is named **Department Head**, while the current interface presents this workflow actor as **Dean**.
- Secretary routing records `workflow_history`; equivalent Dean-stage history writes were not verified.
- Applicant cancellation updates the applicant ban state, while Secretary or Dean rejection writes `application_bans`.
- A psychological-receipt notification route uses the applicant-notification table with a staff-like recipient value, while staff notification views use `admin_notifications`.
- Some dashboard code still checks the older `department_head_review` status while the current workflow uses `waiting_interview_schedule`.

## Layout and structural validation

Automated validation of all standalone files and the combined file confirmed:

- Standalone page counts: **1 / 1 / 1**
- Combined-file page count: **3**
- No broken connector references
- No overlapping diagram shapes
- No connector paths crossing unrelated shapes
- No detected connector-to-connector intersections
- No direct external-entity-to-data-store connections
- Every Level 1 and Level 2 process has meaningful input and output
- Level 0 contains no database stores
- All displayed stores match an exact ERD identifier/table-name pair
- Obsolete resume-analysis, ranking/matching, and recruitment-insight terms are absent

High-resolution PNG previews are included for all three pages.

## Generated files

- `NCHIRE_DFD_LEVEL_0_REVISED.drawio`
- `NCHIRE_DFD_LEVEL_1_REVISED.drawio`
- `NCHIRE_DFD_LEVEL_2_REVISED.drawio`
- `NCHIRE_COMPLETE_REVISED_DFD.drawio`
- `PNG/NCHIRE_DFD_LEVEL_0_REVISED.png`
- `PNG/NCHIRE_DFD_LEVEL_1_REVISED.png`
- `PNG/NCHIRE_DFD_LEVEL_2_REVISED.png`

All original reference files remain unchanged.
