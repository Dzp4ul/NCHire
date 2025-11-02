# Password Update Feature - Testing Guide

## Features Implemented

### 1. **Show/Hide Password Buttons** ✅
- All three password fields (Current, New, Confirm) now have eye icons
- Click the eye icon to toggle password visibility
- Icon changes from eye (ri-eye-line) to eye-off (ri-eye-off-line)

### 2. **Password Update Functionality** ✅
- Complete validation system
- Backend password verification
- Success/error notifications
- Comprehensive debug logging

## Testing Instructions

### Step 1: Access Account Settings
1. Log in to your applicant account
2. Go to **Profile** (user_profile.php)
3. Click the **"Account Settings"** tab

### Step 2: Test Show/Hide Password
1. Click on any password field to focus it
2. Type some characters
3. Click the **eye icon** on the right side of the field
4. Password should become visible (eye icon changes to eye-off)
5. Click again to hide it

### Step 3: Test Password Update
1. Fill in all three fields:
   - **Current Password**: Your actual current password
   - **New Password**: A new password (min. 8 characters)
   - **Confirm New Password**: Same as new password
2. Click **"Update Password"** button

### Expected Behaviors:

#### Validation Tests:
- ❌ Empty fields → "Please fill in all password fields"
- ❌ New password < 8 chars → "New password must be at least 8 characters long"
- ❌ Passwords don't match → "New passwords do not match"
- ❌ New = Current → "New password must be different from current password"
- ❌ Wrong current password → "Current password is incorrect"
- ✅ All valid → "Password updated successfully"

#### Visual Feedback:
- Button shows loading spinner during update
- Button text changes to "Updating..."
- Button re-enables after completion
- Toast notification appears (green for success, red for error)
- All password fields are cleared on success

## Debugging

### Browser Console Logs:
Open Developer Tools (F12) → Console tab. You should see:
```
Password update script loaded
Update button found: <button>
Password fields: {currentPassword, newPassword, confirmPassword}
Update password button clicked  (when clicked)
Password lengths: {current: X, new: Y, confirm: Z}
All validations passed, sending request...
Sending request to save_profile_data.php
Response received: Response {...}
Response data: {success: true/false, message: "..."}
Request completed
```

### Server-Side Logs:
Check PHP error logs (usually in `xampp/apache/logs/error.log`):
```
=== PASSWORD UPDATE REQUEST START ===
User ID: X
Current password length: Y
New password length: Z
Fetching current password from database...
Stored password retrieved (length: W)
Current password verified successfully
New password is different from current
Updating password in database...
SUCCESS: Password updated successfully
=== PASSWORD UPDATE REQUEST END ===
```

## Troubleshooting

### Issue: Button click doesn't do anything
**Check:**
1. Open browser console for errors
2. Verify the button ID is `updatePasswordBtn`
3. Check if script is loaded: Look for "Password update script loaded" in console

### Issue: "Current password is incorrect" but it's correct
**Check:**
1. Check server logs for password comparison
2. Verify you're using the actual stored password
3. Ensure no extra spaces in the password field

### Issue: Password fields auto-fill
**Solution:**
- Click on the field to remove readonly attribute
- Browser may still try to autofill - just clear and retype

## Files Modified

1. **user/user_profile.php**
   - Added show/hide password toggles (lines 857-880)
   - Added togglePasswordVisibility() function (lines 2260-2276)
   - Enhanced password update handler with logging (lines 2278-2411)

2. **user/save_profile_data.php**
   - Added password update backend handler (lines 343-420)
   - Complete validation and error handling
   - Comprehensive debug logging

## Security Features

✅ Session-based user authentication
✅ Current password verification
✅ Prepared statements (SQL injection protection)
✅ Password length validation (minimum 8 characters)
✅ Duplicate password prevention
✅ Proper error messages without exposing sensitive data
✅ Password change flag reset on successful update
