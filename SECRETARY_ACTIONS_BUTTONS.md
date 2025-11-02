# Secretary vs Department Head Action Buttons

## ✅ Implementation Complete

The action buttons now display differently based on **workflow_stage**, giving each role appropriate actions.

## Button Logic by Workflow Stage

### 🔵 Secretary Review Stage (`workflow_stage = 'secretary_review'`)

**Who sees this**: Secretary

**Actions Available**:
1. ✅ **Transfer to Department Head** - Approve documents and send to dept head
2. 📝 **Request Resubmission** - Ask for missing/incorrect documents
3. ❌ **Reject Application** - Reject with reason

**NOT Available**:
- ❌ Schedule Interview (only Dept Head can do this)

---

### 🟢 Department Head Review Stage (`workflow_stage = 'department_head_review'`)

**Who sees this**: Department Head

**Actions Available**:
1. 📅 **Schedule Interview** - First action after receiving from secretary
2. ❌ **Reject Application** - Reject with reason

**NOT Available**:
- ❌ Request Resubmission (Secretary already verified documents)
- ❌ Transfer to Department Head (already with dept head)

---

### 📅 Interview Scheduled Stage (`workflow_stage = 'interview_scheduled'`)

**Who sees this**: Department Head

**Actions Available**:
1. ✅ **Approve Interview** - Mark interview as passed
2. 🔄 **Reschedule Interview** - Change date/time
3. ❌ **Reject Application** - Reject with reason

---

### 📚 Interview Passed Stage (`workflow_stage = 'interview_completed'`)

**Who sees this**: Department Head

**Actions Available**:
1. 📅 **Schedule Demo Teaching** - Next step in process
2. ❌ **Reject Application** - Reject with reason

---

## Implementation Details

### JavaScript Logic

```javascript
function updateActionButtons(status, applicant = null) {
    const workflowStage = applicant ? applicant.workflow_stage : null;
    
    // SECRETARY ACTIONS
    if (workflowStage === 'secretary_review') {
        show: Transfer to Dept Head, Request Resubmission, Reject
        return;
    }
    
    // DEPARTMENT HEAD ACTIONS
    if (workflowStage === 'department_head_review') {
        show: Schedule Interview, Reject
        // NO Request Resubmission
        return;
    }
    
    // Continue with other workflow stages...
}
```

### Key Changes Made

**File**: `admin/admin.js`

1. Added `transferToDeptHeadBtn` button reference
2. Added `workflow_stage` checking from applicant data
3. Created separate logic blocks for:
   - `secretary_review` stage
   - `department_head_review` stage
4. Early returns prevent showing wrong buttons

## Button ID Requirements

The following button IDs must exist in your HTML:

### For Secretary:
- `transferToDeptHeadBtn` - Transfer to Department Head
- `resubmitBtn` - Request Resubmission
- `rejectBtn` - Reject Application

### For Department Head:
- `scheduleBtn` - Schedule Interview
- `rejectBtn` - Reject Application
- `approveInterviewBtn` - Approve Interview (after scheduled)
- `rescheduleInterviewBtn` - Reschedule Interview
- `scheduleDemoBtn` - Schedule Demo Teaching
- `approveDemoBtn` - Approve Demo
- `rescheduleDemoBtn` - Reschedule Demo
- `hireBtn` - Initially Hire Applicant
- `permanentHireBtn` - Permanently Hire

## Testing Checklist

### Test 1: Secretary View
- [ ] Login as Secretary
- [ ] Open an application in `secretary_review` stage
- [ ] Should see: Transfer, Request Resubmission, Reject
- [ ] Should NOT see: Schedule Interview

### Test 2: Department Head View
- [ ] Login as Department Head
- [ ] Open an application in `department_head_review` stage
- [ ] Should see: Schedule Interview, Reject
- [ ] Should NOT see: Request Resubmission, Transfer

### Test 3: After Interview Scheduled
- [ ] Department Head schedules interview
- [ ] Workflow stage changes to `interview_scheduled`
- [ ] Should see: Approve Interview, Reschedule Interview, Reject
- [ ] Should NOT see: Schedule Interview

## Workflow Stage Flow

```
secretary_review
    ↓ (Transfer)
department_head_review
    ↓ (Schedule Interview)
interview_scheduled
    ↓ (Approve)
interview_completed
    ↓ (Schedule Demo)
demo_scheduled
    ↓ (Approve)
demo_completed
    ↓ (Initially Hire)
initially_hired
    ↓ (Permanently Hire)
permanently_hired / hired
```

## Why This Design?

### Secretary Responsibilities
- **Document Verification**: Ensures all required documents are present
- **Initial Screening**: Basic eligibility check
- **Transfer Control**: Only secretary can move applications forward

### Department Head Responsibilities
- **Interview Management**: Schedule and conduct interviews
- **Skills Assessment**: Demo teaching evaluation
- **Hiring Decisions**: Final approval for hiring

### Clear Separation
- Secretary handles **documents** ✅
- Department Head handles **interviews & hiring** 📅
- No overlap in responsibilities 🎯

## Benefits

✅ **Clear Roles**: Each role knows exactly what they can do
✅ **No Confusion**: Right buttons for right stage
✅ **Better UX**: Users see only relevant actions
✅ **Workflow Enforcement**: Can't skip required steps
✅ **Audit Trail**: workflow_stage tracks progress

## Troubleshooting

### Issue: No buttons showing
**Check**: Does applicant have `workflow_stage` field populated?
**Fix**: Run `fix_existing_applications.php`

### Issue: Wrong buttons showing
**Check**: Is `workflow_stage` value correct?
**Fix**: Check database: `SELECT workflow_stage FROM job_applicants WHERE id = X`

### Issue: Transfer button not showing
**Check**: 
1. Is HTML element with id `transferToDeptHeadBtn` present?
2. Is workflow_stage exactly `'secretary_review'`?

## Next Steps

To complete the Secretary workflow UI:
1. Add "Transfer to Department Head" button HTML to applicant details modal
2. Add JavaScript function `openTransferModal()`
3. Add Transfer modal HTML with notes field
4. Connect to `admin/api/secretary_actions.php` endpoint

The button logic is now ready and will show/hide correctly based on workflow stage!
