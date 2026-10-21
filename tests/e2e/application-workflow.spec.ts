import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { accounts, csrfToken, login } from './helpers';

const pdfPath = path.resolve(__dirname, '../fixtures/valid-document.pdf');
const invalidPath = path.resolve(__dirname, '../fixtures/invalid-upload.txt');

test.describe('application submission and cross-role recruitment workflow', () => {
  test.describe.configure({ mode: 'serial' });

  test.beforeEach(async ({}, testInfo) => {
    testInfo.skip(testInfo.project.name !== 'chromium-desktop', 'State-changing workflow tests run once in Chromium.');
  });

  test('profile, ban, qualification, posting-status, and deadline rules are enforced by the backend', async ({ page }) => {
    const submit = async (account: { email: string; password: string }, jobId: number, fileField?: string) => {
      await login(page, account);
      const token = await page.locator('#requirementsCsrfToken').getAttribute('value');
      const multipart: Record<string, string | { name: string; mimeType: string; buffer: Buffer }> = {
        submit_application: '1', ajax_submit: '1', csrf_token: token || '', job_id: String(jobId), job_title: 'Untrusted title',
      };
      if (fileField) {
        multipart[fileField] = { name: 'valid-document.pdf', mimeType: 'application/pdf', buffer: readFileSync(pdfPath) };
      }
      const response = await page.request.post('user/user.php', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        multipart,
      });
      return { response, body: await response.json() };
    };

    const incomplete = await submit(accounts.incomplete, 1);
    expect(incomplete.body).toMatchObject({ success: false, profile_incomplete: true });
    expect(incomplete.body.missing_profile_fields).toEqual(expect.arrayContaining(['contact number', 'work experience']));

    const restricted = await submit(accounts.restricted, 1);
    expect(restricted.body).toMatchObject({ success: false, banned: true });

    const fullTime = await submit(accounts.applicant, 2);
    expect(fullTime.response.status()).toBe(403);
    expect(fullTime.body).toMatchObject({ success: false, code: 'FULL_TIME_EDUCATION_REQUIREMENT' });

    const closed = await submit(accounts.applicant, 3, 'resume_file');
    expect(closed.body.success).toBeFalsy();
    expect(closed.body.error).toContain('no longer open');

    const expired = await submit(accounts.applicant, 4, 'resume_file');
    expect(expired.body.success).toBeFalsy();
    expect(expired.body.error).toContain('no longer open');
  });

  test('applicant accepts terms, sees the current checklist, handles invalid files, and submits without an optional file', async ({ page }) => {
    await login(page, accounts.applicant);
    const apply = page.locator('.apply-btn[data-job-id="1"]');
    await expect(apply).toBeVisible();
    await apply.click();
    await expect(page.locator('#termsModal')).toBeVisible();
    await expect(page.locator('#proceedToApplication')).toBeDisabled();
    await page.locator('#agreeTerms').check();
    await expect(page.locator('#proceedToApplication')).toBeEnabled();
    await page.locator('#proceedToApplication').click();

    const wizard = page.locator('#applicationWizard');
    await expect(wizard).toBeVisible();
    await expect(wizard.locator('#requirementsForm .ui-document-card')).toHaveCount(2);
    const requiredCard = wizard.locator('#requirementsForm .ui-document-card[data-required="true"]');
    const optionalCard = wizard.locator('#requirementsForm .ui-document-card[data-required="false"]');
    await expect(requiredCard).toContainText('Application Letter');
    await expect(optionalCard).toContainText('Teaching Portfolio');

    await wizard.getByRole('button', { name: 'Submit Application' }).click();
    await expect(requiredCard.locator('.ui-document-inline-error')).toContainText('required document');

    const requiredInput = requiredCard.locator('input[type="file"]');
    await requiredInput.setInputFiles(invalidPath);
    await wizard.getByRole('button', { name: 'Submit Application' }).click();
    await expect(page.locator('body')).toContainText(/upload failed|required document/i);
    let applications = await (await page.request.get('user/get_user_applications.php')).json();
    expect(applications.applications).toHaveLength(0);

    await requiredInput.setInputFiles(pdfPath);
    await wizard.getByRole('button', { name: 'Submit Application' }).click();
    await expect(page.locator('body')).toContainText('Application submitted successfully!');
    await expect(wizard.locator('#step1NextBtn')).toBeVisible();
    applications = await (await page.request.get('user/get_user_applications.php')).json();
    expect(applications.success).toBeTruthy();
    expect(applications.applications).toHaveLength(1);
    expect(applications.applications[0]).toMatchObject({ job_id: 1, status: 'Submitted' });
    expect(Number(applications.applications[0].applicable_hourly_rate)).toBe(150);
  });

  test('backend rejects a duplicate application to the same teaching load', async ({ page }) => {
    test.fail(true, 'Known integrity defect: the submission endpoint has no duplicate-application check or unique constraint.');
    await login(page, accounts.applicant);
    const token = await page.locator('#requirementsCsrfToken').getAttribute('value');
    const response = await page.request.post('user/user.php', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      multipart: {
        submit_application: '1', ajax_submit: '1', csrf_token: token || '', job_id: '1', job_title: 'CS 101',
        applicationLetter: { name: 'valid-document.pdf', mimeType: 'application/pdf', buffer: readFileSync(pdfPath) },
      },
    });
    const body = await response.json();
    expect(body.success).toBeFalsy();
    expect(String(body.error || body.message)).toMatch(/already applied|duplicate/i);
  });

  test('applicant can inspect attachments and cancel an application through the UI', async ({ page }) => {
    await login(page, accounts.applicant);
    await page.locator('#applicationsLink').click();
    const applicationsSection = page.locator('.ui-applications-page');
    await expect(applicationsSection).toBeVisible();
    const rows = page.locator('#applicationsContainer .ui-application-item');
    await expect(rows.first()).toBeVisible();

    await rows.first().getByRole('button', { name: /attachment/i }).click();
    await expect(page.locator('#attachmentsModal')).toBeVisible();
    await expect(page.locator('#attachmentsModal')).toContainText('Application Letter');
    await page.locator('[data-close-attachments]').first().click();

    const target = rows.first();
    await target.locator('details summary').click();
    await target.getByRole('button', { name: /Cancel application/i }).click();
    await expect(page.locator('#deleteConfirmModal')).toBeVisible();
    await page.locator('#deleteConfirmModal').getByRole('button', { name: 'Go Back' }).click();
    await expect(page.locator('#deleteConfirmModal')).toBeHidden();
    await target.getByRole('button', { name: /Cancel application/i }).click();
    await page.locator('#deleteConfirmModal').getByRole('button', { name: 'Cancel Application' }).click();
    await expect(applicationsSection).toContainText('Cancelled');
  });

  test('secretary resubmission opens Requirements and accepts the requested replacement', async ({ page }) => {
    await login(page, accounts.secretary);
    let token = await csrfToken(page);
    const requestResubmission = await page.request.post('admin/process_applicant_action.php', {
      headers: { 'X-CSRF-Token': token },
      form: {
        action: 'request_resubmission',
        applicant_id: '1',
        documents: JSON.stringify(['application_letter']),
        notes: 'Please upload a clearer application letter.',
      },
    });
    expect(requestResubmission.status()).toBe(200);
    expect(await requestResubmission.json()).toMatchObject({ success: true });

    await login(page, accounts.pipeline);
    token = await page.locator('#requirementsCsrfToken').getAttribute('value') || '';

    const emptyReplacement = await page.request.post('user/user.php', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      multipart: {
        submit_application: '1',
        ajax_submit: '1',
        csrf_token: token,
        job_id: '1',
        job_title: 'E2E CS 101 - Web Systems',
        is_resubmission: '1',
        resubmit_application_id: '1',
      },
    });
    expect(emptyReplacement.status()).toBe(422);
    expect(await emptyReplacement.json()).toMatchObject({ success: false });

    await page.evaluate(() => (window as typeof window & { viewExistingApplication: (id: number) => Promise<void> }).viewExistingApplication(1));
    const wizard = page.locator('#applicationWizard');
    await expect(wizard).toBeVisible();
    await expect(wizard.locator('#step1')).toBeVisible();
    await expect(wizard.locator('#step2')).toBeHidden();
    await expect(wizard.locator('#wizardStepLabel')).toContainText('Document Resubmission Required');
    await expect(wizard.locator('#step1 .resubmission-notice')).toContainText('Please upload a clearer application letter.');

    const requestedCard = wizard.locator('.ui-document-card[data-document-field="application_letter"]');
    const requestedInput = requestedCard.locator('input[type="file"]');
    await expect(requestedInput).toBeVisible();
    await expect(requestedInput).toBeEnabled();
    await expect(wizard.getByRole('button', { name: 'Resubmit Documents' })).toBeVisible();

    await wizard.getByRole('button', { name: 'Resubmit Documents' }).click();
    await expect(requestedCard.locator('.ui-document-inline-error')).toContainText('required document');

    await requestedInput.setInputFiles(pdfPath);
    await wizard.getByRole('button', { name: 'Resubmit Documents' }).click();
    await expect(page.locator('body')).toContainText('Documents resubmitted successfully!');
    await expect(wizard.locator('#wizardStepLabel')).toContainText('Resubmitted Documents Under Review');
    await expect(wizard.locator('#step1 .resubmission-notice')).toHaveCount(0);

    const application = await (await page.request.get('user/get_application_details.php?id=1')).json();
    expect(application.application).toMatchObject({ status: 'Resubmitted', workflow_stage: 'secretary_review' });
    expect(application.application.resubmission_documents).toBeNull();
  });

  test('Secretary, Dean, and applicant complete the full review-to-assignment workflow', async ({ page }) => {
    await login(page, accounts.secretary);
    let token = await csrfToken(page);
    const transfer = await page.request.post('admin/api/secretary_actions.php', {
      headers: { 'X-CSRF-Token': token },
      form: { action: 'transfer_to_dept_head', application_id: '1', notes: 'Documents verified by E2E.' },
    });
    expect(transfer.status()).toBe(200);
    expect(await transfer.json()).toMatchObject({ success: true });

    await login(page, accounts.deanCs);
    token = await csrfToken(page);

    await page.getByRole('button', { name: 'Applicants' }).click();
    const applicantRow = page.locator('#applicantsTableBody tr').filter({ hasText: 'Casey Pipeline' }).first();
    await expect(applicantRow).toBeVisible();
    await applicantRow.getByRole('button', { name: 'View Details' }).click();
    await page.locator('[data-applicant-tab="decision"]').click();
    await page.locator('#scheduleBtn').click();
    await expect(page.locator('#scheduleModal')).toBeVisible();

    const calendarRules = await page.locator('#interviewDate').evaluate(element => {
      const input = element as HTMLInputElement & { _flatpickr?: any };
      const picker = input._flatpickr;
      if (!picker) return { initialized: false, saturdayDisabled: false, sundayDisabled: false, mondayDisabled: true, rejectedValue: input.value };

      picker.jumpToDate(new Date(2030, 0, 5), false);
      const calendar = picker.calendarContainer as HTMLElement;
      const isDisabled = (label: string) => calendar.querySelector(`[aria-label="${label}"]`)?.classList.contains('flatpickr-disabled') === true;

      input.value = '2030-01-05';
      input.dispatchEvent(new Event('input', { bubbles: true }));

      return {
        initialized: true,
        saturdayDisabled: isDisabled('January 5, 2030'),
        sundayDisabled: isDisabled('January 6, 2030'),
        mondayDisabled: isDisabled('January 7, 2030'),
        rejectedValue: input.value,
      };
    });
    expect(calendarRules).toEqual({
      initialized: true,
      saturdayDisabled: true,
      sundayDisabled: true,
      mondayDisabled: false,
      rejectedValue: '',
    });
    await page.getByRole('button', { name: 'Close schedule interview dialog' }).click();

    const deanAction = async (action: string, extra: Record<string, string> = {}) => {
      const response = await page.request.post('admin/process_applicant_action.php', {
        headers: { 'X-CSRF-Token': token },
        form: { action, applicant_id: '1', ...extra },
      });
      const body = await response.json();
      expect(response.status(), `${action}: ${JSON.stringify(body)}`).toBe(200);
      expect(body.success, `${action}: ${JSON.stringify(body)}`).toBeTruthy();
    };

    const weekendInterview = await page.request.post('admin/process_applicant_action.php', {
      headers: { 'X-CSRF-Token': token },
      form: {
        action: 'schedule_interview', applicant_id: '1', interview_date: '2030-01-05', interview_time: '09:00',
        interview_location: 'Norzagaray College', interview_room: 'Room 101', interview_room_id: '1',
      },
    });
    await expect(weekendInterview.json()).resolves.toMatchObject({
      success: false,
      error: 'Interview schedules are only available on weekdays',
    });

    await deanAction('schedule_interview', {
      interview_date: '2030-01-07', interview_time: '09:00', interview_location: 'Norzagaray College', interview_room: 'Room 101', interview_room_id: '1',
    });
    await deanAction('approve_interview', { interview_notes: 'Interview passed.' });

    const weekendDemo = await page.request.post('admin/process_applicant_action.php', {
      headers: { 'X-CSRF-Token': token },
      form: {
        action: 'schedule_demo', applicant_id: '1', interview_date: '2030-01-06', interview_time: '10:00',
        interview_location: 'Norzagaray College', interview_room: 'Room 202', interview_notes: 'Prepare a lesson.',
      },
    });
    await expect(weekendDemo.json()).resolves.toMatchObject({
      success: false,
      error: 'Demo schedules are only available on weekdays',
    });

    await deanAction('schedule_demo', {
      interview_date: '2030-01-08', interview_time: '10:00', interview_location: 'Norzagaray College', interview_room: 'Room 202', interview_notes: 'Prepare a lesson.'
    });
    await deanAction('approve_demo', { demo_notes: 'Demo passed.' });
    await deanAction('schedule_psych', { interview_date: '2030-01-09', interview_time: '11:00', interview_notes: 'Bring an ID.' });

    const prematureHire = await page.request.post('admin/process_applicant_action.php', {
      headers: { 'X-CSRF-Token': token },
      form: { action: 'mark_initially_hired', applicant_id: '1', initially_hired_notes: 'Too early.' },
    });
    expect(prematureHire.status()).toBe(422);
    expect(await prematureHire.json()).toMatchObject({ success: false });

    await login(page, accounts.pipeline);
    const invalidReceipt = await page.request.post('user/upload_psych_receipt.php', {
      multipart: {
        application_id: '1', psych_receipt: { name: 'invalid-upload.txt', mimeType: 'text/plain', buffer: readFileSync(invalidPath) },
      },
    });
    expect(await invalidReceipt.json()).toMatchObject({ success: false });
    const receipt = await page.request.post('user/upload_psych_receipt.php', {
      multipart: {
        application_id: '1', psych_receipt: { name: 'valid-document.pdf', mimeType: 'application/pdf', buffer: readFileSync(pdfPath) },
      },
    });
    expect(await receipt.json()).toMatchObject({ success: true });

    await login(page, accounts.deanCs);
    token = await csrfToken(page);
    await deanAction('mark_initially_hired', { initially_hired_notes: 'Approved for teaching assignment.' });

    const job = await (await page.request.get('admin/gets_job.php?id=1')).json();
    expect(Number(job.available_sections)).toBe(2);
    const application = await (await page.request.get('admin/view_applicant.php?id=1')).json();
    expect(application.applicant).toMatchObject({ status: 'Passed', workflow_stage: 'passed' });

    await login(page, accounts.pipeline);
    await page.locator('#teachingLoadLink').click();
    await expect(page.locator('body')).toContainText('CS 101');
    await expect(page.locator('body')).toContainText(/₱200|200\/hour/);
  });
});
