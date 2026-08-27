import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/public-empty-quality');
fs.mkdirSync(outputDir, { recursive: true });

function watchRuntime(page) {
  const failures = [];
  page.on('pageerror', (error) => failures.push(`pageerror: ${error.message}`));
  page.on('console', (message) => {
    if (message.type() === 'error') failures.push(`console.error: ${message.text()}`);
  });
  page.on('response', (response) => {
    if (response.status() >= 500) failures.push(`HTTP ${response.status()}: ${response.url()}`);
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

async function assertHealthyNavigation(response) {
  expect(response).not.toBeNull();
  expect(response.status()).toBeLessThan(400);
}

test('[QUALITY][PUBLIC EMPTY] catalog no-results recovery is visible on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const runtimeFailures = watchRuntime(page);
  const response = await page.goto('/catalog?q=NO_SUCH_RP01_PUBLIC_PRODUCT_001');
  await assertHealthyNavigation(response);

  await expect(page.getByTestId('catalog-search')).toHaveValue('NO_SUCH_RP01_PUBLIC_PRODUCT_001');
  const emptyState = page.getByTestId('no-results');
  await expect(emptyState).toBeVisible();
  await expect(emptyState.getByRole('heading', { name: 'لم نجد قطعًا مطابقة' })).toBeVisible();
  const recovery = emptyState.getByRole('link', { name: 'عرض كل المنتجات' });
  await expect(recovery).toBeVisible();
  await expect(recovery).toHaveAttribute('href', /\/catalog$/);
  await expect(page.getByTestId('catalog-results')).toHaveCount(0);
  await assertNoPageOverflow(page);
  expect(runtimeFailures).toEqual([]);

  await page.screenshot({ path: path.join(outputDir, 'catalog-no-results-390.png'), fullPage: true });
});

test('[QUALITY][PUBLIC EMPTY] wishlist empty state is visible and recoverable on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const runtimeFailures = watchRuntime(page);
  const response = await page.goto('/wishlist');
  await assertHealthyNavigation(response);

  const emptyState = page.getByTestId('wishlist-empty');
  await expect(emptyState).toBeVisible();
  await expect(emptyState.getByRole('heading', { name: 'ابدأ من التشكيلة التي تناسب مساحتك.' })).toBeVisible();
  const recovery = emptyState.getByRole('link', { name: 'استكشف المنتجات' });
  await expect(recovery).toBeVisible();
  await expect(recovery).toHaveAttribute('href', /\/catalog$/);
  await expect(page.getByTestId('wishlist-list')).toHaveCount(0);
  await assertNoPageOverflow(page);
  expect(runtimeFailures).toEqual([]);

  await page.screenshot({ path: path.join(outputDir, 'wishlist-empty-390.png'), fullPage: true });
});

test('[QUALITY][PUBLIC EMPTY] comparison empty state is visible and recoverable on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const runtimeFailures = watchRuntime(page);
  const response = await page.goto('/comparison');
  await assertHealthyNavigation(response);

  const emptyState = page.getByTestId('comparison-empty');
  await expect(emptyState).toBeVisible();
  await expect(emptyState.getByRole('heading', { name: /اختر حتى \d+ منتجات من الكتالوج أو صفحات التفاصيل\./ })).toBeVisible();
  const recovery = emptyState.getByRole('link', { name: 'اذهب إلى الكتالوج' });
  await expect(recovery).toBeVisible();
  await expect(recovery).toHaveAttribute('href', /\/catalog$/);
  await expect(page.getByTestId('comparison-grid')).toHaveCount(0);
  await assertNoPageOverflow(page);
  expect(runtimeFailures).toEqual([]);

  await page.screenshot({ path: path.join(outputDir, 'comparison-empty-390.png'), fullPage: true });
});
