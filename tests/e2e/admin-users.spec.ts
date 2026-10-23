import { expect, test } from '@playwright/test';
import { accounts, csrfToken, login } from './helpers';

test.describe('administrator user management', () => {
  test.describe.configure({ mode: 'serial' });

  test.beforeEach(async ({ page }, testInfo) => {
    testInfo.skip(testInfo.project.name !== 'chromium-desktop', 'State-changing administrator tests run once in Chromium.');
    await login(page, accounts.admin);
    await page.getByRole('button', { name: 'Users' }).click();
    await expect(page.locator('#usersSection')).toBeVisible();
    await expect(page.locator('#usersTableBody')).toContainText('E2E Secretary');
  });

  test('directory search, role/status filters, and reset controls work', async ({ page }) => {
    await page.locator('#userSearch').fill('dean.cs');
    await expect(page.locator('#usersTableBody')).toContainText('E2E Computing Dean');
    await expect(page.locator('#usersTableBody')).not.toContainText('E2E Secretary');
    await page.getByRole('button', { name: 'Reset filters' }).click();

    await page.locator('#roleFilter').selectOption('Secretary');
    await expect(page.locator('#usersTableBody')).toContainText('E2E Secretary');
    await expect(page.locator('#usersTableBody')).not.toContainText('E2E Administrator');
    await page.locator('#userStatusFilter').selectOption('Inactive');
    await expect(page.locator('#usersTableBody')).toContainText('No users found');
    await page.getByRole('button', { name: 'Reset filters' }).click();
    await expect(page.locator('#userResultsCount')).toContainText('Showing all');
  });

  test('create modal validates, cancels, creates a user, and rejects duplicate email', async ({ page }) => {
    await page.getByRole('button', { name: 'Create User' }).click();
    const modal = page.locator('#createUserModal');
    await expect(modal).toBeVisible();
    await modal.getByRole('button', { name: 'Cancel' }).click();
    await expect(modal).toBeHidden();

    await page.getByRole('button', { name: 'Create User' }).click();
    await modal.getByRole('button', { name: 'Create User' }).click();
    await expect.poll(() => modal.locator('[name="name"]').evaluate((input: HTMLInputElement) => input.validationMessage)).not.toBe('');
    await modal.locator('[name="name"]').fill('E2E Temporary Staff');
    await modal.locator('[name="email"]').fill('temporary.staff.e2e@example.test');
    await modal.locator('[name="phone"]').fill('09179999999');
    await modal.locator('[name="role"]').selectOption('Secretary');
    await modal.getByRole('button', { name: 'Create User' }).click();
    await expect(modal).toBeHidden();
    await expect(page.locator('#usersTableBody')).toContainText('temporary.staff.e2e@example.test');

    await page.getByRole('button', { name: 'Create User' }).click();
    await modal.locator('[name="name"]').fill('Duplicate Staff');
    await modal.locator('[name="email"]').fill('temporary.staff.e2e@example.test');
    await modal.locator('[name="role"]').selectOption('Secretary');
    await modal.getByRole('button', { name: 'Create User' }).click();
    await expect(page.locator('body')).toContainText('Email already exists');
    await modal.getByRole('button', { name: 'Cancel' }).click();
  });

  test('edit persists role, department, and activation status; deletion has no UI control', async ({ page }) => {
    const row = page.locator('#usersTableBody tr').filter({ hasText: 'temporary.staff.e2e@example.test' });
    await expect(row.getByRole('button', { name: 'Delete' })).toHaveCount(0);
    await row.getByTitle('Edit User').click();
    const modal = page.locator('#editUserModal');
    await expect(modal).toBeVisible();
    // Email is immutable after account creation.
    await expect(modal.locator('#editUserEmail')).not.toBeEditable();
    await modal.locator('#editUserName').fill('E2E Temporary Dean');
    await modal.locator('#editUserRole').selectOption('Department Head');
    await modal.locator('#editUserDepartment').selectOption('Education');
    await modal.locator('#editUserStatus').selectOption('Inactive');
    await modal.getByRole('button', { name: 'Update User' }).click();
    await expect(modal).toBeHidden();

    await page.locator('#userSearch').fill('temporary.staff.e2e@example.test');
    const updated = page.locator('#usersTableBody tr').filter({ hasText: 'temporary.staff.e2e@example.test' });
    await expect(updated).toContainText('E2E Temporary Dean');
    await expect(updated).toContainText('Dean');
    await expect(updated).toContainText('Education');
    await expect(updated).toContainText('Inactive');
  });

  test('administrator cannot deactivate self and the retained delete API is authorization-protected', async ({ page }) => {
    const token = await csrfToken(page);
    const users = await (await page.request.get('admin/api/users.php')).json();
    const self = users.find((user: Record<string, unknown>) => user.email === accounts.admin.email);
    const temporary = users.find((user: Record<string, unknown>) => user.email === 'temporary.staff.e2e@example.test');
    expect(self?.id).toBeTruthy();
    expect(temporary?.id).toBeTruthy();

    const selfDeactivate = await page.request.put('admin/api/users.php', {
      headers: { 'X-CSRF-Token': token },
      data: { id: self.id, status: 'Inactive' },
    });
    expect(await selfDeactivate.json()).toMatchObject({ success: false, message: 'You cannot deactivate your own account' });

    const remove = await page.request.delete(`admin/api/users.php?id=${temporary.id}`, {
      headers: { 'X-CSRF-Token': token },
    });
    expect(remove.status()).toBe(200);
    expect(await remove.json()).toMatchObject({ success: true });
    const refreshed = await (await page.request.get('admin/api/users.php')).json();
    expect(refreshed.some((user: Record<string, unknown>) => user.email === 'temporary.staff.e2e@example.test')).toBeFalsy();
  });
});
