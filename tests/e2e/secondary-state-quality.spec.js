import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/secondary-quality');
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

async function addConfiguredChair(page) {
  await page.goto('/products/olive-velvet-lounge-chair');
  const sand = page.locator('[data-option-key="color"][data-option-value="رملي"]');
  await expect(sand).toHaveAttribute('aria-pressed', 'false');
  await sand.click();
  await expect(sand).toHaveAttribute('aria-pressed', 'true');
  await page.getByTestId('add-to-cart').click();
  await expect(page.locator('.cart-badge')).toHaveText('1');
}

test('[QUALITY][SECONDARY] empty cart is explicit and usable on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const runtimeFailures = watchRuntime(page);
  const response = await page.goto('/cart');

  expect(response).not.toBeNull();
  expect(response.status()).toBeLessThan(400);
  await expect(page.getByRole('heading', { name: 'سلتك تنتظر أول قطعة' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'استكشف المنتجات' })).toBeVisible();

  const metrics = await page.evaluate(() => ({
    documentWidth: document.documentElement.scrollWidth,
    viewportWidth: document.documentElement.clientWidth,
  }));
  expect(metrics.documentWidth).toBeLessThanOrEqual(metrics.viewportWidth + 1);
  expect(runtimeFailures).toEqual([]);

  await page.screenshot({ path: path.join(outputDir, 'empty-cart-390.png'), fullPage: true });
});

test('[QUALITY][SECONDARY] checkout captures real server validation errors', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const runtimeFailures = watchRuntime(page);
  await addConfiguredChair(page);
  await page.goto('/checkout');

  const form = page.getByTestId('checkout-form');
  await expect(form).toBeVisible();
  await form.evaluate((element) => { element.noValidate = true; });
  await page.getByTestId('confirm-checkout').click();

  await expect(page).toHaveURL(/\/checkout$/);
  const errorSummary = page.getByTestId('checkout-errors');
  await expect(errorSummary).toBeVisible();
  await expect(errorSummary.getByRole('listitem')).not.toHaveCount(0);
  await expect(errorSummary).toContainText('الاسم');

  const metrics = await page.evaluate(() => ({
    documentWidth: document.documentElement.scrollWidth,
    viewportWidth: document.documentElement.clientWidth,
  }));
  expect(metrics.documentWidth).toBeLessThanOrEqual(metrics.viewportWidth + 1);
  expect(runtimeFailures).toEqual([]);

  await page.screenshot({ path: path.join(outputDir, 'checkout-server-validation-errors-390.png'), fullPage: true });
});
