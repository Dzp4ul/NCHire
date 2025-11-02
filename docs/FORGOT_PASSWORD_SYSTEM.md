# Forgot Password System with Temporary Passwords

## Overview
Complete "forgot password" system that sends temporary passwords via email and forces users to create new passwords on first login. Works for all user types: Admin, Department Head, Secretary, HR Manager, Recruiter, and Applicants.

## Key Features
- ✅ Sends temporary password via email
- ✅ Forces password change on first login
- ✅ Works for admin users (Department Head, Secretary, etc.)
- ✅ Works for applicants
- ✅ Professional email templates
- ✅ Secure password handling (hashed for admins, plain for applicants)
- ✅ Activity logging for admin users
- ✅ Beautiful standalone forgot password page

---

## Database Schema

### Added Column: `password_change_required`

**Admin Users Table (`admin_users`):**
- Already has `password_change_required TINYINT(1)` column
- Used when creating new admin users or resetting passwords

**Applicants Table (`applicants`):**
- **NEW:** Added `password_change_required TINYINT(1) DEFAULT 0`
- Enables temporary password functionality for applicants

**Migration File:** `database/migrations/add_password_change_to_applicants.php`

---

## User Flow

### 1. Forgot Password Request
1. User clicks "Forgot Password?" link on login page
2. Redirected to `public/forgot_password.php`
3. User enters email address
4. System checks both `admin_users` and `applicants` tables
5. If found, generates 10-character random temporary password
6. Updates database:
   - Sets new password
   - Sets `password_change_required = 1`
7. Sends professional email with temporary password
8. Logs activity (for admin users only)

### 2. Login with Temporary Password
1. User enters email and temporary password
2. System authenticates user
3. Detects `password_change_required = 1`
4. Redirects to appropriate change password page:
   - **Admin Users:** `admin/change_password.php`
   - **Applicants:** `user/change_password.php`

### 3. Change Password
1. User must enter new password (min. 6 characters)
2. Must confirm new password
3. System updates database:
   - Stores new password
   - Sets `password_change_required = 0`
4. User logged out automatically
5. Redirected to login page with success message
6. Can now log in with new password

---

## Files Created/Modified

### New Files

#### 1. `database/migrations/add_password_change_to_applicants.php`
- Adds `password_change_required` column to applicants table
- Displays current table structure
- Idempotent (safe to run multiple times)

#### 2. `shared/helpers/send_temp_password_email.php`
- Reusable email function for all user types
- Professional HTML email template
- Embedded college logo
- Color-coded alert boxes
- Responsive design

#### 3. `user/change_password.php`
- Password change page for applicants
- Matches admin change_password.php design
- Password visibility toggle
- Input validation
- Auto-logout after password change

### Modified Files

#### 1. `public/process_forgot_password.php`
**Before:** Empty duplicate of forgot_password.php  
**After:** Complete backend logic
- Checks both admin_users and applicants tables
- Generates temporary password
- Updates database with password_change_required flag
- Sends email notification
- Logs activity for admin users

#### 2. `public/forgot_password.php`
**Before:** Basic form  
**After:** Professional standalone page
- Matches site design (background image, logo)
- Font Awesome icons
- Success/error message display
- Clear instructions
- Back to login link

#### 3. `public/index.php` (Login Handler)
**Changes:**
- Added `password_change_required` to applicant session
- Check password_change_required for applicants after successful login
- Redirect to `user/change_password.php` if flag is set

#### 4. `admin/change_password.php`
**No changes needed** - Already handles password_change_required for admin users

---

## Email Template

### Temporary Password Email Features
- **Subject:** "Password Reset - Temporary Password"
- **From:** NCHire - Norzagaray College (no-reply@nchire.local)
- **Embedded Logo:** Norzagaray College logo
- **Content Sections:**
  - Alert box with user type (Admin, Department Head, etc.)
  - Credentials box showing email and temporary password
  - Security warning box
  - Step-by-step instructions
  - Login button with proper URL
- **Responsive:** Works on mobile and desktop
- **Professional:** Gradient headers, color-coded sections

---

## Security Features

### Password Handling
- **Admin Users:** Passwords hashed with `password_hash()` (PHP bcrypt)
- **Applicants:** Plain text (as per current system architecture)
- **Temporary Password:** 10 characters (letters, numbers, special chars)

### Session Management
- `password_change_required` stored in session
- Checked on every page load for protected pages
- Cleared after successful password change

### Activity Logging
- All password resets logged in `admin_activity` table (for admin users)
- Includes timestamp and user information

### Validation
- Email required and validated
- New password minimum 6 characters
- Password confirmation must match
- User must exist in database

---

## Testing Instructions

### 1. Run Database Migration
```
Navigate to: http://localhost/FinalResearch - Copy/database/migrations/add_password_change_to_applicants.php
```
- Should see green success message
- Verify column was added

### 2. Test Forgot Password (Applicant)
1. Go to login page
2. Click "Forgot Password?" link
3. Enter applicant email address
4. Submit form
5. Check email for temporary password
6. Use temporary password to log in
7. Should be redirected to change password page
8. Enter new password (twice)
9. Should be logged out and redirected to login
10. Log in with new password - should work!

### 3. Test Forgot Password (Admin/Department Head/Secretary)
1. Go to login page
2. Click "Forgot Password?" link
3. Enter admin email address
4. Submit form
5. Check email for temporary password
6. Use temporary password to log in
7. Should be redirected to admin change password page
8. Enter new password (twice)
9. Should be logged out and redirected to login
10. Log in with new password - should work!

### 4. Verify Email Sending
- Check that email arrives in inbox (may be in spam folder)
- Verify email has professional formatting
- Verify temporary password is visible
- Verify login button works

---

## Troubleshooting

### Email Not Sending
**Problem:** User doesn't receive email  
**Solutions:**
- Check spam/junk folder
- Verify SMTP credentials in `send_temp_password_email.php`
- Check server error logs
- Test with different email provider

### Password Change Not Working
**Problem:** User can't change password  
**Solutions:**
- Verify `password_change_required` column exists in database
- Check session variables in browser dev tools
- Verify user_id is set in session
- Check database connection

### Redirect Loop
**Problem:** User keeps getting redirected to change password  
**Solutions:**
- Verify `password_change_required` is set to 0 after password change
- Check database update query
- Clear browser cookies/session
- Check session handling in login logic

### Column Doesn't Exist Error
**Problem:** SQL error about missing column  
**Solutions:**
- Run migration file: `add_password_change_to_applicants.php`
- Verify column name matches code
- Check table name is correct
- Refresh database connection

---

## Configuration

### Email Settings
**File:** `shared/helpers/send_temp_password_email.php`
```php
$mail->Host       = 'smtp.gmail.com';
$mail->Username   = 'manansalajohnpaul120@gmail.com';
$mail->Password   = 'dcuv npdb mmnz lyfa';
$mail->Port       = 465;
```

### Database Settings
**All Files Use:**
```php
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";
```

### Login URLs
- **Admin Users:** `http://localhost/FinalResearch%20-%20Copy/admin`
- **Applicants:** `http://localhost/FinalResearch%20-%20Copy/index.php`

---

## Code Examples

### Generate Temporary Password
```php
$temporaryPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%'), 0, 10);
```

### Update Admin User
```php
$hashed_password = password_hash($temporaryPassword, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE admin_users SET password = ?, password_change_required = 1 WHERE id = ?");
$stmt->bind_param("si", $hashed_password, $user_id);
```

### Update Applicant
```php
$stmt = $conn->prepare("UPDATE applicants SET applicant_password = ?, password_change_required = 1 WHERE id = ?");
$stmt->bind_param("si", $temporaryPassword, $user_id);
```

### Check Password Change Required (Login)
```php
if (isset($row['password_change_required']) && $row['password_change_required'] == 1) {
    $_SESSION['password_change_required'] = 1;
    header("Location: change_password.php");
    exit();
}
```

---

## Benefits

### For Users
- ✅ Easy password recovery process
- ✅ Professional email notifications
- ✅ Clear instructions at every step
- ✅ Secure temporary passwords
- ✅ Forced password change for security

### For Administrators
- ✅ Complete activity logging
- ✅ Consistent experience across user types
- ✅ Professional branding in emails
- ✅ Secure password handling
- ✅ Easy to maintain and debug

### For System
- ✅ Unified codebase for all user types
- ✅ Reusable email function
- ✅ Clean separation of concerns
- ✅ Follows existing patterns
- ✅ Well-documented

---

## Future Enhancements

### Potential Improvements
1. **Password Expiration:** Temporary passwords expire after X hours
2. **Rate Limiting:** Limit forgot password requests per IP/email
3. **Password Complexity:** Enforce stronger password requirements
4. **2FA Integration:** Add two-factor authentication option
5. **Password History:** Prevent reuse of recent passwords
6. **Email Templates:** More customizable email designs
7. **SMS Option:** Send temporary password via SMS
8. **Security Questions:** Add security questions as alternative

---

## Support

### Common Issues

**Q: Can I use the modal instead of standalone page?**  
A: Yes! The modal still exists in `index.php` but the link redirects to standalone page for better UX.

**Q: Why are applicant passwords not hashed?**  
A: Current system architecture uses plain text for applicants. Future enhancement can add password hashing.

**Q: Can users reset password multiple times?**  
A: Yes, no rate limiting currently. Each reset generates new temporary password.

**Q: What if email never arrives?**  
A: Check spam folder, verify SMTP settings, or contact system administrator.

---

## Version History

**Version 1.0** (Current)
- Initial implementation
- Temporary password generation
- Email notifications
- Forced password change
- Supports all user types
- Professional UI/UX
- Complete documentation

---

## Contact

For questions or issues with the forgot password system:
- Check troubleshooting section above
- Review code comments in files
- Test with debug mode enabled
- Contact system administrator

**Documentation Last Updated:** 2025
**System Version:** NCHire v1.0
