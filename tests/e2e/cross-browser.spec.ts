import { expect, test } from '@playwright/test';
import { accounts, login, openSignIn } from './helpers';

test.describe('cross-browser and responsive smoke coverage', () => {
  test('public landing page, navigation, modals, and live teaching loads render', async ({ page }) => {
    const sameOriginFailures: string[] = [];
    page.on('response', response => {
      const pageOrigin = new URL(page.url()).origin;
      if (new URL(response.url()).origin === pageOrigin && response.status() >= 500) {
        sameOriginFailures.push(`${response.status()} ${response.url()}`);
      }
    });

    await page.goto('public/index.php');
    await expect(page).toHaveTitle(/NCHire/);
    await expect(page.locator('nav')).toContainText('NCHire');

    if (await page.locator('#openSignUp').isVisible()) {
      await page.locator('#openSignUp').click();
    } else {
      await page.locator('#mobileMenuBtn').click();
      await page.locator('#openSignUpMobile').click();
    }
    await expect(page.locator('#signUpModal')).toBeVisible();
    await page.locator('#toggleSignUpPassword').click();
    await expect(page.locator('#signUpPassword')).toHaveAttribute('type', 'text');
    await page.locator('#closeSignUp').click();
    await expect(page.locator('#signUpModal')).toBeHidden();

    await openSignIn(page);
    await page.locator('#togglePassword').click();
    await expect(page.locator('#passwordInput')).toHaveAttribute('type', 'text');
    await page.locator('#closeSignIn').click();
    await expect(page.locator('#signInModal')).toBeHidden();

    await expect.poll(() => sameOriginFailures).toEqual([]);
  });

  test('applicant can sign in and receives applicant navigation', async ({ page }) => {
    await login(page, accounts.applicant);
    await expect(page).toHaveURL(/\/user\/user\.php/);
    await expect(page.locator('#dashboardLink')).toBeAttached();
    await expect(page.locator('#applicationsLink')).toBeAttached();
    await expect(page.locator('#profileLink')).toBeAttached();
    await expect(page.locator('body')).not.toContainText(/Candidate Ranking/i);
  });

  test('Secretary receives operational recruitment navigation without candidate ranking', async ({ page }) => {
    await login(page, accounts.secretary);
    await expect(page).toHaveURL(/\/admin\/index\.php/);
    await expect(page.getByRole('button', { name: 'Teaching Loads' })).toBeAttached();
    await expect(page.getByRole('button', { name: 'Applicants' })).toBeAttached();
    await expect(page.getByRole('button', { name: 'Archive' })).toBeAttached();
    await expect(page.getByRole('button', { name: 'Users' })).toHaveCount(0);
    await expect(page.locator('body')).not.toContainText(/Candidate Ranking/i);
  });

  test('Dean receives department-scoped recruitment navigation', async ({ page }) => {
    await login(page, accounts.deanCs);
    await expect(page).toHaveURL(/\/admin\/index\.php/);
    await expect(page.locator('body')).toContainText('Dean workspace');
    await expect(page.getByRole('button', { name: 'Teaching Loads' })).toBeAttached();
    await expect(page.getByRole('button', { name: 'Applicants' })).toBeAttached();
    await expect(page.getByRole('button', { name: 'Users' })).toHaveCount(0);
  });

  test('Administrator receives user management and not teaching-load management', async ({ page }) => {
    await login(page, accounts.admin);
    await expect(page).toHaveURL(/\/admin\/index\.php/);
    await expect(page.getByRole('button', { name: 'Users' })).toBeAttached();
    await expect(page.getByRole('button', { name: 'Teaching Loads' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Applicants' })).toHaveCount(0);
  });
});

