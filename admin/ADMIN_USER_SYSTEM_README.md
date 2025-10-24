# Dynamic Admin User Management System

## Overview
Successfully replaced the hardcoded admin users with a complete database-driven user management system.

## Features Implemented

### 1. Database Structure
**Table:** `admin_users`

**Columns:**
- `id` - Primary key (auto-increment)
- `full_name` - User's full name
- `email` - Email address (unique)
- `password` - Hashed password
- `role` - Admin role (Admin, HR Manager, Department Head, Recruiter)
- `department` - Department assignment (Computer Science, Education, Hospitality Management)
- `profile_picture` - Optional profile picture filename
- `phone` - Optional phone number
- `status` - Active, Inactive, or Suspended
- `last_login` - Last login timestamp
- `created_at` - Account creation timestamp
- `updated_at` - Last update timestamp

### 2. User Profile Creation
When creating a new admin user, you can now set:
- **Personal Information:**
  - Full Name (required)
  - Email Address (required, must be unique)
  - Password (required, minimum 6 characters)
  - Phone Number (optional)

- **Role & Department:**
  - Role (required): Admin, HR Manager, Department Head, Recruiter
  - Department (required): Computer Science, Education, Hospitality Management

### 3. API Endpoints
**Location:** `admin/api/users.php`

**Methods:**
- `GET` - Fetch all admin users from database
- `POST` - Create new admin user with profile data
- `PUT` - Update existing admin user
- `DELETE` - Delete admin user (prevents deletion of last admin)

### 4. Security Features
- ✅ Passwords are hashed using PHP's `password_hash()`
- ✅ Email uniqueness validation
- ✅ Prevents deleting the last admin user
- ✅ Input validation and sanitization
- ✅ Prepared statements to prevent SQL injection

## Setup Instructions

### Step 1: Create Database Table
1. Navigate to: `http://localhost/FinalResearch%20-%20Copy/admin/create_admin_users_table.php`
2. This will:
   - Create the `admin_users` table
   - Add a default admin user

**Default Admin Credentials:**
- Email: `admin@norzagaraycollege.edu.ph`
- Password: `admin123`

### Step 2: Access Admin Panel
1. Go to: `http://localhost/FinalResearch%20-%20Copy/admin/index.php`
2. Navigate to "Users" section in the sidebar

### Step 3: Create New Admin Users
1. Click "Create User" button
2. Fill in the form:
   - Personal Information (name, email, password, phone)
   - Role & Department selection
3. Click "Create User"
4. User will be saved to database and appear in the table

## Usage

### Creating Users
- Click "Create User" button
- Fill required fields (marked with *)
- Select department from: Computer Science, Education, or Hospitality Management
- Password must be at least 6 characters
- System validates email uniqueness

### Viewing Users
- All users are displayed in a table with:
  - Profile picture (if uploaded) or initials
  - Name and email
  - Role badge with color coding
  - Department assignment
  - Status (Active/Inactive/Suspended)
  - Last login time

### Deleting Users
- Click delete icon (trash) next to user
- Confirm deletion
- System prevents deletion of last admin user

### User Status Colors
- **Active**: Green badge
- **Inactive**: Gray badge  
- **Suspended**: Red badge

### Role Color Coding
- **Admin**: Red badge
- **HR Manager**: Purple badge
- **Department Head**: Blue badge
- **Recruiter**: Gray badge

## Files Modified

1. **admin/create_admin_users_table.php** (NEW)
   - Database table creation script
   - Adds default admin user

2. **admin/api/users.php** (UPDATED)
   - Replaced JSON file storage with MySQL database
   - Added password hashing
   - Enhanced validation and security

3. **admin/index.php** (UPDATED)
   - Enhanced create user modal
   - Added password field
   - Updated departments to: Computer Science, Education, Hospitality Management
   - Added phone number field
   - Improved modal UI with sections

4. **admin/admin.js** (UPDATED)
   - Removed hardcoded users array
   - Updated `loadUsers()` to fetch from database API
   - Updated `createUser()` to send password and profile data
   - Updated `deleteUser()` to use database API
   - Added loading states and error handling

## Database Schema

```sql
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    department VARCHAR(100) NOT NULL,
    profile_picture VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    status ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_department (department),
    INDEX idx_status (status)
);
```

## Benefits

✅ **No More Hardcoded Data** - All users stored in database
✅ **Profile Management** - Full profile information for each admin
✅ **Secure Authentication** - Password hashing and validation
✅ **Department-Based** - Users assigned to specific departments
✅ **Role-Based Access** - Different admin roles with color coding
✅ **Real-Time Updates** - Changes reflect immediately
✅ **Professional UI** - Modern, responsive interface
✅ **Error Handling** - Comprehensive error messages

## Notes

- All passwords are hashed using PHP's `password_hash()` function
- Email addresses must be unique across all admin users
- The system prevents deletion of the last admin user to maintain system access
- Profile pictures can be uploaded (stored in `uploads/profile_pictures/`)
- Last login time is tracked automatically
- Status can be changed between Active, Inactive, and Suspended

## Future Enhancements (Optional)

- Admin login system using the credentials
- Password reset functionality
- Profile picture upload in the modal
- Edit user functionality (currently shows alert)
- User activity logging
- Permission-based access control
- Email notifications for new user creation
- Bulk user import/export

## Support

If you encounter any issues:
1. Check that XAMPP MySQL is running
2. Verify database connection settings in the files
3. Ensure the `admin_users` table exists
4. Check browser console for JavaScript errors
5. Check PHP error logs for backend issues
