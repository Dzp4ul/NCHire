import { expect, test } from '@playwright/test';
import { accounts, csrfToken, login } from './helpers';

async function ask(page: import('@playwright/test').Page, message: string) {
  const token = await csrfToken(page);
  return page.request.post('api/chatbot/message.php', {
    headers: { 'X-CSRF-Token': token },
    data: { message },
  });
}

async function verifiedBody(response: import('@playwright/test').APIResponse) {
  const body = await response.json();
  expect(response.status(), JSON.stringify(body)).toBe(200);
  return body;
}

test.describe('database-aware recruitment assistant', () => {
  test.beforeEach(async ({}, testInfo) => {
    testInfo.skip(testInfo.project.name !== 'chromium-desktop', 'Chatbot API checks run once in Chromium.');
  });

  test('Admin, Secretary, and Dean dashboards expose the assistant', async ({ browser, baseURL }) => {
    for (const account of [accounts.admin, accounts.secretary, accounts.deanCs]) {
      const context = await browser.newContext({ baseURL });
      const page = await context.newPage();
      await login(page, account);
      await expect(page.locator('#chatbotFloatingButton')).toBeVisible();
      await page.locator('#chatbotFloatingButton').click();
      await expect(page.locator('#chatbotWidgetPanel')).toBeVisible();
      await context.close();
    }
  });

  test('chatbot POST actions require the session CSRF token', async ({ page }) => {
    await login(page, accounts.secretary);
    const response = await page.request.post('api/chatbot/message.php', {
      data: { action: 'history' },
    });
    expect(response.status()).toBe(403);
    await expect(response.json()).resolves.toMatchObject({ success: false, type: 'invalid_csrf_token' });

    const token = await csrfToken(page);
    const authorized = await page.request.post('api/chatbot/message.php', {
      headers: { 'X-CSRF-Token': token },
      data: { action: 'history' },
    });
    expect(authorized.status()).toBe(200);
    await expect(authorized.json()).resolves.toMatchObject({ success: true, type: 'conversation_history' });
  });

  test('assistant answers render tables as responsive record cards', async ({ page }) => {
    await login(page, accounts.secretary);
    await page.locator('#chatbotFloatingButton').click();
    await page.evaluate(() => {
      (window as any).appendChatbotMessage(
        'assistant',
        '### Vacant Teaching Loads\n| Load ID | Job Title | Program | Available |\n|---|---|---|---|\n| 109 | Software Engineering | BS Computer Science | 2 |\n| 77 | Instructor | Computing Studies | 1 |'
      );
    });

    await expect(page.locator('[data-chatbot-table-card]')).toHaveCount(2);
    await expect(page.locator('[data-chatbot-table-card]').first()).toContainText('Software Engineering');
    await expect(page.locator('[data-chatbot-table-card]').first()).toContainText('BS Computer Science');
    await expect(page.locator('#chatbotMessages')).not.toContainText('|---|');
    await expect(page.getByRole('dialog', { name: 'NCHire AI Recruitment Assistant' }).getByRole('heading', { name: 'Vacant Teaching Loads', exact: true })).toBeVisible();
  });

  test('Secretary receives a verified pending count and contextual follow-up', async ({ page }) => {
    test.skip(process.env.NCHIRE_RUN_GROQ_E2E !== '1', 'Set NCHIRE_RUN_GROQ_E2E=1 to call the configured Groq model.');
    await login(page, accounts.secretary);

    const pendingResponse = await ask(page, 'How many applications are currently pending? Use the current NCHire records.');
    const pending = await verifiedBody(pendingResponse);
    expect(pending).toMatchObject({ success: true, grounded: true });
    expect(pending.tools_used).toContain('search_applications');
    expect(pending.reply).toMatch(/\b1\b/);
    expect(pending.reply).toContain('Data checked:');

    const listResponse = await ask(page, 'Show all active applications for E2E CS 101 - Web Systems with their application IDs.');
    const list = await verifiedBody(listResponse);
    expect(list).toMatchObject({ success: true, grounded: true });
    expect(list.reply).toContain('Casey Pipeline');

    const followUpResponse = await ask(page, 'Which of them have completed all required documents?');
    const followUp = await verifiedBody(followUpResponse);
    expect(followUp).toMatchObject({ success: true, grounded: true });
    expect(followUp.tools_used).toContain('search_applications');
    expect(followUp.reply).toMatch(/completed|complete/i);
  });

  test('Dean answer does not leak another department', async ({ page }) => {
    test.skip(process.env.NCHIRE_RUN_GROQ_E2E !== '1', 'Set NCHIRE_RUN_GROQ_E2E=1 to call the configured Groq model.');
    await login(page, accounts.deanCs);
    const response = await ask(page, 'List every active applicant currently visible to me, including names and departments.');
    const body = await verifiedBody(response);
    expect(body).toMatchObject({ success: true, grounded: true });
    expect(body.reply).toContain('Casey Pipeline');
    expect(body.reply).not.toContain('Riley Restricted');
  });

  test('Admin receives aggregate and basic recruitment data without detailed qualifications', async ({ page }) => {
    test.skip(process.env.NCHIRE_RUN_GROQ_E2E !== '1', 'Set NCHIRE_RUN_GROQ_E2E=1 to call the configured Groq model.');
    await login(page, accounts.admin);
    const response = await ask(page, 'How many active applications and unique applicants are there?');
    const body = await verifiedBody(response);
    expect(body).toMatchObject({ success: true, grounded: true });
    expect(body.reply).toMatch(/\b3\b/);
    expect(body.reply).toMatch(/\b2\b/);
  });
});
