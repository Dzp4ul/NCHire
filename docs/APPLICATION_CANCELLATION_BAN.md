# Application Cancellation Ban System

## Overview
When an applicant cancels their own application, they are automatically banned from submitting new applications for **4 months (1 semester)**, similar to when they are rejected by a Secretary or Department Head. The cancelled application is also moved to the archive section.

---

## Ban Duration
- **Length**: 4 months from the date of cancellation
- **Trigger**: Applicant cancels their own application
- **Automatic Expiry**: Ban automatically clears after 4 months
- **Same as Rejection**: Uses the same ban mechanism as Secretary/Department Head rejections

---

## How It Works

### 1. Application Cancellation Process

```
1. Applicant views their application in "My Applications"
2. Clicks "Cancel Application" button
3. Confirms cancellation in the confirmation dialog
4. System processes cancellation:
   - Sets status to "Cancelled"
   - Sets workflow_stage to "cancelled"
   - Sets rejected_date to NOW() (for archiving)
   - Sets rejection_reason to "Application cancelled by applicant"
   - Applies 4-month ban to applicant
   - Creates notification for applicant
   - Logs activity in admin_activity table
```

### 2. Database Changes

**job_applicants Table:**
```sql
UPDATE job_applicants SET
    status = 'Cancelled',
    workflow_stage = 'cancelled',
    rejection_reason = 'Application cancelled by applicant',
    rejected_date = NOW()
WHERE id = ?
```

**applicants Table (Ban Application):**
```sql
UPDATE applicants SET
    rejection_ban_until = DATE_ADD(NOW(), INTERVAL 4 MONTH),
    ban_reason = 'You cancelled your application. You cannot apply for new positions for 4 months.',
    banned_by = 'System (Self-Cancellation)',
    rejection_count = rejection_count + 1
WHERE id = ?
```

**application_bans Table (Audit Trail):**
```sql
INSERT INTO application_bans (
    applicant_id,
    applicant_email,
    application_id,
    banned_date,
    ban_expires,
    ban_reason,
    banned_by_name,
    banned_by_role,
    rejection_reason,
    position_applied
) VALUES (
    ?,
    ?,
    ?,
    NOW(),
    DATE_ADD(NOW(), INTERVAL 4 MONTH),
    'You cancelled your application. You cannot apply for new positions for 4 months.',
    'System (Self-Cancellation)',
    'Applicant',
    'Application cancelled by applicant',
    ?
)
```

### 3. Archive Integration

Cancelled applications appear in the **Archive** section for:
- **Secretary**: Sees all cancelled and rejected applications
- **Department Head**: Sees cancelled and rejected applications from their department
- **HR Manager/Recruiter**: Sees cancelled and rejected applications from their department

**Archive Query:**
```sql
SELECT * FROM job_applicants
WHERE workflow_stage IN ('rejected', 'cancelled')
AND assigned_to_department = ? -- (for Department Head/HR/Recruiter)
ORDER BY rejected_date DESC
```

### 4. Ban Enforcement

Same mechanism as rejection bans:

#### Application Submission Check
```php
// In user/user.php (lines 223-276)
When user submits application:
1. Check applicants.rejection_ban_until
2. If ban exists and NOT expired:
   - Block submission
   - Return error with ban details
   - Show days remaining
3. If ban expired:
   - Clear ban fields automatically
   - Allow application
```

#### UI Prevention
```javascript
// In user/user.php (lines 1088-1105)
On page load:
1. Call check_ban_status.php API
2. If banned:
   - Store ban data globally
   - Intercept "Apply Now" button clicks
   - Show modal when user tries to apply
   - Display ban expiration and reason
```

### 5. Notifications

**Applicant Notification:**
```
Title: Application Cancelled
Message: You have cancelled your application for the position of [Position]. 
         You cannot apply for new positions for 4 months.
Type: warning
```

**Admin Activity Log:**
```
Activity Type: application_cancelled
Description: [Applicant Name] cancelled application for [Position] - 4 month ban applied
```

---

## User Experience

### For Applicants

**When Cancelling:**
```
┌─────────────────────────────────────────────────┐
│ Are you sure you want to cancel this           │
│ application?                                    │
│                                                 │
│ [ Cancel ]  [ Yes, Cancel Application ]        │
└─────────────────────────────────────────────────┘
```

**After Cancellation:**
```
✓ Application cancelled successfully. 
  You cannot apply for new positions for 4 months.
  Ban expires: [Date and Time]
```

**When Trying to Apply (Banned):**
```
┌─────────────────────────────────────────────────────┐
│ ⚠️  Application Temporarily Restricted              │
│                                                      │
│ You are currently unable to submit new applications │
│ Your restriction expires on November 1, 2025        │
│ Time remaining: 120 days                            │
│                                                      │
│ Reason: You cancelled your application. You cannot  │
│         apply for new positions for 4 months.       │
│ Issued by: System (Self-Cancellation)               │
│                                                      │
│                [ I Understand ]                     │
└─────────────────────────────────────────────────────┘
```

### For Admin (Secretary/Department Head)

**Archive Table Entry:**
```
┌──────────────────────────────────────────────────────────────────┐
│ Applicant    | Position  | Applied   | Cancelled | Reason       │
│──────────────────────────────────────────────────────────────────│
│ John Doe     | Instructor| 2025-01-15| 2025-01-20| Application  │
│              |           |           |           | cancelled by │
│              |           |           |           | applicant    │
└──────────────────────────────────────────────────────────────────┘
```

Status Badge: **Cancelled** (shown in orange/amber color)

---

## Files Modified

### Backend Files
1. **user/cancel_application.php** - Enhanced to apply ban and archive
   - Applies 4-month ban to applicant
   - Sets workflow_stage to 'cancelled'
   - Sets rejected_date for archiving
   - Creates notification
   - Logs activity

2. **admin/get_archive.php** - Include cancelled applications
   - Updated WHERE clause: `workflow_stage IN ('rejected', 'cancelled')`
   - Shows cancelled applications in archive section

### Database Migration
3. **database/migrations/add_cancelled_workflow_stage.php** - New migration
   - Adds 'cancelled' to workflow_stage ENUM
   - Run this migration to enable the feature

### Documentation
4. **docs/APPLICATION_CANCELLATION_BAN.md** - This file
   - Complete documentation of cancellation ban system

---

## Setup Instructions

### 1. Run Database Migration
```bash
cd c:\xampp\htdocs\FinalResearch - Copy\database\migrations
php add_cancelled_workflow_stage.php
```

Expected output:
```
=== Add Cancelled Workflow Stage Migration ===

1. Checking current workflow_stage ENUM values...
   Adding 'cancelled' to workflow_stage ENUM...
   ✓ 'cancelled' workflow stage added successfully

=== Migration Complete ===
✓ Cancelled workflow stage is now available
✓ Cancelled applications will be archived
✓ Applicants who cancel will be banned for 4 months
```

### 2. Verify Setup
1. Log in as an applicant
2. Submit a job application
3. Go to "My Applications"
4. Click "Cancel Application"
5. Confirm cancellation
6. Verify:
   - Success message shows ban duration
   - Notification created
   - Try to apply again - should be blocked with ban message

### 3. Verify Admin Archive
1. Log in as Secretary or Department Head
2. Navigate to Archive section
3. Verify cancelled application appears in the table
4. Status should show "Cancelled"
5. Reason should show "Application cancelled by applicant"

---

## Technical Details

### Workflow Stage Flow
```
secretary_review → ... → cancelled (when applicant cancels)
                    ↓
                 archived (rejected_date set)
```

### Ban Logic
- Same as rejection ban (4 months)
- Uses `applicants.rejection_ban_until` field
- Increases `rejection_count` by 1
- Logged in `application_bans` table

### Archive Logic
- Cancelled applications have `rejected_date` set
- Visible in archive when `workflow_stage = 'cancelled'`
- Searchable and filterable like rejected applications

---

## Differences from Rejection

| Aspect | Rejection (Secretary/Dept Head) | Cancellation (Applicant) |
|--------|--------------------------------|--------------------------|
| Ban Duration | 4 months | 4 months |
| Ban Reason | "Application rejected by [Role]" | "You cancelled your application" |
| Banned By | "Secretary: [Name]" or "Department Head: [Name]" | "System (Self-Cancellation)" |
| Status | "Rejected" | "Cancelled" |
| Workflow Stage | 'rejected' | 'cancelled' |
| Archive Visibility | Yes | Yes |
| Notification Type | error (red) | warning (orange) |

---

## Benefits

1. **Prevents Abuse**: Applicants cannot spam applications and cancel them repeatedly
2. **Encourages Commitment**: Makes applicants think carefully before applying
3. **Fair Treatment**: Same consequence as being rejected
4. **Administrative Clarity**: Cancelled applications archived separately from rejections
5. **Complete Audit Trail**: All cancellations logged for review

---

## FAQ

**Q: Why ban applicants who cancel their own applications?**
A: To prevent abuse of the system and ensure applicants are committed when they apply.

**Q: Can the ban duration be customized?**
A: Yes, modify the `+4 months` in `cancel_application.php` line 86.

**Q: Can cancelled applications be restored?**
A: No, cancellation is permanent like rejection.

**Q: Can admins see who cancelled vs who was rejected?**
A: Yes, the Status field shows "Cancelled" vs "Rejected", and the Reason field indicates the source.

**Q: What if an applicant cancels by mistake?**
A: The ban applies immediately. Contact system administrator to manually remove the ban if needed.

---

## Future Enhancements

Potential improvements:
1. Configurable ban duration per department
2. Warning count system (e.g., 1st cancellation = warning, 2nd = ban)
3. Email notification when ban expires
4. Dashboard indicator showing ban status to applicants
5. Ability for admins to waive cancellation bans in special cases
