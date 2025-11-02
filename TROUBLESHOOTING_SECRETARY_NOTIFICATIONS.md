# Troubleshooting Secretary Transfer Notifications

## Step 1: Run the Test Script

Visit this URL in your browser:
```
http://localhost/FinalResearch - Copy/admin/test_secretary_transfer_notification.php
```

This will show you:
- ✅ Which database tables exist
- ✅ If email helper files are found
- ✅ Test applications available
- ✅ Department heads in the system
- ✅ Whether test notifications can be created

## Step 2: Check PHP Error Logs

### For XAMPP:
1. Open: `C:\xampp\apache\logs\error.log`
2. Look for recent entries with these patterns:
   - `=== APPLICANT NOTIFICATION DEBUG ===`
   - `=== DEPARTMENT HEAD NOTIFICATION DEBUG ===`
   - `=== APPLICANT EMAIL DEBUG ===`
   - `=== DEPARTMENT HEAD EMAIL DEBUG ===`

### What to Look For:

**Success Indicators:**
- ✅ `In-app notification created successfully (ID: X)`
- ✅ `Admin notification created successfully (ID: X)`
- ✅ `Email notification sent successfully`

**Problem Indicators:**
- ❌ `No active department head found for department: X`
- ❌ `Failed to execute notification insert`
- ❌ `sendEmailNotification function not available`
- ⚠️ `Skipping applicant notification - no email address`

## Step 3: Common Issues and Solutions

### Issue 1: No Department Head Found

**Symptoms:**
- Log shows: "No active department head found for department: X"
- Applicant gets notification, but department head doesn't

**Solution:**
```sql
-- Check what departments exist
SELECT DISTINCT assigned_to_department FROM job_applicants WHERE assigned_to_department IS NOT NULL;

-- Check what department heads exist
SELECT id, full_name, email, department, role, status FROM admin_users WHERE role = 'Department Head';

-- If department names don't match, update them:
UPDATE admin_users SET department = 'Computer Science' WHERE id = X;
-- OR
UPDATE job_applicants SET assigned_to_department = 'Education' WHERE id = X;
```

### Issue 2: Email Helper Not Found

**Symptoms:**
- Log shows: "sendEmailNotification function not available"
- In-app notifications work, but no emails sent

**Solution:**
Check if file exists at:
- `admin/helpers/email_helper.php`
- OR `admin/email_helper.php`

If missing, the email helper should be in one of these locations. The code now tries multiple paths.

### Issue 3: Department Head Has NULL Department

**Symptoms:**
- Test script shows department heads with empty department field

**Solution:**
```sql
-- Update department heads with proper departments
UPDATE admin_users 
SET department = 'Computer Science' 
WHERE id = X AND role = 'Department Head';
```

### Issue 4: Application Has No Email

**Symptoms:**
- Log shows: "Skipping applicant notification - no email address"

**Solution:**
```sql
-- Check if applicant email is populated
SELECT ja.id, ja.full_name, ja.applicant_email, ja.user_id, a.applicant_email as user_email
FROM job_applicants ja
LEFT JOIN applicants a ON ja.user_id = a.id
WHERE ja.id = X;

-- If applicant_email is NULL, update it:
UPDATE job_applicants 
SET applicant_email = 'user@example.com' 
WHERE id = X;
```

### Issue 5: Notifications Not Showing in UI

**Symptoms:**
- Notifications exist in database but don't appear in dropdown
- Test script shows notifications created

**Possible Causes:**

**A. Applicant Notifications:**
```javascript
// Check browser console for errors
// The notification system should call: /user/api/get_notifications.php

// Test the API directly:
http://localhost/FinalResearch - Copy/user/api/get_notifications.php
```

**B. Admin Notifications:**
```javascript
// Admin notification API should be called
// Test the API directly:
http://localhost/FinalResearch - Copy/admin/api/admin_notifications.php
```

**C. Session Mismatch:**
```sql
-- Check if email in session matches notification email
-- In user dashboard, check browser console:
console.log('Session email:', /* check what email is used */);

-- Check notifications table:
SELECT * FROM notifications WHERE user_email = 'actual_email@example.com';
```

## Step 4: Manual Notification Test

Run this SQL to manually create a notification:

### For Applicant:
```sql
INSERT INTO notifications (user_email, user_name, title, message, type, created_at) 
VALUES ('applicant@example.com', 'John Doe', 'Test Notification', 'This is a test message.', 'info', NOW());
```

### For Department Head:
```sql
INSERT INTO admin_notifications (admin_id, title, message, type, action_type, applicant_id, applicant_name, created_at) 
VALUES (1, 'Test Admin Notification', 'This is a test for department head.', 'info', 'test', NULL, 'Test Applicant', NOW());
```

Then:
1. Refresh the page
2. Check if notification icon shows count
3. Click notification icon to see dropdown

## Step 5: Verify Email Sending

Check XAMPP error log for PHPMailer errors:
```
Email sending failed to xxx@example.com. Error: SMTP connect() failed
```

**Common Email Issues:**

1. **SMTP Credentials Wrong**
   - Check `admin/helpers/email_helper.php` lines 26-27
   - Verify Gmail credentials are correct

2. **Less Secure Apps Disabled**
   - For Gmail, enable "Less secure app access"
   - Or use App Password instead

3. **Firewall Blocking SMTP**
   - Check port 465 is not blocked
   - Try port 587 with STARTTLS instead

## Step 6: Quick Debug Checklist

Run through these checks:

- [ ] Run test script - all tables exist?
- [ ] Test script shows department heads?
- [ ] Department names match between job_applicants and admin_users?
- [ ] Check PHP error log after transfer action
- [ ] In-app notification created? (check log for "notification created successfully")
- [ ] Email sent? (check log for "Email notification sent successfully")
- [ ] Refresh admin panel - notification icon shows count?
- [ ] Click notification icon - dropdown shows notifications?

## Step 7: Contact Info for Issues

If all above steps fail, provide this information:

1. Output from test script (screenshot)
2. Last 50 lines from PHP error log after transfer
3. Result of this SQL:
```sql
SELECT COUNT(*) as count FROM admin_notifications;
SELECT COUNT(*) as count FROM notifications;
SELECT id, full_name, department, role, status FROM admin_users WHERE role = 'Department Head';
```

## Expected Behavior

When secretary transfers an application:

1. **Immediate:**
   - Database updated (workflow_stage = 'department_head_review')
   - Applicant notification created in `notifications` table
   - Department head notification created in `admin_notifications` table with specific `admin_id`
   - Success message shown to secretary

2. **Within seconds:**
   - Applicant receives email (if email helper working)
   - Department head receives email (if email helper working)
   - PHP error log shows all success messages

3. **After page refresh:**
   - Applicant sees notification in user dashboard (bell icon)
   - Department head sees notification in admin panel (bell icon)
   - Notification count updates on icon
