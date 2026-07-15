# Department Name Change: Computer Science → Computing Studies

## Overview
Changed all instances of "Computer Science" to "Computing Studies" throughout the NCHire application.

## Changes Made

### 1. Database Migration Script
**File:** `admin/update_computer_science_to_computing_studies.php`

This script updates the database to replace all "Computer Science" references with "Computing Studies":
- Updates `job` table: `department_role` column
- Updates `job` table: `subject` column (Computer Science Professional Subjects → Computing Studies Professional Subjects)
- Updates `job_applicants` table: `assigned_to_department` column
- Updates `admin_users` table: `department` column

**How to run:**
1. Open browser: `http://localhost/FinalResearch - Copy/admin/update_computer_science_to_computing_studies.php`
2. Wait for migration to complete
3. Review the summary and verification results

### 2. Admin Panel Forms (admin/index.php)

Updated all department dropdown options in:
- **Create Job Modal (Instructor)** - Line 1637
- **Create Job Modal (Secretary)** - Line 1992 (subject)
- **Create Job Modal (General)** - Line 2096
- **Create User Modal** - Line 2612
- **Edit User Modal** - Line 2731
- **Edit Job Modal** - Line 3977
- **Job Department Filter** - Line 4286

Updated subject options:
- Changed "Computer Science Professional Subjects" to "Computing Studies Professional Subjects"
- Appears in both job creation modals (Lines 1695, 1992)

### 3. Admin JavaScript (admin/admin.js)

Updated department color coding logic:
- Line 4098: Changed condition to check for 'Computing Studies'
- Maintains blue badge color for Computing Studies department

### 4. User Dashboard (user/user.php)

Updated department filter dropdown:
- Line 1426: Changed filter option from "computer science" to "computing studies"

## Files Modified

1. ✅ **admin/update_computer_science_to_computing_studies.php** (NEW)
   - Database migration script

2. ✅ **admin/index.php**
   - All department dropdowns updated
   - Subject options updated

3. ✅ **admin/admin.js**
   - Department color coding logic updated

4. ✅ **user/user.php**
   - Department filter updated

## Subject Name Changes

The following subject option was also updated:
- **OLD:** Computer Science Professional Subjects
- **NEW:** Computing Studies Professional Subjects

This affects:
- Job creation forms (both Instructor and Secretary modals)
- Database records (via migration script)

## Migration Steps

### Step 1: Run Database Migration
```
http://localhost/FinalResearch - Copy/admin/update_computer_science_to_computing_studies.php
```

This will:
1. Update all existing job postings
2. Update all subject names
3. Update all applicant assignments
4. Update all admin user departments
5. Show verification results

### Step 2: Verify Changes

**Check Database:**
- Jobs with "Computing Studies" department
- Jobs with "Computing Studies Professional Subjects"
- Applicants assigned to "Computing Studies"
- Admin users in "Computing Studies" department

**Check Frontend:**
- Admin job creation forms show "Computing Studies"
- Subject dropdown shows "Computing Studies Professional Subjects"
- User department filter shows "Computing Studies"
- Existing jobs display "Computing Studies"

## Testing Checklist

- [ ] Database migration runs successfully
- [ ] All job postings updated in database
- [ ] All subject names updated in database
- [ ] Admin user departments updated
- [ ] Applicant assignments updated
- [ ] Admin forms show "Computing Studies" in dropdowns
- [ ] Subject dropdown shows "Computing Studies Professional Subjects"
- [ ] User department filter shows "Computing Studies"
- [ ] Department badge colors work correctly (blue for Computing Studies)
- [ ] New jobs can be created with "Computing Studies" department
- [ ] Filtering by "Computing Studies" works on user dashboard

## Affected Tables

| Table | Column | Old Value | New Value |
|-------|--------|-----------|-----------|
| job | department_role | Computer Science | Computing Studies |
| job | subject | Computer Science Professional Subjects | Computing Studies Professional Subjects |
| job_applicants | assigned_to_department | Computer Science | Computing Studies |
| admin_users | department | Computer Science | Computing Studies |

## Rollback Plan

If you need to rollback to "Computer Science":

1. Run SQL commands:
```sql
UPDATE job SET department_role = 'Computer Science' WHERE department_role = 'Computing Studies';
UPDATE job SET subject = 'Computer Science Professional Subjects' WHERE subject = 'Computing Studies Professional Subjects';
UPDATE job_applicants SET assigned_to_department = 'Computer Science' WHERE assigned_to_department = 'Computing Studies';
UPDATE admin_users SET department = 'Computer Science' WHERE department = 'Computing Studies';
```

2. Revert code changes in:
   - admin/index.php
   - admin/admin.js
   - user/user.php

## Impact Summary

### ✅ What Changed
- Department name: "Computer Science" → "Computing Studies"
- Subject name: "Computer Science Professional Subjects" → "Computing Studies Professional Subjects"
- All dropdown options updated
- All database records updated
- All display text updated

### ⚠️ What Stayed the Same
- Database structure (no schema changes)
- Form functionality
- User permissions
- Admin permissions
- Department color coding (still blue)
- All other features and functionality

## Notes

- The migration script is **idempotent** - safe to run multiple times
- No data is lost during migration
- All existing relationships are preserved
- Department head assignments remain intact
- Applicant assignments remain valid

---
**Implementation Date:** November 11, 2025
**Status:** ✅ Ready to Deploy
**Migration Required:** Yes - Run update_computer_science_to_computing_studies.php
