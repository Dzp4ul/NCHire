<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Notification Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-8">
        <h1 class="text-3xl font-bold mb-6">Live Notification System Test</h1>
        
        <!-- Notification Bell (copied from user.php) -->
        <div class="mb-8 relative">
            <button id="notificationBtn" class="relative p-2 text-gray-600 hover:text-gray-800 focus:outline-none">
                <i class="ri-notification-3-line text-2xl"></i>
                <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
            </button>
            
            <!-- Notification Dropdown -->
            <div id="notificationDropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border hidden z-50">
                <div class="p-4 border-b">
                    <div class="flex justify-between items-center">
                        <h3 class="font-semibold text-gray-800">Notifications</h3>
                        <button id="markAllRead" class="text-sm text-blue-600 hover:text-blue-800">Mark all read</button>
                    </div>
                </div>
                <div id="notificationList" class="max-h-96 overflow-y-auto">
                    <div class="p-4 text-center text-gray-500">Loading notifications...</div>
                </div>
            </div>
        </div>
        
        <!-- Test Controls -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-semibold mb-4">Test Controls</h2>
            <div class="space-y-4">
                <button id="createTestNotification" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Create Test Notification
                </button>
                <button id="loadNotifications" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    Reload Notifications
                </button>
                <button id="checkSession" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                    Check Session
                </button>
            </div>
        </div>
        
        <!-- Debug Output -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold mb-4">Debug Output</h2>
            <div id="debugOutput" class="bg-gray-100 p-4 rounded text-sm font-mono min-h-32 overflow-auto">
                Ready for testing...
            </div>
        </div>
    </div>

    <script>
        const debugOutput = document.getElementById('debugOutput');
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationBadge = document.getElementById('notificationBadge');
        const notificationList = document.getElementById('notificationList');
        
        function log(message) {
            const timestamp = new Date().toLocaleTimeString();
            debugOutput.innerHTML += `[${timestamp}] ${message}\n`;
            debugOutput.scrollTop = debugOutput.scrollHeight;
        }
        
        function updateNotificationBadge(count) {
            if (count > 0) {
                notificationBadge.textContent = count;
                notificationBadge.classList.remove('hidden');
            } else {
                notificationBadge.classList.add('hidden');
            }
        }
        
        function loadNotifications() {
            log('Loading notifications...');
            
            fetch('user/get_notifications.php')
                .then(response => {
                    log(`API Response status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    log(`API Response: ${JSON.stringify(data)}`);
                    
                    if (data.success) {
                        const notifications = data.notifications || [];
                        const unreadCount = data.unread_count || 0;
                        
                        log(`Found ${notifications.length} notifications, ${unreadCount} unread`);
                        
                        updateNotificationBadge(unreadCount);
                        
                        if (notifications.length === 0) {
                            notificationList.innerHTML = '<div class="p-4 text-center text-gray-500">No notifications found</div>';
                        } else {
                            let html = '';
                            notifications.forEach(notif => {
                                const isRead = notif.is_read == 1;
                                html += `
                                    <div class="p-4 border-b hover:bg-gray-50 ${!isRead ? 'bg-blue-50' : ''}">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <h4 class="font-medium text-gray-800 ${!isRead ? 'font-semibold' : ''}">${notif.title}</h4>
                                                <p class="text-sm text-gray-600 mt-1">${notif.message}</p>
                                                <p class="text-xs text-gray-400 mt-2">${new Date(notif.created_at).toLocaleString()}</p>
                                            </div>
                                            ${!isRead ? '<div class="w-2 h-2 bg-blue-500 rounded-full ml-2 mt-2"></div>' : ''}
                                        </div>
                                    </div>
                                `;
                            });
                            notificationList.innerHTML = html;
                        }
                    } else {
                        log(`API Error: ${data.error || 'Unknown error'}`);
                        notificationList.innerHTML = '<div class="p-4 text-center text-red-500">Error loading notifications</div>';
                    }
                })
                .catch(error => {
                    log(`Fetch Error: ${error.message}`);
                    notificationList.innerHTML = '<div class="p-4 text-center text-red-500">Network error</div>';
                });
        }
        
        function createTestNotification() {
            log('Creating test notification...');
            
            fetch('user/test_create_notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=create_test'
            })
                .then(response => response.text())
                .then(data => {
                    log(`Test notification response: ${data}`);
                    // Reload notifications after creating
                    setTimeout(loadNotifications, 500);
                })
                .catch(error => {
                    log(`Error creating test notification: ${error.message}`);
                });
        }
        
        function checkSession() {
            log('Checking session...');
            
            fetch('session_debug.php')
                .then(response => response.text())
                .then(data => {
                    log('Session check completed - see browser for full details');
                    // Extract key info from HTML response
                    if (data.includes('Session user_id:')) {
                        const match = data.match(/Session user_id: (\d+)/);
                        if (match) {
                            log(`Current user_id: ${match[1]}`);
                        }
                    } else {
                        log('No user session found');
                    }
                })
                .catch(error => {
                    log(`Error checking session: ${error.message}`);
                });
        }
        
        // Event Listeners
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            log('Notification bell clicked');
            notificationDropdown.classList.toggle('hidden');
            if (!notificationDropdown.classList.contains('hidden')) {
                notificationDropdown.style.display = 'block';
                notificationDropdown.style.visibility = 'visible';
                loadNotifications();
            } else {
                notificationDropdown.style.display = 'none';
            }
        });
        
        document.addEventListener('click', function() {
            notificationDropdown.classList.add('hidden');
            notificationDropdown.style.display = 'none';
        });
        
        document.getElementById('createTestNotification').addEventListener('click', createTestNotification);
        document.getElementById('loadNotifications').addEventListener('click', loadNotifications);
        document.getElementById('checkSession').addEventListener('click', checkSession);
        
        // Initial load
        log('Page loaded, checking session and loading notifications...');
        checkSession();
        loadNotifications();
    </script>
</body>
</html>
