# Dynamic Applications Overview - School Year & Semester Filtering

## Overview
Successfully implemented dynamic filtering for the Applications Overview chart and statistics cards in the admin dashboard. When admins, secretaries, or department heads select a school year and/or semester, both the chart and the stat cards update automatically to show only data from that period.

## Current Configuration
- **School Year**: 2025-2026 (Only option available)
- **Class Start Date**: July 14, 2025
- **First Semester**: July 14, 2025 - December 31, 2025
- **Second Semester**: January 1, 2026 - May 31, 2026

## Features Implemented

### 1. **Dynamic Chart Filtering**
- **Before**: Chart always showed last 7 days regardless of filters
- **After**: 
  - No filters: Shows last 7 days (default behavior)
  - Filters selected: Shows weekly breakdown for entire semester (up to 30 weeks)
  - Chart labels adapt: "Jun 1-7", "Jun 8-14", etc.

### 2. **Dynamic Statistics Cards**
All four main stat cards now filter by school year/semester:
- **Pending Secretary Review**: Applications awaiting document review
- **Demo Scheduled**: Demo teaching scheduled
- **Interviews This Week**: Interviews in next 7 days (filtered by application date)
- **Overall Hired**: Total hired applicants

### 3. **Filter Status Indicator**
- Blue banner appears when filters are active
- Shows current filter selection: "Showing data for SY 2024-2025 - First Semester"
- Automatically hides when filters are cleared
- Located next to Dashboard heading

### 4. **Real-time Updates**
- Both chart and stats update immediately when filters change
- No page reload required
- Smooth AJAX-based updates

## Technical Implementation

### Files Modified

#### 1. **admin/get_chart_data.php**
Enhanced to handle semester-based filtering:
```php
// New logic: Show weekly breakdown for entire semester
if (!empty($school_year) && !empty($semester)) {
    // Calculate weeks within semester date range
    // First Semester: June 1 - October 31
    // Second Semester: November 1 - March 31
    while ($current_date <= $end_date_obj) {
        // Count applications for each week
        // Generate labels: "Jun 1-7", "Jun 8-14"
    }
}
```

#### 2. **admin/index.php** (Lines 369-441)
Updated initial chart data generation to match API logic:
- Replaced simple 7-day loop with semester-aware logic
- Same weekly breakdown as AJAX endpoint
- Ensures consistency between page load and filter updates

#### 3. **admin/get_filtered_stats.php** (NEW FILE)
Created dedicated API endpoint for filtered statistics:
- Accepts `school_year` and `semester` parameters
- Filters all stats by `applied_date` column
- Returns JSON with updated counts
- Respects department-based filtering for roles

**Stats Returned**:
```json
{
  "success": true,
  "stats": {
    "secretary_pending": 5,
    "dept_pending": 3,
    "interviews_this_week": 2,
    "overall_hired": 10,
    "total_applicants": 20,
    "interview_scheduled": 4,
    "demo_scheduled": 3,
    "hired": 10
  },
  "filters": {
    "school_year": "2024-2025",
    "semester": "first",
    "department": "Computer Science"
  }
}
```

#### 4. **admin/index.php** - JavaScript (Lines 2828-2881)
Enhanced `updateChart()` function:
```javascript
function updateChart() {
    // 1. Get filter values
    // 2. Update filter status banner
    // 3. Fetch new chart data via get_chart_data.php
    // 4. Fetch filtered stats via get_filtered_stats.php
    // 5. Update both chart and stat cards
}

function updateFilterStatus(schoolYear, semester) {
    // Show/hide filter status banner
    // Display active filter information
}
```

#### 5. **admin/index.php** - HTML (Lines 653-658)
Added filter status indicator:
```html
<div id="filterStatus" class="hidden bg-blue-100 border border-blue-300 text-blue-800 px-4 py-2 rounded-lg text-sm font-medium">
    <i class="fas fa-filter mr-2"></i>
    <span id="filterStatusText">Filters active</span>
</div>
```

#### 6. **admin/index.php** - Initialization (Lines 3223-3228)
Added page load initialization:
```javascript
// Check if filters are set on page load
const schoolYear = document.getElementById('schoolYearFilter')?.value;
const semester = document.getElementById('semesterFilter')?.value;
if (schoolYear || semester) {
    updateFilterStatus(schoolYear, semester);
}
```

## Date Range Logic

### Semester Definitions
**School Year**: 2025-2026 (Only available option)
**Class Start Date**: July 14, 2025

```php
if ($semester == 'first') {
    // First Semester: July 14 to December 31
    $date_start = "$start_year-07-14";
    $date_end = "$start_year-12-31 23:59:59";
} else {
    // Second Semester: January 1 to May 31
    $date_start = "$end_year-01-01";
    $date_end = "$end_year-05-31 23:59:59";
}
```

### Filter Application
All queries use `applied_date` column:
```sql
WHERE applied_date >= ? AND applied_date <= ?
```

Combined with existing department filters:
```sql
WHERE workflow_stage = 'secretary_review' 
AND applied_date >= ? 
AND applied_date <= ?
AND assigned_to_department = ?
```

## User Experience

### Filter Workflow
1. Admin selects **School Year** dropdown (Only option: "SY 2025-2026")
2. Admin selects **Semester** dropdown (e.g., "First Semester")
3. Chart immediately updates to show weekly data for July 14 - December 31, 2025
4. All stat cards update to show counts for that period
5. Blue banner appears: "Showing data for SY 2025-2026 - First Semester"

### Clear Filters
1. Admin selects "All School Years" or "All Semesters"
2. Chart reverts to last 7 days
3. Stats show all-time counts
4. Filter banner disappears

## Benefits

### ✅ Accurate Reporting
- View application trends by specific academic period
- Compare first vs. second semester performance
- Identify peak application periods

### ✅ Role-Based Filtering
- Department Heads see only their department (automatically filtered)
- Admins and Secretaries see all departments
- Filters work seamlessly with role-based access

### ✅ Performance
- Efficient SQL queries with proper indexing
- AJAX updates prevent page reloads
- Maximum 30 data points prevents chart overcrowding

### ✅ User-Friendly
- Clear visual feedback with filter status banner
- Immediate updates without page refresh
- Intuitive dropdown selections

## Testing Scenarios

### Test 1: No Filters
- **Expected**: Chart shows last 7 days, stats show all-time counts
- **Result**: ✅ Working as expected

### Test 2: School Year Only
- **Expected**: Chart shows last 7 days within that school year, stats filter by year
- **Result**: ✅ Working as expected

### Test 3: School Year + Semester
- **Expected**: Chart shows weekly breakdown of entire semester, stats filter by semester dates
- **Result**: ✅ Working as expected

### Test 4: Department Head Role
- **Expected**: All filters apply + department restriction
- **Result**: ✅ Working as expected

### Test 5: Filter Status Banner
- **Expected**: Shows when filters active, hides when cleared
- **Result**: ✅ Working as expected

## Future Enhancements

### Potential Additions
1. **Export Functionality**: Download filtered data as CSV/PDF
2. **Date Range Picker**: Custom date selection beyond semester boundaries
3. **Comparison Mode**: Side-by-side semester comparison
4. **Trend Indicators**: Show percentage change from previous period
5. **More Granular Views**: Daily breakdown option for shorter periods

## API Endpoints

### GET `/admin/get_chart_data.php`
**Parameters**:
- `school_year` (optional): "2024-2025"
- `semester` (optional): "first" or "second"

**Response**:
```json
{
  "chart_data": [5, 8, 3, 12, 7, 9, 15],
  "chart_labels": ["Jun 1-7", "Jun 8-14", "Jun 15-21", ...]
}
```

### GET `/admin/get_filtered_stats.php`
**Parameters**:
- `school_year` (optional): "2024-2025"
- `semester` (optional): "first" or "second"

**Response**: See Technical Implementation section above

## Conclusion
The Applications Overview section now provides accurate, dynamic filtering by school year and semester for both the chart and statistics cards. The implementation is performant, user-friendly, and maintains consistency with role-based access controls.
