# Fix: Pending Reviews Showing 0 in Secretary Dashboard

## Problem
The "Pending Reviews" stat card in the Secretary dashboard shows **0** even though there might be applications in the database.

## Most Likely Causes

### 1. **Applications Don't Have workflow_stage Set** (Most Common)
Old applications in the database might not have the `workflow_stage` column populated.

### 2. **No Applications Exist**
There might simply be no applications in the system yet.

### 3. **All Applications Are Rejected**
The stat excludes rejected applications, so if all applications are rejected, it will show 0.

## Quick Diagnosis

### Step 1: Run the Debug Tool
1. Open your browser
2. Navigate to: `http://localhost/FinalResearch - Copy/admin/debug_pending_stats.php`
3. This will show you:
   - Total applications in database
   - Applications by workflow_stage
   - Applications by status
   - Detailed list of what Secretary should see

### Step 2: Analyze the Results

**If you see:**
- ✅ **"workflow_stage column does NOT exist"** → Need to run database migration
- ✅ **"No applications in secretary_review"** but applications exist → Need to fix workflow stages
- ✅ **"No applications found at all"** → Need to submit test applications

## Solutions

### Solution 1: Fix Workflow Stages for Existing Applications

If you have applications but they don't have `workflow_stage` set:

1. Navigate to: `http://localhost/FinalResearch - Copy/admin/fix_workflow_stages.php`
2. Review the analysis showing which applications will be fixed
3. Click **"Fix Workflow Stages Now"** button
4. This will:
   - Set `workflow_stage = 'secretary_review'` for all pending applications
   - Set `workflow_stage = 'department_head_review'` for in-progress applications
   - Set specific workflow stages based on current status

### Solution 2: Create Test Application

If you have no applications:

1. **Logout from admin** (important!)
2. Go to the **public homepage**: `http://localhost/FinalResearch - Copy/public/index.php`
3. **Sign up** as a test applicant (or login if you have an account)
4. **Browse jobs** and click "Apply Now" on any job
5. **Fill out the application** form with test data
6. **Submit** the application
7. **Login as Secretary** again
8. The stat should now show **1**

### Solution 3: Check Database Column Exists

If the `workflow_stage` column is missing:

1. Open **phpMyAdmin** (`http://localhost/phpmyadmin`)
2. Select the **nchire** database
3. Open the **job_applicants** table
4. Check if **workflow_stage** column exists
5. If not, run this SQL:

```sql
ALTER TABLE job_applicants 
ADD COLUMN workflow_stage VARCHAR(50) DEFAULT 'secretary_review' 
AFTER status;

-- Update existing records
UPDATE job_applicants 
SET workflow_stage = 'secretary_review' 
WHERE workflow_stage IS NULL 
AND status IN ('Pending', 'Application Received', 'Resubmitted')
AND status != 'Rejected';
```

## How It Should Work

### For NEW Applications:
When users submit applications through the public form:
- ✅ `workflow_stage` is automatically set to `'secretary_review'`
- ✅ `status` is set to `'Pending'`
- ✅ Application appears in Secretary's dashboard immediately
- ✅ Pending Reviews count increases by 1

### For Secretary:
- **Stat Card Shows:** Number of applications with:
  - `workflow_stage = 'secretary_review'`
  - AND `status != 'Rejected'`
- **Applicants List Shows:** Exact same applications
- **Numbers Should Match!**

### What Gets Excluded:
- ❌ Rejected applications (`status = 'Rejected'`)
- ❌ Applications transferred to Dean (`workflow_stage = 'department_head_review'`)
- ❌ Applications in later stages (interview_scheduled, hired, etc.)

## Verification Steps

After applying the fix:

1. **Login as Secretary**
2. Check **Dashboard** - "Pending Reviews" should show correct count
3. Go to **Applicants** section
4. Count the rows in the table
5. **Numbers should match!**

## If Problem Persists

### Manual Database Check:

1. Open **phpMyAdmin**
2. Run this query:

```sql
-- This is exactly what the stat card counts
SELECT COUNT(*) as pending_count
FROM job_applicants 
WHERE workflow_stage = 'secretary_review' 
AND status != 'Rejected';
```

3. The result should match what you see in the Secretary dashboard

### View Actual Data:

```sql
-- See the applications Secretary should see
SELECT id, full_name, position, status, workflow_stage, applied_date
FROM job_applicants 
WHERE workflow_stage = 'secretary_review' 
AND status != 'Rejected'
ORDER BY applied_date DESC;
```

## Helpful Tools Created

1. **debug_pending_stats.php** - Diagnose the issue
2. **fix_workflow_stages.php** - Automatically fix workflow stages
3. Both accessible from admin panel when logged in

## Prevention

To prevent this issue in the future:
- ✅ Always use the application form (it sets workflow_stage correctly)
- ✅ Don't manually insert applications via phpMyAdmin without setting workflow_stage
- ✅ Use the fix tool periodically if you suspect issues

## Expected Behavior

| Action | Effect on Stat Card |
|--------|---------------------|
| User submits new application | Pending Reviews +1 |
| Secretary transfers to Dean | Pending Reviews -1 |
| Secretary rejects application | Pending Reviews -1 |
| User resubmits documents | Pending Reviews +1 |

## Contact

If none of these solutions work, check:
1. Browser console for JavaScript errors
2. PHP error logs for server-side issues
3. Database connection status
4. Session variables are being set correctly
