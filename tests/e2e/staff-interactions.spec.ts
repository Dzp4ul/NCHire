import { expect, test } from '@playwright/test';
import { accounts, login } from './helpers';

test.describe('staff interactive controls', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    testInfo.skip(testInfo.project.name !== 'chromium-desktop', 'Detailed staff interactions run once in Chromium.');
    await login(page, accounts.secretary);
  });

  test('opening the profile menu closes the notifications menu', async ({ page }) => {
    test.fail(true, 'Known defect: the two header dropdowns remain open at the same time.');
    await page.locator('#notificationBtn').click();
    await expect(page.locator('#notificationsDropdown')).toBeVisible();
    await page.locator('#profileDropdownBtn').click();
    await expect(page.locator('#notificationsDropdown')).toBeHidden();
  });

  test('notifications, profile modal, password visibility, and chatbot controls work', async ({ page }) => {
    await page.locator('#notificationBtn').click();
    await expect(page.locator('#notificationsDropdown')).toBeVisible();
    await expect(page.locator('#notificationsList')).not.toContainText('Loading notifications...');
    await page.locator('#notificationBtn').click();
    await expect(page.locator('#notificationsDropdown')).toBeHidden();

    await page.locator('#profileDropdownBtn').click();
    await expect(page.locator('#profileDropdown')).toBeVisible();
    await page.getByRole('button', { name: 'My Profile' }).click();
    const profileModal = page.locator('#myProfileModal');
    await expect(profileModal).toBeVisible();
    await expect(profileModal.locator('#myProfileEmail')).toHaveAttribute('readonly', '');
    await profileModal.locator('#myProfilePassword').fill('Visible1!');
    await profileModal.locator('button[onclick*="togglePasswordVisibility"]').click();
    await expect(profileModal.locator('#myProfilePassword')).toHaveAttribute('type', 'text');
    await profileModal.getByRole('button', { name: 'Cancel' }).click();
    await expect(profileModal).toBeHidden();

    await page.locator('#chatbotFloatingButton').click();
    await expect(page.locator('#chatbotWidgetPanel')).toBeVisible();
    await expect(page.locator('#chatbotInput')).toBeFocused();
    await page.locator('#chatbotCloseBtn').click();
    await expect(page.locator('#chatbotWidgetPanel')).toBeHidden();
  });

  test('applicant search, status filters, details navigation, reset, and archive filters work', async ({ page }) => {
    await page.getByRole('button', { name: 'Applicants' }).click();
    const applicants = page.locator('#applicantsTableBody');
    await expect(applicants).toContainText('Casey Pipeline');

    await page.locator('#nameSearch').fill('Casey');
    await expect(applicants).toContainText('Casey Pipeline');
    await page.locator('#statusFilter').selectOption('Waiting for Interview Schedule');
    await expect(applicants).toContainText('Waiting for Interview Schedule');
    await page.getByRole('button', { name: /Reset filters/i }).click();
    await expect(page.locator('#nameSearch')).toHaveValue('');
    await expect(page.locator('#statusFilter')).toHaveValue('all');

    await applicants.locator('tr').filter({ hasText: 'Casey Pipeline' }).first()
      .getByRole('button', { name: 'View Details' }).click();
    await expect(page.locator('#applicantDetailsSection')).toBeVisible();
    await expect(page.locator('[data-applicant-tab="requirements"]')).toHaveClass(/is-active/);
    await expect(page.locator('#documentReviewerContent')).toBeVisible();
    await expect(page.locator('#selectedDocumentTitle')).toContainText('Application Letter');

    await page.locator('[data-applicant-tab="overview"]').click();
    await expect(page.locator('#applicantOverviewPanel')).toBeVisible();
    await expect(page.locator('#personalInfo')).toContainText('Casey');

    await page.locator('[data-applicant-tab="recruitment"]').click();
    await expect(page.locator('#applicantRecruitmentPanel')).toBeVisible();
    await page.locator('[data-applicant-tab="decision"]').click();
    await expect(page.locator('#applicantDecisionPanel')).toBeVisible();
    await expect(page.locator('#actionButtons')).toBeVisible();

    await page.getByRole('button', { name: 'Back to Applicants' }).click();
    await expect(page.locator('#applicantsSection')).toBeVisible();

    await page.getByRole('button', { name: 'Archive' }).click();
    const archive = page.locator('#archiveTableBody');
    await expect(archive).toContainText('Riley Restricted');
    await page.locator('#archiveStatusFilter').selectOption('cancelled');
    await expect(archive).toContainText('Cancelled');
    await page.locator('#archiveSearch').fill('Riley');
    await page.locator('#archiveSearch').dispatchEvent('keyup');
    await expect(archive).toContainText('Riley Restricted');
    await page.locator('#archiveSearch').fill('No Such Applicant');
    await page.locator('#archiveSearch').dispatchEvent('keyup');
    await expect(archive).toContainText('No archived applicants');
  });
});
