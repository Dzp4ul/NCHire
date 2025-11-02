/**
 * Notifications Module
 * Handles notification loading and display
 */

// Load notifications on page load
document.addEventListener('DOMContentLoaded', function() {
    loadNotifications();
    
    // Refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);
});

/**
 * Load notifications from server
 */
function loadNotifications() {
    fetch('get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayNotifications(data.notifications);
                updateNotificationBadge(data.unread_count);
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
        });
}

/**
 * Display notifications in dropdown
 */
function displayNotifications(notifications) {
    const container = document.getElementById('notificationList');
    if (!container) return;
    
    if (notifications.length === 0) {
        container.innerHTML = `
            <div class="p-8 text-center text-gray-500">
                <i class="ri-notification-off-line text-3xl mb-2 block"></i>
                <p class="text-sm">No notifications</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = notifications.map(notif => `
        <div class="p-4 hover:bg-gray-50 cursor-pointer ${notif.is_read ? '' : 'bg-blue-50'}" 
             onclick="markAsRead(${notif.id}, '${notif.link || ''}')">
            <div class="flex items-start gap-3">
                <i class="ri-notification-line text-primary text-xl"></i>
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900">${escapeHtml(notif.message)}</p>
                    <p class="text-xs text-gray-500 mt-1">${formatDateTime(notif.created_at)}</p>
                </div>
                ${!notif.is_read ? '<span class="w-2 h-2 bg-blue-500 rounded-full"></span>' : ''}
            </div>
        </div>
    `).join('');
}

/**
 * Update notification badge
 */
function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (!badge) return;
    
    if (count > 0) {
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

/**
 * Mark notification as read
 */
function markAsRead(notificationId, link) {
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
            if (link) {
                window.location.href = link;
            }
        }
    });
}
