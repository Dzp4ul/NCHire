# Dynamic Applicants Section Stat Cards

Successfully implemented dynamic stat cards in the secretary/admin applicants section that update in real-time based on role-specific data.

## Implementation Summary

### Problem Solved
- Stat cards in the applicants section were showing static PHP-generated values
- Stats didn't update when applicant actions were performed
- Stats weren't role-specific (secretary vs department head had same data source)

### Solution Implemented

#### 1. **Backend API Endpoint** - `admin/api/get_applicants_stats.php`
Created a dedicated API that returns dynamic stats based on user role:

**Secretary Stats:**
- Total Applicants: All applications they can view (excluding rejected)
- Interview Scheduled: Applications with interview scheduled status
- Demo Scheduled: Applications with demo teaching scheduled
- Hired: Applications that are Initially Hired, Permanently Hired, or Hired

**Department Head Stats:**
- Filtered by their specific department
- Only shows applications in relevant workflow stages

**HR Manager/Recruiter Stats:**
- Filtered by their department
- Shows all non-rejected applications

#### 2. **Frontend Implementation** - `admin/admin.js`

**New Function: `loadApplicantsStats()`**
```javascript
async function loadApplicantsStats() {
    // Fetches stats from API
    // Updates all elements with data-stat attributes
    // Provides console logging for debugging
}
```

**Updated Function: `loadApplicants()`**
- Now calls `loadApplicantsStats()` before loading applicants data
- Ensures stats are always fresh when viewing applicants section

#### 3. **HTML Stat Cards** - `admin/index.php`
Stat cards use `data-stat` attributes for dynamic updates:
```html
<p class="text-2xl font-bold text-gray-900" data-stat="total_applicants">
<p class="text-2xl font-bold text-gray-900" data-stat="interview_scheduled">
<p class="text-2xl font-bold text-gray-900" data-stat="demo_scheduled">
<p class="text-2xl font-bold text-gray-900" data-stat="hired">
```

## Workflow

### Initial Load
1. User navigates to Applicants section
2. `showSection('applicants')` is called
3. `loadApplicants()` is triggered
4. `loadApplicantsStats()` fetches and updates stat cards
5. Applicants table is loaded and filtered

### After Actions
When any applicant action is performed:
1. Action completes (transfer, schedule, hire, reject, etc.)
2. `loadApplicants()` is called to refresh the list
3. Stats are automatically refreshed via `loadApplicantsStats()`
4. Both table and stat cards show updated data

## Role-Based Stats

### Secretary
- Sees ALL applications they have access to (secretary_review stage or transferred by them)
- Stats include applications across all departments
- Filter criteria: `workflow_stage != 'rejected'` AND `(secretary_id IS NULL OR secretary_id = 0 OR workflow_stage = 'secretary_review' OR secretary_id = :secretary_id)`

### Department Head
- Sees only applications in their department
- Only applications in specific workflow stages (department_head_review onwards)
- Filter criteria: Department match + workflow_stage filtering

### HR Manager / Recruiter
- Sees all applications in their department
- Excludes rejected applications
- Filter criteria: Department match + `status != 'Rejected'`

## Technical Details

### API Response Format
```json
{
    "total_applicants": 15,
    "interview_scheduled": 5,
    "demo_scheduled": 3,
    "hired": 2
}
```

### Auto-Refresh Triggers
Stats automatically refresh when:
- ✅ Applicants section is opened
- ✅ Interview is scheduled
- ✅ Demo teaching is scheduled
- ✅ Application is transferred to department head
- ✅ Application is approved
- ✅ Applicant is hired
- ✅ Application is rejected
- ✅ Resubmission is requested

## Benefits

1. **Real-Time Accuracy**: Stats always reflect current database state
2. **Role-Based**: Different roles see relevant stats for their scope
3. **Automatic Updates**: No manual refresh needed after actions
4. **Performance**: Separate API call prevents blocking applicant list load
5. **Maintainable**: Single source of truth for stat calculations
6. **Consistent**: Same filtering logic as applicant list ensures accuracy

## Files Modified

1. **Created**: `admin/api/get_applicants_stats.php` - Stats API endpoint
2. **Modified**: `admin/admin.js` - Added `loadApplicantsStats()` function
3. **Updated**: `loadApplicants()` to call stats refresh
4. **Existing**: `admin/index.php` - Stat cards already have `data-stat` attributes

## Testing

To verify the dynamic stats are working:

1. **As Secretary:**
   - Login as secretary user
   - Navigate to Applicants section
   - Verify stat cards show counts
   - Perform an action (transfer, schedule, etc.)
   - Verify stats update automatically

2. **As Department Head:**
   - Login as department head
   - Navigate to Applicants section
   - Verify stats show only your department's data
   - Compare with secretary view to ensure filtering works

3. **Console Verification:**
   - Open browser console
   - Look for: `Applicants stats updated: {total_applicants: X, ...}`
   - Verify logged stats match displayed values

## Future Enhancements

Potential additions:
- Add "Pending Review" stat card
- Add "Resubmission Required" stat card
- Add loading animations for stat cards
- Add tooltips explaining each stat
- Add click handlers to filter by stat (e.g., click "Interview Scheduled" to filter list)
