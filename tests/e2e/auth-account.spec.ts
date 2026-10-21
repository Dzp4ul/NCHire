import { expect, test } from '@playwright/test';
import { accounts, login, openSignIn } from './helpers';

test.describe('authentication and account management', () => {
  test.beforeEach(async ({}, testInfo) => {
    testInfo.skip(testInfo.project.name !== 'chromium-desktop', 'State-changing account tests run once in Chromium.');
  });
  test.describe.configure({ mode: 'serial' });

  test('required fields and invalid credentials are rejected', async ({ page }) => {
    await openSignIn(page);
    await page.locator('#signInModal button[name="login_submit"]').click();
    await expect(page.locator('#signInEmail')).toBeFocused();
    await expect.poll(() => page.locator('#signInEmail').evaluate((input: HTMLInputElement) => input.validationMessage)).not.toBe('');

    await page.locator('#signInEmail').fill('missing.e2e@example.test');
    await page.locator('#passwordInput').fill('WrongPass1!');
    await page.locator('#signInModal button[name="login_submit"]').click();
    await expect(page.locator('#signInModal')).toBeVisible();
    await expect(page.locator('#signInModal')).toContainText('Email not found.');

    await page.locator('#signInEmail').fill(accounts.applicant.email);
    await page.locator('#passwordInput').fill('WrongPass1!');
    await page.locator('#signInModal button[name="login_submit"]').click();
    await expect(page.locator('#signInModal')).toContainText('Invalid password.');
  });

  test('inactive staff accounts cannot sign in', async ({ page }) => {
    await login(page, accounts.inactive);
    await expect(page).toHaveURL(/\/public\/index\.php/);
    await expect(page.locator('#signInModal')).toBeVisible();
    await expect(page.locator('body')).toContainText('deactivated');
  });

  test('registration validates names, email, password strength, mismatch, and duplicates', async ({ page }) => {
    await page.goto('public/index.php');
    await page.locator('#openSignUp').click();

    await page.locator('#signUpFirstName').fill('Test123');
    await expect(page.locator('#signUpFirstName')).toHaveValue('Test');
    await page.locator('#signUpLastName').fill('Applicant');
    await page.locator('#signUpEmailInput').fill('not-an-email');
    await page.locator('#signUpPassword').fill('short');
    await page.locator('#signUpConfirmPassword').fill('different');
    await page.locator('#signUpModal button[name="signup_submit"]').click();
    await expect.poll(() => page.locator('#signUpEmailInput').evaluate((input: HTMLInputElement) => input.validationMessage)).not.toBe('');

    await page.locator('#signUpEmailInput').fill('new.account.e2e@example.test');
    await page.locator('#signUpPassword').fill('Password1!');
    await page.locator('#signUpConfirmPassword').fill('Password2!');
    await page.locator('#signUpModal button[name="signup_submit"]').click();
    await expect(page.locator('#signUpModal')).toBeVisible();
    await expect(page.locator('#verifyModal')).toBeHidden();

    await page.locator('#signUpEmailInput').fill(accounts.applicant.email);
    await page.locator('#signUpPassword').fill('Password1!');
    await page.locator('#signUpConfirmPassword').fill('Password1!');
    await page.locator('#signUpModal button[name="signup_submit"]').click();
    await expect(page.locator('#signUpModal')).toContainText('Email already exists.');
  });

  test('successful registration verifies the account and permits login', async ({ page }) => {
    await page.goto('public/index.php');
    await page.locator('#openSignUp').click();
    await page.locator('#signUpFirstName').fill('New');
    await page.locator('#signUpLastName').fill('Account');
    await page.locator('#signUpEmailInput').fill('new.account.e2e@example.test');
    await page.locator('#signUpPassword').fill('Password1!');
    await page.locator('#signUpConfirmPassword').fill('Password1!');
    await page.locator('#signUpModal button[name="signup_submit"]').click();

    await expect(page.locator('#verifyModal')).toBeVisible();
    await page.locator('#verifyForm input[name="verification_code"]').fill('WRONG1');
    await page.evaluate(() => {
      window.alert = message => {
        document.documentElement.dataset.lastAlert = String(message);
      };
    });
    await page.locator('#verifyForm button[type="submit"]').click();
    await expect(page.locator('html')).toHaveAttribute('data-last-alert', /Invalid verification code/);

    await page.locator('#verifyForm input[name="verification_code"]').fill('ABC123');
    await page.locator('#verifyForm button[type="submit"]').click();
    await expect(page.locator('#verificationSuccessPopup')).toBeVisible();
    await page.getByRole('button', { name: 'Continue to Sign In' }).click();
    await expect(page.locator('#signInModal')).toBeVisible();

    await page.locator('#signInEmail').fill('new.account.e2e@example.test');
    await page.locator('#passwordInput').fill('Password1!');
    await page.locator('#signInModal button[name="login_submit"]').click();
    await expect(page).toHaveURL(/\/user\/user\.php/);
  });

  test('forgot-password handles unknown accounts and forces a password change for a valid account', async ({ page }) => {
    await page.goto('public/forgot_password.php');
    await page.locator('#email').fill('unknown.e2e@example.test');
    await page.getByRole('button', { name: /Send Temporary Password/i }).click();
    await expect(page.locator('body')).toContainText('No account found');

    await page.locator('#email').fill('new.account.e2e@example.test');
    await page.getByRole('button', { name: /Send Temporary Password/i }).click();
    await expect(page.locator('body')).toContainText('temporary password has been sent');

    await login(page, { email: 'new.account.e2e@example.test', password: 'TempPass1!' });
    await expect(page).toHaveURL(/\/user\/change_password\.php/);
    await page.locator('input[name="new_password"]').fill('weak');
    await page.locator('input[name="confirm_password"]').fill('weak');
    await page.getByRole('button', { name: /Change Password/i }).click();
    await expect.poll(() => page.locator('input[name="new_password"]').evaluate((input: HTMLInputElement) => input.validationMessage)).not.toBe('');

    await page.locator('input[name="new_password"]').fill('ChangedPass1!');
    await page.locator('input[name="confirm_password"]').fill('ChangedPass1!');
    await page.getByRole('button', { name: /Change Password/i }).click();
    await expect(page.locator('body')).toContainText('Password changed successfully');
  });

  test('authenticated session works across tabs and logout invalidates applicant APIs', async ({ page, context }) => {
    await login(page, accounts.applicant);
    const secondTab = await context.newPage();
    await secondTab.goto('user/user_profile.php');
    await expect(secondTab).toHaveURL(/\/user\/user_profile\.php/);
    await expect(secondTab.locator('body')).toContainText('Avery');

    await page.goto('public/index.php?logout=1');
    const apiResponse = await page.request.get('user/get_profile_data.php?type=education');
    const payload = await apiResponse.json();
    expect(payload.success).toBeFalsy();
    await secondTab.reload();
    await expect(secondTab).toHaveURL(/\/public\/index\.php/);
  });

  test('login rotates the PHP session identifier to prevent fixation', async ({ page, context }) => {
    test.fail(true, 'Known security defect: login does not regenerate the PHP session ID.');
    await page.goto('public/index.php');
    const before = (await context.cookies()).find(cookie => cookie.name === 'PHPSESSID')?.value;
    await login(page, accounts.applicant);
    const after = (await context.cookies()).find(cookie => cookie.name === 'PHPSESSID')?.value;
    expect(before).toBeTruthy();
    expect(after).toBeTruthy();
    expect(after).not.toBe(before);
  });
});

