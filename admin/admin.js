// Toast notifications (custom alerts)
function showToast(message, type = 'info', duration = 3000) {
    const containerId = 'toast-container';
    let container = document.getElementById(containerId);
    if (!container) {
        container = document.createElement('div');
        container.id = containerId;
        container.className = 'fixed top-4 right-4 z-[9999] space-y-2';
        document.body.appendChild(container);
    }

    const base = 'px-4 py-3 rounded border shadow flex items-center max-w-sm bg-white';
    const variants = {
        success: 'border-green-300 text-green-800 bg-green-50',
        error: 'border-red-300 text-red-800 bg-red-50',
        info: 'border-blue-300 text-blue-800 bg-blue-50',
        warning: 'border-yellow-300 text-yellow-800 bg-yellow-50'
    };
    const icon = {
        success: '<i class="fas fa-check-circle mr-2"></i>',
        error: '<i class="fas fa-exclamation-triangle mr-2"></i>',
        info: '<i class="fas fa-info-circle mr-2"></i>',
        warning: '<i class="fas fa-exclamation-circle mr-2"></i>'
    };

    const toast = document.createElement('div');
    toast.className = `${base} ${variants[type] || variants.info}`;
    toast.innerHTML = `${icon[type] || icon.info}<span class="text-sm"></span>`;
    toast.querySelector('span').textContent = String(message || '');
    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
        if (container.children.length === 0) container.remove();
    }, duration);
}

// Override window.alert globally to use custom toast
window.alert = function(message) { showToast(message, 'info'); };

// Prevent duplicate form submissions
let isSubmitting = false;

function preventDuplicateSubmission(callback) {
    if (isSubmitting) {
        showToast('Please wait, processing your request...', 'warning');
        return Promise.resolve(false);
    }
    isSubmitting = true;
    return callback().finally(() => {
        isSubmitting = false;
    });
}

// Sample data
let jobs = [];

async function loadJobs() {
    const tbody = document.getElementById('jobsTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4">Loading...</td></tr>';

    try {
        const response = await fetch('gets_job.php');
        jobs = await response.json();

        tbody.innerHTML = '';

        jobs.forEach(job => {
            // compute status
            let status = "Active";
            const today = new Date();
            const deadline = new Date(job.application_deadline);

            if (today > deadline) {
                status = "Closed";
            }

            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50';
            row.innerHTML = `
                <td class="px-6 py-4">
                    <div>
                        <div class="font-medium text-gray-900">${job.job_title}</div>
                        <div class="text-sm text-gray-500">${job.locations}</div>
                        <div class="text-sm text-green-600 font-medium">${job.salary_range}</div>
                    </div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">${job.department_role}</td>
                <td class="px-6 py-4">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                        ${job.job_type}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">${job.application_deadline}</td>
                <td class="px-6 py-4">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                        ${status === "Active" ? "bg-green-100 text-green-800" : "bg-red-100 text-red-800"}">
                        ${status}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                        <button onclick="viewJob(${job.id})" class="text-gray-400 hover:text-gray-600" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="editJob(${job.id})" class="text-gray-400 hover:text-blue-600" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteJob(${job.id})" class="text-gray-400 hover:text-red-600" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-red-500">Failed to load jobs.</td></tr>';
        console.error(error);
    }
}





// Users array - will be loaded from database
let users = [];

// Store current editing user ID
let currentEditingUserId = null;

// Toggle password visibility
function togglePasswordVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// DOM Elements
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const openSidebarBtn = document.getElementById('openSidebar');
const closeSidebarBtn = document.getElementById('closeSidebar');

// Sidebar functionality
openSidebarBtn.addEventListener('click', () => {
    sidebar.classList.remove('-translate-x-full');
    sidebarOverlay.classList.remove('hidden');
});

closeSidebarBtn.addEventListener('click', closeSidebar);
sidebarOverlay.addEventListener('click', closeSidebar);

function closeSidebar() {
    sidebar.classList.add('-translate-x-full');
    sidebarOverlay.classList.add('hidden');
}

// Navigation functionality
function showSection(sectionName) {
    try {
        console.log('Showing section:', sectionName);
        
        // Hide all sections
        document.querySelectorAll('.section').forEach(section => {
            section.classList.add('hidden');
        });
        
        // Show selected section
        const targetSection = document.getElementById(sectionName + 'Section');
        if (targetSection) {
            targetSection.classList.remove('hidden');
            console.log('Section shown:', sectionName + 'Section');
        } else {
            console.error('Section not found:', sectionName + 'Section');
            return;
        }
        
        // Update active nav item
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active', 'text-white');
            item.classList.add('text-gray-700');
        });
        
        // Find and activate the clicked nav item
        const clickedButton = event ? event.target : document.querySelector(`[onclick="showSection('${sectionName}')"]`);
        if (clickedButton) {
            clickedButton.classList.add('active', 'text-white');
            clickedButton.classList.remove('text-gray-700');
        }
        
        // Load section data
        switch(sectionName) {
            case 'dashboard':
                loadDashboardData();
                break;
            case 'jobs':
                loadJobs();
                break;
            case 'applicants':
                loadApplicants();
                break;
            case 'archive':
                loadArchive();
                break;
            case 'users':
                loadUsers();
                break;
        }
        
        // Close sidebar on mobile
        if (window.innerWidth < 1024) {
            closeSidebar();
        }
    } catch (error) {
        console.error('Error in showSection:', error);
    }
}

// Load Applicants
async function loadApplicants() {
    const tbody = document.getElementById('applicantsTableBody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4">Loading...</td></tr>';

    try {
        const response = await fetch('gets_applicants.php');
        if (!response.ok) throw new Error('Network response was not ok');

        const applicants = await response.json();
        
        // Store applicants data globally for filtering
        allApplicantsData = applicants;
        
        // Update status counts
        updateStatusCounts();
        
        // Display applicants based on current filter
        displayFilteredApplicants(currentStatusFilter);
        
    } catch (error) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-red-500">Failed to load applicants.</td></tr>';
        console.error('Error loading applicants:', error);
    }
}

// Global variable to store all archived applicants for search
let allArchivedData = [];

// Load Archive (Rejected Applicants)
async function loadArchive() {
    const tbody = document.getElementById('archiveTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4">Loading archived applicants...</td></tr>';

    try {
        const response = await fetch('get_archive.php');
        if (!response.ok) throw new Error('Network response was not ok');

        const archived = await response.json();
        allArchivedData = archived;
        
        // Update count
        document.getElementById('archivedCount').textContent = archived.length;
        
        displayArchivedApplicants(archived);
        
    } catch (error) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-red-500">Failed to load archived applicants.</td></tr>';
        console.error('Error loading archive:', error);
    }
}

// Display archived applicants in table
function displayArchivedApplicants(archived) {
    const tbody = document.getElementById('archiveTableBody');
    
    if (!archived || archived.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-8 text-gray-500">
                    <i class="fas fa-archive text-4xl text-gray-300 mb-3 block"></i>
                    <p>No archived applicants</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = '';
    
    archived.forEach(applicant => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50';
        
        const appliedDate = new Date(applicant.applied_date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
        
        const rejectedDate = applicant.rejected_date ? 
            new Date(applicant.rejected_date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            }) : 'N/A';
        
        row.innerHTML = `
            <td class="px-6 py-4">
                <div class="font-medium text-gray-900">${applicant.full_name}</div>
                <div class="text-sm text-gray-500">${applicant.applicant_email}</div>
            </td>
            <td class="px-6 py-4 text-sm text-gray-900">${applicant.position}</td>
            <td class="px-6 py-4 text-sm text-gray-500">${appliedDate}</td>
            <td class="px-6 py-4 text-sm text-gray-500">${rejectedDate}</td>
            <td class="px-6 py-4">
                <div class="text-sm text-gray-700 max-w-xs truncate" title="${applicant.rejection_reason || 'No reason provided'}">
                    ${applicant.rejection_reason || 'No reason provided'}
                </div>
            </td>
            <td class="px-6 py-4">
                <button onclick="viewArchivedApplicant(${applicant.id})" 
                        class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    <i class="fas fa-eye mr-1"></i>View Details
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Search archived applicants
function searchArchive(searchTerm) {
    if (!searchTerm) {
        displayArchivedApplicants(allArchivedData);
        return;
    }
    
    const filtered = allArchivedData.filter(applicant => {
        const name = applicant.full_name.toLowerCase();
        const email = applicant.applicant_email.toLowerCase();
        const position = applicant.position.toLowerCase();
        const reason = (applicant.rejection_reason || '').toLowerCase();
        const term = searchTerm.toLowerCase();
        
        return name.includes(term) || email.includes(term) || position.includes(term) || reason.includes(term);
    });
    
    displayArchivedApplicants(filtered);
}

// View archived applicant details (redirect to applicant details)
function viewArchivedApplicant(applicantId) {
    viewApplicant(applicantId);
}

// Load Users from Database
async function loadUsers() {
    const tbody = document.getElementById('usersTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4">Loading users...</td></tr>';
    
    try {
        const response = await fetch('api/users.php');
        users = await response.json();
        
        if (!Array.isArray(users) || users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-500">No users found</td></tr>';
            return;
        }
        
        tbody.innerHTML = '';
        
        users.forEach(user => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50';
            row.innerHTML = `
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        ${user.profile_picture ? 
                            `<img src="../uploads/profile_pictures/${user.profile_picture}" alt="${user.name}" class="w-10 h-10 rounded-full object-cover">` :
                            `<div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center text-white font-semibold">
                                ${getInitials(user.name)}
                            </div>`
                        }
                        <div>
                            <div class="font-medium text-gray-900">${user.name}</div>
                            <div class="text-sm text-gray-500">${user.email}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                        ${getRoleIcon(user.role)}
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getRoleColor(user.role)}">
                            ${user.role}
                        </span>
                    </div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">${user.department}</td>
                <td class="px-6 py-4">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${user.status === 'Active' ? 'bg-green-100 text-green-800' : user.status === 'Inactive' ? 'bg-gray-100 text-gray-800' : 'bg-red-100 text-red-800'}">
                        ${user.status}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-500">${user.lastLogin}</td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                        <button onclick="editUser(${user.id})" class="text-gray-400 hover:text-blue-600" title="Edit User">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteUser(${user.id})" class="text-gray-400 hover:text-red-600" title="Delete User">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        console.error('Error loading users:', error);
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-red-500">Failed to load users</td></tr>';
    }
}

// Modal functions
function openCreateJobModal() {
    document.getElementById('createJobModal').classList.remove('hidden');
}

function closeCreateJobModal() {
    document.getElementById('createJobModal').classList.add('hidden');
    document.querySelector('#createJobModal form').reset();
}

function openCreateUserModal() {
    const modal = document.getElementById('createUserModal');
    const form = modal.querySelector('form');
    
    // Reset form to clear any autofilled values
    if (form) {
        form.reset();
    }
    
    // Reset profile picture preview
    const preview = document.getElementById('profilePreview');
    if (preview) {
        preview.innerHTML = '<i class="fas fa-user text-gray-400 text-4xl"></i>';
    }
    
    modal.classList.remove('hidden');
}

function closeCreateUserModal() {
    document.getElementById('createUserModal').classList.add('hidden');
    document.querySelector('#createUserModal form').reset();
    
    // Reset profile picture preview
    const preview = document.getElementById('profilePreview');
    if (preview) {
        preview.innerHTML = '<i class="fas fa-user text-gray-400 text-4xl"></i>';
    }
    
    // Reset password field to password type and icon
    const createPasswordInput = document.getElementById('createUserPassword');
    const createPasswordIcon = document.getElementById('createPasswordIcon');
    if (createPasswordInput) {
        createPasswordInput.type = 'password';
    }
    if (createPasswordIcon) {
        createPasswordIcon.classList.remove('fa-eye-slash');
        createPasswordIcon.classList.add('fa-eye');
    }
}

async function createJob(event) {
    event.preventDefault();
    const formData = new FormData(event.target);

    const newJob = {
        job_title: formData.get('title') || formData.get('uti') || formData.get('sec'),
        department_role: formData.get('department'),  // ✅ match DB column
        job_type: formData.get('type'),
        locations: formData.get('location'),         // ✅ match DB column
        salary_range: formData.get('salary'),
        application_deadline: formData.get('deadline'),
        job_description: formData.get('description'),
        // New fields from enhanced form
        education: formData.get('education') || '',
        experience: formData.get('experience') || '',
        training: formData.get('training') || '',
        eligibility: formData.get('eligibility') || '',
        competency: formData.get('competency') || '',
        duties: formData.get('duties') || ''
    };

    try {
        const response = await fetch('add_job.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(newJob)
        });

        // First check if response is ok
        if (!response.ok) {
            const errorText = await response.text();
            console.error("HTTP Error:", response.status, errorText);
            alert("HTTP Error " + response.status + ": " + errorText);
            return;
        }

        const responseText = await response.text();
        console.log("Full response:", responseText);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error("JSON Parse Error:", parseError);
            console.error("Response text:", responseText);
            alert("Invalid JSON response: " + responseText);
            return;
        }

        if (result.success) {
            alert(result.message);
            loadJobs(); // ✅ refresh from DB
            closeCreateJobModal();
            // Refresh dashboard if we're on the dashboard page
            if (typeof loadDashboardData === 'function') {
                loadDashboardData();
            }
        } else {
            alert("Failed: " + result.message);
        }
    } catch (error) {
        console.error("Error:", error);
        alert("An error occurred while adding the job: " + error.message);
    }
}



async function createUser(event) {
    event.preventDefault();
    
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
    submitBtn.disabled = true;
    
    const formData = new FormData(event.target);
    
    // Validate required fields on client side
    if (!formData.get('name') || !formData.get('email') || !formData.get('password') || 
        !formData.get('role') || !formData.get('department')) {
        showToast('Please fill in all required fields', 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        return;
    }
    
    // Validate file size if profile picture is uploaded
    const profilePicture = formData.get('profile_picture');
    if (profilePicture && profilePicture.size > 5 * 1024 * 1024) {
        showToast('Profile picture must be less than 5MB', 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        return;
    }
    
    console.log('Creating user with file upload...');
    
    try {
        const response = await fetch('api/users.php', {
            method: 'POST',
            body: formData  // Send FormData directly (not JSON) to support file upload
        });
        
        console.log('Response status:', response.status);
        
        // Get response as text first to see what we're getting
        const responseText = await response.text();
        console.log('Response text:', responseText);
        
        // Try to parse as JSON
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response was:', responseText);
            showToast('Server returned invalid response. Check if database table exists.', 'error');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            return;
        }
        
        if (result.success) {
            showToast('User created successfully!', 'success');
            closeCreateUserModal();
            loadUsers();
        } else {
            console.error('Server error:', result.message);
            showToast(result.message || 'Failed to create user', 'error');
        }
    } catch (error) {
        console.error('Error creating user:', error);
        showToast('Network error: ' + error.message, 'error');
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

// Profile picture preview functionality
document.addEventListener('DOMContentLoaded', function() {
    // Create user modal profile preview
    const profileInput = document.getElementById('profilePictureInput');
    if (profileInput) {
        profileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file type
                if (!file.type.match('image.*')) {
                    showToast('Please select an image file', 'error');
                    e.target.value = '';
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    showToast('Image must be less than 5MB', 'error');
                    e.target.value = '';
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(event) {
                    const preview = document.getElementById('profilePreview');
                    if (preview) {
                        preview.innerHTML = `<img src="${event.target.result}" class="w-full h-full object-cover" alt="Preview">`;
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Edit user modal profile preview
    const editProfileInput = document.getElementById('editProfilePictureInput');
    if (editProfileInput) {
        editProfileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file type
                if (!file.type.match('image.*')) {
                    showToast('Please select an image file', 'error');
                    e.target.value = '';
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    showToast('Image must be less than 5MB', 'error');
                    e.target.value = '';
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(event) {
                    const preview = document.getElementById('editProfilePreview');
                    if (preview) {
                        preview.innerHTML = `<img src="${event.target.result}" class="w-full h-full object-cover" alt="Preview">`;
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
});

// Utility functions
function getStatusColor(status) {
    switch(status) {
        case 'Active': return 'bg-green-100 text-green-800';
        case 'Draft': return 'bg-yellow-100 text-yellow-800';
        case 'Closed': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getApplicantStatusColor(status) {
    switch(status) {
        case 'Hired': return 'bg-green-100 text-green-800';
        case 'Initially Hired': return 'bg-green-100 text-green-700';
        case 'Rejected': return 'bg-red-100 text-red-800';
        case 'Interview Scheduled': return 'bg-blue-100 text-blue-800';
        case 'Interview Passed': return 'bg-teal-100 text-teal-800';
        case 'Demo Scheduled': return 'bg-indigo-100 text-indigo-800';
        case 'Demo Passed': return 'bg-emerald-100 text-emerald-800';
        case 'Psychological Exam': return 'bg-purple-100 text-purple-800';
        case 'Resubmission Required': return 'bg-orange-100 text-orange-800';
        case 'Pending': return 'bg-yellow-100 text-yellow-800';
        case 'Under Review': return 'bg-yellow-100 text-yellow-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getRoleColor(role) {
    switch(role) {
        case 'Admin': return 'bg-red-100 text-red-800';
        case 'HR Manager': return 'bg-purple-100 text-purple-800';
        case 'Department Head': return 'bg-blue-100 text-blue-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getRoleIcon(role) {
    switch(role) {
        case 'Admin': return '<i class="fas fa-shield-alt text-red-500"></i>';
        case 'HR Manager': return '<i class="fas fa-shield text-purple-500"></i>';
        case 'Department Head': return '<i class="fas fa-shield text-blue-500"></i>';
        default: return '<i class="fas fa-user text-gray-500"></i>';
    }
}

function getStatusIcon(status) {
    switch(status) {
        case 'Hired': return '<i class="fas fa-check-circle text-green-500"></i>';
        case 'Initially Hired': return '<i class="fas fa-user-check text-green-500"></i>';
        case 'Rejected': return '<i class="fas fa-times-circle text-red-500"></i>';
        case 'Interview Scheduled': return '<i class="fas fa-calendar text-blue-500"></i>';
        case 'Interview Passed': return '<i class="fas fa-user-check text-teal-500"></i>';
        case 'Demo Scheduled': return '<i class="fas fa-chalkboard-teacher text-indigo-500"></i>';
        case 'Demo Passed': return '<i class="fas fa-check-double text-emerald-500"></i>';
        case 'Psychological Exam': return '<i class="fas fa-brain text-purple-500"></i>';
        case 'Resubmission Required': return '<i class="fas fa-redo text-orange-500"></i>';
        case 'Pending': return '<i class="fas fa-clock text-yellow-500"></i>';
        default: return '<i class="fas fa-clock text-yellow-500"></i>';
    }
}

function getInitials(name) {
    return name.split(' ').map(n => n[0]).join('');
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString();
}

function viewJob(id) {
    const job = jobs.find(j => j.id == id);
    if (!job) return;

    const modal = document.getElementById('viewJobModal');
    modal.querySelector('.job-title').innerText = job.job_title;
    modal.querySelector('.job-dept').innerText = job.department_role;
    modal.querySelector('.job-type').innerText = job.job_type;
    modal.querySelector('.job-loc').innerText = job.locations;
    modal.querySelector('.job-salary').innerText = job.salary_range;
    modal.querySelector('.job-deadline').innerText = job.application_deadline;
    modal.querySelector('.job-desc').innerText = job.job_description;

    modal.classList.remove('hidden');
}


async function editJob(id) {
    try {
        // Show loading state
        showToast('Loading job data...', 'info');
        
        // Fetch fresh job data from database
        const response = await fetch(`gets_job.php?id=${id}`);
        const result = await response.json();
        
        if (!result || result.error) {
            showToast('Job not found in database!', 'error');
            return;
        }
        
        const job = result; // Single job object returned when ID is specified
        
        if (!job || !job.id) {
            showToast('Job not found!', 'error');
            return;
        }

        // Check if this is a secretary position
        const jobTitle = (job.job_title || "").toLowerCase();
        const isSecretary = jobTitle.includes("secretary") || 
                           (job.department_role && job.department_role.toLowerCase().includes("secretary"));

        if (isSecretary) {
            // Populate secretary modal
            populateSecretaryModal(job);
        } else {
            // Populate general job modal
            populateGeneralJobModal(job);
        }
        
        console.log('Job data loaded successfully:', job);
        
    } catch (error) {
        console.error('Error loading job data:', error);
        showToast('Failed to load job data. Please try again.', 'error');
    }
}

// Populate general job modal
function populateGeneralJobModal(job) {
    document.getElementById('editJobId').value = job.id || "";
    document.getElementById('editJobTitle').value = job.job_title || "";
    document.getElementById('editDepartment').value = job.department_role || "";
    document.getElementById('editJobType').value = job.job_type || "";
    document.getElementById('editLocation').value = job.locations || "";
    document.getElementById('editSalary').value = job.salary_range || "";
    
    // Format date for input field
    if (job.application_deadline) {
        const deadline = new Date(job.application_deadline);
        const formattedDate = deadline.toISOString().split('T')[0];
        document.getElementById('editDeadline').value = formattedDate;
    } else {
        document.getElementById('editDeadline').value = "";
    }
    
    document.getElementById('editDescription').value = job.job_description || "";
    
    // Minimum qualifications
    document.getElementById('editEducation').value = job.education || "";
    document.getElementById('editExperience').value = job.experience || "";
    document.getElementById('editTraining').value = job.training || "";
    document.getElementById('editEligibility').value = job.eligibility || "";
    
    // Job details
    document.getElementById('editRequirements').value = job.job_requirements || "";
    document.getElementById('editDuties').value = job.duties || "";
    document.getElementById('editCompetency').value = job.competency || "";

    // Show the general job modal
    document.getElementById('newEditJobModal').classList.remove('hidden');
}

// Populate secretary modal
function populateSecretaryModal(job) {
    document.getElementById('editSecretaryJobId').value = job.id || "";
    document.getElementById('editSecretaryJobTitle').value = job.job_title || "";
    document.getElementById('editSecretaryRole').value = job.department_role || "";
    document.getElementById('editSecretaryJobType').value = job.job_type || "";
    document.getElementById('editSecretaryLocation').value = job.locations || "";
    document.getElementById('editSecretarySalary').value = job.salary_range || "";
    
    // Format date for input field
    if (job.application_deadline) {
        const deadline = new Date(job.application_deadline);
        const formattedDate = deadline.toISOString().split('T')[0];
        document.getElementById('editSecretaryDeadline').value = formattedDate;
    } else {
        document.getElementById('editSecretaryDeadline').value = "";
    }
    
    document.getElementById('editSecretaryDescription').value = job.job_description || "";
    
    // Secretary qualifications
    document.getElementById('editSecretaryEducation').value = job.education || "";
    document.getElementById('editSecretaryExperience').value = job.experience || "";
    document.getElementById('editSecretaryTraining').value = job.training || "";
    document.getElementById('editSecretaryEligibility').value = job.eligibility || "";
    
    // Secretary skills & responsibilities
    document.getElementById('editSecretaryRequirements').value = job.job_requirements || "";
    document.getElementById('editSecretaryDuties').value = job.duties || "";
    document.getElementById('editSecretaryCompetency').value = job.competency || "";

    // Show the secretary modal
    document.getElementById('editSecretaryJobModal').classList.remove('hidden');
}

async function saveJob(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);

    // gather all fields (title/uti/sec only one will exist)
    const jobData = {
        id: formData.get("id"),
        job_title: formData.get("title") || formData.get("uti") || formData.get("sec"),
        department_role: formData.get("department"),
        job_type: formData.get("type"),
        locations: formData.get("location"),
        salary_range: formData.get("salary"),
        application_deadline: formData.get("deadline"),
        job_description: formData.get("description")
    };

    try {
        const response = await fetch("update_job.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(jobData)
        });
        const result = await response.json();

        if (result.success) {
            alert("Job updated successfully!");
            form.closest(".fixed").classList.add("hidden"); // close modal
            loadJobs(); // refresh table
            // Refresh dashboard if we're on the dashboard page
            if (typeof loadDashboardData === 'function') {
                loadDashboardData();
            }
        } else {
            alert("Update failed: " + result.message);
        }
    } catch (error) {
        console.error("Error updating job:", error);
    }
}





function deleteJob(id) {
    const job = jobs.find(j => j.id == id);
    if (!job) return;

    const modal = document.getElementById('deleteJobModal');
    modal.dataset.jobId = id;
    modal.querySelector('.job-title').innerText = job.job_title;
    modal.classList.remove('hidden');
}

function confirmDeleteJob() {
    const modal = document.getElementById('deleteJobModal');
    const jobId = modal.dataset.jobId;

    fetch('delete_job.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: jobId })
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            loadJobs();
            // Refresh dashboard if we're on the dashboard page
            if (typeof loadDashboardData === 'function') {
                loadDashboardData();
            }
        } else {
            console.error(result.message);
        }
        modal.classList.add('hidden');
    });
}

function cancelDeleteJob() {
    document.getElementById('deleteJobModal').classList.add('hidden');
}













function closeViewJobModal() {
    document.getElementById('viewJobModal').classList.add('hidden');
}


function viewApplicant(id) {
    viewApplicantDetails(id);
}

function downloadResume(id) {
    alert(`Download resume for applicant ID: ${id}`);
}

function messageApplicant(id) {
    alert(`Send message to applicant ID: ${id}`);
}

async function editUser(id) {
    console.log('=== EDIT USER DEBUG ===');
    console.log('Clicked user ID:', id);
    console.log('ID type:', typeof id);
    
    try {
        // Fetch fresh user data from API
        console.log('Fetching users from API...');
        const response = await fetch('api/users.php');
        console.log('Response status:', response.status);
        
        const responseText = await response.text();
        console.log('Raw response:', responseText);
        
        let allUsers;
        try {
            allUsers = JSON.parse(responseText);
            console.log('Parsed users:', allUsers);
            console.log('Number of users:', allUsers.length);
        } catch (e) {
            console.error('Failed to parse JSON:', e);
            showToast('Failed to load users. Check if table exists.', 'error');
            return;
        }
        
        // Find the specific user - check both as number and string
        console.log('Looking for user with ID:', id);
        const user = allUsers.find(u => u.id == id); // Use == to match both number and string
        console.log('Found user:', user);
        
        if (!user) {
            showToast('User not found in database', 'error');
            console.error('User ID not found:', id);
            console.log('Available user IDs:', allUsers.map(u => u.id));
            return;
        }
        
        // Store user ID in global variable (BACKUP)
        currentEditingUserId = user.id;
        console.log('Stored user ID in global variable:', currentEditingUserId);
        
        // Also set in hidden input
        document.getElementById('editUserId').value = user.id;
        console.log('Set user ID in hidden input:', user.id);
        
        // Get all input elements
        const emailInput = document.getElementById('editUserEmail');
        const passwordInput = document.getElementById('editUserPassword');
        const nameInput = document.getElementById('editUserName');
        const phoneInput = document.getElementById('editUserPhone');
        const roleSelect = document.getElementById('editUserRole');
        const deptSelect = document.getElementById('editUserDepartment');
        const statusSelect = document.getElementById('editUserStatus');
        
        // FIRST: Make fields readonly to block autofill
        emailInput.setAttribute('readonly', 'readonly');
        passwordInput.setAttribute('readonly', 'readonly');
        
        // Clear ALL fields immediately
        nameInput.value = '';
        emailInput.value = '';
        passwordInput.value = '';
        phoneInput.value = '';
        roleSelect.value = '';
        deptSelect.value = '';
        statusSelect.value = '';
        
        // Set database values immediately (BEFORE opening modal)
        nameInput.value = user.name;
        emailInput.value = user.email;
        passwordInput.value = '';
        phoneInput.value = user.phone || '';
        roleSelect.value = user.role;
        deptSelect.value = user.department;
        statusSelect.value = user.status;
        
        // Show profile picture
        const preview = document.getElementById('editProfilePreview');
        if (user.profile_picture) {
            preview.innerHTML = `<img src="../uploads/profile_pictures/${user.profile_picture}" class="w-full h-full object-cover" alt="${user.name}">`;
        } else {
            preview.innerHTML = '<i class="fas fa-user text-gray-400 text-4xl"></i>';
        }
        
        // NOW open the modal
        document.getElementById('editUserModal').classList.remove('hidden');
        
        // Remove readonly and change password type AFTER modal is visible
        setTimeout(() => {
            emailInput.removeAttribute('readonly');
            passwordInput.removeAttribute('readonly');
            // Change password field to password type so it shows dots when typing
            passwordInput.setAttribute('type', 'password');
            
            // Reset password toggle icon to eye (hidden state)
            const editPasswordIcon = document.getElementById('editPasswordIcon');
            if (editPasswordIcon) {
                editPasswordIcon.classList.remove('fa-eye-slash');
                editPasswordIcon.classList.add('fa-eye');
            }
        }, 100);
        
    } catch (error) {
        console.error('Error fetching user data:', error);
        showToast('Failed to load user data', 'error');
    }
}

function closeEditUserModal() {
    const modal = document.getElementById('editUserModal');
    modal.classList.add('hidden');
    
    // Clear global user ID
    currentEditingUserId = null;
    
    // Manually clear all input fields (no form.reset() to avoid issues)
    setTimeout(() => {
        const passwordInput = document.getElementById('editUserPassword');
        
        document.getElementById('editUserId').value = '';
        document.getElementById('editUserName').value = '';
        document.getElementById('editUserEmail').value = '';
        passwordInput.value = '';
        document.getElementById('editUserPhone').value = '';
        document.getElementById('editUserRole').value = '';
        document.getElementById('editUserDepartment').value = '';
        document.getElementById('editUserStatus').value = '';
        
        // Reset password field type to text for next time
        passwordInput.setAttribute('type', 'text');
        
        // Reset profile picture preview
        const preview = document.getElementById('editProfilePreview');
        if (preview) {
            preview.innerHTML = '<i class="fas fa-user text-gray-400 text-4xl"></i>';
        }
    }, 100);
}

async function updateUser(event) {
    event.preventDefault();
    
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
    submitBtn.disabled = true;
    
    // Get values directly from form fields
    let userId = document.getElementById('editUserId').value;
    
    // BACKUP: Use global variable if hidden input is empty
    if (!userId && currentEditingUserId) {
        userId = currentEditingUserId;
        console.log('Using backup user ID from global variable:', userId);
    }
    
    const name = document.getElementById('editUserName').value;
    const email = document.getElementById('editUserEmail').value;
    const password = document.getElementById('editUserPassword').value;
    const phone = document.getElementById('editUserPhone').value;
    const role = document.getElementById('editUserRole').value;
    const department = document.getElementById('editUserDepartment').value;
    const status = document.getElementById('editUserStatus').value;
    
    console.log('UPDATE USER - User ID:', userId);
    console.log('Global backup ID:', currentEditingUserId);
    
    if (!userId) {
        alert('ERROR: User ID is missing! Please close modal and try again.');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        return;
    }
    
    if (!name || !email || !role || !department) {
        showToast('Please fill in all required fields', 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        return;
    }
    
    // Create JSON data object
    const userData = {
        id: userId,
        name: name,
        email: email,
        role: role,
        department: department,
        status: status
    };
    
    if (password) userData.password = password;
    if (phone) userData.phone = phone;
    
    console.log('Sending update data:', userData);
    
    try {
        const response = await fetch('api/users.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(userData)
        });
        
        const result = await response.json();
        
        console.log('Update result:', result);
        
        if (result.success) {
            // Check if there's a new profile picture to upload
            const fileInput = document.getElementById('editProfilePictureInput');
            if (fileInput.files.length > 0) {
                console.log('Uploading profile picture...');
                
                // Upload profile picture separately
                const picFormData = new FormData();
                picFormData.append('user_id', userId);
                picFormData.append('profile_picture', fileInput.files[0]);
                
                const picResponse = await fetch('api/update_profile_picture.php', {
                    method: 'POST',
                    body: picFormData
                });
                
                const picResult = await picResponse.json();
                if (picResult.success) {
                    console.log('Profile picture uploaded successfully');
                } else {
                    console.error('Profile picture upload failed:', picResult.message);
                }
            }
            
            if (result.affected_rows === 0 && fileInput.files.length === 0) {
                showToast('No changes detected (data was the same)', 'warning');
            } else {
                showToast('User updated successfully!', 'success');
            }
            closeEditUserModal();
            loadUsers();
        } else {
            showToast(result.message || 'Failed to update user', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Update failed: ' + error.message, 'error');
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

async function deleteUser(id) {
    // Prevent admin from deleting their own account
    if (typeof CURRENT_ADMIN_ID !== 'undefined' && id == CURRENT_ADMIN_ID) {
        showToast('You cannot delete your own account!', 'error');
        return;
    }
    
    if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        return;
    }
    
    try {
        const response = await fetch(`api/users.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('User deleted successfully!', 'success');
            loadUsers();
        } else {
            showToast(result.message || 'Failed to delete user', 'error');
        }
    } catch (error) {
        console.error('Error deleting user:', error);
        showToast('An error occurred while deleting user', 'error');
    }
}

function moreUserActions(id) {
    alert(`More actions for user ID: ${id}`);
}

// Close modals when clicking outside
// Disable outside-click-to-close behavior for modals (keep modals open unless explicit close buttons are used)
document.addEventListener('click', (e) => {
    // Intentionally left blank to prevent closing on outside clicks
});

// Initialize dashboard on page load
document.addEventListener('DOMContentLoaded', () => {
    // Set dashboard as active by default
    document.querySelector('.nav-item').classList.add('active', 'text-white');
    document.querySelector('.nav-item').classList.remove('text-gray-700');
});

function openJobTypeSelectionModal() {
    document.getElementById('jobTypeSelectionModal').classList.remove('hidden');
}

function closeJobTypeSelectionModal() {
    document.getElementById('jobTypeSelectionModal').classList.add('hidden');
}

function openCreateJobModal(jobType) {
    // Set the job type in the job creation modal
    document.querySelector('input[name="title"]').value = jobType; // Set the job title to the selected job type
    closeJobTypeSelectionModal(); // Close the job type selection modal
    document.getElementById('createJobModal').classList.remove('hidden'); // Open the job creation modal
}

function openCreateutilityJobModal(jobuType) {
    // Set the job type in the job creation modal
    document.querySelector('input[name="uti"]').value = jobuType; // Set the job title to the selected job type
    closeJobTypeSelectionModal(); // Close the job type selection modal
    document.getElementById('createutilityJobModal').classList.remove('hidden'); // Open the job creation modal
}

function openCreatesecJobModal(jobuType) {
    // Set the job type in the job creation modal
    document.querySelector('input[name="sec"]').value = jobuType; // Set the job title to the selected job type
    closeJobTypeSelectionModal(); // Close the job type selection modal
    document.getElementById('createsecJobModal').classList.remove('hidden'); // Open the job creation modal
}


function closeCreateJobModal() {
    document.getElementById('createJobModal').classList.add('hidden');
}

function closeCreateutilityJobModal() {
    document.getElementById('createutilityJobModal').classList.add('hidden');
}

function closeCreatesecJobModal() {
    document.getElementById('createsecJobModal').classList.add('hidden');
}

function closeeditJobModal() {
    document.getElementById('editJobModal').classList.add('hidden');
}

function closeeditutilityJobModal() {
    document.getElementById('editutilityJobModal').classList.add('hidden');
}

function closeeditsecJobModal() {
    document.getElementById('editsecJobModal').classList.add('hidden');
}

// Function to refresh dashboard data
async function loadDashboardData() {
    try {
        const response = await fetch('dashboard_api.php');
        const data = await response.json();
        
        if (data.success) {
            // Update dashboard statistics
            if (data.stats) {
                // Update Total Jobs
                const totalJobsElement = document.querySelector('[data-stat="total_jobs"]');
                if (totalJobsElement) {
                    totalJobsElement.textContent = data.stats.total_jobs;
                }
                
                // Update Total Applications
                const totalApplicantsElement = document.querySelector('[data-stat="total_applicants"]');
                if (totalApplicantsElement) {
                    totalApplicantsElement.textContent = data.stats.total_applicants;
                }
                
                // Update Active Users
                const activeUsersElement = document.querySelector('[data-stat="active_users"]');
                if (activeUsersElement) {
                    activeUsersElement.textContent = data.stats.active_users;
                }
                
                // Update Pending Reviews
                const pendingReviewsElement = document.querySelector('[data-stat="pending_reviews"]');
                if (pendingReviewsElement) {
                    pendingReviewsElement.textContent = data.stats.pending_reviews;
                }
            }
            
            // Update recent activity
            const activityContainer = document.getElementById('recentActivityContainer');
            if (activityContainer && data.recent_activity) {
                let activityHTML = '';
                data.recent_activity.forEach(activity => {
                    let iconClass = 'fas fa-user-plus text-green-600';
                    let bgClass = 'bg-green-100';
                    let activityTitle = 'New application received';
                    
                    switch(activity.activity_type) {
                        case 'application':
                            iconClass = 'fas fa-user-plus text-green-600';
                            bgClass = 'bg-green-100';
                            activityTitle = 'New application received';
                            break;
                        case 'job_created':
                            iconClass = 'fas fa-briefcase text-blue-600';
                            bgClass = 'bg-blue-100';
                            activityTitle = 'Job posting created';
                            break;
                        case 'job_edited':
                            iconClass = 'fas fa-edit text-orange-600';
                            bgClass = 'bg-orange-100';
                            activityTitle = 'Job posting updated';
                            break;
                        case 'job_deleted':
                            iconClass = 'fas fa-trash text-red-600';
                            bgClass = 'bg-red-100';
                            activityTitle = 'Job posting deleted';
                            break;
                        case 'admin_login':
                            iconClass = 'fas fa-sign-in-alt text-indigo-600';
                            bgClass = 'bg-indigo-100';
                            activityTitle = 'Admin logged in';
                            break;
                        case 'status_changed':
                            iconClass = 'fas fa-exchange-alt text-purple-600';
                            bgClass = 'bg-purple-100';
                            activityTitle = 'Application status changed';
                            break;
                        case 'data_export':
                            iconClass = 'fas fa-download text-teal-600';
                            bgClass = 'bg-teal-100';
                            activityTitle = 'Data exported';
                            break;
                    }
                    
                    const date = new Date(activity.created_at);
                    const timeStr = date.toLocaleDateString('en-US', { 
                        month: 'short', 
                        day: 'numeric', 
                        hour: 'numeric', 
                        minute: '2-digit',
                        hour12: true 
                    });
                    
                    activityHTML += `
                        <div class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg transition-colors">
                            <div class="w-8 h-8 ${bgClass} rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="${iconClass} text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">${activityTitle}</p>
                                <p class="text-xs text-gray-500 truncate">${activity.description}</p>
                            </div>
                            <span class="text-xs text-gray-400 flex-shrink-0">${timeStr}</span>
                        </div>
                    `;
                });
                
                if (activityHTML === '') {
                    activityHTML = '<div class="text-center py-4"><p class="text-gray-500">No recent activity</p></div>';
                }
                
                activityContainer.innerHTML = activityHTML;
            }
            
            // Update recent job postings
            if (data.recent_jobs) {
                const jobsContainer = document.querySelector('#recentJobsContainer');
                if (jobsContainer) {
                    let jobsHTML = '';
                    data.recent_jobs.forEach(job => {
                        jobsHTML += `
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="font-medium text-gray-900">${job.job_title}</div>
                                        <div class="text-sm text-gray-500">${job.department_role || 'General'}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">${job.application_count || 0}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        Active
                                    </span>
                                </td>
                            </tr>
                        `;
                    });
                    
                    if (jobsHTML === '') {
                        jobsHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">No jobs found</td></tr>';
                    }
                    
                    jobsContainer.innerHTML = jobsHTML;
                }
            }
            
            // Refresh jobs table if on jobs page
            if (typeof loadJobs === 'function') {
                loadJobs();
            }
        }
    } catch (error) {
        console.error('Error refreshing dashboard:', error);
    }
}

// Global variable to store current applicant data
let currentApplicantData = null;

// View Applicant Details Function
async function viewApplicantDetails(applicantId) {
    try {
        console.log('Fetching applicant details for ID:', applicantId);
        currentApplicantId = applicantId; // Set the current applicant ID for modal operations
        
        // Add cache-busting parameter to always get fresh data
        const timestamp = new Date().getTime();
        const response = await fetch(`view_applicant.php?id=${applicantId}&_=${timestamp}`, {
            cache: 'no-store',
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache'
            }
        });
        console.log('Response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        console.log('Raw response:', responseText);
        
        const data = JSON.parse(responseText);
        
        if (data.success) {
            const applicant = data.applicant;
            
            // Update status badge in header
            const statusBadgeHTML = getStatusBadge(applicant.status);
            document.getElementById('applicantStatus').innerHTML = statusBadgeHTML;
            
            // Update status badge in Actions section
            const actionStatusBadge = document.getElementById('actionStatusBadge');
            if (actionStatusBadge) {
                actionStatusBadge.innerHTML = statusBadgeHTML;
                console.log('Initial status loaded:', applicant.status, statusBadgeHTML);
            }
            
            // Update personal information
            const personalInfo = document.getElementById('personalInfo');
            
            // Create profile picture HTML
            const profilePictureHTML = applicant.profile_picture 
                ? `<div class="flex justify-center mb-4">
                     <div class="w-24 h-24 rounded-full overflow-hidden bg-gray-200 flex items-center justify-center">
                       <img src="../user/uploads/profile_pictures/${applicant.profile_picture}" alt="Profile Picture" class="w-full h-full object-cover">
                     </div>
                   </div>`
                : `<div class="flex justify-center mb-4">
                     <div class="w-24 h-24 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center">
                       <span class="text-white font-bold text-2xl">${(applicant.full_name || 'N').charAt(0).toUpperCase()}</span>
                     </div>
                   </div>`;
            
            personalInfo.innerHTML = `
                ${profilePictureHTML}
                <div>
                    <label class="block text-sm font-medium text-gray-600">Full Name</label>
                    <p class="text-gray-900">${applicant.full_name || 'N/A'}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">Email</label>
                    <p class="text-gray-900">${applicant.applicant_email || 'N/A'}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">Contact Number</label>
                    <p class="text-gray-900">${applicant.contact_num || 'N/A'}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">Position Applied</label>
                    <p class="text-gray-900">${applicant.position || 'N/A'}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">Address</label>
                    <p class="text-gray-900 whitespace-pre-wrap">${applicant.address || 'Not provided'}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600">Applied Date</label>
                    <p class="text-gray-900">${formatDate(applicant.applied_date)}</p>
                </div>
            `;
            
            // Update education information
            const educationInfo = document.getElementById('educationInfo');
            if (data.education && data.education.length > 0) {
                let educationHTML = '';
                data.education.forEach(edu => {
                    educationHTML += `
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="font-semibold text-gray-900">${edu.degree}</h3>
                                <span class="text-sm text-gray-500">${edu.start_year} - ${edu.end_year}</span>
                            </div>
                            <p class="text-gray-700 mb-1">${edu.field_of_study}</p>
                            <p class="text-gray-600 text-sm mb-1">${edu.institution}</p>
                            ${edu.gpa ? `<p class="text-gray-600 text-sm">GPA: ${edu.gpa}</p>` : ''}
                        </div>
                    `;
                });
                educationInfo.innerHTML = educationHTML;
            } else {
                educationInfo.innerHTML = '<p class="text-gray-500 italic">No education information provided</p>';
            }
            
            // Update work experience information
            const experienceInfo = document.getElementById('experienceInfo');
            if (data.experience && data.experience.length > 0) {
                let experienceHTML = '';
                data.experience.forEach(exp => {
                    const startDate = new Date(exp.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
                    const endDate = exp.is_current ? 'Present' : new Date(exp.end_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
                    
                    experienceHTML += `
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="font-semibold text-gray-900">${exp.job_title}</h3>
                                <span class="text-sm text-gray-500">${startDate} - ${endDate}</span>
                            </div>
                            <p class="text-gray-700 mb-1">${exp.company}</p>
                            ${exp.location ? `<p class="text-gray-600 text-sm mb-2">${exp.location}</p>` : ''}
                            ${exp.description ? `<p class="text-gray-600 text-sm">${exp.description}</p>` : ''}
                            ${exp.is_current ? '<span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full mt-2">Current Position</span>' : ''}
                        </div>
                    `;
                });
                experienceInfo.innerHTML = experienceHTML;
            } else {
                experienceInfo.innerHTML = '<p class="text-gray-500 italic">No work experience information provided</p>';
            }
            
            // Update skills information
            const skillsInfo = document.getElementById('skillsInfo');
            if (data.skills && Object.keys(data.skills).length > 0) {
                let skillsHTML = '';
                Object.keys(data.skills).forEach(category => {
                    skillsHTML += `
                        <div class="border border-gray-200 rounded-lg p-4">
                            <h3 class="font-semibold text-gray-900 mb-3">${category}</h3>
                            <div class="grid grid-cols-1 gap-2">
                    `;
                    
                    data.skills[category].forEach(skill => {
                        const skillLevel = skill.skill_level;
                        const stars = '★'.repeat(skillLevel) + '☆'.repeat(5 - skillLevel);
                        
                        skillsHTML += `
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">${skill.skill_name}</span>
                                <span class="text-yellow-500 text-sm">${stars}</span>
                            </div>
                        `;
                    });
                    
                    skillsHTML += `
                            </div>
                        </div>
                    `;
                });
                skillsInfo.innerHTML = skillsHTML;
            } else {
                skillsInfo.innerHTML = '<p class="text-gray-500 italic">No skills information provided</p>';
            }
            
            // Update documents grid
            const documentsGrid = document.getElementById('documentsGrid');
            const documents = [
                { field: 'application_letter', label: 'Application Letter' },
                { field: 'resume', label: 'Updated and Comprehensive Resume' },
                { field: 'tor', label: 'Transcript of Record (TOR)' },
                { field: 'diploma', label: 'Diploma' },
                { field: 'professional_license', label: 'Professional License' },
                { field: 'coe', label: 'Certificate of Employment (COE)' },
                { field: 'seminars_trainings', label: 'Seminar/Training Certificates' },
                { field: 'masteral_cert', label: 'Masteral Certificate' }
            ];
            
            console.log('📄 Document files from database:');
            console.log('  Application Letter:', applicant.application_letter);
            console.log('  Resume:', applicant.resume);
            console.log('  TOR:', applicant.tor);
            console.log('  Diploma:', applicant.diploma);
            console.log('  Professional License:', applicant.professional_license);
            console.log('  COE:', applicant.coe);
            console.log('  Seminars/Trainings:', applicant.seminars_trainings);
            console.log('  Masteral Cert:', applicant.masteral_cert);
            
            let documentsHTML = '';
            documents.forEach(doc => {
                if (applicant[doc.field]) {
                    const fileName = applicant[doc.field];
                    const fileExtension = fileName.split('.').pop().toLowerCase();
                    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(fileExtension);
                    const isPdf = fileExtension === 'pdf';
                    
                    documentsHTML += `
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-medium text-gray-900">${doc.label}</h3>
                                <div class="flex items-center gap-2">
                                    <button onclick="viewDocument('../user/uploads/${fileName}', '${doc.label}', '${isImage}')" 
                                            class="text-blue-600 hover:text-blue-800 text-sm">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <a href="../user/uploads/${fileName}" 
                                       download 
                                       class="text-green-600 hover:text-green-800 text-sm">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-gray-600 text-sm">
                                <i class="fas ${isImage ? 'fa-image' : isPdf ? 'fa-file-pdf' : 'fa-file-alt'}"></i>
                                <span class="truncate">${fileName}</span>
                            </div>
                            ${isImage ? `
                                <div class="mt-3">
                                    <img src="../user/uploads/${fileName}" 
                                         alt="${doc.label}" 
                                         class="w-full h-32 object-cover rounded border cursor-pointer"
                                         onclick="viewDocument('../user/uploads/${fileName}', '${doc.label}', true)">
                                </div>
                            ` : ''}
                        </div>
                    `;
                }
            });
            
            if (documentsHTML === '') {
                documentsHTML = '<p class="text-gray-500 text-center py-4">No documents submitted</p>';
            }
            
            documentsGrid.innerHTML = documentsHTML;
            
            // Update interview information if available
            const interviewInfo = document.getElementById('interviewInfo');
            if (applicant.interview_date) {
                const interviewDetails = document.getElementById('interviewDetails');
                const interviewDateTime = new Date(applicant.interview_date);
                const interviewDate = interviewDateTime.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                const interviewTime = interviewDateTime.toLocaleTimeString('en-US', { 
                    hour: 'numeric', 
                    minute: '2-digit',
                    hour12: true 
                });
                
                interviewDetails.innerHTML = `
                    <div>
                        <label class="block text-sm font-medium text-gray-600">Date</label>
                        <p class="text-gray-900">${interviewDate}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600">Time</label>
                        <p class="text-gray-900">${interviewTime}</p>
                    </div>
                    ${applicant.interview_notes ? `
                    <div>
                        <label class="block text-sm font-medium text-gray-600">Notes</label>
                        <p class="text-gray-900">${applicant.interview_notes}</p>
                    </div>
                    ` : ''}
                `;
                interviewInfo.style.display = 'block';
            } else {
                interviewInfo.style.display = 'none';
            }
            
            // Update demo teaching information if available
            const demoInfo = document.getElementById('demoInfo');
            if (applicant.demo_date) {
                const demoDetails = document.getElementById('demoDetails');
                const demoDateTime = new Date(applicant.demo_date);
                const demoDate = demoDateTime.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                const demoTime = demoDateTime.toLocaleTimeString('en-US', { 
                    hour: 'numeric', 
                    minute: '2-digit',
                    hour12: true 
                });
                
                demoDetails.innerHTML = `
                    <div>
                        <label class="block text-sm font-medium text-gray-600">Date</label>
                        <p class="text-gray-900">${demoDate}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600">Time</label>
                        <p class="text-gray-900">${demoTime}</p>
                    </div>
                    <div class="mt-2 p-3 bg-indigo-50 border border-indigo-200 rounded-lg">
                        <p class="text-sm text-indigo-800 flex items-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            Demo teaching session scheduled. Applicant will prepare teaching materials.
                        </p>
                    </div>
                `;
                demoInfo.style.display = 'block';
            } else {
                demoInfo.style.display = 'none';
            }
            
            // Display psychological exam receipt if uploaded
            const psychReceiptInfo = document.getElementById('psychReceiptInfo');
            const psychReceiptDetails = document.getElementById('psychReceiptDetails');
            
            if (applicant.psych_exam_receipt) {
                const receiptPath = '../user/uploads/' + applicant.psych_exam_receipt;
                const fileExtension = applicant.psych_exam_receipt.split('.').pop().toLowerCase();
                const isPDF = fileExtension === 'pdf';
                
                psychReceiptDetails.innerHTML = `
                    <div class="p-4 bg-purple-50 border border-purple-200 rounded-lg">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-medium text-purple-900">Receipt Status:</span>
                            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-semibold">
                                <i class="fas fa-check-circle mr-1"></i>Uploaded
                            </span>
                        </div>
                        <div class="flex items-center gap-3 mb-3">
                            <i class="fas fa-file${isPDF ? '-pdf' : '-image'} text-3xl text-purple-600"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-900">${applicant.psych_exam_receipt}</p>
                                <p class="text-xs text-gray-600">${fileExtension.toUpperCase()} File</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <a href="${receiptPath}" target="_blank" 
                               class="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-center text-sm font-medium">
                                <i class="fas fa-eye mr-2"></i>View Receipt
                            </a>
                            <a href="${receiptPath}" download 
                               class="flex-1 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors text-center text-sm font-medium">
                                <i class="fas fa-download mr-2"></i>Download
                            </a>
                        </div>
                        ${!applicant.initially_hired_date ? `
                        <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <p class="text-sm text-yellow-800 flex items-center">
                                <i class="fas fa-info-circle mr-2"></i>
                                Please review the receipt and click "Initially Hire Applicant" to approve.
                            </p>
                        </div>
                        ` : `
                        <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                            <p class="text-sm text-green-800 flex items-center">
                                <i class="fas fa-check-circle mr-2"></i>
                                Receipt approved - Applicant marked as initially hired
                            </p>
                        </div>
                        `}
                    </div>
                `;
                psychReceiptInfo.style.display = 'block';
            } else {
                psychReceiptInfo.style.display = 'none';
            }
            
            // Store current applicant ID and data for form submissions
            window.currentApplicantId = applicantId;
            currentApplicantData = applicant; // Store globally for validation checks
            
            // Update button visibility based on status
            updateActionButtons(applicant.status, applicant);
            
            // Show applicant details section without changing navigation highlight
            document.querySelectorAll('.section').forEach(section => {
                section.classList.add('hidden');
            });
            document.getElementById('applicantDetailsSection').classList.remove('hidden');
            
        } else {
            alert('Error loading applicant details: ' + data.error);
        }
    } catch (error) {
        console.error('Error loading applicant details:', error);
        alert('Failed to load applicant details');
    }
}

function getStatusBadge(status) {
    let colorClass = 'bg-gray-100 text-gray-800';
    let icon = '';
    
    switch(status) {
        case 'Pending':
            colorClass = 'bg-yellow-100 text-yellow-800';
            icon = '<i class="fas fa-clock mr-1"></i>';
            break;
        case 'Approved':
            colorClass = 'bg-green-100 text-green-800';
            icon = '<i class="fas fa-check mr-1"></i>';
            break;
        case 'Hired':
            colorClass = 'bg-green-100 text-green-800 border-2 border-green-300';
            icon = '<i class="fas fa-check-circle mr-1"></i>';
            break;
        case 'Rejected':
            colorClass = 'bg-red-100 text-red-800 border-2 border-red-300';
            icon = '<i class="fas fa-times-circle mr-1"></i>';
            break;
        case 'Interview Scheduled':
            colorClass = 'bg-blue-100 text-blue-800';
            icon = '<i class="fas fa-calendar mr-1"></i>';
            break;
        case 'Resubmission Required':
            colorClass = 'bg-orange-100 text-orange-800';
            icon = '<i class="fas fa-redo mr-1"></i>';
            break;
    }
    
    return `<span class="px-3 py-2 text-sm font-bold rounded-full ${colorClass} inline-flex items-center">${icon}${status}</span>`;
}

// Update action buttons visibility based on applicant status
function updateActionButtons(status, applicant = null) {
    const scheduleBtn = document.getElementById('scheduleBtn');
    const approveInterviewBtn = document.getElementById('approveInterviewBtn');
    const rescheduleInterviewBtn = document.getElementById('rescheduleInterviewBtn');
    const scheduleDemoBtn = document.getElementById('scheduleDemoBtn');
    const approveDemoBtn = document.getElementById('approveDemoBtn');
    const rescheduleDemoBtn = document.getElementById('rescheduleDemoBtn');
    const hireBtn = document.getElementById('hireBtn');
    const permanentHireBtn = document.getElementById('permanentHireBtn');
    const resubmitBtn = document.getElementById('resubmitBtn');
    const rejectBtn = document.getElementById('rejectBtn');
    
    // Use stored applicant data if not provided
    if (!applicant && currentApplicantData) {
        applicant = currentApplicantData;
    }
    
    // Hide all buttons first
    if (scheduleBtn) scheduleBtn.classList.add('hidden');
    if (approveInterviewBtn) approveInterviewBtn.classList.add('hidden');
    if (rescheduleInterviewBtn) rescheduleInterviewBtn.classList.add('hidden');
    if (scheduleDemoBtn) scheduleDemoBtn.classList.add('hidden');
    if (approveDemoBtn) approveDemoBtn.classList.add('hidden');
    if (rescheduleDemoBtn) rescheduleDemoBtn.classList.add('hidden');
    if (hireBtn) hireBtn.classList.add('hidden');
    if (permanentHireBtn) permanentHireBtn.classList.add('hidden');
    if (resubmitBtn) resubmitBtn.classList.add('hidden');
    if (rejectBtn) rejectBtn.classList.add('hidden');
    
    // Check if psychological exam receipt has been uploaded
    const hasPsychReceipt = applicant && applicant.psych_exam_receipt;
    
    switch(status) {
        case 'Pending':
        case 'Resubmission Required':
            // Show all buttons except hire
            if (scheduleBtn) scheduleBtn.classList.remove('hidden');
            if (resubmitBtn) resubmitBtn.classList.remove('hidden');
            if (rejectBtn) rejectBtn.classList.remove('hidden');
            break;
        case 'Interview Scheduled':
            // After interview scheduled, show Approve Interview, Reschedule Interview, and Reject buttons
            if (approveInterviewBtn) approveInterviewBtn.classList.remove('hidden');
            if (rescheduleInterviewBtn) rescheduleInterviewBtn.classList.remove('hidden');
            if (rejectBtn) rejectBtn.classList.remove('hidden');
            break;
        case 'Interview Passed':
            // After interview approved, show Schedule Demo and Reject buttons
            if (scheduleDemoBtn) scheduleDemoBtn.classList.remove('hidden');
            if (rejectBtn) rejectBtn.classList.remove('hidden');
            break;
        case 'Demo Scheduled':
            // After demo scheduled, show Approve Demo, Reschedule Demo, and Reject buttons
            if (approveDemoBtn) approveDemoBtn.classList.remove('hidden');
            if (rescheduleDemoBtn) rescheduleDemoBtn.classList.remove('hidden');
            if (rejectBtn) rejectBtn.classList.remove('hidden');
            break;
        case 'Demo Passed':
            // After demo passed, check if psych receipt uploaded before showing hire button
            if (hasPsychReceipt) {
                if (hireBtn) hireBtn.classList.remove('hidden');
            } else {
                // Show message that receipt is required
                const actionButtonsContainer = document.getElementById('actionButtons');
                if (actionButtonsContainer && !actionButtonsContainer.querySelector('.psych-warning')) {
                    const warningDiv = document.createElement('div');
                    warningDiv.className = 'psych-warning bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-3';
                    warningDiv.innerHTML = `
                        <div class="flex items-start gap-3">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mt-1"></i>
                            <div>
                                <h4 class="font-semibold text-yellow-900 text-sm">Psychological Exam Receipt Required</h4>
                                <p class="text-xs text-yellow-700 mt-1">The applicant must upload their psychological exam receipt before you can proceed with hiring.</p>
                            </div>
                        </div>
                    `;
                    actionButtonsContainer.prepend(warningDiv);
                }
            }
            if (rejectBtn) rejectBtn.classList.remove('hidden');
            break;
        case 'Psychological Exam':
            // User uploaded receipt, admin reviews and can hire or reject
            if (hasPsychReceipt) {
                if (hireBtn) hireBtn.classList.remove('hidden');
            } else {
                // Show message that receipt is required
                const actionButtonsContainer = document.getElementById('actionButtons');
                if (actionButtonsContainer && !actionButtonsContainer.querySelector('.psych-warning')) {
                    const warningDiv = document.createElement('div');
                    warningDiv.className = 'psych-warning bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-3';
                    warningDiv.innerHTML = `
                        <div class="flex items-start gap-3">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mt-1"></i>
                            <div>
                                <h4 class="font-semibold text-yellow-900 text-sm">Psychological Exam Receipt Required</h4>
                                <p class="text-xs text-yellow-700 mt-1">The applicant must upload their psychological exam receipt before you can proceed with hiring.</p>
                            </div>
                        </div>
                    `;
                    actionButtonsContainer.prepend(warningDiv);
                }
            }
            if (rejectBtn) rejectBtn.classList.remove('hidden');
            break;
        case 'Initially Hired':
            // After initially hired, show Permanently Hire button
            if (permanentHireBtn) permanentHireBtn.classList.remove('hidden');
            break;
        case 'Hired':
        case 'Rejected':
            // No actions available for final statuses
            break;
        default:
            // Default case - show schedule, resubmit, and reject
            if (scheduleBtn) scheduleBtn.classList.remove('hidden');
            if (resubmitBtn) resubmitBtn.classList.remove('hidden');
            if (rejectBtn) rejectBtn.classList.remove('hidden');
            break;
    }
    
    // Remove any existing warning if hire button is now visible
    if (hireBtn && !hireBtn.classList.contains('hidden')) {
        const existingWarning = document.querySelector('.psych-warning');
        if (existingWarning) {
            existingWarning.remove();
        }
    }
}

// Modal functions for applicant actions
let currentApplicantId = null;

function openScheduleModal() {
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.getElementById('interviewDate');
    const timeInput = document.getElementById('interviewTime');
    
    if (dateInput) {
        dateInput.setAttribute('min', today);
    }
    
    // Set time restrictions (8:00 AM to 4:00 PM)
    if (timeInput) {
        timeInput.setAttribute('min', '08:00');
        timeInput.setAttribute('max', '16:00');
    }
    
    document.getElementById('scheduleModal').classList.remove('hidden');
    document.getElementById('scheduleModal').classList.add('flex');
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').classList.add('hidden');
    document.getElementById('scheduleModal').classList.remove('flex');
    document.getElementById('scheduleForm').reset();
}

function openResubmitModal() {
    document.getElementById('resubmitModal').classList.remove('hidden');
    document.getElementById('resubmitModal').classList.add('flex');
}

function closeResubmitModal() {
    document.getElementById('resubmitModal').classList.add('hidden');
    document.getElementById('resubmitModal').classList.remove('flex');
    document.getElementById('resubmitForm').reset();
}

function openRejectModal() {
    document.getElementById('rejectModal').classList.remove('hidden');
    document.getElementById('rejectModal').classList.add('flex');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
    document.getElementById('rejectModal').classList.remove('flex');
    document.getElementById('rejectForm').reset();
}

function openHireModal() {
    document.getElementById('hireModal').classList.remove('hidden');
    document.getElementById('hireModal').classList.add('flex');
}

function closeHireModal() {
    document.getElementById('hireModal').classList.add('hidden');
    document.getElementById('hireModal').classList.remove('flex');
    document.getElementById('hireForm').reset();
}

function openPermanentHireModal() {
    document.getElementById('permanentHireModal').classList.remove('hidden');
    document.getElementById('permanentHireModal').classList.add('flex');
}

function closePermanentHireModal() {
    document.getElementById('permanentHireModal').classList.add('hidden');
    document.getElementById('permanentHireModal').classList.remove('flex');
    document.getElementById('permanentHireForm').reset();
}

function openApproveDemoModal() {
    document.getElementById('approveDemoModal').classList.remove('hidden');
    document.getElementById('approveDemoModal').classList.add('flex');
}

function closeApproveDemoModal() {
    document.getElementById('approveDemoModal').classList.add('hidden');
    document.getElementById('approveDemoModal').classList.remove('flex');
    document.getElementById('approveDemoForm').reset();
}

function openApproveInterviewModal() {
    document.getElementById('approveInterviewModal').classList.remove('hidden');
    document.getElementById('approveInterviewModal').classList.add('flex');
}

function closeApproveInterviewModal() {
    document.getElementById('approveInterviewModal').classList.add('hidden');
    document.getElementById('approveInterviewModal').classList.remove('flex');
    document.getElementById('approveInterviewForm').reset();
}

function openRescheduleInterviewModal() {
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.getElementById('rescheduleInterviewDate');
    const timeInput = document.getElementById('rescheduleInterviewTime');
    
    if (dateInput) {
        dateInput.setAttribute('min', today);
    }
    
    // Set time restrictions (8:00 AM to 4:00 PM)
    if (timeInput) {
        timeInput.setAttribute('min', '08:00');
        timeInput.setAttribute('max', '16:00');
    }
    
    document.getElementById('rescheduleInterviewModal').classList.remove('hidden');
    document.getElementById('rescheduleInterviewModal').classList.add('flex');
}

function closeRescheduleInterviewModal() {
    document.getElementById('rescheduleInterviewModal').classList.add('hidden');
    document.getElementById('rescheduleInterviewModal').classList.remove('flex');
    document.getElementById('rescheduleInterviewForm').reset();
}

function openRescheduleDemoModal() {
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.getElementById('rescheduleDemoDate');
    const timeInput = document.getElementById('rescheduleDemoTime');
    
    if (dateInput) {
        dateInput.setAttribute('min', today);
    }
    
    // Set time restrictions (8:00 AM to 4:00 PM)
    if (timeInput) {
        timeInput.setAttribute('min', '08:00');
        timeInput.setAttribute('max', '16:00');
    }
    
    document.getElementById('rescheduleDemoModal').classList.remove('hidden');
    document.getElementById('rescheduleDemoModal').classList.add('flex');
}

function closeRescheduleDemoModal() {
    document.getElementById('rescheduleDemoModal').classList.add('hidden');
    document.getElementById('rescheduleDemoModal').classList.remove('flex');
    document.getElementById('rescheduleDemoForm').reset();
}

function openDemoScheduleModal() {
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.getElementById('interviewDate');
    const timeInput = document.getElementById('interviewTime');
    
    if (dateInput) {
        dateInput.setAttribute('min', today);
    }
    
    // Set time restrictions (8:00 AM to 4:00 PM)
    if (timeInput) {
        timeInput.setAttribute('min', '08:00');
        timeInput.setAttribute('max', '16:00');
    }
    
    // Reuse the same schedule modal but change the title and action
    document.getElementById('scheduleModal').classList.remove('hidden');
    document.getElementById('scheduleModal').classList.add('flex');
    document.querySelector('#scheduleModal h3').textContent = 'Schedule Demo Teaching';
    document.getElementById('scheduleForm').dataset.action = 'schedule_demo';
}

// Form submission handlers
document.addEventListener('DOMContentLoaded', function() {
    // Schedule Interview/Demo/Psych Form
    document.getElementById('scheduleForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Validate date and time
        const dateValue = document.getElementById('interviewDate').value;
        const timeValue = document.getElementById('interviewTime').value;
        
        if (!dateValue || !timeValue) {
            showToast('Please select both date and time', 'warning');
            return;
        }
        
        // Check if the selected date/time is in the past
        const selectedDateTime = new Date(dateValue + 'T' + timeValue);
        const now = new Date();
        
        if (selectedDateTime < now) {
            showToast('Please select a future date and time for the schedule.', 'warning');
            return;
        }
        
        // Check if time is within business hours (8:00 AM - 4:00 PM)
        const timeHours = parseInt(timeValue.split(':')[0]);
        const timeMinutes = parseInt(timeValue.split(':')[1]);
        const timeInMinutes = timeHours * 60 + timeMinutes;
        const minTime = 8 * 60; // 8:00 AM
        const maxTime = 16 * 60; // 4:00 PM
        
        if (timeInMinutes < minTime || timeInMinutes > maxTime) {
            showToast('Please select a time between 8:00 AM and 4:00 PM.', 'warning');
            return;
        }
        
        // Prevent duplicate submissions
        preventDuplicateSubmission(async () => {
            // Get the action from dataset (defaults to interview)
            const action = this.dataset.action || 'schedule_interview';
            
            const formData = new FormData();
            formData.append('action', action);
            formData.append('applicant_id', currentApplicantId);
            formData.append('interview_date', dateValue);
            formData.append('interview_time', timeValue);
            formData.append('interview_notes', document.getElementById('interviewNotes').value);
            
            // Map actions to status names
            const statusMap = {
                'schedule_interview': 'Interview Scheduled',
                'schedule_demo': 'Demo Scheduled'
            };
            
            const successMessages = {
                'schedule_interview': 'Interview scheduled successfully!',
                'schedule_demo': 'Demo teaching scheduled successfully!'
            };
            
            try {
                const response = await fetch('process_applicant_action.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(successMessages[action], 'success');
                    closeScheduleModal();
                    
                    // Immediately update the status badge
                    const newStatus = statusMap[action];
                    const statusBadge = getStatusBadge(newStatus);
                    document.getElementById('applicantStatus').innerHTML = statusBadge;
                    
                    // Update action buttons immediately
                    updateActionButtons(newStatus);
                    
                    // Reset form action
                    this.dataset.action = 'schedule_interview';
                    document.querySelector('#scheduleModal h3').textContent = 'Schedule Interview';
                    
                    // Refresh applicant details
                    setTimeout(() => {
                        viewApplicantDetails(currentApplicantId);
                        loadApplicants();
                    }, 500); // Small delay to ensure database update is complete
                } else {
                    // Check if it's a validation message
                    if (result.error && (result.error.includes('Please select') || result.error.includes('8:00 AM and 4:00 PM'))) {
                        showToast(result.error, 'warning');
                    } else {
                        showToast(result.error, 'error');
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Failed to complete action', 'error');
            }
        });
    });
    
    // Request Resubmission Form
    document.getElementById('resubmitForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const checkedBoxes = document.querySelectorAll('input[name="resubmit_documents"]:checked');
        const documents = Array.from(checkedBoxes).map(cb => cb.value);
        
        if (documents.length === 0) {
            showToast('Please select at least one document for resubmission.', 'warning');
            return;
        }
        
        // Prevent duplicate submissions
        preventDuplicateSubmission(async () => {
            const formData = new FormData();
            formData.append('action', 'request_resubmission');
            formData.append('applicant_id', currentApplicantId);
            formData.append('documents', JSON.stringify(documents));
            formData.append('notes', document.getElementById('resubmitNotes').value);
            
            try {
                const response = await fetch('process_applicant_action.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Resubmission request sent successfully!', 'success');
                    closeResubmitModal();
                    
                    // Immediately update the status badge
                    const statusBadge = getStatusBadge('Resubmission Required');
                    document.getElementById('applicantStatus').innerHTML = statusBadge;
                    
                    // Update action buttons immediately
                    updateActionButtons('Resubmission Required');
                    
                    // Refresh applicant details
                    setTimeout(() => {
                        viewApplicantDetails(currentApplicantId);
                        loadApplicants();
                    }, 500); // Small delay to ensure database update is complete
                } else {
                    showToast('Error requesting resubmission: ' + result.error, 'error');
                }
            } catch (error) {
                console.error('Error requesting resubmission:', error);
                showToast('Failed to request resubmission', 'error');
            }
        });
    });
    
    // Reject Application Form
    document.getElementById('rejectForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Prevent duplicate submissions
        preventDuplicateSubmission(async () => {
            const formData = new FormData();
            formData.append('action', 'reject_application');
            formData.append('applicant_id', currentApplicantId);
            formData.append('rejection_reason', document.getElementById('rejectionReason').value);
            
            try {
            const response = await fetch('process_applicant_action.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                closeRejectModal();
                
                // Immediately update the status badge to show "Rejected"
                const rejectedBadge = '<span class="px-3 py-2 text-sm font-bold rounded-full bg-red-100 text-red-800 border-2 border-red-300 inline-flex items-center"><i class="fas fa-times-circle mr-1"></i>Rejected</span>';
                
                // Update header status
                const statusContainer = document.getElementById('applicantStatus');
                if (statusContainer) {
                    statusContainer.innerHTML = rejectedBadge;
                    console.log('Header status updated to: Rejected');
                }
                
                // Update Actions section status
                const actionStatusBadge = document.getElementById('actionStatusBadge');
                if (actionStatusBadge) {
                    actionStatusBadge.innerHTML = rejectedBadge;
                    console.log('Actions status updated to: Rejected');
                }
                
                // Update action buttons immediately - hide all buttons for rejected status
                updateActionButtons('Rejected');
                
                // Show success message
                showToast('Application rejected successfully!', 'success');
                
                // Refresh applicant details and applicants list
                setTimeout(() => {
                    viewApplicantDetails(currentApplicantId);
                    loadApplicants(); // This will update counts and refresh the filtered view
                }, 500); // Small delay to ensure database update is complete
            } else {
                showToast('Error rejecting application: ' + result.error, 'error');
            }
        } catch (error) {
            console.error('Error rejecting application:', error);
            showToast('Failed to reject application', 'error');
        }
        });
    });
    
    // Initially Hire Applicant Form
    document.getElementById('hireForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Prevent duplicate submissions
        preventDuplicateSubmission(async () => {
            const formData = new FormData();
            formData.append('action', 'mark_initially_hired');
            formData.append('applicant_id', currentApplicantId);
            formData.append('initially_hired_notes', document.getElementById('hireNotes').value);
            
            try {
            const response = await fetch('process_applicant_action.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                closeHireModal();
                
                // Immediately update the status badge to show "Initially Hired"
                const hiredBadge = '<span class="px-3 py-2 text-sm font-bold rounded-full bg-green-100 text-green-800 border-2 border-green-300 inline-flex items-center"><i class="fas fa-user-check mr-1"></i>Initially Hired</span>';
                
                // Update header status
                const statusContainer = document.getElementById('applicantStatus');
                if (statusContainer) {
                    statusContainer.innerHTML = hiredBadge;
                    console.log('Header status updated to: Initially Hired');
                }
                
                // Update Actions section status
                const actionStatusBadge = document.getElementById('actionStatusBadge');
                if (actionStatusBadge) {
                    actionStatusBadge.innerHTML = hiredBadge;
                    console.log('Actions status updated to: Initially Hired');
                }
                
                // Update action buttons immediately - hide all buttons for initially hired status
                updateActionButtons('Initially Hired');
                
                // Show success message
                showToast('Applicant marked as initially hired successfully!', 'success');
                
                // Refresh applicant details and applicants list
                setTimeout(() => {
                    viewApplicantDetails(currentApplicantId);
                    loadApplicants();
                }, 500); // Small delay to ensure database update is complete
            } else {
                showToast('Error marking as initially hired: ' + result.error, 'error');
            }
        } catch (error) {
            console.error('Error marking as initially hired:', error);
            showToast('Failed to mark applicant as initially hired', 'error');
        }
        });
    });
    
    // Permanent Hire Form
    document.getElementById('permanentHireForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Prevent duplicate submissions
        preventDuplicateSubmission(async () => {
            const formData = new FormData();
            formData.append('action', 'mark_permanently_hired');
            formData.append('applicant_id', currentApplicantId);
            formData.append('hired_notes', document.getElementById('permanentHireNotes').value);
            
            try {
            const response = await fetch('process_applicant_action.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                closePermanentHireModal();
                
                // Immediately update the status badge to show "Hired"
                const hiredBadge = '<span class="px-3 py-2 text-sm font-bold rounded-full bg-green-100 text-green-800 border-2 border-green-300 inline-flex items-center"><i class="fas fa-user-tie mr-1"></i>Hired</span>';
                
                // Update header status
                const statusContainer = document.getElementById('applicantStatus');
                if (statusContainer) {
                    statusContainer.innerHTML = hiredBadge;
                }
                
                // Replace action buttons with success message
                const actionButtonsContainer = document.getElementById('actionButtons');
                if (actionButtonsContainer) {
                    actionButtonsContainer.innerHTML = `
                        <div class="text-center py-8">
                            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                                <i class="fas fa-user-tie text-3xl text-green-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-green-900 mb-2">Permanently Hired</h3>
                            <p class="text-sm text-green-700">This applicant has been permanently hired and is now a regular employee.</p>
                        </div>
                    `;
                }
                
                // Show success message
                showToast('Applicant permanently hired successfully!', 'success');
                
                // Refresh applicant details and applicants list
                setTimeout(() => {
                    viewApplicantDetails(currentApplicantId);
                    loadApplicants();
                }, 500);
            } else {
                showToast('Error marking as permanently hired: ' + result.error, 'error');
            }
        } catch (error) {
            console.error('Error marking as permanently hired:', error);
            showToast('Failed to mark applicant as permanently hired', 'error');
        }
        });
    });
    
    // Approve Interview Form
    document.getElementById('approveInterviewForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Prevent duplicate submissions
        preventDuplicateSubmission(async () => {
            const formData = new FormData();
            formData.append('action', 'approve_interview');
            formData.append('applicant_id', currentApplicantId);
            formData.append('interview_notes', document.getElementById('approveInterviewNotes').value);
            
            try {
            const response = await fetch('process_applicant_action.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                closeApproveInterviewModal();
                
                // Immediately update the status badge to show "Interview Passed"
                const interviewBadge = '<span class="px-3 py-2 text-sm font-bold rounded-full bg-teal-100 text-teal-800 border-2 border-teal-300 inline-flex items-center"><i class="fas fa-user-check mr-1"></i>Interview Passed</span>';
                
                // Update header status
                const statusContainer = document.getElementById('applicantStatus');
                if (statusContainer) {
                    statusContainer.innerHTML = interviewBadge;
                }
                
                // Update action buttons immediately
                updateActionButtons('Interview Passed');
                
                // Show success message
                showToast('Interview approved successfully! You can now schedule the demo teaching.', 'success');
                
                // Refresh applicant details and applicants list
                setTimeout(() => {
                    viewApplicantDetails(currentApplicantId);
                    loadApplicants();
                }, 500);
            } else {
                showToast('Error approving interview: ' + result.error, 'error');
            }
        } catch (error) {
            console.error('Error approving interview:', error);
            showToast('Failed to approve interview', 'error');
        }
        });
    });
    
    // Approve Demo Form
    document.getElementById('approveDemoForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Prevent duplicate submissions
        preventDuplicateSubmission(async () => {
            const formData = new FormData();
            formData.append('action', 'approve_demo');
            formData.append('applicant_id', currentApplicantId);
            formData.append('demo_notes', document.getElementById('approveDemoNotes').value);
            
            try {
            const response = await fetch('process_applicant_action.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                closeApproveDemoModal();
                
                // Immediately update the status badge to show "Demo Passed"
                const demoBadge = '<span class="px-3 py-2 text-sm font-bold rounded-full bg-emerald-100 text-emerald-800 border-2 border-emerald-300 inline-flex items-center"><i class="fas fa-check-double mr-1"></i>Demo Passed</span>';
                
                // Update header status
                const statusContainer = document.getElementById('applicantStatus');
                if (statusContainer) {
                    statusContainer.innerHTML = demoBadge;
                }
                
                // Update action buttons immediately
                updateActionButtons('Demo Passed');
                
                // Show success message
                showToast('Demo approved successfully! You can now proceed with hiring.', 'success');
                
                // Refresh applicant details and applicants list
                setTimeout(() => {
                    viewApplicantDetails(currentApplicantId);
                    loadApplicants();
                }, 500);
            } else {
                showToast('Error approving demo: ' + result.error, 'error');
            }
        } catch (error) {
            console.error('Error approving demo:', error);
            showToast('Failed to approve demo', 'error');
        }
        });
    });
    
    // Schedule Demo Form
    const demoForm = document.getElementById('scheduleDemoForm');
    if (demoForm) {
        demoForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Prevent duplicate submissions
            preventDuplicateSubmission(async () => {
                const formData = new FormData();
                formData.append('action', 'schedule_demo');
                formData.append('applicant_id', currentApplicantId);
                formData.append('demo_date', document.getElementById('demoDate').value);
                formData.append('demo_time', document.getElementById('demoTime').value);
                formData.append('demo_notes', document.getElementById('demoNotes').value);
                
                try {
                const response = await fetch('process_applicant_action.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Demo scheduled successfully!', 'success');
                    closeDemoModal();
                    
                    // Update status badge
                    const statusBadge = getStatusBadge('Demo Scheduled');
                    document.getElementById('applicantStatus').innerHTML = statusBadge;
                    
                    // Update action buttons
                    updateActionButtons('Demo Scheduled');
                    
                    // Refresh applicant details
                    setTimeout(() => {
                        viewApplicantDetails(currentApplicantId);
                        loadApplicants();
                    }, 500);
                } else {
                    showToast('Error scheduling demo: ' + result.error, 'error');
                }
            } catch (error) {
                console.error('Error scheduling demo:', error);
                showToast('Failed to schedule demo', 'error');
            }
            });
        });
    }
    
    // Mark Initially Hired Form
    const initialHireForm = document.getElementById('initialHireForm');
    if (initialHireForm) {
        initialHireForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Prevent duplicate submissions
            preventDuplicateSubmission(async () => {
                const formData = new FormData();
                formData.append('action', 'mark_initially_hired');
                formData.append('applicant_id', currentApplicantId);
                formData.append('initially_hired_notes', document.getElementById('initialHireNotes').value);
                
                try {
                    const response = await fetch('process_applicant_action.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast('Applicant marked as initially hired successfully!', 'success');
                        closeInitialHireModal();
                        
                        // Update status badge
                        const statusBadge = getStatusBadge('Initially Hired');
                        document.getElementById('applicantStatus').innerHTML = statusBadge;
                        
                        // Update action buttons
                        updateActionButtons('Initially Hired');
                        
                        // Refresh applicant details
                        setTimeout(() => {
                            viewApplicantDetails(currentApplicantId);
                            loadApplicants();
                        }, 500);
                    } else {
                        showToast('Error marking as initially hired: ' + result.error, 'error');
                    }
                } catch (error) {
                    console.error('Error marking as initially hired:', error);
                    showToast('Failed to mark as initially hired', 'error');
                }
            });
        });
    }
    
    // Reschedule Interview Form
    document.getElementById('rescheduleInterviewForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Validate date and time
        const dateValue = document.getElementById('rescheduleInterviewDate').value;
        const timeValue = document.getElementById('rescheduleInterviewTime').value;
        const reasonValue = document.getElementById('rescheduleInterviewNotes').value;
        
        if (!dateValue || !timeValue) {
            showToast('Please select both date and time', 'warning');
            return;
        }
        
        if (!reasonValue || reasonValue.trim() === '') {
            showToast('Please provide a reason for rescheduling', 'warning');
            return;
        }
        
        // Check if the selected date/time is in the past
        const selectedDateTime = new Date(dateValue + 'T' + timeValue);
        const now = new Date();
        
        if (selectedDateTime < now) {
            showToast('Please select a future date and time for the new schedule.', 'warning');
            return;
        }
        
        // Check if time is within business hours (8:00 AM - 4:00 PM)
        const timeHours = parseInt(timeValue.split(':')[0]);
        const timeMinutes = parseInt(timeValue.split(':')[1]);
        const timeInMinutes = timeHours * 60 + timeMinutes;
        const minTime = 8 * 60; // 8:00 AM
        const maxTime = 16 * 60; // 4:00 PM
        
        if (timeInMinutes < minTime || timeInMinutes > maxTime) {
            showToast('Please select a time between 8:00 AM and 4:00 PM.', 'warning');
            return;
        }
        
        // Prevent duplicate submissions
        preventDuplicateSubmission(async () => {
            const formData = new FormData();
            formData.append('action', 'reschedule_interview');
            formData.append('applicant_id', currentApplicantId);
            formData.append('interview_date', dateValue);
            formData.append('interview_time', timeValue);
            formData.append('interview_notes', reasonValue);
            
            try {
                const response = await fetch('process_applicant_action.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Interview rescheduled successfully!', 'success');
                    closeRescheduleInterviewModal();
                    
                    // Update status badge - status remains "Interview Scheduled"
                    const statusBadge = getStatusBadge('Interview Scheduled');
                    document.getElementById('applicantStatus').innerHTML = statusBadge;
                    
                    // Update action buttons
                    updateActionButtons('Interview Scheduled');
                    
                    // Refresh applicant details
                    setTimeout(() => {
                        viewApplicantDetails(currentApplicantId);
                        loadApplicants();
                    }, 500);
                } else {
                    // Check if it's a validation message
                    if (result.error && (result.error.includes('Please select') || result.error.includes('8:00 AM and 4:00 PM'))) {
                        showToast(result.error, 'warning');
                    } else {
                        showToast(result.error, 'error');
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Failed to reschedule interview', 'error');
            }
        });
    });
    
    // Reschedule Demo Form
    document.getElementById('rescheduleDemoForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Validate date and time
        const dateValue = document.getElementById('rescheduleDemoDate').value;
        const timeValue = document.getElementById('rescheduleDemoTime').value;
        const reasonValue = document.getElementById('rescheduleDemoNotes').value;
        
        if (!dateValue || !timeValue) {
            showToast('Please select both date and time', 'warning');
            return;
        }
        
        if (!reasonValue || reasonValue.trim() === '') {
            showToast('Please provide a reason for rescheduling', 'warning');
            return;
        }
        
        // Check if the selected date/time is in the past
        const selectedDateTime = new Date(dateValue + 'T' + timeValue);
        const now = new Date();
        
        if (selectedDateTime < now) {
            showToast('Please select a future date and time for the new schedule.', 'warning');
            return;
        }
        
        // Check if time is within business hours (8:00 AM - 4:00 PM)
        const timeHours = parseInt(timeValue.split(':')[0]);
        const timeMinutes = parseInt(timeValue.split(':')[1]);
        const timeInMinutes = timeHours * 60 + timeMinutes;
        const minTime = 8 * 60; // 8:00 AM
        const maxTime = 16 * 60; // 4:00 PM
        
        if (timeInMinutes < minTime || timeInMinutes > maxTime) {
            showToast('Please select a time between 8:00 AM and 4:00 PM.', 'warning');
            return;
        }
        
        // Prevent duplicate submissions
        preventDuplicateSubmission(async () => {
            const formData = new FormData();
            formData.append('action', 'reschedule_demo');
            formData.append('applicant_id', currentApplicantId);
            formData.append('demo_date', dateValue);
            formData.append('demo_time', timeValue);
            formData.append('demo_notes', reasonValue);
            
            try {
                const response = await fetch('process_applicant_action.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Demo teaching rescheduled successfully!', 'success');
                    closeRescheduleDemoModal();
                    
                    // Update status badge - status remains "Demo Scheduled"
                    const statusBadge = getStatusBadge('Demo Scheduled');
                    document.getElementById('applicantStatus').innerHTML = statusBadge;
                    
                    // Update action buttons
                    updateActionButtons('Demo Scheduled');
                    
                    // Refresh applicant details
                    setTimeout(() => {
                        viewApplicantDetails(currentApplicantId);
                        loadApplicants();
                    }, 500);
                } else {
                    // Check if it's a validation message
                    if (result.error && (result.error.includes('Please select') || result.error.includes('8:00 AM and 4:00 PM'))) {
                        showToast(result.error, 'warning');
                    } else {
                        showToast(result.error, 'error');
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Failed to reschedule demo', 'error');
            }
        });
    });
    
    // Close modals when clicking outside
    document.getElementById('scheduleModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeScheduleModal();
        }
    });
    
    document.getElementById('resubmitModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeResubmitModal();
        }
    });
    
    document.getElementById('rejectModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRejectModal();
        }
    });
    
    document.getElementById('hireModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeHireModal();
        }
    });
    
    document.getElementById('rescheduleInterviewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRescheduleInterviewModal();
        }
    });
    
    document.getElementById('rescheduleDemoModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRescheduleDemoModal();
        }
    });
});

// Document viewer function
function viewDocument(filePath, documentName, isImage) {
    const modal = document.getElementById('documentViewerModal');
    const modalTitle = document.getElementById('documentModalTitle');
    const modalContent = document.getElementById('documentModalContent');
    
    modalTitle.textContent = documentName;
    
    if (isImage === 'true' || isImage === true) {
        modalContent.innerHTML = `
            <img src="${filePath}" alt="${documentName}" class="max-w-full max-h-96 mx-auto rounded">
        `;
    } else {
        modalContent.innerHTML = `
            <div class="text-center">
                <i class="fas fa-file-alt text-6xl text-gray-400 mb-4"></i>
                <p class="text-gray-600 mb-4">Click below to open the document in a new tab</p>
                <a href="${filePath}" target="_blank" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-external-link-alt mr-2"></i>Open Document
                </a>
            </div>
        `;
    }
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeDocumentViewer() {
    const modal = document.getElementById('documentViewerModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// Initialize admin panel when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin panel initializing...');
    
    // Show dashboard by default
    showSection('dashboard');
    
    // Auto-refresh dashboard every 30 seconds when on dashboard page
    if (window.location.pathname.includes('admin') && !window.location.pathname.includes('user')) {
        setInterval(loadDashboardData, 30000);
    }
});

// Global variables for applicant filtering
let currentStatusFilter = 'all';
let allApplicantsData = [];

// Filter applicants by status
function filterApplicantsByStatus(status) {
    currentStatusFilter = status;
    
    // Update dropdown selection
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        statusFilter.value = status;
    }
    
    // Filter and display applicants
    displayFilteredApplicants(status);
}

// Display filtered applicants
function displayFilteredApplicants(status) {
    const tbody = document.getElementById('applicantsTableBody');
    
    if (!allApplicantsData || allApplicantsData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-gray-500">No applicants found</td></tr>';
        return;
    }
    
    let filteredApplicants = allApplicantsData;
    
    if (status !== 'all') {
        filteredApplicants = allApplicantsData.filter(applicant => applicant.status === status);
    }
    
    if (filteredApplicants.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-gray-500">No ${status === 'all' ? '' : status.toLowerCase()} applicants found</td></tr>`;
        return;
    }
    
    tbody.innerHTML = filteredApplicants.map(applicant => `
        <tr class="hover:bg-gray-50 cursor-pointer" onclick="viewApplicantDetails(${applicant.id})">
            <td class="px-6 py-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                        <span class="text-blue-600 font-semibold text-sm">${getInitials(applicant.full_name)}</span>
                    </div>
                    <div>
                        <div class="font-medium text-gray-900">${applicant.full_name}</div>
                        <div class="text-gray-500 text-sm">${applicant.applicant_email}</div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 text-gray-900">${applicant.position}</td>
            <td class="px-6 py-4 text-gray-500">${formatDate(applicant.applied_date)}</td>
            <td class="px-6 py-4">
                <span class="px-2 py-1 text-xs font-semibold rounded-full ${getApplicantStatusColor(applicant.status)}">
                    ${applicant.status}
                </span>
            </td>
            <td class="px-6 py-4">
                <button onclick="event.stopPropagation(); viewApplicantDetails(${applicant.id})" 
                        class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                    View Details
                </button>
            </td>
        </tr>
    `).join('');
}

// Update status counts in filter display
function updateStatusCounts() {
    if (!allApplicantsData) return;
    
    const counts = {
        all: allApplicantsData.length,
        pending: allApplicantsData.filter(a => a.status === 'Pending').length,
        interview: allApplicantsData.filter(a => a.status === 'Interview Scheduled').length,
        resubmission: allApplicantsData.filter(a => a.status === 'Resubmission Required').length,
        rejected: allApplicantsData.filter(a => a.status === 'Rejected').length,
        hired: allApplicantsData.filter(a => a.status === 'Hired').length
    };
    
    // Update count displays
    Object.keys(counts).forEach(key => {
        const countElement = document.getElementById(`count-${key}`);
        if (countElement) {
            countElement.textContent = counts[key];
        }
    });
    
    console.log('Status counts updated:', counts);
}

// New Edit Job Modal Functions
function closeNewEditJobModal() {
    document.getElementById('newEditJobModal').classList.add('hidden');
}

// Secretary Modal Functions
function closeSecretaryEditModal() {
    document.getElementById('editSecretaryJobModal').classList.add('hidden');
}

async function submitSecretaryEditJob(event) {
    event.preventDefault();
    console.log('Submit secretary edit job function called');
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Show loading state
    const submitBtn = document.querySelector('#editSecretaryJobForm button[type="submit"]');
    if (!submitBtn) {
        console.error('Secretary submit button not found!');
        return;
    }
    
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
    submitBtn.disabled = true;
    
    // Convert FormData to JSON object
    const jobData = {
        id: formData.get('job_id'),
        job_title: formData.get('job_title'),
        department_role: formData.get('department_role'),
        job_type: formData.get('job_type'),
        locations: formData.get('locations'),
        salary_range: formData.get('salary_range'),
        application_deadline: formData.get('application_deadline'),
        job_description: formData.get('job_description'),
        job_requirements: formData.get('job_requirements'),
        education: formData.get('education'),
        experience: formData.get('experience'),
        training: formData.get('training'),
        eligibility: formData.get('eligibility'),
        duties: formData.get('duties'),
        competency: formData.get('competency')
    };
    
    try {
        const response = await fetch('update_job.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(jobData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Secretary position updated successfully!', 'success');
            closeSecretaryEditModal();
            loadJobs(); // Refresh the jobs list
        } else {
            showToast('Error updating secretary position: ' + (result.message || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error updating secretary position:', error);
        showToast('Failed to update secretary position. Please try again.', 'error');
    } finally {
        // Reset button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

async function submitEditJob(event) {
    event.preventDefault();
    console.log('Submit edit job function called');
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Show loading state
    const submitBtn = document.querySelector('#newEditJobForm button[type="submit"]');
    if (!submitBtn) {
        console.error('Submit button not found!');
        return;
    }
    
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
    submitBtn.disabled = true;
    
    // Convert FormData to JSON object
    const jobData = {
        id: formData.get('job_id'),
        job_title: formData.get('job_title'),
        department_role: formData.get('department_role'),
        job_type: formData.get('job_type'),
        locations: formData.get('locations'),
        salary_range: formData.get('salary_range'),
        application_deadline: formData.get('application_deadline'),
        job_description: formData.get('job_description'),
        job_requirements: formData.get('job_requirements'),
        education: formData.get('education'),
        experience: formData.get('experience'),
        training: formData.get('training'),
        eligibility: formData.get('eligibility'),
        duties: formData.get('duties'),
        competency: formData.get('competency')
    };
    
    try {
        const response = await fetch('update_job.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(jobData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Job updated successfully!', 'success');
            closeNewEditJobModal();
            loadJobs(); // Refresh the jobs list
        } else {
            showToast('Error updating job: ' + (result.message || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error updating job:', error);
        showToast('Failed to update job. Please try again.', 'error');
    } finally {
        // Reset button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

// Legacy Update Job function (keeping for compatibility)
async function updateJob(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Convert FormData to JSON object
    const jobData = {
        id: formData.get('id'),
        job_title: formData.get('title'),
        department_role: formData.get('department'),
        job_type: formData.get('type'),
        locations: formData.get('location'),
        salary_range: formData.get('salary'),
        application_deadline: formData.get('deadline'),
        job_description: formData.get('description'),
        job_requirements: formData.get('requirements'),
        education: formData.get('education'),
        experience: formData.get('experience'),
        training: formData.get('training'),
        eligibility: formData.get('eligibility'),
        duties: formData.get('duties'),
        competency: formData.get('competency')
    };
    
    try {
        const response = await fetch('update_job.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(jobData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Job updated successfully!', 'success');
            closeeditJobModal();
            loadJobs(); // Refresh the jobs list
        } else {
            showToast('Error updating job: ' + (result.message || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error updating job:', error);
        showToast('Failed to update job. Please try again.', 'error');
    }
}


