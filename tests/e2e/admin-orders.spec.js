import { expect, test } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const adminEmail = process.env.S09_ADMIN_EMAIL;
const adminPassword = process.env.S09_ADMIN_PASSWORD;

test.describe.configure({ mode: 'serial' });

async function login(page) {
  if (!adminEmail || !adminPassword) throw new Error('S09_ADMIN_EMAIL and S09_ADMIN_PASSWORD are required for S10 browser evidence.');
  await page.goto('/admin/login');
  await page.getByLabel('البريد الإلكتروني').fill(adminEmail);
  await page.getByLabel('كلمة المرور').fill(adminPassword);
  await page.getByRole('button', { name: 'دخول آمن' }).click();
  await expect(page).toHaveURL(/\/admin\/catalog(?:\?|$)/);
}

async function assertNoPageOverflow(page) {
  const metrics = await page.evaluate(() => ({
    direction: document.documentElement.dir,
    documentWidth: document.documentElement.scrollWidth,
    viewportWidth: document.documentElement.clientWidth,
  }));
  expect(metrics.direction).toBe('rtl');
  expect(metrics.documentWidth).toBeLessThanOrEqual(metrics.viewportWidth);
}

async function capture(page, name) {
  const dir = path.resolve('storage/test-artifacts/s10');
  await fs.mkdir(dir, { recursive: true });
  await page.screenshot({ path: path.join(dir, `${name}.png`), fullPage: true });
}

for (const viewport of [
  { name: '1440', width: 1440, height: 1000 },
  { name: '820', width: 820, height: 1000 },
  { name: '768', width: 768, height: 1024 },
  { name: '430', width: 430, height: 932 },
  { name: '390', width: 390, height: 844 },
]) {
  test(`S10 Admin Orders is RTL and overflow-safe at ${viewport.name}`, async ({ page }) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await login(page);

    await page.getByRole('link', { name: 'الطلبات والمدفوعات والمرتجعات' }).click();
    await expect(page).toHaveURL(/\/admin\/orders(?:\?|$)/);
    await expect(page.getByRole('heading', { name: 'الطلبات والمدفوعات والمرتجعات' })).toBeVisible();
    await expect(page.getByText('رجوع المتصفح أو ادعاء العميل لا يثبت نجاح الدفع')).toBeVisible();
    await assertNoPageOverflow(page);

    const row = page.getByRole('row').filter({ hasText: 'BAS-S10-EVIDENCE' });
    await expect(row).toHaveCount(1);
    await row.getByRole('link', { name: 'فتح' }).click();

    await expect(page).toHaveURL(/\/admin\/orders\/BAS-S10-EVIDENCE$/);
    await expect(page.getByRole('heading', { name: 'الدفع' })).toBeVisible();
    await expect(page.getByText('لا توجد سلطة نجاح دفع في المتصفح.')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'حالات الإرجاع' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'سجلات الاسترداد' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'قيود رصيد المتجر' })).toBeVisible();
    await assertNoPageOverflow(page);
    await capture(page, `admin-orders-payments-returns-${viewport.name}`);
  });
}
