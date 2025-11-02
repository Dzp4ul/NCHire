/**
 * Job Manager Module
 * Handles job listing, search, and filters
 */

let currentPage = 1;
let jobsPerPage = 9;
let allJobs = [];

// Load jobs on page load
document.addEventListener('DOMContentLoaded', function() {
    loadJobs();
    setupEventListeners();
});

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Search
    const searchInput = document.getElementById('jobSearch');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(filterJobs, 300));
    }
    
    // Filters
    const departmentFilter = document.getElementById('departmentFilter');
    const typeFilter = document.getElementById('typeFilter');
    
    if (departmentFilter) {
        departmentFilter.addEventListener('change', filterJobs);
    }
    
    if (typeFilter) {
        typeFilter.addEventListener('change', filterJobs);
    }
    
    // Pagination
    const prevBtn = document.getElementById('prevPage');
    const nextBtn = document.getElementById('nextPage');
    
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                displayJobs();
            }
        });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            const totalPages = Math.ceil(allJobs.length / jobsPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                displayJobs();
            }
        });
    }
}

/**
 * Load jobs from server
 */
function loadJobs() {
    fetch('get_jobs_paginated.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allJobs = data.jobs;
                displayJobs();
            } else {
                showError('Failed to load jobs');
            }
        })
        .catch(error => {
            console.error('Error loading jobs:', error);
            showError('Failed to load jobs');
        });
}

/**
 * Filter jobs based on search and filters
 */
function filterJobs() {
    const searchTerm = document.getElementById('jobSearch')?.value.toLowerCase() || '';
    const department = document.getElementById('departmentFilter')?.value || '';
    const type = document.getElementById('typeFilter')?.value || '';
    
    allJobs = allJobs.filter(job => {
        const matchesSearch = job.job_title.toLowerCase().includes(searchTerm) ||
                            job.department_role.toLowerCase().includes(searchTerm);
        const matchesDepartment = !department || job.department_role === department;
        const matchesType = !type || job.job_type === type;
        
        return matchesSearch && matchesDepartment && matchesType;
    });
    
    currentPage = 1;
    displayJobs();
}

/**
 * Display jobs on current page
 */
function displayJobs() {
    const container = document.getElementById('jobsContainer');
    if (!container) return;
    
    const start = (currentPage - 1) * jobsPerPage;
    const end = start + jobsPerPage;
    const jobsToShow = allJobs.slice(start, end);
    
    if (jobsToShow.length === 0) {
        container.innerHTML = `
            <div class="col-span-full text-center py-12">
                <i class="ri-briefcase-line text-6xl text-gray-300 mb-4 block"></i>
                <p class="text-gray-600">No jobs found</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = jobsToShow.map(job => `
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
            <h3 class="text-xl font-semibold text-gray-900 mb-2">${escapeHtml(job.job_title)}</h3>
            <p class="text-gray-600 mb-4">${escapeHtml(job.department_role)}</p>
            <div class="flex items-center gap-4 text-sm text-gray-500 mb-4">
                <span><i class="ri-map-pin-line"></i> ${escapeHtml(job.locations || 'Norzagaray')}</span>
                <span><i class="ri-time-line"></i> ${escapeHtml(job.job_type || 'Full-time')}</span>
            </div>
            <div class="flex gap-2">
                <button onclick="viewJobDetails(${job.id})" class="flex-1 px-4 py-2 border border-primary text-primary rounded-lg hover:bg-primary hover:text-white transition-colors">
                    View Details
                </button>
                <button onclick="applyForJob(${job.id}, '${escapeHtml(job.job_title)}')" class="flex-1 px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Apply Now
                </button>
            </div>
        </div>
    `).join('');
    
    updatePagination();
}

/**
 * Update pagination controls
 */
function updatePagination() {
    const totalPages = Math.ceil(allJobs.length / jobsPerPage);
    const info = document.getElementById('paginationInfo');
    const prevBtn = document.getElementById('prevPage');
    const nextBtn = document.getElementById('nextPage');
    
    if (info) {
        const start = (currentPage - 1) * jobsPerPage + 1;
        const end = Math.min(start + jobsPerPage - 1, allJobs.length);
        info.textContent = `Showing ${start}-${end} of ${allJobs.length} jobs`;
    }
    
    if (prevBtn) {
        prevBtn.disabled = currentPage === 1;
    }
    
    if (nextBtn) {
        nextBtn.disabled = currentPage >= totalPages;
    }
}

/**
 * View job details
 */
function viewJobDetails(jobId) {
    window.location.href = `user.php?view=job_details&job_id=${jobId}`;
}

/**
 * Apply for job
 */
function applyForJob(jobId, jobTitle) {
    // Store job info and show application wizard
    window.currentJobId = jobId;
    window.currentJobTitle = jobTitle;
    
    // This would open your application modal/wizard
    showToast('Opening application form for ' + jobTitle, 'info');
}

/**
 * Show error message
 */
function showError(message) {
    const container = document.getElementById('jobsContainer');
    if (container) {
        container.innerHTML = `
            <div class="col-span-full text-center py-12">
                <i class="ri-error-warning-line text-6xl text-red-300 mb-4 block"></i>
                <p class="text-red-600">${escapeHtml(message)}</p>
            </div>
        `;
    }
}
