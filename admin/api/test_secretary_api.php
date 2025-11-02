<?php
/**
 * Test Secretary Actions API
 * Visit this file to test the API endpoint
 */

session_start();

// Simulate admin login for testing
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_role'] = 'Secretary';
$_SESSION['admin_id'] = 1;
$_SESSION['admin_name'] = 'Test Secretary';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Secretary API</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 50px auto; padding: 20px; }
        .result { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Secretary API Test</h1>
    
    <h2>Session Info:</h2>
    <div class="result">
        <strong>Logged In:</strong> <?php echo $_SESSION['admin_logged_in'] ? 'Yes' : 'No'; ?><br>
        <strong>Role:</strong> <?php echo $_SESSION['admin_role'] ?? 'Not set'; ?><br>
        <strong>Admin ID:</strong> <?php echo $_SESSION['admin_id'] ?? 'Not set'; ?><br>
        <strong>Name:</strong> <?php echo $_SESSION['admin_name'] ?? 'Not set'; ?>
    </div>
    
    <h2>Test Transfer to Department Head:</h2>
    <p>Application ID: <input type="number" id="appId" value="1"></p>
    <p>Notes: <input type="text" id="notes" value="Documents reviewed and approved"></p>
    <button onclick="testTransfer()">Test Transfer</button>
    
    <div id="result"></div>
    
    <h2>Check Application:</h2>
    <button onclick="checkApplication()">Check Application #1</button>
    <div id="checkResult"></div>
    
    <script>
        async function testTransfer() {
            const appId = document.getElementById('appId').value;
            const notes = document.getElementById('notes').value;
            
            const formData = new FormData();
            formData.append('action', 'transfer_to_dept_head');
            formData.append('application_id', appId);
            formData.append('notes', notes);
            
            try {
                const response = await fetch('secretary_actions.php', {
                    method: 'POST',
                    body: formData
                });
                
                const text = await response.text();
                console.log('Raw response:', text);
                
                try {
                    const data = JSON.parse(text);
                    document.getElementById('result').innerHTML = 
                        `<div class="result ${data.success ? 'success' : 'error'}">
                            <strong>Status:</strong> ${response.status}<br>
                            <strong>Success:</strong> ${data.success}<br>
                            <strong>Message:</strong> ${data.message}
                        </div>`;
                } catch (e) {
                    document.getElementById('result').innerHTML = 
                        `<div class="result error">
                            <strong>JSON Parse Error:</strong><br>
                            <pre>${text}</pre>
                        </div>`;
                }
            } catch (error) {
                document.getElementById('result').innerHTML = 
                    `<div class="result error">
                        <strong>Network Error:</strong> ${error.message}
                    </div>`;
            }
        }
        
        async function checkApplication() {
            try {
                const response = await fetch('../../admin/view_applicant.php?id=1');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('checkResult').innerHTML = 
                        `<div class="result success">
                            <strong>Application Found:</strong><br>
                            Name: ${data.applicant.full_name}<br>
                            Status: ${data.applicant.status}<br>
                            Workflow Stage: ${data.applicant.workflow_stage || 'Not set'}
                        </div>`;
                } else {
                    document.getElementById('checkResult').innerHTML = 
                        `<div class="result error">
                            <strong>Error:</strong> ${data.error}
                        </div>`;
                }
            } catch (error) {
                document.getElementById('checkResult').innerHTML = 
                    `<div class="result error">
                        <strong>Error:</strong> ${error.message}
                    </div>`;
            }
        }
    </script>
</body>
</html>
