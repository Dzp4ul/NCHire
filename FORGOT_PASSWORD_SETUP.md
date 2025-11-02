# Forgot Password System - Quick Setup Guide

## 🚀 Quick Start (5 Minutes)

### Step 1: Run Database Migration
```
http://localhost/FinalResearch - Copy/database/migrations/add_password_change_to_applicants.php
```
✅ Adds `password_change_required` column to applicants table

### Step 2: Test the System

#### For Applicants:
1. Go to: `http://localhost/FinalResearch - Copy/public/index.php`
2. Click "Forgot Password?" link
3. Enter applicant email
4. Check email inbox for temporary password
5. Login with temporary password
6. Change password when prompted
7. Login with new password ✓

#### For Admin/Department Head/Secretary:
1. Go to: `http://localhost/FinalResearch - Copy/public/index.php`
2. Click "Forgot Password?" link
3. Enter admin email
4. Check email inbox for temporary password
5. Login with temporary password
6. Change password when prompted (admin page)
7. Login with new password ✓

---

## 📁 Files Created

### 1. Database Migration
- `database/migrations/add_password_change_to_applicants.php`

### 2. Email Helper
- `shared/helpers/send_temp_password_email.php`

### 3. User Change Password Page
- `user/change_password.php`

### 4. Documentation
- `docs/FORGOT_PASSWORD_SYSTEM.md` (Complete guide)
- `FORGOT_PASSWORD_SETUP.md` (This file)

---

## 📝 Files Modified

### 1. Process Forgot Password Backend
- `public/process_forgot_password.php` - Complete logic implementation

### 2. Forgot Password UI
- `public/forgot_password.php` - Professional standalone page

### 3. Login Handler
- `public/index.php` - Added password_change_required check for applicants

---

## ✨ How It Works

### User Flow:
```
Forgot Password? 
    ↓
Enter Email
    ↓
Email Sent with Temporary Password
    ↓
Login with Temporary Password
    ↓
Redirected to Change Password Page
    ↓
Enter New Password
    ↓
Logged Out & Redirected to Login
    ↓
Login with New Password ✓
```

### Technical Flow:
```
1. User submits email in forgot_password.php
2. process_forgot_password.php checks admin_users and applicants tables
3. Generates 10-character temporary password
4. Updates database: password + password_change_required = 1
5. Sends professional email with temporary password
6. User logs in → system detects password_change_required = 1
7. Redirects to change_password.php (admin or user)
8. User changes password → password_change_required = 0
9. User can now login normally
```

---

## 🔐 Security Features

- ✅ Temporary passwords (10 characters, random)
- ✅ Forced password change on first login
- ✅ Admin passwords hashed (bcrypt)
- ✅ Activity logging for admin users
- ✅ Session management
- ✅ Email validation
- ✅ Password confirmation

---

## 🎨 User Experience

### Forgot Password Page
- Professional design matching site theme
- Background image with overlay
- College logo
- Font Awesome icons
- Success/error messages
- Clear instructions

### Change Password Page
- Secure password entry
- Password visibility toggle
- Requirements display
- Validation messages
- Auto-logout after change

### Email Template
- Professional HTML design
- Embedded college logo
- Gradient headers
- Color-coded sections
- Responsive layout
- Clear call-to-action button

---

## 🛠️ Troubleshooting

### Email Not Arriving?
- Check spam/junk folder
- Verify SMTP settings in `send_temp_password_email.php`
- Ensure internet connection active

### Column Error?
- Run migration: `add_password_change_to_applicants.php`
- Refresh database connection

### Stuck in Password Change Loop?
- Clear browser cookies
- Check database: `password_change_required` should be 0 after change
- Verify session handling

---

## 📊 Database Changes

### Before:
```sql
applicants table:
- id
- first_name
- last_name
- applicant_email
- applicant_password
- ...
```

### After:
```sql
applicants table:
- id
- first_name
- last_name
- applicant_email
- applicant_password
- password_change_required  ← NEW (TINYINT, DEFAULT 0)
- ...
```

---

## 🎯 Who Can Use This?

### All User Types Supported:
- ✅ Admins
- ✅ Department Heads
- ✅ Secretaries
- ✅ HR Managers
- ✅ Recruiters
- ✅ Applicants

### Same Process for Everyone:
1. Click "Forgot Password?"
2. Enter email
3. Receive temporary password
4. Login and change password
5. Done!

---

## 💡 Tips

### For Testing:
- Use real email addresses
- Check spam folder
- Copy temporary password carefully
- Clear cache if issues occur

### For Production:
- Update SMTP credentials
- Test with all user types
- Monitor email delivery
- Check activity logs

---

## 📞 Support

**Need Help?**
- Read full documentation: `docs/FORGOT_PASSWORD_SYSTEM.md`
- Check troubleshooting section above
- Review code comments in files
- Contact system administrator

---

## ✅ Checklist

Before going live, ensure:
- [ ] Database migration run successfully
- [ ] Tested with applicant account
- [ ] Tested with admin account
- [ ] Email sending works
- [ ] Password change works
- [ ] New password login works
- [ ] UI looks professional
- [ ] Error messages display correctly
- [ ] Activity logged (for admins)
- [ ] Documentation reviewed

---

## 🎉 Success!

You now have a complete, professional forgot password system that:
- Works for all user types
- Sends beautiful emails
- Forces secure password changes
- Follows best practices
- Matches your site design

**Ready to use!** 🚀
