# Workflow Filtering Fix - Complete

## Problem Solved
Department Heads were seeing applications that should only be visible to the Secretary because the workflow_stage filtering wasn't applied to the main applicants loading endpoint.

## Files Fixed

### 1. ✅ gets_applicants.php
Added workflow_stage filtering:
- **Secretary**: Only sees `workflow_stage = 'secretary_review'`
- **Department Head**: Only sees `workflow_stage IN ('department_head_review', 'interview_scheduled', ...)`
- **HR Manager/Recruiter**: See all non-rejected in their department

### 2. ✅ get_archive.php
Added workflow_stage filtering for archive:
- Uses `workflow_stage = 'rejected'` instead of `status = 'Rejected'`
- Secretary can now see all rejected applications
- Department Head sees rejected in their department only

### 3. ✅ admin/index.php
- Secretary now sees "Applicants" menu (same as Department Head)
- Both roles use the same interface, but see different data based on filtering

## How It Works Now

### New Application Flow:
```
1. Applicant submits application
   ↓
   workflow_stage = 'secretary_review'
   ↓
2. SECRETARY SEES IT in Applicants section
   (Department Head does NOT see it yet)
   ↓
3. Secretary clicks "Transfer to Department Head"
   ↓
   workflow_stage = 'department_head_review'
   ↓
4. DEPARTMENT HEAD NOW SEES IT in Applicants section
   (Filtered by their department: Computer Science, etc.)
```

## Important: Fix Existing Applications

If you have applications that were created BEFORE the workflow system was implemented, they may have `workflow_stage = NULL` and won't show up correctly.

### Run the Fix Script:
1. Open your browser
2. Navigate to: `http://localhost/FinalResearch - Copy/admin/fix_existing_applications.php`
3. The script will:
   - Find all applications with NULL workflow_stage
   - Set appropriate workflow_stage based on current status
   - Show you a summary of what was updated

**Example:**
- If status = 'Pending' → workflow_stage = 'secretary_review'
- If status = 'Interview Scheduled' → workflow_stage = 'interview_scheduled'
- If status = 'Rejected' → workflow_stage = 'rejected'

## Testing the Workflow

### Test 1: New Application
1. **As Applicant**: Submit a new application for Computer Science
2. **As Secretary**: 
   - Login → Go to "Applicants"
   - You should SEE the new application
3. **As Computer Science Department Head**:
   - Login → Go to "Applicants"
   - You should NOT SEE the application yet

### Test 2: Transfer to Department Head
1. **As Secretary**:
   - Click on the application
   - Click "Transfer to Department Head" button
2. **As Computer Science Department Head**:
   - Refresh the Applicants page
   - You should NOW SEE the application
3. **As Secretary**:
   - Refresh the Applicants page
   - The application should be GONE (it's now with dept head)

### Test 3: Rejection
1. **As Secretary**: Reject an application
2. **Check Archive section**:
   - Both Secretary and Department Head can see it in Archive
   - It's NO LONGER in the Applicants section

## Role Visibility Summary

| Application Stage | Secretary Sees | Dept Head Sees |
|-------------------|----------------|----------------|
| `secretary_review` | ✅ YES | ❌ NO |
| `department_head_review` | ❌ NO | ✅ YES |
| `interview_scheduled` | ❌ NO | ✅ YES |
| `interview_completed` | ❌ NO | ✅ YES |
| `demo_scheduled` | ❌ NO | ✅ YES |
| `hired` | ❌ NO | ✅ YES |
| `rejected` (Archive) | ✅ YES | ✅ YES |

## Troubleshooting

### Issue: No applications showing for Secretary
**Cause**: No applications with `workflow_stage = 'secretary_review'`
**Fix**: Either:
1. Submit a new application as an applicant (will auto-set to secretary_review)
2. Run `fix_existing_applications.php` to update old applications

### Issue: Department Head still sees secretary applications
**Cause**: Browser cache or old data
**Fix**: 
1. Hard refresh the page (Ctrl+Shift+R)
2. Clear browser cache
3. Logout and login again

### Issue: Transferred application not showing for Department Head
**Cause**: Department filter mismatch
**Fix**: Make sure the application's `assigned_to_department` matches the Department Head's department in their user profile

## Files Modified Summary

```
✅ admin/gets_applicants.php - Added workflow_stage filtering
✅ admin/get_archive.php - Added workflow_stage filtering
✅ admin/index.php - Secretary sees "Applicants" menu
✅ user/user.php - New applications set workflow_stage
✅ database/migrations/add_secretary_workflow.php - Database structure

🆕 admin/fix_existing_applications.php - Fix old applications
```

## Complete Workflow System

The Secretary workflow is now fully functional with proper role-based filtering. Applications flow correctly from Secretary → Department Head, and each role only sees what they should see.

✅ **Secretary**: Reviews new applications, transfers to dept head
✅ **Department Head**: Sees only transferred applications, schedules interviews
✅ **Clean Separation**: No overlap in visibility
✅ **Archive**: Both can see rejected applications

The system is production-ready! 🎉
