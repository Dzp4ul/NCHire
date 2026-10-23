import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

const appRoot = process.env.NCHIRE_E2E_APP_ROOT || '';
const host = process.env.NCHIRE_E2E_HOST || '127.0.0.1';
const port = Number(process.env.NCHIRE_E2E_PORT || 4173);
const origin = `http://${host}:${port}`;
const baseURL = `${origin}${appRoot}`;

export default defineConfig({
  testDir: './tests/e2e',
  globalSetup: './tests/e2e/global-setup.ts',
  fullyParallel: false,
  workers: 1,
  timeout: 45_000,
  expect: { timeout: 8_000 },
  outputDir: 'test-results/playwright',
  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ['json', { outputFile: 'test-results/playwright-results.json' }],
  ],
  use: {
    baseURL: `${baseURL}/`,
    actionTimeout: 10_000,
    navigationTimeout: 20_000,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    video: 'retain-on-failure',
  },
  webServer: {
    command: `php -S ${host}:${port} -t .`,
    cwd: path.resolve(__dirname),
    url: `${baseURL}/public/index.php`,
    timeout: 30_000,
    reuseExistingServer: false,
    stdout: 'ignore',
    stderr: 'ignore',
    env: {
      ...process.env,
      NCHIRE_DB_NAME: 'nchire_e2e',
      NCHIRE_TEST_MODE: '1',
      NCHIRE_TEST_VERIFICATION_CODE: 'ABC123',
      NCHIRE_TEST_TEMP_PASSWORD: 'TempPass1!',
    },
  },
  projects: [
    {
      name: 'chromium-desktop',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1000 } },
    },
    {
      name: 'firefox-desktop',
      testMatch: /cross-browser\.spec\.ts/,
      use: { ...devices['Desktop Firefox'], viewport: { width: 1440, height: 1000 } },
    },
    {
      name: 'webkit-desktop',
      testMatch: /cross-browser\.spec\.ts/,
      use: { ...devices['Desktop Safari'], viewport: { width: 1440, height: 1000 } },
    },
    {
      name: 'chromium-tablet',
      testMatch: /cross-browser\.spec\.ts/,
      use: { ...devices['iPad Pro 11'] },
    },
    {
      name: 'chromium-mobile',
      testMatch: /cross-browser\.spec\.ts/,
      use: { ...devices['Pixel 7'] },
    },
  ],
});
