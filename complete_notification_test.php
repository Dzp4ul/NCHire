<?php
session_start();

$servername = "127.0.0.1";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);

// Step 1: Auto-login if not logged in
if (!isset($_SESSION['user_id'])) {
    $result = $conn->query("SELECT id, applicant_email, first_name FROM applicants WHERE id = 1");
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_email'] = $user['applicant_email'];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['first_name'] = $user['first_name'];
    }
}

$user_id = $_SESSION['user_id'] ?? 1;

// Step 2: Ensure notifications exist
$check = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id");
$count = $check->fetch_assoc()['count'];

if ($count == 0) {
    $notifications = [
        ['title' => 'Interview Scheduled', 'message' => 'Your interview has been scheduled for December 20, 2024 at 2:00 PM. Please prepare your documents.', 'type' => 'info'],
        ['title' => 'Document Resubmission Required', 'message' => 'Please resubmit your resume and cover letter with updated information.', 'type' => 'warning'],
        ['title' => 'Application Status Update', 'message' => 'Your application has been reviewed and moved to the next stage.', 'type' => 'info']
    ];
    
    foreach ($notifications as $notif) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $notif['title'], $notif['message'], $notif['type']);
        $stmt->execute();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Complete Notification Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Complete Notification System Test</h1>
        
        <!-- Status Display -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-semibold mb-4">System Status</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <strong>User ID:</strong> <?php echo $user_id; ?><br>
                    <strong>Session Status:</strong> <?php echo isset($_SESSION['user_id']) ? 'Logged In' : 'Not Logged In'; ?><br>
                    <strong>User Name:</strong> <?php echo $_SESSION['first_name'] ?? 'Unknown'; ?>
                </div>
                <div>
                    <strong>Database:</strong> Connected<br>
                    <strong>Notifications:</strong> <span id="dbNotificationCount">Loading...</span><br>
                    <strong>API Status:</strong> <span id="apiStatus">Testing...</span>
                </div>
            </div>
        </div>

        <!-- Notification Bell Test -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-semibold mb-4">Live Notification Bell</h2>
            <div class="flex items-center gap-4">
                <div class="relative">
                    <button id="notificationBtn" class="relative p-4 bg-blue-500 text-white rounded-full hover:bg-blue-600 transition-colors">
                        <i class="ri-notification-3-line text-2xl"></i>
                        <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-6 w-6 flex items-center justify-center hidden">0</span>
                    </button>
                    
                    <div id="notificationDropdown" class="absolute right-0 mt-2 w-96 bg-white rounded-lg shadow-xl border hidden z-50">
                        <div class="p-4 border-b bg-gray-50">
                            <div class="flex justify-between items-center">
                                <h3 class="font-semibold text-gray-800">Notifications</h3>
                                <button id="markAllRead" class="text-sm text-blue-600 hover:text-blue-800">Mark all read</button>
                            </div>
                        </div>
                        <div id="notificationList" class="max-h-80 overflow-y-auto">
                            <div class="p-4 text-center text-gray-500">Click bell to load notifications...</div>
                        </div>
                    </div>
                </div>
                <div class="text-sm text-gray-600">
                    Click the bell icon to test notification loading
                </div>
            </div>
        </div>

        <!-- Test Controls -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-semibold mb-4">Test Controls</h2>
            <div class="flex gap-3 flex-wrap">
                <button onclick="testAPI()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Test API</button>
                <button onclick="createTestNotification()" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">Create Test Notification</button>
                <button onclick="checkDatabase()" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">Check Database</button>
                <button onclick="clearConsole()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Clear Console</button>
            </div>
        </div>

        <!-- Debug Console -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold mb-4">Debug Console</h2>
            <div id="debugConsole" class="bg-gray-900 text-green-400 p-4 rounded text-sm font-mono h-64 overflow-auto">
                <div class="text-blue-400">[SYSTEM] Notification test initialized...</div>
            </div>
        </div>
    </div>

    <script>
        const debugConsole = document.getElementById('debugConsole');
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationBadge = document.getElementById('notificationBadge');
        const notificationList = document.getElementById('notificationList');
        
        function debug(message, type = 'INFO') {
            const timestamp = new Date().toLocaleTimeString();
            const colors = {
                'INFO': 'text-blue-400',
                'SUCCESS': 'text-green-400',
                'ERROR': 'text-red-400',
                'WARNING': 'text-yellow-400'
            };
            const color = colors[type] || 'text-white';
            
            debugConsole.innerHTML += `<div class="${color}">[${timestamp}] [${type}] ${message}</div>`;
            debugConsole.scrollTop = debugConsole.scrollHeight;
            console.log(`[${type}] ${message}`);
        }
        
        function clearConsole() {
            debugConsole.innerHTML = '<div class="text-blue-400">[SYSTEM] Console cleared...</div>';
        }
        
        function updateBadge(count) {
            debug(`Updating notification badge: ${count}`);
            if (count > 0) {
                notificationBadge.textContent = count;
                notificationBadge.classList.remove('hidden');
                debug(`Badge displayed with count: ${count}`, 'SUCCESS');
            } else {
                notificationBadge.classList.add('hidden');
                debug('Badge hidden (no unread notifications)');
            }
        }
        
        function displayNotifications(notifications) {
            debug(`Rendering ${notifications.length} notifications`);
            
            if (notifications.length === 0) {
                notificationList.innerHTML = '<div class="p-4 text-center text-gray-500">No notifications found</div>';
                return;
            }
            
            let html = '';
            notifications.forEach((notif, index) => {
                const isRead = notif.is_read == 1;
                const bgClass = !isRead ? 'bg-blue-50 border-l-4 border-blue-500' : '';
                const fontWeight = !isRead ? 'font-semibold' : '';
                const typeIcon = notif.type === 'warning' ? 'ri-alert-line text-yellow-500' : 
                               notif.type === 'error' ? 'ri-error-warning-line text-red-500' : 
                               'ri-information-line text-blue-500';
                
                html += `
                    <div class="p-4 border-b hover:bg-gray-50 ${bgClass}" data-notification-id="${notif.id}">
                        <div class="flex items-start gap-3">
                            <i class="${typeIcon} text-lg mt-1"></i>
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-800 ${fontWeight}">${notif.title}</h4>
                                <p class="text-sm text-gray-600 mt-1">${notif.message}</p>
                                <p class="text-xs text-gray-400 mt-2">${new Date(notif.created_at).toLocaleString()}</p>
                            </div>
                            ${!isRead ? '<div class="w-3 h-3 bg-blue-500 rounded-full mt-2"></div>' : ''}
                        </div>
                    </div>
                `;
            });
            
            notificationList.innerHTML = html;
            debug(`Successfully rendered ${notifications.length} notifications`, 'SUCCESS');
        }
        
        function loadNotifications() {
            debug('Loading notifications from API...');
            
            fetch('user/get_notifications.php')
                .then(response => {
                    debug(`API Response Status: ${response.status}`);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.text();
                })
                .then(text => {
                    debug(`Raw API Response: ${text.substring(0, 100)}...`);
                    
                    try {
                        const data = JSON.parse(text);
                        debug('JSON parsed successfully');
                        
                        if (data.success) {
                            const notifications = data.notifications || [];
                            const unreadCount = data.unread_count || 0;
                            
                            debug(`API Success: ${notifications.length} notifications, ${unreadCount} unread`, 'SUCCESS');
                            debug(`User ID from API: ${data.debug_user_id}`);
                            
                            displayNotifications(notifications);
                            updateBadge(unreadCount);
                            document.getElementById('apiStatus').textContent = 'Working';
                            document.getElementById('apiStatus').className = 'text-green-600';
                        } else {
                            debug(`API Error: ${data.error}`, 'ERROR');
                            notificationList.innerHTML = `<div class="p-4 text-center text-red-500">API Error: ${data.error}</div>`;
                            document.getElementById('apiStatus').textContent = 'Error';
                            document.getElementById('apiStatus').className = 'text-red-600';
                        }
                    } catch (e) {
                        debug(`JSON Parse Error: ${e.message}`, 'ERROR');
                        debug(`Raw response: ${text}`, 'ERROR');
                        notificationList.innerHTML = '<div class="p-4 text-center text-red-500">Invalid JSON response</div>';
                        document.getElementById('apiStatus').textContent = 'JSON Error';
                        document.getElementById('apiStatus').className = 'text-red-600';
                    }
                })
                .catch(error => {
                    debug(`Network Error: ${error.message}`, 'ERROR');
                    notificationList.innerHTML = `<div class="p-4 text-center text-red-500">Network Error: ${error.message}</div>`;
                    document.getElementById('apiStatus').textContent = 'Network Error';
                    document.getElementById('apiStatus').className = 'text-red-600';
                });
        }
        
        function testAPI() {
            debug('Manual API test initiated...');
            loadNotifications();
        }
        
        function createTestNotification() {
            debug('Creating test notification...');
            
            const testData = {
                title: 'Test Notification ' + Date.now(),
                message: 'This is a test notification created at ' + new Date().toLocaleString(),
                type: 'info'
            };
            
            fetch('user/test_create_notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=create_test'
            })
                .then(response => response.text())
                .then(data => {
                    debug(`Test notification response: ${data}`);
                    setTimeout(() => {
                        debug('Reloading notifications after creation...');
                        loadNotifications();
                    }, 1000);
                })
                .catch(error => debug(`Failed to create test notification: ${error.message}`, 'ERROR'));
        }
        
        function checkDatabase() {
            debug('Checking database notification count...');
            
            fetch('check_notification_status.php')
                .then(response => response.text())
                .then(data => {
                    debug('Database check completed');
                    // Extract notification count from response
                    const match = data.match(/Found (\d+) notifications/);
                    if (match) {
                        document.getElementById('dbNotificationCount').textContent = match[1];
                        debug(`Database has ${match[1]} notifications`, 'SUCCESS');
                    } else {
                        document.getElementById('dbNotificationCount').textContent = 'Unknown';
                        debug('Could not determine notification count from database', 'WARNING');
                    }
                })
                .catch(error => {
                    debug(`Database check failed: ${error.message}`, 'ERROR');
                    document.getElementById('dbNotificationCount').textContent = 'Error';
                });
        }
        
        // Event Listeners
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            debug('Notification bell clicked');
            
            const isHidden = notificationDropdown.classList.contains('hidden');
            
            if (isHidden) {
                debug('Opening notification dropdown');
                notificationDropdown.classList.remove('hidden');
                notificationDropdown.style.display = 'block';
                notificationDropdown.style.visibility = 'visible';
                loadNotifications();
            } else {
                debug('Closing notification dropdown');
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
        
        // Initial setup
        debug('Page loaded successfully');
        debug('User ID: <?php echo $user_id; ?>');
        debug('Session Status: <?php echo isset($_SESSION['user_id']) ? 'Active' : 'None'; ?>');
        
        // Initial checks
        setTimeout(() => {
            debug('Running initial system checks...');
            checkDatabase();
            testAPI();
        }, 1000);
    </script>
</body>
</html>
