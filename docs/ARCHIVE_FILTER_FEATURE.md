# Archive Filter Feature - Rejected and Cancelled Applications

## Overview
Enhanced the Archive section for Department Heads and Secretaries to display both rejected and cancelled applications with filter capability.

## Features Implemented

### 1. **Archive Status Filter**
- Added dropdown filter with three options:
  - **All Status**: Shows both rejected and cancelled applications (default)
  - **Rejected Only**: Shows only applications rejected by admin
  - **Cancelled Only**: Shows only applications cancelled by applicants

### 2. **Status Badge Display**
- **Rejected**: Red badge (bg-red-100 text-red-800)
- **Cancelled**: Orange badge (bg-orange-100 text-orange-800)

### 3. **Enhanced Table Structure**
Updated table columns:
- Applicant
- Position
- **Status** (NEW - shows badge)
- Applied Date
- Archive Date (renamed from "Rejected Date")
- Reason
- Actions

## Technical Implementation

### Files Modified

#### 1. **admin/index.php**
- Updated subtitle from "Rejected applicants" to "Rejected and Cancelled applicants"
- Added status filter dropdown in search/filter section
- Updated table headers to include Status column
- Changed "Rejected Date" to "Archive Date"
- Updated colspan from 6 to 7 for empty state

#### 2. **admin/admin.js**
- Added `currentArchiveFilter` global variable to track filter state
- Created `filterArchiveByStatus(status)` function
- Updated `searchArchive()` to work with filter
- Created `applyArchiveFilters()` to combine search and status filtering
- Updated `displayArchivedApplicants()` to:
  - Show status badges (rejected/cancelled)
  - Display archive date instead of just rejected date
  - Update colspan to 7

#### 3. **user/cancel_application.php**
- Updated cancellation logic to properly set:
  - `status = 'Cancelled'`
  - `workflow_stage = 'cancelled'`
  - `rejected_date = NOW()` (for archive sorting)
  - `rejection_reason = 'Application cancelled by applicant'`

#### 4. **admin/get_archive.php** (Already implemented)
- Already includes both rejected and cancelled applications:
  ```sql
  WHERE ja.workflow_stage IN ('rejected', 'cancelled')
  ```

## User Interface

### Filter Dropdown
```html
<select id="archiveStatusFilter" onchange="filterArchiveByStatus(this.value)">
    <option value="all">All Status</option>
    <option value="rejected">Rejected Only</option>
    <option value="cancelled">Cancelled Only</option>
</select>
```

### Status Badges
- **Rejected**: `<span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Rejected</span>`
- **Cancelled**: `<span class="px-2 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-800">Cancelled</span>`

## User Experience Flow

### For Secretaries:
1. Navigate to Archive section
2. See all rejected and cancelled applications
3. Use filter dropdown to view specific status
4. Search works in combination with filter

### For Department Heads:
1. Navigate to Archive section
2. See archived applications from their department only
3. Filter by rejected/cancelled status
4. Search within filtered results

## Data Flow

### Rejection Flow (by Admin):
1. Admin rejects application
2. `workflow_stage = 'rejected'`
3. `rejected_date = NOW()`
4. Shows in archive with **red badge**

### Cancellation Flow (by Applicant):
1. Applicant cancels application
2. `status = 'Cancelled'`
3. `workflow_stage = 'cancelled'`
4. `rejected_date = NOW()`
5. `rejection_reason = 'Application cancelled by applicant'`
6. Shows in archive with **orange badge**

## Filter Logic

```javascript
function applyArchiveFilters(searchTerm = null) {
    let filtered = allArchivedData;
    
    // Apply status filter
    if (currentArchiveFilter !== 'all') {
        filtered = filtered.filter(applicant => 
            applicant.workflow_stage === currentArchiveFilter
        );
    }
    
    // Apply search filter
    if (searchTerm) {
        filtered = filtered.filter(applicant => {
            // Search in name, email, position, reason
        });
    }
    
    displayArchivedApplicants(filtered);
}
```

## Benefits

✅ **Clear Status Indication**: Visual badges differentiate rejected vs cancelled applications  
✅ **Flexible Filtering**: Admins can focus on specific application types  
✅ **Combined Search**: Filter and search work together  
✅ **Proper Data Storage**: Cancelled applications properly archived with dates  
✅ **Consistent UX**: Both rejection and cancellation follow same archive pattern  

## Testing

### Test Scenarios:
1. **View All**: Select "All Status" - should show both rejected and cancelled
2. **Filter Rejected**: Select "Rejected Only" - should show only red badges
3. **Filter Cancelled**: Select "Cancelled Only" - should show only orange badges
4. **Search with Filter**: Type search term + select filter - results match both criteria
5. **Clear Search**: Remove search term - filter remains active

### Test Data:
- Create applications and reject them (should show red badge)
- Create applications and have applicants cancel them (should show orange badge)
- Verify both appear in archive
- Test filter dropdown changes
- Verify search works with filters

## Database Schema

### Relevant Columns:
- `workflow_stage`: ENUM including 'rejected' and 'cancelled'
- `status`: 'Rejected' or 'Cancelled'
- `rejected_date`: Timestamp when archived (used for both rejected and cancelled)
- `rejection_reason`: Reason text (or "Application cancelled by applicant")

## Notes

- Archive only visible to Secretary and Department Head roles
- Department Heads see only their department's archived applications
- Secretaries see all archived applications regardless of department
- Both search and filter are client-side for fast performance
- Filter state persists during search operations
