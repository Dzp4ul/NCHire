# Application Ban System Documentation

## Overview
When a Secretary or Department Head rejects an applicant's application, the applicant is automatically banned from submitting new applications for **4 months (1 semester)**.

---

## Ban Duration
- **Length**: 4 months from the date of rejection
- **Trigger**: Secretary rejection OR Department Head rejection
- **Automatic Expiry**: Ban automatically clears after 4 months

---

## Database Schema

### Applicants Table (New Columns)
```sql
- rejection_ban_until (DATETIME) - Expiration date of ban
- ban_reason (TEXT) - Reason for the ban
- banned_by (VARCHAR) - Who issued the ban (Secretary/Department Head name)
- rejection_count (INT) - Number of times applicant has been rejected
```

### Application_Bans Table (Audit Trail)
```sql
CREATE TABLE application_bans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    applicant_id INT NOT NULL,
    applicant_email VARCHAR(255) NOT NULL,
    application_id INT NULL,
    banned_date DATETIME NOT NULL,
    ban_expires DATETIME NOT NULL,
    ban_reason TEXT NOT NULL,
    banned_by_id INT NULL,
    banned_by_name VARCHAR(255) NOT NULL,
    banned_by_role VARCHAR(50) NOT NULL,
    rejection_reason TEXT NOT NULL,
    position_applied VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## How It Works

### 1. Rejection Process

#### Secretary Rejection
```
1. Secretary reviews application
2. Clicks "Reject Application"
3. Enters rejection reason
4. System:
   - Sets applicant status to "Rejected"
   - Calculates ban_expires = NOW() + 4 months
   - Updates applicants table with ban info
   - Logs rejection in application_bans table
   - Sends email notification to applicant
```

#### Department Head Rejection
```
1. Department Head reviews application
2. Clicks "Reject Application"
3. Enters rejection reason
4. System performs same ban process as Secretary
```

### 2. Ban Enforcement

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
// In user/user.php (lines 942-1042)
On page load:
1. Call check_ban_status.php API
2. If banned:
   - Display prominent red warning banner
   - Show ban expiration date and days remaining
   - Disable all "Apply Now" buttons
   - Show error toast when user clicks disabled buttons
```

### 3. Ban Display

#### Warning Banner (Top of Page)
```
┌─────────────────────────────────────────────────────────┐
│ ⚠️  Application Temporarily Restricted                   │
│                                                          │
│ You are currently unable to submit new job applications.│
│ Your restriction expires on November 1, 2025 at 7:48 PM │
│ Time remaining: 120 days (approximately 2880 hours)     │
│                                                          │
│ ┌──────────────────────────────────────────────────┐   │
│ │ Reason: Application rejected by Secretary.       │   │
│ │ Issued by: Secretary: Jane Doe                   │   │
│ └──────────────────────────────────────────────────┘   │
│                                                          │
│ ℹ️  After the restriction expires, you will be able to  │
│    apply for positions again.                           │
└─────────────────────────────────────────────────────────┘
```

#### Apply Buttons - Clickable with Modal Warning
- All "Apply Now" buttons **remain enabled and clickable**
- Buttons keep their normal appearance (blue/primary color)
- When clicked, shows a professional modal popup
- Modal displays:
  - "Application Rejected" title with red warning icon
  - Clear message about temporary restriction
  - Ban expiration date and time remaining
  - Rejection reason
  - Who issued the ban (Secretary/Department Head)
  - "I Understand" button to close
- Can be closed by clicking outside modal or close button

---

## API Endpoints

### Check Ban Status
**Endpoint**: `user/check_ban_status.php`

**Response (Banned)**:
```json
{
  "banned": true,
  "ban_until": "2025-03-01 15:30:00",
  "ban_until_formatted": "March 1, 2025 at 3:30 PM",
  "ban_reason": "Application rejected by Department Head. Reason: Incomplete qualifications",
  "banned_by": "Department Head: John Smith",
  "days_remaining": 45,
  "hours_remaining": 1080,
  "rejection_count": 2
}
```

**Response (Not Banned)**:
```json
{
  "banned": false
}
```

---

## Testing Guide

### Prerequisites
1. Database migration completed
2. Two user roles: Secretary OR Department Head
3. Test applicant account
4. Active job posting

### Test Scenario 1: Secretary Rejection Ban

**Steps:**
1. **Applicant**: Submit job application
2. **Secretary**: Login to admin panel
3. **Secretary**: Navigate to "Document Review"
4. **Secretary**: Click on applicant's application
5. **Secretary**: Click "Reject Application"
6. **Secretary**: Enter reason: "Test rejection - incomplete documents"
7. **Secretary**: Confirm rejection

**Expected Results:**
- ✅ Application status = "Rejected"
- ✅ Applicant receives rejection email
- ✅ Database: `applicants.rejection_ban_until` = NOW() + 4 months
- ✅ Database: `applicants.ban_reason` = rejection details
- ✅ Database: `applicants.banned_by` = "Secretary: [Name]"
- ✅ Database: `applicants.rejection_count` incremented
- ✅ Record created in `application_bans` table

**Verify Ban Enforcement:**
1. **Applicant**: Logout and login again
2. **Applicant**: Visit dashboard
3. **Check**: Red warning banner displayed at top
4. **Check**: All "Apply Now" buttons are gray/disabled
5. **Check**: Banner shows correct expiration date
6. **Check**: Banner shows "120 days remaining" (approximately)
7. **Applicant**: Click any "Apply Now" button
8. **Check**: Error toast appears: "You cannot apply for jobs until..."

**Try to Apply:**
1. **Applicant**: Attempt to submit application directly
2. **Expected**: Blocked with error message
3. **Expected**: Error shows days remaining
4. **Expected**: Application NOT saved to database

### Test Scenario 2: Department Head Rejection Ban

**Steps:**
1. **Applicant**: Submit job application
2. **Secretary**: Transfer application to Department Head
3. **Dept Head**: Login to admin panel
4. **Dept Head**: Review application
5. **Dept Head**: Click "Reject Application"
6. **Dept Head**: Enter reason: "Test rejection - not qualified"
7. **Dept Head**: Confirm rejection

**Expected Results:**
- Same as Secretary rejection
- `banned_by` = "Department Head: [Name]"

### Test Scenario 3: Ban Expiration

**Steps:**
1. **Database**: Manually set `rejection_ban_until` to yesterday's date
   ```sql
   UPDATE applicants 
   SET rejection_ban_until = DATE_SUB(NOW(), INTERVAL 1 DAY) 
   WHERE id = [applicant_id];
   ```
2. **Applicant**: Logout and login
3. **Applicant**: Visit dashboard

**Expected Results:**
- ✅ No warning banner shown
- ✅ All "Apply Now" buttons enabled
- ✅ Can submit new applications
- ✅ Database: Ban fields automatically cleared
- ✅ Database: `rejection_ban_until`, `ban_reason`, `banned_by` = NULL

### Test Scenario 4: Multiple Rejections

**Steps:**
1. Complete Test Scenario 1 or 2
2. Wait for ban to expire (or manually expire it)
3. Submit new application
4. Get rejected again

**Expected Results:**
- ✅ New 4-month ban applied
- ✅ `rejection_count` = 2
- ✅ New record in `application_bans` table
- ✅ Ban history preserved

---

## SQL Queries for Testing

### Check Ban Status
```sql
SELECT id, first_name, last_name, email,
       rejection_ban_until, ban_reason, banned_by, rejection_count
FROM applicants
WHERE rejection_ban_until IS NOT NULL;
```

### View Ban History
```sql
SELECT ab.*, a.first_name, a.last_name
FROM application_bans ab
JOIN applicants a ON ab.applicant_id = a.id
ORDER BY ab.banned_date DESC;
```

### Manually Set Ban (For Testing)
```sql
UPDATE applicants
SET rejection_ban_until = DATE_ADD(NOW(), INTERVAL 4 MONTH),
    ban_reason = 'Manual test ban',
    banned_by = 'Test Admin',
    rejection_count = rejection_count + 1
WHERE id = [applicant_id];
```

### Clear Ban (For Testing)
```sql
UPDATE applicants
SET rejection_ban_until = NULL,
    ban_reason = NULL,
    banned_by = NULL
WHERE id = [applicant_id];
```

### Check Active Bans
```sql
SELECT COUNT(*) as active_bans
FROM applicants
WHERE rejection_ban_until IS NOT NULL
  AND rejection_ban_until > NOW();
```

---

## Files Modified

### Database
- `database/migrations/add_application_ban_system.php` - Migration script

### Backend
- `admin/api/secretary_actions.php` - Secretary rejection handler
- `admin/process_applicant_action.php` - Department Head rejection handler
- `user/user.php` - Application submission ban checking (lines 223-276)
- `user/check_ban_status.php` - Ban status API (NEW)

### Frontend
- `user/user.php` - Ban warning UI and button disabling (lines 942-1042)

---

## Troubleshooting

### Ban Not Showing
1. Check database: `SELECT rejection_ban_until FROM applicants WHERE id = ?`
2. Check API response: Visit `user/check_ban_status.php` directly
3. Check browser console for JavaScript errors
4. Verify user is logged in with correct session

### Ban Not Enforcing
1. Clear browser cache
2. Check session variables: `$_SESSION['user_id']`
3. Verify database columns exist
4. Check PHP error logs

### Ban Not Clearing After Expiration
1. Ban auto-clears on:
   - User visits dashboard (JavaScript check)
   - User attempts to apply (PHP check)
2. Manual clear: Run SQL query to set fields to NULL

---

## Admin Notifications

When rejection occurs:
- ✅ Admin activity logged
- ✅ Email sent to applicant
- ✅ Notification created in notifications table
- ✅ Workflow history updated
- ✅ Ban audit trail created

---

## Security Considerations

1. **Session-Based**: Ban checking uses session user_id
2. **Server-Side Validation**: Ban enforced in PHP (can't bypass via JavaScript)
3. **Audit Trail**: Complete history in application_bans table
4. **Automatic Expiry**: No manual intervention needed
5. **Email Notifications**: Applicants informed of ban

---

## Future Enhancements

1. **Configurable Duration**: Allow admins to set ban length
2. **Ban Appeals**: Let applicants request ban review
3. **Graduated Penalties**: Longer bans for repeat offenses
4. **Admin Dashboard**: View all banned applicants
5. **Email Reminders**: Notify when ban is about to expire

---

## Support

For issues or questions:
- Check database migration logs
- Review PHP error logs
- Test with manual SQL queries
- Verify session data
- Check browser console
