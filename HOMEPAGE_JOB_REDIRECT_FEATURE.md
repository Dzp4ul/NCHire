# Homepage Job Post to Sign In to View Details Feature

## Overview
Successfully implemented a feature where clicking on a job post in the homepage "Browse Opportunities" section will prompt the user to sign in, and after successful login, automatically redirect them to view the details of that specific job.

## User Flow

1. **User visits homepage** (not logged in)
2. **User clicks on a job post** in the Browse Opportunities carousel
3. **Sign-in modal appears**
4. **User enters credentials and signs in**
5. **System automatically redirects** to user dashboard
6. **Job details page automatically opens** for the selected job

## Technical Implementation

### 1. Frontend - Homepage (public/index.php)

#### Modified `showJobDetails()` Function (Lines 1438-1462)
```javascript
function showJobDetails(jobId) {
    console.log('Job clicked, ID:', jobId);
    
    // Store job ID in localStorage for after login
    localStorage.setItem('pendingJobView', jobId);
    
    // Send job ID to server session via AJAX
    fetch('store_job_view.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ job_id: jobId })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Job ID stored in session:', data);
    })
    .catch(error => {
        console.error('Error storing job ID:', error);
    });
    
    // Open sign in modal
    document.getElementById('openSignIn')?.click();
}
```

**What it does:**
- Stores job ID in localStorage (client-side backup)
- Sends job ID to server session via AJAX
- Opens the sign-in modal

### 2. Backend - Session Storage (public/store_job_view.php)

**New file created** to store job ID in PHP session:

```php
<?php
session_start();
header('Content-Type: application/json');

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (isset($data['job_id']) && is_numeric($data['job_id'])) {
        $_SESSION['view_job_id'] = intval($data['job_id']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Job ID stored in session',
            'job_id' => $_SESSION['view_job_id']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid job ID'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
```

### 3. Backend - Login Handler (public/index.php)

#### Modified Login Success Handler (Lines 98-103)
```php
// Check if there's a job_id to view from homepage
if (isset($_SESSION['view_job_id'])) {
    $job_id = $_SESSION['view_job_id'];
    unset($_SESSION['view_job_id']);
    $redirect_url = '../user/user.php?view_job=' . $job_id;
}
```

**What it does:**
- After successful login, checks if `view_job_id` exists in session
- If yes, constructs redirect URL with `view_job` parameter
- Clears the session variable to prevent re-redirecting

### 4. User Dashboard - Auto-Show Job Details (user/user.php)

#### Added Script (Lines 9187-9212)
```javascript
document.addEventListener('DOMContentLoaded', function() {
    // Check URL for view_job parameter
    const urlParams = new URLSearchParams(window.location.search);
    const viewJobId = urlParams.get('view_job');
    
    if (viewJobId) {
        console.log('🔗 Redirected from homepage to view job ID:', viewJobId);
        
        // Wait a moment for the page to fully load, then show job details
        setTimeout(function() {
            // Make sure showJobDetails function exists
            if (typeof showJobDetails === 'function') {
                console.log('✅ Calling showJobDetails for job ID:', viewJobId);
                showJobDetails(parseInt(viewJobId));
                
                // Clean up URL (remove the parameter)
                window.history.replaceState({}, document.title, window.location.pathname);
            } else {
                console.error('❌ showJobDetails function not found!');
            }
        }, 500); // Small delay to ensure all scripts are loaded
    }
});
```

**What it does:**
- Checks URL parameters for `view_job`
- If found, waits 500ms for page to fully load
- Calls `showJobDetails()` function with the job ID
- Cleans up URL to remove the parameter

## Complete Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│ 1. User clicks job post in homepage Browse Opportunities   │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. showJobDetails(jobId) function executes                 │
│    - Stores jobId in localStorage                          │
│    - Sends jobId to store_job_view.php via AJAX           │
│    - Opens sign-in modal                                    │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. store_job_view.php saves jobId in $_SESSION            │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. User enters credentials and submits login form          │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 5. Login handler in public/index.php processes login       │
│    - Validates credentials                                  │
│    - Checks for $_SESSION['view_job_id']                   │
│    - Redirects to: ../user/user.php?view_job={jobId}      │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 6. User dashboard loads (user/user.php)                    │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 7. DOMContentLoaded script detects view_job parameter      │
│    - Extracts jobId from URL                                │
│    - Waits 500ms for page load                             │
│    - Calls showJobDetails(jobId)                           │
│    - Job details view opens automatically                   │
│    - URL cleaned (parameter removed)                        │
└─────────────────────────────────────────────────────────────┘
```

## Files Modified

1. **public/index.php** - Lines 98-103, 1438-1462
   - Modified login handler to check for `view_job_id`
   - Modified `showJobDetails()` to store job ID and open sign-in

2. **user/user.php** - Lines 9187-9212
   - Added auto-show job details script

## Files Created

1. **public/store_job_view.php** - New file
   - Stores job ID in PHP session via AJAX

## Features

✅ **Seamless User Experience**: User clicks job → Signs in → Sees job details automatically
✅ **Session-Based**: Uses PHP session to persist job ID through login
✅ **URL Parameter Redirect**: Clean URL-based redirection after login
✅ **Auto-Cleanup**: Removes URL parameter after showing job details
✅ **Error Handling**: Console logging for debugging
✅ **Fallback Ready**: LocalStorage backup (for future enhancements)

## Testing Instructions

1. **Open homepage** while logged out: `http://localhost/FinalResearch - Copy/public/index.php`
2. **Scroll to "Browse Career Opportunities"** section
3. **Click the arrow icon** on any job card
4. **Sign-in modal should appear**
5. **Enter credentials and sign in**
6. **User dashboard loads**
7. **Job details should automatically open** for the clicked job
8. **Check URL** - should be clean (no view_job parameter visible after loading)

## Console Output

When working correctly, you should see:
```
Job clicked, ID: 5
Job ID stored in session: {success: true, message: "Job ID stored in session", job_id: 5}
🔗 Redirected from homepage to view job ID: 5
✅ Calling showJobDetails for job ID: 5
🔍 Showing job details for ID: 5
```

## Browser Compatibility

- ✅ Modern browsers (Chrome, Firefox, Edge, Safari)
- ✅ Uses standard APIs: localStorage, fetch, URLSearchParams
- ✅ No external dependencies required

## Security Considerations

- ✅ Job ID validated as numeric before storage
- ✅ Session-based (server-side storage)
- ✅ SQL injection protected (intval conversion)
- ✅ URL parameter sanitized

## Future Enhancements

- Could add localStorage fallback if session fails
- Could add loading indicator while redirecting
- Could add toast notification confirming which job is being viewed
- Could track analytics for which jobs get most clicks from homepage

---

**Implementation Date**: November 1, 2025
**Status**: ✅ Complete and Ready for Testing
