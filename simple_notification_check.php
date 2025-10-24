<?php
session_start();

$servername = "127.0.0.1";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);

// Auto-login as user 1 for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_email'] = 'test@example.com';
    $_SESSION['user_id'] = 1;
    $_SESSION['first_name'] = 'Test User';
}

$user_id = $_SESSION['user_id'];

// Ensure notifications exist
$check = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id");
$count = $check->fetch_assoc()['count'];

if ($count == 0) {
    $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES 
        ($user_id, 'Test Notification 1', 'This is a test notification message.', 'info'),
        ($user_id, 'Test Notification 2', 'Another test notification for debugging.', 'warning')");
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Notification Check</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body class="p-8">
    <h1 class="text-2xl font-bold mb-4">Notification System Check</h1>
    
    <p>User ID: <?php echo $user_id; ?></p>
    <p>Session Status: <?php echo isset($_SESSION['user_id']) ? 'Logged In' : 'Not Logged In'; ?></p>
    
    <div class="mt-6">
        <h2 class="text-xl font-semibold mb-2">Notification Bell Test</h2>
        <div class="relative inline-block">
            <button id="notificationBtn" class="relative p-3 bg-blue-500 text-white rounded-full hover:bg-blue-600">
                <i class="ri-notification-3-line text-xl"></i>
                <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
            </button>
            
            <div id="notificationDropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border hidden z-50">
                <div class="p-4 border-b">
                    <h3 class="font-semibold">Notifications</h3>
                </div>
                <div id="notificationList" class="max-h-64 overflow-y-auto">
                    <div class="p-4 text-center text-gray-500">Loading...</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mt-6">
        <h2 class="text-xl font-semibold mb-2">Debug Console</h2>
        <div id="debugConsole" class="bg-gray-100 p-4 rounded text-sm font-mono h-32 overflow-auto"></div>
    </div>
    
    <div class="mt-4">
        <button onclick="testAPI()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Test API</button>
        <button onclick="loadNotifications()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Load Notifications</button>
    </div>

    <script>
        const debugConsole = document.getElementById('debugConsole');
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationBadge = document.getElementById('notificationBadge');
        const notificationList = document.getElementById('notificationList');
        
        function debug(message) {
            const time = new Date().toLocaleTimeString();
            debugConsole.innerHTML += `[${time}] ${message}\n`;
            debugConsole.scrollTop = debugConsole.scrollHeight;
            console.log(message);
        }
        
        function updateBadge(count) {
            debug(`Updating badge: ${count}`);
            if (count > 0) {
                notificationBadge.textContent = count;
                notificationBadge.classList.remove('hidden');
            } else {
                notificationBadge.classList.add('hidden');
            }
        }
        
        function displayNotifications(notifications) {
            debug(`Displaying ${notifications.length} notifications`);
            
            if (notifications.length === 0) {
                notificationList.innerHTML = '<div class="p-4 text-center text-gray-500">No notifications</div>';
                return;
            }
            
            let html = '';
            notifications.forEach(notif => {
                const isRead = notif.is_read == 1;
                html += `
                    <div class="p-3 border-b ${!isRead ? 'bg-blue-50' : ''}">
                        <div class="font-medium">${notif.title}</div>
                        <div class="text-sm text-gray-600">${notif.message}</div>
                        <div class="text-xs text-gray-400 mt-1">${notif.created_at}</div>
                    </div>
                `;
            });
            
            notificationList.innerHTML = html;
            debug('Notifications displayed successfully');
        }
        
        function loadNotifications() {
            debug('Loading notifications...');
            
            fetch('user/get_notifications.php')
                .then(response => {
                    debug(`Response status: ${response.status}`);
                    return response.text();
                })
                .then(text => {
                    debug(`Raw response: ${text.substring(0, 100)}...`);
                    
                    try {
                        const data = JSON.parse(text);
                        debug(`JSON parsed successfully`);
                        
                        if (data.success) {
                            const notifications = data.notifications || [];
                            const unreadCount = data.unread_count || 0;
                            
                            debug(`Success: ${notifications.length} notifications, ${unreadCount} unread`);
                            displayNotifications(notifications);
                            updateBadge(unreadCount);
                        } else {
                            debug(`API Error: ${data.error}`);
                            notificationList.innerHTML = `<div class="p-4 text-red-500">Error: ${data.error}</div>`;
                        }
                    } catch (e) {
                        debug(`JSON Parse Error: ${e.message}`);
                        debug(`Raw text was: ${text}`);
                        notificationList.innerHTML = '<div class="p-4 text-red-500">Invalid response</div>';
                    }
                })
                .catch(error => {
                    debug(`Fetch Error: ${error.message}`);
                    notificationList.innerHTML = `<div class="p-4 text-red-500">Network error: ${error.message}</div>`;
                });
        }
        
        function testAPI() {
            debug('Testing API directly...');
            loadNotifications();
        }
        
        // Bell click handler
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            debug('Bell clicked');
            
            if (notificationDropdown.classList.contains('hidden')) {
                debug('Opening dropdown');
                notificationDropdown.classList.remove('hidden');
                notificationDropdown.style.display = 'block';
                loadNotifications();
            } else {
                debug('Closing dropdown');
                notificationDropdown.classList.add('hidden');
                notificationDropdown.style.display = 'none';
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationDropdown.contains(e.target) && !notificationBtn.contains(e.target)) {
                notificationDropdown.classList.add('hidden');
                notificationDropdown.style.display = 'none';
            }
        });
        
        // Initial load
        debug('Page loaded, testing notification system...');
        setTimeout(() => {
            debug('Running initial test...');
            loadNotifications();
        }, 1000);
    </script>
</body>
</html>
