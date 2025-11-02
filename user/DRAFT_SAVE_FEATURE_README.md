# Draft Save Feature - Setup & Usage Guide

## Overview
The Draft Save feature allows users to save their uploaded documents when applying for jobs. Once saved, these documents will automatically load for future job applications, eliminating the need to upload files one by one each time.

## Features Implemented

### 1. **Save Draft Button**
- Located in the application wizard Step 2 (Submit Requirements)
- Saves all currently uploaded documents to the database
- Documents are stored in user-specific directories

### 2. **Auto-Load Saved Drafts**
- When opening wizard Step 2 for a new application, previously saved documents automatically load
- Visual indicators show which documents are available from saved drafts
- Users can still upload new files to replace draft versions

### 3. **Seamless Application Submission**
- If user doesn't upload new files, the system automatically uses draft files
- Draft files are copied to the application folder during submission
- No change required in user workflow

## Setup Instructions

### Step 1: Create Database Table
Run the setup script to create the required database table:

```bash
# Navigate to the user directory
cd user/

# Run the table creation script in your browser
http://localhost/FinalResearch%20-%20Copy/user/create_draft_documents_table.php
```

This creates the `user_draft_documents` table with the following structure:
- `id` - Primary key
- `user_id` - Foreign key to applicants table
- `application_letter` - Filename of saved application letter
- `resume` - Filename of saved resume
- `tor` - Filename of saved transcript
- `diploma` - Filename of saved diploma
- `professional_license` - Filename of saved license
- `coe` - Filename of saved certificate of employment
- `seminars_trainings` - Comma-separated filenames of certificates
- `masteral_cert` - Filename of masteral certificate
- `created_at` - Timestamp of first save
- `updated_at` - Timestamp of last update

### Step 2: Verify File Structure
The system will automatically create these directories when needed:
```
user/
  uploads/
    drafts/
      {user_id}/
        draft_123_timestamp_filename.pdf
        draft_123_timestamp_resume.docx
        ...
```

### Step 3: Test the Feature
1. Log in as a user
2. Click "Apply Now" on any job
3. Go through wizard to Step 2
4. Upload documents (at least the required ones)
5. Click **"Save Draft"** button
6. See success notification confirming draft is saved
7. Go back to dashboard
8. Apply for a different job
9. When you reach Step 2, you'll see:
   - Blue notification saying "Previously saved documents loaded!"
   - Blue indicators showing which documents are available from draft
   - File inputs are no longer required (draft satisfies requirement)
10. You can either:
    - Upload new files to replace draft versions
    - Submit with draft files (no new uploads needed)

## User Workflow

### First Application (Creating Draft)
1. User applies to Job A
2. Uploads all required documents in Step 2
3. Clicks **"Save Draft"** 
4. Documents are saved to database and user's draft folder
5. Continues with application submission

### Subsequent Applications (Using Draft)
1. User applies to Job B
2. Opens Step 2
3. **Auto-loaded**: Previously saved documents appear with blue indicators
4. User has 3 options:
   - a) Submit directly using saved drafts (no uploads needed)
   - b) Upload new files to replace specific documents
   - c) Mix: Use some drafts, upload new versions for others
5. Clicks **"Submit Application"**
6. System uses draft files for documents not newly uploaded

### Updating Draft
1. User can update draft anytime by:
   - Starting a new application
   - Uploading new/updated documents in Step 2
   - Clicking **"Save Draft"** again
2. New files replace old ones in the draft

## Technical Details

### Files Created/Modified

#### New Files:
1. **create_draft_documents_table.php** - Database table setup
2. **save_draft.php** - API to save draft documents
3. **get_draft.php** - API to retrieve saved drafts
4. **DRAFT_SAVE_FEATURE_README.md** - This documentation

#### Modified Files:
1. **user.php**:
   - Added Save Draft button to Step 2 form actions
   - Added Save Draft button click handler
   - Added auto-load draft functionality in `setStep()` function
   - Modified form submission to use draft files when no new uploads
   - Added `copyDraftFile()` function to copy drafts to application folder

### API Endpoints

#### save_draft.php
- **Method**: POST
- **Input**: FormData with file uploads (same as application form)
- **Process**: 
  - Checks if user has existing draft
  - Uploads new files or keeps existing ones
  - Updates or inserts draft record
- **Response**: JSON
  ```json
  {
    "success": true,
    "message": "Draft saved successfully!",
    "draft": { /* document filenames */ }
  }
  ```

#### get_draft.php
- **Method**: GET
- **Process**: Fetches user's saved draft from database
- **Response**: JSON
  ```json
  {
    "success": true,
    "has_draft": true,
    "draft": {
      "application_letter": "draft_123_letter.pdf",
      "resume": "draft_123_resume.docx",
      ...
    }
  }
  ```

### Database Schema

```sql
CREATE TABLE user_draft_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    application_letter VARCHAR(255) DEFAULT NULL,
    resume VARCHAR(255) DEFAULT NULL,
    tor VARCHAR(255) DEFAULT NULL,
    diploma VARCHAR(255) DEFAULT NULL,
    professional_license VARCHAR(255) DEFAULT NULL,
    coe VARCHAR(255) DEFAULT NULL,
    seminars_trainings TEXT DEFAULT NULL,
    masteral_cert VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES applicants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_draft (user_id)
);
```

## Benefits

### For Users:
- ✅ **Save Time**: Upload documents once, reuse across multiple applications
- ✅ **Convenience**: No need to locate files repeatedly
- ✅ **Flexibility**: Can update draft or replace individual files anytime
- ✅ **Peace of Mind**: Documents are safely stored and ready to use

### For System:
- ✅ **Better UX**: Reduces friction in application process
- ✅ **Data Integrity**: Draft files kept separate from submitted applications
- ✅ **Storage Management**: User-specific folders for organization
- ✅ **Reusability**: Same files copied for each application submission

## Troubleshooting

### Draft Not Loading
- **Check**: Browser console for error messages
- **Verify**: Database table exists and has user_draft_documents
- **Confirm**: User has actually saved a draft previously
- **Clear**: Browser cache and try again

### Files Not Saving
- **Check**: File permissions on uploads/drafts/ directory (should be 0777)
- **Verify**: Database connection is working
- **Confirm**: User is logged in (session active)
- **Review**: PHP error logs for upload issues

### Draft Files Missing During Submission
- **Check**: Draft files exist in `uploads/drafts/{user_id}/` directory
- **Verify**: Filenames in database match actual files
- **Confirm**: `copyDraftFile()` function has proper permissions
- **Test**: Upload permissions on main uploads directory

## Future Enhancements

Potential improvements:
1. **Draft Preview**: Show thumbnails or file names before applying
2. **Draft Management**: Dedicated page to view/update saved drafts
3. **Multiple Draft Sets**: Save different document sets for different job types
4. **File Version History**: Keep track of document updates
5. **Cloud Sync**: Store drafts in cloud storage
6. **Expiration**: Auto-delete drafts after X days of inactivity

## Support

For issues or questions:
1. Check browser console for JavaScript errors
2. Review PHP error logs
3. Verify database table structure
4. Confirm file permissions
5. Test with different browsers

---

**Version**: 1.0  
**Last Updated**: 2025-01-20  
**Compatibility**: NCHire Application System v2.0+
