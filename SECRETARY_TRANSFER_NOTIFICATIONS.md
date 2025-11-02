# Secretary Transfer Notifications Feature

## Overview
When a secretary transfers an application to a department head, the system now sends notifications to both the applicant and the department head.

## Notifications Implemented

### 1. Applicant Notifications

#### In-App Notification
- **Title**: "Application Transferred to Department Head"
- **Message**: "Your application for [Position] has been reviewed and forwarded to the department head for evaluation. You will be notified of the next steps."
- **Type**: Info (blue)
- Stored in `notifications` table
- Visible in user dashboard notification dropdown

#### Email Notification
- **Subject**: "Application Transferred to Department Head"
- **Content**: Professional HTML email with:
  - NCHire branding
  - Position details
  - Information about next steps
  - Link to view application in user dashboard
- Uses existing email template system

### 2. Department Head Notifications

#### In-App Notification
- **Title**: "New Application Transferred"
- **Message**: "Application from [Applicant Name] for [Position] has been transferred to you by [Secretary Name] for review. [Notes if any]"
- **Type**: Info (blue)
- Stored in `admin_notifications` table
- Targeted to specific department head (not all admins)
- Visible in admin panel notification dropdown

#### Email Notification
- **Subject**: "New Application Transferred - NCHire"
- **Content**: Professional HTML email with:
  - Applicant name and position
  - Secretary name who transferred
  - Secretary notes (if any)
  - Link to admin panel for review
- Uses existing email template system

## Technical Implementation

### File Modified
- `admin/api/secretary_actions.php` - `handleTransferToDeptHead()` function

### Workflow
1. Secretary transfers application → Database updated with `workflow_stage = 'department_head_review'`
2. In-app notification created for applicant
3. Email sent to applicant
4. Department head identified by `assigned_to_department` field
5. In-app notification created for department head
6. Email sent to department head

### Database Queries
- Applicant notification: `INSERT INTO notifications`
- Department head lookup: `SELECT FROM admin_users WHERE role = 'Department Head' AND department = ?`
- Admin notification: `INSERT INTO admin_notifications` (with specific `admin_id`)

### Error Handling
- All notification operations are wrapped in try-catch blocks
- Failed notifications are logged but don't stop the transfer process
- Error messages logged to PHP error log for debugging

## Benefits

✅ **Applicants**: Immediate awareness that their application is progressing  
✅ **Department Heads**: Real-time notification of new applications to review  
✅ **Transparency**: Clear audit trail of when applications are transferred  
✅ **Professional Communication**: Branded emails maintain institutional credibility  
✅ **Targeted Notifications**: Department heads only see applications for their department  

## Testing

### To Test:
1. Log in as Secretary
2. View an application in "Document Review"
3. Click "Transfer to Department Head"
4. Add optional notes
5. Submit

### Expected Results:
1. ✅ Applicant receives in-app notification
2. ✅ Applicant receives email notification
3. ✅ Department head receives in-app notification
4. ✅ Department head receives email notification
5. ✅ Console logs confirm all notifications sent
6. ✅ Application moves to "Department Head Review" stage

## Configuration Requirements

### Prerequisites:
- PHPMailer configured (already done)
- Email credentials set in `email_helper.php`
- `notifications` table exists
- `admin_notifications` table exists
- `admin_users` table has department heads with assigned departments
- `job_applicants` table has `assigned_to_department` populated

### Email Configuration:
- SMTP: smtp.gmail.com
- Port: 465 (SSL)
- Sender: no-reply@nchire.local (NCHire - Norzagaray College)

## Troubleshooting

### No Email Sent
- Check PHP error log for detailed error messages
- Verify PHPMailer credentials in `email_helper.php`
- Ensure recipient email addresses are valid

### No In-App Notification
- Check if `notifications` or `admin_notifications` table exists
- Verify user/admin email matches session email
- Check browser console for errors

### Department Head Not Notified
- Ensure department head has matching `department` in `admin_users` table
- Verify department head status is 'Active'
- Check `assigned_to_department` is populated in application

## Future Enhancements
- Batch notifications for multiple applications
- Custom email templates per department
- SMS notifications (if implemented)
- Push notifications (if implemented)
