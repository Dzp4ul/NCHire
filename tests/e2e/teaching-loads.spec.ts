import { expect, test } from '@playwright/test';
import { accounts, csrfToken, login } from './helpers';

test.describe('teaching-load and dynamic-requirement management', () => {
  test.describe.configure({ mode: 'serial' });

  test.beforeEach(async ({ page }, testInfo) => {
    testInfo.skip(testInfo.project.name !== 'chromium-desktop', 'State-changing teaching-load tests run once in Chromium.');
    await login(page, accounts.secretary);
    await page.getByRole('button', { name: 'Teaching Loads' }).click();
    await expect(page.locator('#jobsSection')).toBeVisible();
    await expect(page.locator('#jobsTableBody')).toContainText('CS 101 - Web Systems');
  });

  test('search, status, advanced filters, details, and modal controls work', async ({ page }) => {
    await page.locator('#jobSearchInput').fill('Learning Design');
    await expect(page.locator('#jobsTableBody')).toContainText('EDU 201 - Learning Design');
    await expect(page.locator('#jobsTableBody')).not.toContainText('CS 101 - Web Systems');

    await page.locator('#jobSearchInput').fill('');
    await page.locator('#jobStatusFilter').selectOption('closed');
    await expect(page.locator('#jobsTableBody')).toContainText('CS 404 - Closed Load');
    await expect(page.locator('#jobsTableBody')).not.toContainText('CS 101 - Web Systems');
    await page.locator('#jobStatusFilter').selectOption('all');

    await page.getByRole('button', { name: 'More Filters' }).click();
    await expect(page.locator('#moreFiltersModal')).toBeVisible();
    await page.locator('#jobDepartmentFilter').selectOption('Education');
    await page.locator('#jobTypeFilter').selectOption('Full-time');
    await page.locator('#moreFiltersModal').getByRole('button', { name: /Apply Filters/i }).click();
    await expect(page.locator('#jobsTableBody')).toContainText('EDU 201 - Learning Design');
    await expect(page.locator('#jobsTableBody')).not.toContainText('CS 101 - Web Systems');

    await page.getByRole('button', { name: 'More Filters' }).click();
    await page.locator('#jobDepartmentFilter').selectOption('all');
    await page.locator('#jobTypeFilter').selectOption('all');
    await page.locator('#moreFiltersModal').getByRole('button', { name: /Apply Filters/i }).click();
    const row = page.locator('#jobsTableBody tr').filter({ hasText: 'CS 101 - Web Systems' });
    await row.getByRole('button', { name: 'View' }).click();
    await expect(page.locator('#viewJobModal')).toBeVisible();
    await expect(page.locator('#viewJobModal')).toContainText('Web Systems');
    await page.locator('#viewJobModal').getByRole('button', { name: 'Close', exact: true }).click();
    await expect(page.locator('#viewJobModal')).toBeHidden();
  });

  test('create, calculate workload, persist requirements, edit, close, reopen, and delete a load', async ({ page }) => {
    await page.getByRole('button', { name: 'Create Teaching Load' }).click();
    const modal = page.locator('#createJobModal');
    const form = modal.locator('form');
    await expect(modal).toBeVisible();
    await expect(modal.locator('.application-requirement-row')).toHaveCount(12);

    await form.locator('[name="type"]').selectOption('Part-time');
    await form.locator('[name="deadline"]').fill('2099-11-30');
    await form.locator('[name="academic_year"]').fill('2099-2100');
    await form.locator('[name="semester"]').selectOption('Second Semester');
    await form.locator('[name="department"]').selectOption('Computing Studies');
    await form.locator('[name="subject_code"]').fill('QA 301');
    await form.locator('[name="subject_name"]').fill('Automated Verification');
    await form.locator('[name="program"]').fill('BS Computer Science');
    await form.locator('[name="subject"]').selectOption('Computing Studies Professional Subjects');
    await form.locator('[name="lecture_units"]').fill('3');
    await form.locator('[name="laboratory_units"]').fill('1');
    await expect(modal.locator('#createTeachingHoursDisplay')).toContainText('6 hours/week');
    await form.locator('[name="available_sections"]').fill('2');
    await form.locator('[name="description"]').fill('Synthetic Playwright-created teaching load.');

    await modal.getByRole('button', { name: 'Add Requirement' }).click();
    const custom = modal.locator('.application-requirement-row').last();
    await form.getByRole('button', { name: 'Create Teaching Load' }).click();
    await expect(custom.locator('.requirement-name-error')).toBeVisible();
    await custom.locator('.requirement-name').fill('QA Automation Portfolio');
    await custom.locator('.requirement-description').fill('Optional automation work samples.');
    await custom.locator('.requirement-category').selectOption('Supporting Documents');
    await custom.locator('.requirement-status').selectOption('0');

    await form.getByRole('button', { name: 'Create Teaching Load' }).click();
    await expect(modal).toBeHidden();
    await expect(page.locator('#jobsTableBody')).toContainText('QA 301 - Automated Verification');

    const allResponse = await page.request.get('admin/gets_job.php');
    const allJobs = await allResponse.json();
    const created = allJobs.find((job: Record<string, unknown>) => job.job_title === 'QA 301 - Automated Verification');
    expect(created).toBeTruthy();
    expect(Number(created.teaching_hours_per_week)).toBe(6);
    expect(Number(created.available_sections)).toBe(2);
    expect(created.application_requirements).toEqual(expect.arrayContaining([
      expect.objectContaining({ requirement_name: 'QA Automation Portfolio', is_required: false }),
    ]));

    let row = page.locator('#jobsTableBody tr').filter({ hasText: 'QA 301 - Automated Verification' });
    await row.locator('details summary').click();
    await row.getByRole('button', { name: 'Edit' }).click();
    const editModal = page.locator('#newEditJobModal');
    await expect(editModal).toBeVisible();
    await expect(editModal.locator('#editApplicationRequirements .requirement-name').last()).toHaveValue('QA Automation Portfolio');
    await editModal.locator('#editJobTitle').fill('QA 301 - Automated Verification Updated');
    await editModal.locator('#editSubjectName').fill('Automated Verification Updated');
    await editModal.locator('#editLectureUnits').fill('4');
    await editModal.locator('#editLaboratoryUnits').fill('0.5');
    await editModal.locator('#editAvailableSections').fill('3');
    await expect(editModal.locator('#editTeachingHoursDisplay')).toContainText('5.5 hours/week');
    const portfolio = editModal.locator('.application-requirement-row').last();
    await portfolio.locator('.requirement-status').selectOption('1');
    await editModal.getByRole('button', { name: /Update Job/i }).click();
    await expect(editModal).toBeHidden();
    await expect(page.locator('#jobsTableBody')).toContainText('QA 301 - Automated Verification Updated');

    const updatedResponse = await page.request.get(`admin/gets_job.php?id=${created.id}`);
    const updated = await updatedResponse.json();
    expect(Number(updated.teaching_hours_per_week)).toBe(5.5);
    expect(Number(updated.available_sections)).toBe(3);
    expect(updated.application_requirements).toEqual(expect.arrayContaining([
      expect.objectContaining({ requirement_name: 'QA Automation Portfolio', is_required: true }),
    ]));

    const token = await csrfToken(page);
    for (const status of ['Closed', 'Active']) {
      const response = await page.request.post('admin/toggle_job_status.php', {
        headers: { 'X-CSRF-Token': token },
        form: { job_id: String(created.id), status },
      });
      expect(response.status()).toBe(200);
      expect(await response.json()).toMatchObject({ success: true, job: { status } });
    }

    await page.evaluate(() => (window as unknown as { loadJobs: () => Promise<void> }).loadJobs());
    row = page.locator('#jobsTableBody tr').filter({ hasText: 'QA 301 - Automated Verification Updated' });
    await row.locator('details summary').click();
    await row.getByRole('button', { name: 'Delete' }).click();
    await expect(page.locator('#deleteJobModal')).toBeVisible();
    await page.locator('#deleteJobModal').getByRole('button', { name: 'Cancel' }).click();
    await expect(page.locator('#deleteJobModal')).toBeHidden();
    await row.getByRole('button', { name: 'Delete' }).click();
    await page.locator('#deleteJobModal').getByRole('button', { name: 'Delete' }).click();
    await expect(page.locator('#jobsTableBody')).not.toContainText('QA 301 - Automated Verification Updated');
  });

  test('requirement changes on a published load require confirmation and preserve different checklists', async ({ page }) => {
    const token = await csrfToken(page);
    const [csResponse, eduResponse] = await Promise.all([
      page.request.get('admin/gets_job.php?id=1'),
      page.request.get('admin/gets_job.php?id=2'),
    ]);
    const cs = await csResponse.json();
    const edu = await eduResponse.json();
    expect(cs.application_requirements.map((item: Record<string, unknown>) => item.requirement_name))
      .not.toEqual(edu.application_requirements.map((item: Record<string, unknown>) => item.requirement_name));

    const requirements = cs.application_requirements.map((item: Record<string, unknown>) => ({
      ...item,
      is_required: item.requirement_name === 'Teaching Portfolio' ? true : Boolean(Number(item.is_required)),
    }));
    const payload = {
      ...cs,
      application_requirements: requirements,
      csrf_token: token,
    };
    const warning = await page.request.post('admin/update_job.php', { data: payload });
    expect(warning.status()).toBe(409);
    const warningBody = await warning.json();
    expect(warningBody).toMatchObject({ success: false, confirmation_required: true });
    expect(Number(warningBody.affected_applicants)).toBeGreaterThan(0);

    const confirmed = await page.request.post('admin/update_job.php', {
      data: { ...payload, confirm_requirement_changes: true },
    });
    expect(confirmed.status()).toBe(200);
    expect(await confirmed.json()).toMatchObject({ success: true, requirements_changed: true });
    const persisted = await (await page.request.get('admin/gets_job.php?id=1')).json();
    expect(persisted.application_requirements).toEqual(expect.arrayContaining([
      expect.objectContaining({ requirement_name: 'Teaching Portfolio', is_required: true }),
    ]));
  });

  test('deleting an available load cancels and archives its related applications', async ({ page }) => {
    const row = page.locator('#jobsTableBody tr').filter({ hasText: 'CS 101 - Web Systems' });
    await row.locator('details summary').click();
    await row.getByRole('button', { name: 'Delete' }).click();

    const deleteModal = page.locator('#deleteJobModal');
    await expect(deleteModal).toBeVisible();
    await expect(deleteModal).toContainText('cancel 2 related applications');
    await deleteModal.getByRole('button', { name: 'Delete' }).click();

    await expect(page.locator('body')).toContainText('2 related applications were cancelled and moved to Archive');
    await expect(page.locator('#jobsTableBody')).not.toContainText('CS 101 - Web Systems');

    const archiveResponse = await page.request.get('admin/get_archive.php');
    expect(archiveResponse.status()).toBe(200);
    const archived = await archiveResponse.json();
    const cancelled = archived.filter((application: Record<string, unknown>) =>
      application.applicant_email === accounts.pipeline.email
      && application.position === 'E2E CS 101 - Web Systems'
    );
    expect(cancelled).toHaveLength(2);
    expect(cancelled).toEqual(expect.arrayContaining([
      expect.objectContaining({ status: 'Cancelled', workflow_stage: 'cancelled' }),
    ]));

    const activeResponse = await page.request.get('admin/gets_applicants.php');
    expect(activeResponse.status()).toBe(200);
    const activeApplications = await activeResponse.json();
    expect(activeApplications).not.toEqual(expect.arrayContaining([
      expect.objectContaining({ position: 'E2E CS 101 - Web Systems' }),
    ]));
  });
});
