# Secretary Workflow System

## Overview
New workflow system where applications go through Secretary review before reaching Department Heads. The Secretary reviews documents and can Transfer to Department Head, Request Resubmission, or Reject applications.

## Workflow Stages

### Application Flow
```
Applicant Submits Application
         ↓
[Secretary Review] ← Applications start here
         ↓
Secretary Actions:
  • Transfer to Department Head → [Department Head Review]
  • Request Resubmission → Stays in [Secretary Review]
  • Reject → [Rejected]
         ↓
[Department Head Review] ← Only transferred applications
         ↓
Department Head Actions:
  • Schedule Interview
  • Demo Teaching
  • Psychological Exam
  • Hire / Reject
```

## Database Schema

### New Columns in `job_applicants` Table
- `workflow_stage` - ENUM tracking current stage
  - `secretary_review` - With secretary for document review
  - `secretary_approved` - Secretary approved (not used, goes directly to department_head_review)
  - `department_head_review` - With department head
  - `interview_scheduled` - Interview scheduled by dept head
  - `interview_completed` - Interview completed
  - `demo_scheduled` - Demo teaching scheduled
  - `demo_completed` - Demo completed
  - `psych_scheduled` - Psych exam scheduled
  - `psych_completed` - Psych exam completed
  - `initially_hired` - Initially hired
  - `permanently_hired` - Permanently hired
  - `hired` - Final hired status
  - `rejected` - Rejected at any stage

- `secretary_id` - INT, which secretary reviewed the application
- `secretary_review_date` - DATETIME, when secretary took action
- `secretary_notes` - TEXT, secretary's notes
- `transferred_to_dept_head_date` - DATETIME, when transferred to dept head

### New Table: `workflow_history`
Audit trail of all workflow changes:
- `id` - Primary key
- `application_id` - Foreign key to job_applicants
- `from_stage` - Previous workflow stage
- `to_stage` - New workflow stage
- `action_by_id` - Admin user ID who took action
- `action_by_role` - Role of admin (Secretary, Department Head, etc.)
- `action_type` - Type of action (transfer, reject, resubmission_requested, etc.)
- `notes` - Action notes
- `created_at` - Timestamp

### Updated `admin_users` Table
- `role` ENUM now includes 'Secretary'

## Secretary Role

### Responsibilities
1. **Document Review**: Review all submitted documents for completeness and quality
2. **Requirements Verification**: Ensure all required documents are present
3. **Initial Screening**: Basic qualification check

### Available Actions

#### 1. Transfer to Department Head
**When to use**: Documents are complete and requirements met
- Updates `workflow_stage` to `department_head_review`
- Records `secretary_id`, `secretary_review_date`, `transferred_to_dept_head_date`
- Logs action in `workflow_history`
- Sends email to applicant notifying them of transfer
- **Result**: Application moves to Department Head's queue

#### 2. Request Resubmission
**When to use**: Documents are missing, incomplete, or need correction
- Keeps `workflow_stage` as `secretary_review`
- Updates `status` to `Resubmission Required`
- Stores list of requested documents in `resubmission_documents` (JSON)
- Records reason in `resubmission_reason`
- Sends email to applicant listing required documents
- **Result**: Applicant can resubmit through the application wizard

#### 3. Reject Application
**When to use**: Applicant does not meet basic requirements
- Updates `workflow_stage` to `rejected`
- Updates `status` to `Rejected`
- Stores rejection reason
- Sends rejection email to applicant
- **Result**: Application moves to Archive, workflow ends

## Department Head Role Changes

### New Filtering
Department Heads now only see applications with:
- `workflow_stage` IN ('department_head_review', 'interview_scheduled', 'interview_completed', etc.)
- `assigned_to_department` matching their department

### First Available Action
**Schedule Interview** - No longer needs to approve documents first, as Secretary already verified them

## API Endpoints

### Secretary Actions API
**File**: `admin/api/secretary_actions.php`

**Authentication**: Session-based, requires `admin_role = 'Secretary'`

**Actions**:
1. `transfer_to_dept_head`
   - POST params: `application_id`, `notes` (optional)
   
2. `request_resubmission`
   - POST params: `application_id`, `documents[]`, `reason`
   
3. `reject`
   - POST params: `application_id`, `reason`

**Response**: JSON
```json
{
  "success": true/false,
  "message": "Action result message"
}
```

### Updated Applicants API
**File**: `admin/api/applicants.php`

**Changes**: Now filters applications based on role:
- **Secretary**: Only `workflow_stage = 'secretary_review'`
- **Department Head**: Only `workflow_stage IN ('department_head_review', ...)`
- **HR Manager/Recruiter**: All applications in their department

## User Interface

### Secretary Navigation
New menu item: **Document Review**
- Shows all applications in `secretary_review` stage
- Displays application cards with basic info
- Click to view full application details

### Secretary Action Buttons
When viewing an application, Secretary sees:
1. **Transfer to Department Head** (Green) - Primary action
2. **Request Resubmission** (Orange) - Opens document selection modal
3. **Reject Application** (Red) - Opens rejection reason modal

### Department Head View
- No longer sees applications in `secretary_review` stage
- Only sees transferred applications
- First action is "Schedule Interview" (no document approval needed)

## Email Notifications

### Transfer Notification
**To**: Applicant
**Subject**: Application Under Review
**Content**: "Your application has been reviewed and forwarded to the department head..."

### Resubmission Notification
**To**: Applicant  
**Subject**: Document Resubmission Required
**Content**: Lists required documents and reason, link to resubmit

### Rejection Notification
**To**: Applicant
**Subject**: Application Status Update  
**Content**: Professional rejection message with reason

## Setup Instructions

### 1. Run Database Migration
```bash
php database/migrations/add_secretary_workflow.php
```

### 2. Create Secretary User Account
1. Login as Admin
2. Go to Users section
3. Create new user with role "Secretary"
4. Assign appropriate permissions

### 3. Test Workflow
1. Submit test application as applicant
2. Login as Secretary - see application in Document Review
3. Transfer to Department Head
4. Login as Department Head - see application in Applicants
5. Schedule Interview and continue workflow

## Benefits

### ✅ Improved Efficiency
- Department Heads only review pre-screened applications
- Reduces time spent on incomplete applications
- Clear separation of responsibilities

### ✅ Better Quality Control
- Dedicated document review step
- Consistent requirements verification
- Reduced errors and incomplete submissions

### ✅ Enhanced Tracking
- Complete workflow history
- Audit trail for compliance
- Clear accountability at each stage

### ✅ Better User Experience
- Faster initial feedback for applicants
- Clear communication about document issues
- Professional workflow management

## Troubleshooting

### Issue: Applications not appearing for Secretary
**Check**: workflow_stage = 'secretary_review'
**Fix**: Run migration again or manually update existing applications

### Issue: Department Head sees secretary_review applications
**Check**: API filtering logic in admin/api/applicants.php
**Fix**: Ensure role check is working correctly

### Issue: Transfer not working
**Check**: Secretary role assigned correctly, API permissions
**Fix**: Verify admin_role in session

## Future Enhancements

1. **Bulk Actions**: Transfer multiple applications at once
2. **Document Checklists**: Predefined document requirements per position
3. **Automated Screening**: Flag applications missing common documents
4. **Secretary Dashboard**: Statistics on review times, common issues
5. **Department Head Notifications**: Email when applications are transferred
6. **Workflow Templates**: Different workflows for different job types

## Files Modified

### Database
- `database/migrations/add_secretary_workflow.php` - Migration script

### Backend
- `admin/api/secretary_actions.php` - NEW: Secretary actions API
- `admin/api/applicants.php` - Updated: Role-based filtering
- `user/user.php` - Updated: Set workflow_stage on application submission

### Frontend
- `admin/index.php` - Updated: Add Secretary navigation
- `admin/admin.js` - To be updated: Add secretary action modals and handlers

### Documentation
- `docs/SECRETARY_WORKFLOW.md` - This file
