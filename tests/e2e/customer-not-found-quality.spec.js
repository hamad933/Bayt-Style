import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/not-found-quality');
fs.mkdirSync(outputDir, { recursive: true });

function watchRuntime(page) {
  const failures = [];
  page.on('pageerror', (error) => failures.push(`pageerror: ${error.message}`));
  page.on('console', (message) => {
    if (message.type() !== 'error') return;

    const text = message.text();
    const isExpectedDocument404 = text === 'Failed to load resource: the server responded with a status of 404 (Not Found)';
    if (!isExpectedDocument404) failures.push(`console.error: ${text}`);
  });
  page.on('response', (response) => {
    if (response.status() >= 500) failures.push(`HTTP ${response.status()}: ${response.url()}`);
    if (response.status() === 404 && response.request().resourceType() !== 'document') {
      failures.push(`unexpected HTTP 404: ${response.url()}`);
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

for (const target of [
  { name: 'missing-product', path: '/products/rp01-product-that-does-not-exist' },
  { name: 'missing-order', path: '/orders/RP01-NOT-AN-ORDER-000001' },
]) {
  test(`[QUALITY][404] ${target.name} is Arabic, branded, recoverable and runtime-clean`, async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const runtimeFailures = watchRuntime(page);
    const response = await page.goto(target.path);

    expect(response).not.toBeNull();
    expect(response.status()).toBe(404);
    await page.screenshot({ path: path.join(outputDir, `${target.name}-390.png`), fullPage: true });

    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.getByRole('heading', { name: 'الصفحة غير موجودة' })).toBeVisible();
    await expect(page.getByText('تعذر العثور على الصفحة التي طلبتها.')).toBeVisible();
    await expect(page.getByRole('link', { name: 'العودة إلى الرئيسية' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'استعراض الكتالوج' })).toBeVisible();
    await assertNoPageOverflow(page);
    expect(runtimeFailures).toEqual([]);
  });
}
