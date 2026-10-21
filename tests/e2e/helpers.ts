import { expect, Page } from '@playwright/test';

export const accounts = {
  applicant: { email: 'applicant.e2e@example.test', password: 'ApplicantPass1!' },
  pipeline: { email: 'pipeline.e2e@example.test', password: 'ApplicantPass1!' },
  incomplete: { email: 'incomplete.e2e@example.test', password: 'ApplicantPass1!' },
  restricted: { email: 'restricted.e2e@example.test', password: 'ApplicantPass1!' },
  admin: { email: 'admin.e2e@example.test', password: 'AdminPass1!' },
  secretary: { email: 'secretary.e2e@example.test', password: 'AdminPass1!' },
  securitySecretary: { email: 'security.secretary.e2e@example.test', password: 'AdminPass1!' },
  deanCs: { email: 'dean.cs.e2e@example.test', password: 'AdminPass1!' },
  deanEducation: { email: 'dean.edu.e2e@example.test', password: 'AdminPass1!' },
  inactive: { email: 'inactive.e2e@example.test', password: 'AdminPass1!' },
} as const;

export async function openSignIn(page: Page): Promise<void> {
  await page.goto('public/index.php');
  const desktopButton = page.locator('#openSignIn');
  if (await desktopButton.isVisible()) {
    await desktopButton.click();
  } else {
    await page.locator('#mobileMenuBtn').click();
    await page.locator('#openSignInMobile').click();
  }
  await expect(page.locator('#signInModal')).toBeVisible();
}

export async function login(page: Page, account: { email: string; password: string }): Promise<void> {
  await openSignIn(page);
  await page.locator('#signInEmail').fill(account.email);
  await page.locator('#passwordInput').fill(account.password);
  await page.locator('#signInModal button[name="login_submit"]').click();
  await page.waitForLoadState('domcontentloaded');
}

export function collectRuntimeErrors(page: Page): string[] {
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(`pageerror: ${error.message}`));
  page.on('console', message => {
    if (message.type() === 'error') errors.push(`console: ${message.text()}`);
  });
  return errors;
}

export async function csrfToken(page: Page): Promise<string> {
  return page.locator('meta[name="csrf-token"]').getAttribute('content').then(value => value || '');
}
