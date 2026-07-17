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
        
        // Store all jobs for filtering
        allJobs = [...jobs];
        filteredJobs = [...jobs];
        
        // Display jobs using the filter display function
        displayFilteredJobs();
        
    } catch (error) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-red-500">Failed to load jobs.</td></tr>';
        console.error(error);
    }
}

// Copy job link for sharing
function copyJobLink(jobId, jobTitle) {
    console.log('Copying job link for:', jobId, jobTitle);
    
    // Generate the shareable link using current location path
    // Get the base path from current URL (e.g., /FinalResearch - Copy or /FinalResearch%20-%20Copy)
    const currentPath = window.location.pathname;
    const basePath = currentPath.substring(0, currentPath.lastIndexOf('/admin'));
    const baseUrl = window.location.origin + basePath;
    const jobLink = `${baseUrl}/user/user.php?job_id=${jobId}`;
    
    console.log('Current path:', currentPath);
    console.log('Base path:', basePath);
    console.log('Generated link:', jobLink);
    
    // Copy to clipboard
    navigator.clipboard.writeText(jobLink).then(() => {
        console.log('Link copied successfully');
        showToast(`Link copied! Share "${jobTitle}" on social media`, 'success');
    }).catch(err => {
        console.log('Clipboard API failed, using fallback:', err);
        // Fallback for older browsers
        const tempInput = document.createElement('input');
        tempInput.value = jobLink;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        showToast(`Link copied! Share "${jobTitle}" on social media`, 'success');
    });
}

// Global variables for job filtering
let allJobs = []; // Store all jobs
let filteredJobs = []; // Store filtered results

// Filter jobs based on search and filters
function filterJobs() {
    const searchTerm = document.getElementById('jobSearchInput').value.toLowerCase();
    const statusFilter = document.getElementById('jobStatusFilter').value;
    
    // Get more filters values (if set)
    const departmentFilter = document.getElementById('jobDepartmentFilter')?.value || 'all';
    const typeFilter = document.getElementById('jobTypeFilter')?.value || 'all';
    const deadlineFrom = document.getElementById('jobDeadlineFrom')?.value || '';
    const deadlineTo = document.getElementById('jobDeadlineTo')?.value || '';
    
    // Filter jobs
    filteredJobs = allJobs.filter(job => {
        // Calculate status
        const today = new Date();
        const deadline = new Date(job.application_deadline);
        const jobStatus = today > deadline ? "Closed" : "Active";
        
        // Search filter (job title, location, department)
        const matchesSearch = !searchTerm || 
            job.job_title.toLowerCase().includes(searchTerm) ||
            job.locations.toLowerCase().includes(searchTerm) ||
            job.department_role.toLowerCase().includes(searchTerm);
        
        // Status filter
        const matchesStatus = statusFilter === 'all' || 
            jobStatus.toLowerCase() === statusFilter.toLowerCase();
        
        // Department filter
        const matchesDepartment = departmentFilter === 'all' || 
            job.department_role === departmentFilter;
        
        // Job type filter
        const matchesType = typeFilter === 'all' || 
            job.job_type === typeFilter;
        
        // Deadline range filter
        let matchesDeadlineRange = true;
        if (deadlineFrom && deadline < new Date(deadlineFrom)) {
            matchesDeadlineRange = false;
        }
        if (deadlineTo && deadline > new Date(deadlineTo)) {
            matchesDeadlineRange = false;
        }
        
        return matchesSearch && matchesStatus && matchesDepartment && matchesType && matchesDeadlineRange;
    });
    
    // Display filtered results
    displayFilteredJobs();
}

// Display filtered jobs
function displayFilteredJobs() {
    const tbody = document.getElementById('jobsTableBody');
    tbody.innerHTML = '';
    
    if (filteredJobs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-gray-500">No jobs found matching your filters.</td></tr>';
        return;
    }
    
    filteredJobs.forEach(job => {
        // Compute status
        const today = new Date();
        const deadline = new Date(job.application_deadline);
        const status = today > deadline ? "Closed" : "Active";
        
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
                    <button class="share-job-btn text-gray-400 hover:text-green-600" 
                            data-job-id="${job.id}" 
                            data-job-title="${job.job_title.replace(/"/g, '&quot;')}" 
                            title="Copy Link to Share">
                        <i class="fas fa-share-alt"></i>
                    </button>
                    <button onclick="deleteJob(${job.id})" class="text-gray-400 hover:text-red-600" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
    
    // Add event delegation for share buttons
    const oldListener = tbody._shareClickListener;
    if (oldListener) {
        tbody.removeEventListener('click', oldListener);
    }
    
    const shareClickListener = function(e) {
        const shareBtn = e.target.closest('.share-job-btn');
        if (shareBtn) {
            e.preventDefault();
            e.stopPropagation();
            const jobId = shareBtn.dataset.jobId;
            const jobTitle = shareBtn.dataset.jobTitle;
            copyJobLink(jobId, jobTitle);
        }
    };
    
    tbody.addEventListener('click', shareClickListener);
    tbody._shareClickListener = shareClickListener;
}

// More Filters Modal Functions
function openMoreFiltersModal() {
    document.getElementById('moreFiltersModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeMoreFiltersModal() {
    document.getElementById('moreFiltersModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function applyMoreFilters() {
    filterJobs();
    closeMoreFiltersModal();
    showToast('Filters applied successfully', 'success');
}

function clearJobFilters() {
    // Clear all filter inputs
    document.getElementById('jobSearchInput').value = '';
    document.getElementById('jobStatusFilter').value = 'all';
    document.getElementById('jobDepartmentFilter').value = 'all';
    document.getElementById('jobTypeFilter').value = 'all';
    document.getElementById('jobDeadlineFrom').value = '';
    document.getElementById('jobDeadlineTo').value = '';
    
    // Reset to show all jobs
    filteredJobs = [...allJobs];
    displayFilteredJobs();
    closeMoreFiltersModal();
    showToast('All filters cleared', 'success');
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
        
        // Clear all search inputs and filters when changing sections
        const searchInputs = [
            // Job Postings filters
            { id: 'jobSearchInput', type: 'text', callback: 'filterJobs' },
            { id: 'jobStatusFilter', type: 'select', defaultValue: 'all', callback: 'filterJobs' },
            { id: 'jobDepartmentFilter', type: 'select', defaultValue: 'all', callback: 'filterJobs' },
            { id: 'jobTypeFilter', type: 'select', defaultValue: 'all', callback: 'filterJobs' },
            { id: 'jobDeadlineFrom', type: 'date', callback: 'filterJobs' },
            { id: 'jobDeadlineTo', type: 'date', callback: 'filterJobs' },
            
            // Applicants filters
            { id: 'nameSearch', type: 'text', callback: 'applyAllFilters' },
            { id: 'statusFilter', type: 'select', defaultValue: 'all', callback: 'applyAllFilters' },
            { id: 'fromDate', type: 'date', callback: 'applyAllFilters' },
            { id: 'toDate', type: 'date', callback: 'applyAllFilters' },
            
            // Archive search
            { id: 'archiveSearch', type: 'text', callback: null },
            
            // Users search
            { id: 'userSearch', type: 'text', callback: 'filterUsers' }
        ];
        
        let shouldTriggerCallbacks = {};
        
        searchInputs.forEach(inputConfig => {
            const input = document.getElementById(inputConfig.id);
            if (input) {
                const hasValue = input.value && input.value !== (inputConfig.defaultValue || '');
                
                if (hasValue) {
                    // Reset to default value or empty
                    if (inputConfig.type === 'select' && inputConfig.defaultValue) {
                        input.value = inputConfig.defaultValue;
                    } else {
                        input.value = '';
                    }
                    console.log(`Cleared input: ${inputConfig.id}`);
                    
                    // Track which callbacks need to be triggered
                    if (inputConfig.callback) {
                        shouldTriggerCallbacks[inputConfig.callback] = true;
                    }
                }
            }
        });
        
        // Trigger filter callbacks once per unique function (avoid duplicate calls)
        Object.keys(shouldTriggerCallbacks).forEach(callbackName => {
            if (typeof window[callbackName] === 'function') {
                window[callbackName]();
            }
        });
        
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
// Load and update dynamic applicants stats
async function loadApplicantsStats() {
    try {
        const response = await fetch('api/get_applicants_stats.php');
        if (!response.ok) throw new Error('Network response was not ok');

        const stats = await response.json();
        
        // Update stat cards with data-stat attributes
        document.querySelectorAll('[data-stat]').forEach(element => {
            const statKey = element.getAttribute('data-stat');
            if (stats[statKey] !== undefined) {
                element.textContent = stats[statKey];
            }
        });
        
        console.log('Applicants stats updated:', stats);
        
    } catch (error) {
        console.error('Error loading applicants stats:', error);
    }
}

async function loadApplicants() {
    const tbody = document.getElementById('applicantsTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4">Loading...</td></tr>';

    try {
        // Load dynamic stats
        await loadApplicantsStats();
        
        const response = await fetch('gets_applicants.php');
        if (!response.ok) throw new Error('Network response was not ok');

        const applicants = await response.json();
        
        // Store applicants data globally for filtering
        allApplicantsData = applicants;
        
        // Update status counts
        updateStatusCounts();
        
        // Display applicants with current filters
        displayFilteredApplicants();
        
    } catch (error) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-red-500">Failed to load applicants.</td></tr>';
        console.error('Error loading applicants:', error);
    }
}

// Global variable to store all archived applicants for search
let allArchivedData = [];
let currentArchiveFilter = 'all';

// Load Archive (Rejected and Cancelled Applicants)
async function loadArchive() {
    const tbody = document.getElementById('archiveTableBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">Loading archived applicants...</td></tr>';

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
                <td colspan="7" class="text-center py-8 text-gray-500">
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
        
        const archiveDate = applicant.rejected_date ? 
            new Date(applicant.rejected_date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            }) : 'N/A';
        
        // Determine status badge color and text
        const isRejected = applicant.workflow_stage === 'rejected';
        const statusBadge = isRejected
            ? '<span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Rejected</span>'
            : '<span class="px-2 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-800">Cancelled</span>';
        
        // Create profile picture HTML
        const profilePictureHTML = applicant.profile_picture 
            ? `<img src="../user/uploads/profile_pictures/${applicant.profile_picture}" alt="Profile" class="w-10 h-10 rounded-full object-cover mr-3">`
            : `<div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                 <span class="text-blue-600 font-semibold text-sm">${getInitials(applicant.full_name)}</span>
               </div>`;
        
        row.innerHTML = `
            <td class="px-6 py-4">
                <div class="flex items-center">
                    ${profilePictureHTML}
                    <div>
                        <div class="font-medium text-gray-900">${applicant.full_name}</div>
                        <div class="text-sm text-gray-500">${applicant.applicant_email}</div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 text-sm text-gray-900">${applicant.position}</td>
            <td class="px-6 py-4">${statusBadge}</td>
            <td class="px-6 py-4 text-sm text-gray-500">${appliedDate}</td>
            <td class="px-6 py-4 text-sm text-gray-500">${archiveDate}</td>
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

// Filter archive by status
function filterArchiveByStatus(status) {
    currentArchiveFilter = status;
    applyArchiveFilters();
}

// Search archived applicants
function searchArchive(searchTerm) {
    applyArchiveFilters(searchTerm);
}

// Apply both search and status filters
function applyArchiveFilters(searchTerm = null) {
    // Get current search term if not provided
    if (searchTerm === null) {
        const searchInput = document.getElementById('archiveSearch');
        searchTerm = searchInput ? searchInput.value : '';
    }
    
    let filtered = allArchivedData;
    
    // Apply status filter
    if (currentArchiveFilter !== 'all') {
        filtered = filtered.filter(applicant => 
            applicant.workflow_stage === currentArchiveFilter
        );
    }
    
    // Apply search filter
    if (searchTerm) {
        const term = searchTerm.toLowerCase();
        filtered = filtered.filter(applicant => {
            const name = applicant.full_name.toLowerCase();
            const email = applicant.applicant_email.toLowerCase();
            const position = applicant.position.toLowerCase();
            const reason = (applicant.rejection_reason || '').toLowerCase();
            
            return name.includes(term) || email.includes(term) || position.includes(term) || reason.includes(term);
        });
    }
    
    displayArchivedApplicants(filtered);
}

// View archived applicant details (redirect to applicant details)
function viewArchivedApplicant(applicantId) {
    viewApplicant(applicantId);
}

// Global variable to store all users
let allUsers = [];

// Load Users from Database
async function loadUsers() {
    const tbody = document.getElementById('usersTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4">Loading users...</td></tr>';
    
    try {
        const response = await fetch('api/users.php');
        allUsers = await response.json();
        
        if (!Array.isArray(allUsers) || allUsers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-500">No users found</td></tr>';
            updateUserResultsCount(0, 0);
            return;
        }
        
        // Initial display of all users
        displayUsers(allUsers);
        
    } catch (error) {
        console.error('Error loading users:', error);
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-red-500">Failed to load users</td></tr>';
        updateUserResultsCount(0, 0);
    }
}

// Display users in the table
function displayUsers(usersToDisplay) {
    const tbody = document.getElementById('usersTableBody');
    
    if (!Array.isArray(usersToDisplay) || usersToDisplay.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-500">No users found matching the filters</td></tr>';
        updateUserResultsCount(0, allUsers.length);
        return;
    }
    
    tbody.innerHTML = '';
    
    usersToDisplay.forEach(user => {
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
                            ${displayRoleName(user.role)}
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
                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });
    
    updateUserResultsCount(usersToDisplay.length, allUsers.length);
}

// Filter users based on search and filters
function filterUsers() {
    const searchTerm = document.getElementById('userSearch')?.value.toLowerCase() || '';
    const roleFilter = document.getElementById('roleFilter')?.value || '';
    const statusFilter = document.getElementById('statusFilter')?.value || '';
    
    let filtered = allUsers.filter(user => {
        // Search filter (name or email)
        const matchesSearch = user.name.toLowerCase().includes(searchTerm) || 
                            user.email.toLowerCase().includes(searchTerm);
        
        // Role filter
        const matchesRole = !roleFilter || user.role === roleFilter;
        
        // Status filter
        const matchesStatus = !statusFilter || user.status === statusFilter;
        
        return matchesSearch && matchesRole && matchesStatus;
    });
    
    displayUsers(filtered);
}

// Update results count display
function updateUserResultsCount(showing, total) {
    const countElement = document.getElementById('userResultsCount');
    if (countElement) {
        if (showing === total) {
            countElement.textContent = `Showing all ${total} user${total !== 1 ? 's' : ''}`;
        } else {
            countElement.textContent = `Showing ${showing} of ${total} user${total !== 1 ? 's' : ''}`;
        }
    }
}

// Clear all filters
function clearUserFilters() {
    const searchInput = document.getElementById('userSearch');
    const roleFilter = document.getElementById('roleFilter');
    const statusFilter = document.getElementById('statusFilter');
    
    if (searchInput) searchInput.value = '';
    if (roleFilter) roleFilter.value = '';
    if (statusFilter) statusFilter.value = '';
    
    // Re-display all users
    displayUsers(allUsers);
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
    
    // Reset department dropdown to show placeholder
    const deptDropdown = document.getElementById('createUserDepartment');
    if (deptDropdown) {
        deptDropdown.value = '';
    }
    
    // Hide department field by default
    toggleDepartmentField('create', '');
    
    modal.classList.remove('hidden');
}

// Toggle department field visibility based on role
function toggleDepartmentField(formType, role) {
    const container = document.getElementById(formType + 'DepartmentContainer');
    const departmentSelect = document.getElementById(formType + 'UserDepartment');
    const requiredSpan = document.getElementById(formType + 'DeptRequired');
    
    if (!container) return;
    
    if (role === 'Department Head') {
        // Show department field for Dean
        container.style.display = 'block';
        if (departmentSelect) {
            departmentSelect.required = true;
            // Reset to empty so user can see the placeholder "Select department"
            departmentSelect.value = '';
        }
        if (requiredSpan) {
            requiredSpan.style.display = 'inline';
        }
    } else {
        // Hide department field for Admin and Secretary
        container.style.display = 'none';
        if (departmentSelect) {
            departmentSelect.required = false;
            departmentSelect.value = ''; // Clear value when hidden
        }
        if (requiredSpan) {
            requiredSpan.style.display = 'none';
        }
    }
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
        subject: formData.get('subject') || '',      // ✅ subject field
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
            
            // Close the correct modal and reset its form
            if (!document.getElementById('createJobModal').classList.contains('hidden')) {
                closeCreateJobModal();
            } else if (!document.getElementById('createutilityJobModal').classList.contains('hidden')) {
                closeCreateutilityJobModal();
            } else if (!document.getElementById('createsecJobModal').classList.contains('hidden')) {
                closeCreatesecJobModal();
            }
            
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
    const role = formData.get('role');
    if (!formData.get('name') || !formData.get('email') || !role) {
        showToast('Please fill in all required fields', 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        return;
    }
    
    // Only require department for Dean role
    if (role === 'Department Head' && !formData.get('department')) {
        showToast('Please select a department for Dean role', 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        return;
    }
    
    // Set default department for Admin and Secretary
    if (role !== 'Department Head') {
        formData.set('department', 'General');
    }
    
    // Add dummy password (API will generate its own temporary password)
    formData.append('password', 'temporary_will_be_replaced');
    
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
            const message = result.emailSent 
                ? 'User created successfully! Temporary password sent to their email.' 
                : 'User created successfully! However, email notification failed.';
            showToast(message, result.emailSent ? 'success' : 'warning');
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
    
    // Role change listeners for department field visibility
    const createRoleSelect = document.getElementById('createUserRole');
    if (createRoleSelect) {
        createRoleSelect.addEventListener('change', function(e) {
            toggleDepartmentField('create', e.target.value);
        });
    }
    
    const editRoleSelect = document.getElementById('editUserRole');
    if (editRoleSelect) {
        editRoleSelect.addEventListener('change', function(e) {
            toggleDepartmentField('edit', e.target.value);
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
        case 'Secretary': return 'bg-green-100 text-green-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getRoleIcon(role) {
    switch(role) {
        case 'Admin': return '<i class="fas fa-shield-alt text-red-500"></i>';
        case 'HR Manager': return '<i class="fas fa-shield text-purple-500"></i>';
        case 'Department Head': return '<i class="fas fa-shield text-blue-500"></i>';
        case 'Secretary': return '<i class="fas fa-user-tie text-green-500"></i>';
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

// Convert role name for display (Department Head -> Dean)
function displayRoleName(role) {
    return role === 'Department Head' ? 'Dean' : role;
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
    
    // Update header information
    modal.querySelector('.job-title').innerText = job.job_title || 'Job Title';
    modal.querySelectorAll('.job-dept').forEach(el => el.innerText = job.department_role || 'Not specified');
    modal.querySelectorAll('.job-type').forEach(el => el.innerText = job.job_type || 'Not specified');
    modal.querySelectorAll('.job-loc').forEach(el => el.innerText = job.locations || 'Not specified');
    modal.querySelector('.job-salary').innerText = job.salary_range || 'Not specified';
    modal.querySelector('.job-deadline').innerText = job.application_deadline ? new Date(job.application_deadline).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'Not specified';
    
    // Update job highlights
    modal.querySelector('.job-type-highlight').innerText = job.job_type || 'Not specified';
    modal.querySelector('.job-loc-highlight').innerText = job.locations || 'Not specified';
    modal.querySelector('.job-dept-highlight').innerText = job.department_role || 'Not specified';
    
    // Update Position Overview (Job Description)
    const descContainer = modal.querySelector('.job-desc');
    if (job.job_description && job.job_description.trim()) {
        const descriptions = job.job_description.split('\n').filter(desc => desc.trim());
        descContainer.innerHTML = descriptions.map(desc => 
            `<p class="flex items-start"><i class="fas fa-check-circle text-blue-600 mr-2 mt-1"></i><span>${desc.trim()}</span></p>`
        ).join('');
    } else {
        descContainer.innerHTML = '<p class="text-gray-500 italic">No description available</p>';
    }
    
    // Update Duties & Responsibilities
    const dutiesSection = document.getElementById('job-duties-section');
    const dutiesContainer = modal.querySelector('.job-duties');
    if (job.duties && job.duties.trim()) {
        dutiesSection.style.display = 'block';
        const duties = job.duties.split('\n').filter(duty => duty.trim());
        dutiesContainer.innerHTML = duties.map(duty => 
            `<p class="flex items-start"><i class="fas fa-arrow-right text-blue-600 mr-2 mt-1"></i><span>${duty.trim()}</span></p>`
        ).join('');
    } else {
        dutiesSection.style.display = 'none';
    }
    
    // Update Qualifications
    modal.querySelector('.job-education').innerText = job.education || 'Not specified';
    modal.querySelector('.job-experience').innerText = job.experience || 'Not specified';
    modal.querySelector('.job-training').innerText = job.training || 'Not specified';
    modal.querySelector('.job-eligibility').innerText = job.eligibility || 'Not specified';
    
    // Update Competencies
    const competencyContainer = modal.querySelector('.job-competency');
    if (job.competency && job.competency.trim()) {
        const competencies = job.competency.split('\n').filter(comp => comp.trim());
        competencyContainer.innerHTML = competencies.map(comp => 
            `<p class="flex items-start"><i class="fas fa-star text-amber-500 mr-2 mt-1"></i><span>${comp.trim()}</span></p>`
        ).join('');
    } else {
        competencyContainer.innerHTML = '<p class="text-gray-500 italic">Not specified</p>';
    }

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
    const jobTitle = modal.querySelector('.job-title').innerText;

    fetch('delete_job.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: jobId })
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            showToast(`Job posting "${jobTitle}" has been deleted successfully`, 'success');
            loadJobs();
            // Refresh dashboard if we're on the dashboard page
            if (typeof loadDashboardData === 'function') {
                loadDashboardData();
            }
        } else {
            showToast(result.message || 'Failed to delete job posting', 'error');
            console.error(result.message);
        }
        modal.classList.add('hidden');
    })
    .catch(error => {
        showToast('Network error: Failed to delete job posting', 'error');
        console.error('Error deleting job:', error);
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
        
        // Prevent admin from setting their own status to Inactive
        if (typeof CURRENT_ADMIN_ID !== 'undefined' && user.id == CURRENT_ADMIN_ID) {
            // Disable the Inactive option in status dropdown
            const inactiveOption = statusSelect.querySelector('option[value="Inactive"]');
            if (inactiveOption) {
                inactiveOption.disabled = true;
                inactiveOption.textContent = 'Inactive (Cannot deactivate yourself)';
            }
            // If current status is already Inactive (shouldn't happen), force it to Active
            if (statusSelect.value === 'Inactive') {
                statusSelect.value = 'Active';
            }
        } else {
            // Re-enable the Inactive option for other users
            const inactiveOption = statusSelect.querySelector('option[value="Inactive"]');
            if (inactiveOption) {
                inactiveOption.disabled = false;
                inactiveOption.textContent = 'Inactive';
            }
        }
        
        // Toggle department field visibility based on role
        toggleDepartmentField('edit', user.role);
        
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
    let department = document.getElementById('editUserDepartment').value;
    const status = document.getElementById('editUserStatus').value;
    const fileInput = document.getElementById('editProfilePictureInput');
    
    console.log('UPDATE USER - User ID:', userId);
    console.log('Global backup ID:', currentEditingUserId);
    
    if (!userId) {
        alert('ERROR: User ID is missing! Please close modal and try again.');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        return;
    }
    
    if (!name || !email || !role) {
        showToast('Please fill in all required fields', 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        return;
    }
    
    // Only require department for Dean role
    if (role === 'Department Head' && !department) {
        showToast('Please select a department for Dean role', 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        return;
    }
    
    // Set default department for Admin and Secretary
    if (role !== 'Department Head') {
        department = 'General';
    }
    
    try {
        // Check if profile picture is included
        const hasProfilePicture = fileInput.files.length > 0;
        
        if (hasProfilePicture) {
            // Use FormData when profile picture is included
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('name', name);
            formData.append('email', email);
            formData.append('role', role);
            formData.append('department', department);
            formData.append('status', status);
            if (password) formData.append('password', password);
            if (phone) formData.append('phone', phone);
            formData.append('profile_picture', fileInput.files[0]);
            
            console.log('Sending update with FormData (includes profile picture)');
            
            const response = await fetch('api/users.php', {
                method: 'PUT',
                body: formData
            });
            
            const result = await response.json();
            console.log('Update result:', result);
            
            if (result.success) {
                showToast(result.message || 'User updated successfully!', 'success');
                closeEditUserModal();
                loadUsers();
            } else {
                showToast(result.message || 'Failed to update user', 'error');
            }
        } else {
            // Use JSON when no profile picture
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
            
            console.log('Sending update with JSON (no profile picture)');
            
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
                if (result.affected_rows === 0) {
                    showToast('No changes detected (data was the same)', 'warning');
                } else {
                    showToast('User updated successfully!', 'success');
                }
                closeEditUserModal();
                loadUsers();
            } else {
                showToast(result.message || 'Failed to update user', 'error');
            }
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Update failed: ' + error.message, 'error');
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

// Delete user function removed - users cannot be deleted for data integrity

function moreUserActions(id) {
    alert(`More actions for user ID: ${id}`);
}

// Close modals when clicking outside
// Disable outside-click-to-close behavior for modals (keep modals open unless explicit close buttons are used)
document.addEventListener('click', (e) => {
    // Intentionally left blank to prevent closing on outside clicks
});

function openJobTypeSelectionModal() {
    document.getElementById('jobTypeSelectionModal').classList.remove('hidden');
}

function closeJobTypeSelectionModal() {
    document.getElementById('jobTypeSelectionModal').classList.add('hidden');
}

function openCreateJobModal(jobType) {
    // Job title is now set to "Instructor" by default in the HTML (readonly)
    // Location is set to "Norzagaray College" by default in the HTML (readonly)
    if (jobType) {
        closeJobTypeSelectionModal(); // Close the job type selection modal if it was open
    }
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
    const modal = document.getElementById('createJobModal');
    modal.classList.add('hidden');
    const form = modal.querySelector('form');
    if (form) form.reset();
}

function closeCreateutilityJobModal() {
    const modal = document.getElementById('createutilityJobModal');
    modal.classList.add('hidden');
    const form = modal.querySelector('form');
    if (form) form.reset();
}

function closeCreatesecJobModal() {
    const modal = document.getElementById('createsecJobModal');
    modal.classList.add('hidden');
    const form = modal.querySelector('form');
    if (form) form.reset();
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
            // Update dashboard statistics using data-stat attributes
            if (data.stats) {
                // Update each stat by its data-stat attribute
                for (const [key, value] of Object.entries(data.stats)) {
                    const statElement = document.querySelector(`[data-stat="${key}"]`);
                    if (statElement) {
                        statElement.textContent = value;
                    }
                }
            }
            
            // Update recent activity
            const activityContainer = document.getElementById('recentActivityContainer');
            if (activityContainer && data.recent_activity) {
                let activityHTML = '';
                data.recent_activity.forEach(activity => {
                    // Skip application and login activities
                    if (activity.activity_type === 'application' || activity.activity_type === 'admin_login') {
                        return;
                    }
                    
                    let iconClass = 'fas fa-circle text-gray-600';
                    let bgClass = 'bg-gray-100';
                    let activityTitle = 'Activity';
                    
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
                        case 'interview_scheduled':
                            iconClass = 'fas fa-calendar-check text-blue-600';
                            bgClass = 'bg-blue-100';
                            activityTitle = 'Interview scheduled';
                            break;
                        case 'demo_scheduled':
                            iconClass = 'fas fa-chalkboard-teacher text-blue-600';
                            bgClass = 'bg-blue-100';
                            activityTitle = 'Demo teaching scheduled';
                            break;
                        case 'psych_exam_scheduled':
                            iconClass = 'fas fa-brain text-purple-600';
                            bgClass = 'bg-purple-100';
                            activityTitle = 'Psychological exam scheduled';
                            break;
                        case 'interview_approved':
                            iconClass = 'fas fa-check-circle text-green-600';
                            bgClass = 'bg-green-100';
                            activityTitle = 'Interview approved';
                            break;
                        case 'demo_approved':
                            iconClass = 'fas fa-check-double text-green-600';
                            bgClass = 'bg-green-100';
                            activityTitle = 'Demo teaching approved';
                            break;
                        case 'resubmission_requested':
                            iconClass = 'fas fa-file-upload text-orange-600';
                            bgClass = 'bg-orange-100';
                            activityTitle = 'Resubmission requested';
                            break;
                        case 'applicant_rejected':
                            iconClass = 'fas fa-times-circle text-red-600';
                            bgClass = 'bg-red-100';
                            activityTitle = 'Application rejected';
                            break;
                        case 'applicant_hired':
                            iconClass = 'fas fa-user-check text-green-600';
                            bgClass = 'bg-green-100';
                            activityTitle = 'Applicant hired';
                            break;
                        case 'applicant_transferred':
                            iconClass = 'fas fa-share text-blue-600';
                            bgClass = 'bg-blue-100';
                            activityTitle = 'Application transferred';
                            break;
                        case 'applicant_initially_hired':
                            iconClass = 'fas fa-user-plus text-green-600';
                            bgClass = 'bg-green-100';
                            activityTitle = 'Applicant initially hired';
                            break;
                        case 'user_created':
                            iconClass = 'fas fa-user-shield text-purple-600';
                            bgClass = 'bg-purple-100';
                            activityTitle = 'Admin user created';
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
            
            // Update recent applicants
            if (data.recent_applicants) {
                const applicantsContainer = document.querySelector('#recentApplicantsContainer');
                if (applicantsContainer) {
                    let applicantsHTML = '';
                    data.recent_applicants.forEach(applicant => {
                        // Determine status badge
                        let statusClass = '';
                        let statusText = '';
                        
                        switch(applicant.status) {
                            case 'Approved':
                            case 'Initially Hired':
                            case 'Permanently Hired':
                            case 'Hired':
                                statusClass = 'bg-green-100 text-green-800';
                                statusText = applicant.status;
                                break;
                            case 'Rejected':
                                statusClass = 'bg-red-100 text-red-800';
                                statusText = 'Rejected';
                                break;
                            case 'Interview Scheduled':
                            case 'Demo Scheduled':
                            case 'Psych Scheduled':
                                statusClass = 'bg-blue-100 text-blue-800';
                                statusText = applicant.status;
                                break;
                            case 'Resubmission Required':
                                statusClass = 'bg-orange-100 text-orange-800';
                                statusText = 'Resubmission Required';
                                break;
                            default:
                                statusClass = 'bg-yellow-100 text-yellow-800';
                                statusText = 'Under Review';
                        }
                        
                        applicantsHTML += `
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900">${applicant.full_name || 'N/A'}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">${applicant.position || 'N/A'}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${statusClass}">
                                        ${statusText}
                                    </span>
                                </td>
                            </tr>
                        `;
                    });
                    
                    if (applicantsHTML === '') {
                        applicantsHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">No recent applicants</td></tr>';
                    }
                    
                    applicantsContainer.innerHTML = applicantsHTML;
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
            
            // Update status badge in Actions section
            const statusBadgeHTML = getStatusBadge(applicant.status);
            const actionStatusBadge = document.getElementById('actionStatusBadge');
            if (actionStatusBadge) {
                actionStatusBadge.innerHTML = statusBadgeHTML;
                console.log('Status loaded:', applicant.status);
            }
            
            // Update personal information
            const personalInfo = document.getElementById('personalInfo');
            
            personalInfo.innerHTML = `
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
                { field: 'letter_of_intent', label: 'Letter of Intent' },
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
            console.log('  Letter of Intent:', applicant.letter_of_intent);
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
                    ${(applicant.status === 'Interview Passed' || 
                      applicant.status === 'Demo Scheduled' || 
                      applicant.status === 'Demo Passed' ||
                      applicant.status === 'Psych Scheduled' ||
                      applicant.status === 'Initially Hired' ||
                      applicant.status === 'Permanently Hired' ||
                      applicant.status === 'Hired' ||
                      applicant.workflow_stage === 'interview_completed' ||
                      applicant.workflow_stage === 'demo_scheduled' ||
                      applicant.workflow_stage === 'demo_completed') ? `
                    <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-sm font-semibold text-green-800 flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            Interview Approved
                        </p>
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
                    ${applicant.status === 'Demo Passed' || 
                      applicant.workflow_stage === 'demo_completed' ||
                      applicant.status === 'Psychological Exam' ||
                      applicant.status === 'Initially Hired' ||
                      applicant.status === 'Hired' ? `
                    <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-sm font-semibold text-green-800 flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            Demo Teaching Approved
                        </p>
                    </div>
                    ` : `
                    <div class="mt-2 p-3 bg-indigo-50 border border-indigo-200 rounded-lg">
                        <p class="text-sm text-indigo-800 flex items-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            Demo teaching session scheduled. Applicant will prepare teaching materials.
                        </p>
                    </div>
                    `}
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
    // Ensure status is a string, not an object
    if (typeof status === 'object') {
        console.error('Status is an object:', status);
        status = status?.status || status?.name || 'Unknown';
    }
    
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
        case 'Interview Scheduled':
            colorClass = 'bg-blue-100 text-blue-800';
            icon = '<i class="fas fa-calendar mr-1"></i>';
            break;
        case 'Interview Passed':
            colorClass = 'bg-teal-100 text-teal-800';
            icon = '<i class="fas fa-user-check mr-1"></i>';
            break;
        case 'Demo Scheduled':
            colorClass = 'bg-indigo-100 text-indigo-800';
            icon = '<i class="fas fa-chalkboard-teacher mr-1"></i>';
            break;
        case 'Demo Passed':
            colorClass = 'bg-emerald-100 text-emerald-800';
            icon = '<i class="fas fa-check-double mr-1"></i>';
            break;
        case 'Psychological Exam':
            colorClass = 'bg-purple-100 text-purple-800';
            icon = '<i class="fas fa-brain mr-1"></i>';
            break;
        case 'Initially Hired':
            colorClass = 'bg-green-100 text-green-700 border border-green-300';
            icon = '<i class="fas fa-user-check mr-1"></i>';
            break;
        case 'Hired':
            colorClass = 'bg-green-100 text-green-800 border-2 border-green-300';
            icon = '<i class="fas fa-check-circle mr-1"></i>';
            break;
        case 'Resubmission Required':
            colorClass = 'bg-orange-100 text-orange-800';
            icon = '<i class="fas fa-redo mr-1"></i>';
            break;
        case 'Rejected':
            colorClass = 'bg-red-100 text-red-800 border-2 border-red-300';
            icon = '<i class="fas fa-times-circle mr-1"></i>';
            break;
    }
    
    return `<span class="px-3 py-2 text-sm font-bold rounded-full ${colorClass} inline-flex items-center">${icon}${status}</span>`;
}

// Update action buttons visibility based on applicant status
function updateActionButtons(status, applicant = null) {
    const scheduleBtn = document.getElementById('scheduleBtn');
    const transferBtn = document.getElementById('transferToDeptHeadBtn');
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
    
    // Get workflow stage from applicant data
    const workflowStage = applicant ? applicant.workflow_stage : null;
    
    // Hide all buttons first
    if (scheduleBtn) scheduleBtn.classList.add('hidden');
    if (transferBtn) transferBtn.classList.add('hidden');
    if (approveInterviewBtn) approveInterviewBtn.classList.add('hidden');
    if (rescheduleInterviewBtn) rescheduleInterviewBtn.classList.add('hidden');
    if (scheduleDemoBtn) scheduleDemoBtn.classList.add('hidden');
    if (approveDemoBtn) approveDemoBtn.classList.add('hidden');
    if (rescheduleDemoBtn) rescheduleDemoBtn.classList.add('hidden');
    if (hireBtn) hireBtn.classList.add('hidden');
    if (permanentHireBtn) permanentHireBtn.classList.add('hidden');
    if (resubmitBtn) resubmitBtn.classList.add('hidden');
    if (rejectBtn) rejectBtn.classList.add('hidden');
    
    // Remove any existing transfer info message and psych indicator
    const existingTransferInfo = document.querySelector('.transfer-info');
    if (existingTransferInfo) {
        existingTransferInfo.remove();
    }
    const existingPsychIndicator = document.querySelector('.psych-indicator');
    if (existingPsychIndicator) {
        existingPsychIndicator.remove();
    }
    const existingCancelledInfo = document.querySelector('.cancelled-info');
    if (existingCancelledInfo) {
        existingCancelledInfo.remove();
    }
    const existingHiredStatus = document.querySelector('.hired-status');
    if (existingHiredStatus) {
        existingHiredStatus.remove();
    }
    const existingRejectedStatus = document.querySelector('.rejected-status');
    if (existingRejectedStatus) {
        existingRejectedStatus.remove();
    }
    
    // Check if psychological exam receipt has been uploaded
    const hasPsychReceipt = applicant && applicant.psych_exam_receipt;
    
    // CHECK IF APPLICATION IS CANCELLED
    const applicationStatus = applicant && applicant.status ? applicant.status.toLowerCase() : '';
    if (applicationStatus.includes('cancel')) {
        // Show "Application Cancelled" message - no action buttons
        const actionButtonsContainer = document.getElementById('actionButtons');
        if (actionButtonsContainer && !actionButtonsContainer.querySelector('.cancelled-info')) {
            const infoDiv = document.createElement('div');
            infoDiv.className = 'cancelled-info bg-gray-50 border border-gray-300 rounded-lg p-4';
            infoDiv.innerHTML = `
                <div class="flex items-start gap-3">
                    <i class="fas fa-times-circle text-gray-600 mt-1"></i>
                    <div>
                        <h4 class="font-semibold text-gray-900 text-sm">Application Cancelled</h4>
                        <p class="text-xs text-gray-700 mt-1">
                            This application has been cancelled by the applicant. No further actions can be taken.
                        </p>
                    </div>
                </div>
            `;
            actionButtonsContainer.prepend(infoDiv);
        }
        return; // Exit - no action buttons for cancelled applications
    }
    
    // CHECK IF APPLICATION IS REJECTED
    if (workflowStage === 'rejected' || (status && status.toLowerCase() === 'rejected')) {
        const actionButtonsContainerRejected = document.getElementById('actionButtons');
        if (actionButtonsContainerRejected && !actionButtonsContainerRejected.querySelector('.rejected-status')) {
            const rejectedDiv = document.createElement('div');
            rejectedDiv.className = 'rejected-status bg-red-50 border-2 border-red-300 rounded-lg p-6';
            rejectedDiv.innerHTML = `
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                        <i class="fas fa-times-circle text-3xl text-red-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-red-900 mb-2">Application Rejected</h3>
                    <p class="text-sm text-red-700">
                        This application has been rejected. The applicant has been notified and the application has been archived.
                    </p>
                </div>
            `;
            actionButtonsContainerRejected.innerHTML = '';
            actionButtonsContainerRejected.appendChild(rejectedDiv);
        }
        return; // Exit - no action buttons for rejected applications
    }
    
    // SECRETARY ACTIONS: Transfer, Request Resubmission, Reject
    if (workflowStage === 'secretary_review') {
        if (transferBtn) transferBtn.classList.remove('hidden');
        if (resubmitBtn) resubmitBtn.classList.remove('hidden');
        if (rejectBtn) rejectBtn.classList.remove('hidden');
        return; // Exit early for secretary
    }
    
    // SECRETARY VIEW-ONLY: Application already transferred
    // Secretary can see it but cannot take actions
    if (typeof CURRENT_ADMIN_ROLE !== 'undefined' && CURRENT_ADMIN_ROLE === 'Secretary') {
        if (workflowStage && (workflowStage.startsWith('department_head') || 
            workflowStage === 'interview_scheduled' || workflowStage === 'interview_completed' ||
            workflowStage === 'demo_scheduled' || workflowStage === 'demo_completed' ||
            workflowStage === 'psych_scheduled' || workflowStage === 'psych_completed' ||
            workflowStage === 'hired' || workflowStage === 'initially_hired' ||
            workflowStage === 'permanently_hired')) {
            // Show info message for Secretary viewing transferred application
            const actionButtonsContainer = document.getElementById('actionButtons');
            if (actionButtonsContainer && !actionButtonsContainer.querySelector('.transfer-info')) {
                const infoDiv = document.createElement('div');
                infoDiv.className = 'transfer-info bg-blue-50 border border-blue-200 rounded-lg p-4';
                infoDiv.innerHTML = `
                    <div class="flex items-start gap-3">
                        <i class="fas fa-info-circle text-blue-600 mt-1"></i>
                        <div>
                            <h4 class="font-semibold text-blue-900 text-sm">Application Transferred</h4>
                            <p class="text-xs text-blue-700 mt-1">
                                This application has been transferred to the Dean. 
                                You can view the progress but cannot make changes.
                            </p>
                        </div>
                    </div>
                `;
                actionButtonsContainer.prepend(infoDiv);
            }
            return; // Exit - no action buttons for transferred applications
        }
    }
    
    // DEPARTMENT HEAD ACTIONS: Schedule Interview (NO Request Resubmission)
    if (workflowStage === 'department_head_review') {
        if (scheduleBtn) scheduleBtn.classList.remove('hidden');
        if (rejectBtn) rejectBtn.classList.remove('hidden');
        // Note: NO resubmitBtn for Dean
        return; // Exit early
    }
    
    // INTERVIEW SCHEDULED: Approve or Reschedule
    if (workflowStage === 'interview_scheduled') {
        if (approveInterviewBtn) approveInterviewBtn.classList.remove('hidden');
        if (rescheduleInterviewBtn) rescheduleInterviewBtn.classList.remove('hidden');
        if (rejectBtn) rejectBtn.classList.remove('hidden');
        return;
    }
    
    // INTERVIEW COMPLETED: Schedule Demo
    if (workflowStage === 'interview_completed') {
        if (scheduleDemoBtn) scheduleDemoBtn.classList.remove('hidden');
        if (rejectBtn) rejectBtn.classList.remove('hidden');
        return;
    }
    
    // DEMO SCHEDULED: Approve or Reschedule Demo
    if (workflowStage === 'demo_scheduled') {
        if (approveDemoBtn) approveDemoBtn.classList.remove('hidden');
        if (rescheduleDemoBtn) rescheduleDemoBtn.classList.remove('hidden');
        if (rejectBtn) rejectBtn.classList.remove('hidden');
        return;
    }
    
    // DEMO COMPLETED: Show hire button and psych exam indicator
    if (workflowStage === 'demo_completed') {
        const actionButtonsContainer = document.getElementById('actionButtons');
        
        // Show hire button (may be disabled if no psych receipt)
        if (hireBtn) {
            hireBtn.classList.remove('hidden');
            
            // If no psych receipt, disable and show warning
            if (!hasPsychReceipt) {
                hireBtn.disabled = true;
                hireBtn.classList.add('opacity-50', 'cursor-not-allowed');
                hireBtn.title = 'Waiting for psychological exam receipt';
                
                // Add yellow indicator message
                if (actionButtonsContainer && !actionButtonsContainer.querySelector('.psych-indicator')) {
                    const indicatorDiv = document.createElement('div');
                    indicatorDiv.className = 'psych-indicator bg-yellow-50 border border-yellow-300 rounded-lg p-4 mb-3';
                    indicatorDiv.innerHTML = `
                        <div class="flex items-start gap-3">
                            <i class="fas fa-hourglass-half text-yellow-600 mt-1"></i>
                            <div>
                                <h4 class="font-semibold text-yellow-900 text-sm">Waiting for Psychological Exam Receipt</h4>
                                <p class="text-xs text-yellow-700 mt-1">
                                    The applicant needs to take the psychological examination and upload the receipt before you can proceed with hiring.
                                </p>
                            </div>
                        </div>
                    `;
                    actionButtonsContainer.prepend(indicatorDiv);
                }
            } else {
                // Has receipt, enable button
                hireBtn.disabled = false;
                hireBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                hireBtn.title = '';
            }
        }
        
        if (rejectBtn) rejectBtn.classList.remove('hidden');
        return;
    }

    // PSYCH EXAM: Wait for receipt, then allow Dean to hire.
    if (workflowStage === 'psych_scheduled' || workflowStage === 'psych_completed') {
        const actionButtonsContainer = document.getElementById('actionButtons');

        if (hireBtn) {
            hireBtn.classList.remove('hidden');

            if (!hasPsychReceipt) {
                hireBtn.disabled = true;
                hireBtn.classList.add('opacity-50', 'cursor-not-allowed');
                hireBtn.title = 'Waiting for psychological exam receipt';

                if (actionButtonsContainer && !actionButtonsContainer.querySelector('.psych-indicator')) {
                    const indicatorDiv = document.createElement('div');
                    indicatorDiv.className = 'psych-indicator bg-yellow-50 border border-yellow-300 rounded-lg p-4 mb-3';
                    indicatorDiv.innerHTML = `
                        <div class="flex items-start gap-3">
                            <i class="fas fa-hourglass-half text-yellow-600 mt-1"></i>
                            <div>
                                <h4 class="font-semibold text-yellow-900 text-sm">Waiting for Psychological Exam Receipt</h4>
                                <p class="text-xs text-yellow-700 mt-1">
                                    The applicant must upload the psychological exam receipt before hiring can proceed.
                                </p>
                            </div>
                        </div>
                    `;
                    actionButtonsContainer.prepend(indicatorDiv);
                }
            } else {
                hireBtn.disabled = false;
                hireBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                hireBtn.title = '';
            }
        }

        if (rejectBtn) rejectBtn.classList.remove('hidden');
        return;
    }

    // INITIALLY HIRED: Allow final hiring confirmation.
    if (workflowStage === 'initially_hired') {
        if (permanentHireBtn) permanentHireBtn.classList.remove('hidden');
        if (rejectBtn) rejectBtn.classList.remove('hidden');
        return;
    }
    
    // HIRED: Show success message
    if (workflowStage === 'hired') {
        const actionButtonsContainerHired = document.getElementById('actionButtons');
        if (actionButtonsContainerHired && !actionButtonsContainerHired.querySelector('.hired-status')) {
            const hiredDiv = document.createElement('div');
            hiredDiv.className = 'hired-status bg-green-50 border-2 border-green-300 rounded-lg p-6';
            hiredDiv.innerHTML = `
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                        <i class="fas fa-user-check text-3xl text-green-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-green-900 mb-2">Applicant Hired</h3>
                    <p class="text-sm text-green-700">
                        This applicant has been successfully hired. No further actions are required.
                    </p>
                </div>
            `;
            actionButtonsContainerHired.innerHTML = '';
            actionButtonsContainerHired.appendChild(hiredDiv);
        }
        return; // No buttons for completed applications
    }
    
    // Continue with status-based logic for backward compatibility
    switch(status) {
        case 'Pending':
        case 'Resubmission Required':
            // For backward compatibility if workflow_stage not set
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
            // These are handled earlier in the function with workflow stages
            // No need to duplicate the display logic here
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
    
    if (dateInput) {
        dateInput.setAttribute('min', today);
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
    // Get the document checkboxes container
    const checkboxContainer = document.getElementById('documentCheckboxes');
    
    // Define all possible documents with their labels
    const allDocuments = [
        { field: 'application_letter', label: 'Application Letter' },
        { field: 'letter_of_intent', label: 'Letter of Intent' },
        { field: 'resume', label: 'Updated and Comprehensive Resume' },
        { field: 'tor', label: 'Transcript of Record (TOR)' },
        { field: 'diploma', label: 'Diploma' },
        { field: 'professional_license', label: 'Professional License' },
        { field: 'coe', label: 'Certificate of Employment (COE)' },
        { field: 'seminars_trainings', label: 'Seminar/Training Certificates' },
        { field: 'masteral_cert', label: 'Masteral Certificate' }
    ];
    
    // Clear existing checkboxes
    checkboxContainer.innerHTML = '';
    
    // Only show checkboxes for documents that were uploaded
    let uploadedCount = 0;
    allDocuments.forEach(doc => {
        // Check if this document was uploaded by the applicant
        if (currentApplicantData && currentApplicantData[doc.field]) {
            uploadedCount++;
            
            // Create checkbox HTML
            const label = document.createElement('label');
            label.className = 'flex items-center';
            label.innerHTML = `
                <input type="checkbox" name="resubmit_documents" value="${doc.field}" 
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-2 text-sm text-gray-700">${doc.label}</span>
            `;
            checkboxContainer.appendChild(label);
        }
    });
    
    // Show message if no documents were uploaded
    if (uploadedCount === 0) {
        checkboxContainer.innerHTML = '<p class="text-sm text-gray-500 italic">No documents have been uploaded yet.</p>';
    }
    
    console.log(`✅ Resubmission modal showing ${uploadedCount} uploaded documents`);
    
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

// Transfer to Dean Modal Functions
function openTransferModal() {
    document.getElementById('transferModal').classList.remove('hidden');
    document.getElementById('transferModal').classList.add('flex');
}

function closeTransferModal() {
    document.getElementById('transferModal').classList.add('hidden');
    document.getElementById('transferModal').classList.remove('flex');
    document.getElementById('transferForm').reset();
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
    
    if (dateInput) {
        dateInput.setAttribute('min', today);
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
    
    if (dateInput) {
        dateInput.setAttribute('min', today);
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
    
    if (dateInput) {
        dateInput.setAttribute('min', today);
    }
    
    // Reuse the same schedule modal but change the title and action
    document.getElementById('scheduleModal').classList.remove('hidden');
    document.getElementById('scheduleModal').classList.add('flex');
    document.querySelector('#scheduleModal h3').textContent = 'Schedule Demo Teaching';
    document.getElementById('scheduleForm').dataset.action = 'schedule_demo';
}

// Helper function to check if time is a valid 15-minute interval
function isValid15MinuteInterval(timeString) {
    if (!timeString) return false;
    
    const [hours, minutes] = timeString.split(':').map(Number);
    
    // Check if minutes are 00, 15, 30, or 45
    return minutes % 15 === 0;
}

// Add time validation to all time inputs
function validateTimeInput(inputElement) {
    if (!inputElement) return;
    
    inputElement.addEventListener('change', function() {
        if (this.value && !isValid15MinuteInterval(this.value)) {
            showToast('Please select a time in 15-minute intervals (e.g., 8:00, 8:15, 8:30, 8:45)', 'warning');
            this.classList.add('border-red-500');
        } else {
            this.classList.remove('border-red-500');
        }
    });
    
    inputElement.addEventListener('blur', function() {
        if (this.value && !isValid15MinuteInterval(this.value)) {
            this.classList.add('border-red-500');
        }
    });
}

// Form submission handlers
document.addEventListener('DOMContentLoaded', function() {
    // Apply time validation to all time inputs
    validateTimeInput(document.getElementById('interviewTime'));
    validateTimeInput(document.getElementById('rescheduleInterviewTime'));
    validateTimeInput(document.getElementById('rescheduleDemoTime'));
    
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
        
        // Validate 15-minute interval
        if (!isValid15MinuteInterval(timeValue)) {
            showToast('Please select a time in 15-minute intervals (e.g., 8:00, 8:15, 8:30, 8:45)', 'warning');
            document.getElementById('interviewTime').classList.add('border-red-500');
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
            formData.append('interview_location', document.getElementById('interviewLocation').value);
            formData.append('interview_room', document.getElementById('interviewRoom').value);
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
                    
                    // Update action buttons immediately
                    const newStatus = statusMap[action];
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
    
    // Transfer to Dean Form
    document.getElementById('transferForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Prevent duplicate submissions
        preventDuplicateSubmission(async () => {
            const formData = new FormData();
            formData.append('action', 'transfer_to_dept_head');
            formData.append('application_id', currentApplicantId);
            formData.append('notes', document.getElementById('transferNotes').value);
            
            try {
                const response = await fetch('api/secretary_actions.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    closeTransferModal();
                    showToast('Application transferred to Dean successfully!', 'success');
                    
                    // Refresh the current applicant details to show new status
                    // and update the applicants list
                    setTimeout(() => {
                        viewApplicantDetails(currentApplicantId);
                        loadApplicants(); // Update the list in background
                    }, 500);
                } else {
                    showToast('Error transferring application: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error transferring application:', error);
                showToast('Failed to transfer application', 'error');
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
        
        // Validate 15-minute interval
        if (!isValid15MinuteInterval(timeValue)) {
            showToast('Please select a time in 15-minute intervals (e.g., 8:00, 8:15, 8:30, 8:45)', 'warning');
            document.getElementById('rescheduleInterviewTime').classList.add('border-red-500');
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
            formData.append('interview_location', document.getElementById('rescheduleInterviewLocation').value);
            formData.append('interview_room', document.getElementById('rescheduleInterviewRoom').value);
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
        
        // Validate 15-minute interval
        if (!isValid15MinuteInterval(timeValue)) {
            showToast('Please select a time in 15-minute intervals (e.g., 8:00, 8:15, 8:30, 8:45)', 'warning');
            document.getElementById('rescheduleDemoTime').classList.add('border-red-500');
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
            formData.append('demo_location', document.getElementById('rescheduleDemoLocation').value);
            formData.append('demo_room', document.getElementById('rescheduleDemoRoom').value);
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

// Applicants Pagination Functions

function showApplicantsPagination() {
    const paginationContainer = document.getElementById('applicantsPaginationContainer');
    if (paginationContainer) {
        paginationContainer.classList.remove('hidden');
    }
}

function hideApplicantsPagination() {
    const paginationContainer = document.getElementById('applicantsPaginationContainer');
    if (paginationContainer) {
        paginationContainer.classList.add('hidden');
    }
}

function updateApplicantsPaginationInfo(start, end, total) {
    const infoElement = document.getElementById('applicantsPaginationInfo');
    if (infoElement) {
        infoElement.textContent = `Showing ${start}-${end} of ${total} applicants`;
    }
}

function updateApplicantsPaginationButtons() {
    const prevBtn = document.getElementById('applicantsPrevBtn');
    const nextBtn = document.getElementById('applicantsNextBtn');
    
    if (prevBtn) {
        prevBtn.disabled = currentApplicantsPage === 1;
    }
    
    if (nextBtn) {
        nextBtn.disabled = currentApplicantsPage >= totalApplicantsPages;
    }
    
    // Update page numbers
    updateApplicantsPageNumbers();
}

function updateApplicantsPageNumbers() {
    const pageNumbersContainer = document.getElementById('applicantsPageNumbers');
    if (!pageNumbersContainer) return;
    
    pageNumbersContainer.innerHTML = '';
    
    // Calculate which pages to show
    let startPage = Math.max(1, currentApplicantsPage - 2);
    let endPage = Math.min(totalApplicantsPages, currentApplicantsPage + 2);
    
    // Adjust if we're near the beginning or end
    if (currentApplicantsPage <= 3) {
        endPage = Math.min(5, totalApplicantsPages);
    }
    if (currentApplicantsPage >= totalApplicantsPages - 2) {
        startPage = Math.max(1, totalApplicantsPages - 4);
    }
    
    // Add first page if not visible
    if (startPage > 1) {
        addApplicantsPageButton(1);
        if (startPage > 2) {
            addApplicantsEllipsis();
        }
    }
    
    // Add visible page numbers
    for (let i = startPage; i <= endPage; i++) {
        addApplicantsPageButton(i, i === currentApplicantsPage);
    }
    
    // Add last page if not visible
    if (endPage < totalApplicantsPages) {
        if (endPage < totalApplicantsPages - 1) {
            addApplicantsEllipsis();
        }
        addApplicantsPageButton(totalApplicantsPages);
    }
}

function addApplicantsPageButton(pageNum, isActive = false) {
    const pageNumbersContainer = document.getElementById('applicantsPageNumbers');
    if (!pageNumbersContainer) return;
    
    const button = document.createElement('button');
    button.className = `px-3 py-2 text-sm rounded-lg transition-colors ${
        isActive 
            ? 'bg-primary text-white' 
            : 'border border-gray-300 hover:bg-gray-50'
    }`;
    button.textContent = pageNum;
    button.onclick = () => goToApplicantsPage(pageNum);
    pageNumbersContainer.appendChild(button);
}

function addApplicantsEllipsis() {
    const pageNumbersContainer = document.getElementById('applicantsPageNumbers');
    if (!pageNumbersContainer) return;
    
    const ellipsis = document.createElement('span');
    ellipsis.className = 'px-2 text-gray-500';
    ellipsis.textContent = '...';
    pageNumbersContainer.appendChild(ellipsis);
}

function goToApplicantsPage(pageNum) {
    currentApplicantsPage = pageNum;
    displayFilteredApplicants();
}

function changeApplicantsPage(direction) {
    if (direction === 'prev' && currentApplicantsPage > 1) {
        currentApplicantsPage--;
        displayFilteredApplicants();
    } else if (direction === 'next' && currentApplicantsPage < totalApplicantsPages) {
        currentApplicantsPage++;
        displayFilteredApplicants();
    }
}

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
    
    // Set dashboard as active and show dashboard by default
    const dashboardBtn = document.querySelector('[onclick="showSection(\'dashboard\')"]');
    if (dashboardBtn) {
        dashboardBtn.classList.add('active', 'text-white');
        dashboardBtn.classList.remove('text-gray-700');
    }
    
    // Show dashboard section
    document.querySelectorAll('.section').forEach(section => {
        section.classList.add('hidden');
    });
    const dashboardSection = document.getElementById('dashboardSection');
    if (dashboardSection) {
        dashboardSection.classList.remove('hidden');
    }
    
    // Load dashboard data
    loadDashboardData();
    
    // Auto-refresh dashboard every 30 seconds when on dashboard page
    if (window.location.pathname.includes('admin') && !window.location.pathname.includes('user')) {
        setInterval(loadDashboardData, 30000);
    }
});

// Global variables for applicant filtering
let currentStatusFilter = 'all';
let currentNameSearch = '';
let currentFromDate = '';
let currentToDate = '';
let allApplicantsData = [];

// Global variables for applicant pagination
let currentApplicantsPage = 1;
let applicantsPerPage = 5;
let totalApplicantsPages = 1;

// Apply all filters combined
function applyAllFilters() {
    // Get filter values
    const nameSearch = document.getElementById('nameSearch')?.value || '';
    const statusFilter = document.getElementById('statusFilter')?.value || 'all';
    const fromDate = document.getElementById('fromDate')?.value || '';
    const toDate = document.getElementById('toDate')?.value || '';
    
    // Update current filter values
    currentNameSearch = nameSearch.toLowerCase();
    currentStatusFilter = statusFilter;
    currentFromDate = fromDate;
    currentToDate = toDate;
    
    // Reset to page 1 when filters change
    currentApplicantsPage = 1;
    
    // Apply filters
    displayFilteredApplicants();
}

// Clear all filters
function clearAllFilters() {
    // Reset input values
    const nameSearch = document.getElementById('nameSearch');
    const statusFilter = document.getElementById('statusFilter');
    const fromDate = document.getElementById('fromDate');
    const toDate = document.getElementById('toDate');
    
    if (nameSearch) nameSearch.value = '';
    if (statusFilter) statusFilter.value = 'all';
    if (fromDate) fromDate.value = '';
    if (toDate) toDate.value = '';
    
    // Reset filter variables
    currentNameSearch = '';
    currentStatusFilter = 'all';
    currentFromDate = '';
    currentToDate = '';
    
    // Refresh display
    displayFilteredApplicants();
}

// Filter applicants by status (legacy function, now calls applyAllFilters)
function filterApplicantsByStatus(status) {
    currentStatusFilter = status;
    
    // Update dropdown selection
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        statusFilter.value = status;
    }
    
    // Apply all filters
    applyAllFilters();
}

// Display filtered applicants with all filters applied
function displayFilteredApplicants() {
    const tbody = document.getElementById('applicantsTableBody');
    
    if (!allApplicantsData || allApplicantsData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-500">No applicants found</td></tr>';
        return;
    }
    
    let filteredApplicants = allApplicantsData;
    
    // Apply status filter
    if (currentStatusFilter !== 'all') {
        filteredApplicants = filteredApplicants.filter(applicant => applicant.status === currentStatusFilter);
    }
    
    // Apply name search filter
    if (currentNameSearch) {
        filteredApplicants = filteredApplicants.filter(applicant => 
            applicant.full_name.toLowerCase().includes(currentNameSearch)
        );
    }
    
    // Apply date range filter
    if (currentFromDate || currentToDate) {
        filteredApplicants = filteredApplicants.filter(applicant => {
            const appliedDate = new Date(applicant.applied_date);
            let matchesDateRange = true;
            
            if (currentFromDate) {
                const fromDate = new Date(currentFromDate);
                matchesDateRange = matchesDateRange && appliedDate >= fromDate;
            }
            
            if (currentToDate) {
                const toDate = new Date(currentToDate);
                // Set toDate to end of day to include the entire day
                toDate.setHours(23, 59, 59, 999);
                matchesDateRange = matchesDateRange && appliedDate <= toDate;
            }
            
            return matchesDateRange;
        });
    }
    
    if (filteredApplicants.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-gray-500">No applicants found matching your filters</td></tr>`;
        hideApplicantsPagination();
        return;
    }
    
    // Calculate pagination
    totalApplicantsPages = Math.ceil(filteredApplicants.length / applicantsPerPage);
    const startIndex = (currentApplicantsPage - 1) * applicantsPerPage;
    const endIndex = startIndex + applicantsPerPage;
    const paginatedApplicants = filteredApplicants.slice(startIndex, endIndex);
    
    // Show/hide pagination based on total applicants
    if (filteredApplicants.length > applicantsPerPage) {
        showApplicantsPagination();
        updateApplicantsPaginationInfo(startIndex + 1, Math.min(endIndex, filteredApplicants.length), filteredApplicants.length);
        updateApplicantsPaginationButtons();
    } else {
        hideApplicantsPagination();
    }
    
    tbody.innerHTML = paginatedApplicants.map(applicant => {
        const profilePictureHTML = applicant.profile_picture 
            ? `<img src="../user/uploads/profile_pictures/${applicant.profile_picture}" alt="Profile" class="w-10 h-10 rounded-full object-cover mr-3">`
            : `<div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                 <span class="text-blue-600 font-semibold text-sm">${getInitials(applicant.full_name)}</span>
               </div>`;
        
        // Department badge with color coding
        let departmentBadge = '';
        if (applicant.assigned_to_department) {
            let deptColor = 'bg-gray-100 text-gray-800';
            if (applicant.assigned_to_department === 'Computing Studies') {
                deptColor = 'bg-blue-100 text-blue-800';
            } else if (applicant.assigned_to_department === 'Education') {
                deptColor = 'bg-green-100 text-green-800';
            } else if (applicant.assigned_to_department === 'Hospitality Management') {
                deptColor = 'bg-purple-100 text-purple-800';
            }
            departmentBadge = `<span class="px-2 py-1 text-xs font-semibold rounded-full ${deptColor}">${applicant.assigned_to_department}</span>`;
        } else {
            departmentBadge = '<span class="text-gray-400 text-sm italic">Not assigned</span>';
        }
        
        return `
        <tr class="hover:bg-gray-50 cursor-pointer" onclick="viewApplicantDetails(${applicant.id})">
            <td class="px-6 py-4">
                <div class="flex items-center">
                    ${profilePictureHTML}
                    <div>
                        <div class="font-medium text-gray-900">${applicant.full_name}</div>
                        <div class="text-gray-500 text-sm">${applicant.applicant_email}</div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 text-gray-900">${applicant.position}</td>
            <td class="px-6 py-4">${departmentBadge}</td>
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
        `;
    }).join('');
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

// Salary Range Input Formatting
document.addEventListener('DOMContentLoaded', function() {
    // Function to format number with commas
    function formatNumberWithCommas(value) {
        // Remove non-digits
        value = value.replace(/\D/g, '');
        // Add commas
        return value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // Function to update hidden salary field
    function updateSalaryRange(minId, maxId, rangeId) {
        const minInput = document.getElementById(minId);
        const maxInput = document.getElementById(maxId);
        const rangeInput = document.getElementById(rangeId);
        
        if (minInput && maxInput && rangeInput) {
            const minVal = minInput.value.replace(/,/g, '');
            const maxVal = maxInput.value.replace(/,/g, '');
            
            if (minVal && maxVal) {
                // Store without peso sign - let frontend add it when displaying
                rangeInput.value = `${formatNumberWithCommas(minVal)} - ${formatNumberWithCommas(maxVal)}`;
            }
        }
    }

    // Attach event listeners to all salary inputs
    const salaryInputs = document.querySelectorAll('.salary-input');
    salaryInputs.forEach(input => {
        // Format on input
        input.addEventListener('input', function(e) {
            const cursorPosition = e.target.selectionStart;
            const oldLength = e.target.value.length;
            
            // Format the value
            e.target.value = formatNumberWithCommas(e.target.value);
            
            // Restore cursor position
            const newLength = e.target.value.length;
            const newPosition = cursorPosition + (newLength - oldLength);
            e.target.setSelectionRange(newPosition, newPosition);
            
            // Update corresponding hidden field
            const inputId = e.target.id;
            if (inputId) {
                const num = inputId.match(/\d+/)[0];
                updateSalaryRange(`salaryMin${num}`, `salaryMax${num}`, `salaryRange${num}`);
            }
        });

        // Only allow numbers and commas
        input.addEventListener('keypress', function(e) {
            if (!/[\d,]/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Delete' && e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') {
                e.preventDefault();
            }
        });
    });

    // Update hidden fields on form submission
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Update all salary range fields before submission
            for (let i = 1; i <= 6; i++) {
                updateSalaryRange(`salaryMin${i}`, `salaryMax${i}`, `salaryRange${i}`);
            }
        });
    });
});


