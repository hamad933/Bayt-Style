import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 30_000,
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000',
    browserName: 'chromium',
    viewport: { width: 1440, height: 1000 },
    locale: 'ar-SA',
  },
  reporter: [['list'], ['html', { outputFolder: 'storage/test-artifacts/playwright-report', open: 'never' }]],
});
