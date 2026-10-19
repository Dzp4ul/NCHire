<?php
// user_application.php
// Start session or include any necessary PHP logic here if needed
session_start();
?>

<div class="ui-page-stack ui-applications-page">
<header class="ui-page-header ui-applications-hero">
  <div class="ui-page-header__main">
    <p class="ui-eyebrow">Your recruitment activity</p>
    <h1 class="ui-page-title">My Applications</h1>
    <p class="ui-page-description">Track your applications, monitor their progress, and manage your required documents—all in one place.</p>
  </div>
</header>
<section class="ui-applications-workspace" aria-labelledby="applicationsListTitle">
  <div class="ui-application-toolbar" role="search" aria-label="Search and filter applications">
    <label class="ui-application-control ui-application-search" for="applicationSearch">
      <span class="sr-only">Search applications</span>
      <i class="ri-search-line" aria-hidden="true"></i>
      <input id="applicationSearch" type="search" autocomplete="off" placeholder="Search applications by title, course code, or application ID...">
      <button type="button" id="clearApplicationSearch" class="ui-application-search__clear" aria-label="Clear application search" hidden>
        <i class="ri-close-line" aria-hidden="true"></i>
      </button>
    </label>
    <label class="ui-application-control ui-application-select" for="applicationStatusFilter">
      <i class="ri-filter-3-line" aria-hidden="true"></i>
      <span class="sr-only">Filter by status</span>
      <select id="applicationStatusFilter">
        <option value="">All statuses</option>
      </select>
      <i class="ri-arrow-down-s-line ui-application-select__arrow" aria-hidden="true"></i>
    </label>
    <label class="ui-application-control ui-application-select" for="applicationSort">
      <i class="ri-arrow-up-down-line" aria-hidden="true"></i>
      <span class="sr-only">Sort applications</span>
      <select id="applicationSort">
        <option value="newest">Newest Applications</option>
        <option value="oldest">Oldest Applications</option>
        <option value="title-asc">Application Title (A–Z)</option>
        <option value="title-desc">Application Title (Z–A)</option>
      </select>
      <i class="ri-arrow-down-s-line ui-application-select__arrow" aria-hidden="true"></i>
    </label>
  </div>

  <div class="ui-applications-list-header">
    <div>
      <p class="ui-eyebrow">Application history</p>
      <h2 id="applicationsListTitle">Submitted Applications</h2>
    </div>
    <p id="applicationResultsCount" class="ui-application-results-count" aria-live="polite">Loading applications…</p>
  </div>
  <div id="applicationsContainer" class="ui-application-list">
    <div class="ui-application-state" role="status">
      <i class="ri-loader-4-line ui-application-state__spinner" aria-hidden="true"></i>
      <div><strong>Loading your applications</strong><p>Please wait while we retrieve your recruitment activity.</p></div>
    </div>
  </div>
</section>
</div>

<!-- Add/Update Attachments Modal -->
<div id="attachmentsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center p-4" style="z-index: 100000;">
  <div class="bg-white rounded-xl shadow-2xl max-w-5xl w-full max-h-[90vh] flex flex-col">
    <div class="bg-gradient-to-r from-blue-700 to-blue-900 px-6 py-4 rounded-t-xl flex items-center justify-between">
      <div>
        <h3 class="text-xl font-bold text-white">Add/Update Attachments</h3>
        <p class="text-sm text-blue-100">Keep unchanged files and select only the documents you need to update.</p>
      </div>
      <button type="button" data-close-attachments class="text-white hover:text-blue-100" aria-label="Close attachments">
        <i class="ri-close-line text-2xl"></i>
      </button>
    </div>
    <form id="attachmentsForm" class="min-h-0 flex flex-col">
      <input type="hidden" name="application_id" id="attachmentsApplicationId">
      <div class="overflow-y-auto p-6">
        <div class="grid grid-cols-[minmax(160px,1.1fr),minmax(200px,1.5fr),auto,auto] gap-3 px-3 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 border-b">
          <div>Document Name</div>
          <div>Current / Existing File</div>
          <div>Date Uploaded</div>
          <div>Status / Action</div>
        </div>
        <div id="attachmentsList" class="divide-y divide-gray-200">
          <div class="py-12 text-center text-gray-500"><i class="ri-loader-4-line animate-spin text-3xl"></i><p class="mt-2">Loading attachments...</p></div>
        </div>
      </div>
      <div class="px-6 py-4 border-t bg-gray-50 rounded-b-xl flex items-center justify-between gap-3">
        <p class="text-xs text-gray-500">Accepted: PDF, DOC, DOCX, JPG, PNG. Maximum 5MB per file.</p>
        <div class="flex gap-3">
          <button type="button" data-close-attachments class="px-5 py-2 border border-gray-300 rounded-lg hover:bg-white">Cancel</button>
          <button type="submit" id="saveAttachmentsBtn" class="px-5 py-2 bg-blue-700 text-white rounded-lg hover:bg-blue-800 font-semibold">
            <i class="ri-save-line mr-1"></i>Save Attachment Updates
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Application Progress View Modal (Full-Screen Wizard Style) -->
<div id="applicationProgressModal" class="fixed inset-0 bg-gray-50 hidden" style="position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; z-index: 99999 !important; background-color: #f9fafb !important;">
  <div class="min-h-screen bg-white" style="min-height: 100vh !important; background-color: white !important;">
    <!-- Progress Header -->
    <div class="bg-gradient-to-r from-blue-800 to-blue-900 text-white sticky top-0 z-10 shadow-lg" style="background: linear-gradient(to right, #1e40af, #1e3a8a) !important; color: white !important; padding: 1rem !important;">
      <div class="flex items-center justify-between mb-3">
        <button onclick="closeProgressModal()" class="text-white hover:text-gray-200 transition-colors flex items-center">
          <i class="ri-arrow-left-line text-lg mr-2"></i>Back to My Applications
        </button>
        <div class="text-sm opacity-80" id="progressJobTitle">
          Application Progress: <span>-</span>
        </div>
      </div>
      
      <!-- Progress Steps (Duplicate of Wizard) -->
      <div class="max-w-6xl mx-auto" style="max-width: 72rem !important; margin-left: auto !important; margin-right: auto !important;">
        <div class="flex items-center justify-between" style="display: flex !important; align-items: center !important; justify-content: space-between !important;">
          <!-- Step 1 -->
          <div class="flex-1 flex items-center" style="flex: 1 !important; display: flex !important; align-items: center !important;">
            <div class="w-7 h-7 rounded-full flex items-center justify-center font-semibold progress-step-dot" data-step="1" style="width: 1.75rem !important; height: 1.75rem !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-weight: 600 !important; background: rgba(255,255,255,0.3) !important; color: white !important; font-size: 0.875rem !important;">1</div>
            <div class="flex-1 h-0.5 mx-1 bg-white bg-opacity-30 progress-step-line" data-after="1" style="flex: 1 !important; height: 2px !important; margin: 0 0.25rem !important; background-color: rgba(255, 255, 255, 0.3) !important;"></div>
          </div>
          <!-- Step 2 -->
          <div class="flex-1 flex items-center" style="flex: 1 !important; display: flex !important; align-items: center !important;">
            <div class="w-7 h-7 rounded-full flex items-center justify-center font-semibold progress-step-dot" data-step="2" style="width: 1.75rem !important; height: 1.75rem !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-weight: 600 !important; background: rgba(255,255,255,0.3) !important; color: white !important; font-size: 0.875rem !important;">2</div>
            <div class="flex-1 h-0.5 mx-1 bg-white bg-opacity-30 progress-step-line" data-after="2" style="flex: 1 !important; height: 2px !important; margin: 0 0.25rem !important; background-color: rgba(255, 255, 255, 0.3) !important;"></div>
          </div>
          <!-- Step 3 -->
          <div class="flex-1 flex items-center" style="flex: 1 !important; display: flex !important; align-items: center !important;">
            <div class="w-7 h-7 rounded-full flex items-center justify-center font-semibold progress-step-dot" data-step="3" style="width: 1.75rem !important; height: 1.75rem !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-weight: 600 !important; background: rgba(255,255,255,0.3) !important; color: white !important; font-size: 0.875rem !important;">3</div>
            <div class="flex-1 h-0.5 mx-1 bg-white bg-opacity-30 progress-step-line" data-after="3" style="flex: 1 !important; height: 2px !important; margin: 0 0.25rem !important; background-color: rgba(255, 255, 255, 0.3) !important;"></div>
          </div>
          <!-- Step 4 -->
          <div class="flex-1 flex items-center" style="flex: 1 !important; display: flex !important; align-items: center !important;">
            <div class="w-7 h-7 rounded-full flex items-center justify-center font-semibold progress-step-dot" data-step="4" style="width: 1.75rem !important; height: 1.75rem !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-weight: 600 !important; background: rgba(255,255,255,0.3) !important; color: white !important; font-size: 0.875rem !important;">4</div>
            <div class="flex-1 h-0.5 mx-1 bg-white bg-opacity-30 progress-step-line" data-after="4" style="flex: 1 !important; height: 2px !important; margin: 0 0.25rem !important; background-color: rgba(255, 255, 255, 0.3) !important;"></div>
          </div>
          <!-- Step 5 -->
          <div class="flex-1 flex items-center" style="flex: 1 !important; display: flex !important; align-items: center !important;">
            <div class="w-7 h-7 rounded-full flex items-center justify-center font-semibold progress-step-dot" data-step="5" style="width: 1.75rem !important; height: 1.75rem !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-weight: 600 !important; background: rgba(255,255,255,0.3) !important; color: white !important; font-size: 0.875rem !important;">5</div>
            <div class="flex-1 h-0.5 mx-1 bg-white bg-opacity-30 progress-step-line" data-after="5" style="flex: 1 !important; height: 2px !important; margin: 0 0.25rem !important; background-color: rgba(255, 255, 255, 0.3) !important;"></div>
          </div>
        </div>
        <div class="mt-2 text-center text-blue-100 text-xs" id="progressStepLabel" style="margin-top: 0.5rem !important; text-align: center !important; color: #bfdbfe !important; font-size: 0.75rem !important;">
          Step 1 of 5: Documents Under Review
        </div>
      </div>
    </div>

    <!-- Progress Content -->
    <div class="p-4 pb-16" style="min-height: 400px; background: #f8fafc !important; padding: 1.5rem !important; padding-bottom: 4rem !important; padding-top: 1rem !important;">
      <div class="max-w-4xl mx-auto" style="position: relative; z-index: 1; max-width: 56rem; margin: 0 auto;">
        <div id="progressContent" class="bg-white rounded-lg border border-gray-200 p-6">
          <!-- Progress content will be populated by JavaScript -->
          <div class="text-center py-8">
            <i class="ri-loader-4-line text-4xl text-gray-400 animate-spin mb-4"></i>
            <p class="text-gray-500">Loading application progress...</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Toast Notification Animations -->
<style>
@keyframes slideInRight {
  from {
    transform: translateX(400px);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

@keyframes slideOutRight {
  from {
    transform: translateX(0);
    opacity: 1;
  }
  to {
    transform: translateX(400px);
    opacity: 0;
  }
}
</style>

<!-- Cancel Confirmation Modal -->
<div id="deleteConfirmModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
    <div class="p-6">
      <!-- Orange icon circle -->
      <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="ri-close-circle-line text-3xl text-orange-600"></i>
      </div>
      
      <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Cancel Application?</h3>
      <p class="text-gray-600 text-center mb-6">Are you sure you want to cancel this application? You can apply again later if needed.</p>
      
      <div class="flex gap-3">
        <button type="button" onclick="closeDeleteModal()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-medium">
          Go Back
        </button>
        <button type="button" onclick="confirmDelete()" class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 font-medium">
          Cancel Application
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Upload Psych Exam Receipt Modal -->
<div id="uploadPsychModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
    <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-6 py-4 rounded-t-xl">
      <h3 class="text-xl font-bold text-white">Upload Psychological Exam Receipt</h3>
    </div>
    
    <form id="uploadPsychForm" class="p-6">
      <input type="hidden" id="psych_application_id">
      
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Upload Receipt/Proof <span class="text-red-500">*</span>
        </label>
        <input type="file" id="psych_receipt" accept=".pdf,.jpg,.jpeg,.png" required
               class="w-full border border-gray-300 rounded-lg p-3">
        <p class="text-xs text-gray-500 mt-1">Accepted formats: PDF, JPG, PNG (Max 5MB)</p>
      </div>
      
      <div class="flex gap-3">
        <button type="button" onclick="closePsychModal()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
          Cancel
        </button>
        <button type="submit" class="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
          Upload Receipt
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function() {
  // Small delay to ensure DOM is fully inserted
  setTimeout(function() {
    console.log('=== MY APPLICATIONS SCRIPT LOADED ===');
    const container = document.getElementById('applicationsContainer');
    
    if (!container) {
      console.error('Applications container not found!');
      return;
    }
    
    console.log('Applications container found');

  const searchInput = document.getElementById('applicationSearch');
  const clearSearchButton = document.getElementById('clearApplicationSearch');
  const statusFilter = document.getElementById('applicationStatusFilter');
  const sortControl = document.getElementById('applicationSort');
  const resultsCount = document.getElementById('applicationResultsCount');
  const applicationCollator = new Intl.Collator(undefined, { sensitivity: 'base', numeric: true });
  let allApplications = [];

  const statusToClasses = (status) => {
    const s = (status || '').toLowerCase();
    if (s.includes('cancel')) return 'bg-gray-100 text-gray-800';
    if (s.includes('interview')) return 'bg-blue-100 text-blue-800';
    if (s.includes('resubmission')) return 'bg-orange-100 text-orange-800';
    if (s.includes('reject')) return 'bg-red-100 text-red-800';
    if (s.includes('accept') || s.includes('hired')) return 'bg-green-100 text-green-800';
    return 'bg-yellow-100 text-yellow-800';
  };

  const statusPresentation = (status) => {
    const normalized = String(status || '').toLowerCase();
    if (normalized.includes('reject')) return { tone: 'is-rejected', icon: 'ri-close-circle-fill' };
    if (normalized.includes('hired') || normalized.includes('accept')) return { tone: 'is-hired', icon: 'ri-team-fill' };
    if (normalized.includes('interview')) return { tone: 'is-interview', icon: 'ri-calendar-event-fill' };
    if (normalized.includes('demo')) return { tone: 'is-demo', icon: 'ri-presentation-fill' };
    if (normalized.includes('passed')) return { tone: 'is-passed', icon: 'ri-checkbox-circle-fill' };
    if (normalized.includes('resubmission')) return { tone: 'is-attention', icon: 'ri-error-warning-fill' };
    if (normalized.includes('cancel')) return { tone: 'is-cancelled', icon: 'ri-forbid-2-fill' };
    return { tone: 'is-pending', icon: 'ri-time-fill' };
  };

  function applicationTitleParts(app) {
    const courseCode = String(app.course_code || '').trim();
    let title = String(app.position || app.job_title || 'Unknown Position').trim();
    if (courseCode && title.toLocaleLowerCase().startsWith(courseCode.toLocaleLowerCase())) {
      const withoutCode = title.slice(courseCode.length).replace(/^\s*[-–—:]\s*/, '').trim();
      if (withoutCode) title = withoutCode;
    }
    return { courseCode, title };
  }

  function compensationDisplay(app) {
    const details = app.salary_projection_details || {};
    if (details.calculation_type === 'salary_grade') {
      return {
        value: details.salary_grade || details.salary_display || 'Salary grade to be determined',
        isSalaryGrade: true,
      };
    }

    let value = String(details.salary_display || '').trim();
    if (!value && app.applicable_hourly_rate !== null && app.applicable_hourly_rate !== undefined && app.applicable_hourly_rate !== '') {
      const rate = Number(app.applicable_hourly_rate);
      value = Number.isFinite(rate)
        ? `₱${rate.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })}/hour`
        : '';
    }

    return {
      value: (value || 'Rate to be determined').replace(/\$/g, '₱'),
      isSalaryGrade: false,
    };
  }

  function renderApplicationCards(apps) {
    if (!apps || apps.length === 0) {
      const hasActiveFilters = Boolean((searchInput?.value || '').trim() || statusFilter?.value);
      container.innerHTML = `
        <div class="ui-application-state">
          <span class="ui-application-state__icon"><i class="${hasActiveFilters ? 'ri-search-eye-line' : 'ri-inbox-2-line'}" aria-hidden="true"></i></span>
          <div>
            <strong>${hasActiveFilters ? 'No matching applications' : 'No applications yet'}</strong>
            <p>${hasActiveFilters ? 'Try a different search term or clear your status filter.' : 'Applications you submit will appear here with their current progress.'}</p>
            ${hasActiveFilters ? '<button type="button" class="ui-application-empty-action" data-reset-application-filters>Clear search and filters</button>' : ''}
          </div>
        </div>
      `;
      return;
    }

    container.innerHTML = apps.map(app => {
      const status = (app.status || '').toLowerCase();
      const showCancelBtn = !(status.includes('cancel') || status.includes('hired'));
      const title = applicationTitleParts(app);
      const statusStyle = statusPresentation(app.status);
      const compensation = compensationDisplay(app);
      const attachmentCount = Math.max(0, Number.parseInt(app.attachment_count, 10) || 0);
      const attachmentLabel = `${attachmentCount} ${attachmentCount === 1 ? 'attachment' : 'attachments'}`;

      return `
      <article class="ui-application-item" data-id="${app.id}">
        <div class="ui-application-item__main">
          <span class="ui-application-item__icon" aria-hidden="true"><i class="ri-briefcase-4-line"></i></span>
          <div class="ui-application-item__title">
            <span class="ui-application-item__id">Application #${escapeHtml(app.id)}</span>
            <h3>${title.courseCode ? `<span class="ui-application-item__course">${escapeHtml(title.courseCode)}</span><span class="ui-application-item__separator" aria-hidden="true">·</span>` : ''}<span>${escapeHtml(title.title)}</span></h3>
            <div class="ui-application-item__meta">
              <span class="ui-application-meta-item">
                <span class="ui-application-meta-item__icon"><i class="ri-calendar-line" aria-hidden="true"></i></span>
                <span><small>Applied</small><strong>${escapeHtml(app.applied_date_pretty || app.applied_date || 'Date unavailable')}</strong></span>
              </span>
              <span class="ui-application-meta-item">
                <span class="ui-application-meta-item__icon ui-application-meta-item__icon--compensation">${compensation.isSalaryGrade ? '<i class="ri-scales-3-line" aria-hidden="true"></i>' : '<span aria-hidden="true">₱</span>'}</span>
                <span><small>${compensation.isSalaryGrade ? 'Salary grade' : 'Compensation'}</small><strong>${escapeHtml(compensation.value)}</strong></span>
              </span>
            </div>
          </div>
        </div>
        <div class="ui-application-item__status">
          <span class="ui-application-status ${statusStyle.tone}"><i class="${statusStyle.icon}" aria-hidden="true"></i>${escapeHtml(app.status || 'Pending')}</span>
        </div>
        <div class="ui-application-item__actions">
          <button type="button" class="ui-application-action ui-application-action--progress" data-action="view"><i class="ri-eye-line" aria-hidden="true"></i><span>View Progress</span></button>
          <button type="button" class="ui-application-action ui-application-action--attachments" data-action="attachments" aria-label="Manage ${escapeHtml(attachmentLabel)} for application ${escapeHtml(app.id)}"><i class="ri-attachment-2" aria-hidden="true"></i><span>Attachments <span class="ui-application-action__count">${attachmentCount}</span></span></button>
          ${showCancelBtn ? `<details class="ui-action-menu ui-application-more"><summary aria-label="More actions for application ${escapeHtml(app.id)}"><i class="ri-more-2-fill" aria-hidden="true"></i></summary><div class="ui-action-menu__items"><button type="button" class="ui-action-menu__danger" data-action="cancel"><i class="ri-close-circle-line" aria-hidden="true"></i>Cancel application</button></div></details>` : ''}
        </div>
      </article>
    `;
    }).join('');
  }

  function updateStatusOptions(apps) {
    if (!statusFilter) return;
    const selectedStatus = statusFilter.value;
    const statuses = [...new Set((apps || []).map(app => String(app.status || 'Pending').trim()).filter(Boolean))]
      .sort(applicationCollator.compare);
    statusFilter.replaceChildren(new Option('All statuses', ''));
    statuses.forEach(status => statusFilter.add(new Option(status, status)));
    statusFilter.value = statuses.includes(selectedStatus) ? selectedStatus : '';
  }

  function applyApplicationControls() {
    const query = String(searchInput?.value || '').trim().toLocaleLowerCase();
    const selectedStatus = String(statusFilter?.value || '').toLocaleLowerCase();
    const sortValue = sortControl?.value || 'newest';
    const filtered = allApplications.filter(app => {
      const searchable = [app.id, `application ${app.id}`, app.position, app.course_code, app.course_name, app.job_title]
        .filter(value => value !== null && value !== undefined)
        .join(' ')
        .toLocaleLowerCase();
      return (!query || searchable.includes(query))
        && (!selectedStatus || String(app.status || 'Pending').toLocaleLowerCase() === selectedStatus);
    });

    filtered.sort((a, b) => {
      if (sortValue === 'title-asc' || sortValue === 'title-desc') {
        const firstTitle = applicationTitleParts(a);
        const secondTitle = applicationTitleParts(b);
        const comparison = applicationCollator.compare(
          [firstTitle.courseCode, firstTitle.title].filter(Boolean).join(' '),
          [secondTitle.courseCode, secondTitle.title].filter(Boolean).join(' ')
        );
        return sortValue === 'title-desc' ? -comparison : comparison;
      }
      const firstDate = Date.parse(`${a.applied_date || ''}T00:00:00`) || 0;
      const secondDate = Date.parse(`${b.applied_date || ''}T00:00:00`) || 0;
      const dateComparison = firstDate - secondDate || Number(a.id || 0) - Number(b.id || 0);
      return sortValue === 'oldest' ? dateComparison : -dateComparison;
    });

    renderApplicationCards(filtered);
    if (resultsCount) {
      const total = allApplications.length;
      resultsCount.textContent = filtered.length === total
        ? `${total} ${total === 1 ? 'application' : 'applications'}`
        : `${filtered.length} of ${total} applications`;
    }
    if (clearSearchButton) clearSearchButton.hidden = !String(searchInput?.value || '').length;
  }

  function renderApplications(apps) {
    allApplications = Array.isArray(apps) ? apps : [];
    updateStatusOptions(allApplications);
    applyApplicationControls();
  }

  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  async function loadApplications() {
    try {
      container.setAttribute('aria-busy', 'true');
      const res = await fetch('get_user_applications.php', { credentials: 'same-origin' });
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'Failed to load applications');
      renderApplications(data.applications);
    } catch (err) {
      container.innerHTML = `
        <div class="ui-application-state ui-application-state--error" role="alert">
          <span class="ui-application-state__icon"><i class="ri-error-warning-line" aria-hidden="true"></i></span>
          <div><strong>Applications could not be loaded</strong><p>${escapeHtml(err.message)}</p><button type="button" class="ui-application-empty-action" data-retry-applications>Try again</button></div>
        </div>
      `;
      if (resultsCount) resultsCount.textContent = 'Unable to load applications';
    } finally {
      container.removeAttribute('aria-busy');
    }
  }

  searchInput?.addEventListener('input', applyApplicationControls);
  statusFilter?.addEventListener('change', applyApplicationControls);
  sortControl?.addEventListener('change', applyApplicationControls);
  clearSearchButton?.addEventListener('click', () => {
    searchInput.value = '';
    searchInput.focus();
    applyApplicationControls();
  });

  const attachmentsModal = document.getElementById('attachmentsModal');
  const attachmentsForm = document.getElementById('attachmentsForm');
  const attachmentsList = document.getElementById('attachmentsList');
  const attachmentsApplicationId = document.getElementById('attachmentsApplicationId');

  function closeAttachmentsModal() {
    if (!attachmentsModal) return;
    attachmentsModal.classList.add('hidden');
    attachmentsModal.classList.remove('flex');
    document.body.style.overflow = '';
    if (attachmentsForm) attachmentsForm.reset();
  }

  function renderAttachments(documents) {
    if (!attachmentsList) return;
    attachmentsList.innerHTML = (documents || []).map(documentInfo => {
      const files = (documentInfo.files || []).length
        ? documentInfo.files.map(file => `<a href="${escapeHtml(file.url)}" target="_blank" rel="noopener" class="block text-blue-700 hover:underline break-all"><i class="ri-file-text-line mr-1"></i>${escapeHtml(file.name)}</a>`).join('')
        : '<span class="text-gray-400 italic">No document submitted</span>';
      const uploadedDate = documentInfo.has_file && documentInfo.uploaded_at
        ? new Date(documentInfo.uploaded_at).toLocaleDateString()
        : '&mdash;';
      const actionLabel = documentInfo.has_file ? 'Update / Replace' : 'Add Attachment';
      const statusClass = documentInfo.has_file ? 'bg-emerald-100 text-emerald-800' : (documentInfo.required ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700');
      return `
        <div class="grid grid-cols-1 md:grid-cols-[minmax(160px,1.1fr),minmax(200px,1.5fr),auto,auto] gap-3 p-3 items-center attachment-row">
          <div>
            <div class="font-semibold text-gray-900">${escapeHtml(documentInfo.label)}</div>
            <div class="text-xs ${documentInfo.required ? 'text-red-600' : 'text-gray-500'}">${documentInfo.required ? 'Required' : 'Optional'}</div>
          </div>
          <div class="text-sm min-w-0">
            ${files}
            ${documentInfo.history_count > 1 ? `<span class="text-xs text-gray-500">${documentInfo.history_count} saved versions</span>` : ''}
          </div>
          <div class="text-sm text-gray-600">${uploadedDate}</div>
          <div class="min-w-[150px]">
            <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold ${statusClass}">${escapeHtml(documentInfo.status)}</span>
            <input type="file" name="${escapeHtml(documentInfo.document_type)}${documentInfo.multiple ? '[]' : ''}" ${documentInfo.multiple ? 'multiple' : ''} accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="attachment-file-input hidden">
            <button type="button" class="choose-attachment mt-2 block px-3 py-1.5 text-xs font-semibold border border-blue-300 text-blue-700 rounded-lg hover:bg-blue-50">${actionLabel}</button>
            <div class="selected-attachment-name text-xs text-emerald-700 mt-1 break-all"></div>
          </div>
        </div>`;
    }).join('');

    attachmentsList.querySelectorAll('.choose-attachment').forEach(button => {
      button.addEventListener('click', () => button.closest('.attachment-row')?.querySelector('.attachment-file-input')?.click());
    });
    attachmentsList.querySelectorAll('.attachment-file-input').forEach(input => {
      input.addEventListener('change', () => {
        const names = Array.from(input.files || []).map(file => file.name);
        const display = input.closest('.attachment-row')?.querySelector('.selected-attachment-name');
        if (display) display.textContent = names.length ? `Selected: ${names.join(', ')}` : '';
      });
    });
  }

  async function openAttachmentsModal(applicationId) {
    if (!attachmentsModal || !attachmentsList || !attachmentsApplicationId) return;
    attachmentsApplicationId.value = applicationId;
    attachmentsList.innerHTML = '<div class="py-12 text-center text-gray-500"><i class="ri-loader-4-line animate-spin text-3xl"></i><p class="mt-2">Loading attachments...</p></div>';
    attachmentsModal.classList.remove('hidden');
    attachmentsModal.classList.add('flex');
    document.body.style.overflow = 'hidden';
    try {
      const response = await fetch(`manage_application_attachments.php?application_id=${encodeURIComponent(applicationId)}`, { credentials: 'same-origin' });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.error || 'Unable to load attachments');
      renderAttachments(data.documents);
    } catch (error) {
      attachmentsList.innerHTML = `<div class="py-10 text-center text-red-600"><i class="ri-error-warning-line text-3xl"></i><p class="mt-2">${escapeHtml(error.message)}</p></div>`;
    }
  }

  document.querySelectorAll('[data-close-attachments]').forEach(button => button.addEventListener('click', closeAttachmentsModal));
  if (attachmentsModal) {
    attachmentsModal.addEventListener('click', event => {
      if (event.target === attachmentsModal) closeAttachmentsModal();
    });
  }
  if (attachmentsForm) {
    attachmentsForm.addEventListener('submit', async event => {
      event.preventDefault();
      const selectedFiles = attachmentsForm.querySelectorAll('input[type="file"]');
      if (![...selectedFiles].some(input => input.files && input.files.length)) {
        showToast('Select at least one attachment to add or update.', 'warning');
        return;
      }
      const saveButton = document.getElementById('saveAttachmentsBtn');
      const originalHtml = saveButton.innerHTML;
      saveButton.disabled = true;
      saveButton.innerHTML = '<i class="ri-loader-4-line animate-spin mr-1"></i>Saving...';
      try {
        const response = await fetch('manage_application_attachments.php', {
          method: 'POST',
          credentials: 'same-origin',
          body: new FormData(attachmentsForm),
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.error || 'Unable to update attachments');
        renderAttachments(data.documents);
        attachmentsForm.reset();
        showToast(data.message || 'Attachments updated successfully.', 'success');
        loadApplications();
      } catch (error) {
        showToast(error.message || 'Unable to update attachments.', 'error');
      } finally {
        saveButton.disabled = false;
        saveButton.innerHTML = originalHtml;
      }
    });
  }

  // Store the application ID to delete
  let pendingDeleteId = null;
  
  // Delegated events for view and delete
  container.addEventListener('click', async (e) => {
    if (e.target.closest('[data-reset-application-filters]')) {
      if (searchInput) searchInput.value = '';
      if (statusFilter) statusFilter.value = '';
      applyApplicationControls();
      searchInput?.focus();
      return;
    }
    if (e.target.closest('[data-retry-applications]')) {
      loadApplications();
      return;
    }
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const row = btn.closest('[data-id]');
    const id = row ? row.getAttribute('data-id') : null;
    const action = btn.getAttribute('data-action');
    if (!id) return;

    if (action === 'view') {
      console.log('🖱️ View button clicked for application:', id);
      
      // Try to use wizard function from user.php (if available)
      if (typeof window.viewExistingApplication === 'function') {
        console.log('✅ Opening application wizard...');
        window.viewExistingApplication(id);
      } 
      // Fallback: Try waiting a bit (function might be loading)
      else {
        console.log('⏳ Wizard function not ready, waiting...');
        setTimeout(() => {
          if (typeof window.viewExistingApplication === 'function') {
            console.log('✅ Wizard function now available, opening...');
            window.viewExistingApplication(id);
          } else {
            console.log('📋 Using modal progress view instead');
            viewApplicationDetails(id);
          }
        }, 200);
      }
    }

    if (action === 'attachments') {
      openAttachmentsModal(id);
    }

    if (action === 'cancel') {
      // Store the ID and show custom modal
      pendingDeleteId = id;
      document.getElementById('deleteConfirmModal').classList.remove('hidden');
    }
  });
  
  // Close delete modal
  window.closeDeleteModal = function() {
    document.getElementById('deleteConfirmModal').classList.add('hidden');
    pendingDeleteId = null;
  };
  
  // Confirm delete action
  window.confirmDelete = async function() {
    if (!pendingDeleteId) return;
    
    const id = pendingDeleteId;
    const row = document.querySelector(`[data-id="${id}"]`);
    
    // Close modal
    closeDeleteModal();
    
    try {
      const form = new FormData();
      form.append('id', id);
      form.append('action', 'cancel');
      const res = await fetch('cancel_application.php', {
        method: 'POST',
        body: form,
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'Cancel failed');
      // Update row status instead of removing
      showToast('Your application has been successfully cancelled', 'success');
      // Reload to show updated status
      loadApplications();
    } catch (err) {
      showToast('Error cancelling application: ' + err.message, 'error');
    }
  };

  function showToast(message, type = 'info') {
    const el = document.createElement('div');
    el.className = `fixed top-4 right-4 ${type === 'success' ? 'bg-green-100 text-green-800 border-green-300' : type === 'error' ? 'bg-red-100 text-red-800 border-red-300' : 'bg-blue-100 text-blue-800 border-blue-300'} px-4 py-3 rounded border shadow-lg`;
    el.style.cssText = 'z-index: 999999; min-width: 300px; animation: slideInRight 0.3s ease-out;';
    el.innerHTML = `<div class="flex items-center"><i class="${type === 'success' ? 'ri-check-circle-line' : type === 'error' ? 'ri-error-warning-line' : 'ri-information-line'} mr-2 text-lg"></i><span class="font-medium">${escapeHtml(message)}</span></div>`;
    document.body.appendChild(el);
    setTimeout(() => {
      el.style.animation = 'slideOutRight 0.3s ease-out';
      setTimeout(() => el.remove(), 300);
    }, 3000);
  }

  // Initial load and polling for near real-time updates
  loadApplications();
  const POLL_MS = 5000; // 5 seconds
  let poller = setInterval(loadApplications, POLL_MS);

  // Optional: pause polling when tab is hidden to save resources
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      clearInterval(poller);
    } else {
      loadApplications();
      poller = setInterval(loadApplications, POLL_MS);
    }
  });
  
  // View application progress (wizard-style)
  async function viewApplicationDetails(appId) {
    console.log('=== VIEW APPLICATION DETAILS ===');
    console.log('Application ID:', appId);
    
    let progressModal = document.getElementById('applicationProgressModal');
    let progressContent = document.getElementById('progressContent');
    
    console.log('Modal element:', progressModal);
    console.log('Content element:', progressContent);
    
    if (!progressModal || !progressContent) {
      console.error('❌ Modal elements not found!');
      alert('Modal not found! Please refresh the page.');
      return;
    }
    
    // CRITICAL FIX: Move modal to body if it's not already there
    if (progressModal.parentElement !== document.body) {
      console.log('🔧 Moving modal to body...');
      document.body.appendChild(progressModal);
      console.log('✅ Modal moved to body');
    }
    
    // FORCE show modal with aggressive CSS
    progressModal.classList.remove('hidden');
    progressModal.style.cssText = `
      display: block !important;
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      right: 0 !important;
      bottom: 0 !important;
      width: 100vw !important;
      height: 100vh !important;
      z-index: 99999 !important;
      background-color: #f9fafb !important;
      overflow-y: auto !important;
    `;
    
    // Hide body scrollbar
    document.body.style.overflow = 'hidden';
    
    console.log('✅ Modal should be VISIBLE NOW');
    console.log('Modal parent:', progressModal.parentElement.tagName);
    console.log('Modal display:', window.getComputedStyle(progressModal).display);
    console.log('Modal z-index:', window.getComputedStyle(progressModal).zIndex);
    
    progressContent.innerHTML = `
      <div class="text-center py-8">
        <i class="ri-loader-4-line text-4xl text-gray-400 animate-spin mb-4"></i>
        <p class="text-gray-500">Loading application progress...</p>
      </div>
    `;
    
    try {
      const res = await fetch(`get_application_details.php?id=${appId}`);
      
      if (!res.ok) {
        throw new Error('Failed to fetch application details');
      }
      
      const data = await res.json();
      console.log('API Response:', data);
      
      if (!data.success) {
        throw new Error(data.error || 'Failed to load application details');
      }
      
      const app = data.application;
      console.log('Application data loaded:', app);
      
      // Determine current step based on status
      let currentStep = 1;
      const status = (app.status || '').toLowerCase();
      
      if (status.includes('reject')) {
        currentStep = -1; // Rejected
      } else if (status.includes('initially hired') || status.includes('hired')) {
        currentStep = 5;
      } else if (status.includes('psychological') || status.includes('psych')) {
        currentStep = 4;
      } else if (status.includes('demo')) {
        currentStep = 3;
      } else if (status.includes('interview')) {
        currentStep = 2;
      } else if (status.includes('documents approved')) {
        currentStep = 1;
      } else {
        currentStep = 1; // Pending or submitted
      }
      
      console.log('Current step determined:', currentStep);
      
      // Update progress header
      const titleSpan = document.querySelector('#progressJobTitle span');
      if (titleSpan) {
        titleSpan.textContent = escapeHtml(app.position);
      }
      
      // Update progress dots and lines
      updateProgressSteps(currentStep);
      
      // Update progress content
      progressContent.innerHTML = generateProgressContent(app, currentStep);
      
      console.log('✅ Progress modal updated successfully');
      
    } catch (err) {
      console.error('❌ Error loading application details:', err);
      progressContent.innerHTML = `
        <div class="text-center py-8">
          <i class="ri-error-warning-line text-4xl text-red-500 mb-4"></i>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Unable to Load Progress</h3>
          <p class="text-gray-600 mb-4">${escapeHtml(err.message)}</p>
          <button onclick="closeProgressModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
            Close
          </button>
        </div>
      `;
    }
  }
  
  function updateProgressSteps(currentStep) {
    const stepLabels = {
      1: 'Step 1 of 5: Documents Under Review',
      2: 'Step 2 of 5: Interview Scheduled',
      3: 'Step 3 of 5: Demo Teaching Scheduled',
      4: 'Step 4 of 5: Psychological Examination',
      5: 'Step 5 of 5: Initially Hired',
      '-1': 'Application Rejected'
    };
    
    document.getElementById('progressStepLabel').textContent = stepLabels[currentStep] || stepLabels[1];
    
    // Update step dots
    document.querySelectorAll('.progress-step-dot').forEach(dot => {
      const stepNum = Number(dot.getAttribute('data-step'));
      
      if (currentStep === -1) {
        // Rejected - all red
        dot.style.background = '#ef4444';
        dot.style.color = '#fff';
      } else if (stepNum < currentStep) {
        // Completed - green
        dot.style.background = '#10b981';
        dot.style.color = '#fff';
      } else if (stepNum === currentStep) {
        // Current - yellow
        dot.style.background = '#f59e0b';
        dot.style.color = '#1e40af';
      } else {
        // Future - gray
        dot.style.background = 'rgba(255,255,255,0.3)';
        dot.style.color = '#fff';
      }
    });
    
    // Update lines between dots
    document.querySelectorAll('.progress-step-line').forEach((line, idx) => {
      const lineAfterStep = idx + 1;
      if (currentStep === -1) {
        line.style.backgroundColor = '#ef4444';
      } else if (lineAfterStep < currentStep) {
        line.style.backgroundColor = '#10b981';
      } else {
        line.style.backgroundColor = 'rgba(255, 255, 255, 0.3)';
      }
    });
  }
  
  function generateProgressContent(app, currentStep) {
    const status = (app.status || '').toLowerCase();
    
    // Step content generators
    const stepContent = {
      1: () => `
        <div class="py-6">
          <div class="text-center mb-6">
            <i class="ri-file-text-line text-6xl text-blue-500 mb-4"></i>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Application Submitted</h3>
            <p class="text-gray-600">Your application has been successfully submitted and is under review.</p>
          </div>
          
          <!-- Application Summary -->
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 max-w-2xl mx-auto mb-6">
            <h4 class="font-semibold text-gray-900 mb-3 flex items-center">
              <i class="ri-information-line mr-2 text-blue-600"></i>Application Summary
            </h4>
            <div class="text-sm text-gray-700 space-y-2">
              <p><strong>Application #:</strong> ${app.id}</p>
              <p><strong>Position:</strong> ${escapeHtml(app.position)}</p>
              <p><strong>Submitted:</strong> ${escapeHtml(app.applied_date_pretty || app.applied_date)}</p>
              <p><strong>Status:</strong> <span class="${statusToClasses(app.status)} px-2 py-1 rounded">${escapeHtml(app.status)}</span></p>
              ${app.salary_projection_details ? `
                <p><strong>Projected Compensation:</strong> ${escapeHtml(app.salary_projection_details.salary_display || 'Rate to be determined')}<sup>*</sup></p>
                <p class="text-xs text-gray-500">*${escapeHtml(app.salary_projection_details.disclaimer || 'Guide only; final compensation is subject to profile and credential verification.')}</p>
              ` : ''}
            </div>
          </div>
          
          <!-- Personal Information Submitted -->
          <div class="bg-white border border-gray-200 rounded-lg p-4 max-w-2xl mx-auto mb-4">
            <h4 class="font-semibold text-gray-900 mb-3 flex items-center">
              <i class="ri-user-line mr-2 text-blue-600"></i>Personal Information
            </h4>
            <div class="grid md:grid-cols-2 gap-4 text-sm">
              ${app.first_name || app.full_name ? `
                <div>
                  <p class="text-gray-600 font-medium">Full Name</p>
                  <p class="text-gray-900">${escapeHtml(app.first_name && app.last_name ? app.first_name + ' ' + app.last_name : app.full_name || 'Not provided')}</p>
                </div>
              ` : ''}
              ${app.applicant_email ? `
                <div>
                  <p class="text-gray-600 font-medium">Email</p>
                  <p class="text-gray-900">${escapeHtml(app.applicant_email)}</p>
                </div>
              ` : ''}
              ${app.contact_num ? `
                <div>
                  <p class="text-gray-600 font-medium">Contact Number</p>
                  <p class="text-gray-900">${escapeHtml(app.contact_num)}</p>
                </div>
              ` : ''}
              ${app.address ? `
                <div class="md:col-span-2">
                  <p class="text-gray-600 font-medium">Address</p>
                  <p class="text-gray-900">${escapeHtml(app.address)}</p>
                </div>
              ` : ''}
            </div>
          </div>
          
          <p class="text-sm text-gray-500 text-center mt-6">Please wait while we review your documents. You will be notified of the next steps.</p>
        </div>
      `,
      2: () => `
        <div class="text-center py-8">
          <i class="ri-checkbox-circle-line text-6xl text-green-500 mb-4"></i>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Documents Approved</h3>
          <p class="text-gray-600">Your submitted documents have been reviewed and approved.</p>
        </div>
      `,
      3: () => `
        <div class="text-center py-8">
          <i class="ri-calendar-line text-6xl text-blue-500 mb-4"></i>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Interview Scheduled</h3>
          ${app.interview_date ? `
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 max-w-md mx-auto mt-4">
              <p class="text-sm font-semibold text-gray-900 mb-2">Interview Details:</p>
              <p class="text-sm text-gray-700"><i class="ri-calendar-event-line mr-2"></i>${new Date(app.interview_date).toLocaleDateString('en-US', { 
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
              })}</p>
              <p class="text-sm text-gray-700"><i class="ri-time-line mr-2"></i>${new Date(app.interview_date).toLocaleTimeString('en-US', { 
                hour: '2-digit', minute: '2-digit' 
              })}</p>
              ${app.interview_notes ? `<p class="text-sm text-gray-600 mt-2">${escapeHtml(app.interview_notes)}</p>` : ''}
            </div>
          ` : `
            <p class="text-gray-600 mt-4">Waiting for admin to schedule your interview.</p>
          `}
        </div>
      `,
      4: () => `
        <div class="text-center py-8">
          <i class="ri-presentation-line text-6xl text-indigo-500 mb-4"></i>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Demo Teaching Scheduled</h3>
          ${app.demo_date ? `
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 max-w-md mx-auto mt-4">
              <p class="text-sm font-semibold text-gray-900 mb-2">Demo Teaching Details:</p>
              <p class="text-sm text-gray-700"><i class="ri-calendar-event-line mr-2"></i>${new Date(app.demo_date).toLocaleDateString('en-US', { 
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
              })}</p>
              <p class="text-sm text-gray-700"><i class="ri-time-line mr-2"></i>${new Date(app.demo_date).toLocaleTimeString('en-US', { 
                hour: '2-digit', minute: '2-digit' 
              })}</p>
              ${app.demo_notes ? `<p class="text-sm text-gray-600 mt-2">${escapeHtml(app.demo_notes)}</p>` : ''}
            </div>
          ` : `
            <p class="text-gray-600 mt-4">Waiting for admin to schedule your demo teaching session.</p>
          `}
        </div>
      `,
      5: () => `
        <div class="text-center py-8">
          <i class="ri-brain-line text-6xl text-purple-500 mb-4"></i>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Psychological Examination</h3>
          ${app.psych_exam_receipt ? `
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 max-w-md mx-auto mt-4">
              <i class="ri-check-circle-line text-2xl text-green-600 mb-2"></i>
              <p class="text-sm font-semibold text-green-800 mb-2">Receipt Uploaded Successfully</p>
              <a href="download_application_file.php?application_id=${encodeURIComponent(app.id)}&type=psych" target="_blank"
                 class="text-sm text-blue-600 hover:underline">View uploaded receipt</a>
            </div>
          ` : app.psych_exam_date ? `
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 max-w-md mx-auto mt-4">
              <p class="text-sm font-semibold text-gray-900 mb-2">Exam Scheduled:</p>
              <p class="text-sm text-gray-700"><i class="ri-calendar-event-line mr-2"></i>${new Date(app.psych_exam_date).toLocaleDateString('en-US', { 
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
              })}</p>
              <p class="text-sm text-gray-700"><i class="ri-time-line mr-2"></i>${new Date(app.psych_exam_date).toLocaleTimeString('en-US', { 
                hour: '2-digit', minute: '2-digit' 
              })}</p>
              <button onclick="openUploadPsychModal(${app.id})" 
                      class="mt-4 px-4 py-2 bg-purple-600 text-white text-sm rounded-lg hover:bg-purple-700">
                <i class="ri-upload-line mr-1"></i>Upload Receipt
              </button>
            </div>
          ` : `
            <p class="text-gray-600 mt-4">Waiting for psychological exam schedule.</p>
          `}
        </div>
      `,
      6: () => `
        <div class="text-center py-8">
          <i class="ri-user-star-line text-6xl text-green-500 mb-4"></i>
          <h3 class="text-2xl font-bold text-gray-900 mb-2">Congratulations!</h3>
          <p class="text-lg text-gray-600 mb-4">You have been marked as initially hired</p>
          ${app.initially_hired_date ? `
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 max-w-md mx-auto">
              <p class="text-sm text-gray-700"><strong>Date:</strong> ${new Date(app.initially_hired_date).toLocaleDateString()}</p>
              ${app.initially_hired_notes ? `<p class="text-sm text-gray-600 mt-2">${escapeHtml(app.initially_hired_notes)}</p>` : ''}
            </div>
          ` : ''}
          <p class="text-sm text-gray-500 mt-6">Please wait for further instructions regarding your onboarding process.</p>
        </div>
      `,
      '-1': () => `
        <div class="text-center py-8">
          <i class="ri-close-circle-line text-6xl text-red-500 mb-4"></i>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Application Rejected</h3>
          <div class="bg-red-50 border border-red-200 rounded-lg p-4 max-w-md mx-auto mt-4">
            <p class="text-sm text-red-800">
              ${app.rejection_reason ? escapeHtml(app.rejection_reason) : 'Unfortunately, your application has been rejected. Please try applying for other positions.'}
            </p>
          </div>
        </div>
      `
    };
    
    return stepContent[currentStep] ? stepContent[currentStep]() : stepContent[1]();
  }
  
  window.closeProgressModal = function() {
    const modal = document.getElementById('applicationProgressModal');
    if (modal) {
      modal.classList.add('hidden');
      modal.style.display = 'none';
    }
    // Restore body scroll
    document.body.style.overflow = '';
    console.log('✅ Modal closed');
  };
  
  window.openUploadPsychModal = function(appId) {
    document.getElementById('psych_application_id').value = appId;
    document.getElementById('uploadPsychModal').classList.remove('hidden');
  };
  
  window.closePsychModal = function() {
    document.getElementById('uploadPsychModal').classList.add('hidden');
    document.getElementById('uploadPsychForm').reset();
  };
  
  // Handle psych receipt upload
  document.getElementById('uploadPsychForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const appId = document.getElementById('psych_application_id').value;
    const fileInput = document.getElementById('psych_receipt');
    const file = fileInput.files[0];
    
    if (!file) {
      showToast('Please select a file', 'error');
      return;
    }
    
    if (file.size > 5 * 1024 * 1024) {
      showToast('File size must be less than 5MB', 'error');
      return;
    }
    
    const formData = new FormData();
    formData.append('application_id', appId);
    formData.append('psych_receipt', file);
    
    try {
      const res = await fetch('upload_psych_receipt.php', {
        method: 'POST',
        body: formData
      });
      
      const data = await res.json();
      
      if (!data.success) {
        throw new Error(data.error || 'Upload failed');
      }
      
      showToast('Receipt uploaded successfully!', 'success');
      closePsychModal();
      
      // Reload the progress view to show updated receipt status
      viewApplicationDetails(appId);
      loadApplications();
      
    } catch (err) {
      showToast('Error uploading receipt: ' + err.message, 'error');
    }
  });
  
  }, 100); // Small delay for DOM insertion
})(); // Execute immediately
</script>
