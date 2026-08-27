import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

const outputDir = path.resolve('storage/test-artifacts/secondary-quality');
fs.mkdirSync(outputDir, { recursive: true });

const adminEmail = process.env.S09_ADMIN_EMAIL;
const adminPassword = process.env.S09_ADMIN_PASSWORD;

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

async function loginAdmin(page) {
  if (!adminEmail || !adminPassword) throw new Error('S09 admin CI identity is required.');
  await page.goto('/admin/login');
  await page.getByLabel('البريد الإلكتروني').fill(adminEmail);
  await page.getByLabel('كلمة المرور').fill(adminPassword);
  await page.getByRole('button', { name: 'دخول آمن' }).click();
  await expect(page).toHaveURL(/\/admin\/catalog(?:\?|$)/);
}

test('[QUALITY][SECONDARY] unavailable product option is explicit on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const runtimeFailures = watchRuntime(page);
  await page.goto('/products/olive-velvet-lounge-chair');

  const sand = page.locator('[data-option-key="color"][data-option-value="رملي"]');
  await expect(sand).toHaveAttribute('aria-pressed', 'false');
  await sand.click();
  await expect(sand).toHaveAttribute('aria-pressed', 'true');

  const unavailableFinish = page.locator('[data-option-key="finish"][data-option-value="بلوط طبيعي"]');
  await expect(unavailableFinish).toBeDisabled();
  await expect(page.getByTestId('variant-sku')).toHaveText('BAS-CHAIR-SAND-01');
  await assertNoPageOverflow(page);
  expect(runtimeFailures).toEqual([]);

  await page.screenshot({ path: path.join(outputDir, 'product-unavailable-option-390.png'), fullPage: true });
});

test('[QUALITY][SECONDARY] admin login failure is explicit and runtime-clean', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const runtimeFailures = watchRuntime(page);
  await page.goto('/admin/login');

  await page.getByLabel('البريد الإلكتروني').fill('invalid-admin@example.test');
  await page.getByLabel('كلمة المرور').fill('not-the-ci-password');
  await page.getByRole('button', { name: 'دخول آمن' }).click();

  await expect(page).toHaveURL(/\/admin\/login$/);
  const alert = page.getByRole('alert');
  await expect(alert).toBeVisible();
  await expect(alert).toHaveText('بيانات الدخول غير صحيحة.');
  await assertNoPageOverflow(page);
  expect(runtimeFailures).toEqual([]);

  await page.screenshot({ path: path.join(outputDir, 'admin-login-error-390.png'), fullPage: true });
});

test('[QUALITY][SECONDARY] admin catalog no-results state is explicit on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const runtimeFailures = watchRuntime(page);
  await loginAdmin(page);

  await page.getByLabel('بحث').fill('NO_SUCH_RP01_ADMIN_PRODUCT_001');
  await page.getByRole('button', { name: 'تطبيق' }).click();
  await expect(page).toHaveURL(/\/admin\/catalog\?/);

  const emptyState = page.getByRole('status');
  await expect(emptyState).toBeVisible();
  await expect(emptyState.getByRole('heading', { name: 'لا توجد نتائج مطابقة.' })).toBeVisible();
  await expect(emptyState).toContainText('امسح الفلاتر');
  await expect(page.getByLabel('عدد المنتجات')).toContainText('0');
  await expect(page.getByTestId('catalog-table-wrap')).toHaveCount(0);
  await assertNoPageOverflow(page);
  expect(runtimeFailures).toEqual([]);

  await page.screenshot({ path: path.join(outputDir, 'admin-catalog-no-results-390.png'), fullPage: true });
});
