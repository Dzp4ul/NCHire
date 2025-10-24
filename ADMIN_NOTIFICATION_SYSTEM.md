# Admin Notification System

## ✅ Successfully Implemented

A complete real-time notification system for all admin users that notifies them about applicant actions and sends email notifications.

---

## 🎯 Features Implemented

### 1. **Database Structure**
- **Table**: `admin_notifications`
- **Fields**:
  - `id` - Primary key
  - `admin_id` - NULL = all admins see it, specific ID = only that admin
  - `title` - Notification title
  - `message` - Notification message
  - `type` - info, success, warning, danger
  - `action_type` - interview_scheduled, demo_scheduled, hired, etc.
  - `applicant_id` - Related applicant ID
  - `applicant_name` - Related applicant name
  - `is_read` - Read status (0 = unread, 1 = read)
  - `created_at` - Timestamp
  - `read_at` - When marked as read

### 2. **Notification Triggers**
Admin notifications are created and emails sent when:
- ✅ **Interview Scheduled** - Blue info notification
- ✅ **Demo Teaching Scheduled** - Blue info notification
- ✅ **Interview Rescheduled** - Orange warning notification
- ✅ **Demo Rescheduled** - Orange warning notification
- ✅ **Applicant Hired** - Green success notification

### 3. **Email Notifications**
All active admin users receive professional HTML emails when actions occur:
- **Sender**: NCHire - Norzagaray College
- **Design**: Professional gradient header with college branding
- **Content**: Action details with color-coded alert boxes
- **CTA**: "View in Admin Panel" button
- **Responsive**: Works on mobile and desktop

### 4. **UI Components**

**Notification Bell Icon**:
- Located in admin header (top-right)
- Red badge shows unread count (hidden when 0)
- Badge shows "99+" for counts over 99
- Click to open dropdown

**Notification Dropdown**:
- 96 (w-96) width, max 96 (max-h-96) height
- Blue gradient header with "Mark all as read" button
- Scrollable notification list
- Real-time updates every 30 seconds

**Notification Cards**:
- Color-coded icons based on type
- Shows title, message, applicant name
- "Time ago" format (e.g., "5 minutes ago")
- Blue background for unread notifications
- Click to mark as read

---

## 📁 Files Created/Modified

### **Created Files**:
1. `admin/create_admin_notifications_table.php` - Database table creation
2. `admin/api/admin_notifications.php` - REST API for notifications
3. `admin/admin_notification_helper.php` - Helper functions for creating notifications and sending emails

### **Modified Files**:
1. `admin/process_applicant_action.php` - Added notification creation for all actions
2. `admin/index.php` - Added notification UI and JavaScript

---

## 🔧 Technical Implementation

### **Backend (PHP)**

**Notification Creation**:
```php
createAdminNotification(
    $conn,                      // Database connection
    "Interview Scheduled",      // Title
    "Interview for John Doe...", // Message
    'info',                     // Type
    'interview_scheduled',      // Action type
    $applicant_id,             // Applicant ID
    $applicant_name,           // Applicant name
    true                       // Send email
);
```

**Email Sending**:
- Automatically sends to ALL active admin users
- Uses existing PHPMailer configuration
- Professional HTML template with color-coding
- Error logging for debugging

### **Frontend (JavaScript)**

**Auto-Load & Refresh**:
```javascript
// Load on page load
loadNotifications();

// Auto-refresh every 30 seconds
setInterval(loadNotifications, 30000);
```

**API Endpoints**:
- `GET api/admin_notifications.php?limit=20` - Fetch notifications
- `POST api/admin_notifications.php` - Mark single notification as read
- `PUT api/admin_notifications.php` - Mark all as read

---

## 🎨 Notification Types & Colors

| Type | Color | Use Case |
|------|-------|----------|
| `info` | Blue | Scheduling (interview, demo, psych exam) |
| `success` | Green | Hiring, approvals |
| `warning` | Orange | Rescheduling, resubmission requests |
| `danger` | Red | Rejections, errors |

---

## 📧 Email Template Features

- **Gradient Header**: Blue gradient with NCHire branding
- **Color-Coded Alerts**: Match notification type
- **Applicant Info**: Shows who the action is for
- **Action Button**: "View in Admin Panel" link
- **Footer**: Professional branding and disclaimer
- **Responsive**: Mobile-friendly design

---

## 🔄 Workflow Example

### **Interview Scheduling**:
1. Admin schedules interview for applicant
2. System:
   - Updates `job_applicants` table
   - Sends email to applicant
   - Creates `admin_notifications` record
   - Sends email to ALL active admins
3. All admins see:
   - Red badge on bell icon (unread count)
   - Notification in dropdown
   - Email in inbox
4. Admin clicks notification → Marks as read
5. Badge count decreases

---

## 🚀 Setup Instructions

1. **Create Database Table**:
   ```
   http://localhost/FinalResearch - Copy/admin/create_admin_notifications_table.php
   ```

2. **Ensure PHPMailer is Configured**:
   - Already set up in `email_helper.php`
   - Uses Gmail SMTP

3. **Test Notification System**:
   - Schedule an interview
   - Check notification bell icon
   - Check admin emails
   - Click notification to mark as read

---

## ✨ Features

### **For Admins**:
- ✅ Real-time notifications in-app
- ✅ Email notifications
- ✅ Unread count badge
- ✅ Mark individual as read
- ✅ Mark all as read
- ✅ Auto-refresh (30 seconds)
- ✅ Time ago display
- ✅ Color-coded by importance
- ✅ Applicant name display
- ✅ Scrollable list

### **For System**:
- ✅ Automatic notification creation
- ✅ Batch email sending to all admins
- ✅ Error logging
- ✅ Session-based admin identification
- ✅ Efficient database queries
- ✅ Clean UI integration

---

## 🎯 Notification Actions Covered

| Action | Applicant Notification | Admin Notification | Email to Admins |
|--------|----------------------|-------------------|-----------------|
| Interview Scheduled | ✅ | ✅ | ✅ |
| Demo Scheduled | ✅ | ✅ | ✅ |
| Interview Rescheduled | ✅ | ✅ | ✅ |
| Demo Rescheduled | ✅ | ✅ | ✅ |
| Hired | ✅ | ✅ | ✅ |
| Psych Exam Scheduled | ✅ | ❌ | ❌ |
| Initially Hired | ✅ | ❌ | ❌ |
| Permanently Hired | ✅ | ❌ | ❌ |
| Rejection | ✅ | ❌ | ❌ |
| Resubmission | ✅ | ❌ | ❌ |

---

## 🔍 Debugging

**Check if notifications are created**:
```sql
SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT 10;
```

**Check email logs**:
- Look in PHP error log for email success/failure messages

**Check notification badge**:
- Open browser console
- Look for "Error loading notifications" messages

---

## 🎉 Success!

The notification system is now fully functional:
- ✅ All admins notified of key actions
- ✅ Professional email notifications
- ✅ Real-time UI updates
- ✅ Clean, intuitive interface
- ✅ Automatic and efficient
