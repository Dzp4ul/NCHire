<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

$servername = "127.0.0.1";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Live Notification Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { color: red; }
        .success { color: green; }
        .warning { color: orange; }
        pre { background: #eee; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Live Notification Debug</h1>
    
    <div class="section">
        <h2>1. Current Session Status</h2>
        <?php
        echo "<strong>Session Data:</strong><br>";
        if (isset($_SESSION['user_id'])) {
            echo "<span class='success'>✓ User logged in</span><br>";
            echo "User ID: " . $_SESSION['user_id'] . "<br>";
            echo "First Name: " . ($_SESSION['first_name'] ?? 'Not set') . "<br>";
            echo "Email: " . ($_SESSION['user_email'] ?? 'Not set') . "<br>";
            $current_user_id = $_SESSION['user_id'];
        } else {
            echo "<span class='error'>✗ No user session found</span><br>";
            echo "Available session keys: " . implode(', ', array_keys($_SESSION)) . "<br>";
            $current_user_id = null;
        }
        ?>
    </div>
    
    <div class="section">
        <h2>2. Database Notification Check</h2>
        <?php
        if ($current_user_id) {
            $stmt = $conn->prepare("SELECT id, title, message, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("i", $current_user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            echo "<strong>Notifications for User ID $current_user_id:</strong><br>";
            if ($result->num_rows > 0) {
                echo "<span class='success'>Found " . $result->num_rows . " notifications</span><br>";
                while ($row = $result->fetch_assoc()) {
                    $read_status = $row['is_read'] ? 'Read' : 'Unread';
                    echo "- ID: {$row['id']} | {$row['title']} | {$read_status} | {$row['created_at']}<br>";
                }
            } else {
                echo "<span class='warning'>No notifications found for this user</span><br>";
                
                // Check if notifications exist for other users
                $all_notifs = $conn->query("SELECT DISTINCT user_id FROM notifications");
                if ($all_notifs->num_rows > 0) {
                    echo "Notifications exist for user IDs: ";
                    while ($row = $all_notifs->fetch_assoc()) {
                        echo $row['user_id'] . " ";
                    }
                    echo "<br>";
                }
            }
        } else {
            echo "<span class='error'>Cannot check - no user session</span><br>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>3. API Test (get_notifications.php)</h2>
        <?php
        if ($current_user_id) {
            // Simulate the API call
            ob_start();
            include 'user/get_notifications.php';
            $api_response = ob_get_clean();
            
            echo "<strong>API Response:</strong><br>";
            echo "<pre>" . htmlspecialchars($api_response) . "</pre>";
            
            // Try to decode JSON
            $json_data = json_decode($api_response, true);
            if ($json_data) {
                echo "<strong>Parsed JSON:</strong><br>";
                echo "Success: " . ($json_data['success'] ? 'true' : 'false') . "<br>";
                if (isset($json_data['notifications'])) {
                    echo "Notification count: " . count($json_data['notifications']) . "<br>";
                    echo "Unread count: " . ($json_data['unread_count'] ?? 0) . "<br>";
                }
                if (isset($json_data['error'])) {
                    echo "<span class='error'>Error: " . $json_data['error'] . "</span><br>";
                }
            } else {
                echo "<span class='error'>Invalid JSON response</span><br>";
            }
        } else {
            echo "<span class='error'>Cannot test API - no user session</span><br>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>4. Create Test Notification</h2>
        <?php
        if ($current_user_id && isset($_POST['create_test'])) {
            $title = "Live Test Notification";
            $message = "This is a test notification created at " . date('Y-m-d H:i:s');
            $type = "info";
            
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $current_user_id, $title, $message, $type);
            
            if ($stmt->execute()) {
                echo "<span class='success'>✓ Test notification created successfully!</span><br>";
                echo "Notification ID: " . $conn->insert_id . "<br>";
            } else {
                echo "<span class='error'>✗ Failed to create notification: " . $stmt->error . "</span><br>";
            }
        }
        
        if ($current_user_id) {
            echo '<form method="post">';
            echo '<button type="submit" name="create_test" style="padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 5px; cursor: pointer;">Create Test Notification</button>';
            echo '</form>';
        } else {
            echo "<span class='error'>Cannot create test notification - no user session</span><br>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>5. Login Simulation</h2>
        <?php
        if (!$current_user_id && isset($_POST['simulate_login'])) {
            // Find first user with notifications
            $user_with_notifs = $conn->query("
                SELECT DISTINCT n.user_id, a.applicant_email, a.first_name 
                FROM notifications n 
                JOIN applicants a ON n.user_id = a.id 
                LIMIT 1
            ");
            
            if ($user_with_notifs->num_rows > 0) {
                $user = $user_with_notifs->fetch_assoc();
                $_SESSION['user_logged_in'] = true;
                $_SESSION['user_email'] = $user['applicant_email'];
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['first_name'] = $user['first_name'];
                
                echo "<span class='success'>✓ Simulated login for user: " . $user['first_name'] . " (ID: " . $user['user_id'] . ")</span><br>";
                echo '<a href="?" style="color: blue;">Refresh page to see updated session</a><br>';
            } else {
                echo "<span class='error'>No users with notifications found</span><br>";
            }
        }
        
        if (!$current_user_id) {
            echo '<form method="post">';
            echo '<button type="submit" name="simulate_login" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">Simulate Login</button>';
            echo '</form>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>6. JavaScript Test</h2>
        <button onclick="testNotificationAPI()" style="padding: 10px 20px; background: #6f42c1; color: white; border: none; border-radius: 5px; cursor: pointer;">Test JavaScript API Call</button>
        <div id="jsResult" style="margin-top: 10px; padding: 10px; background: #fff; border: 1px solid #ddd;"></div>
        
        <script>
        function testNotificationAPI() {
            const resultDiv = document.getElementById('jsResult');
            resultDiv.innerHTML = 'Testing API call...';
            
            fetch('user/get_notifications.php')
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.text();
                })
                .then(text => {
                    console.log('Raw response:', text);
                    resultDiv.innerHTML = '<strong>Raw Response:</strong><br><pre>' + text + '</pre>';
                    
                    try {
                        const data = JSON.parse(text);
                        resultDiv.innerHTML += '<strong>Parsed JSON:</strong><br><pre>' + JSON.stringify(data, null, 2) + '</pre>';
                    } catch (e) {
                        resultDiv.innerHTML += '<br><span style="color: red;">JSON Parse Error: ' + e.message + '</span>';
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    resultDiv.innerHTML = '<span style="color: red;">Fetch Error: ' + error.message + '</span>';
                });
        }
        </script>
    </div>
    
</body>
</html>

<?php $conn->close(); ?>
