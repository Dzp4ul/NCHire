# Clickable Notifications Implementation Guide

## Overview
Make notifications clickable so users can navigate to relevant pages based on notification type.

## Database Changes Required

### 1. Add `related_url` column to `notifications` table

```sql
ALTER TABLE notifications 
ADD COLUMN related_url VARCHAR(255) DEFAULT NULL AFTER message,
ADD COLUMN notification_type VARCHAR(50) DEFAULT 'general' AFTER related_url;
```

## Implementation Steps

### Step 1: Update Backend (process_applicant_action.php)

When creating notifications, include the `related_url`:

```php
// Example for Interview Scheduled
$notification_url = "?view=applications&action=view_progress&app_id=" . $applicant_id;
$notification_type = "interview_scheduled";

$stmt = $conn->prepare("INSERT INTO notifications 
    (user_id, title, message, related_url, notification_type, created_at) 
    VALUES (?, ?, ?, ?, ?, NOW())");
```

### Step 2: Update get_notifications.php

Modify to include `related_url` and `notification_type` in response:

```php
$result = $conn->query("SELECT 
    id, 
    title, 
    message, 
    related_url, 
    notification_type, 
    is_read, 
    created_at 
FROM notifications 
WHERE user_id = $user_id 
ORDER BY created_at DESC 
LIMIT 20");
```

### Step 3: Update Frontend (user.php)

Modify notification rendering to make items clickable:

```javascript
function renderNotification(notification) {
    const isClickable = notification.related_url && notification.related_url.trim() !== '';
    const cursorClass = isClickable ? 'cursor-pointer hover:bg-gray-100' : '';
    
    const onClick = isClickable 
        ? `onclick="handleNotificationClick('${notification.id}', '${notification.related_url}')"` 
        : '';
    
    return `
        <div class="p-3 border-b border-gray-100 ${cursorClass}" ${onClick}>
            <div class="flex items-start">
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900">${notification.title}</p>
                    <p class="text-xs text-gray-600 mt-1">${notification.message}</p>
                    <p class="text-xs text-gray-400 mt-1">${formatTime(notification.created_at)}</p>
                </div>
                ${isClickable ? '<i class="ri-arrow-right-line text-gray-400"></i>' : ''}
            </div>
        </div>
    `;
}

function handleNotificationClick(notificationId, url) {
    // Mark notification as read
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `notification_id=${notificationId}`
    });
    
    // Navigate to the URL
    if (url.startsWith('?')) {
        // Internal navigation
        const params = new URLSearchParams(url.substring(1));
        const view = params.get('view');
        const action = params.get('action');
        const appId = params.get('app_id');
        
        if (view === 'applications') {
            // Load My Applications
            const applicationsLink = document.getElementById('applicationsLink');
            if (applicationsLink) {
                applicationsLink.click();
                
                // If there's an app_id, open that specific application
                if (appId && action === 'view_progress') {
                    setTimeout(() => {
                        if (typeof viewExistingApplication === 'function') {
                            viewExistingApplication(appId);
                        }
                    }, 500);
                }
            }
        } else if (view === 'profile') {
            // Navigate to profile
            window.location.href = 'user_profile.php';
        }
    } else {
        // External URL
        window.location.href = url;
    }
    
    // Close notification dropdown
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown) dropdown.classList.add('hidden');
}
```

## Notification Types and URLs

### Interview Scheduled
- **Type**: `interview_scheduled`
- **URL**: `?view=applications&action=view_progress&app_id=123`
- **Action**: Opens application wizard showing interview details

### Demo Teaching Scheduled
- **Type**: `demo_scheduled`
- **URL**: `?view=applications&action=view_progress&app_id=123`
- **Action**: Opens application wizard showing demo details

### Resubmission Required
- **Type**: `resubmission_required`
- **URL**: `?view=applications&action=view_progress&app_id=123`
- **Action**: Opens application wizard showing required documents

### Application Status Change
- **Type**: `status_change`
- **URL**: `?view=applications&action=view_details&app_id=123`
- **Action**: Opens My Applications showing status update

### Profile Update Reminder
- **Type**: `profile_reminder`
- **URL**: `user_profile.php`
- **Action**: Redirects to profile page

## Testing Checklist

- [ ] Database column added successfully
- [ ] Backend creates notifications with URLs
- [ ] get_notifications.php returns URL data
- [ ] Frontend renders clickable notifications
- [ ] Click marks notification as read
- [ ] Navigation works for My Applications
- [ ] Navigation works for Profile
- [ ] Arrow icon appears for clickable notifications
- [ ] Dropdown closes after clicking
- [ ] Non-clickable notifications remain static

## Future Enhancements

1. **Deep Linking**: Support for opening specific wizard steps
2. **Notification Actions**: Allow inline actions (Approve, Reject) from dropdown
3. **Read Receipts**: Track when notifications were read
4. **Notification Grouping**: Group similar notifications
5. **Push Notifications**: Browser push notifications for real-time updates
