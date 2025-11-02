/**
 * Profile Manager Module
 * Handles profile editing and updates
 */

// Profile picture upload
document.getElementById('profilePicture')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    // Validate file
    if (!file.type.match('image.*')) {
        showToast('Please select an image file', 'error');
        return;
    }
    
    if (file.size > 5 * 1024 * 1024) {
        showToast('File size must be less than 5MB', 'error');
        return;
    }
    
    // Upload
    const formData = new FormData();
    formData.append('profile_picture', file);
    formData.append('action', 'upload_picture');
    
    fetch('save_profile_data.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Profile picture updated!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Upload failed', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Upload failed', 'error');
    });
});

// Education form
document.getElementById('educationForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'save_education');
    
    fetch('save_profile_data.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Education added successfully!', 'success');
            this.reset();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Failed to save', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to save', 'error');
    });
});

// Experience form
document.getElementById('experienceForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'save_experience');
    
    fetch('save_profile_data.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Experience added successfully!', 'success');
            this.reset();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Failed to save', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to save', 'error');
    });
});

// Skills form
document.getElementById('skillsForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'save_skills');
    
    fetch('save_profile_data.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Skill added successfully!', 'success');
            this.reset();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Failed to save', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to save', 'error');
    });
});
