/**
 * Admin Applicant Actions Module
 * Handles schedule, approve, reject actions
 */

// Schedule Interview
function scheduleInterview(applicantId) {
    // Open schedule modal
    const modal = document.getElementById('scheduleInterviewModal');
    if (modal) {
        document.getElementById('scheduleApplicantId').value = applicantId;
        modal.classList.remove('hidden');
    }
}

// Schedule Demo Teaching
function scheduleDemoTeaching(applicantId) {
    const modal = document.getElementById('scheduleDemoModal');
    if (modal) {
        document.getElementById('demoApplicantId').value = applicantId;
        modal.classList.remove('hidden');
    }
}

// Approve Interview
function approveInterview(applicantId) {
    if (confirm('Approve this applicant for the next stage?')) {
        fetch('process_applicant_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=approve_interview&applicant_id=${applicantId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Interview approved', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.message || 'Action failed', 'error');
            }
        });
    }
}

// Reject Application
function rejectApplication(applicantId) {
    const modal = document.getElementById('rejectModal');
    if (modal) {
        document.getElementById('rejectApplicantId').value = applicantId;
        modal.classList.remove('hidden');
    }
}

// Hire Applicant
function hireApplicant(applicantId) {
    if (confirm('Mark this applicant as hired?')) {
        fetch('process_applicant_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=hired&applicant_id=${applicantId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Applicant marked as hired', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.message || 'Action failed', 'error');
            }
        });
    }
}
