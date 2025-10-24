<!DOCTYPE html>
<html>
<head>
    <title>Notification Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-lg">
        <div class="p-6">
            <h2 class="text-xl font-bold mb-4">Notification System Test</h2>
            
            <!-- Notification Bell -->
            <div class="relative inline-block">
                <button id="testNotificationBtn" class="w-10 h-10 bg-blue-500 text-white rounded-full flex items-center justify-center">
                    <i class="ri-notification-line text-xl"></i>
                    <span id="testBadge" class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full hidden"></span>
                </button>
                
                <!-- Dropdown -->
                <div id="testDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border z-50">
                    <div class="p-4 border-b">
                        <h3 class="font-semibold">Test Notifications</h3>
                    </div>
                    <div id="testNotificationsList" class="max-h-60 overflow-y-auto">
                        <div class="p-4 text-center text-gray-500">
                            <p class="text-sm">Loading...</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <button id="createTestNotification" class="px-4 py-2 bg-green-500 text-white rounded">
                    Create Test Notification
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('testNotificationBtn');
            const dropdown = document.getElementById('testDropdown');
            const list = document.getElementById('testNotificationsList');
            const badge = document.getElementById('testBadge');
            const createBtn = document.getElementById('createTestNotification');
            
            console.log('Elements found:', {
                btn: !!btn,
                dropdown: !!dropdown,
                list: !!list,
                badge: !!badge,
                createBtn: !!createBtn
            });
            
            // Toggle dropdown
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                console.log('Button clicked');
                dropdown.classList.toggle('hidden');
                if (!dropdown.classList.contains('hidden')) {
                    loadTestNotifications();
                }
            });
            
            // Close on outside click
            document.addEventListener('click', function(e) {
                if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });
            
            // Create test notification
            createBtn.addEventListener('click', function() {
                fetch('test_create_notification.php')
                    .then(response => response.text())
                    .then(data => {
                        console.log('Create response:', data);
                        alert('Test notification created!');
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            });
            
            // Load notifications
            function loadTestNotifications() {
                console.log('Loading test notifications...');
                fetch('get_notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        console.log('Response:', data);
                        if (data.success) {
                            displayTestNotifications(data.notifications);
                            updateTestBadge(data.unread_count);
                        } else {
                            list.innerHTML = '<div class="p-4 text-center text-red-500"><p class="text-sm">Error: ' + data.error + '</p></div>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        list.innerHTML = '<div class="p-4 text-center text-red-500"><p class="text-sm">Network Error</p></div>';
                    });
            }
            
            // Display notifications
            function displayTestNotifications(notifications) {
                console.log('Displaying notifications:', notifications);
                
                if (notifications.length === 0) {
                    list.innerHTML = '<div class="p-4 text-center text-gray-500"><p class="text-sm">No notifications</p></div>';
                    return;
                }
                
                let html = '';
                notifications.forEach(notification => {
                    const bgClass = notification.is_read == '0' ? 'bg-blue-50' : '';
                    html += `
                        <div class="p-4 ${bgClass} border-b hover:bg-gray-50 cursor-pointer">
                            <div class="flex items-start">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="ri-information-line text-blue-600 text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">${notification.title}</p>
                                    <p class="text-xs text-gray-600 mt-1">${notification.message}</p>
                                    <p class="text-xs text-gray-400 mt-1">${notification.created_at}</p>
                                </div>
                                ${notification.is_read == '0' ? '<div class="w-2 h-2 bg-blue-500 rounded-full ml-2 mt-2"></div>' : ''}
                            </div>
                        </div>
                    `;
                });
                
                console.log('Setting HTML:', html.substring(0, 100) + '...');
                list.innerHTML = html;
                console.log('HTML set successfully');
            }
            
            // Update badge
            function updateTestBadge(count) {
                console.log('Updating badge:', count);
                if (count > 0) {
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }
        });
    </script>
</body>
</html>
