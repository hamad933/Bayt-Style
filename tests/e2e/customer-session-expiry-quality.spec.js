import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/session-expiry-quality');
fs.mkdirSync(outputDir, { recursive: true });

function watchRuntime(page) {
  const failures = [];
  page.on('pageerror', (error) => failures.push(`pageerror: ${error.message}`));
  page.on('console', (message) => {
    if (message.type() !== 'error') return;

    const text = message.text();
    const isExpectedDocument419 = text === 'Failed to load resource: the server responded with a status of 419 (unknown status)';
    if (!isExpectedDocument419) failures.push(`console.error: ${text}`);
  });
  page.on('response', (response) => {
    if (response.status() >= 500) failures.push(`HTTP ${response.status()}: ${response.url()}`);
    if (response.status() === 419 && response.request().resourceType() !== 'document') {
      failures.push(`unexpected HTTP 419: ${response.url()}`);
    }
  });
  return failures;
}

async function assertNoPageOverflow(page) {
  const metrics = await page.evaluate(() => ({
    documentWidth: document.documentElement.scrollWidth,
    viewportWidth: document.documentElement.clientWidth,
  }));
  expect(metrics.documentWidth).toBeLessThanOrEqual(metrics.viewportWidth + 1);
}

test('[QUALITY][419] expired customer session is Arabic, branded, recoverable and runtime-clean', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const runtimeFailures = watchRuntime(page);
  await page.goto('/cart');

  const [response] = await Promise.all([
    page.waitForResponse((candidate) => candidate.url().endsWith('/cart/items') && candidate.request().resourceType() === 'document'),
    page.evaluate(() => {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = '/cart/items';
      document.body.appendChild(form);
      form.submit();
    }),
  ]);

  expect(response.status()).toBe(419);
  await page.waitForLoadState('domcontentloaded');
  await page.screenshot({ path: path.join(outputDir, 'session-expired-390.png'), fullPage: true });

  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  await expect(page.getByRole('heading', { name: 'انتهت جلسة التصفح' })).toBeVisible();
  await expect(page.getByText('انتهت صلاحية الجلسة قبل إكمال الإجراء المطلوب.')).toBeVisible();
  await expect(page.getByRole('link', { name: 'إعادة تحميل الصفحة' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'العودة إلى الرئيسية' })).toBeVisible();
  await assertNoPageOverflow(page);
  expect(runtimeFailures).toEqual([]);
});
