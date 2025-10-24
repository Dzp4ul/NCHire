<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin User System Setup Check</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1e3a8a;
            border-bottom: 3px solid #fbbf24;
            padding-bottom: 10px;
        }
        .status {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid;
        }
        .success {
            background: #d1fae5;
            border-color: #10b981;
            color: #065f46;
        }
        .error {
            background: #fee2e2;
            border-color: #ef4444;
            color: #991b1b;
        }
        .warning {
            background: #fef3c7;
            border-color: #f59e0b;
            color: #92400e;
        }
        .info {
            background: #dbeafe;
            border-color: #3b82f6;
            color: #1e40af;
        }
        .step {
            background: #f9fafb;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 3px solid #1e3a8a;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #1e3a8a;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }
        .button:hover {
            background: #1e40af;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th, table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        table th {
            background: #f3f4f6;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Admin User System Setup Check</h1>
        
        <?php
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        // Database connection
        $host = "127.0.0.1";
        $user = "root";
        $pass = "12345678";
        $dbname = "nchire";

        $conn = new mysqli($host, $user, $pass, $dbname);

        // Step 1: Database Connection
        echo '<div class="step">';
        echo '<h2>Step 1: Database Connection</h2>';
        if ($conn->connect_error) {
            echo '<div class="status error">❌ FAILED: ' . htmlspecialchars($conn->connect_error) . '</div>';
            echo '<p><strong>Action:</strong> Please check your database credentials and ensure MySQL is running in XAMPP.</p>';
            echo '</div></div></body></html>';
            exit;
        } else {
            echo '<div class="status success">✅ Connected to database successfully</div>';
        }
        echo '</div>';

        // Step 2: Check if admin_users table exists
        echo '<div class="step">';
        echo '<h2>Step 2: Check admin_users Table</h2>';
        $check_table = $conn->query("SHOW TABLES LIKE 'admin_users'");
        if ($check_table->num_rows > 0) {
            echo '<div class="status success">✅ Table admin_users exists</div>';
            
            // Show table structure
            $structure = $conn->query("DESCRIBE admin_users");
            echo '<p><strong>Table Structure:</strong></p>';
            echo '<table>';
            echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>';
            while ($row = $structure->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['Field']) . '</td>';
                echo '<td>' . htmlspecialchars($row['Type']) . '</td>';
                echo '<td>' . htmlspecialchars($row['Null']) . '</td>';
                echo '<td>' . htmlspecialchars($row['Key']) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            
            $tableExists = true;
        } else {
            echo '<div class="status error">❌ Table admin_users does NOT exist</div>';
            echo '<div class="status warning">';
            echo '<strong>⚠️ ACTION REQUIRED:</strong><br>';
            echo 'You need to create the database table first!<br><br>';
            echo '<a href="create_admin_users_table.php" class="button">Create Database Table Now</a>';
            echo '</div>';
            $tableExists = false;
        }
        echo '</div>';

        if ($tableExists) {
            // Step 3: Check existing users
            echo '<div class="step">';
            echo '<h2>Step 3: Existing Admin Users</h2>';
            $users = $conn->query("SELECT id, full_name, email, role, department, status, created_at FROM admin_users ORDER BY created_at DESC");
            
            if ($users->num_rows > 0) {
                echo '<div class="status success">✅ Found ' . $users->num_rows . ' user(s) in database</div>';
                echo '<table>';
                echo '<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th></tr>';
                while ($row = $users->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['full_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['role']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['department']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['status']) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<div class="status warning">⚠️ No users found. You can create your first admin user now.</div>';
            }
            echo '</div>';

            // Step 4: API File Check
            echo '<div class="step">';
            echo '<h2>Step 4: API Files Check</h2>';
            if (file_exists('api/users.php')) {
                echo '<div class="status success">✅ API file exists: api/users.php</div>';
            } else {
                echo '<div class="status error">❌ API file not found: api/users.php</div>';
            }
            echo '</div>';

            // Step 5: Test Insert
            echo '<div class="step">';
            echo '<h2>Step 5: Test Database Insert</h2>';
            $test_email = 'test_' . time() . '@test.com';
            $test_password = password_hash('test123', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admin_users (full_name, email, password, role, department, status) VALUES (?, ?, ?, 'Admin', 'Computer Science', 'Active')");
            $test_name = 'Test User';
            $stmt->bind_param("sss", $test_name, $test_email, $test_password);
            
            if ($stmt->execute()) {
                $test_id = $conn->insert_id;
                echo '<div class="status success">✅ Database INSERT works correctly (test user ID: ' . $test_id . ')</div>';
                
                // Clean up test user
                $conn->query("DELETE FROM admin_users WHERE id = $test_id");
                echo '<p style="color: #666; font-size: 14px;">Test user deleted (cleanup complete)</p>';
            } else {
                echo '<div class="status error">❌ Database INSERT failed: ' . htmlspecialchars($stmt->error) . '</div>';
            }
            echo '</div>';

            // All Good!
            echo '<div class="status success" style="margin-top: 30px; text-align: center; font-size: 18px;">';
            echo '<strong>🎉 All checks passed! The system is ready to use.</strong><br><br>';
            echo '<a href="index.php" class="button">Go to Admin Dashboard</a>';
            echo '</div>';
        }

        $conn->close();
        ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb; text-align: center;">
            <p style="color: #666;">
                <strong>Quick Actions:</strong><br>
                <a href="create_admin_users_table.php" class="button">Setup Database</a>
                <a href="test_admin_user_creation.php" class="button">Run Full Test</a>
                <a href="index.php" class="button">Admin Dashboard</a>
            </p>
        </div>
    </div>
</body>
</html>
