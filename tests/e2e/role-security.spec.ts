import { expect, test } from '@playwright/test';
import { accounts, csrfToken, login } from './helpers';

test.describe('authorization, session, and route security', () => {
  test.beforeEach(async ({}, testInfo) => {
    testInfo.skip(testInfo.project.name !== 'chromium-desktop', 'Security mutation probes run once in Chromium.');
  });

  test('unauthenticated admin APIs reject access', async ({ request }) => {
    const endpoints = [
      'admin/gets_applicants.php',
      'admin/gets_job.php',
      'admin/api/users.php',
      'admin/api/applicants.php',
      'admin/dashboard_api.php',
      'admin/view_applicant.php?id=1',
      'api/chatbot/message.php',
    ];
    for (const endpoint of endpoints) {
      const response = await request.get(endpoint);
      expect([401, 403, 405], `${endpoint} must reject anonymous requests`).toContain(response.status());
    }
  });

  test('dashboard statistics reject anonymous requests', async ({ request }) => {
    const response = await request.get('admin/api/dashboard_stats.php');
    expect([401, 403]).toContain(response.status());
  });

  test('unauthenticated protected pages resolve to the public login page', async ({ page }) => {
    const response = await page.goto('admin/index.php');
    expect(response?.status()).toBe(200);
    await expect(page).toHaveURL(/\/public\/index\.php/);
    const userResponse = await page.goto('user/user.php');
    expect(userResponse?.status()).toBe(200);
    await expect(page).toHaveURL(/\/public\/index\.php/);
  });

  test('applicant session cannot access staff pages or APIs and cannot read another application', async ({ page }) => {
    await login(page, accounts.applicant);
    const adminApi = await page.request.get('admin/api/users.php');
    expect(adminApi.status()).toBe(401);

    const foreignApplication = await page.request.get('user/get_application_details.php?id=1');
    expect(foreignApplication.status()).toBe(200);
    expect((await foreignApplication.json()).success).toBeFalsy();

    await page.goto('admin/index.php');
    await expect(page).not.toHaveURL(/\/admin\/index\.php/);
  });

  test('applicant profile mutations require a CSRF token', async ({ page }) => {
    await login(page, accounts.applicant);
    const response = await page.request.post('user/save_profile_data.php', {
      form: {
        savePersonal: '1',
        applicant_fname: 'Avery',
        applicant_lname: 'Applicant',
        applicant_email: accounts.applicant.email,
        applicant_num: '+639171112222',
        applicant_address: 'Test address',
      },
    });
    expect(response.status()).toBe(403);
  });

  test('mutating admin APIs enforce CSRF tokens', async ({ page }) => {
    await login(page, accounts.secretary);
    const missingToken = await page.request.post('admin/toggle_job_status.php', {
      form: { job_id: '1', status: 'Closed' },
    });
    expect(missingToken.status()).toBe(403);
    expect(await missingToken.json()).toMatchObject({ success: false });

    const invalidToken = await page.request.post('admin/toggle_job_status.php', {
      headers: { 'X-CSRF-Token': 'invalid' },
      form: { job_id: '1', status: 'Closed' },
    });
    expect(invalidToken.status()).toBe(403);
  });

  test('Secretary cannot invoke Dean-only workflow transitions', async ({ page }) => {
    await login(page, accounts.secretary);
    const token = await csrfToken(page);
    const response = await page.request.post('admin/process_applicant_action.php', {
      headers: { 'X-CSRF-Token': token },
      form: {
        action: 'schedule_interview',
        applicant_id: '2',
        interview_date: '2099-11-02',
        interview_time: '09:00',
        interview_location: 'Norzagaray College',
        interview_room: 'Room 101',
        interview_room_id: '1',
      },
    });
    expect(response.status()).toBe(403);
    await expect(response.json()).resolves.toMatchObject({ success: false });
  });

  test('Dean cannot act on an application outside the assigned department', async ({ page }) => {
    await login(page, accounts.deanCs);
    const token = await csrfToken(page);
    const response = await page.request.post('admin/process_applicant_action.php', {
      headers: { 'X-CSRF-Token': token },
      form: {
        action: 'schedule_interview',
        applicant_id: '3',
        interview_date: '2099-11-02',
        interview_time: '09:00',
        interview_location: 'Norzagaray College',
        interview_room: 'Room 101',
        interview_room_id: '1',
      },
    });
    expect(response.status()).toBe(403);
    await expect(response.json()).resolves.toMatchObject({ success: false });
  });

  test('authorized staff can view archived applicant details without crossing department scope', async ({ page }) => {
    await login(page, accounts.secretary);
    const archiveResponse = await page.request.get('admin/get_archive.php');
    expect(archiveResponse.status()).toBe(200);
    const archive = await archiveResponse.json();
    const cancelled = archive.find((application: Record<string, unknown>) =>
      application.workflow_stage === 'cancelled' && application.assigned_to_department === 'Computing Studies'
    );
    expect(cancelled).toBeTruthy();
    const cancelledId = Number(cancelled.id);

    let detailsResponse = await page.request.get(`admin/view_applicant.php?id=${cancelledId}`);
    expect(detailsResponse.status()).toBe(200);
    await expect(detailsResponse.json()).resolves.toMatchObject({
      success: true,
      applicant: { id: cancelledId, workflow_stage: 'cancelled' },
    });

    await login(page, accounts.deanCs);
    detailsResponse = await page.request.get(`admin/view_applicant.php?id=${cancelledId}`);
    expect(detailsResponse.status()).toBe(200);
    await expect(detailsResponse.json()).resolves.toMatchObject({
      success: true,
      applicant: { id: cancelledId, workflow_stage: 'cancelled' },
    });

    await page.getByRole('button', { name: 'Archive' }).click();
    const archivedRow = page.locator('#archiveTableBody tr').filter({ hasText: 'Riley Restricted' }).first();
    await expect(archivedRow).toBeVisible();
    await archivedRow.getByRole('button', { name: 'View Details' }).click();
    await expect(page.locator('#applicantDetailsSection')).toBeVisible();
    await expect(page.locator('#applicantReviewName')).toContainText('Riley Restricted');

    await login(page, accounts.deanEducation);
    detailsResponse = await page.request.get(`admin/view_applicant.php?id=${cancelledId}`);
    expect(detailsResponse.status()).toBe(403);
    await expect(detailsResponse.json()).resolves.toMatchObject({ success: false });
  });

  test('non-admin staff cannot create or delete staff accounts', async ({ page }) => {
    await login(page, accounts.secretary);
    const token = await csrfToken(page);
    const ownDirectory = await page.request.get('admin/api/users.php');
    expect(ownDirectory.status()).toBe(200);
    const ownRows = await ownDirectory.json();
    expect(ownRows).toHaveLength(1);
    expect(ownRows[0].email).toBe(accounts.secretary.email);

    const create = await page.request.post('admin/api/users.php', {
      headers: { 'X-CSRF-Token': token },
      data: { name: 'Unauthorized User', email: 'unauthorized@example.test', password: 'Password1!', role: 'Admin', department: 'Administration' },
    });
    expect(create.status()).toBe(403);

    const remove = await page.request.delete('admin/api/users.php?id=1', {
      headers: { 'X-CSRF-Token': token },
    });
    expect(remove.status()).toBe(403);
  });

  test('non-admin staff cannot elevate their own role through the users API', async ({ page }) => {
    await login(page, accounts.securitySecretary);
    const token = await csrfToken(page);
    const ownDirectory = await page.request.get('admin/api/users.php');
    const [self] = await ownDirectory.json();
    const response = await page.request.put('admin/api/users.php', {
      headers: { 'X-CSRF-Token': token },
      data: { user_id: self.id, role: 'Admin', status: 'Active' },
    });
    expect(response.status()).toBe(403);
  });

  test('diagnostic routes cannot create an authenticated Secretary session', async ({ page }) => {
    const routes = ['admin/api/test_secretary_api.php', 'admin/test_secretary_transfer_notification.php'];
    for (const route of routes) {
      const response = await page.goto(route);
      expect([401, 403, 404]).toContain(response?.status());
    }
    const protectedResponse = await page.request.get('admin/gets_applicants.php');
    expect([401, 403]).toContain(protectedResponse.status());
  });

  test('removed candidate-ranking pages and APIs remain unavailable', async ({ page }) => {
    await login(page, accounts.deanCs);
    await expect(page.locator('body')).not.toContainText(/candidate ranking|rank candidates/i);
    for (const route of ['admin/candidate_rankings.php', 'admin/api/candidate_rankings.php', 'api/rank_candidates.php']) {
      const response = await page.request.get(route);
      const body = await response.text();
      expect(body, route).not.toMatch(/candidate ranking|rank candidates/i);
    }
  });

  test('admin logout invalidates protected API access', async ({ page }) => {
    await login(page, accounts.admin);
    expect((await page.request.get('admin/api/users.php')).status()).toBe(200);
    await page.goto('admin/logout.php');
    const afterLogout = await page.request.get('admin/api/users.php');
    expect(afterLogout.status()).toBe(401);
  });
});

