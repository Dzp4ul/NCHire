# NCHire Application Workflow Update

## Overview
This document outlines the changes made to implement a comprehensive 6-stage hiring workflow process for the NCHire application system.

## Application Workflow Stages

### User/Applicant Side

#### Stage 1: Application Submission
**Step 1 - Personal Information, Work Experience & Skills**
- Combined form collecting:
  - Personal Information (Name, Email, Contact, Address)
  - Work Experience (Job Title, Company, Location, Start/End Date, Description)
  - Skills & Competencies (Optional skill listing)
- All information collected in a single comprehensive step
- Validation ensures required fields are completed before proceeding

**Step 2 - Document Requirements**
- Required Documents:
  - Application Letter
  - Resume/CV
  - Transcript of Records (TOR)
  - Diploma/Certificate
  - Professional License
  - Certificate of Employment
  - Seminars, Trainings & Other Certificates
- Optional Document:
  - **Masteral Certificate** (newly added)
- File format: PDF, DOC, DOCX, JPG, PNG
- Maximum size: 5MB per file

#### Stage 2-6: Status Tracking
After submission, applicants can track their application through the following stages:
1. **Pending** - Initial application submitted, under review
2. **Interview Scheduled** - Interview date and time set by admin
3. **Demo Scheduled** - Demo teaching scheduled (for teaching positions)
4. **Psychological Exam** - Scheduled for psychological examination
5. **Initially Hired** - Marked as initially hired, awaiting final approval
6. **Hired** - Fully hired and accepted

### Admin Side

#### New Action Options
Admins can now manage applicants through all workflow stages:

1. **Schedule Interview**
   - Set interview date and time
   - Add interview notes
   - Automatically notifies applicant

2. **Schedule Demo**
   - Set demo teaching date and time
   - Add demo notes
   - Notifies applicant of demo schedule

3. **Schedule Psychological Exam**
   - Set psychological exam date and time
   - Add exam notes
   - Notifies applicant to take exam and upload receipt

4. **Mark as Initially Hired**
   - Flag applicant as initially hired
   - Add notes about initial hiring
   - Notifies applicant of initial hiring status

5. **Request Resubmission** (existing)
   - Request specific document resubmissions
   - Add notes explaining requirements

6. **Reject Application** (existing)
   - Reject application with reason
   - Notifies applicant

7. **Hire Applicant** (existing)
   - Final hiring action
   - Add onboarding notes

## Database Changes

### New Fields Added to `job_applicants` Table

```sql
-- Optional masteral certificate
masteral_cert VARCHAR(255) DEFAULT NULL

-- Demo teaching schedule
demo_date DATETIME DEFAULT NULL
demo_notes TEXT DEFAULT NULL

-- Psychological exam
psych_exam_date DATETIME DEFAULT NULL
psych_exam_receipt VARCHAR(255) DEFAULT NULL

-- Initial hiring
initially_hired_date DATETIME DEFAULT NULL
initially_hired_notes TEXT DEFAULT NULL
```

### New Status Values
- `Pending` - Initial state
- `Interview Scheduled` - After interview is scheduled
- `Demo Scheduled` - After demo is scheduled
- `Psychological Exam` - During psychological examination period
- `Initially Hired` - After initial hiring decision
- `Hired` - Final hired state
- `Rejected` - Application rejected
- `Resubmission Required` - Documents need resubmission

## Files Modified

### Frontend (User Side)
- **user/user.php**
  - Restructured wizard to 2 steps instead of 3
  - Combined personal info, work experience, and skills into Step 1
  - Added masteral certificate upload field (optional)
  - Updated JavaScript validation and navigation
  - Added masteral_cert to form submission

### Backend (Admin Side)
- **admin/process_applicant_action.php**
  - Added `schedule_demo` action handler
  - Added `schedule_psych_exam` action handler
  - Added `mark_initially_hired` action handler
  - All handlers include notification creation

- **admin/admin.js**
  - Updated status color coding for new statuses
  - Updated status icons for new workflow stages
  - Added form submission handlers for:
    - Demo scheduling
    - Psychological exam scheduling
    - Initial hiring marking
  - Enhanced status badge display system

### Database
- **database_update.sql** (NEW FILE)
  - SQL migration script to add new fields
  - Adds indexes for better query performance
  - Includes documentation of status values

## Status Color Coding

- **Green**: Hired, Initially Hired
- **Yellow**: Pending, Under Review
- **Blue**: Interview Scheduled
- **Indigo**: Demo Scheduled
- **Purple**: Psychological Exam
- **Orange**: Resubmission Required
- **Red**: Rejected

## Installation Instructions

1. **Run Database Migration**
   ```bash
   mysql -u root -p nchire < database_update.sql
   ```

2. **Clear Browser Cache**
   - Ensure JavaScript changes are loaded

3. **Test Workflow**
   - Submit a test application
   - Verify all stages work correctly from admin panel

## User Experience Flow

### Applicant Journey
1. User clicks "Apply Now" on job posting
2. Accepts Terms & Conditions
3. **Step 1**: Completes personal info, work experience, and skills
4. **Step 2**: Uploads all required documents (and optional masteral certificate)
5. Submits application → Status: **Pending**
6. Receives notifications as admin moves application through stages
7. Can track status in "My Applications" section

### Admin Management
1. Admin views applicant in "Applicants" section
2. Reviews application and documents
3. Takes appropriate action based on hiring stage:
   - Schedule Interview → **Interview Scheduled**
   - After interview, schedule Demo → **Demo Scheduled**
   - After demo, schedule Psychological Exam → **Psychological Exam**
   - After exam, mark Initially Hired → **Initially Hired**
   - Final decision → **Hired** or **Rejected**
4. Applicant receives real-time notifications at each stage

## Benefits

### For Applicants
- ✅ Streamlined 2-step application process
- ✅ Clear visibility of application progress
- ✅ Real-time notifications at each stage
- ✅ Optional fields for additional qualifications (masteral cert)
- ✅ Reduced form fatigue with consolidated Step 1

### For Administrators
- ✅ Complete workflow management
- ✅ Stage-by-stage applicant tracking
- ✅ Automated notifications to applicants
- ✅ Comprehensive applicant information
- ✅ Flexible decision-making at each stage
- ✅ Status-based filtering and reporting

## Notes

- The system now supports a complete hiring pipeline from application to final hiring
- Each workflow stage automatically notifies the applicant
- Admin has full control over moving applicants through the stages
- The "Initially Hired" status allows for preliminary hiring decisions before final approval
- All status changes are logged and tracked in the database
- Notifications are sent via the existing notification system

## Future Enhancements (Optional)

- Email notifications in addition to in-app notifications
- Document templates for each stage
- Calendar integration for interviews and exams
- Bulk status updates
- Advanced reporting and analytics per workflow stage
- Interview scoring and feedback forms
- Demo evaluation rubrics
