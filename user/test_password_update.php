<?php
session_start();

// Quick diagnostic test for password update
?>
<!DOCTYPE html>
<html>
<head>
    <title>Password Update Test</title>
</head>
<body>
    <h2>Password Update Diagnostic Test</h2>
    
    <h3>1. Session Check</h3>
    <pre><?php print_r($_SESSION); ?></pre>
    
    <h3>2. Button Test</h3>
    <button type="button" id="updatePasswordBtn">Test Update Button</button>
    <button type="button" id="cancelPasswordBtn">Test Cancel Button</button>
    
    <h3>3. Password Fields Test</h3>
    <input type="password" id="currentPassword" placeholder="Current">
    <input type="password" id="newPassword" placeholder="New">
    <input type="password" id="confirmPassword" placeholder="Confirm">
    
    <h3>4. Console Output</h3>
    <p>Check browser console (F12) for messages</p>
    
    <div id="output"></div>

    <script>
    function showToast(message, type) {
        const output = document.getElementById('output');
        output.innerHTML += `<p style="color: ${type === 'error' ? 'red' : 'green'}">${message}</p>`;
        console.log(`Toast [${type}]: ${message}`);
    }

    console.log('=== TEST SCRIPT START ===');
    console.log('DOM Loaded');
    
    const updatePasswordBtn = document.getElementById('updatePasswordBtn');
    const cancelPasswordBtn = document.getElementById('cancelPasswordBtn');
    const currentPassword = document.getElementById('currentPassword');
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    
    console.log('Elements found:', {
        updatePasswordBtn,
        cancelPasswordBtn,
        currentPassword,
        newPassword,
        confirmPassword
    });
    
    if (updatePasswordBtn) {
        console.log('Adding click listener to update button');
        updatePasswordBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('UPDATE BUTTON CLICKED!');
            showToast('Button clicked successfully!', 'success');
            
            const currentPwd = currentPassword.value.trim();
            const newPwd = newPassword.value.trim();
            const confirmPwd = confirmPassword.value.trim();
            
            console.log('Field values:', {
                current: currentPwd.length + ' chars',
                new: newPwd.length + ' chars',
                confirm: confirmPwd.length + ' chars'
            });
            
            if (!currentPwd || !newPwd || !confirmPwd) {
                showToast('Please fill in all fields', 'error');
                return;
            }
            
            if (newPwd.length < 8) {
                showToast('Password must be 8+ characters', 'error');
                return;
            }
            
            if (newPwd !== confirmPwd) {
                showToast('Passwords do not match', 'error');
                return;
            }
            
            showToast('All validations passed!', 'success');
            console.log('Sending test request to save_profile_data.php');
            
            const formData = new FormData();
            formData.append('updatePassword', '1');
            formData.append('current_password', currentPwd);
            formData.append('new_password', newPwd);
            
            updatePasswordBtn.disabled = true;
            updatePasswordBtn.textContent = 'Testing...';
            
            fetch('save_profile_data.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response OK:', response.ok);
                return response.text();
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed data:', data);
                    showToast(data.message || 'Response received', data.success ? 'success' : 'error');
                } catch (e) {
                    console.error('JSON parse error:', e);
                    showToast('Response: ' + text.substring(0, 100), 'error');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showToast('Error: ' + error.message, 'error');
            })
            .finally(() => {
                updatePasswordBtn.disabled = false;
                updatePasswordBtn.textContent = 'Test Update Button';
            });
        });
        console.log('Click listener added successfully');
    } else {
        console.error('updatePasswordBtn not found!');
    }
    
    console.log('=== TEST SCRIPT END ===');
    </script>
</body>
</html>
