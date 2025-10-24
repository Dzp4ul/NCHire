<?php
// user_application.php
// Start session or include any necessary PHP logic here if needed
session_start();
?>

<div class="mb-8">
  <h1 class="text-3xl font-bold text-gray-900 mb-2">My Applications</h1>
  <p class="text-gray-600">Track and manage your job applications</p>
</div>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
  <div class="grid grid-cols-[1fr,auto,auto,auto] gap-4 p-4 bg-gray-50 border-b border-gray-200 text-sm font-medium text-gray-600">
    <div>Position</div>
    <div>Status</div>
    <div>Applied Date</div>
    <div>Actions</div>
  </div>
  <div id="applicationsContainer" class="divide-y divide-gray-200">
    <?php
      // Immediate server-side rendering for zero perceived loading
      $host = "127.0.0.1";
      $user = "root";
      $pass = "12345678";
      $dbname = "nchire";
      $conn = @new mysqli($host, $user, $pass, $dbname);
      if ($conn && !$conn->connect_error) {
          $user_id = $_SESSION['user_id'] ?? null;
          $user_email = $_SESSION['email'] ?? ($_SESSION['applicant_email'] ?? null);

          if ($user_id) {
              $stmt = $conn->prepare("SELECT id, position, applied_date, status FROM job_applicants WHERE user_id = ? ORDER BY applied_date DESC, id DESC");
              $stmt->bind_param("i", $user_id);
          } else if ($user_email) {
              $stmt = $conn->prepare("SELECT id, position, applied_date, status FROM job_applicants WHERE applicant_email = ? ORDER BY applied_date DESC, id DESC");
              $stmt->bind_param("s", $user_email);
          } else {
              $stmt = null;
          }

          if ($stmt && $stmt->execute()) {
              $result = $stmt->get_result();
              if ($result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                      $id = (int)$row['id'];
                      $position = htmlspecialchars($row['position'] ?? 'Unknown Position');
                      $status = $row['status'] ?? 'Pending';
                      $status_l = strtolower($status);
                      $status_class = 'bg-yellow-100 text-yellow-800';
                      if (strpos($status_l, 'interview') !== false && strpos($status_l, 'passed') !== false) $status_class = 'bg-teal-100 text-teal-800';
                      else if (strpos($status_l, 'interview') !== false) $status_class = 'bg-blue-100 text-blue-800';
                      else if (strpos($status_l, 'demo') !== false && strpos($status_l, 'passed') !== false) $status_class = 'bg-emerald-100 text-emerald-800';
                      else if (strpos($status_l, 'demo') !== false) $status_class = 'bg-indigo-100 text-indigo-800';
                      else if (strpos($status_l, 'resubmission') !== false) $status_class = 'bg-orange-100 text-orange-800';
                      else if (strpos($status_l, 'reject') !== false) $status_class = 'bg-red-100 text-red-800';
                      else if (strpos($status_l, 'accept') !== false || strpos($status_l, 'hired') !== false) $status_class = 'bg-green-100 text-green-800';
                      $applied_pretty = $row['applied_date'] ? date('m/d/Y', strtotime($row['applied_date'])) : '';
                      echo '<div class="grid grid-cols-[1fr,auto,auto,auto] gap-4 p-4 items-center" data-id="'.$id.'">'
                        .'<div>'
                        .'<h3 class="font-semibold text-gray-900">'.$position.'</h3>'
                        .'<p class="text-sm text-gray-600">Application #'.$id.'</p>'
                        .'</div>'
                        .'<div>'
                        .'<span class="'.$status_class.' px-3 py-1 rounded-full text-sm">'.htmlspecialchars($status).'</span>'
                        .'</div>'
                        .'<div class="text-sm text-gray-600">'.htmlspecialchars($applied_pretty).'</div>'
                        .'<div class="flex items-center space-x-2">'
                        .'<button class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-primary rounded-lg hover:bg-gray-100 !rounded-button" data-action="view">'
                        .'<i class="ri-eye-line"></i>'
                        .'</button>'
                        .'<button class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-red-600 rounded-lg hover:bg-gray-100 !rounded-button" data-action="delete">'
                        .'<i class="ri-close-line"></i>'
                        .'</button>'
                        .'</div>'
                        .'</div>';
                  }
              } else {
                  echo '<div class="p-8 text-center text-gray-500">'
                      .'<i class="ri-inbox-line text-3xl mb-2 block"></i>'
                      .'<p class="text-sm">You haven\'t applied to any jobs yet.</p>'
                      .'</div>';
              }
          } else {
              echo '<div class="p-8 text-center text-red-600">'
                  .'<i class="ri-error-warning-line text-2xl mb-2 block"></i>'
                  .'<p class="text-sm">Unable to load applications.</p>'
                  .'</div>';
          }

          if ($stmt) { $stmt->close(); }
          $conn->close();
      } else {
          echo '<div class="p-8 text-center text-red-600">'
              .'<i class="ri-error-warning-line text-2xl mb-2 block"></i>'
              .'<p class="text-sm">Database connection failed.</p>'
              .'</div>';
      }
    ?>
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
          <!-- Step 6 -->
          <div class="flex items-center" style="display: flex !important; align-items: center !important;">
            <div class="w-7 h-7 rounded-full flex items-center justify-center font-semibold progress-step-dot" data-step="6" style="width: 1.75rem !important; height: 1.75rem !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-weight: 600 !important; background: rgba(255,255,255,0.3) !important; color: white !important; font-size: 0.875rem !important;">6</div>
          </div>
        </div>
        <div class="mt-2 text-center text-blue-100 text-xs" id="progressStepLabel" style="margin-top: 0.5rem !important; text-align: center !important; color: #bfdbfe !important; font-size: 0.75rem !important;">
          Step 1 of 6: Application Submitted
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
    
    console.log('✅ Applications container found');

  const statusToClasses = (status) => {
    const s = (status || '').toLowerCase();
    if (s.includes('interview')) return 'bg-blue-100 text-blue-800';
    if (s.includes('resubmission')) return 'bg-orange-100 text-orange-800';
    if (s.includes('reject')) return 'bg-red-100 text-red-800';
    if (s.includes('accept') || s.includes('hired')) return 'bg-green-100 text-green-800';
    return 'bg-yellow-100 text-yellow-800'; // Pending/Under Review
  };

  function renderApplications(apps) {
    if (!apps || apps.length === 0) {
      container.innerHTML = `
        <div class="p-8 text-center text-gray-500">
          <i class="ri-inbox-line text-3xl mb-2 block"></i>
          <p class="text-sm">You haven't applied to any jobs yet.</p>
        </div>
      `;
      return;
    }

    container.innerHTML = apps.map(app => `
      <div class="grid grid-cols-[1fr,auto,auto,auto] gap-4 p-4 items-center" data-id="${app.id}">
        <div>
          <h3 class="font-semibold text-gray-900">${escapeHtml(app.position || 'Unknown Position')}</h3>
          <p class="text-sm text-gray-600">Application #${app.id}</p>
        </div>
        <div>
          <span class="${statusToClasses(app.status)} px-3 py-1 rounded-full text-sm">${escapeHtml(app.status || 'Pending')}</span>
        </div>
        <div class="text-sm text-gray-600">${escapeHtml(app.applied_date_pretty || (app.applied_date ? new Date(app.applied_date).toLocaleDateString('en-US', {month: '2-digit', day: '2-digit', year: 'numeric'}).replace(/\//g, '/') : ''))}</div>
        <div class="flex items-center space-x-2">
          <button class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-primary rounded-lg hover:bg-gray-100 !rounded-button" data-action="view">
            <i class="ri-eye-line"></i>
          </button>
          <button class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-red-600 rounded-lg hover:bg-gray-100 !rounded-button" data-action="delete">
            <i class="ri-close-line"></i>
          </button>
        </div>
      </div>
    `).join('');
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
      const res = await fetch('get_user_applications.php', { credentials: 'same-origin' });
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'Failed to load applications');
      renderApplications(data.applications);
    } catch (err) {
      container.innerHTML = `
        <div class="p-8 text-center text-red-600">
          <i class="ri-error-warning-line text-2xl mb-2 block"></i>
          <p class="text-sm">Error loading applications: ${escapeHtml(err.message)}</p>
        </div>
      `;
    }
  }

  // Delegated events for view and delete
  container.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const row = btn.closest('[data-id]');
    const id = row ? row.getAttribute('data-id') : null;
    const action = btn.getAttribute('data-action');
    if (!id) return;

    if (action === 'view') {
      console.log('🖱️ View button clicked for application:', id);
      // Always use viewExistingApplication if available (stays on current page)
      if (typeof window.viewExistingApplication === 'function') {
        console.log('✅ Opening wizard via viewExistingApplication...');
        window.viewExistingApplication(id);
      } else {
        console.log('⚠️ viewExistingApplication not available, showing modal view');
        viewApplicationDetails(id);
      }
    }

    if (action === 'delete') {
      if (!confirm('Are you sure you want to delete this application?')) return;
      try {
        const form = new FormData();
        form.append('id', id);
        const res = await fetch('delete_application.php', {
          method: 'POST',
          body: form,
          credentials: 'same-origin'
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Delete failed');
        // Optimistically remove row
        row.remove();
        showToast('Application deleted', 'success');
        // Reload to ensure state is fresh
        loadApplications();
      } catch (err) {
        showToast('Error deleting: ' + err.message, 'error');
      }
    }
  });

  function showToast(message, type = 'info') {
    const el = document.createElement('div');
    el.className = `fixed top-4 right-4 ${type === 'success' ? 'bg-green-100 text-green-800 border-green-300' : type === 'error' ? 'bg-red-100 text-red-800 border-red-300' : 'bg-blue-100 text-blue-800 border-blue-300'} px-4 py-3 rounded border shadow`;
    el.innerHTML = `<div class="flex items-center"><i class="${type === 'success' ? 'ri-check-line' : type === 'error' ? 'ri-error-warning-line' : 'ri-information-line'} mr-2"></i><span>${escapeHtml(message)}</span></div>`;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
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
        currentStep = 6;
      } else if (status.includes('psychological') || status.includes('psych')) {
        currentStep = 5;
      } else if (status.includes('demo')) {
        currentStep = 4;
      } else if (status.includes('interview')) {
        currentStep = 3;
      } else if (status.includes('documents approved')) {
        currentStep = 2;
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
      1: 'Step 1 of 6: Application Submitted',
      2: 'Step 2 of 6: Documents Under Review',
      3: 'Step 3 of 6: Interview Scheduled',
      4: 'Step 4 of 6: Demo Teaching Scheduled',
      5: 'Step 5 of 6: Psychological Examination',
      6: 'Step 6 of 6: Initially Hired',
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
              <a href="../user/uploads/${escapeHtml(app.psych_exam_receipt)}" target="_blank" 
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
