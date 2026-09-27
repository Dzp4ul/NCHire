<?php
$profile_h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$profile_skill_levels = ['', 'Beginner', 'Novice', 'Intermediate', 'Advanced', 'Expert'];
?>
<div id="profileMainContent">
  <main class="ui-profile-page">
    <header class="ui-page-header ui-profile-page__header">
      <div class="ui-page-header__main">
        <p class="ui-eyebrow">Applicant workspace</p>
        <h1 class="ui-page-title">My Profile</h1>
        <p class="ui-page-description">Your qualifications and contact information used for teaching-load applications.</p>
      </div>
    </header>

    <section class="ui-profile-identity-header" aria-label="Applicant identity">
      <div class="ui-profile-summary__identity">
          <div class="ui-profile-avatar-wrap">
            <div id="profilePictureContainer" class="ui-profile-avatar">
              <?php if (!empty($applicant['profile_picture']) && file_exists('uploads/profile_pictures/' . $applicant['profile_picture'])): ?>
                <img src="uploads/profile_pictures/<?php echo $profile_h($applicant['profile_picture']); ?>" alt="Profile picture" id="profileImage">
              <?php else: ?>
                <span id="profileInitials"><?php echo $profile_h($profile_initials); ?></span>
              <?php endif; ?>
            </div>
            <button type="button" id="uploadPhotoBtn" class="ui-profile-photo-action" aria-label="Update profile photo" title="Update profile photo">
              <i class="ri-camera-line"></i>
            </button>
            <input type="file" id="photoUpload" accept="image/*" class="hidden">
          </div>
          <div class="ui-profile-summary__name">
            <p class="ui-eyebrow">Candidate profile</p>
            <h2><?php echo $profile_h($profile_display_name); ?></h2>
            <span>Applicant / Candidate</span>
            <div class="ui-profile-identity-header__contact">
              <span><i class="ri-mail-line" aria-hidden="true"></i><?php echo $profile_h($applicant['applicant_email'] ?: 'Email not provided'); ?></span>
              <span><i class="ri-phone-line" aria-hidden="true"></i><?php echo $profile_h($applicant['applicant_num'] ?: 'Phone not provided'); ?></span>
            </div>
          </div>
      </div>
      <dl class="ui-profile-identity-header__facts">
        <div><dt>Profile status</dt><dd><span class="ui-status-dot"></span><?php echo $profile_h($account_status_label); ?></dd></div>
        <div><dt>Graduate qualification</dt><dd><?php echo $profile_h($qualification_summary['title']); ?></dd></div>
        <div><dt>Profile readiness</dt><dd><?php echo $profile_h($profile_readiness_label); ?></dd></div>
      </dl>
      <button class="ui-button ui-button--secondary ui-profile-edit-basic" type="button"><i class="ri-edit-line"></i>Edit Basic Information</button>
    </section>

    <div class="ui-profile-layout">
      <aside class="ui-profile-navigation" aria-label="Profile sections">
        <p class="ui-eyebrow">Profile sections</p>
        <nav>
          <button type="button" class="ui-profile-navigation__item is-active" data-profile-target="overview" aria-current="page"><i class="ri-user-line"></i><span>Overview</span><i class="ri-arrow-right-s-line"></i></button>
          <button type="button" class="ui-profile-navigation__item" data-profile-target="education"><i class="ri-graduation-cap-line"></i><span>Education</span><i class="ri-arrow-right-s-line"></i></button>
          <button type="button" class="ui-profile-navigation__item" data-profile-target="experience"><i class="ri-briefcase-line"></i><span>Work Experience</span><i class="ri-arrow-right-s-line"></i></button>
          <button type="button" class="ui-profile-navigation__item" data-profile-target="skills"><i class="ri-tools-line"></i><span>Skills</span><i class="ri-arrow-right-s-line"></i></button>
          <button type="button" class="ui-profile-navigation__item" data-profile-target="qualifications"><i class="ri-award-line"></i><span>Certifications &amp; Licenses</span><i class="ri-arrow-right-s-line"></i></button>
          <button type="button" class="ui-profile-navigation__item" data-profile-target="settings"><i class="ri-shield-keyhole-line"></i><span>Account &amp; Security</span><i class="ri-arrow-right-s-line"></i></button>
        </nav>
      </aside>

      <div class="ui-profile-mobile-navigation">
        <label for="profileSectionSelect">Profile section</label>
        <select id="profileSectionSelect">
          <option value="overview">Overview</option>
          <option value="education">Education</option>
          <option value="experience">Work Experience</option>
          <option value="skills">Skills</option>
          <option value="qualifications">Certifications &amp; Licenses</option>
          <option value="settings">Account &amp; Security</option>
        </select>
      </div>

      <div class="ui-profile-content">
        <section id="personalInfo" class="ui-profile-section ui-profile-panel is-active" data-profile-section="overview">
          <div class="ui-profile-section__header">
            <div><p class="ui-eyebrow">Profile overview</p><h2>Personal Information</h2><p>Review the contact details used throughout your applications.</p></div>
            <button class="ui-button ui-button--secondary" id="editPersonalBtn" type="button"><i class="ri-edit-line"></i>Edit</button>
          </div>

          <?php if (!empty($ranking_profile_missing)): ?>
          <div class="ui-profile-notice" role="status">
            <i class="ri-information-line" aria-hidden="true"></i>
            <div>
              <strong>Complete your profile</strong>
              <p>Add <?php echo $profile_h(implode(', ', $ranking_profile_missing)); ?> to improve your qualification assessment. Missing details are never assumed.</p>
            </div>
            <button type="button" data-review-profile="<?php echo $profile_h($profile_review_target); ?>">Review missing information</button>
          </div>
          <?php endif; ?>

          <div class="ui-profile-overview-stats" aria-label="Profile record summary">
            <div><span>Education</span><strong><?php echo count($education_data); ?></strong><small><?php echo count($education_data) === 1 ? 'record' : 'records'; ?></small></div>
            <div><span>Experience</span><strong><?php echo count($experience_data); ?></strong><small><?php echo count($experience_data) === 1 ? 'record' : 'records'; ?></small></div>
            <div><span>Skills</span><strong><?php echo count($skills_data); ?></strong><small><?php echo count($skills_data) === 1 ? 'skill' : 'skills'; ?></small></div>
            <div><span>Credentials</span><strong><?php echo count($qualifications_data); ?></strong><small><?php echo count($qualifications_data) === 1 ? 'record' : 'records'; ?></small></div>
          </div>

          <dl id="personalInfoView" class="ui-profile-info-grid">
            <div><dt>First Name</dt><dd><?php echo $profile_h($applicant['applicant_fname'] ?: 'Not provided'); ?></dd></div>
            <div><dt>Last Name</dt><dd><?php echo $profile_h($applicant['applicant_lname'] ?: 'Not provided'); ?></dd></div>
            <div><dt>Email Address</dt><dd><?php echo $profile_h($applicant['applicant_email'] ?: 'Not provided'); ?></dd></div>
            <div><dt>Phone Number</dt><dd><?php echo $profile_h($applicant['applicant_num'] ?: 'Not provided'); ?></dd></div>
            <div class="ui-profile-info-grid__wide"><dt>Address</dt><dd class="<?php echo empty($applicant['applicant_address']) ? 'is-empty' : ''; ?>"><?php echo $profile_h($applicant['applicant_address'] ?: 'Not provided'); ?></dd></div>
          </dl>

          <div id="personalInfoEdit" class="ui-profile-edit-grid hidden">
            <div><label for="profileFirstName">First Name</label><input id="profileFirstName" type="text" name="applicant_fname" value="<?php echo $profile_h($applicant['applicant_fname']); ?>" pattern="[A-Za-z\s\-']+" title="Please enter only letters, spaces, hyphens, and apostrophes" disabled></div>
            <div><label for="profileLastName">Last Name</label><input id="profileLastName" type="text" name="applicant_lname" value="<?php echo $profile_h($applicant['applicant_lname']); ?>" pattern="[A-Za-z\s\-']+" title="Please enter only letters, spaces, hyphens, and apostrophes" disabled></div>
            <div><label for="profileEmail">Email Address</label><input id="profileEmail" type="email" name="applicant_email" value="<?php echo $profile_h($applicant['applicant_email']); ?>" disabled></div>
            <div><label for="profilePhone">Phone Number</label><input id="profilePhone" type="tel" name="applicant_num" value="<?php echo $profile_h($applicant['applicant_num']); ?>" pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" title="Please enter a valid Philippine mobile number (e.g., 09123456789)" oninput="this.value = this.value.replace(/[^0-9]/g, '')" disabled></div>
            <div class="ui-profile-edit-grid__wide"><label for="profileAddress">Address</label><textarea id="profileAddress" name="applicant_address" rows="3" disabled placeholder="Enter your complete address"><?php echo $profile_h($applicant['applicant_address']); ?></textarea></div>
          </div>
          <div class="hidden ui-form-actions" id="personalActions">
            <button class="ui-button ui-button--secondary" id="cancelPersonalBtn" type="button">Cancel</button>
            <button class="ui-button ui-button--primary" id="savePersonalBtn" type="button">Save Changes</button>
          </div>
        </section>

        <section id="education" class="ui-profile-section ui-profile-panel" data-profile-section="education" hidden>
          <div class="ui-profile-section__header">
            <div><p class="ui-eyebrow">Academic background</p><h2>Education</h2><p>Degrees, graduate studies, and supporting academic records.</p></div>
            <button class="ui-button ui-button--primary" id="addEducationBtn" type="button"><i class="ri-add-line"></i>Add Education</button>
          </div>
          <div class="ui-profile-record-list ui-profile-timeline" id="educationList">
            <?php if (!empty($education_data)): ?>
            <?php foreach ($education_data as $education): ?>
            <?php
            $level = $education['education_level'] ?? nc_classify_education_level($education);
            $status = strtolower(trim((string)($education['education_status'] ?? 'completed'))) ?: 'completed';
            $level_label = profileEducationLevelLabel($level);
            $status_label = $status === 'ongoing' ? 'Ongoing' : 'Completed';
            $year_display = $status === 'ongoing'
                ? (($education['start_year'] ?? '') . ' – Present')
                : (($education['start_year'] ?? '') . ' – ' . (($education['year_completed'] ?? '') ?: ($education['end_year'] ?? '')));
            ?>
            <div class="ui-profile-record">
              <div class="ui-profile-record__body">
                <div class="ui-profile-record__title-row">
                  <div><h3><?php echo $profile_h($education['degree'] ?: $level_label); ?></h3><p><?php echo $profile_h($level_label); ?> <span aria-hidden="true">•</span> <?php echo $profile_h($status_label); ?></p></div>
                  <div class="ui-profile-record__actions">
                    <button type="button" onclick="editEducation(<?php echo (int)$education['id']; ?>)" class="ui-link-action"><i class="ri-edit-line"></i>Edit</button>
                    <button type="button" onclick="deleteEducation(<?php echo (int)$education['id']; ?>)" class="ui-link-action ui-link-action--danger"><i class="ri-delete-bin-line"></i>Delete</button>
                  </div>
                </div>
                <div class="ui-profile-record__meta">
                  <p><strong><?php echo $profile_h($education['institution'] ?: 'Institution not provided'); ?></strong><?php if (!empty($education['field_of_study'])): ?><span><?php echo $profile_h($education['field_of_study']); ?></span><?php endif; ?></p>
                  <p><i class="ri-calendar-line"></i><?php echo $profile_h($year_display); ?><?php if (!empty($education['gpa'])): ?><span>GPA <?php echo $profile_h($education['gpa']); ?></span><?php endif; ?></p>
                </div>
                <?php if ($level === 'master' && $status === 'ongoing' && $education['completed_units'] !== null && $education['completed_units'] !== ''): ?>
                  <div class="ui-profile-inline-fact"><span>Completed Master's Units</span><strong><?php echo $profile_h($education['completed_units']); ?> units</strong></div>
                <?php endif; ?>
                <?php if (!empty($education['certificate_of_grades']) || !empty($education['proof_of_enrollment'])): ?>
                <div class="ui-profile-documents">
                  <h4>Supporting Documents</h4>
                  <?php if (!empty($education['certificate_of_grades'])): ?>
                  <div class="ui-profile-document-row">
                    <i class="ri-file-list-3-line" aria-hidden="true"></i>
                    <div><strong>Certificate of Grades</strong><span><?php echo $profile_h(basename($education['certificate_of_grades'])); ?></span></div>
                    <span class="ui-document-status">Current</span>
                    <div class="ui-profile-document-row__actions"><a href="<?php echo $profile_h($education['certificate_of_grades']); ?>" target="_blank" rel="noopener">View</a><button type="button" onclick="editEducation(<?php echo (int)$education['id']; ?>)">Replace</button></div>
                  </div>
                  <?php endif; ?>
                  <?php if (!empty($education['proof_of_enrollment'])): ?>
                  <div class="ui-profile-document-row">
                    <i class="ri-file-user-line" aria-hidden="true"></i>
                    <div><strong>Proof of Enrollment</strong><span><?php echo $profile_h(basename($education['proof_of_enrollment'])); ?></span></div>
                    <span class="ui-document-status">Current</span>
                    <div class="ui-profile-document-row__actions"><a href="<?php echo $profile_h($education['proof_of_enrollment']); ?>" target="_blank" rel="noopener">View</a><button type="button" onclick="editEducation(<?php echo (int)$education['id']; ?>)">Replace</button></div>
                  </div>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="ui-profile-empty"><i class="ri-graduation-cap-line"></i><div><strong>No education added yet</strong><p>Add your academic background so NCHire can assess teaching-load qualifications.</p></div><button type="button" onclick="document.getElementById('addEducationBtn').click()">Add Education</button></div>
            <?php endif; ?>
          </div>
          <div class="ui-profile-assessment">
            <div><span>Qualification assessment</span><strong><?php echo $profile_h($qualification_summary['title']); ?></strong><p><?php echo $profile_h($qualification_summary['note']); ?></p></div>
            <div><span>Projected compensation</span><strong><?php echo $profile_h($qualification_summary['rate']); ?><sup>*</sup></strong><p>*<?php echo $profile_h($qualification_projection['disclaimer']); ?></p></div>
          </div>
        </section>

        <section id="experience" class="ui-profile-section ui-profile-panel" data-profile-section="experience" hidden>
          <div class="ui-profile-section__header">
            <div><p class="ui-eyebrow">Professional background</p><h2>Work Experience</h2></div>
            <button class="ui-button ui-button--primary" id="addExperienceBtn" type="button"><i class="ri-add-line"></i>Add Experience</button>
          </div>
          <div class="ui-profile-record-list ui-profile-timeline" id="experienceList">
            <?php if (!empty($experience_data)): ?>
            <?php foreach ($experience_data as $experience): ?>
            <?php $startDate = !empty($experience['start_date']) ? date('F Y', strtotime($experience['start_date'])) : 'Start date not provided'; $endDate = !empty($experience['end_date']) ? date('F Y', strtotime($experience['end_date'])) : 'Present'; ?>
            <div class="ui-profile-record">
              <div class="ui-profile-record__body">
                <div class="ui-profile-record__title-row">
                  <div><h3><?php echo $profile_h($experience['job_title']); ?></h3><p><?php echo $profile_h(ucfirst($experience['experience_type'] ?? 'other')); ?> experience</p></div>
                  <div class="ui-profile-record__actions"><button type="button" onclick="editExperience(<?php echo (int)$experience['id']; ?>)" class="ui-link-action"><i class="ri-edit-line"></i>Edit</button><button type="button" onclick="deleteExperience(<?php echo (int)$experience['id']; ?>)" class="ui-link-action ui-link-action--danger"><i class="ri-delete-bin-line"></i>Delete</button></div>
                </div>
                <div class="ui-profile-record__meta"><p><strong><?php echo $profile_h($experience['company']); ?></strong><?php if (!empty($experience['location'])): ?><span><?php echo $profile_h($experience['location']); ?></span><?php endif; ?></p><p><i class="ri-calendar-line"></i><?php echo $profile_h($startDate . ' – ' . $endDate); ?></p></div>
                <?php if (!empty($experience['description'])): ?><div class="ui-profile-record__description"><span>Responsibilities</span><p><?php echo nl2br($profile_h($experience['description'])); ?></p></div><?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="ui-profile-empty"><i class="ri-briefcase-line"></i><div><strong>No work experience added yet</strong><p>Add previous employment or professional experience to complete your profile.</p></div><button type="button" onclick="document.getElementById('addExperienceBtn').click()">Add Work Experience</button></div>
            <?php endif; ?>
          </div>
        </section>

        <section id="skills" class="ui-profile-section ui-profile-panel" data-profile-section="skills" hidden>
          <div class="ui-profile-section__header">
            <div><p class="ui-eyebrow">Capabilities</p><h2>Skills</h2></div>
            <button class="ui-button ui-button--primary" id="addSkillBtn" type="button"><i class="ri-add-line"></i>Add Skill</button>
          </div>
          <div class="ui-skill-grid" id="skillsList">
            <?php if (!empty($skills_data)): ?>
            <?php foreach ($skills_data as $skill): ?>
            <div class="ui-skill-item"><div><strong><?php echo $profile_h($skill['skill_name']); ?></strong><span><?php echo $profile_h($profile_skill_levels[(int)$skill['skill_level']] ?? 'Not rated'); ?></span></div><div><button type="button" onclick="editSkill(<?php echo (int)$skill['id']; ?>)" aria-label="Edit <?php echo $profile_h($skill['skill_name']); ?>"><i class="ri-edit-line"></i></button><button type="button" onclick="deleteSkill(<?php echo (int)$skill['id']; ?>)" class="is-danger" aria-label="Delete <?php echo $profile_h($skill['skill_name']); ?>"><i class="ri-delete-bin-line"></i></button></div></div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="ui-profile-empty ui-profile-empty--wide"><i class="ri-tools-line"></i><div><strong>No skills added yet</strong><p>Add technical, teaching, or professional skills relevant to your applications.</p></div><button type="button" onclick="document.getElementById('addSkillBtn').click()">Add Skill</button></div>
            <?php endif; ?>
          </div>
        </section>

        <section id="qualifications" class="ui-profile-section ui-profile-panel" data-profile-section="qualifications" hidden>
          <div class="ui-profile-section__header">
            <div><p class="ui-eyebrow">Professional credentials</p><h2>Certifications &amp; Licenses</h2><p>Certifications, professional licenses, and relevant training.</p></div>
            <button class="ui-button ui-button--primary" id="addQualificationBtn" type="button"><i class="ri-add-line"></i>Add Qualification</button>
          </div>
          <div class="ui-profile-record-list" id="qualificationsList">
            <?php if (!empty($qualifications_data)): ?>
            <?php foreach ($qualifications_data as $qualification): ?>
            <div class="ui-profile-record ui-profile-record--credential" data-qualification-id="<?php echo (int)$qualification['id']; ?>">
              <div class="ui-profile-record__body">
                <div class="ui-profile-record__title-row"><div><h3><?php echo $profile_h($qualification['title']); ?></h3><p><?php echo $profile_h(ucfirst($qualification['qualification_type'])); ?> <span aria-hidden="true">•</span> <?php echo $profile_h(ucfirst($qualification['verification_status'])); ?></p></div><div class="ui-profile-record__actions"><button type="button" onclick="editQualification(<?php echo (int)$qualification['id']; ?>)" class="ui-link-action"><i class="ri-edit-line"></i>Edit</button><button type="button" onclick="deleteQualification(<?php echo (int)$qualification['id']; ?>)" class="ui-link-action ui-link-action--danger"><i class="ri-delete-bin-line"></i>Delete</button></div></div>
                <div class="ui-profile-credential-grid"><div><span>Issued by</span><strong><?php echo $profile_h($qualification['issuing_organization'] ?: 'Not provided'); ?></strong></div><div><span>Issued</span><strong><?php echo $qualification['issued_date'] ? $profile_h(date('F Y', strtotime($qualification['issued_date']))) : 'Not provided'; ?></strong></div><?php if (!empty($qualification['expiry_date'])): ?><div><span>Expires</span><strong><?php echo $profile_h(date('F Y', strtotime($qualification['expiry_date']))); ?></strong></div><?php endif; ?></div>
                <?php if (!empty($qualification['proof_document'])): ?><div class="ui-profile-document-row ui-profile-document-row--standalone"><i class="ri-file-text-line" aria-hidden="true"></i><div><strong>Proof document</strong><span><?php echo $profile_h(basename($qualification['proof_document'])); ?></span></div><span class="ui-document-status"><?php echo $profile_h(ucfirst($qualification['verification_status'])); ?></span><div class="ui-profile-document-row__actions"><a href="<?php echo $profile_h($qualification['proof_document']); ?>" target="_blank" rel="noopener">View</a></div></div><?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="ui-profile-empty"><i class="ri-award-line"></i><div><strong>No certifications or licenses added yet</strong><p>Add only credentials and training that you currently hold.</p></div><button type="button" onclick="document.getElementById('addQualificationBtn').click()">Add Qualification</button></div>
            <?php endif; ?>
          </div>
        </section>

        <section id="settings" class="ui-profile-section ui-profile-section--last ui-profile-panel" data-profile-section="settings" hidden>
          <div class="ui-profile-section__header"><div><p class="ui-eyebrow">Sign-in protection</p><h2>Account &amp; Security</h2></div></div>
          <div class="ui-account-summary">
            <div><span>Email Address</span><strong><?php echo $profile_h($applicant['applicant_email']); ?></strong></div>
            <div><span>Password</span><strong aria-label="Password hidden">••••••••••••</strong></div>
            <div><span>Account Status</span><strong><?php echo $profile_h($account_status_label); ?></strong></div>
            <button type="button" id="changePasswordBtn" class="ui-button ui-button--secondary"><i class="ri-lock-password-line"></i>Change Password</button>
          </div>
          <div id="passwordChangeForm" class="ui-password-form hidden">
            <div class="ui-password-form__header"><div><h3>Change Password</h3><p>Use a unique password that you do not use for another account.</p></div></div>
            <div class="ui-profile-edit-grid">
              <div class="ui-profile-edit-grid__wide"><label for="currentPassword">Current Password</label><div class="ui-password-field"><input type="password" id="currentPassword" autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly');" placeholder="Enter your current password" required><button type="button" aria-label="Show current password" onclick="togglePasswordVisibility('currentPassword', this)"><i class="ri-eye-line"></i></button></div></div>
              <div><label for="newPassword">New Password</label><div class="ui-password-field"><input type="password" id="newPassword" autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly');" placeholder="At least 8 characters" required><button type="button" aria-label="Show new password" onclick="togglePasswordVisibility('newPassword', this)"><i class="ri-eye-line"></i></button></div></div>
              <div><label for="confirmPassword">Confirm New Password</label><div class="ui-password-field"><input type="password" id="confirmPassword" autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly');" placeholder="Re-enter new password" required><button type="button" aria-label="Show confirmed password" onclick="togglePasswordVisibility('confirmPassword', this)"><i class="ri-eye-line"></i></button></div></div>
            </div>
            <p class="ui-password-requirements"><i class="ri-shield-check-line"></i>Use at least 8 characters with one number and one symbol (!@#$%^&amp;*).</p>
            <div class="ui-form-actions">
              <button type="button" id="cancelPasswordBtn" class="ui-button ui-button--secondary">Cancel</button>
              <button type="button" id="updatePasswordBtn" class="ui-button ui-button--primary"><i class="ri-lock-password-line"></i>Update Password</button>
            </div>
          </div>
        </section>
      </div>
    </div>
  </main>
</div>
<script id="profileWorkspaceInteractions">
(function initializeProfileWorkspaceInteractions() {
  function bindProfileWorkspaceInteractions() {
    const root = document.getElementById('profileMainContent');
    if (!root) return;
    const navigationItems = Array.from(root.querySelectorAll('[data-profile-target]'));
    const panels = Array.from(root.querySelectorAll('[data-profile-section]'));
    const mobileSelect = document.getElementById('profileSectionSelect');
    const sectionAliases = { personalInfo: 'overview', education: 'education', experience: 'experience', skills: 'skills', qualifications: 'qualifications', settings: 'settings' };

    function showProfileSection(section, updateHash) {
      const target = sectionAliases[section] || section;
      if (!panels.some(panel => panel.dataset.profileSection === target)) return;
      panels.forEach(panel => {
        const active = panel.dataset.profileSection === target;
        panel.hidden = !active;
        panel.classList.toggle('is-active', active);
      });
      navigationItems.forEach(item => {
        const active = item.dataset.profileTarget === target;
        item.classList.toggle('is-active', active);
        if (active) item.setAttribute('aria-current', 'page');
        else item.removeAttribute('aria-current');
      });
      if (mobileSelect) mobileSelect.value = target;
      if (updateHash && history.replaceState) history.replaceState(null, '', '#' + target);
    }

    navigationItems.forEach(item => {
      if (item.dataset.profileBound === '1') return;
      item.dataset.profileBound = '1';
      item.addEventListener('click', () => showProfileSection(item.dataset.profileTarget, true));
    });
    if (mobileSelect && mobileSelect.dataset.bound !== '1') {
      mobileSelect.dataset.bound = '1';
      mobileSelect.addEventListener('change', () => showProfileSection(mobileSelect.value, true));
    }
    root.querySelectorAll('[data-review-profile]').forEach(button => {
      button.addEventListener('click', () => showProfileSection(button.dataset.reviewProfile, true));
    });
    root.querySelector('.ui-profile-edit-basic')?.addEventListener('click', function() {
      showProfileSection('overview', true);
      document.getElementById('editPersonalBtn')?.click();
    });

    const changePasswordButton = document.getElementById('changePasswordBtn');
    const cancelPasswordButton = document.getElementById('cancelPasswordBtn');
    const passwordForm = document.getElementById('passwordChangeForm');
    if (changePasswordButton && passwordForm && changePasswordButton.dataset.bound !== '1') {
      changePasswordButton.dataset.bound = '1';
      changePasswordButton.addEventListener('click', function() {
        passwordForm.classList.remove('hidden');
        changePasswordButton.classList.add('hidden');
        passwordForm.querySelector('input')?.focus();
      });
    }
    if (cancelPasswordButton && passwordForm && cancelPasswordButton.dataset.workspaceBound !== '1') {
      cancelPasswordButton.dataset.workspaceBound = '1';
      cancelPasswordButton.addEventListener('click', function() {
        passwordForm.classList.add('hidden');
        changePasswordButton?.classList.remove('hidden');
      });
    }
    const requestedSection = window.location.hash.replace('#', '');
    if (requestedSection) showProfileSection(requestedSection, false);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bindProfileWorkspaceInteractions, { once: true });
  else setTimeout(bindProfileWorkspaceInteractions, 0);
})();
</script>
