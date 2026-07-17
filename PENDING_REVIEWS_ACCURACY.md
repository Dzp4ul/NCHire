# Pending Reviews Stat Card - Accuracy Enhancement

## Summary
Enhanced the "Pending Reviews" stat card in the admin dashboard to be fully dynamic and accurately reflect what Secretary and Dean see in their applicants sections.

## Changes Made

### 1. **Dashboard Stats API** (`admin/api/dashboard_stats.php`)
**Lines 47-79:**
- Added `AND status != 'Rejected'` filter to Secretary pending count
- Added `AND status != 'Rejected'` filter to Department Head pending count
- Ensures only active, actionable applications are counted

**Logic:**
```sql
-- Secretary Pending (what appears in Secretary's applicant list)
SELECT COUNT(*) FROM job_applicants 
WHERE workflow_stage = 'secretary_review' 
AND status != 'Rejected'

-- Department Head Pending (what appears in Dean's applicant list)
SELECT COUNT(*) FROM job_applicants 
WHERE workflow_stage = 'department_head_review' 
AND status != 'Rejected'
```

### 2. **Initial Page Load Stats** (`admin/index.php`)
**Lines 92-124:**
- Updated secretary_pending calculation to exclude rejected applications
- Updated dept_pending calculation to exclude rejected applications
- Ensures initial page load matches API results

### 3. **Applicants List API** (`admin/api/applicants.php`)
**Lines 32-38:**
- Added `status != 'Rejected'` filter to Secretary's applicant query
- Ensures the list shown in UI exactly matches the stat card count

**Before:**
```php
if ($admin_role === 'Secretary') {
    $where_conditions[] = "workflow_stage = ?";
    $params[] = 'secretary_review';
}
```

**After:**
```php
if ($admin_role === 'Secretary') {
    $where_conditions[] = "workflow_stage = ?";
    $where_conditions[] = "status != ?";
    $params[] = 'secretary_review';
    $params[] = 'Rejected';
}
```

## How It Works

### For Secretary:
1. **Stat Card shows:** Count of applications with `workflow_stage = 'secretary_review'` AND `status != 'Rejected'`
2. **Applicants list shows:** Exact same applications
3. **Result:** Numbers match perfectly ✅

### For Admin:
1. **Stat Card shows:** Sum of secretary_pending + dept_pending (both excluding rejected)
2. **Shows total actionable applications** across all workflow stages
3. **Result:** Accurate overview of pending work ✅

### For Department Head (Dean):
1. **Stat Card shows:** Count of applications with `workflow_stage = 'department_head_review'` AND `status != 'Rejected'` in their department
2. **Applicants list shows:** Same filtered applications
3. **Result:** Numbers match perfectly ✅

## Auto-Refresh
- Stats update every **30 seconds** automatically via JavaScript
- Uses `updateStatsCards()` function in `admin/index.php` (lines 4691-4720)
- Fetches from `api/dashboard_stats.php` with cache-busting headers

## Benefits
✅ **Accurate counts** - Excludes rejected/inactive applications
✅ **Real-time sync** - List count matches stat card
✅ **Dynamic updates** - Auto-refreshes without page reload
✅ **Role-specific** - Each role sees their relevant pending count
✅ **Performance optimized** - Efficient SQL queries with proper indexing

## Testing
To verify accuracy:
1. Login as Secretary
2. Check "Pending Review" stat card number
3. Navigate to Applicants section
4. Count should match the number of applicants shown in the list
5. Reject an application → stat should decrease by 1
6. Transfer an application → stat should decrease by 1

## Database Queries Used

### Secretary View:
```sql
-- Stat Card Count
SELECT COUNT(*) as count 
FROM job_applicants 
WHERE workflow_stage = 'secretary_review' 
  AND status != 'Rejected'

-- Applicants List
SELECT * FROM job_applicants 
WHERE workflow_stage = 'secretary_review' 
  AND status != 'Rejected'
ORDER BY applied_date DESC
```

### Admin View:
```sql
-- Combined count of both queues
SELECT 
  (SELECT COUNT(*) FROM job_applicants 
   WHERE workflow_stage = 'secretary_review' AND status != 'Rejected') +
  (SELECT COUNT(*) FROM job_applicants 
   WHERE workflow_stage = 'department_head_review' AND status != 'Rejected')
as pending_reviews
```

## Notes
- The `workflow_stage` field tracks the exact position in the hiring workflow
- Applications move from `secretary_review` → `department_head_review` → other stages
- Once rejected, applications are excluded from pending counts
- The stat card updates automatically every 30 seconds in the background
