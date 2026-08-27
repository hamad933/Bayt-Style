import { expect, test } from '@playwright/test';

const adminEmail = process.env.S09_ADMIN_EMAIL;
const adminPassword = process.env.S09_ADMIN_PASSWORD;

test.describe.configure({ mode: 'serial' });

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

async function login(page) {
  if (!adminEmail || !adminPassword) {
    throw new Error('S09_ADMIN_EMAIL and S09_ADMIN_PASSWORD are required for admin quality review.');
  }
  await page.goto('/admin/login');
  await page.getByLabel('البريد الإلكتروني').fill(adminEmail);
  await page.getByLabel('كلمة المرور').fill(adminPassword);
  await page.getByRole('button', { name: 'دخول آمن' }).click();
  await expect(page).toHaveURL(/\/admin\/catalog(?:\?|$)/);
}

async function assertAccessibleSurface(page, surface) {
  const defects = await page.evaluate(() => {
    const defects = [];
    const visible = (element) => {
      const style = window.getComputedStyle(element);
      return style.display !== 'none' && style.visibility !== 'hidden' && element.getClientRects().length > 0;
    };
    const labelledByText = (element) => (element.getAttribute('aria-labelledby') || '')
      .split(/\s+/)
      .filter(Boolean)
      .map((id) => document.getElementById(id)?.textContent?.trim() || '')
      .join(' ')
      .trim();
    const labelText = (element) => Array.from(element.labels || [])
      .map((label) => label.textContent?.trim() || '')
      .join(' ')
      .trim();
    const accessibleName = (element) => (
      element.getAttribute('aria-label')?.trim()
      || labelledByText(element)
      || labelText(element)
      || element.getAttribute('title')?.trim()
      || element.textContent?.trim()
      || ''
    );

    if (document.documentElement.lang !== 'ar') defects.push(`html lang=${JSON.stringify(document.documentElement.lang)}`);
    if (document.documentElement.dir !== 'rtl') defects.push(`html dir=${JSON.stringify(document.documentElement.dir)}`);
    if (!document.title.trim()) defects.push('document title is empty');

    const ids = Array.from(document.querySelectorAll('[id]')).map((element) => element.id).filter(Boolean);
    const duplicateIds = [...new Set(ids.filter((id, index) => ids.indexOf(id) !== index))];
    if (duplicateIds.length) defects.push(`duplicate ids: ${duplicateIds.join(', ')}`);

    for (const control of document.querySelectorAll('input, select, textarea')) {
      if (!visible(control) || control.type === 'hidden') continue;
      if (!accessibleName(control)) defects.push(`unnamed form control: ${control.tagName.toLowerCase()}#${control.id || '<no-id>'}`);
    }
    for (const interactive of document.querySelectorAll('button, a[href]')) {
      if (!visible(interactive)) continue;
      if (!accessibleName(interactive)) defects.push(`unnamed interactive element: ${interactive.tagName.toLowerCase()}#${interactive.id || '<no-id>'}`);
    }
    return defects;
  });

  expect(defects, `${surface} accessibility/semantic defects`).toEqual([]);
}

test('[QUALITY][ADMIN] catalog shell supports keyboard entry and accessible semantics', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  const runtimeFailures = watchRuntime(page);
  await login(page);
  await assertAccessibleSurface(page, 'admin catalog');

  const skipLink = page.locator('.skip-link');
  await page.keyboard.press('Tab');
  await expect(skipLink).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('#admin-main')).toBeFocused();

  expect(runtimeFailures, 'admin catalog runtime failures').toEqual([]);
});

test('[QUALITY][ADMIN] order index and detail stay semantic and runtime-clean on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const runtimeFailures = watchRuntime(page);
  await login(page);

  await page.getByRole('link', { name: 'الطلبات والمدفوعات والمرتجعات' }).click();
  await expect(page).toHaveURL(/\/admin\/orders(?:\?|$)/);
  await assertAccessibleSurface(page, 'admin orders index');

  const row = page.getByRole('row').filter({ hasText: 'BAS-S10-EVIDENCE' });
  await expect(row).toHaveCount(1);
  await row.getByRole('link', { name: 'فتح' }).click();
  await expect(page).toHaveURL(/\/admin\/orders\/BAS-S10-EVIDENCE$/);
  await assertAccessibleSurface(page, 'admin order detail');

  expect(runtimeFailures, 'admin orders runtime failures').toEqual([]);
});
