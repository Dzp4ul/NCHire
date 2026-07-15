# Subject Field Implementation for Job Postings

## Overview
Added a subject dropdown field to the job posting creation forms for Dean and Secretary roles.

## Changes Made

### 1. Frontend (admin/index.php)
**Added subject dropdown to TWO modals:**

#### a) Instructor/Dean Job Modal (createJobModal)
- Location: Lines 1688-1702
- Added subject dropdown with 7 subject options
- Placed between Application Deadline and Job Description fields

#### b) Secretary Job Modal (createsecJobModal)
- Location: Lines 1985-1999
- Added identical subject dropdown
- Same placement and options as Dean modal

**Subject Options:**
1. BSEd and BEED Professional Education Subjects
2. Computing Studies Professional Subjects
3. Major Subjects (Biology, Chemistry and Physics)
4. Business Management Subjects
5. Social Sciences
6. English
7. PATHFit

### 2. JavaScript (admin/admin.js)
**Updated createJob() function:**
- Added `subject: formData.get('subject') || ''` to the newJob object
- Location: Line 884
- Subject value is captured from form and sent to backend

### 3. Backend (admin/add_job.php)
**Updated to handle subject field:**
- Added `$subject = $data["subject"] ?? '';` variable (Line 36)
- Updated SQL INSERT query to include subject column (Line 49)
- Updated bind_param to include subject value (Line 57)

### 4. Database Migration (admin/add_subject_column.php)
**Created migration script:**
- Checks if 'subject' column exists
- Adds column if missing: `VARCHAR(255) DEFAULT ''`
- Displays current table structure
- Shows success/error messages

## Installation Instructions

### Step 1: Run Database Migration
1. Open your browser
2. Navigate to: `http://localhost/FinalResearch - Copy/admin/add_subject_column.php`
3. Wait for confirmation message
4. Verify that the subject column was added successfully

### Step 2: Test the Feature
1. Log in to the admin panel
2. Navigate to Job Postings section
3. Click "Create New Job Posting" (for Instructor/Dean)
   - OR click the Secretary job posting button
4. Verify that the Subject dropdown appears
5. Select a subject from the dropdown
6. Fill in other required fields
7. Submit the form
8. Check that the job was created successfully

## Form Structure

### Subject Field Position
```
Application Deadline
    ↓
Subject (NEW) ← **REQUIRED** dropdown
    ↓
Job Description
```

### Field Details
- **Field Name:** subject
- **Field Type:** Dropdown/Select
- **Required:** Yes ⚠️ **REQUIRED FIELD**
- **Default:** Empty string
- **Database Column:** subject VARCHAR(255)

## Which Modals Have the Subject Field?

✅ **Instructor/Dean Modal** (createJobModal) - HAS SUBJECT FIELD
✅ **Secretary Modal** (createsecJobModal) - HAS SUBJECT FIELD
❌ **Utility Job Modal** (createutilityJobModal) - NO SUBJECT FIELD

## Files Modified

1. `admin/index.php` - Added subject dropdown to 2 modals
2. `admin/admin.js` - Updated JavaScript to capture subject value
3. `admin/add_job.php` - Updated backend to save subject to database
4. `admin/add_subject_column.php` - NEW: Database migration script

## Testing Checklist

- [ ] Database migration completed successfully
- [ ] Subject dropdown appears in Instructor/Dean modal
- [ ] Subject dropdown appears in Secretary modal
- [ ] All 7 subject options are visible
- [ ] Red asterisk (*) appears next to Subject label
- [ ] Cannot submit form without selecting a subject (validation works)
- [ ] Can create job posting with selected subject
- [ ] Subject value saves to database correctly
- [ ] Subject displays in job listings/details

## Database Schema

```sql
ALTER TABLE job 
ADD COLUMN subject VARCHAR(255) DEFAULT '' 
AFTER application_deadline;
```

## Troubleshooting

**Issue:** Subject dropdown not appearing
- **Solution:** Clear browser cache and refresh page

**Issue:** Error when submitting form
- **Solution:** Run the migration script first (add_subject_column.php)

**Issue:** Subject value not saving
- **Solution:** Check database - ensure subject column exists in job table

**Issue:** Migration script shows error
- **Solution:** Check database credentials in add_subject_column.php

## Future Enhancements

Possible improvements:
- Make subject required for certain departments
- Add more subject options based on college programs
- Display subject in job listing cards
- Filter jobs by subject
- Subject-specific requirements

## Support

If you encounter any issues:
1. Check browser console for JavaScript errors
2. Check PHP error logs
3. Verify database column was added successfully
4. Ensure all files were updated correctly

---
**Implementation Date:** November 11, 2025
**Status:** ✅ Complete and Ready to Use
