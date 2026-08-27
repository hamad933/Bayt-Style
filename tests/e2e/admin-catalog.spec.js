import { expect, test } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const adminEmail = process.env.S09_ADMIN_EMAIL;
const adminPassword = process.env.S09_ADMIN_PASSWORD;

test.describe.configure({ mode: 'serial' });

async function login(page) {
  if (!adminEmail || !adminPassword) {
    throw new Error('S09_ADMIN_EMAIL and S09_ADMIN_PASSWORD are required for Admin browser evidence.');
  }

  await page.goto('/admin/login');
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  await page.getByLabel('البريد الإلكتروني').fill(adminEmail);
  await page.getByLabel('كلمة المرور').fill(adminPassword);
  await page.getByRole('button', { name: 'دخول آمن' }).click();
  await expect(page).toHaveURL(/\/admin\/catalog(?:\?|$)/);
  await expect(page.getByRole('heading', { name: 'حقيقة الكتالوج الحالية' })).toBeVisible();
}

async function assertNoPageOverflow(page) {
  const metrics = await page.evaluate(() => {
    const viewportWidth = document.documentElement.clientWidth;
    const offenders = [...document.querySelectorAll('body *')]
      .map((element) => {
        const rect = element.getBoundingClientRect();
        return {
          tag: element.tagName.toLowerCase(),
          className: typeof element.className === 'string' ? element.className : '',
          left: Math.round(rect.left),
          right: Math.round(rect.right),
          width: Math.round(rect.width),
          scrollWidth: element.scrollWidth,
          clientWidth: element.clientWidth,
        };
      })
      .filter((item) => item.left < -1 || item.right > viewportWidth + 1)
      .sort((a, b) => (Math.max(-a.left, a.right - viewportWidth) - Math.max(-b.left, b.right - viewportWidth)))
      .slice(-12);

    return {
      direction: document.documentElement.dir,
      documentWidth: document.documentElement.scrollWidth,
      viewportWidth,
      offenders,
    };
  });

  expect(metrics.direction).toBe('rtl');
  expect(metrics.documentWidth, JSON.stringify(metrics, null, 2)).toBeLessThanOrEqual(metrics.viewportWidth);
}

async function capture(page, name) {
  const dir = path.resolve('storage/test-artifacts/s09');
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
  test(`S09 Admin Catalog and Inventory is RTL and overflow-safe at ${viewport.name}`, async ({ page }) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await login(page);
    await assertNoPageOverflow(page);

    await page.getByLabel('بحث').fill('BAS-CHAIR-SAND-01');
    await page.getByRole('button', { name: 'تطبيق' }).click();
    await assertNoPageOverflow(page);

    const matchedRow = page.getByRole('row').filter({ hasText: 'BAS-CHAIR-SAND-01' });
    await expect(matchedRow).toHaveCount(1);
    await expect(matchedRow.getByText('BAS-CHAIR-SAND-01', { exact: true })).toBeVisible();
    await matchedRow.getByRole('link', { name: 'إدارة' }).click();

    await expect(page).toHaveURL(/\/admin\/catalog\/\d+\/edit$/);
    await expect(page.getByRole('heading', { name: 'خيارات البيع والمخزون' })).toBeVisible();
    await expect(page.getByText('التكوين الحالي').first()).toBeVisible();
    await expect(page.getByText('سجل حركة المخزون')).toBeVisible();
    await expect(page.getByText('سجل التدقيق')).toBeVisible();
    await assertNoPageOverflow(page);

    if (viewport.width === 1440) {
      const reason = 'تعديل تجريبي موثق ضمن تحقق المتصفح';
      const adjustmentForm = page.locator('form.admin-form--inventory').first();
      await adjustmentForm.getByLabel('التغيير في الكمية').fill('1');
      await adjustmentForm.getByLabel('سبب التعديل').fill(reason);
      await adjustmentForm.getByRole('button', { name: 'تسجيل حركة المخزون' }).click();
      await expect(page.getByText('تم تسجيل حركة المخزون وتحديث الرصيد الحالي.')).toBeVisible();
      await expect(page.getByRole('region', { name: 'سجل حركة المخزون' }).getByText(reason)).toBeVisible();
      await expect(page.getByRole('region', { name: 'سجل التدقيق' }).getByText(reason)).toBeVisible();
      await assertNoPageOverflow(page);
    }

    await capture(page, `admin-catalog-inventory-${viewport.name}`);
  });
}
